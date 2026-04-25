<?php

declare(strict_types=1);

require_once __DIR__ . "/config.php";

function repair_mojibake_segment(string $value): string
{
    if ($value === '' || !preg_match('/[ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â½ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢]/u', $value)) {
        return $value;
    }

    $decoded = $value;

    for ($i = 0; $i < 4; $i++) {
        $candidate = @iconv('UTF-8', 'Windows-1252//IGNORE', $decoded);

        if (!is_string($candidate) || $candidate === '' || $candidate === $decoded) {
            break;
        }

        $decoded = $candidate;

        if (preg_match('/\p{Greek}/u', $decoded) && !preg_match('/[ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â½ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢]/u', $decoded)) {
            break;
        }
    }

    return $decoded;
}

function repair_mojibake_output_buffer(string $buffer): string
{
    $fixed = preg_replace_callback(
        '/[ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â½ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢][^<>"\']*/u',
        static fn(array $matches): string => repair_mojibake_segment($matches[0]),
        $buffer
    );

    return is_string($fixed) ? $fixed : $buffer;
}

function ensure_output_encoding_fix(): void
{
    static $started = false;

    if ($started || PHP_SAPI === 'cli') {
        return;
    }

    ob_start('repair_mojibake_output_buffer');
    $started = true;
}

ensure_output_encoding_fix();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function h(?string $value): string
{
    return e($value);
}

function current_scheme(): string
{
    $https = $_SERVER["HTTPS"] ?? "";
    $forwardedProto = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "";

    if (
        (is_string($https) && $https !== "" && strtolower($https) !== "off")
        || strtolower((string) $forwardedProto) === "https"
    ) {
        return "https";
    }

    return "http";
}

function current_host(): string
{
    $host = trim((string) ($_SERVER["HTTP_HOST"] ?? ""));

    return $host !== "" ? $host : "localhost";
}

function current_script_dir(): string
{
    $scriptName = str_replace("\\", "/", (string) ($_SERVER["SCRIPT_NAME"] ?? ""));
    $dir = rtrim(str_replace("\\", "/", dirname($scriptName)), "/.");

    return $dir === "" ? "" : $dir;
}

function build_app_url(string $path = ""): string
{
    $base = current_scheme() . "://" . current_host() . current_script_dir();
    $path = ltrim($path, "/");

    return $path === "" ? $base : $base . "/" . $path;
}

function is_local_host(): bool
{
    $host = strtolower(current_host());
    $host = explode(":", $host)[0];

    return in_array($host, ["localhost", "127.0.0.1", "::1"], true);
}

function format_mailbox_header(string $email, string $name): string
{
    $safeEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ?: MAIL_FROM_ADDRESS;
    $safeName = trim(str_replace(["\r", "\n"], "", $name));

    if ($safeName === "") {
        return $safeEmail;
    }

    $encodedName = "=?UTF-8?B?" . base64_encode($safeName) . "?=";

    return $encodedName . " <" . $safeEmail . ">";
}

function send_html_email(string $to, string $subject, string $htmlBody, string $textBody = ""): bool
{
    $recipient = filter_var($to, FILTER_VALIDATE_EMAIL);

    if ($recipient === false) {
        return false;
    }

    $plainText = trim($textBody) !== "" ? $textBody : strip_tags($htmlBody);
    $boundary = "mixed_" . bin2hex(random_bytes(12));
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $fromHeader = format_mailbox_header(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"",
        "From: " . $fromHeader,
        "Reply-To: " . $fromHeader,
        "X-Mailer: PHP/" . phpversion(),
    ];

    $message = [];
    $message[] = "--" . $boundary;
    $message[] = "Content-Type: text/plain; charset=UTF-8";
    $message[] = "Content-Transfer-Encoding: 8bit";
    $message[] = "";
    $message[] = $plainText;
    $message[] = "";
    $message[] = "--" . $boundary;
    $message[] = "Content-Type: text/html; charset=UTF-8";
    $message[] = "Content-Transfer-Encoding: 8bit";
    $message[] = "";
    $message[] = $htmlBody;
    $message[] = "";
    $message[] = "--" . $boundary . "--";

    return @mail($recipient, $encodedSubject, implode("\r\n", $message), implode("\r\n", $headers));
}

function build_password_reset_link(string $token): string
{
    return build_app_url("reset_password.php") . "?token=" . urlencode($token);
}

