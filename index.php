<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

$role = current_user_role();
$isGuest = $role === null;
$isAdmin = $role === ROLE_ADMIN;
$isCandidate = $role === ROLE_CANDIDATE;

$primaryHref = $isGuest ? 'auth/register.php' : ($isAdmin ? 'Admin/admindashboard.php' : 'Candidate/candidatedashboard.php');
$primaryLabel = $isGuest ? 'Δημιούργησε Λογαριασμό' : ($isAdmin ? 'Μετάβαση στο Admin' : 'Μετάβαση στο Dashboard');
$secondaryHref = 'Search/searchdashboard.php';
$secondaryLabel = 'Αναζήτηση Υποψηφίων';
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
            --bg: #eef3f8;
            --bg-accent: #dce7f5;
            --panel: rgba(255, 255, 255, 0.96);
            --panel-soft: rgba(247, 249, 252, 0.9);
            --panel-border: rgba(21, 55, 92, 0.12);
            --text: #14263d;
            --muted: #5d7088;
            --accent: #b8862f;
            --accent-2: #d9ab55;
            --accent-dark: #7a5720;
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
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: linear-gradient(rgba(255,255,255,0.16) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.14) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(circle at center, rgba(0,0,0,0.35), transparent 78%);
            opacity: 0.22;
        }
        .page { position: relative; z-index: 1; min-height: 100vh; }
        .container { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
        .site-header {
            position: sticky; top: 0; z-index: 10;
            background: rgba(238, 243, 248, 0.82);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(21, 55, 92, 0.10);
        }
        .header-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 18px; padding: 16px 0;
        }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text); }
        .brand-mark {
            width: 50px; height: 50px; display: grid; place-items: center; border-radius: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff; font-family: "Space Grotesk", sans-serif; font-weight: 700;
            box-shadow: 0 14px 28px rgba(184, 134, 47, 0.22);
        }
        .brand-copy strong { display: block; font-size: 1rem; font-family: "Space Grotesk", sans-serif; }
        .brand-copy span { display: block; margin-top: 2px; color: var(--muted); font-size: 0.92rem; font-weight: 600; }
        .header-links, .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .nav-link, .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 11px 16px;
            border-radius: 16px; text-decoration: none; font-weight: 800;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease, background 0.2s ease;
        }
        .nav-link, .btn-secondary { border: 1px solid var(--panel-border); background: rgba(255, 255, 255, 0.72); color: var(--text); }
        .nav-link:hover, .btn:hover { transform: translateY(-1px); }
        .nav-link.is-active { background: rgba(184, 134, 47, 0.14); border-color: rgba(184, 134, 47, 0.26); color: var(--accent-dark); }
        .btn-primary {
            border: none; color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 32px rgba(184, 134, 47, 0.24);
        }
        .hero { padding: 34px 0 26px; }
        .hero-card {
            display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 18px; padding: 34px;
            border-radius: 30px; background: var(--panel); border: 1px solid var(--panel-border); box-shadow: var(--shadow);
        }
        .eyebrow {
            display: inline-flex; align-items: center; margin-bottom: 16px; padding: 7px 12px; border-radius: 999px;
            background: rgba(184, 134, 47, 0.12); color: var(--accent-dark); font-size: 0.78rem; font-weight: 800;
            letter-spacing: 0.06em; text-transform: uppercase;
        }
        h1,h2,h3 { font-family: "Space Grotesk", sans-serif; margin: 0; }
        .hero-copy h1 { font-size: clamp(2.4rem, 5vw, 4.2rem); line-height: 0.98; margin-bottom: 16px; max-width: 11ch; }
        .hero-copy p { margin: 0 0 14px; color: var(--muted); font-size: 1.02rem; line-height: 1.7; max-width: 58ch; }
        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
        .hero-points { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-top: 24px; }
        .hero-point, .side-card, .feature-card, .info-card { border-radius: 22px; background: var(--panel-soft); border: 1px solid var(--panel-border); }
        .hero-point { padding: 16px; }
        .hero-point strong, .hero-point span { display: block; }
        .hero-point strong { margin-bottom: 6px; }
        .hero-point span, .side-card p, .side-card span, .section-head p, .feature-card p, .info-copy p, .footer-card p { color: var(--muted); }
        .hero-side { display: grid; gap: 14px; }
        .side-card { padding: 20px; }
        .side-card strong { display: block; margin: 4px 0 6px; font-size: 1.08rem; }
        .side-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; }
        .section { padding: 0 0 20px; }
        .section-head { margin-bottom: 12px; }
        .section-head h2 { margin-bottom: 6px; }
        .feature-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
        .feature-card { padding: 20px; box-shadow: var(--shadow); }
        .feature-icon {
            width: 46px; height: 46px; display: grid; place-items: center; border-radius: 16px; margin-bottom: 14px;
            background: linear-gradient(135deg, rgba(184, 134, 47, 0.14), rgba(217, 171, 85, 0.14));
            border: 1px solid rgba(184, 134, 47, 0.22); color: var(--accent-dark); font-weight: 800;
        }
        .feature-card h3 { margin-bottom: 10px; font-size: 1.24rem; }
        .info-band {
            display: grid; grid-template-columns: 0.95fr 1.05fr; gap: 18px; padding: 28px; border-radius: 30px;
            background: var(--panel); border: 1px solid var(--panel-border); box-shadow: var(--shadow); margin-bottom: 28px;
        }
        .info-copy h2 { margin: 10px 0 12px; font-size: clamp(1.9rem, 3vw, 2.8rem); line-height: 1.05; }
        .info-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; }
        .info-card { padding: 18px; }
        .info-card h3 { margin-bottom: 12px; font-size: 1.05rem; }
        .info-row {
            display: flex; justify-content: space-between; gap: 12px; padding: 10px 0;
            border-bottom: 1px solid rgba(21, 55, 92, 0.08); color: var(--muted);
        }
        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-row strong { color: var(--text); text-align: right; }
        .site-footer { padding: 0 0 22px; }
        .footer-card {
            display: flex; justify-content: space-between; gap: 18px; flex-wrap: wrap; padding: 18px 22px;
            border-radius: 24px; background: rgba(255,255,255,0.7); border: 1px solid var(--panel-border);
        }
        @media (max-width: 980px) {
            .hero-card, .info-band, .feature-grid, .hero-points, .side-grid, .info-grid { grid-template-columns: 1fr; }
            .header-row { flex-wrap: wrap; }
            .header-links, .header-actions { width: 100%; }
            .hero-copy h1 { max-width: none; }
        }
        @media (max-width: 640px) {
            .container { width: min(100%, calc(100% - 24px)); }
            .hero-card, .info-band { padding: 22px; }
            .hero-actions, .header-links, .header-actions { width: 100%; }
            .nav-link, .btn { flex: 1 1 auto; }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="site-header">
        <div class="container header-row">
            <a class="brand" href="index.php">
                <span class="brand-mark">EEY</span>
                <span class="brand-copy">
                    <strong><?php echo h(APP_NAME); ?></strong>
                    <span>Σύστημα αναζήτησης και πρόσβασης χρηστών</span>
                </span>
            </a>

            <nav class="header-links" aria-label="Κύρια πλοήγηση">
                <a class="nav-link is-active" href="index.php">Αρχική</a>
                <a class="nav-link" href="Search/searchdashboard.php">Search</a>
                <?php if ($isCandidate): ?><a class="nav-link" href="Candidate/candidatedashboard.php">Candidate</a><?php endif; ?>
                <?php if ($isAdmin): ?><a class="nav-link" href="Admin/admindashboard.php">Admin</a><?php endif; ?>
                <?php if ($isAdmin): ?><a class="nav-link" href="list.php">List</a><?php endif; ?>
                <a class="nav-link" href="api/api.php">API</a>
            </nav>

            <div class="header-actions">
                <?php if ($isGuest): ?>
                    <a class="btn btn-primary" href="auth/register.php">Εγγραφή</a>
                    <a class="btn btn-secondary" href="auth/login.php">Σύνδεση</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?php echo h($primaryHref); ?>"><?php echo h($primaryLabel); ?></a>
                    <a class="btn btn-secondary" href="auth/logout.php">Αποσύνδεση</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container hero">
        <section class="hero-card" aria-labelledby="homeTitle">
            <div class="hero-copy">
                <span class="eyebrow">Public Entry Point</span>
                <h1 id="homeTitle">Πίνακες Διοριστέων για αναζήτηση, πρόσβαση και διαχείριση</h1>
                <p>Η αρχική σελίδα είναι το δημόσιο σημείο εισόδου της εφαρμογής. Από εδώ ο επισκέπτης μπορεί να καταλάβει τι προσφέρει το σύστημα και να μεταβεί γρήγορα στη σωστή ενότητα.</p>
                <p>Η εφαρμογή συνδυάζει authentication, ρόλους χρηστών, αναζήτηση δεδομένων, dashboards και API πρόσβαση σε ένα ενιαίο περιβάλλον.</p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="<?php echo h($primaryHref); ?>"><?php echo h($primaryLabel); ?></a>
                    <a class="btn btn-secondary" href="<?php echo h($secondaryHref); ?>"><?php echo h($secondaryLabel); ?></a>
                </div>

                <div class="hero-points">
                    <div class="hero-point">
                        <strong>Ασφαλής πρόσβαση</strong>
                        <span>Register, Login, Logout και Session Guard</span>
                    </div>
                    <div class="hero-point">
                        <strong>Καθαρή πλοήγηση</strong>
                        <span>Ομαλή μετάβαση σε Search, Candidate, Admin και API</span>
                    </div>
                    <div class="hero-point">
                        <strong>Backend εργασίας</strong>
                        <span>PHP, MySQL, PDO και δομημένη βάση δεδομένων</span>
                    </div>
                </div>
            </div>

            <aside class="hero-side" aria-label="Σύνοψη εφαρμογής">
                <div class="side-card">
                    <span>Εφαρμογή</span>
                    <strong>4 βασικές ενότητες</strong>
                    <p>Search, Candidate, Admin και API με ενιαία λογική και πιο επαγγελματική εμπειρία χρήσης.</p>
                </div>
                <div class="side-grid">
                    <div class="side-card"><span>Backend</span><strong>PHP + MySQL / PDO</strong></div>
                    <div class="side-card"><span>Auth</span><strong>Role-based access</strong></div>
                    <div class="side-card"><span>Search</span><strong>Keyword search</strong></div>
                    <div class="side-card"><span>API</span><strong>JSON endpoints</strong></div>
                </div>
            </aside>
        </section>

        <section class="section" aria-labelledby="modulesTitle">
            <div class="section-head">
                <h2 id="modulesTitle">Βασικές Ενότητες</h2>
                <p>Κάθε ενότητα εξυπηρετεί διαφορετικό ρόλο και διαφορετική ανάγκη του συστήματος.</p>
            </div>
            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon">1</div>
                    <h3>Search Module</h3>
                    <p>Δημόσια αναζήτηση υποψηφίων με φίλτρα, ειδικότητες και στατιστικά.</p>
                    <a class="btn btn-secondary" href="Search/searchdashboard.php">Άνοιγμα Search</a>
                </article>
                <article class="feature-card">
                    <div class="feature-icon">2</div>
                    <h3>Candidate Module</h3>
                    <p>Προσωπικός χώρος υποψηφίου με προφίλ, ειδοποιήσεις και πορεία αίτησης.</p>
                    <a class="btn btn-secondary" href="Candidate/candidatedashboard.php">Άνοιγμα Candidate</a>
                </article>
                <article class="feature-card">
                    <div class="feature-icon">3</div>
                    <h3>Admin Module</h3>
                    <p>Διαχείριση χρηστών, λιστών και reports μέσα από ενιαίο admin περιβάλλον.</p>
                    <a class="btn btn-secondary" href="Admin/admindashboard.php">Άνοιγμα Admin</a>
                </article>
                <article class="feature-card">
                    <div class="feature-icon">4</div>
                    <h3>API Module</h3>
                    <p>JSON endpoints για αξιοποίηση δεδομένων από τρίτα συστήματα.</p>
                    <a class="btn btn-secondary" href="api/api.php">Άνοιγμα API</a>
                </article>
            </div>
        </section>

        <section class="info-band" aria-labelledby="projectTitle">
            <div class="info-copy">
                <span class="eyebrow">Landing Page</span>
                <h2 id="projectTitle">Μια αρχική σελίδα που εξηγεί σωστά την εφαρμογή</h2>
                <p>Η αρχική σελίδα δεν πρέπει να μοιάζει με εσωτερικό dashboard. Πρέπει να είναι καθαρή, επαγγελματική και να δίνει αμέσως στον χρήστη σωστή εικόνα για το project και τα διαθέσιμα modules.</p>
            </div>
            <div class="info-grid">
                <article class="info-card">
                    <h3>Τι περιλαμβάνει</h3>
                    <div class="info-row"><span>Authentication</span><strong>Register / Login / Logout</strong></div>
                    <div class="info-row"><span>Session Guard</span><strong>Προστασία ανά ρόλο</strong></div>
                    <div class="info-row"><span>Database</span><strong>PDO και κοινή βάση</strong></div>
                </article>
                <article class="info-card">
                    <h3>Τι μπορείς να κάνεις</h3>
                    <div class="info-row"><span>Public Search</span><strong>Άμεση αναζήτηση</strong></div>
                    <div class="info-row"><span>Dashboard Access</span><strong>Candidate ή Admin</strong></div>
                    <div class="info-row"><span>API Access</span><strong>JSON δεδομένα</strong></div>
                </article>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-card">
            <div>
                <p><?php echo h(APP_NAME); ?> - Αρχική σελίδα δημόσιας πρόσβασης για το project.</p>
                <p>Σχεδιασμός για καθαρή πρώτη εντύπωση και σωστή πλοήγηση.</p>
            </div>
        </div>
    </footer>
</div>
</body>
</html>
