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

$endpoint = trim((string) ($_GET['endpoint'] ?? ''));

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
    $name = trim((string) ($_GET['name'] ?? ''));
    $specialtyId = (int) ($_GET['specialty_id'] ?? 0);
    $year = (int) ($_GET['year'] ?? 0);
    $order = (string) ($_GET['order'] ?? 'rank_asc');
    $nameTerm = '%' . $name . '%';

    $orderSql = match ($order) {
        'name_asc' => 'up.last_name ASC, up.first_name ASC',
        'points_desc' => 'cp.points IS NULL, cp.points DESC, up.last_name ASC',
        'recent_desc' => 'cp.created_at DESC, up.last_name ASC',
        default => 'cp.ranking_position IS NULL, cp.ranking_position ASC, up.last_name ASC',
    };

    $stmt = $conn->prepare(
        'SELECT
            cp.id,
            up.first_name,
            up.last_name,
            s.title AS specialty,
            cp.ranking_position,
            cp.points,
            cp.application_status,
            YEAR(cp.created_at) AS list_year,
            CASE WHEN MONTH(cp.created_at) BETWEEN 1 AND 6 THEN "Α" ELSE "Β" END AS list_period
         FROM candidate_profiles cp
         INNER JOIN users u ON u.id = cp.user_id
         INNER JOIN user_profiles up ON up.user_id = u.id
         LEFT JOIN specialties s ON s.id = cp.specialty_id
         WHERE (? = "" OR CONCAT(up.first_name, " ", up.last_name) LIKE ?)
           AND (? = 0 OR cp.specialty_id = ?)
           AND (? = 0 OR YEAR(cp.created_at) = ?)
         ORDER BY ' . $orderSql . '
         LIMIT 50'
    );

    $items = [];

    if ($stmt) {
        $stmt->bind_param('ssiiii', $name, $nameTerm, $specialtyId, $specialtyId, $year, $year);
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
            'year' => $year,
            'order' => $order,
        ],
        'count' => count($items),
        'data' => $items,
    ]);
}

if ($endpoint === 'stats') {
    $specialtyId = (int) ($_GET['specialty_id'] ?? 0);

    if ($specialtyId <= 0) {
        respondJson([
            'error' => 'Δώσε specialty_id για το endpoint stats.',
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
            'error' => 'Η ειδικότητα δεν βρέθηκε.',
        ], 404);
    }

    $summary = [
        'candidate_count' => 0,
        'average_age' => null,
        'average_points' => null,
    ];

    $summaryStmt = $conn->prepare(
        'SELECT
            COUNT(*) AS candidate_count,
            AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) AS average_age,
            AVG(points) AS average_points
         FROM candidate_profiles
         WHERE specialty_id = ?'
    );

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
    $yearlyStmt = $conn->prepare(
        'SELECT
            YEAR(created_at) AS report_year,
            COUNT(*) AS candidate_count,
            AVG(points) AS average_points
         FROM candidate_profiles
         WHERE specialty_id = ?
         GROUP BY YEAR(created_at)
         ORDER BY report_year ASC'
    );

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

    $periods = [];
    $periodStmt = $conn->prepare(
        'SELECT
            YEAR(created_at) AS report_year,
            CASE WHEN MONTH(created_at) BETWEEN 1 AND 6 THEN "Α" ELSE "Β" END AS report_period,
            COUNT(*) AS candidate_count,
            AVG(points) AS average_points
         FROM candidate_profiles
         WHERE specialty_id = ?
         GROUP BY YEAR(created_at), CASE WHEN MONTH(created_at) BETWEEN 1 AND 6 THEN "Α" ELSE "Β" END
         ORDER BY report_year ASC, report_period ASC'
    );

    if ($periodStmt) {
        $periodStmt->bind_param('i', $specialtyId);
        $periodStmt->execute();
        $periodResult = $periodStmt->get_result();

        if ($periodResult) {
            while ($row = $periodResult->fetch_assoc()) {
                $periods[] = $row;
            }
        }

        $periodStmt->close();
    }

    respondJson([
        'endpoint' => 'stats',
        'specialty' => $specialty,
        'summary' => $summary,
        'yearly' => $yearly,
        'periods' => $periods,
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
                Το API προσφέρει JSON endpoints για ειδικότητες, υποψηφίους και στατιστικά ανά ειδικότητα.
                Έτσι ένα τρίτο σύστημα μπορεί να αντλεί δεδομένα από την ίδια βάση χωρίς να χρησιμοποιεί το γραφικό περιβάλλον.
            </p>
        </div>
    </section>

    <section class="panel" id="endpoints" aria-labelledby="endpointsTitle">
        <div class="panel-head">
            <h2 id="endpointsTitle">Διαθέσιμα Endpoints</h2>
            <p class="muted">Τα endpoints επιστρέφουν UTF-8 JSON και υποστηρίζουν βασικά φίλτρα αναζήτησης.</p>
        </div>

        <div class="code-card">
            <h3>GET /api/api.php?endpoint=specialties</h3>
            <pre><code>Επιστρέφει όλες τις ειδικότητες.</code></pre>
        </div>

        <div class="code-card">
            <h3>GET /api/api.php?endpoint=candidates&amp;name=...&amp;specialty_id=...&amp;year=...&amp;order=...</h3>
            <pre><code>Επιστρέφει υποψηφίους με φίλτρα ονόματος, ειδικότητας, έτους και ταξινόμησης.</code></pre>
        </div>

        <div class="code-card">
            <h3>GET /api/api.php?endpoint=stats&amp;specialty_id=1</h3>
            <pre><code>Επιστρέφει σύνοψη, μέσους όρους, στατιστικά ανά έτος και ανά περίοδο.</code></pre>
        </div>
    </section>

    <section class="panel" aria-labelledby="notesTitle">
        <div class="panel-head">
            <h2 id="notesTitle">Παραδείγματα Χρήσης</h2>
        </div>
        <div class="year-list">
            <div class="year-item"><span>Specialties</span><strong><a href="./api.php?endpoint=specialties">Άνοιγμα JSON</a></strong></div>
            <div class="year-item"><span>Candidates</span><strong><a href="./api.php?endpoint=candidates&amp;order=points_desc">Άνοιγμα JSON</a></strong></div>
            <div class="year-item"><span>Stats</span><strong><a href="./api.php?endpoint=stats&amp;specialty_id=1">Άνοιγμα JSON</a></strong></div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
