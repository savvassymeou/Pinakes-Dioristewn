<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$username = trim((string) ($_GET['username'] ?? $_POST['username'] ?? ''));

if ($username === '') {
    echo json_encode(['available' => false, 'message' => 'Συμπλήρωσε πρώτα username.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_valid_username_format($username)) {
    echo json_encode(['available' => false, 'message' => username_validation_message()], JSON_UNESCAPED_UNICODE);
    exit;
}

$exists = username_exists($conn, $username);

echo json_encode([
    'available' => !$exists,
    'message' => $exists ? 'Το username χρησιμοποιείται ήδη.' : 'Το username είναι διαθέσιμο.'
], JSON_UNESCAPED_UNICODE);
