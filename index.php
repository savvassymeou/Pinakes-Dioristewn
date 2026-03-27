<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

$role = current_user_role();
$isGuest = $role === null;
$isAdmin = $role === ROLE_ADMIN;
$isCandidate = $role === ROLE_CANDIDATE;

$dashboardHref = $isAdmin ? 'Admin/admindashboard.php' : 'Candidate/candidatedashboard.php';
$dashboardLabel = $isAdmin ? 'Admin Module' : 'Candidate Module';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(APP_NAME); ?> | Αρχική</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f8;
            --surface: #ffffff;
            --surface-soft: #fafbfc;
            --border: #d7dde5;
            --text: #21344d;
            --muted: #5f6f83;
            --accent: #b08a42;
            --accent-dark: #7a5c24;
            --topbar: #2f2c2a;
            --topbar-soft: #403b38;
            --brand-blue: #1f97d4;
            --shadow: 0 18px 38px rgba(34, 51, 74, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #f5f7fa 0%, #edf2f7 100%);
        }

        a { color: inherit; text-decoration: none; }
        .container { width: min(1280px, calc(100% - 32px)); margin: 0 auto; }

        .top-strip {
            height: 10px;
            background: linear-gradient(90deg, var(--brand-blue), #35b4ea);
        }

        .portal-header {
            background: var(--topbar);
            color: #f8fafc;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.14);
        }

        .portal-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 72px;
        }

        .portal-brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            padding: 10px 0;
            flex-shrink: 0;
        }

        .portal-mark {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #9f7a2e, #c6a555);
            color: #fff;
            font-weight: 800;
            font-family: "Space Grotesk", sans-serif;
        }

        .portal-brand-text strong {
            display: block;
            font-size: 1rem;
            letter-spacing: 0.01em;
        }

        .portal-brand-text span {
            display: block;
            color: rgba(255,255,255,0.74);
            font-size: 0.85rem;
        }

        .portal-nav {
            display: flex;
            align-items: stretch;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .portal-nav a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 72px;
            padding: 0 18px;
            font-weight: 700;
            border-left: 1px solid rgba(255,255,255,0.08);
            transition: background 0.2s ease;
        }

        .portal-nav a:hover,
        .portal-nav a.is-active {
            background: var(--topbar-soft);
        }

        .portal-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 12px;
            flex-wrap: wrap;
        }

        .portal-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.08);
            color: #fff;
            font-weight: 800;
        }

        .portal-btn-primary {
            background: linear-gradient(135deg, #b08a42, #cfaf68);
            border-color: rgba(255,255,255,0.12);
            color: #fff;
        }

        .portal-body {
            padding: 26px 0 40px;
        }

        .breadcrumb {
            padding: 0 0 18px;
            color: var(--muted);
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 34px;
            align-items: start;
        }

        .panel-title {
            margin: 0 0 12px;
            padding-bottom: 10px;
            font-size: 1.05rem;
            border-bottom: 1px solid #aeb7c2;
        }

        .welcome-panel,
        .social-panel,
        .media-panel,
        .quick-panel,
        .notice-panel {
            background: rgba(255,255,255,0.82);
            border: 1px solid rgba(33, 52, 77, 0.1);
            border-radius: 0;
            padding: 20px 0 24px;
        }

        .welcome-box {
            display: grid;
            grid-template-columns: 190px 1fr;
            gap: 24px;
            align-items: start;
            padding-top: 8px;
        }

        .welcome-logo {
            display: grid;
            place-items: center;
            gap: 10px;
            padding: 10px 0;
            color: var(--accent-dark);
        }

        .welcome-logo-mark {
            width: 90px;
            height: 90px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, #fff, #f2f4f7);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            font-size: 2rem;
            font-weight: 800;
        }

        .welcome-logo-text {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.1rem;
            line-height: 1.15;
            text-align: center;
        }

        .welcome-copy p {
            margin: 0 0 14px;
            color: #34485f;
            line-height: 1.8;
            font-size: 1rem;
        }

        .social-icons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 28px;
            min-height: 170px;
        }

        .social-icon {
            width: 58px;
            height: 58px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            font-weight: 800;
            color: #fff;
            box-shadow: var(--shadow);
        }

        .social-icon.facebook { background: #1877f2; }
        .social-icon.viber { background: #7360f2; }
        .social-icon.x { background: #16181c; }

        .lower-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 34px;
            margin-top: 28px;
            align-items: start;
        }

        .video-card {
            position: relative;
            min-height: 290px;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #b7d7f2, #8dc1ee);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 18px;
        }

        .video-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(6, 27, 48, 0.08), rgba(6, 27, 48, 0.28));
        }

        .video-card-content {
            position: relative;
            z-index: 1;
            color: #fff;
            max-width: 70%;
        }

        .video-card h3 {
            margin: 0 0 8px;
            font-size: 2rem;
            line-height: 1.1;
            font-family: "Space Grotesk", sans-serif;
        }

        .video-card p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.92);
        }

        .play-button {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 84px;
            height: 58px;
            border-radius: 18px;
            background: #ff2d20;
            box-shadow: 0 18px 34px rgba(255, 45, 32, 0.34);
        }

        .play-button::before {
            content: "";
            position: absolute;
            left: 34px;
            top: 18px;
            border-left: 20px solid #fff;
            border-top: 11px solid transparent;
            border-bottom: 11px solid transparent;
        }

        .quick-list {
            display: grid;
            gap: 14px;
        }

        .quick-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 5px solid var(--brand-blue);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: var(--shadow);
        }

        .quick-card h3 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        .quick-card p {
            margin: 0 0 12px;
            color: var(--muted);
            line-height: 1.65;
            font-size: 0.95rem;
        }

        .quick-card a {
            color: var(--brand-blue);
            font-weight: 800;
        }

        .notice-panel {
            margin-top: 28px;
            padding-bottom: 8px;
        }

        .notice-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .notice-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .notice-card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .notice-card span {
            display: block;
            color: var(--muted);
            line-height: 1.65;
        }

        @media (max-width: 1100px) {
            .content-grid,
            .lower-grid,
            .notice-grid {
                grid-template-columns: 1fr;
            }

            .welcome-box {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .portal-header-inner {
                align-items: flex-start;
                flex-wrap: wrap;
                padding: 10px 0;
            }

            .portal-nav,
            .portal-actions {
                width: 100%;
                margin-left: 0;
            }

            .portal-nav a {
                min-height: 54px;
                flex: 1 1 auto;
            }
        }

        @media (max-width: 640px) {
            .container { width: min(100%, calc(100% - 24px)); }
            .social-icons { gap: 16px; min-height: 120px; }
            .social-icon { width: 50px; height: 50px; }
            .video-card-content { max-width: 100%; }
            .video-card h3 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="top-strip"></div>

    <header class="portal-header">
        <div class="container portal-header-inner">
            <a class="portal-brand" href="index.php">
                <span class="portal-mark">EEY</span>
                <span class="portal-brand-text">
                    <strong><?php echo h(APP_NAME); ?></strong>
                    <span>Εφαρμογή Παρακολούθησης Πινάκων Διοριστέων</span>
                </span>
            </a>

            <nav class="portal-nav" aria-label="Κύρια πλοήγηση">
                <a class="is-active" href="index.php">ΑΡΧΙΚΗ</a>
                <a href="Search/searchdashboard.php">ΑΝΑΖΗΤΗΣΗ</a>
                <?php if ($isCandidate): ?><a href="Candidate/candidatedashboard.php">CANDIDATE</a><?php endif; ?>
                <?php if ($isAdmin): ?><a href="Admin/admindashboard.php">ADMIN</a><?php endif; ?>
                <?php if ($isAdmin): ?><a href="list.php">LIST</a><?php endif; ?>
                <a href="api/api.php">API</a>
            </nav>

            <div class="portal-actions">
                <?php if ($isGuest): ?>
                    <a class="portal-btn portal-btn-primary" href="auth/register.php">Εγγραφή</a>
                    <a class="portal-btn" href="auth/login.php">Σύνδεση</a>
                <?php else: ?>
                    <a class="portal-btn portal-btn-primary" href="<?php echo h($dashboardHref); ?>"><?php echo h($dashboardLabel); ?></a>
                    <a class="portal-btn" href="auth/logout.php">Αποσύνδεση</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="portal-body">
        <div class="container">
            <div class="breadcrumb">› ΑΡΧΙΚΗ</div>

            <section class="content-grid" aria-labelledby="welcomeTitle">
                <div class="welcome-panel">
                    <h2 class="panel-title" id="welcomeTitle">Καλωσορίσατε</h2>
                    <div class="welcome-box">
                        <div class="welcome-logo">
                            <div class="welcome-logo-mark">Ε</div>
                            <div class="welcome-logo-text">Επιτροπή<br>Εκπαιδευτικής<br>Υπηρεσίας</div>
                        </div>

                        <div class="welcome-copy">
                            <p>Καλωσορίσατε στην εφαρμογή παρακολούθησης πινάκων διοριστέων. Από εδώ μπορείτε να αναζητήσετε υποψηφίους, να δείτε βασικές πληροφορίες για ειδικότητες και να μεταβείτε στο κατάλληλο module ανάλογα με τον ρόλο σας.</p>
                            <p>Στόχος της εφαρμογής είναι η οργανωμένη αναζήτηση και παρουσίαση πληροφοριών, η ασφαλής πρόσβαση χρηστών και η σωστή διάκριση ανάμεσα σε δημόσια, candidate και admin εμπειρία.</p>
                        </div>
                    </div>
                </div>

                <aside class="social-panel">
                    <h2 class="panel-title">Ακολουθήστε μας στα Μέσα Κοινωνικής Δικτύωσης</h2>
                    <div class="social-icons">
                        <span class="social-icon facebook">f</span>
                        <span class="social-icon viber">v</span>
                        <span class="social-icon x">X</span>
                    </div>
                </aside>
            </section>

            <section class="lower-grid" aria-labelledby="mediaTitle">
                <div class="media-panel">
                    <h2 class="panel-title" id="mediaTitle">Παρουσίαση Διαδικασιών</h2>
                    <div class="video-card">
                        <div class="video-card-content">
                            <h3>Νέα διαδικασία διορισμών με σύμβαση</h3>
                            <p>Στην εφαρμογή μπορείς να περιηγηθείς στις βασικές ενότητες του συστήματος, να εντοπίσεις υποψηφίους και να μεταβείς στο κατάλληλο dashboard σύμφωνα με τον ρόλο σου.</p>
                        </div>
                        <div class="play-button" aria-hidden="true"></div>
                    </div>
                </div>

                <div class="quick-panel">
                    <h2 class="panel-title">Γρήγορη Πρόσβαση</h2>
                    <div class="quick-list">
                        <article class="quick-card">
                            <h3>Search Module</h3>
                            <p>Δημόσια αναζήτηση υποψηφίων με keyword search, φίλτρα και βασικά στατιστικά.</p>
                            <a href="Search/searchdashboard.php">Μετάβαση στην αναζήτηση</a>
                        </article>
                        <article class="quick-card">
                            <h3>Authentication</h3>
                            <p>Εγγραφή, σύνδεση και αποσύνδεση χρηστών με role-based πρόσβαση.</p>
                            <a href="auth/login.php">Μετάβαση στη σύνδεση</a>
                        </article>
                        <article class="quick-card">
                            <h3>API Module</h3>
                            <p>JSON endpoints για specialties, candidates και στατιστικά δεδομένα.</p>
                            <a href="api/api.php">Προβολή API</a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="notice-panel" aria-labelledby="noticeTitle">
                <h2 class="panel-title" id="noticeTitle">Βασικές Ενότητες της Εφαρμογής</h2>
                <div class="notice-grid">
                    <article class="notice-card">
                        <strong>Candidate Module</strong>
                        <span>Προσωπικός χώρος υποψηφίου με My Profile, Track My Applications, Track Others και αλλαγή κωδικού.</span>
                    </article>
                    <article class="notice-card">
                        <strong>Admin Module</strong>
                        <span>Διαχείριση χρηστών, λιστών και reports μέσα από συγκεντρωμένο admin dashboard.</span>
                    </article>
                    <article class="notice-card">
                        <strong>Κοινή Βάση και PDO</strong>
                        <span>Όλα τα modules δουλεύουν πάνω στην ίδια βάση δεδομένων με ασφαλή πρόσβαση και prepared statements.</span>
                    </article>
                </div>
            </section>
        </div>
    </main>
</body>
</html>