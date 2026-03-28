<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

$success_message = "";
$error_message = "";
$specialties = [];
$specialties_result = $conn->query("SELECT id, title FROM specialties ORDER BY title ASC");

if ($specialties_result) {
    while ($row = $specialties_result->fetch_assoc()) {
        $specialties[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = normalize_username($_POST["username"] ?? "");
    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $father_name = trim($_POST["father_name"] ?? "");
    $mother_name = trim($_POST["mother_name"] ?? "");
    $birth_date = trim($_POST["birth_date"] ?? "");
    $specialty_id = (int) ($_POST["specialty_id"] ?? 0);

    if ($username === "" || $first_name === "" || $last_name === "" || $email === "" || $password === "") {
        $error_message = "Συμπλήρωσε όλα τα υποχρεωτικά πεδία.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Το email δεν είναι έγκυρο.";
    } elseif (strlen($username) < 3) {
        $error_message = "Το username πρέπει να έχει τουλάχιστον 3 χαρακτήρες.";
    } elseif (strlen($password) < 8) {
        $error_message = "Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
    } elseif ($birth_date !== "" && strtotime($birth_date) === false) {
        $error_message = "Η ημερομηνία γέννησης δεν είναι έγκυρη.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");

        if ($check_stmt) {
            $check_stmt->bind_param("ss", $email, $username);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $error_message = "Υπάρχει ήδη λογαριασμός με αυτό το email ή username.";
            }

            $check_stmt->close();
        } else {
            $error_message = "Σφάλμα ελέγχου στοιχείων: " . $conn->error;
        }

        if ($error_message === "") {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $valid_specialty_id = null;

            foreach ($specialties as $specialty) {
                if ((int) $specialty["id"] === $specialty_id) {
                    $valid_specialty_id = $specialty_id;
                    break;
                }
            }

            $conn->begin_transaction();

            try {
                $insert_stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?, ?)");

                if (!$insert_stmt) {
                    throw new RuntimeException("Σφάλμα προετοιμασίας εγγραφής: " . $conn->error);
                }

                $insert_stmt->bind_param("ssssss", $username, $first_name, $last_name, $email, $phone, $hashed_password);

                if (!$insert_stmt->execute()) {
                    throw new RuntimeException("Σφάλμα κατά την εγγραφή: " . $insert_stmt->error);
                }

                $user_id = (int) $conn->insert_id;
                $insert_stmt->close();

                if ($valid_specialty_id !== null || $birth_date !== "" || $father_name !== "" || $mother_name !== "") {
                    $profile_stmt = $conn->prepare(
                        "INSERT INTO candidate_profiles
                        (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points)
                        VALUES (?, ?, ?, ?, ?, 'Νέα εγγραφή υποψηφίου', NULL, NULL)"
                    );

                    if (!$profile_stmt) {
                        throw new RuntimeException("Σφάλμα προετοιμασίας candidate profile: " . $conn->error);
                    }

                    $birth_date_value = $birth_date !== "" ? $birth_date : null;
                    $profile_stmt->bind_param("isssi", $user_id, $father_name, $mother_name, $birth_date_value, $valid_specialty_id);

                    if (!$profile_stmt->execute()) {
                        throw new RuntimeException("Σφάλμα συσχέτισης με candidate profile: " . $profile_stmt->error);
                    }

                    $profile_stmt->close();
                }

                $conn->commit();
                $success_message = "Η εγγραφή ολοκληρώθηκε με επιτυχία. Μπορείς τώρα να συνδεθείς στον λογαριασμό σου.";
            } catch (Throwable $exception) {
                $conn->rollback();
                $error_message = $exception->getMessage();
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
        :root {
            --bg: #eef3f8;
            --bg-accent: #dce7f5;
            --panel: rgba(255, 255, 255, 0.96);
            --panel-border: rgba(21, 55, 92, 0.12);
            --text: #14263d;
            --muted: #5d7088;
            --accent: #b8862f;
            --accent-2: #d9ab55;
            --accent-dark: #7a5720;
            --field: #f7f9fc;
            --field-border: #cfdae8;
            --danger-bg: #fff1f1;
            --danger-border: #efc2c2;
            --danger-text: #8e2f2f;
            --success-bg: #eef9f0;
            --success-border: #c8e8cf;
            --success-text: #25613a;
            --shadow: 0 24px 60px rgba(17, 39, 68, 0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(185, 134, 47, 0.16), transparent 22%),
                radial-gradient(circle at left, rgba(52, 103, 168, 0.10), transparent 26%),
                linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%);
        }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 32px 18px; }
        .register-card {
            width: min(100%, 760px);
            padding: 34px 32px;
            border-radius: 28px;
            background: var(--panel);
            border: 1px solid var(--panel-border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; font-weight: 800; letter-spacing: 0.01em; }
        .brand-mark {
            width: 44px; height: 44px; display: grid; place-items: center; border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff;
            font-family: "Space Grotesk", sans-serif; box-shadow: 0 14px 28px rgba(184, 134, 47, 0.22);
        }
        .brand-copy strong { display: block; font-size: 1rem; }
        .brand-copy span { display: block; margin-top: 2px; color: var(--muted); font-size: 0.92rem; font-weight: 600; }
        .eyebrow {
            display: inline-flex; align-items: center; margin-bottom: 16px; padding: 7px 12px; border-radius: 999px;
            background: rgba(184, 134, 47, 0.12); color: var(--accent-dark); font-size: 0.78rem; font-weight: 800;
            letter-spacing: 0.06em; text-transform: uppercase;
        }
        h1 { margin: 0 0 10px; font-family: "Space Grotesk", sans-serif; font-size: clamp(2rem, 4vw, 2.6rem); line-height: 1.02; }
        .intro { margin: 0 0 28px; color: var(--muted); line-height: 1.65; font-size: 0.98rem; max-width: 60ch; }
        .message { margin-bottom: 18px; padding: 14px 16px; border-radius: 14px; line-height: 1.55; border: 1px solid transparent; }
        .message.success { background: var(--success-bg); border-color: var(--success-border); color: var(--success-text); }
        .message.error { background: var(--danger-bg); border-color: var(--danger-border); color: var(--danger-text); }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 20px; }
        .field-full { grid-column: 1 / -1; }
        label { display: block; margin: 0 0 8px; color: var(--text); font-weight: 800; }
        input, select {
            width: 100%; padding: 15px 16px; border-radius: 16px; border: 1px solid var(--field-border);
            background: var(--field); color: var(--text); font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; font-family: inherit;
        }
        input::placeholder { color: #8b9cb0; }
        input:focus, select:focus {
            outline: none; border-color: rgba(184, 134, 47, 0.72); box-shadow: 0 0 0 4px rgba(184, 134, 47, 0.12);
            transform: translateY(-1px);
        }
        .field-hint { margin-top: 7px; color: var(--muted); font-size: 0.86rem; line-height: 1.45; }
        .actions { margin-top: 26px; }
        button {
            width: 100%; padding: 15px; border: none; border-radius: 16px; cursor: pointer; font-size: 1rem;
            font-weight: 800; color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 32px rgba(184, 134, 47, 0.24);
        }
        .helper-links { margin-top: 20px; text-align: center; color: var(--muted); font-size: 0.95rem; }
        .helper-links a { color: var(--accent-dark); font-weight: 800; }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } .field-full { grid-column: auto; } }
        @media (max-width: 560px) { .page { padding: 18px 12px; } .register-card { padding: 24px 18px; border-radius: 22px; } }
    </style>
