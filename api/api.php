<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

function respondJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$endpoint = $_GET['endpoint'] ?? '';

if ($endpoint === 'specialties') {
    $items = [];
    $stmt = $conn->prepare('SELECT id, title, description FROM specialties ORDER BY title ASC');

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }

        $stmt->close();
    }

    respondJson([
        'endpoint' => 'specialties',
        'count' => count($items),
        'data' => $items,
    ]);
}

if ($endpoint === 'candidates') {
    $name = trim($_GET['name'] ?? '');
    $specialtyId = (int) ($_GET['specialty_id'] ?? 0);

    $sql = '
        SELECT
            cp.id,
            up.first_name,
            up.last_name,
            s.title AS specialty,
            cp.ranking_position,
            cp.points,
            cp.application_status
        FROM candidate_profiles cp
        INNER JOIN users u ON u.id = cp.user_id
        INNER JOIN user_profiles up ON up.user_id = u.id
        LEFT JOIN specialties s ON s.id = cp.specialty_id
        WHERE 1=1
    ';

    $types = '';
    $params = [];

    if ($name !== '') {
        $sql .= " AND CONCAT(up.first_name, ' ', up.last_name) LIKE ?";
        $types .= 's';
        $params[] = '%' . $name . '%';
    }

    if ($specialtyId > 0) {
        $sql .= ' AND cp.specialty_id = ?';
        $types .= 'i';
        $params[] = $specialtyId;
    }

    $sql .= ' ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, up.last_name ASC LIMIT 50';

    $stmt = $conn->prepare($sql);
    $items = [];

    if ($stmt) {
        if ($params !== []) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }

        $stmt->close();
    }

    respondJson([
        'endpoint' => 'candidates',
        'filters' => [
            'name' => $name,
            'specialty_id' => $specialtyId,
        ],
        'count' => count($items),
        'data' => $items,
    ]);
}

if ($endpoint === 'stats') {
    $specialtyId = (int) ($_GET['specialty_id'] ?? 0);

    if ($specialtyId <= 0) {
        respondJson([
            'error' => '??se specialty_id ??a t? endpoint stats.',
        ], 400);
    }

    $specialtyStmt = $conn->prepare('SELECT id, title, description FROM specialties WHERE id = ? LIMIT 1');
    $specialty = null;

    if ($specialtyStmt) {
        $specialtyStmt->bind_param('i', $specialtyId);
        $specialtyStmt->execute();
        $specialtyResult = $specialtyStmt->get_result();
        $specialty = $specialtyResult ? $specialtyResult->fetch_assoc() : null;
        $specialtyStmt->close();
    }

    if (!$specialty) {
        respondJson([
            'error' => '? e?d???t?ta de? ß?????e.',
        ], 404);
    }

    $summary = [
        'candidate_count' => 0,
        'average_age' => null,
        'average_points' => null,
    ];

    $summaryStmt = $conn->prepare('
        SELECT
            COUNT(*) AS candidate_count,
            AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) AS average_age,
            AVG(points) AS average_points
        FROM candidate_profiles
        WHERE specialty_id = ?
    ');

    if ($summaryStmt) {
        $summaryStmt->bind_param('i', $specialtyId);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        $summaryRow = $summaryResult ? $summaryResult->fetch_assoc() : null;

        if ($summaryRow) {
            $summary = $summaryRow;
        }

        $summaryStmt->close();
    }

    $yearly = [];
    $yearlyStmt = $conn->prepare('
        SELECT
            YEAR(created_at) AS report_year,
            COUNT(*) AS candidate_count
        FROM candidate_profiles
        WHERE specialty_id = ?
        GROUP BY YEAR(created_at)
        ORDER BY report_year ASC
    ');

    if ($yearlyStmt) {
        $yearlyStmt->bind_param('i', $specialtyId);
        $yearlyStmt->execute();
        $yearlyResult = $yearlyStmt->get_result();

        if ($yearlyResult) {
            while ($row = $yearlyResult->fetch_assoc()) {
                $yearly[] = $row;
            }
        }

        $yearlyStmt->close();
    }

    respondJson([
        'endpoint' => 'stats',
        'specialty' => $specialty,
        'summary' => $summary,
        'yearly' => $yearly,
    ]);
}

$pageTitle = APP_NAME . ' | API Module';
$bodyClass = 'theme-api';
$currentPage = 'api';
$navBase = '../';
$headerActionLabel = 'Endpoints';
$headerActionHref = '#endpoints';

require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-hero" aria-labelledby="apiTitle">
        <div class="hero-text">
            <h1 id="apiTitle">API Module</h1>
            <p class="muted">
                ?? API p??sf??e? ßas??? JSON endpoints p??? sta ded?µ??a t?? efa?µ???? ??a specialties,
                candidates ?a? stats a?? e?d???t?ta.
            </p>
            <p class="muted">
                ? ?????? t?? module e??a? ?a ep?t??pe? se t??ta s?st?µata ?a a?t???? p????f???e? ap? t?? ?d?a ß?s?,
                ????? ?a ??e???eta? ?a µp??? ap? t? ??af??? pe??ß????? t?? efa?µ????.
            </p>
        </div>
    </section>

    <section class="panel" id="endpoints" aria-labelledby="endpointsTitle">
        <div class="panel-head">
            <h2 id="endpointsTitle">??a??s?µa Endpoints</h2>
            <p class="muted">?????? ta ap? browser ? ???es? ta ap? ???? efa?µ??? µe query parameter `endpoint`.</p>
        </div>

        <div class="code-card">
            <h3>`GET /API/api.php?endpoint=specialties`</h3>
            <pre><code>?p?st??fe? ??e? t?? e?d???t?te?.</code></pre>
        </div>

        <div class="code-card">
            <h3>`GET /API/api.php?endpoint=candidates&amp;name=...&amp;specialty_id=...`</h3>
            <pre><code>?p?st??fe? ap?te??sµata ?p???f??? µe f??t?a.</code></pre>
        </div>

        <div class="code-card">
            <h3>`GET /API/api.php?endpoint=stats&amp;specialty_id=1`</h3>
            <pre><code>?p?st??fe? s???pt??? ?a? et?s?a stat?st??? ??a µ?a e?d???t?ta.</code></pre>
        </div>
    </section>

    <section class="panel" aria-labelledby="notesTitle">
        <div class="panel-head">
            <h2 id="notesTitle">?a??de??µa ???s??</h2>
        </div>
        <div class="year-list">
            <div class="year-item"><span>Specialties</span><strong><a href="./api.php?endpoint=specialties">?????µa JSON</a></strong></div>
            <div class="year-item"><span>Candidates</span><strong><a href="./api.php?endpoint=candidates">?????µa JSON</a></strong></div>
            <div class="year-item"><span>Stats</span><strong><a href="./api.php?endpoint=stats&amp;specialty_id=1">?????µa JSON</a></strong></div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>





