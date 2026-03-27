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

function current_user_role(): ?string
{
    return $_SESSION["role"] ?? null;
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