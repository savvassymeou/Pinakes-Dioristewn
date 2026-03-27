<?php

session_start();

require_once __DIR__ . "/../includes/db.php";

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error_message = "Συμπλήρωσε email και κωδικό πρόσβασης.";
    } else {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role FROM users WHERE email = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["first_name"] = $user["first_name"];
                    $_SESSION["last_name"] = $user["last_name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    if ($user["role"] === "admin") {
                        header("Location: ../Admin/admindashboard.php");
                        exit;
                    }

                    header("Location: ../Candidate/candidatedashboard.php");
                    exit;
                }

                $error_message = "Λάθος email ή κωδικός πρόσβασης.";
            } else {
                $error_message = "Λάθος email ή κωδικός πρόσβασης.";
            }

            $stmt->close();
        } else {
            $error_message = "Σφάλμα σύνδεσης με τη βάση: " . $conn->error;
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
            --shadow: 0 24px 60px rgba(17, 39, 68, 0.14);
        }

        * {
            box-sizing: border-box;
        }

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

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 18px;
        }

        .login-card {
            width: min(100%, 480px);
            padding: 34px 32px;
            border-radius: 28px;
            background: var(--panel);
            border: 1px solid var(--panel-border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            font-family: "Space Grotesk", sans-serif;
            box-shadow: 0 14px 28px rgba(184, 134, 47, 0.22);
        }

        .brand-copy strong {
            display: block;
            font-size: 1rem;
        }

        .brand-copy span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            margin-bottom: 16px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(184, 134, 47, 0.12);
            color: var(--accent-dark);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 10px;
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(2rem, 4vw, 2.6rem);
            line-height: 1.02;
        }

        .intro {
            margin: 0 0 28px;
            color: var(--muted);
            line-height: 1.65;
            font-size: 0.98rem;
        }

        label {
            display: block;
            margin: 16px 0 8px;
            color: var(--text);
            font-weight: 800;
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
            color: #8b9cb0;
        }

        input:focus {
            outline: none;
            border-color: rgba(184, 134, 47, 0.72);
            box-shadow: 0 0 0 4px rgba(184, 134, 47, 0.12);
            transform: translateY(-1px);
        }

        button {
            width: 100%;
            margin-top: 26px;
            padding: 15px;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 32px rgba(184, 134, 47, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 36px rgba(184, 134, 47, 0.3);
            filter: brightness(1.03);
        }

        .message {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            line-height: 1.55;
        }

        .helper-links {
            margin-top: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .helper-links a {
            color: var(--accent-dark);
            font-weight: 800;
            text-decoration: none;
        }

        .helper-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 560px) {
            .page {
                padding: 18px 12px;
            }

            .login-card {
                padding: 24px 18px;
                border-radius: 22px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="login-card">
            <div class="brand">
                <span class="brand-mark">EEY</span>
                <div class="brand-copy">
                    <strong>Πίνακες Διοριστέων</strong>
                    <span>Σύστημα πρόσβασης χρηστών</span>
                </div>
            </div>

            <span class="eyebrow">Secure Login</span>
            <h1>Σύνδεση Χρήστη</h1>
            <p class="intro">
                Συμπλήρωσε το email και τον κωδικό πρόσβασής σου για να συνδεθείς στην εφαρμογή.
            </p>

            <?php if ($error_message !== ""): ?>
                <div class="message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, "UTF-8"); ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="name@example.com"
                    value="<?php echo htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                    required
                >

                <label for="password">Κωδικός πρόσβασης</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Εισαγωγή κωδικού"
                    required
                >

                <button type="submit">Σύνδεση</button>
            </form>

            <div class="helper-links">
                Δεν έχεις λογαριασμό; <a href="register.php">Εγγραφή</a>
            </div>
        </section>
    </main>
</body>
</html>
