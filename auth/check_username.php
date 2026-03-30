<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

header("Content-Type: application/json; charset=UTF-8");

$username = trim($_GET["username"] ?? "");

if ($username === "") {
    echo json_encode([
        "valid" => false,
        "available" => false,
        "message" => "Συμπλήρωσε username.",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_valid_username_format($username)) {
    echo json_encode([
        "valid" => false,
        "available" => false,
        "message" => username_validation_message(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");

if (!$stmt) {
    echo json_encode([
        "valid" => true,
        "available" => false,
        "message" => "Δεν ήταν δυνατός ο έλεγχος του username αυτή τη στιγμή.",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$available = $stmt->num_rows === 0;
$stmt->close();

echo json_encode([
    "valid" => true,
    "available" => $available,
    "message" => $available
        ? "Το username είναι διαθέσιμο."
        : "Το username χρησιμοποιείται ήδη.",
], JSON_UNESCAPED_UNICODE);
