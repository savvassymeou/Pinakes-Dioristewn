<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$errorMessage = '';
$successMessage = '';
$resetRequest = null;

if (!ensure_password_reset_tokens_table($conn)) {
    $errorMessage = u('\u0394\u03B5\u03BD \u03AE\u03C4\u03B1\u03BD \u03B4\u03C5\u03BD\u03B1\u03C4\u03AE \u03B7 \u03C0\u03C1\u03BF\u03B5\u03C4\u03BF\u03B9\u03BC\u03B1\u03C3\u03AF\u03B1 \u03C4\u03B7\u03C2 \u03B5\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC\u03C2 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD.');
} elseif ($token === '') {
    $errorMessage = u('\u039B\u03B5\u03AF\u03C0\u03B5\u03B9 \u03C4\u03BF token \u03B5\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC\u03C2 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD.');
} else {
    $resetRequest = find_valid_password_reset($conn, $token);
    if (!$resetRequest) {
        $errorMessage = u('\u039F \u03C3\u03CD\u03BD\u03B4\u03B5\u03C3\u03BC\u03BF\u03C2 \u03B5\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC\u03C2 \u03B4\u03B5\u03BD \u03B5\u03AF\u03BD\u03B1\u03B9 \u03AD\u03B3\u03BA\u03C5\u03C1\u03BF\u03C2 \u03AE \u03AD\u03C7\u03B5\u03B9 \u03BB\u03AE\u03BE\u03B5\u03B9.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '' && $resetRequest) {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirmPassword === '') {
        $errorMessage = u('\u03A3\u03C5\u03BC\u03C0\u03BB\u03AE\u03C1\u03C9\u03C3\u03B5 \u03BA\u03B1\u03B9 \u03C4\u03B1 \u03B4\u03CD\u03BF \u03C0\u03B5\u03B4\u03AF\u03B1 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD.');
    } elseif (strlen($password) < 8) {
        $errorMessage = u('\u039F \u03BD\u03AD\u03BF\u03C2 \u03BA\u03C9\u03B4\u03B9\u03BA\u03CC\u03C2 \u03C0\u03C1\u03AD\u03C0\u03B5\u03B9 \u03BD\u03B1 \u03AD\u03C7\u03B5\u03B9 \u03C4\u03BF\u03C5\u03BB\u03AC\u03C7\u03B9\u03C3\u03C4\u03BF\u03BD 8 \u03C7\u03B1\u03C1\u03B1\u03BA\u03C4\u03AE\u03C1\u03B5\u03C2.');
    } elseif ($password !== $confirmPassword) {
        $errorMessage = u('\u0397 \u03B5\u03C0\u03B9\u03B2\u03B5\u03B2\u03B1\u03AF\u03C9\u03C3\u03B7 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD \u03B4\u03B5\u03BD \u03C4\u03B1\u03B9\u03C1\u03B9\u03AC\u03B6\u03B5\u03B9.');
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');

        if (!$updateStmt) {
            $errorMessage = u('\u0394\u03B5\u03BD \u03AE\u03C4\u03B1\u03BD \u03B4\u03C5\u03BD\u03B1\u03C4\u03AE \u03B7 \u03B1\u03C0\u03BF\u03B8\u03AE\u03BA\u03B5\u03C5\u03C3\u03B7 \u03C4\u03BF\u03C5 \u03BD\u03AD\u03BF\u03C5 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD.');
        } else {
            $userId = (int) $resetRequest['user_id'];
            $updateStmt->bind_param('si', $hashedPassword, $userId);

            if ($updateStmt->execute()) {
                mark_password_reset_used($conn, (int) $resetRequest['id']);
                $successMessage = u('\u039F \u03BA\u03C9\u03B4\u03B9\u03BA\u03CC\u03C2 \u03C3\u03BF\u03C5 \u03AC\u03BB\u03BB\u03B1\u03BE\u03B5 \u03B5\u03C0\u03B9\u03C4\u03C5\u03C7\u03CE\u03C2. \u03A4\u03CE\u03C1\u03B1 \u03BC\u03C0\u03BF\u03C1\u03B5\u03AF\u03C2 \u03BD\u03B1 \u03C3\u03C5\u03BD\u03B4\u03B5\u03B8\u03B5\u03AF\u03C2 \u03BC\u03B5 \u03C4\u03BF\u03BD \u03BD\u03AD\u03BF \u03BA\u03C9\u03B4\u03B9\u03BA\u03CC.');
                $resetRequest = null;
            } else {
                $errorMessage = u('\u0397 \u03B1\u03BB\u03BB\u03B1\u03B3\u03AE \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD \u03B1\u03C0\u03AD\u03C4\u03C5\u03C7\u03B5.');
            }

            $updateStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(u('\u039D\u03AD\u03BF\u03C2 \u039A\u03C9\u03B4\u03B9\u03BA\u03CC\u03C2') . ' | ' . APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --success-bg:#eef9f0; --success-border:#c8e8cf; --success-text:#25613a; --shadow:0 24px 60px rgba(17,39,68,.14); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Manrope',sans-serif; color:var(--text); background:radial-gradient(circle at top, rgba(185,134,47,.16), transparent 22%), radial-gradient(circle at left, rgba(52,103,168,.10), transparent 26%), linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%); }
        .page { min-height:100vh; display:grid; place-items:center; padding:32px 18px; }
        .card { width:min(100%,520px); padding:34px 32px; border-radius:28px; background:var(--panel); border:1px solid var(--panel-border); box-shadow:var(--shadow); }
        .brand { display:flex; align-items:center; gap:14px; margin-bottom:28px; font-weight:800; }
        .brand-logo { width:148px; max-width:44vw; height:auto; object-fit:contain; display:block; }
        .brand-copy strong { display:block; font-size:1rem; }
        .brand-copy span { display:block; margin-top:2px; color:var(--muted); font-size:.92rem; font-weight:600; }
        .eyebrow { display:inline-flex; align-items:center; margin-bottom:16px; padding:7px 12px; border-radius:999px; background:rgba(184,134,47,.12); color:var(--accent-dark); font-size:.78rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        h1 { margin:0 0 10px; font-family:'Space Grotesk',sans-serif; font-size:clamp(2rem,4vw,2.6rem); line-height:1.02; }
        .intro { margin:0 0 28px; color:var(--muted); line-height:1.65; font-size:.98rem; }
        label { display:block; margin:0 0 8px; color:var(--text); font-weight:800; }
        .message { margin-bottom:18px; padding:14px 16px; border-radius:14px; line-height:1.55; border:1px solid transparent; }
        .message.success { background:var(--success-bg); border-color:var(--success-border); color:var(--success-text); }
        .message.error { background:var(--danger-bg); border-color:var(--danger-border); color:var(--danger-text); }
        .password-field { position:relative; margin-bottom:18px; }
        .password-field input { width:100%; padding:15px 68px 15px 16px; border-radius:16px; border:1px solid var(--field-border); background:var(--field); color:var(--text); font-size:1rem; }
        .password-toggle { position:absolute; top:50%; right:12px; transform:translateY(-50%); width:42px; height:42px; border:1px solid var(--field-border); border-radius:999px; background:#fff; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; color:var(--accent-dark); box-shadow:0 6px 16px rgba(20,38,61,.08); }
        .password-toggle svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
        .password-toggle .icon-hide { display:none; }
        .password-toggle.is-visible .icon-show { display:none; }
        .password-toggle.is-visible .icon-hide { display:block; }
        .submit-btn { width:100%; padding:15px; border:none; border-radius:16px; cursor:pointer; font-size:1rem; font-weight:800; color:#fff; background:linear-gradient(135deg,var(--accent),var(--accent-2)); box-shadow:0 18px 32px rgba(184,134,47,.24); }
        .helper-links { margin-top:20px; text-align:center; color:var(--muted); font-size:.95rem; }
        .helper-links a { color:var(--accent-dark); font-weight:800; }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/ichnos-logo.jpg" alt="<?php echo h(APP_NAME); ?> logo">
                <div class="brand-copy">
                    <strong><?php echo h(APP_NAME); ?></strong>
                    <span>&#927;&#961;&#953;&#963;&#956;&#972;&#962; &#957;&#941;&#959;&#965; &#954;&#969;&#948;&#953;&#954;&#959;&#973;</span>
                </div>
            </div>
            <span class="eyebrow">Reset Password</span>
            <h1>&#925;&#941;&#959;&#962; &#954;&#969;&#948;&#953;&#954;&#972;&#962;</h1>
            <p class="intro">&#908;&#961;&#953;&#963;&#949; &#957;&#941;&#959; &#954;&#969;&#948;&#953;&#954;&#972; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;&#962; &#947;&#953;&#945; &#964;&#959;&#957; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972; &#963;&#959;&#965;.</p>
            <?php if ($successMessage !== ''): ?><div class="message success"><?php echo h($successMessage); ?></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="message error"><?php echo h($errorMessage); ?></div><?php endif; ?>
            <?php if ($resetRequest && $successMessage === ''): ?>
                <form method="post" action="">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <label for="password">&#925;&#941;&#959;&#962; &#954;&#969;&#948;&#953;&#954;&#972;&#962;</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" placeholder="&#932;&#959;&#965;&#955;&#940;&#967;&#953;&#963;&#964;&#959;&#957; 8 &#967;&#945;&#961;&#945;&#954;&#964;&#942;&#961;&#949;&#962;" required>
                        <button type="button" class="password-toggle" data-target="password" aria-label="&#917;&#956;&#966;&#940;&#957;&#953;&#963;&#951; &#954;&#969;&#948;&#953;&#954;&#959;&#973;">
                            <svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg>
                        </button>
                    </div>
                    <label for="confirm_password">&#917;&#960;&#953;&#946;&#949;&#946;&#945;&#943;&#969;&#963;&#951; &#954;&#969;&#948;&#953;&#954;&#959;&#973;</label>
                    <div class="password-field">
                        <input id="confirm_password" name="confirm_password" type="password" placeholder="&#917;&#960;&#945;&#957;&#940;&#955;&#945;&#946;&#949; &#964;&#959;&#957; &#957;&#941;&#959; &#954;&#969;&#948;&#953;&#954;&#972;" required>
                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="&#917;&#956;&#966;&#940;&#957;&#953;&#963;&#951; &#954;&#969;&#948;&#953;&#954;&#959;&#973;">
                            <svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg>
                        </button>
                    </div>
                    <button type="submit" class="submit-btn">&#913;&#955;&#955;&#945;&#947;&#942; &#954;&#969;&#948;&#953;&#954;&#959;&#973;</button>
                </form>
            <?php endif; ?>
            <div class="helper-links"><a href="login.php">&#917;&#960;&#953;&#963;&#964;&#961;&#959;&#966;&#942; &#963;&#964;&#951; &#931;&#973;&#957;&#948;&#949;&#963;&#951;</a></div>
        </section>
    </main>
    <script>
        document.querySelectorAll('.password-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);
                if (!input) return;
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.classList.toggle('is-visible', visible);
                button.setAttribute('aria-label', visible ? '\u0391\u03C0\u03CC\u03BA\u03C1\u03C5\u03C8\u03B7 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD' : '\u0395\u03BC\u03C6\u03AC\u03BD\u03B9\u03C3\u03B7 \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD');
            });
        });
    </script>
</body>
</html>