function send_password_reset_email(string $email, string $resetLink): bool
{
    $subject = APP_NAME . " - Î•Ï€Î±Î½Î±Ï†Î¿ÏÎ¬ ÎºÏ‰Î´Î¹ÎºÎ¿Ï";
    $htmlBody = <<<HTML
<html lang="el">
<body style="font-family: Arial, sans-serif; background: #f6f8fb; color: #17324d; padding: 24px;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 32px; border: 1px solid #dbe5f0;">
        <h1 style="margin-top: 0; font-size: 24px;">Î•Ï€Î±Î½Î±Ï†Î¿ÏÎ¬ ÎºÏ‰Î´Î¹ÎºÎ¿Ï</h1>
        <p>Î›Î¬Î²Î±Î¼Îµ Î±Î¯Ï„Î·Î¼Î± Î³Î¹Î± Î±Î»Î»Î±Î³Î® ÎºÏ‰Î´Î¹ÎºÎ¿Ï Ï€ÏÏŒÏƒÎ²Î±ÏƒÎ·Ï‚ ÏƒÏ„Î¿Î½ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒ ÏƒÎ¿Ï….</p>
        <p>Î Î¬Ï„Î·ÏƒÎµ Ï„Î¿ Ï€Î±ÏÎ±ÎºÎ¬Ï„Ï‰ ÎºÎ¿Ï…Î¼Ï€Î¯ Î³Î¹Î± Î½Î± Î¿ÏÎ¯ÏƒÎµÎ¹Ï‚ Î½Î­Î¿ ÎºÏ‰Î´Î¹ÎºÏŒ. ÎŸ ÏƒÏÎ½Î´ÎµÏƒÎ¼Î¿Ï‚ Î¹ÏƒÏ‡ÏÎµÎ¹ Î³Î¹Î± 1 ÏŽÏÎ±.</p>
        <p>
            <a href="{$resetLink}" style="display: inline-block; padding: 14px 22px; border-radius: 12px; background: #b8862f; color: #ffffff; text-decoration: none; font-weight: 700;">ÎŸÏÎ¹ÏƒÎ¼ÏŒÏ‚ Î½Î­Î¿Ï… ÎºÏ‰Î´Î¹ÎºÎ¿Ï</a>
        </p>
        <p>Î‘Î½ Î´ÎµÎ½ Î¶Î®Ï„Î·ÏƒÎµÏ‚ ÎµÏ€Î±Î½Î±Ï†Î¿ÏÎ¬ ÎºÏ‰Î´Î¹ÎºÎ¿Ï, Î±Î³Î½ÏŒÎ·ÏƒÎµ Î±Ï…Ï„ÏŒ Ï„Î¿ email.</p>
    </div>
</body>
</html>
HTML;

    $textBody = "Î•Ï€Î±Î½Î±Ï†Î¿ÏÎ¬ ÎºÏ‰Î´Î¹ÎºÎ¿Ï\n\n"
        . "Î›Î¬Î²Î±Î¼Îµ Î±Î¯Ï„Î·Î¼Î± Î³Î¹Î± Î±Î»Î»Î±Î³Î® ÎºÏ‰Î´Î¹ÎºÎ¿Ï Ï€ÏÏŒÏƒÎ²Î±ÏƒÎ·Ï‚ ÏƒÏ„Î¿Î½ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒ ÏƒÎ¿Ï….\n"
        . "Î†Î½Î¿Î¹Î¾Îµ Ï„Î¿ Ï€Î±ÏÎ±ÎºÎ¬Ï„Ï‰ link Î³Î¹Î± Î½Î± Î¿ÏÎ¯ÏƒÎµÎ¹Ï‚ Î½Î­Î¿ ÎºÏ‰Î´Î¹ÎºÏŒ. ÎŸ ÏƒÏÎ½Î´ÎµÏƒÎ¼Î¿Ï‚ Î¹ÏƒÏ‡ÏÎµÎ¹ Î³Î¹Î± 1 ÏŽÏÎ±.\n\n"
        . $resetLink . "\n\n"
        . "Î‘Î½ Î´ÎµÎ½ Î¶Î®Ï„Î·ÏƒÎµÏ‚ ÎµÏ€Î±Î½Î±Ï†Î¿ÏÎ¬ ÎºÏ‰Î´Î¹ÎºÎ¿Ï, Î±Î³Î½ÏŒÎ·ÏƒÎµ Î±Ï…Ï„ÏŒ Ï„Î¿ email.";

    return send_html_email($email, $subject, $htmlBody, $textBody);
}
function is_valid_username_format(string $value): bool
{
    $value = trim($value);

    return $value !== "" && preg_match('/^\p{L}{3,}$/u', $value) === 1;
}

function username_validation_message(): string
{
    return u('\u03A4\u03BF username \u03C0\u03C1\u03AD\u03C0\u03B5\u03B9 \u03BD\u03B1 \u03C0\u03B5\u03C1\u03B9\u03AD\u03C7\u03B5\u03B9 \u03BC\u03CC\u03BD\u03BF \u03B3\u03C1\u03AC\u03BC\u03BC\u03B1\u03C4\u03B1 \u03BA\u03B1\u03B9 \u03BD\u03B1 \u03AD\u03C7\u03B5\u03B9 \u03C4\u03BF\u03C5\u03BB\u03AC\u03C7\u03B9\u03C3\u03C4\u03BF\u03BD 3 \u03C7\u03B1\u03C1\u03B1\u03BA\u03C4\u03AE\u03C1\u03B5\u03C2.');
}


