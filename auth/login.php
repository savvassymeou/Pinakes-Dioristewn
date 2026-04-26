<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect_to_dashboard_by_role('../modules/admin/admindashboard.php', '../modules/candidate/candidatedashboard.php', 'login.php');
}

$errorMessage = '';
$successMessage = isset($_GET['registered']) && $_GET['registered'] === '1'
    ? u('\u0397 \u03B5\u03B3\u03B3\u03C1\u03B1\u03C6\u03AE \u03BF\u03BB\u03BF\u03BA\u03BB\u03B7\u03C1\u03CE\u03B8\u03B7\u03BA\u03B5 \u03B5\u03C0\u03B9\u03C4\u03C5\u03C7\u03CE\u03C2. \u039C\u03C0\u03BF\u03C1\u03B5\u03AF\u03C2 \u03C4\u03CE\u03C1\u03B1 \u03BD\u03B1 \u03C3\u03C5\u03BD\u03B4\u03B5\u03B8\u03B5\u03AF\u03C2.')
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $genericError = u('\u039B\u03B1\u03BD\u03B8\u03B1\u03C3\u03BC\u03AD\u03BD\u03B1 \u03C3\u03C4\u03BF\u03B9\u03C7\u03B5\u03AF\u03B1 \u03C3\u03CD\u03BD\u03B4\u03B5\u03C3\u03B7\u03C2.');

    if ($email === '' || $password === '') {
        $errorMessage = $genericError;
    } else {
        $stmt = $conn->prepare(
            'SELECT *
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        if (!$stmt) {
            $errorMessage = $genericError;
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user && password_verify($password, (string) $user['password_hash'])) {
                $profileStmt = $conn->prepare(
                    'SELECT first_name, last_name
                     FROM user_profiles
                     WHERE user_id = ?
                     LIMIT 1'
                );
                $profile = null;

                if ($profileStmt) {
                    $userId = (int) $user['id'];
                    $profileStmt->bind_param('i', $userId);
                    $profileStmt->execute();
                    $profileResult = $profileStmt->get_result();
                    $profile = $profileResult ? $profileResult->fetch_assoc() : null;
                    $profileStmt->close();
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['role'] = (string) ($user['role'] ?? ROLE_CANDIDATE);
                $_SESSION['username'] = (string) $user['username'];
                $_SESSION['email'] = (string) ($user['email'] ?? '');
                $_SESSION['first_name'] = (string) ($profile['first_name'] ?? '');
                $_SESSION['last_name'] = (string) ($profile['last_name'] ?? '');

                header('Location: ../index.php');
                exit;
            }

            $errorMessage = $genericError;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(u('\u03A3\u03CD\u03BD\u03B4\u03B5\u03C3\u03B7') . ' | ' . APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --shadow:0 24px 60px rgba(17,39,68,.14); }
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
        .message { margin-bottom:18px; padding:14px 16px; border-radius:14px; line-height:1.55; border:1px solid var(--danger-border); background:var(--danger-bg); color:var(--danger-text); }
        label { display:block; margin:16px 0 8px; color:var(--text); font-weight:800; }
        input { width:100%; padding:15px 16px; border-radius:16px; border:1px solid var(--field-border); background:var(--field); color:var(--text); font-size:1rem; }
        .password-field { position:relative; }
        .password-field input { padding-right:68px; }
        .password-toggle { position:absolute; top:50%; right:12px; transform:translateY(-50%); width:42px; height:42px; border:1px solid var(--field-border); border-radius:999px; background:#fff; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; color:var(--accent-dark); box-shadow:0 6px 16px rgba(20,38,61,.08); }
        .password-toggle svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
        .password-toggle .icon-show { display:none; }
        .password-toggle .icon-hide { display:block; }
        .password-toggle.is-visible .icon-show { display:block; }
        .password-toggle.is-visible .icon-hide { display:none; }
        .submit-btn { width:100%; margin-top:26px; padding:15px; border:none; border-radius:16px; cursor:pointer; font-size:1rem; font-weight:800; color:#fff; background:linear-gradient(135deg,var(--accent),var(--accent-2)); box-shadow:0 18px 32px rgba(184,134,47,.24); }
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
                    <span>&#913;&#963;&#966;&#945;&#955;&#942;&#962; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951; &#967;&#961;&#951;&#963;&#964;&#974;&#957;</span>
                </div>
            </div>
            <span class="eyebrow">Secure Login</span>
            <h1>&#931;&#973;&#957;&#948;&#949;&#963;&#951;</h1>
            <p class="intro">&#931;&#965;&#956;&#960;&#955;&#942;&#961;&#969;&#963;&#949; &#964;&#959; email &#954;&#945;&#953; &#964;&#959;&#957; &#954;&#969;&#948;&#953;&#954;&#972; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#942;&#962; &#963;&#959;&#965; &#947;&#953;&#945; &#957;&#945; &#956;&#960;&#949;&#953;&#962; &#963;&#964;&#951;&#957; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;.</p>
            <?php if ($successMessage !== ''): ?>
                <div class="message" style="background:#eef9f0;border-color:#c8e8cf;color:#25613a;"><?php echo h($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="message"><?php echo h($errorMessage); ?></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?php echo h((string) ($_POST['email'] ?? '')); ?>" placeholder="name@example.com" autocomplete="username" required>
                <label for="password">&#922;&#969;&#948;&#953;&#954;&#972;&#962; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;&#962;</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" placeholder="&#917;&#953;&#963;&#945;&#947;&#969;&#947;&#942; &#954;&#969;&#948;&#953;&#954;&#959;&#973;" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="&#917;&#956;&#966;&#940;&#957;&#953;&#963;&#951; &#954;&#969;&#948;&#953;&#954;&#959;&#973;">
                        <svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg>
                    </button>
                </div>
                <button type="submit" class="submit-btn">&#931;&#973;&#957;&#948;&#949;&#963;&#951;</button>
            </form>
            <div class="helper-links">
                &#916;&#949;&#957; &#941;&#967;&#949;&#953;&#962; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972;; <a href="register.php">&#917;&#947;&#947;&#961;&#945;&#966;&#942;</a><br>
                <a href="forgot_password.php">&#926;&#941;&#967;&#945;&#963;&#945; &#964;&#959;&#957; &#954;&#969;&#948;&#953;&#954;&#972; &#956;&#959;&#965;</a>
            </div>
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
