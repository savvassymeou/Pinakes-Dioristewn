<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = u('\u0395\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC \u03BA\u03C9\u03B4\u03B9\u03BA\u03BF\u03CD');
$errorMessage = '';
$successMessage = '';
$resetLink = '';
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($email === '') {
        $errorMessage = u('\u03A3\u03C5\u03BC\u03C0\u03BB\u03AE\u03C1\u03C9\u03C3\u03B5 \u03C4\u03BF email \u03C4\u03BF\u03C5 \u03BB\u03BF\u03B3\u03B1\u03C1\u03B9\u03B1\u03C3\u03BC\u03BF\u03CD \u03C3\u03BF\u03C5.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = u('\u0394\u03CE\u03C3\u03B5 \u03AD\u03B3\u03BA\u03C5\u03C1\u03B7 \u03B4\u03B9\u03B5\u03CD\u03B8\u03C5\u03BD\u03C3\u03B7 email.');
    } elseif (!ensure_password_reset_tokens_table($conn)) {
        $errorMessage = u('\u0394\u03B5\u03BD \u03AE\u03C4\u03B1\u03BD \u03B4\u03C5\u03BD\u03B1\u03C4\u03AE \u03B7 \u03B4\u03B7\u03BC\u03B9\u03BF\u03C5\u03C1\u03B3\u03AF\u03B1 \u03B1\u03B9\u03C4\u03AE\u03BC\u03B1\u03C4\u03BF\u03C2 \u03B5\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC\u03C2 \u03B1\u03C5\u03C4\u03AE \u03C4\u03B7 \u03C3\u03C4\u03B9\u03B3\u03BC\u03AE.');
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');

        if (!$stmt) {
            $errorMessage = u('\u0394\u03B5\u03BD \u03AE\u03C4\u03B1\u03BD \u03B4\u03C5\u03BD\u03B1\u03C4\u03AE \u03B7 \u03B1\u03BD\u03B1\u03B6\u03AE\u03C4\u03B7\u03C3\u03B7 \u03C4\u03BF\u03C5 \u03BB\u03BF\u03B3\u03B1\u03C1\u03B9\u03B1\u03C3\u03BC\u03BF\u03CD.');
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user) {
                $token = create_password_reset_token($conn, (int) $user['id']);

                if ($token === null) {
                    $errorMessage = u('\u0394\u03B5\u03BD \u03AE\u03C4\u03B1\u03BD \u03B4\u03C5\u03BD\u03B1\u03C4\u03AE \u03B7 \u03B4\u03B7\u03BC\u03B9\u03BF\u03C5\u03C1\u03B3\u03AF\u03B1 \u03C3\u03C5\u03BD\u03B4\u03AD\u03C3\u03BC\u03BF\u03C5 \u03B5\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC\u03C2.');
                } else {
                    $resetLink = build_password_reset_link($token);
                    $successMessage = u('\u039F \u03C3\u03CD\u03BD\u03B4\u03B5\u03C3\u03BC\u03BF\u03C2 \u03B5\u03C0\u03B1\u03BD\u03B1\u03C6\u03BF\u03C1\u03AC\u03C2 \u03B5\u03AF\u03BD\u03B1\u03B9 \u03AD\u03C4\u03BF\u03B9\u03BC\u03BF\u03C2.');
                }
            } else {
                $errorMessage = u('\u0394\u03B5\u03BD \u03B2\u03C1\u03AD\u03B8\u03B7\u03BA\u03B5 \u03BB\u03BF\u03B3\u03B1\u03C1\u03B9\u03B1\u03C3\u03BC\u03CC\u03C2 \u03BC\u03B5 \u03B1\u03C5\u03C4\u03CC \u03C4\u03BF email.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle . ' | ' . APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --success-bg:#eef8f0; --success-border:#bfdcc7; --success-text:#255f3a; --shadow:0 24px 60px rgba(17,39,68,.14); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Manrope',sans-serif; color:var(--text); background:radial-gradient(circle at top, rgba(185,134,47,.16), transparent 22%), radial-gradient(circle at left, rgba(52,103,168,.10), transparent 26%), linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%); }
        .page { min-height:100vh; display:grid; place-items:center; padding:32px 18px; }
        .card { width:min(100%,480px); padding:34px 32px; border-radius:28px; background:var(--panel); border:1px solid var(--panel-border); box-shadow:var(--shadow); }
        .brand { display:flex; align-items:center; gap:14px; margin-bottom:28px; font-weight:800; }
        .brand-logo { width:min(148px,44vw); height:min(148px,44vw); padding:12px; border-radius:50%; background:#fff; object-fit:contain; box-sizing:border-box; box-shadow:0 14px 30px rgba(16,54,96,.14); display:block; }
        .brand-copy strong { display:block; font-size:1rem; }
        .brand-copy span { display:block; margin-top:2px; color:var(--muted); font-size:.92rem; font-weight:600; }
        .eyebrow { display:inline-flex; align-items:center; margin-bottom:16px; padding:7px 12px; border-radius:999px; background:rgba(184,134,47,.12); color:var(--accent-dark); font-size:.78rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        h1 { margin:0 0 10px; font-family:'Space Grotesk',sans-serif; font-size:clamp(2rem,4vw,2.6rem); line-height:1.02; }
        .intro { margin:0 0 28px; color:var(--muted); line-height:1.65; font-size:.98rem; }
        .message { margin-bottom:18px; padding:14px 16px; border-radius:14px; line-height:1.55; border:1px solid transparent; }
        .message.error { background:var(--danger-bg); border-color:var(--danger-border); color:var(--danger-text); }
        .success-panel { margin-bottom:22px; padding:18px; border-radius:18px; background:var(--success-bg); border:1px solid var(--success-border); color:var(--success-text); }
        .success-panel h2 { margin:0 0 8px; font-size:1.05rem; }
        .success-panel p { margin:0 0 14px; line-height:1.6; }
        label { display:block; margin:16px 0 8px; color:var(--text); font-weight:800; }
        input { width:100%; padding:15px 16px; border-radius:16px; border:1px solid var(--field-border); background:var(--field); color:var(--text); font-size:1rem; }
        .primary-btn { width:100%; margin-top:26px; padding:15px; border:none; border-radius:16px; cursor:pointer; font-size:1rem; font-weight:800; color:#fff; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--accent),var(--accent-2)); box-shadow:0 18px 32px rgba(184,134,47,.24); }
        .helper-links { margin-top:20px; text-align:center; color:var(--muted); font-size:.95rem; }
        .helper-links a { color:var(--accent-dark); font-weight:800; }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/ichnos-logo.jpg?v=20260402" alt="<?php echo h(APP_NAME); ?> logo">
                <div class="brand-copy">
                    <strong><?php echo h(APP_NAME); ?></strong>
                    <span>&#917;&#960;&#945;&#957;&#945;&#966;&#959;&#961;&#940; &#954;&#969;&#948;&#953;&#954;&#959;&#973; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;&#962;</span>
                </div>
            </div>
            <span class="eyebrow">Forgot Password</span>
            <h1>&#926;&#941;&#967;&#945;&#963;&#945; &#964;&#959;&#957; &#954;&#969;&#948;&#953;&#954;&#972; &#956;&#959;&#965;</h1>
            <p class="intro">&#931;&#965;&#956;&#960;&#955;&#942;&#961;&#969;&#963;&#949; &#964;&#959; email &#964;&#959;&#965; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#959;&#973; &#963;&#959;&#965; &#954;&#945;&#953; &#952;&#945; &#948;&#951;&#956;&#953;&#959;&#965;&#961;&#947;&#951;&#952;&#949;&#943; &#963;&#973;&#957;&#948;&#949;&#963;&#956;&#959;&#962; &#949;&#960;&#945;&#957;&#945;&#966;&#959;&#961;&#940;&#962; &#947;&#953;&#945; &#957;&#941;&#959; &#954;&#969;&#948;&#953;&#954;&#972;.</p>
            <?php if ($resetLink !== ''): ?><div class="success-panel"><h2><?php echo h($successMessage); ?></h2><p>&#935;&#961;&#951;&#963;&#953;&#956;&#959;&#960;&#959;&#943;&#951;&#963;&#949; &#964;&#959;&#957; &#960;&#945;&#961;&#945;&#954;&#940;&#964;&#969; &#963;&#973;&#957;&#948;&#949;&#963;&#956;&#959; &#947;&#953;&#945; &#957;&#945; &#959;&#961;&#943;&#963;&#949;&#953;&#962; &#957;&#941;&#959; &#954;&#969;&#948;&#953;&#954;&#972; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;&#962;.</p><a class="primary-btn" href="<?php echo h($resetLink); ?>">&#924;&#949;&#964;&#940;&#946;&#945;&#963;&#951; &#963;&#964;&#951;&#957; &#949;&#960;&#945;&#957;&#945;&#966;&#959;&#961;&#940;</a></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="message error"><?php echo h($errorMessage); ?></div><?php endif; ?>
            <form method="post" novalidate>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo h($email); ?>" placeholder="name@example.com" autocomplete="email" required>
                <button type="submit" class="primary-btn">&#916;&#951;&#956;&#953;&#959;&#965;&#961;&#947;&#943;&#945; &#963;&#965;&#957;&#948;&#941;&#963;&#956;&#959;&#965;</button>
            </form>
            <div class="helper-links"><a href="login.php">&#917;&#960;&#953;&#963;&#964;&#961;&#959;&#966;&#942; &#963;&#964;&#951; &#931;&#973;&#957;&#948;&#949;&#963;&#951;</a></div>
        </section>
    </main>
</body>
</html>