function normalize_identity_number(string $value): string
{
    $value = preg_replace('/\s+/u', '', trim($value));
    $value = is_string($value) ? $value : '';

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($value, 'UTF-8');
    }

    return strtoupper($value);
}

function is_valid_identity_number(string $value): bool
{
    $value = normalize_identity_number($value);

    return $value !== "" && preg_match('/^[\p{L}\d]{5,20}$/u', $value) === 1;
}

function identity_number_validation_message(): string
{
    return u('\u039F \u03B1\u03C1\u03B9\u03B8\u03BC\u03CC\u03C2 \u03C4\u03B1\u03C5\u03C4\u03CC\u03C4\u03B7\u03C4\u03B1\u03C2 \u03C0\u03C1\u03AD\u03C0\u03B5\u03B9 \u03BD\u03B1 \u03C0\u03B5\u03C1\u03B9\u03AD\u03C7\u03B5\u03B9 \u03BC\u03CC\u03BD\u03BF \u03B3\u03C1\u03AC\u03BC\u03BC\u03B1\u03C4\u03B1 \u03BA\u03B1\u03B9 \u03B1\u03C1\u03B9\u03B8\u03BC\u03BF\u03CD\u03C2.');
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
    if ($ignoreUserId !== null) {
        return fetch_one_prepared(
            $conn,
            'SELECT id
             FROM users
             WHERE username = ?
               AND id <> ?
             LIMIT 1',
            'si',
            [$username, $ignoreUserId]
        ) !== null;
    }

    return fetch_one_prepared(
        $conn,
        'SELECT id
         FROM users
         WHERE username = ?
         LIMIT 1',
        's',
        [$username]
    ) !== null;
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
function u(string $value): string
{
    $decoded = json_decode('"' . $value . '"');
    return is_string($decoded) ? $decoded : $value;
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
    return u('\\u03A7\\u03C1\\u03AE\\u03C3\\u03C4\\u03B7\\u03C2');
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
    return $initials !== "" ? $initials : 'XX';
}
function current_role_label(): ?string
{
    return match (current_user_role()) {
        ROLE_ADMIN => u('\\u0394\\u03B9\\u03B1\\u03C7\\u03B5\\u03B9\\u03C1\\u03B9\\u03C3\\u03C4\\u03AE\\u03C2'),
        ROLE_CANDIDATE => u('\\u03A5\\u03C0\\u03BF\\u03C8\\u03AE\\u03C6\\u03B9\\u03BF\\u03C2'),
        default => null,
    };
}
function current_dashboard_item(): ?array
{
    $role = current_user_role();
    if ($role === ROLE_ADMIN) {
        return ["key" => "admin", "label" => "Admin", "href" => "modules/admin/admindashboard.php"];
    }
    if ($role === ROLE_CANDIDATE) {
        return ["key" => "candidate", "label" => "Candidate", "href" => "modules/candidate/candidatedashboard.php"];
    }
    return null;
}
function nav_items(string $currentPage): array
{
    $role = current_user_role();
    $items = [
        ["key" => "home", "label" => u('\\u0391\\u03C1\\u03C7\\u03B9\\u03BA\\u03AE'), "href" => "index.php"],
        ["key" => "search", "label" => u('\\u0391\\u03BD\\u03B1\\u03B6\\u03AE\\u03C4\\u03B7\\u03C3\\u03B7'), "href" => "modules/search/searchdashboard.php"],
    ];
    if ($role === ROLE_ADMIN) {
        $items[] = ["key" => "admin", "label" => u('\\u0394\\u03B9\\u03B1\\u03C7\\u03B5\\u03AF\\u03C1\\u03B9\\u03C3\\u03B7'), "href" => "modules/admin/admindashboard.php"];
        $items[] = ["key" => "list", "label" => u('\\u039B\\u03AF\\u03C3\\u03C4\\u03B1'), "href" => "modules/admin/list.php"];
    } elseif ($role === ROLE_CANDIDATE) {
        $items[] = ["key" => "candidate", "label" => u('\\u03A5\\u03C0\\u03BF\\u03C8\\u03AE\\u03C6\\u03B9\\u03BF\\u03C2'), "href" => "modules/candidate/candidatedashboard.php"];
    }
    foreach ($items as &$item) {
        $item["active"] = $item["key"] === $currentPage;
    }
    unset($item);
    return $items;
}
function path_from_root(string $target): string
{
    $cleanTarget = ltrim($target, "/\\");
    $scriptName = str_replace("\\", "/", (string) ($_SERVER["SCRIPT_NAME"] ?? ""));
    $scriptDirUrl = rtrim(str_replace("\\", "/", dirname($scriptName)), "/.");
    $scriptFilename = str_replace("\\", "/", (string) ($_SERVER["SCRIPT_FILENAME"] ?? ""));
    $projectRoot = str_replace("\\", "/", dirname(__DIR__));
    $currentDirFs = $scriptFilename !== "" ? str_replace("\\", "/", dirname($scriptFilename)) : $projectRoot;
    $relativeDir = "";

    if ($currentDirFs === $projectRoot) {
        $relativeDir = "";
    } elseif (str_starts_with($currentDirFs, $projectRoot . "/")) {
        $relativeDir = trim(substr($currentDirFs, strlen($projectRoot)), "/");
    }

    $basePath = $scriptDirUrl;

    if ($relativeDir !== "") {
        $relativeUrlSuffix = "/" . str_replace("\\", "/", $relativeDir);

        if (str_ends_with($basePath, $relativeUrlSuffix)) {
            $basePath = substr($basePath, 0, -strlen($relativeUrlSuffix));
        }
    }

    $basePath = rtrim($basePath, "/");

    return ($basePath === "" ? "" : $basePath) . "/" . str_replace("\\", "/", $cleanTarget);
}
function execute_prepared_statement($conn, string $sql, string $types = "", array $params = []): bool
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    $executed = $stmt->execute();
    $stmt->close();

    return $executed;
}
function fetch_all_prepared($conn, string $sql, string $types = "", array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $rows = [];
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();

    return $rows;
}
function fetch_one_prepared($conn, string $sql, string $types = "", array $params = []): ?array
{
    $rows = fetch_all_prepared($conn, $sql, $types, $params);

    return $rows[0] ?? null;
}
function table_column_exists($conn, string $tableName, string $columnName): bool
{
    $row = fetch_one_prepared(
        $conn,
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        'ss',
        [$tableName, $columnName]
    );

    return (int) ($row['total'] ?? 0) > 0;
}
function ensure_password_reset_tokens_table($conn): bool
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    return execute_prepared_statement($conn, $sql);
}
function ensure_user_profiles_table($conn): bool
{
    $createSql = <<<SQL
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    identity_number VARCHAR(30) DEFAULT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    if (!execute_prepared_statement($conn, $createSql)) {
        return false;
    }

    $hasFirstName = table_column_exists($conn, 'users', 'first_name');
    $hasLastName = table_column_exists($conn, 'users', 'last_name');

    if (!$hasFirstName || !$hasLastName) {
        return true;
    }

    $hasIdentity = table_column_exists($conn, 'users', 'identity_number');
    $hasPhone = table_column_exists($conn, 'users', 'phone');

    $migrateSql = match (true) {
        $hasIdentity && $hasPhone => <<<SQL
            INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.identity_number,
                u.phone
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE up.user_id IS NULL
        SQL,
        $hasIdentity => <<<SQL
            INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.identity_number,
                NULL
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE up.user_id IS NULL
        SQL,
        $hasPhone => <<<SQL
            INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                NULL,
                u.phone
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE up.user_id IS NULL
        SQL,
        default => <<<SQL
            INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                NULL,
                NULL
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            WHERE up.user_id IS NULL
        SQL,
    };

    return execute_prepared_statement($conn, $migrateSql);
}

