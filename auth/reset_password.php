<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$errorMessage = '';
$successMessage = '';
$resetRequest = null;

if (!ensure_password_reset_tokens_table($conn)) {
    $errorMessage = 'Δεν ήταν δυνατή η προετοιμασία της επαναφοράς κωδικού.';
} elseif ($token === '') {
    $errorMessage = 'Λείπει το token επαναφοράς κωδικού.';
} else {
    $resetRequest = find_valid_password_reset($conn, $token);
    if (!$resetRequest) {
        $errorMessage = 'Ο σύνδεσμος επαναφοράς δεν είναι έγκυρος ή έχει λήξει.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '' && $resetRequest) {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirmPassword === '') {
        $errorMessage = 'Συμπλήρωσε και τα δύο πεδία κωδικού.';
    } elseif (strlen($password) < 8) {
        $errorMessage = 'Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Η επιβεβαίωση κωδικού δεν ταιριάζει.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');

        if (!$updateStmt) {
            $errorMessage = 'Δεν ήταν δυνατή η αποθήκευση του νέου κωδικού.';
        } else {
            $userId = (int) $resetRequest['user_id'];
            $updateStmt->bind_param('si', $hashedPassword, $userId);

            if ($updateStmt->execute()) {
                mark_password_reset_used($conn, (int) $resetRequest['id']);
                $successMessage = 'Ο κωδικός σου άλλαξε επιτυχώς. Τώρα μπορείς να συνδεθείς με τον νέο κωδικό.';
                $resetRequest = null;
            } else {
                $errorMessage = 'Η αλλαγή κωδικού απέτυχε.';
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
    <title>Νέος Κωδικός | Πίνακες Διοριστέων</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --success-bg:#eef9f0; --success-border:#c8e8cf; --success-text:#25613a; --shadow:0 24px 60px rgba(17,39,68,.14); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Manrope',sans-serif; color:var(--text); background:radial-gradient(circle at top, rgba(185,134,47,.16), transparent 22%), radial-gradient(circle at left, rgba(52,103,168,.10), transparent 26%), linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%); }
        .page { min-height:100vh; display:grid; place-items:center; padding:32px 18px; }
        .card { width:min(100%,520px); padding:34px 32px; border-radius:28px; background:var(--panel); border:1px solid var(--panel-border); box-shadow:var(--shadow); }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; font-weight:800; }
        .brand-mark { width:44px; height:44px; display:grid; place-items:center; border-radius:14px; background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#fff; font-family:'Space Grotesk',sans-serif; box-shadow:0 14px 28px rgba(184,134,47,.22); }
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
            <div class="brand"><span class="brand-mark">EEY</span><div class="brand-copy"><strong>Πίνακες Διοριστέων</strong><span>Ορισμός νέου κωδικού</span></div></div>
            <span class="eyebrow">Reset Password</span>
            <h1>Νέος Κωδικός</h1>
            <p class="intro">Όρισε νέο κωδικό πρόσβασης για τον λογαριασμό σου.</p>
            <?php if ($successMessage !== ''): ?><div class="message success"><?php echo h($successMessage); ?></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="message error"><?php echo h($errorMessage); ?></div><?php endif; ?>
            <?php if ($resetRequest && $successMessage === ''): ?>
                <form method="post" action="">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <label for="password">Νέος κωδικός</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" placeholder="Τουλάχιστον 8 χαρακτήρες" required>
                        <button type="button" class="password-toggle" data-target="password" aria-label="Εμφάνιση κωδικού">
                            <svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg>
                        </button>
                    </div>
                    <label for="confirm_password">Επιβεβαίωση κωδικού</label>
                    <div class="password-field">
                        <input id="confirm_password" name="confirm_password" type="password" placeholder="Επανάλαβε τον νέο κωδικό" required>
                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Εμφάνιση κωδικού">
                            <svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg>
                        </button>
                    </div>
                    <button type="submit" class="submit-btn">Αλλαγή Κωδικού</button>
                </form>
            <?php endif; ?>
            <div class="helper-links"><a href="login.php">Επιστροφή στη Σύνδεση</a></div>
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
                button.setAttribute('aria-label', visible ? 'Απόκρυψη κωδικού' : 'Εμφάνιση κωδικού');
            });
        });
    </script>
</body>
</html>