</head>
<body>
    <main class="page">
        <section class="register-card">
            <div class="brand">
                <span class="brand-mark">EEY</span>
                <div class="brand-copy">
                    <strong>Πίνακες Διοριστέων</strong>
                    <span>Δημιουργία νέου λογαριασμού</span>
                </div>
            </div>
            <span class="eyebrow">Create Account</span>
            <h1>Εγγραφή Χρήστη</h1>
            <p class="intro">Συμπλήρωσε τα στοιχεία σου για να δημιουργήσεις νέο λογαριασμό και να αποκτήσεις πρόσβαση στην εφαρμογή.</p>
            <?php if ($success_message !== ""): ?><div class="message success"><?php echo h($success_message); ?></div><?php endif; ?>
            <?php if ($error_message !== ""): ?><div class="message error"><?php echo h($error_message); ?></div><?php endif; ?>
            <form action="" method="POST">
                <div class="form-grid">
                    <div>
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Εισαγωγή username" value="<?php echo h($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="name@example.com" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="first_name">Όνομα</label>
                        <input type="text" id="first_name" name="first_name" placeholder="Εισαγωγή ονόματος" value="<?php echo h($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="last_name">Επώνυμο</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Εισαγωγή επωνύμου" value="<?php echo h($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="phone">Τηλέφωνο</label>
                        <input type="text" id="phone" name="phone" placeholder="99xxxxxx" value="<?php echo h($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="specialty_id">Ειδικότητα</label>
                        <select id="specialty_id" name="specialty_id">
                            <option value="0">Επιλογή ειδικότητας</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo (int) $specialty['id']; ?>" <?php echo (int) ($_POST['specialty_id'] ?? 0) === (int) $specialty['id'] ? 'selected' : ''; ?>><?php echo h($specialty['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="father_name">Όνομα πατέρα</label>
                        <input type="text" id="father_name" name="father_name" placeholder="Προαιρετικό" value="<?php echo h($_POST['father_name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="mother_name">Όνομα μητέρας</label>
                        <input type="text" id="mother_name" name="mother_name" placeholder="Προαιρετικό" value="<?php echo h($_POST['mother_name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="birth_date">Ημερομηνία γέννησης</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?php echo h($_POST['birth_date'] ?? ''); ?>">
                    </div>
                    <div class="field-full">
                        <label for="password">Κωδικός πρόσβασης</label>
                        <input type="password" id="password" name="password" placeholder="Δημιουργία κωδικού" required>
                        <div class="field-hint">Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 8 χαρακτήρες.</div>
                    </div>
                </div>
                <div class="actions"><button type="submit">Δημιουργία λογαριασμού</button></div>
            </form>
            <div class="helper-links">Έχεις ήδη λογαριασμό; <a href="login.php">Σύνδεση</a></div>
        </section>
    </main>
</body>
</html>