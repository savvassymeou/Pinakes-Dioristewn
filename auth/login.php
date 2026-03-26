<?php

session_start();

require_once __DIR__ . "/../includes/db.php";

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error_message = "Συμπλήρωσε email και password.";
    } else {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role FROM users WHERE email = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user["password"])) {
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

                $error_message = "Λάθος email ή password.";
            } else {
                $error_message = "Λάθος email ή password.";
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
            font-size: clamp(2.4rem, 5vw, 4.2rem);
            line-height: 0.96;
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
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: #ffd8d8;
            line-height: 1.55;
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
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div class="brand">
                <span class="brand-mark">EEY</span>
                <span>Πίνακες Διοριστέων</span>
            </div>

            <h1>Σύγχρονη πρόσβαση για admin και candidate.</h1>
            <p>
                Η πλατφόρμα σου αποκτά πιο προσεγμένη πρώτη εικόνα, με καθαρή δομή,
                επαγγελματική χρωματική παλέτα και καλύτερη παρουσίαση για την εργασία.
            </p>

            <ul class="hero-points">
                <li>Είσοδος για dashboard διαχείρισης και υποψηφίους</li>
                <li>Καλύτερη οπτική ιεραρχία και πιο premium εμφάνιση</li>
                <li>Responsive layout που φαίνεται σωστά και σε μικρότερες οθόνες</li>
            </ul>
        </section>

        <section class="panel">
            <div class="container">
                <span class="eyebrow">Secure Login</span>
                <h2>Σύνδεση Χρήστη</h2>
                <p class="panel-copy">Συμπλήρωσε το email και τον κωδικό σου για να μπεις στην εφαρμογή.</p>

                <?php if ($error_message !== ""): ?>
                    <div class="message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, "UTF-8"); ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="admin@example.com"
                        value="<?php echo htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                        required
                    >

                    <label for="password">Κωδικός</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Εισαγωγή κωδικού"
                        required
                    >

                    <button type="submit">Είσοδος στην πλατφόρμα</button>
                </form>

                <div class="helper-links">
                    Δεν έχεις λογαριασμό; <a href="register.php">Εγγραφή</a>
                </div>
            </div>
        </section>
    </div>
</body>
</html>

