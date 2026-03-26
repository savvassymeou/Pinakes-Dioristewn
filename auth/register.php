<?php

require_once __DIR__ . "/../includes/db.php";

$success_message = "";
$error_message = "";
$specialties = [];
$specialties_result = $conn->query("SELECT id, title FROM specialties ORDER BY title ASC");

if ($specialties_result instanceof mysqli_result) {
    while ($row = $specialties_result->fetch_assoc()) {
        $specialties[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $father_name = trim($_POST["father_name"] ?? "");
    $mother_name = trim($_POST["mother_name"] ?? "");
    $birth_date = trim($_POST["birth_date"] ?? "");
    $specialty_id = (int) ($_POST["specialty_id"] ?? 0);

    if ($first_name === "" || $last_name === "" || $email === "" || $password === "") {
        $error_message = "Συμπλήρωσε όλα τα πεδία.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Το email δεν είναι έγκυρο.";
    } elseif (strlen($password) < 8) {
        $error_message = "Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
    } elseif ($birth_date !== "" && strtotime($birth_date) === false) {
        $error_message = "Η ημερομηνία γέννησης δεν είναι έγκυρη.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");

        if ($check_stmt) {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $error_message = "Υπάρχει ήδη λογαριασμός με αυτό το email.";
            }

            $check_stmt->close();
        } else {
            $error_message = "Σφάλμα ελέγχου email: " . $conn->error;
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
                $insert_stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password) VALUES (?, ?, ?, ?, ?)");

                if (!$insert_stmt) {
                    throw new RuntimeException("Σφάλμα προετοιμασίας εγγραφής: " . $conn->error);
                }

                $insert_stmt->bind_param("sssss", $first_name, $last_name, $email, $phone, $hashed_password);

                if (!$insert_stmt->execute()) {
                    throw new RuntimeException("Σφάλμα κατά την εγγραφή: " . $insert_stmt->error);
                }

                $user_id = (int) $conn->insert_id;
                $insert_stmt->close();

                if ($valid_specialty_id !== null || $birth_date !== "" || $father_name !== "" || $mother_name !== "") {
                    $profile_stmt = $conn->prepare("
                        INSERT INTO candidate_profiles
                        (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points)
                        VALUES (?, ?, ?, ?, ?, 'Νέα εγγραφή υποψηφίου', NULL, NULL)
                    ");

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
                $success_message = "Η εγγραφή ολοκληρώθηκε με επιτυχία και ο λογαριασμός συνδέθηκε με candidate profile.";
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
            --bg-1: #07111f;
            --bg-2: #132743;
            --panel: rgba(8, 17, 31, 0.8);
            --panel-soft: rgba(255, 255, 255, 0.05);
            --panel-border: rgba(255, 255, 255, 0.11);
            --text: #eef4ff;
            --muted: #a7b6cc;
            --accent: #d8a13f;
            --accent-2: #f2ca75;
            --field: rgba(255, 255, 255, 0.08);
            --field-border: rgba(255, 255, 255, 0.15);
            --danger-bg: rgba(173, 58, 58, 0.2);
            --danger-border: rgba(245, 138, 138, 0.2);
            --success-bg: rgba(49, 138, 94, 0.2);
            --success-border: rgba(125, 232, 170, 0.2);
            --shadow: 0 32px 70px rgba(0, 0, 0, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--text);
            font-family: "Manrope", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(216, 161, 63, 0.24), transparent 24%),
                radial-gradient(circle at 85% 15%, rgba(92, 145, 255, 0.18), transparent 20%),
                linear-gradient(135deg, var(--bg-1) 0%, #102038 45%, var(--bg-2) 100%);
        }

        .shell {
            width: min(1080px, 100%);
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            border-radius: 28px;
            overflow: hidden;
            background: rgba(4, 10, 20, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .hero {
            position: relative;
            padding: 54px 48px;
            background:
                linear-gradient(180deg, rgba(18, 35, 60, 0.96), rgba(8, 17, 31, 0.98));
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: 24px;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 56px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #17253c;
            font-family: "Space Grotesk", sans-serif;
            box-shadow: 0 14px 28px rgba(216, 161, 63, 0.2);
        }

        .hero h1 {
            margin: 0 0 16px;
            max-width: 8ch;
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(2.3rem, 5vw, 4rem);
            line-height: 0.98;
        }

        .hero p {
            max-width: 46ch;
            margin: 0 0 34px;
            color: var(--muted);
            line-height: 1.7;
            font-size: 1.02rem;
        }

        .hero-points {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 14px;
        }

        .hero-points li {
            padding: 15px 16px;
            border-radius: 16px;
            background: var(--panel-soft);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 56px 42px;
            background: var(--panel);
        }

        .container {
            width: 100%;
            max-width: 420px;
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(216, 161, 63, 0.14);
            color: #f3cf87;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .panel h2 {
            margin: 0 0 8px;
            font-family: "Space Grotesk", sans-serif;
            font-size: 2.2rem;
        }

        .panel-copy {
            margin: 0 0 28px;
            color: var(--muted);
            line-height: 1.7;
        }

        label {
            display: block;
            margin: 16px 0 8px;
            color: #dbe6f7;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 15px 16px;
            border-radius: 16px;
            border: 1px solid var(--field-border);
            background: var(--field);
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        input::placeholder {
            color: #8ea1bc;
        }

        input:focus {
            outline: none;
            border-color: rgba(216, 161, 63, 0.8);
            box-shadow: 0 0 0 4px rgba(216, 161, 63, 0.14);
            transform: translateY(-1px);
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 15px;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 800;
            color: #12243c;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 32px rgba(184, 124, 34, 0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 36px rgba(184, 124, 34, 0.34);
            filter: brightness(1.03);
        }

        .message {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            line-height: 1.55;
            border: 1px solid transparent;
        }

        .success {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: #d6ffe5;
        }

        .error {
            background: var(--danger-bg);
            border-color: var(--danger-border);
            color: #ffd8d8;
        }

        .helper-links {
            margin-top: 18px;
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .helper-links a {
            color: #f3cf87;
            text-decoration: none;
        }

        .helper-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 920px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 40px 28px 28px;
            }

            .panel {
                padding: 34px 24px 40px;
            }

            .brand {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 560px) {
            body {
                padding: 14px;
            }

            .hero h1,
            .panel h2 {
                font-size: 1.95rem;
            }
        }

        .form-grid-local {
            display: grid;
            gap: 14px;
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div class="brand">
                <span class="brand-mark">EEY</span>
                <span>Πίνακες Διοριστέων</span>
            </div>

            <h1>Νέα εγγραφή με το ίδιο επαγγελματικό ύφος.</h1>
            <p>
                Δημιούργησε λογαριασμό μέσα από ένα καθαρό, προσεγμένο περιβάλλον που
                ταιριάζει απόλυτα με τη σελίδα σύνδεσης και κάνει την εφαρμογή πιο ολοκληρωμένη.
            </p>

            <ul class="hero-points">
                <li>Ομοιόμορφο design με το login page</li>
                <li>Καθαρή φόρμα εγγραφής με μοντέρνα εμφάνιση</li>
                <li>Success και error μηνύματα με πιο επαγγελματική παρουσίαση</li>
            </ul>
        </section>

        <section class="panel">
            <div class="container">
                <span class="eyebrow">Create Account</span>
                <h2>Εγγραφή Χρήστη</h2>
                <p class="panel-copy">Συμπλήρωσε τα στοιχεία σου για να δημιουργήσεις νέο λογαριασμό.</p>

                <?php if ($success_message !== ""): ?>
                    <div class="message success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, "UTF-8"); ?></div>
                <?php endif; ?>

                <?php if ($error_message !== ""): ?>
                    <div class="message error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, "UTF-8"); ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-grid-local">
                        <div>
                            <label for="first_name">Όνομα</label>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                placeholder="Εισαγωγή ονόματος"
                                value="<?php echo htmlspecialchars($_POST["first_name"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="last_name">Επώνυμο</label>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                placeholder="Εισαγωγή επωνύμου"
                                value="<?php echo htmlspecialchars($_POST["last_name"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="email">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="name@example.com"
                                value="<?php echo htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="phone">Τηλέφωνο</label>
                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                placeholder="99xxxxxx"
                                value="<?php echo htmlspecialchars($_POST["phone"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div>
                            <label for="father_name">Όνομα πατέρα</label>
                            <input
                                type="text"
                                id="father_name"
                                name="father_name"
                                placeholder="Προαιρετικό"
                                value="<?php echo htmlspecialchars($_POST["father_name"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div>
                            <label for="mother_name">Όνομα μητέρας</label>
                            <input
                                type="text"
                                id="mother_name"
                                name="mother_name"
                                placeholder="Προαιρετικό"
                                value="<?php echo htmlspecialchars($_POST["mother_name"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div>
                            <label for="birth_date">Ημερομηνία γέννησης</label>
                            <input
                                type="date"
                                id="birth_date"
                                name="birth_date"
                                value="<?php echo htmlspecialchars($_POST["birth_date"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div>
                            <label for="specialty_id">Ειδικότητα</label>
                            <select id="specialty_id" name="specialty_id">
                                <option value="0">Επιλογή ειδικότητας</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo (int) ($_POST["specialty_id"] ?? 0) === (int) $specialty["id"] ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($specialty["title"], ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="password">Κωδικός</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Δημιουργία κωδικού"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit">Δημιουργία λογαριασμού</button>
                </form>

                <div class="helper-links">
                    Έχεις ήδη λογαριασμό; <a href="login.php">Σύνδεση</a>
                </div>
            </div>
        </section>
    </div>
</body>
</html>

