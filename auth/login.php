<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identity === '' || $password === '') {
        $errorMessage = 'Συμπλήρωσε email ή username και κωδικό πρόσβασης.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, username, first_name, last_name, email, password_hash, role
             FROM users
             WHERE email = ? OR username = ?
             LIMIT 1'
        );

        if (!$stmt) {
            $errorMessage = 'Σφάλμα σύνδεσης με τη βάση δεδομένων.';
        } else {
            $stmt->bind_param('ss', $identity, $identity);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user && password_verify($password, (string) $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = (string) $user['username'];
                $_SESSION['first_name'] = (string) ($user['first_name'] ?? '');
                $_SESSION['last_name'] = (string) ($user['last_name'] ?? '');
                $_SESSION['email'] = (string) ($user['email'] ?? '');
                $_SESSION['role'] = (string) ($user['role'] ?? ROLE_CANDIDATE);

                header('Location: ../index.php');
                exit;
            }

            $errorMessage = 'Λάθος στοιχεία σύνδεσης.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύνδεση | Πίνακες Διοριστέων</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --shadow:0 24px 60px rgba(17,39,68,.14); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Manrope',sans-serif; color:var(--text); background:radial-gradient(circle at top, rgba(185,134,47,.16), transparent 22%), radial-gradient(circle at left, rgba(52,103,168,.10), transparent 26%), linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%); }
        .page { min-height:100vh; display:grid; place-items:center; padding:32px 18px; }
        .card { width:min(100%,480px); padding:34px 32px; border-radius:28px; background:var(--panel); border:1px solid var(--panel-border); box-shadow:var(--shadow); }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; font-weight:800; }
        .brand-mark { width:44px; height:44px; display:grid; place-items:center; border-radius:14px; background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#fff; font-family:'Space Grotesk',sans-serif; box-shadow:0 14px 28px rgba(184,134,47,.22); }
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
        .password-toggle .icon-hide { display:none; }
        .password-toggle.is-visible .icon-show { display:none; }
        .password-toggle.is-visible .icon-hide { display:block; }
        .submit-btn { width:100%; margin-top:26px; padding:15px; border:none; border-radius:16px; cursor:pointer; font-size:1rem; font-weight:800; color:#fff; background:linear-gradient(135deg,var(--accent),var(--accent-2)); box-shadow:0 18px 32px rgba(184,134,47,.24); }
        .helper-links { margin-top:20px; text-align:center; color:var(--muted); font-size:.95rem; }
        .helper-links a { color:var(--accent-dark); font-weight:800; }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <div class="brand"><span class="brand-mark">EEY</span><div class="brand-copy"><strong>Πίνακες Διοριστέων</strong><span>Ασφαλής πρόσβαση χρηστών</span></div></div>
            <span class="eyebrow">Secure Login</span>
            <h1>Σύνδεση</h1>
            <p class="intro">Συμπλήρωσε email ή username και τον κωδικό πρόσβασής σου για να μπεις στην εφαρμογή.</p>
            <?php if ($errorMessage !== ''): ?><div class="message"><?php echo h($errorMessage); ?></div><?php endif; ?>
            <form method="post" novalidate>
                <label for="identity">Email ή Username</label>
                <input id="identity" name="identity" type="text" value="<?php echo h((string) ($_POST['identity'] ?? '')); ?>" placeholder="name@example.com ή username" autocomplete="username" required>
                <label for="password">Κωδικός πρόσβασης</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" placeholder="Εισαγωγή κωδικού" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Εμφάνιση κωδικού">
                        <svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg>
                    </button>
                </div>
                <button type="submit" class="submit-btn">Σύνδεση</button>
            </form>
            <div class="helper-links">Δεν έχεις λογαριασμό; <a href="register.php">Εγγραφή</a><br><a href="forgot_password.php">Ξέχασα τον κωδικό μου</a></div>
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
