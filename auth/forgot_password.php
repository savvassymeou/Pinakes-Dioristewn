<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Επαναφορά Κωδικού';
$errorMessage = '';
$successMessage = '';
$resetLink = '';
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($email === '') {
        $errorMessage = 'Συμπλήρωσε το email του λογαριασμού σου.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Δώσε έγκυρη διεύθυνση email.';
    } elseif (!ensure_password_reset_tokens_table($conn)) {
        $errorMessage = 'Δεν ήταν δυνατή η δημιουργία αιτήματος επαναφοράς αυτή τη στιγμή.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');

        if (!$stmt) {
            $errorMessage = 'Δεν ήταν δυνατή η προετοιμασία του αιτήματος επαναφοράς.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user) {
                $token = create_password_reset_token($conn, (int) $user['id']);

                if ($token === null) {
                    $errorMessage = 'Δεν ήταν δυνατή η δημιουργία συνδέσμου επαναφοράς.';
                } else {
                    $resetLink = build_password_reset_link($token);
                    $successMessage = 'Ο σύνδεσμος επαναφοράς είναι έτοιμος.';
                }
            } else {
                $errorMessage = 'Δεν βρέθηκε λογαριασμός με αυτό το email.';
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
    <title><?php echo h($pageTitle); ?> | Πίνακες Διοριστέων</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --success-bg:#eef8f0; --success-border:#bfdcc7; --success-text:#255f3a; --shadow:0 24px 60px rgba(17,39,68,.14); }
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
            <div class="brand"><span class="brand-mark">EEY</span><div class="brand-copy"><strong>Πίνακες Διοριστέων</strong><span>Επαναφορά κωδικού πρόσβασης</span></div></div>
            <span class="eyebrow">Forgot Password</span>
            <h1>Ξέχασα τον Κωδικό</h1>
            <p class="intro">Συμπλήρωσε το email σου και το σύστημα θα ετοιμάσει σύνδεσμο επαναφοράς για να ορίσεις νέο κωδικό.</p>
            <?php if ($resetLink !== ''): ?><div class="success-panel"><h2><?php echo h($successMessage); ?></h2><p>Συνέχισε στο επόμενο βήμα για να ορίσεις νέο κωδικό πρόσβασης.</p><a class="primary-btn" href="<?php echo h($resetLink); ?>">Άνοιγμα συνδέσμου επαναφοράς</a></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="message error"><?php echo h($errorMessage); ?></div><?php endif; ?>
            <form method="post" novalidate>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo h($email); ?>" placeholder="name@example.com" autocomplete="email" required>
                <button type="submit" class="primary-btn">Δημιουργία Συνδέσμου</button>
            </form>
            <div class="helper-links"><a href="login.php">Επιστροφή στη Σύνδεση</a></div>
        </section>
    </main>
</body>
</html>
