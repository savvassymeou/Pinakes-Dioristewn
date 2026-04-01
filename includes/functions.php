<?php

declare(strict_types=1);

require_once __DIR__ . "/config.php";

function repair_mojibake_segment(string $value): string
{
    if ($value === '' || !preg_match('/[ÃƒÆ’Ã†â€™ÃƒÆ’Ã¢â‚¬Å¡ÃƒÆ’Ã…Â½ÃƒÆ’Ã‚ÂÃƒÆ’Ã‚Â¢]/u', $value)) {
        return $value;
    }

    $decoded = $value;

    for ($i = 0; $i < 4; $i++) {
        $candidate = @iconv('UTF-8', 'Windows-1252//IGNORE', $decoded);

        if (!is_string($candidate) || $candidate === '' || $candidate === $decoded) {
            break;
        }

        $decoded = $candidate;

        if (preg_match('/\p{Greek}/u', $decoded) && !preg_match('/[ÃƒÆ’Ã†â€™ÃƒÆ’Ã¢â‚¬Å¡ÃƒÆ’Ã…Â½ÃƒÆ’Ã‚ÂÃƒÆ’Ã‚Â¢]/u', $decoded)) {
            break;
        }
    }

    return $decoded;
}

function repair_mojibake_output_buffer(string $buffer): string
{
    $fixed = preg_replace_callback(
        '/[ÃƒÆ’Ã†â€™ÃƒÆ’Ã¢â‚¬Å¡ÃƒÆ’Ã…Â½ÃƒÆ’Ã‚ÂÃƒÆ’Ã‚Â¢][^<>"\']*/u',
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
    $subject = APP_NAME . " - ÃƒÅ½Ã¢â‚¬Â¢ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¬ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â";
    $htmlBody = <<<HTML
<html lang="el">
<body style="font-family: Arial, sans-serif; background: #f6f8fb; color: #17324d; padding: 24px;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 32px; border: 1px solid #dbe5f0;">
        <h1 style="margin-top: 0; font-size: 24px;">ÃƒÅ½Ã¢â‚¬Â¢ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¬ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â</h1>
        <p>ÃƒÅ½Ã¢â‚¬ÂºÃƒÅ½Ã‚Â¬ÃƒÅ½Ã‚Â²ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â¼ÃƒÅ½Ã‚Âµ ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â¯ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â·ÃƒÅ½Ã‚Â¼ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â»ÃƒÅ½Ã‚Â»ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â® ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â ÃƒÂÃ¢â€šÂ¬ÃƒÂÃ‚ÂÃƒÂÃ…â€™ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â²ÃƒÅ½Ã‚Â±ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â·ÃƒÂÃ¢â‚¬Å¡ ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â»ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â±ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â±ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¼ÃƒÂÃ…â€™ ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦.</p>
        <p>ÃƒÅ½Ã‚Â ÃƒÅ½Ã‚Â¬ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â·ÃƒÂÃ†â€™ÃƒÅ½Ã‚Âµ ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¬ÃƒÂÃ¢â‚¬Å¾ÃƒÂÃ¢â‚¬Â° ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦ÃƒÅ½Ã‚Â¼ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â¯ ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¯ÃƒÂÃ†â€™ÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â¹ÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â­ÃƒÅ½Ã‚Â¿ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÂÃ…â€™. ÃƒÅ½Ã…Â¸ ÃƒÂÃ†â€™ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚ÂµÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¼ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚Â¹ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Â¡ÃƒÂÃ‚ÂÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â¹ ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â± 1 ÃƒÂÃ…Â½ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â±.</p>
        <p style="margin: 28px 0;">
            <a href="{$resetLink}" style="display: inline-block; padding: 14px 22px; border-radius: 12px; background: #b8862f; color: #ffffff; text-decoration: none; font-weight: 700;">ÃƒÅ½Ã…Â¸ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¹ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¼ÃƒÂÃ…â€™ÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â­ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â</a>
        </p>
        <p>ÃƒÅ½Ã¢â‚¬ËœÃƒÅ½Ã‚Â½ ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦ÃƒÅ½Ã‚Â¼ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â¯ ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â¯ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â¹, ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â­ÃƒÅ½Ã‚Â³ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â±ÃƒÂÃ‹â€ ÃƒÅ½Ã‚Âµ ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â¦ÃƒÂÃ¢â‚¬Å¾ÃƒÂÃ…â€™ ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ link ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â½ browser ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦:</p>
        <p><a href="{$resetLink}">{$resetLink}</a></p>
        <p style="margin-top: 28px; color: #5d7088;">ÃƒÅ½Ã¢â‚¬ËœÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â¶ÃƒÅ½Ã‚Â®ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â·ÃƒÂÃ†â€™ÃƒÅ½Ã‚ÂµÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚ÂµÃƒÂÃ†â€™ÃƒÂÃ‚Â ÃƒÅ½Ã‚ÂµÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¬ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â, ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â½ÃƒÂÃ…â€™ÃƒÅ½Ã‚Â·ÃƒÂÃ†â€™ÃƒÅ½Ã‚Âµ ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â¦ÃƒÂÃ¢â‚¬Å¾ÃƒÂÃ…â€™ ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ email.</p>
    </div>
</body>
</html>
HTML;
    $textBody = "ÃƒÅ½Ã¢â‚¬Â¢ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¬ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â\n\n"
        . "ÃƒÅ½Ã¢â‚¬ÂºÃƒÅ½Ã‚Â¬ÃƒÅ½Ã‚Â²ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â¼ÃƒÅ½Ã‚Âµ ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â¯ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â·ÃƒÅ½Ã‚Â¼ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â»ÃƒÅ½Ã‚Â»ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â® ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â ÃƒÂÃ¢â€šÂ¬ÃƒÂÃ‚ÂÃƒÂÃ…â€™ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â²ÃƒÅ½Ã‚Â±ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â·ÃƒÂÃ¢â‚¬Å¡ ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â»ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â±ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â±ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¼ÃƒÂÃ…â€™ ÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Â¦.\n"
        . "ÃƒÅ½Ã¢â‚¬Â ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â¿ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â¾ÃƒÅ½Ã‚Âµ ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ ÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¬ÃƒÂÃ¢â‚¬Å¾ÃƒÂÃ¢â‚¬Â° link ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â± ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¯ÃƒÂÃ†â€™ÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â¹ÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â­ÃƒÅ½Ã‚Â¿ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÂÃ…â€™. ÃƒÅ½Ã…Â¸ ÃƒÂÃ†â€™ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚ÂµÃƒÂÃ†â€™ÃƒÅ½Ã‚Â¼ÃƒÅ½Ã‚Â¿ÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚Â¹ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Â¡ÃƒÂÃ‚ÂÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â¹ ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚Â± 1 ÃƒÂÃ…Â½ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â±.\n\n"
        . $resetLink . "\n\n"
        . "ÃƒÅ½Ã¢â‚¬ËœÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚ÂµÃƒÅ½Ã‚Â½ ÃƒÅ½Ã‚Â¶ÃƒÅ½Ã‚Â®ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â·ÃƒÂÃ†â€™ÃƒÅ½Ã‚ÂµÃƒÂÃ¢â‚¬Å¡ ÃƒÅ½Ã‚ÂµÃƒÂÃ†â€™ÃƒÂÃ‚Â ÃƒÅ½Ã‚ÂµÃƒÂÃ¢â€šÂ¬ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â½ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â ÃƒÅ½Ã‚Â¿ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â¬ ÃƒÅ½Ã‚ÂºÃƒÂÃ¢â‚¬Â°ÃƒÅ½Ã‚Â´ÃƒÅ½Ã‚Â¹ÃƒÅ½Ã‚ÂºÃƒÅ½Ã‚Â¿ÃƒÂÃ‚Â, ÃƒÅ½Ã‚Â±ÃƒÅ½Ã‚Â³ÃƒÅ½Ã‚Â½ÃƒÂÃ…â€™ÃƒÅ½Ã‚Â·ÃƒÂÃ†â€™ÃƒÅ½Ã‚Âµ ÃƒÅ½Ã‚Â±ÃƒÂÃ¢â‚¬Â¦ÃƒÂÃ¢â‚¬Å¾ÃƒÂÃ…â€™ ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â¿ email.";

    return send_html_email($email, $subject, $htmlBody, $textBody);
}

function is_valid_username_format(string $value): bool
{
    $value = trim($value);

    return $value !== "" && preg_match('/^\p{L}{3,}$/u', $value) === 1;
}

function username_validation_message(): string
{
    return 'Το username πρέπει να περιέχει μόνο γράμματα και να έχει τουλάχιστον 3 χαρακτήρες.';
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
    return 'Ο αριθμός ταυτότητας πρέπει να περιέχει μόνο γράμματα και αριθμούς.';
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

    return "ÃƒÅ½Ã‚Â§ÃƒÂÃ‚ÂÃƒÅ½Ã‚Â®ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬Å¾ÃƒÅ½Ã‚Â·ÃƒÂÃ¢â‚¬Å¡";
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

    return $initials !== "" ? $initials : "ÃƒÅ½Ã‚Â§ÃƒÅ½Ã‚Â¡";
}

function current_role_label(): ?string
{
    return match (current_user_role()) {
        ROLE_ADMIN => "????????????",
        ROLE_CANDIDATE => "?????????",
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
        ["key" => "home", "label" => "??????", "href" => "index.php"],
        ["key" => "search", "label" => "?????????", "href" => "modules/search/searchdashboard.php"],
    ];

    if ($role === ROLE_ADMIN) {
        $items[] = ["key" => "admin", "label" => "Admin", "href" => "modules/admin/admindashboard.php"];
        $items[] = ["key" => "list", "label" => "List", "href" => "list.php"];
    } elseif ($role === ROLE_CANDIDATE) {
        $items[] = ["key" => "candidate", "label" => "Candidate", "href" => "modules/candidate/candidatedashboard.php"];
    }

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

    return $conn->query($sql) !== false;
}

function ensure_identity_number_column($conn): bool
{
    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'identity_number'");

    if ($columnCheck === false) {
        return false;
    }

    if ($columnCheck->num_rows === 0) {
        $added = $conn->query(
            "ALTER TABLE users ADD COLUMN identity_number VARCHAR(30) DEFAULT NULL AFTER email"
        ) !== false;

        if (!$added) {
            return false;
        }
    }

    $indexCheck = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'uniq_users_identity_number'");

    if ($indexCheck === false) {
        return false;
    }

    if ($indexCheck->num_rows === 0) {
        return $conn->query(
            "ALTER TABLE users ADD UNIQUE KEY uniq_users_identity_number (identity_number)"
        ) !== false;
    }

    return true;
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


