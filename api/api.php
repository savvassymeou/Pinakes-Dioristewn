<?php
declare(strict_types=1);

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

if ($endpoint === '') {
    respondJson([
        'module' => 'api',
        'description' => 'Technical JSON endpoints for third-party systems.',
        'endpoints' => [
            [
                'name' => 'specialties',
                'method' => 'GET',
                'url' => 'api/api.php?endpoint=specialties',
                'description' => 'Returns all available specialties.',
            ],
            [
                'name' => 'candidates',
                'method' => 'GET',
                'url' => 'api/api.php?endpoint=candidates&name=&specialty_id=0&year=0&order=rank_asc',
                'description' => 'Returns candidates with optional filters.',
                'filters' => [
                    'name' => 'Optional full-name search term.',
                    'specialty_id' => 'Optional specialty id.',
                    'year' => 'Optional registration year.',
                    'order' => 'rank_asc, name_asc, points_desc, recent_desc.',
                ],
            ],
            [
                'name' => 'stats',
                'method' => 'GET',
                'url' => 'api/api.php?endpoint=stats&specialty_id=1',
                'description' => 'Returns summary, yearly and period statistics for one specialty.',
                'required' => ['specialty_id'],
            ],
        ],
    ]);
}

if ($endpoint === 'specialties') {
    $items = [];
    $stmt = $conn->prepare('SELECT id, title, description FROM specialties ORDER BY title ASC');

    if (!$stmt) {
        respondJson([
            'error' => 'database_error',
            'message' => 'Could not prepare specialties query.',
        ], 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $stmt->close();

    respondJson([
        'endpoint' => 'specialties',
        'count' => count($items),
        'data' => $items,
    ]);
}

if ($endpoint === 'candidates') {
    $name = trim((string) ($_GET['name'] ?? ''));
    $specialtyId = max(0, (int) ($_GET['specialty_id'] ?? 0));
    $year = max(0, (int) ($_GET['year'] ?? 0));
    $order = (string) ($_GET['order'] ?? 'rank_asc');
    $allowedOrders = ['rank_asc', 'name_asc', 'points_desc', 'recent_desc'];

    if (!in_array($order, $allowedOrders, true)) {
        $order = 'rank_asc';
    }

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
        respondJson([
            'error' => 'database_error',
            'message' => 'Could not prepare candidates query.',
        ], 500);
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
            'error' => 'missing_specialty_id',
            'message' => 'Provide specialty_id for the stats endpoint.',
        ], 400);
    }

    $specialtyStmt = $conn->prepare('SELECT id, title, description FROM specialties WHERE id = ? LIMIT 1');
    if (!$specialtyStmt) {
        respondJson([
            'error' => 'database_error',
            'message' => 'Could not prepare specialty query.',
        ], 500);
    }

    $specialtyStmt->bind_param('i', $specialtyId);
    $specialtyStmt->execute();
    $specialtyResult = $specialtyStmt->get_result();
    $specialty = $specialtyResult ? $specialtyResult->fetch_assoc() : null;
    $specialtyStmt->close();

    if (!$specialty) {
        respondJson([
            'error' => 'specialty_not_found',
            'message' => 'The requested specialty was not found.',
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

    if (!$summaryStmt) {
        respondJson([
            'error' => 'database_error',
            'message' => 'Could not prepare stats summary query.',
        ], 500);
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
        respondJson([
            'error' => 'database_error',
            'message' => 'Could not prepare yearly stats query.',
        ], 500);
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
        respondJson([
            'error' => 'database_error',
            'message' => 'Could not prepare period stats query.',
        ], 500);
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

    respondJson([
        'endpoint' => 'stats',
        'specialty' => $specialty,
        'summary' => $summary,
        'yearly' => $yearly,
        'periods' => $periods,
    ]);
}

respondJson([
    'error' => 'unknown_endpoint',
    'message' => 'Unknown endpoint. Call api/api.php without endpoint to see available endpoints.',
    'available_endpoints' => ['specialties', 'candidates', 'stats'],
], 404);
