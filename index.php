<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$role = current_user_role();
$isGuest = $role === null;
$isAdmin = $role === ROLE_ADMIN;
$isCandidate = $role === ROLE_CANDIDATE;
$userRoleLabel = current_role_label();
$userInitials = current_user_initials();

$dashboardHref = $isAdmin ? 'modules/admin/admindashboard.php' : 'modules/candidate/candidatedashboard.php';
$dashboardLabel = $isAdmin ? 'Dashboard Διαχειριστή' : 'Dashboard Υποψηφίου';

$specialties = fetch_all_prepared(
    $conn,
    'SELECT id, title FROM specialties ORDER BY title ASC'
);

$overview = fetch_one_prepared(
    $conn,
    "SELECT
        (SELECT COUNT(*) FROM candidate_profiles) AS total_candidates,
        (SELECT COUNT(*) FROM specialties) AS total_specialties,
        (SELECT COUNT(*) FROM tracked_candidates) AS tracked_total,
        (SELECT MAX(created_at) FROM candidate_profiles) AS last_update"
) ?? [
    'total_candidates' => 0,
    'total_specialties' => count($specialties),
    'tracked_total' => 0,
    'last_update' => null,
];

$topSpecialties = fetch_all_prepared(
    $conn,
    'SELECT
        s.title,
        COUNT(cp.id) AS candidate_count
     FROM specialties s
     LEFT JOIN candidate_profiles cp ON cp.specialty_id = s.id
     GROUP BY s.id, s.title
     ORDER BY candidate_count DESC, s.title ASC
     LIMIT 4'
);

$usefulLinks = [
    [
        'title' => 'Πίνακες Διοριστέων',
        'text' => 'Πρόσβαση στους πιο πρόσφατους αναρτημένους πίνακες και στο σχετικό αρχείο.',
        'href' => 'https://www.gov.cy/eey/documents/pinakes/archeio-pinakes-dioristeon-2/',
    ],
    [
        'title' => 'Πίνακες Διορισίμων',
        'text' => 'Ενημερώσεις και αναρτημένοι πίνακες διορισίμων ανά ειδικότητα.',
        'href' => 'https://www.gov.cy/eey/mi-katigoriopoiimeno/pinakes-diorisimon/',
    ],
    [
        'title' => 'Εγγραφή σε πίνακα',
        'text' => 'Οδηγίες, προϋποθέσεις και χρήσιμες πληροφορίες για την εγγραφή σε πίνακα.',
        'href' => 'https://www.gov.cy/eey/documents/pinakes/eggrafi-se-pinaka-eleytheria/',
    ],
    [
        'title' => 'Διορισμοί',
        'text' => 'Πληροφορίες για μόνιμους, έκτακτους και συμβασιούχους διορισμούς.',
        'href' => 'https://eey.gov.cy/%CE%94%CE%99%CE%9F%CE%A1%CE%99%CE%A3%CE%9C%CE%9F%CE%99',
    ],
];

