<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

function respondJson(array $payload, int $status = 200, array $headers = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function respondApiSuccess(string $endpoint, array $data = [], array $meta = [], int $status = 200): never
{
    respondJson([
        'success' => true,
        'status' => $status,
        'endpoint' => $endpoint,
        'meta' => $meta === [] ? new stdClass() : $meta,
        'data' => $data,
    ], $status);
}

function respondApiError(string $code, string $message, int $status, array $extra = [], array $headers = []): never
{
    respondJson(array_merge([
        'success' => false,
        'status' => $status,
        'error' => $code,
        'message' => $message,
    ], $extra), $status, $headers);
}

function apiDocs(): array
{
    return [
        [
            'name' => 'specialties',
            'method' => 'GET',
            'url' => 'api/api.php?endpoint=specialties',
            'description' => 'Επιστρέφει όλες τις διαθέσιμες ειδικότητες.',
            'query' => [],
            'success_codes' => [200],
            'error_codes' => [500],
            'response_fields' => ['id', 'title', 'description'],
            'example' => 'api/api.php?endpoint=specialties',
        ],
        [
            'name' => 'candidates',
            'method' => 'GET',
            'url' => 'api/api.php?endpoint=candidates&name=Μαρία&specialty_id=1&year=2024&order=points_desc',
            'description' => 'Επιστρέφει υποψηφίους με φίλτρα και ταξινόμηση.',
            'query' => [
                'name' => 'Προαιρετικό όνομα ή ονοματεπώνυμο.',
                'specialty_id' => 'Προαιρετικό numeric id ειδικότητας.',
                'year' => 'Προαιρετικό έτος λίστας.',
                'order' => 'rank_asc, name_asc, points_desc, recent_desc.',
            ],
            'success_codes' => [200],
            'error_codes' => [400, 500],
            'response_fields' => ['id', 'first_name', 'last_name', 'specialty', 'ranking_position', 'points', 'application_status', 'list_year', 'list_period'],
            'example' => 'api/api.php?endpoint=candidates&name=Μαρία&specialty_id=1&year=2024&order=points_desc',
        ],
        [
            'name' => 'stats',
            'method' => 'GET',
            'url' => 'api/api.php?endpoint=stats&specialty_id=1',
            'description' => 'Επιστρέφει σύνοψη, στατιστικά ανά έτος και ανά περίοδο για μία ειδικότητα.',
            'query' => [
                'specialty_id' => 'Υποχρεωτικό numeric id ειδικότητας.',
            ],
            'success_codes' => [200],
            'error_codes' => [400, 404, 500],
            'response_fields' => ['specialty', 'summary', 'yearly', 'periods'],
            'example' => 'api/api.php?endpoint=stats&specialty_id=1',
        ],
    ];
}

function renderApiDocumentation(array $docs): never
{
    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> | API Documentation</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --text: #14263d;
            --muted: #5d7088;
            --accent: #b8862f;
            --border: rgba(21, 55, 92, 0.10);
            --shadow: 0 10px 30px rgba(17, 39, 68, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(180deg, #f9fbfd 0%, #f2f6fb 100%);
            color: var(--text);
        }

        a { color: inherit; }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .hero,
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .hero {
            padding: 28px;
            margin-bottom: 20px;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.05;
        }

        .hero p,
        .muted {
            color: var(--muted);
            line-height: 1.65;
        }

        .hero-actions,
        .chips,
        .meta-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .hero-actions {
            margin-top: 18px;
        }

        .btn,
        .chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid var(--border);
            background: #fff;
            font-weight: 700;
        }

        .btn-primary {
            background: linear-gradient(135deg, #c59335, #e0ad49);
            border-color: transparent;
            color: #fff;
        }

        .layout {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            padding: 22px;
        }

        .card h2,
        .card h3 {
            margin: 0 0 10px;
        }

        .card h2 {
            font-size: 1.2rem;
        }

        .card h3 {
            font-size: 1.1rem;
        }

        code,
        pre {
            font-family: Consolas, Monaco, monospace;
        }

        pre {
            margin: 12px 0 0;
            padding: 14px;
            overflow: auto;
            border-radius: 16px;
            background: #10243f;
            color: #eef4ff;
        }

        ul {
            margin: 10px 0 0;
            padding-left: 18px;
        }

        li + li {
            margin-top: 6px;
        }

        .endpoint-grid {
            display: grid;
            gap: 16px;
            margin-top: 18px;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(184, 134, 47, 0.12);
            color: #8a611f;
            font-size: 0.85rem;
            font-weight: 800;
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="hero">
            <div class="chips">
                <span class="chip">JSON API</span>
                <span class="chip">GET Endpoints</span>
                <span class="chip">UTF-8 Responses</span>
            </div>
            <h1>API Documentation</h1>
            <p>Η σελίδα αυτή τεκμηριώνει τα διαθέσιμα endpoints του project. Όλα τα endpoints επιστρέφουν JSON, χρησιμοποιούν UTF-8 και δίνουν σαφή status codes για έγκυρα και μη έγκυρα requests.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="../index.php">Επιστροφή στην αρχική</a>
                <a class="btn" href="api.php?format=json">JSON index</a>
            </div>
        </section>

        <section class="layout">
            <article class="card">
                <h2>Γενικοί Κανόνες</h2>
                <ul>
                    <li>Υποστηρίζεται μόνο `GET` στα διαθέσιμα endpoints.</li>
                    <li>Επιτυχής απόκριση: `success: true` με `status`, `endpoint`, `meta`, `data`.</li>
                    <li>Αποτυχία: `success: false` με `error`, `message` και κατάλληλο status code.</li>
                </ul>
            </article>
            <article class="card">
                <h2>Συνηθισμένα Status Codes</h2>
                <ul>
                    <li>`200` επιτυχής απόκριση.</li>
                    <li>`400` μη έγκυρα query params.</li>
                    <li>`404` άγνωστο endpoint ή ανύπαρκτη ειδικότητα.</li>
                    <li>`405` μη υποστηριζόμενο HTTP method.</li>
                    <li>`500` αποτυχία query ή προετοιμασίας statement.</li>
                </ul>
            </article>
            <article class="card">
                <h2>Δομή Απόκρισης</h2>
<pre>{
  "success": true,
  "status": 200,
  "endpoint": "specialties",
  "meta": {},
  "data": []
}</pre>
            </article>
        </section>

        <section class="endpoint-grid">
            <?php foreach ($docs as $doc): ?>
                <article class="card">
                    <div class="meta-list">
                        <span class="tag"><?php echo h($doc['method']); ?></span>
                        <span class="tag"><?php echo h($doc['name']); ?></span>
                    </div>
                    <h3><?php echo h($doc['url']); ?></h3>
                    <p class="muted"><?php echo h($doc['description']); ?></p>

                    <strong>Query parameters</strong>
                    <?php if ($doc['query'] === []): ?>
                        <p class="muted">Δεν υπάρχουν query parameters.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($doc['query'] as $name => $description): ?>
                                <li><code><?php echo h($name); ?></code>: <?php echo h($description); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <strong>Response fields</strong>
                    <ul>
                        <?php foreach ($doc['response_fields'] as $field): ?>
                            <li><code><?php echo h($field); ?></code></li>
                        <?php endforeach; ?>
                    </ul>

                    <strong>Success codes</strong>
                    <p class="muted"><?php echo h(implode(', ', array_map('strval', $doc['success_codes']))); ?></p>

                    <strong>Error codes</strong>
                    <p class="muted"><?php echo h(implode(', ', array_map('strval', $doc['error_codes']))); ?></p>

                    <strong>Example request</strong>
                    <pre><?php echo h($doc['example']); ?></pre>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
<?php
    exit;
}

function docsIndexPayload(array $docs): array
{
    return [
        'module' => 'api',
        'description' => 'JSON endpoints for candidate and specialty data.',
        'endpoints' => $docs,
    ];
}

function requireGetMethod(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET') {
        respondApiError(
            'method_not_allowed',
            'Only GET requests are supported for this API module.',
            405,
            ['allowed_methods' => ['GET']],
            ['Allow' => 'GET']
        );
    }
}

function readPositiveIntQuery(string $key, bool $required = false): int
{
    $raw = trim((string) ($_GET[$key] ?? ''));
    if ($raw === '') {
        if ($required) {
            respondApiError('missing_parameter', 'Missing required parameter: ' . $key . '.', 400, ['parameter' => $key]);
        }
        return 0;
    }

    if (!ctype_digit($raw)) {
        respondApiError('invalid_parameter', 'Parameter ' . $key . ' must be a positive integer.', 400, ['parameter' => $key]);
    }

    return (int) $raw;
}

requireGetMethod();

$docs = apiDocs();
$endpoint = trim((string) ($_GET['endpoint'] ?? ''));
$format = trim((string) ($_GET['format'] ?? 'html'));

if ($endpoint === '') {
    if ($format === 'json') {
        respondApiSuccess('index', docsIndexPayload($docs), ['documentation' => true]);
    }
    renderApiDocumentation($docs);
}

if ($endpoint === 'specialties') {
    $items = [];
    $stmt = $conn->prepare('SELECT id, title, description FROM specialties ORDER BY title ASC');

    if (!$stmt) {
        respondApiError('database_error', 'Could not prepare specialties query.', 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $stmt->close();

    respondApiSuccess('specialties', $items, ['count' => count($items)]);
}

if ($endpoint === 'candidates') {
    $name = trim((string) ($_GET['name'] ?? ''));
    $specialtyId = readPositiveIntQuery('specialty_id');
    $year = readPositiveIntQuery('year');
    $order = trim((string) ($_GET['order'] ?? 'rank_asc'));
    $allowedOrders = ['rank_asc', 'name_asc', 'points_desc', 'recent_desc'];

    if (!in_array($order, $allowedOrders, true)) {
        respondApiError(
            'invalid_order',
            'Parameter order must be one of: rank_asc, name_asc, points_desc, recent_desc.',
            400,
            ['allowed_values' => $allowedOrders]
        );
    }

    $orderSql = match ($order) {
        'name_asc' => 'up.last_name ASC, up.first_name ASC',
        'points_desc' => 'cp.points IS NULL, cp.points DESC, up.last_name ASC, up.first_name ASC',
        'recent_desc' => 'cp.created_at DESC, up.last_name ASC, up.first_name ASC',
        default => 'cp.ranking_position IS NULL, cp.ranking_position ASC, up.last_name ASC, up.first_name ASC',
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
            CASE WHEN MONTH(cp.created_at) BETWEEN 1 AND 6 THEN "A" ELSE "B" END AS list_period
         FROM candidate_profiles cp
         INNER JOIN users u ON u.id = cp.user_id
         INNER JOIN user_profiles up ON up.user_id = u.id
         LEFT JOIN specialties s ON s.id = cp.specialty_id
         WHERE (? = "" OR up.first_name LIKE ? OR up.last_name LIKE ? OR CONCAT(up.first_name, " ", up.last_name) LIKE ? OR CONCAT(up.last_name, " ", up.first_name) LIKE ?)
           AND (? = 0 OR cp.specialty_id = ?)
           AND (? = 0 OR YEAR(cp.created_at) = ?)
         ORDER BY ' . $orderSql . '
         LIMIT 50'
    );

    if (!$stmt) {
        respondApiError('database_error', 'Could not prepare candidates query.', 500);
    }

    $items = [];
    $nameTerm = '%' . $name . '%';
    $stmt->bind_param(
        'sssssiiii',
        $name,
        $nameTerm,
        $nameTerm,
        $nameTerm,
        $nameTerm,
        $specialtyId,
        $specialtyId,
        $year,
        $year
    );
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $stmt->close();

    respondApiSuccess('candidates', $items, [
        'count' => count($items),
        'filters' => [
            'name' => $name,
            'specialty_id' => $specialtyId,
            'year' => $year,
            'order' => $order,
        ],
    ]);
}

if ($endpoint === 'stats') {
    $specialtyId = readPositiveIntQuery('specialty_id', true);

    $specialtyStmt = $conn->prepare('SELECT id, title, description FROM specialties WHERE id = ? LIMIT 1');
    if (!$specialtyStmt) {
        respondApiError('database_error', 'Could not prepare specialty query.', 500);
    }

    $specialtyStmt->bind_param('i', $specialtyId);
    $specialtyStmt->execute();
    $specialtyResult = $specialtyStmt->get_result();
    $specialty = $specialtyResult ? $specialtyResult->fetch_assoc() : null;
    $specialtyStmt->close();

    if (!$specialty) {
        respondApiError('specialty_not_found', 'The requested specialty was not found.', 404);
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

    if (!$summaryStmt) {
        respondApiError('database_error', 'Could not prepare stats summary query.', 500);
    }

    $summaryStmt->bind_param('i', $specialtyId);
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    $summaryRow = $summaryResult ? $summaryResult->fetch_assoc() : null;

    if ($summaryRow) {
        $summary = $summaryRow;
    }

    $summaryStmt->close();

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

    if (!$yearlyStmt) {
        respondApiError('database_error', 'Could not prepare yearly stats query.', 500);
    }

    $yearlyStmt->bind_param('i', $specialtyId);
    $yearlyStmt->execute();
    $yearlyResult = $yearlyStmt->get_result();

    if ($yearlyResult) {
        while ($row = $yearlyResult->fetch_assoc()) {
            $yearly[] = $row;
        }
    }

    $yearlyStmt->close();

    $periods = [];
    $periodStmt = $conn->prepare(
        'SELECT
            YEAR(created_at) AS report_year,
            CASE WHEN MONTH(created_at) BETWEEN 1 AND 6 THEN "A" ELSE "B" END AS report_period,
            COUNT(*) AS candidate_count,
            AVG(points) AS average_points
         FROM candidate_profiles
         WHERE specialty_id = ?
         GROUP BY YEAR(created_at), CASE WHEN MONTH(created_at) BETWEEN 1 AND 6 THEN "A" ELSE "B" END
         ORDER BY report_year ASC, report_period ASC'
    );

    if (!$periodStmt) {
        respondApiError('database_error', 'Could not prepare period stats query.', 500);
    }

    $periodStmt->bind_param('i', $specialtyId);
    $periodStmt->execute();
    $periodResult = $periodStmt->get_result();

    if ($periodResult) {
        while ($row = $periodResult->fetch_assoc()) {
            $periods[] = $row;
        }
    }

    $periodStmt->close();

    respondApiSuccess('stats', [
        'specialty' => $specialty,
        'summary' => $summary,
        'yearly' => $yearly,
        'periods' => $periods,
    ]);
}

respondApiError(
    'unknown_endpoint',
    'Unknown endpoint. Open api/api.php to view the API documentation page.',
    404,
    ['available_endpoints' => array_map(static fn(array $doc): string => $doc['name'], $docs)]
);
