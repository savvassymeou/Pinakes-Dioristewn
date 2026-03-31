<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$successMessage = '';
$errorMessage = '';
$specialties = [];

$specialtiesResult = $conn->query('SELECT id, title FROM specialties ORDER BY title ASC');
if ($specialtiesResult) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $identityNumber = normalize_identity_number($_POST['identity_number'] ?? '');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $fatherName = trim((string) ($_POST['father_name'] ?? ''));
    $motherName = trim((string) ($_POST['mother_name'] ?? ''));
    $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
    $specialtyId = (int) ($_POST['specialty_id'] ?? 0);

    if (!ensure_identity_number_column($conn)) {
        $errorMessage = 'Δεν ήταν δυνατή η προετοιμασία του πεδίου αριθμού ταυτότητας.';
    } elseif ($username === '' || $firstName === '' || $lastName === '' || $email === '' || $identityNumber === '' || $password === '' || $confirmPassword === '') {
        $errorMessage = 'Συμπλήρωσε όλα τα υποχρεωτικά πεδία.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Το email δεν είναι έγκυρο.';
    } elseif (!is_valid_identity_number($identityNumber)) {
        $errorMessage = identity_number_validation_message();
    } elseif (!is_valid_username_format($username)) {
        $errorMessage = username_validation_message();
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Οι δύο κωδικοί πρόσβασης δεν ταιριάζουν.';
    } elseif (strlen($password) < 8) {
        $errorMessage = 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
    } elseif ($birthDate !== '' && strtotime($birthDate) === false) {
        $errorMessage = 'Η ημερομηνία γέννησης δεν είναι έγκυρη.';
    } else {
        $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? OR username = ? OR identity_number = ? LIMIT 1');

        if (!$checkStmt) {
            $errorMessage = 'Σφάλμα ελέγχου στοιχείων.';
        } else {
            $checkStmt->bind_param('sss', $email, $username, $identityNumber);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $errorMessage = 'Υπάρχει ήδη λογαριασμός με αυτό το email, username ή αριθμό ταυτότητας.';
            }
            $checkStmt->close();
        }

        if ($errorMessage === '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $validSpecialtyId = null;
            foreach ($specialties as $specialty) {
                if ((int) $specialty['id'] === $specialtyId) {
                    $validSpecialtyId = $specialtyId;
                    break;
                }
            }

            $conn->begin_transaction();
            try {
                $insertStmt = $conn->prepare('INSERT INTO users (username, first_name, last_name, email, identity_number, phone, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if (!$insertStmt) {
                    throw new RuntimeException('Σφάλμα προετοιμασίας εγγραφής.');
                }
                $insertStmt->bind_param('sssssss', $username, $firstName, $lastName, $email, $identityNumber, $phone, $hashedPassword);
                if (!$insertStmt->execute()) {
                    throw new RuntimeException('Σφάλμα κατά την εγγραφή του λογαριασμού.');
                }
                $userId = (int) $conn->insert_id;
                $insertStmt->close();

                if ($validSpecialtyId !== null || $birthDate !== '' || $fatherName !== '' || $motherName !== '') {
                    $profileStmt = $conn->prepare("INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points) VALUES (?, ?, ?, ?, ?, 'Νέα εγγραφή υποψηφίου', NULL, NULL)");
                    if (!$profileStmt) {
                        throw new RuntimeException('Σφάλμα προετοιμασίας προφίλ υποψηφίου.');
                    }
                    $birthDateValue = $birthDate !== '' ? $birthDate : null;
                    $profileStmt->bind_param('isssi', $userId, $fatherName, $motherName, $birthDateValue, $validSpecialtyId);
                    if (!$profileStmt->execute()) {
                        throw new RuntimeException('Σφάλμα συσχέτισης με προφίλ υποψηφίου.');
                    }
                    $profileStmt->close();
                }

                $conn->commit();
                $successMessage = 'Η εγγραφή ολοκληρώθηκε με επιτυχία. Μπορείς τώρα να συνδεθείς στον λογαριασμό σου.';
                $_POST = [];
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = $exception->getMessage();
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
    <title>Εγγραφή | Πίνακες Διοριστέων</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f8; --bg-accent:#dce7f5; --panel:rgba(255,255,255,.96); --panel-border:rgba(21,55,92,.12); --text:#14263d; --muted:#5d7088; --accent:#b8862f; --accent-2:#d9ab55; --accent-dark:#7a5720; --field:#f7f9fc; --field-border:#cfdae8; --danger-bg:#fff1f1; --danger-border:#efc2c2; --danger-text:#8e2f2f; --success-bg:#eef9f0; --success-border:#c8e8cf; --success-text:#25613a; --shadow:0 24px 60px rgba(17,39,68,.14); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Manrope',sans-serif; color:var(--text); background:radial-gradient(circle at top, rgba(185,134,47,.16), transparent 22%), radial-gradient(circle at left, rgba(52,103,168,.10), transparent 26%), linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%); }
        .page { min-height:100vh; display:grid; place-items:center; padding:32px 18px; }
        .card { width:min(100%,760px); padding:34px 32px; border-radius:28px; background:var(--panel); border:1px solid var(--panel-border); box-shadow:var(--shadow); }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; font-weight:800; }
        .brand-mark { width:44px; height:44px; display:grid; place-items:center; border-radius:14px; background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#fff; font-family:'Space Grotesk',sans-serif; box-shadow:0 14px 28px rgba(184,134,47,.22); }
        .brand-copy strong { display:block; font-size:1rem; }
        .brand-copy span { display:block; margin-top:2px; color:var(--muted); font-size:.92rem; font-weight:600; }
        .eyebrow { display:inline-flex; align-items:center; margin-bottom:16px; padding:7px 12px; border-radius:999px; background:rgba(184,134,47,.12); color:var(--accent-dark); font-size:.78rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        h1 { margin:0 0 10px; font-family:'Space Grotesk',sans-serif; font-size:clamp(2rem,4vw,2.6rem); line-height:1.02; }
        .intro { margin:0 0 28px; color:var(--muted); line-height:1.65; font-size:.98rem; max-width:60ch; }
        .message { margin-bottom:18px; padding:14px 16px; border-radius:14px; line-height:1.55; border:1px solid transparent; }
        .message.success { background:var(--success-bg); border-color:var(--success-border); color:var(--success-text); }
        .message.error { background:var(--danger-bg); border-color:var(--danger-border); color:var(--danger-text); }
        .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px 20px; }
        .field-full { grid-column:1 / -1; }
        label { display:block; margin:0 0 8px; color:var(--text); font-weight:800; }
        input, select { width:100%; padding:15px 16px; border-radius:16px; border:1px solid var(--field-border); background:var(--field); color:var(--text); font-size:1rem; }
        .hint { margin-top:8px; color:var(--muted); font-size:.92rem; }
        .availability { margin-top:8px; font-size:.92rem; font-weight:700; min-height:1.2em; }
        .availability.ok { color:#2d7a46; }
        .availability.error { color:#b13333; }
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
        @media (max-width:720px) { .form-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <div class="brand"><span class="brand-mark">EEY</span><div class="brand-copy"><strong>Πίνακες Διοριστέων</strong><span>Δημιουργία νέου λογαριασμού</span></div></div>
            <span class="eyebrow">Create Account</span>
            <h1>Εγγραφή Χρήστη</h1>
            <p class="intro">Συμπλήρωσε τα στοιχεία σου για να δημιουργήσεις νέο λογαριασμό και να αποκτήσεις πρόσβαση στην εφαρμογή.</p>
            <?php if ($successMessage !== ''): ?><div class="message success"><?php echo h($successMessage); ?></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="message error"><?php echo h($errorMessage); ?></div><?php endif; ?>
            <form method="post" novalidate>
                <div class="form-grid">
                    <div><label for="username">Username</label><input id="username" name="username" type="text" value="<?php echo h((string) ($_POST['username'] ?? '')); ?>" placeholder="Μόνο γράμματα" required><div class="hint">Το username πρέπει να περιέχει μόνο γράμματα και να έχει τουλάχιστον 3 χαρακτήρες.</div><div id="usernameAvailability" class="availability"></div></div>
                    <div><label for="email">Email</label><input id="email" name="email" type="email" value="<?php echo h((string) ($_POST['email'] ?? '')); ?>" placeholder="name@example.com" required></div>
                    <div><label for="identity_number">Αριθμός ταυτότητας</label><input id="identity_number" name="identity_number" type="text" value="<?php echo h((string) ($_POST['identity_number'] ?? '')); ?>" placeholder="π.χ. AB123456" required><div class="hint">Μόνο γράμματα και αριθμοί, χωρίς κενά.</div></div>
                    <div><label for="first_name">Όνομα</label><input id="first_name" name="first_name" type="text" value="<?php echo h((string) ($_POST['first_name'] ?? '')); ?>" placeholder="Εισαγωγή ονόματος" required></div>
                    <div><label for="last_name">Επώνυμο</label><input id="last_name" name="last_name" type="text" value="<?php echo h((string) ($_POST['last_name'] ?? '')); ?>" placeholder="Εισαγωγή επωνύμου" required></div>
                    <div><label for="phone">Τηλέφωνο</label><input id="phone" name="phone" type="text" value="<?php echo h((string) ($_POST['phone'] ?? '')); ?>" placeholder="99xxxxxx"></div>
                    <div><label for="specialty_id">Ειδικότητα</label><select id="specialty_id" name="specialty_id"><option value="0">Επιλογή ειδικότητας</option><?php foreach ($specialties as $specialty): ?><option value="<?php echo (int) $specialty['id']; ?>" <?php echo ((int) ($_POST['specialty_id'] ?? 0) === (int) $specialty['id']) ? 'selected' : ''; ?>><?php echo h((string) $specialty['title']); ?></option><?php endforeach; ?></select></div>
                    <div><label for="father_name">Όνομα πατέρα</label><input id="father_name" name="father_name" type="text" value="<?php echo h((string) ($_POST['father_name'] ?? '')); ?>" placeholder="Προαιρετικό"></div>
                    <div><label for="mother_name">Όνομα μητέρας</label><input id="mother_name" name="mother_name" type="text" value="<?php echo h((string) ($_POST['mother_name'] ?? '')); ?>" placeholder="Προαιρετικό"></div>
                    <div><label for="birth_date">Ημερομηνία γέννησης</label><input id="birth_date" name="birth_date" type="date" value="<?php echo h((string) ($_POST['birth_date'] ?? '')); ?>"></div>
                    <div class="field-full"><label for="password">Κωδικός πρόσβασης</label><div class="password-field"><input id="password" name="password" type="password" placeholder="Δημιουργία κωδικού" required><button type="button" class="password-toggle" data-target="password" aria-label="Εμφάνιση κωδικού"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg></button></div><div class="hint">Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 8 χαρακτήρες.</div></div>
                    <div class="field-full"><label for="confirm_password">Επιβεβαίωση κωδικού</label><div class="password-field"><input id="confirm_password" name="confirm_password" type="password" placeholder="Επανάλαβε τον κωδικό" required><button type="button" class="password-toggle" data-target="confirm_password" aria-label="Εμφάνιση κωδικού"><svg class="icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.7A3 3 0 0 0 13.3 13.4"></path><path d="M9.9 5.2A11.4 11.4 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.2 4.2"></path><path d="M6.2 6.3C3.6 8.1 2 12 2 12a17.8 17.2 0 0 0 6.1 5.3A10.8 10.8 0 0 0 12 18c1.2 0 2.4-.2 3.5-.6"></path></svg></button></div></div>
                </div>
                <button type="submit" class="submit-btn">Δημιουργία Λογαριασμού</button>
            </form>
            <div class="helper-links">Έχεις ήδη λογαριασμό; <a href="login.php">Σύνδεση</a></div>
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
        const usernameInput = document.getElementById('username');
        const availability = document.getElementById('usernameAvailability');
        let usernameTimer = null;
        const setAvailability = (message, kind = '') => { availability.textContent = message; availability.className = 'availability' + (kind ? ' ' + kind : ''); };
        const checkUsername = async () => {
            const username = usernameInput.value.trim();
            if (username === '') { setAvailability(''); return; }
            try {
                const response = await fetch('check_username.php?username=' + encodeURIComponent(username), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await response.json();
                setAvailability(data.message || '', data.available ? 'ok' : 'error');
            } catch (error) {
                setAvailability('Δεν ήταν δυνατός ο έλεγχος username αυτή τη στιγμή.', 'error');
            }
        };
        usernameInput.addEventListener('input', () => { window.clearTimeout(usernameTimer); usernameTimer = window.setTimeout(checkUsername, 250); });
    </script>
</body>
</html>