$announcements = [
    [
        'date' => '24 Απριλίου 2026',
        'category' => 'Ανακοινωθέν',
        'title' => 'Μεταθέσεις Εκπαιδευτικών Λειτουργών Σχολείων Μέσης Γενικής Εκπαίδευσης',
        'summary' => 'Ανακοίνωση της ΕΕΥ για μεταθέσεις εκπαιδευτικών λειτουργών Μέσης Γενικής Εκπαίδευσης με ισχύ από 1η Σεπτεμβρίου 2026.',
        'href' => 'https://www.gov.cy/eey/mi-katigoriopoiimeno/metatheseis-ekpaideytikon-leitoyrgon-scholeion-mesis-genikis-ekpaideysis/',
    ],
    [
        'date' => '22 Απριλίου 2026',
        'category' => 'Ανακοινωθέν',
        'title' => 'Μεταθέσεις και τοποθετήσεις εκπαιδευτικών λειτουργών ΜΤΕΕΚ',
        'summary' => 'Ανακοίνωση για μεταθέσεις και τοποθετήσεις εκπαιδευτικών λειτουργών σχολείων Μέσης Τεχνικής και Επαγγελματικής Εκπαίδευσης.',
        'href' => 'https://www.gov.cy/eey/mi-katigoriopoiimeno/metatheseis-kai-topothetiseis-ekpaideytikon-leitoyrgon-scholeion-mesis-technikis-kai-epaggelmatikis-ekpaideysis-kai-katartisis/',
    ],
    [
        'date' => '8 Απριλίου 2026',
        'category' => 'Ανακοινωθέν',
        'title' => 'Μεταθέσεις Εκπαιδευτικών Λειτουργών Σχολείων Δημοτικής Εκπαίδευσης',
        'summary' => 'Δημοσιευμένη ανακοίνωση για μεταθέσεις εκπαιδευτικών λειτουργών Δημοτικής Εκπαίδευσης και σχετική διαδικασία ενστάσεων.',
        'href' => 'https://www.gov.cy/eey/mi-katigoriopoiimeno/metatheseis-ekpaideytikon-leitoyrgon-scholeion-dimotikis-ekpaideysis/',
    ],
    [
        'date' => '31 Μαρτίου 2026',
        'category' => 'Ανακοινωθέν',
        'title' => 'Πίνακες Διορισίμων',
        'summary' => 'Ανακοίνωση για αναθεωρημένους πίνακες διορισίμων εκπαιδευτικών και ειδικούς καταλόγους διορισίμων ανά ειδικότητα.',
        'href' => 'https://www.gov.cy/eey/mi-katigoriopoiimeno/pinakes-diorisimon/',
    ],
    [
        'date' => '27 Φεβρουαρίου 2026',
        'category' => 'Ανακοινωθέν',
        'title' => 'Προαγωγές και ανάρτηση πινάκων διοριστέων',
        'summary' => 'Ανακοίνωση της ΕΕΥ που περιλαμβάνει θέματα προαγωγών και ανάρτηση πινάκων διοριστέων.',
        'href' => 'https://www.gov.cy/paideia-athlitismos-neolaia/anakoinosi-tis-epitropis-ekpaideftikis-ypiresias-gia-proagoges-kai-anartisi-pinakon-dioristeon-27-fevrouariou-2026/',
    ],
];
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
            --bg: #f2f5f8;
            --panel: #ffffff;
            --panel-soft: #f8fafc;
            --panel-border: rgba(21, 55, 92, 0.12);
            --field-border: #cfdae8;
            --border: rgba(21, 55, 92, 0.12);
            --border-strong: rgba(21, 55, 92, 0.2);
            --text: #14263d;
            --muted: #5d7088;
            --accent: #b8862f;
            --accent-2: #d9ab55;
            --accent-dark: #73501d;
            --blue: #2f5d8c;
            --green: #2f7f64;
            --shadow: 0 18px 48px rgba(17, 39, 68, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", system-ui, sans-serif;
            color: var(--text);
            background:
                linear-gradient(180deg, rgba(242,245,248,0.96), rgba(229,237,246,0.96)),
                #eef3f8;
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto 40px;
            padding-top: 0;
        }

        .topbar {
            width: 100vw;
            margin-left: calc(50% - 50vw);
            margin-top: 24px;
            padding: 16px 0;
            background: rgba(255,255,255,0.92);
            border-top: 1px solid var(--panel-border);
            border-bottom: 1px solid var(--panel-border);
            box-shadow: 0 18px 40px rgba(17,39,68,0.10);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar-inner {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(260px, 1.2fr) auto;
            align-items: center;
            gap: 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(17,39,68,0.12);
            flex-shrink: 0;
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

        .nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .nav-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-group.auth {
            padding-left: 20px;
            margin-left: 6px;
            border-left: 1px solid rgba(21,55,92,0.14);
        }

        .nav a {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border-radius: 16px;
            border: 1px solid var(--field-border);
            background: rgba(255,255,255,0.86);
            color: var(--text);
            font-weight: 800;
        }

        .nav a.auth-link {
            min-height: 42px;
            padding: 0 16px;
            font-size: 0.95rem;
            background: #fff;
            box-shadow: 0 12px 24px rgba(17,39,68,0.08);
        }

        .nav a.auth-link.primary {
            min-height: 42px;
            padding: 0 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            border-color: transparent;
            box-shadow: 0 18px 32px rgba(184,134,47,0.20);
        }

        .nav a.active {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            border-color: transparent;
            box-shadow: 0 18px 32px rgba(184,134,47,0.18);
        }

        .profile-menu {
            position: relative;
        }

        .profile-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 58px;
            padding: 8px 10px 8px 8px;
            border: 1px solid rgba(137,92,199,0.16);
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,244,255,0.96));
            color: var(--text);
            font: inherit;
            box-shadow: 0 16px 34px rgba(17,39,68,0.10);
            cursor: pointer;
        }

        .profile-trigger:hover,
        .profile-menu:focus-within .profile-trigger {
            transform: translateY(-1px);
            box-shadow: 0 20px 38px rgba(17,39,68,0.14);
        }

        .profile-role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: linear-gradient(135deg, #6f1fc7 0%, #8a37df 100%);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            white-space: nowrap;
            box-shadow: 0 12px 22px rgba(111,31,199,0.24);
        }

        .profile-trigger-avatar {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f1e8ff 0%, #e4d2ff 100%);
            border: 1px solid rgba(111,31,199,0.14);
            color: #6122b7;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .profile-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 176px;
            padding: 8px 0;
            background: #fff;
            box-shadow: 0 18px 40px rgba(17,39,68,0.16);
            display: grid;
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
            z-index: 20;
        }

        .profile-menu:hover .profile-dropdown,
        .profile-menu:focus-within .profile-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            color: #24364d;
            font-weight: 600;
            font-size: 0.92rem;
            background: transparent;
        }

        .profile-dropdown-item:hover {
            background: rgba(241,246,252,0.9);
        }

        .profile-dropdown-item-logout {
            color: #e35b52;
        }

        .profile-dropdown-icon {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: currentColor;
            flex: 0 0 20px;
        }

        .profile-dropdown-icon svg {
            width: 20px;
            height: 20px;
            display: block;
            fill: currentColor;
        }

        .page {
            display: grid;
            gap: 22px;
            padding-top: 24px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.75fr);
            gap: 22px;
            align-items: stretch;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            border-radius: 8px;
            padding: 26px;
        }

        .hero-main {
            padding: 34px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(184,134,47,0.12);
            color: var(--accent-dark);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1, h2, h3 {
            font-family: "Space Grotesk", "Manrope", sans-serif;
            margin: 0;
            letter-spacing: 0;
        }

        h1 {
            margin-top: 16px;
            max-width: 13ch;
            font-size: clamp(2.6rem, 6vw, 4.8rem);
            line-height: 0.98;
        }

        h2 {
            font-size: clamp(1.45rem, 2vw, 2rem);
        }

        h3 {
            font-size: 1.05rem;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .lead {
            margin-top: 18px;
            max-width: 72ch;
            font-size: 1.02rem;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border-radius: 14px;
            border: 1px solid var(--border-strong);
            background: #fff;
            color: var(--text);
            font-weight: 800;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            border-color: transparent;
            box-shadow: 0 14px 28px rgba(184,134,47,0.22);
        }

        .quick-search {
            display: grid;
            gap: 16px;
        }

        .quick-search form {
            display: grid;
            gap: 12px;
        }

        label {
            display: grid;
            gap: 6px;
            color: var(--muted);
            font-size: 0.86rem;
            font-weight: 800;
        }

        input,
        select {
            width: 100%;
            min-height: 46px;
            border: 1px solid var(--border-strong);
            border-radius: 12px;
            padding: 0 13px;
            background: var(--panel-soft);
            color: var(--text);
            font: inherit;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
        }

        .section-head p {
            max-width: 68ch;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .stat-card,
        .link-card,
        .announcement-card,
        .step-card {
            border: 1px solid var(--border);
            background: var(--panel-soft);
            border-radius: 8px;
            padding: 18px;
        }

        .stat-label {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 0.84rem;
            font-weight: 800;
        }

        .stat-value {
            display: block;
            color: var(--text);
            font-family: "Space Grotesk", "Manrope", sans-serif;
            font-size: 2rem;
            font-weight: 800;
        }

        .stat-note {
            margin-top: 6px;
            font-size: 0.9rem;
        }

        .top-list {
            display: grid;
            gap: 10px;
        }

        .top-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 11px 0;
            border-bottom: 1px solid var(--border);
        }

        .top-row:last-child {
            border-bottom: 0;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            min-height: 30px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(47,93,140,0.1);
            color: var(--blue);
            font-weight: 800;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .links-grid,
        .announcements-grid,
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .announcements-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .two-column .steps-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .link-card,
        .announcement-card,
        .step-card {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 800;
        }

        .announcement-category {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border-radius: 999px;
            background: rgba(184,134,47,0.12);
            color: var(--accent-dark);
        }

        .announcement-card a,
        .link-card a {
            color: var(--accent-dark);
            font-weight: 800;
        }

        .source-note {
            margin-top: 12px;
            font-size: 0.92rem;
        }

        .step-number {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--green);
            color: #fff;
            font-weight: 800;
        }

        .footer-note {
            padding: 12px 0 4px;
            text-align: center;
            color: var(--muted);
            font-size: 0.94rem;
        }

        @media (max-width: 980px) {
            .topbar { position: static; }
            .hero,
            .two-column,
            .stats-grid,
            .links-grid,
            .announcements-grid,
            .steps-grid,
            .two-column .steps-grid {
                grid-template-columns: 1fr;
            }
            .nav {
                justify-content: flex-start;
            }
            h1 {
                max-width: none;
            }
        }

        @media (max-width: 760px) {
            .topbar-inner {
                grid-template-columns: 1fr;
                align-items: flex-start;
            }
            .nav {
                width: 100%;
                justify-content: flex-start;
                gap: 12px;
            }
            .nav-group {
                width: 100%;
            }
            .nav-group.auth {
                padding-left: 0;
                margin-left: 0;
                border-left: none;
            }
            .nav a {
                flex: 1 1 calc(50% - 10px);
            }
            .profile-menu,
            .profile-trigger {
                width: 100%;
            }
            .profile-trigger {
                justify-content: space-between;
            }
        }

        @media (max-width: 640px) {
            .shell,
            .topbar-inner {
                width: min(100%, calc(100% - 22px));
            }
            .hero-main,
            .panel {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="topbar-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="<?php echo h(path_from_root('assets/images/ichnos-logo.jpg') . '?v=20260402'); ?>" alt="<?php echo h(APP_NAME); ?> logo">
                    <span class="brand-copy">
                        <strong><?php echo h(APP_NAME); ?></strong>
                        <span><?php echo h(APP_TAGLINE); ?></span>
                    </span>
                </a>

                <nav class="nav" aria-label="Κύρια πλοήγηση">
                    <div class="nav-group main">
                        <a class="active" href="index.php">Αρχική</a>
                        <a href="modules/search/searchdashboard.php">Αναζήτηση</a>
                        <?php if ($isAdmin): ?>
                            <a href="modules/admin/admindashboard.php">Admin</a>
                        <?php elseif ($isCandidate): ?>
                            <a href="modules/candidate/candidatedashboard.php">Υποψήφιος</a>
                        <?php endif; ?>
                    </div>

                    <div class="nav-group auth">
                        <?php if ($isGuest): ?>
                            <a class="auth-link primary" href="auth/register.php">Εγγραφή</a>
                            <a class="auth-link" href="auth/login.php">Σύνδεση</a>
                        <?php else: ?>
                            <div class="profile-menu">
                                <button type="button" class="profile-trigger" aria-haspopup="menu">
                                    <span class="profile-role-badge"><?php echo h($userRoleLabel ?? u('\u03A3\u03C5\u03BD\u03B4\u03B5\u03B4\u03B5\u03BC\u03AD\u03BD\u03BF\u03C2')); ?></span>
                                    <span class="profile-trigger-avatar"><?php echo h($userInitials); ?></span>
                                </button>
                                <div class="profile-dropdown" role="menu">
                                    <a class="profile-dropdown-item" href="<?php echo h($dashboardHref); ?>" role="menuitem">
                                        <span class="profile-dropdown-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" focusable="false"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.33 0-6 1.79-6 4v1h12v-1c0-2.21-2.67-4-6-4Z"/></svg>
                                        </span>
                                        <span>Ο λογαριασμός μου</span>
                                    </a>
                                    <a class="profile-dropdown-item profile-dropdown-item-logout" href="auth/logout.php" role="menuitem">
                                        <span class="profile-dropdown-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" focusable="false"><path d="M10 17v-2h4V9h-4V7l-5 5 5 5Z"/><path d="M14 5h5v14h-5v-2h3V7h-3V5Z"/></svg>
                                        </span>
                                        <span>Αποσύνδεση</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </header>

        <main class="page">
            <section class="hero" aria-labelledby="homeTitle">
                <article class="panel hero-main">
                    <span class="eyebrow">Εφαρμογή παρακολούθησης</span>
                    <h1 id="homeTitle">Πίνακες διοριστέων, καθαρά και οργανωμένα</h1>
                    <p class="lead">
                        Βρες γρήγορα πληροφορίες για υποψηφίους και ειδικότητες, παρακολούθησε τις πρόσφατες
                        ανακοινώσεις της ΕΕΥ και δες μια καθαρή εικόνα των διαθέσιμων πινάκων. Με λογαριασμό μπορείς
                        να αποθηκεύεις την παρακολούθησή σου και να ενημερώνεσαι πιο εύκολα για την πορεία που σε αφορά.
                    </p>
                    <div class="actions">
                        <a class="btn btn-primary" href="modules/search/searchdashboard.php">Αναζήτηση Πινάκων</a>
                        <?php if ($isGuest): ?>
                            <a class="btn" href="auth/register.php">Δημιουργία λογαριασμού</a>
                        <?php else: ?>
                            <a class="btn" href="<?php echo h($dashboardHref); ?>"><?php echo h($dashboardLabel); ?></a>
                        <?php endif; ?>
                    </div>
                </article>

                <aside class="panel quick-search" aria-labelledby="quickSearchTitle">
                    <div>
                        <span class="eyebrow">Γρήγορη αναζήτηση</span>
                        <h2 id="quickSearchTitle">Βρες υποψήφιο ή ειδικότητα</h2>
                        <p>Χρησιμοποίησε όνομα, επώνυμο ή ειδικότητα για να μεταφερθείς απευθείας στα σχετικά αποτελέσματα.</p>
                    </div>
                    <form method="get" action="modules/search/searchdashboard.php">
                        <label for="name">
                            Αναζήτηση με ονοματεπώνυμο
                            <input id="name" name="name" type="search" placeholder="π.χ. Μαρία Παπαδοπούλου">
                        </label>
                        <label for="specialty_id">
                            Φίλτρο ειδικότητας
                            <select id="specialty_id" name="specialty_id">
                                <option value="0">Όλες οι ειδικότητες</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo (int) $specialty['id']; ?>"><?php echo h($specialty['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <input type="hidden" name="order" value="rank_asc">
                        <button class="btn btn-primary" type="submit">Μετάβαση στα αποτελέσματα</button>
                    </form>
                </aside>
            </section>

            <section class="panel" aria-labelledby="statsTitle">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Συνοπτικά στοιχεία</span>
                        <h2 id="statsTitle">Συνοπτική εικόνα πινάκων</h2>
                    </div>
                    <p>Μια σύντομη εικόνα για το περιεχόμενο που είναι διαθέσιμο αυτή τη στιγμή.</p>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Υποψήφιοι</span>
                        <span class="stat-value"><?php echo (int) ($overview['total_candidates'] ?? 0); ?></span>
                        <p class="stat-note">Πρόσωπα που εμφανίζονται στους διαθέσιμους πίνακες</p>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Ειδικότητες</span>
                        <span class="stat-value"><?php echo (int) ($overview['total_specialties'] ?? count($specialties)); ?></span>
                        <p class="stat-note">Κατηγορίες στις οποίες μπορείς να φιλτράρεις αποτελέσματα</p>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Παρακολουθήσεις</span>
                        <span class="stat-value"><?php echo (int) ($overview['tracked_total'] ?? 0); ?></span>
                        <p class="stat-note">Αποθηκευμένες παρακολουθήσεις μέσα από λογαριασμούς χρηστών</p>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Τελευταία ενημέρωση</span>
                        <span class="stat-value">
                            <?php echo !empty($overview['last_update']) ? h(date('d/m/Y', strtotime((string) $overview['last_update']))) : '-'; ?>
                        </span>
                        <p class="stat-note">Πότε προστέθηκε ή ενημερώθηκε πιο πρόσφατα διαθέσιμη εγγραφή</p>
                    </div>
                </div>
            </section>

            <section class="two-column">
                <article class="panel" aria-labelledby="popularTitle">
                    <div class="section-head">
                        <div>
                            <span class="eyebrow">Κατανομή</span>
                            <h2 id="popularTitle">Ειδικότητες με περισσότερες εγγραφές</h2>
                        </div>
                    </div>
                    <div class="top-list">
                        <?php if ($topSpecialties === []): ?>
                            <p>Δεν υπάρχουν ακόμα διαθέσιμες εγγραφές για εμφάνιση.</p>
                        <?php else: ?>
                            <?php foreach ($topSpecialties as $row): ?>
                                <div class="top-row">
                                    <strong><?php echo h($row['title']); ?></strong>
                                    <span class="pill"><?php echo (int) $row['candidate_count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="panel" aria-labelledby="howTitle">
                    <div class="section-head">
                        <div>
                            <span class="eyebrow">Ροή χρήσης</span>
                            <h2 id="howTitle">Πώς λειτουργεί</h2>
                        </div>
                    </div>
                    <div class="steps-grid">
                        <div class="step-card">
                            <span class="step-number">1</span>
                            <h3>Αναζήτηση</h3>
                            <p>Ξεκίνα με ονοματεπώνυμο ή ειδικότητα και δες τα αποτελέσματα ταξινομημένα.</p>
                        </div>
                        <div class="step-card">
                            <span class="step-number">2</span>
                            <h3>Εγγραφή</h3>
                            <p>Δημιούργησε λογαριασμό για να αποθηκεύεις στοιχεία και προτιμήσεις παρακολούθησης.</p>
                        </div>
                        <div class="step-card">
                            <span class="step-number">3</span>
                            <h3>Παρακολούθηση</h3>
                            <p>Σύνδεσε το προφίλ σου με πίνακα και κράτησε συγκεντρωμένη την πορεία που σε ενδιαφέρει.</p>
                        </div>
                        <div class="step-card">
                            <span class="step-number">4</span>
                            <h3>Ενημέρωση</h3>
                            <p>Συμβουλεύσου πρόσφατες ανακοινώσεις και επίσημες πηγές για ολοκληρωμένη πληροφόρηση.</p>
                        </div>
                    </div>
                </article>
            </section>

            <section class="panel" aria-labelledby="linksTitle">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Επίσημη πληροφόρηση</span>
                        <h2 id="linksTitle">Πρόσφατες ανακοινώσεις ΕΕΥ</h2>
                    </div>
                    <p>Οι πιο πρόσφατες ενημερώσεις εμφανίζονται συγκεντρωμένα, με σύντομη περίληψη και σύνδεσμο προς την επίσημη δημοσίευση.</p>
                </div>
                <div class="announcements-grid">
                    <?php foreach ($announcements as $announcement): ?>
                        <article class="announcement-card">
                            <div class="announcement-meta">
                                <span><?php echo h($announcement['date']); ?></span>
                                <span class="announcement-category"><?php echo h($announcement['category']); ?></span>
                            </div>
                            <h3><?php echo h($announcement['title']); ?></h3>
                            <p><?php echo h($announcement['summary']); ?></p>
                            <a href="<?php echo h($announcement['href']); ?>" target="_blank" rel="noopener">Προβολή στην επίσημη πηγή</a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="source-note">Οι περιλήψεις βοηθούν στη γρήγορη ενημέρωση. Για πλήρες περιεχόμενο, χρησιμοποιείται πάντα η επίσημη πηγή της Επιτροπής Εκπαιδευτικής Υπηρεσίας / Gov.cy.</p>
            </section>

            <section class="panel" aria-labelledby="usefulLinksTitle">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Χρήσιμες σελίδες</span>
                        <h2 id="usefulLinksTitle">Σταθερές πηγές πληροφόρησης</h2>
                    </div>
                    <p>Συγκεντρωμένες επίσημες σελίδες για πίνακες, εγγραφή και διορισμούς, ώστε να μη χρειάζεται να τις αναζητάς ξεχωριστά.</p>
                </div>
                <div class="links-grid">
                    <?php foreach ($usefulLinks as $link): ?>
                        <article class="link-card">
                            <h3><?php echo h($link['title']); ?></h3>
                            <p><?php echo h($link['text']); ?></p>
                            <a href="<?php echo h($link['href']); ?>" target="_blank" rel="noopener">Άνοιγμα πηγής</a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="source-note">Πηγή συνδέσμων: Επιτροπή Εκπαιδευτικής Υπηρεσίας / Gov.cy.</p>
            </section>

            <div class="footer-note">
                <?php echo h(APP_NAME); ?> - εφαρμογή παρακολούθησης πινάκων διοριστέων.
            </div>
        </main>
    </div>
</body>
</html>