function ensure_identity_number_column($conn): bool
{
    return ensure_user_profiles_table($conn);
}
function create_password_reset_token($conn, int $userId): ?string
{
    if (!ensure_password_reset_tokens_table($conn)) {
        return null;
    }
    $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $userId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable) {
        return null;
    }
    $tokenHash = hash("sha256", $token);
    $expiresAt = date("Y-m-d H:i:s", time() + 3600);
    $insertStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        return null;
    }
    $insertStmt->bind_param("iss", $userId, $tokenHash, $expiresAt);
    $ok = $insertStmt->execute();
    $insertStmt->close();
    return $ok ? $token : null;
}
function find_valid_password_reset($conn, string $token): ?array
{
    if ($token === "" || !ensure_password_reset_tokens_table($conn)) {
        return null;
    }
    $tokenHash = hash("sha256", $token);
    $stmt = $conn->prepare(
        "SELECT prt.id, prt.user_id, prt.expires_at, prt.used_at, u.email
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return null;
    }
    if (($row["used_at"] ?? null) !== null) {
        return null;
    }
    if (strtotime((string) $row["expires_at"]) < time()) {
        return null;
    }
    return $row;
}
function mark_password_reset_used($conn, int $resetId): void
{
    $stmt = $conn->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $resetId);
    $stmt->execute();
    $stmt->close();
}
