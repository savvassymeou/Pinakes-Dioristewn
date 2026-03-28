<?php

declare(strict_types=1);

require_once __DIR__ . "/config.php";

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function h(?string $value): string
{
    return e($value);
}

function normalize_username(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]+/', '', $value) ?? '';

    return $value;
}

function username_from_email(string $email): string
{
    $localPart = strstr($email, '@', true);
    $base = normalize_username($localPart !== false ? $localPart : $email);

    return $base !== '' ? $base : 'user';
}

function username_exists($conn, string $username, ?int $ignoreUserId = null): bool
{
    $sql = 'SELECT id FROM users WHERE username = ?';
    if ($ignoreUserId !== null) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($ignoreUserId !== null) {
        $stmt->bind_param('si', $username, $ignoreUserId);
    } else {
        $stmt->bind_param('s', $username);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function generate_unique_username($conn, string $base, ?int $ignoreUserId = null): string
{
    $base = normalize_username($base);
    if ($base === '') {
        $base = 'user';
    }

    $candidate = $base;
    $suffix = 1;

    while (username_exists($conn, $candidate, $ignoreUserId)) {
        $candidate = $base . $suffix;
        $suffix++;
    }

    return $candidate;
}
function current_user_role(): ?string
{
    return $_SESSION["role"] ?? null;
}

function current_user_full_name(): string
{
    $firstName = trim((string) ($_SESSION["first_name"] ?? ""));
    $lastName = trim((string) ($_SESSION["last_name"] ?? ""));
    $fullName = trim($firstName . " " . $lastName);

    if ($fullName !== "") {
        return $fullName;
    }

    return "Χρήστης";
}

function current_user_initials(): string
{
    $firstName = trim((string) ($_SESSION["first_name"] ?? ""));
    $lastName = trim((string) ($_SESSION["last_name"] ?? ""));
    $initials = "";

    if ($firstName !== "") {
        $initials .= mb_strtoupper(mb_substr($firstName, 0, 1, "UTF-8"), "UTF-8");
    }

    if ($lastName !== "") {
        $initials .= mb_strtoupper(mb_substr($lastName, 0, 1, "UTF-8"), "UTF-8");
    }

    return $initials !== "" ? $initials : "ΧΡ";
}

function current_role_label(): ?string
{
    return match (current_user_role()) {
        ROLE_ADMIN => "Διαχειριστής",
        ROLE_CANDIDATE => "Υποψήφιος",
        default => null,
    };
}

function current_dashboard_item(): ?array
{
    $role = current_user_role();

    if ($role === ROLE_ADMIN) {
        return ["key" => "admin", "label" => "Admin", "href" => "Admin/admindashboard.php"];
    }

    if ($role === ROLE_CANDIDATE) {
        return ["key" => "candidate", "label" => "Candidate", "href" => "Candidate/candidatedashboard.php"];
    }

    return null;
}

function nav_items(string $currentPage): array
{
    $role = current_user_role();
    $items = [
        ["key" => "home", "label" => "Αρχική", "href" => "index.php"],
        ["key" => "search", "label" => "Search", "href" => "Search/searchdashboard.php"],
    ];

    $dashboardItem = current_dashboard_item();
    if ($dashboardItem !== null) {
        $items[] = $dashboardItem;
    }

    if ($role === ROLE_ADMIN) {
        $items[] = ["key" => "list", "label" => "List", "href" => "list.php"];
    }

    $items[] = ["key" => "api", "label" => "API", "href" => "api/api.php"];

    foreach ($items as &$item) {
        $item["active"] = $item["key"] === $currentPage;
    }
    unset($item);

    return $items;
}

function path_from_root(string $target): string
{
    global $navBase;

    return ($navBase ?? "") . $target;
}