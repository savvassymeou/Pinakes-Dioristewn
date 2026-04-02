<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$username = trim((string) ($_GET['username'] ?? $_POST['username'] ?? ''));

if ($username === '') {
    echo json_encode([
        'available' => false,
        'message' => u('\u03A3\u03C5\u03BC\u03C0\u03BB\u03AE\u03C1\u03C9\u03C3\u03B5 \u03C0\u03C1\u03CE\u03C4\u03B1 username.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_valid_username_format($username)) {
    echo json_encode([
        'available' => false,
        'message' => username_validation_message()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$exists = username_exists($conn, $username);

echo json_encode([
    'available' => !$exists,
    'message' => $exists
        ? u('\u03A4\u03BF username \u03C7\u03C1\u03B7\u03C3\u03B9\u03BC\u03BF\u03C0\u03BF\u03B9\u03B5\u03AF\u03C4\u03B1\u03B9 \u03AE\u03B4\u03B7.')
        : u('\u03A4\u03BF username \u03B5\u03AF\u03BD\u03B1\u03B9 \u03B4\u03B9\u03B1\u03B8\u03AD\u03C3\u03B9\u03BC\u03BF.')
], JSON_UNESCAPED_UNICODE);
