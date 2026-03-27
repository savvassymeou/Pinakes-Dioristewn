<?php

declare(strict_types=1);

require_once __DIR__ . "/config.php";

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function is_logged_in(): bool
{
    ensure_session_started();

    return isset($_SESSION["user_id"], $_SESSION["role"]);
}

function current_session_role(): ?string
{
    ensure_session_started();

    return $_SESSION["role"] ?? null;
}

function redirect_to_login(string $loginPath): void
{
    header("Location: " . $loginPath);
    exit;
}

function redirect_to_dashboard_by_role(string $adminPath, string $candidatePath, string $loginPath): void
{
    $role = current_session_role();

    if ($role === ROLE_ADMIN) {
        header("Location: " . $adminPath);
        exit;
    }

    if ($role === ROLE_CANDIDATE) {
        header("Location: " . $candidatePath);
        exit;
    }

    redirect_to_login($loginPath);
}

function require_login(string $loginPath): void
{
    if (!is_logged_in()) {
        redirect_to_login($loginPath);
    }
}

function require_role(string $requiredRole, string $loginPath, string $adminPath, string $candidatePath): void
{
    require_login($loginPath);

    $role = current_session_role();

    if ($role === $requiredRole) {
        return;
    }

    redirect_to_dashboard_by_role($adminPath, $candidatePath, $loginPath);
}