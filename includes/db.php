<?php

mysqli_report(MYSQLI_REPORT_OFF);

$db_host = "localhost";
$db_name = "pinakes_dioristewn";
$db_user = "root";
$db_pass = "";
$db_port = 3306;

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    die(
        "<!DOCTYPE html>
        <html lang='el'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Sf??µa s??des?? ß?s??</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f4f6f8;
                    margin: 0;
                    padding: 40px 20px;
                    color: #1f2937;
                }
                .error-box {
                    max-width: 720px;
                    margin: 0 auto;
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-left: 6px solid #dc2626;
                    border-radius: 10px;
                    padding: 24px;
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
                }
                h1 {
                    margin-top: 0;
                    color: #b91c1c;
                }
                p {
                    line-height: 1.6;
                }
                code {
                    background: #f3f4f6;
                    padding: 2px 6px;
                    border-radius: 4px;
                }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h1>?e? ?p???e? s??des? µe t? ß?s? ded?µ????</h1>
                <p>? efa?µ??? de? µp??e? ?a s??de?e? st? MySQL. ??e??e a? t???e? ? ß?s? ap? t? XAMPP.</p>
                <p>?????e t? <code>XAMPP Control Panel</code> ?a? p?t?se <code>Start</code> st? <code>MySQL</code>.</p>
            </div>
        </body>
        </html>"
    );
}

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    http_response_code(500);
    die("? mysqli s??des? ap?t??e: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, "UTF-8"));
}

$conn->set_charset("utf8mb4");
