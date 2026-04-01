<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
session_start();
require_once __DIR__ . '/includes/functions.php';

$role = current_user_role();
$isGuest = $role === null;
$isAdmin = $role === ROLE_ADMIN;
$isCandidate = $role === ROLE_CANDIDATE;
$userFullName = current_user_full_name();
$userInitials = current_user_initials();
$userRoleLabel = current_role_label();

$dashboardHref = $isAdmin ? 'modules/admin/admindashboard.php' : 'modules/candidate/candidatedashboard.php';
$dashboardLabel = $isAdmin ? '??????? ???????????' : '?????? ?????????';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(APP_NAME); ?> | &#913;&#961;&#967;&#953;&#954;&#942;</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eef3f8;
            --bg-accent: #dce7f5;
            --panel: rgba(255,255,255,0.96);
            --panel-border: rgba(21,55,92,0.12);
            --text: #14263d;
            --muted: #5d7088;
            --accent: #b8862f;
            --accent-2: #d9ab55;
            --accent-dark: #7a5720;
            --field: #f7f9fc;
            --field-border: #cfdae8;
            --shadow: 0 24px 60px rgba(17,39,68,0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(185,134,47,0.16), transparent 22%),
                radial-gradient(circle at left, rgba(52,103,168,0.10), transparent 26%),
                linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%);
        }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(1200px, calc(100% - 32px)); margin: 0 auto 40px; padding-top: 24px; }
        .topbar {
            width: 100vw;
            margin-left: calc(50% - 50vw);
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .brand-mark {
            width: 44px; height: 44px; display: grid; place-items: center; border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff;
            font-family: "Space Grotesk", sans-serif; font-weight: 800; box-shadow: 0 14px 28px rgba(184,134,47,0.22);
            flex-shrink: 0;
        }
        .brand-copy strong { display: block; font-size: 1rem; }
        .brand-copy span { display: block; margin-top: 2px; color: var(--muted); font-size: 0.92rem; font-weight: 600; }
        .nav { display: flex; align-items: center; gap: 26px; flex-wrap: wrap; justify-content: flex-end; }
        .nav-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .nav-group.auth { padding-left: 28px; margin-left: 18px; border-left: 1px solid rgba(21,55,92,0.14); }
        .user-chip {
            display: inline-flex; align-items: center; gap: 12px; min-height: 48px; padding: 6px 12px 6px 8px;
            border-radius: 18px; background: rgba(255,255,255,0.92); border: 1px solid var(--field-border);
            box-shadow: 0 12px 24px rgba(17,39,68,0.08);
        }
        .user-avatar {
            width: 38px; height: 38px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #2f7f64, #4fa77e); color: #fff; font-weight: 800; letter-spacing: 0.05em;
            box-shadow: 0 10px 20px rgba(47,127,100,0.22); flex-shrink: 0;
        }
        .user-meta { display: grid; gap: 2px; min-width: 0; }
        .user-meta strong { font-size: 0.95rem; line-height: 1.1; }
        .user-meta span { color: var(--muted); font-size: 0.8rem; font-weight: 700; }
        .nav a {
            display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px;
            border-radius: 16px; border: 1px solid var(--field-border); background: rgba(255,255,255,0.86);
            color: var(--text); font-weight: 800;
        }
        .nav a.auth-link { min-height: 42px; padding: 0 16px; font-size: 0.95rem; background: #ffffff; box-shadow: 0 12px 24px rgba(17,39,68,0.08); }
        .nav a.auth-link.primary { min-height: 42px; padding: 0 16px; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; border-color: transparent; box-shadow: 0 18px 32px rgba(184,134,47,0.20); }
        .nav a.active {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff; border-color: transparent; box-shadow: 0 18px 32px rgba(184,134,47,0.18);
        }
        .page { margin-top: 26px; display: grid; gap: 24px; }
        .hero { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; align-items: stretch; }
        .card {
            background: var(--panel); border: 1px solid var(--panel-border); box-shadow: var(--shadow);
            border-radius: 30px; padding: 30px; backdrop-filter: blur(12px);
        }
        .eyebrow {
            display: inline-flex; align-items: center; margin-bottom: 16px; padding: 7px 12px; border-radius: 999px;
            background: rgba(184,134,47,0.12); color: var(--accent-dark); font-size: 0.78rem; font-weight: 800;
            letter-spacing: 0.06em; text-transform: uppercase;
        }
        h1 {
            margin: 0 0 14px; font-family: "Space Grotesk", sans-serif;
            font-size: clamp(2.4rem, 5vw, 4.2rem); line-height: 1.02;
        }
        .lead { margin: 0 0 16px; color: var(--muted); line-height: 1.75; font-size: 1rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 14px 18px;
            border-radius: 16px; font-weight: 800; border: 1px solid var(--field-border); background: var(--field); color: var(--text);
        }
        .btn-primary {
            border: none; color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 32px rgba(184,134,47,0.24);
        }
        .stats { display: grid; gap: 14px; }
        .stat {
            padding: 18px 20px; border-radius: 20px; background: var(--field); border: 1px solid var(--field-border);
        }
        .stat-label { color: var(--muted); font-size: 0.92rem; margin-bottom: 8px; display: block; }
        .stat strong { display: block; font-family: "Space Grotesk", sans-serif; font-size: 1.8rem; margin-bottom: 4px; }
        .stat p { margin: 0; color: var(--muted); line-height: 1.6; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .module-card h2, .info-card h2 { margin: 0 0 8px; font-size: 1.25rem; }
        .module-card p, .info-card p { margin: 0; color: var(--muted); line-height: 1.7; }
        .module-card .btn, .info-card .btn { margin-top: 18px; }
        .footer-note { text-align: center; color: var(--muted); padding: 6px 0 0; font-size: 0.95rem; }
        @media (max-width: 960px) {
            .topbar { position: static; }
            .hero, .grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .shell { width: min(100%, calc(100% - 22px)); margin-top: 12px; }
            .topbar-inner { align-items: flex-start; flex-direction: column; }
            .nav { width: 100%; justify-content: flex-start; gap: 12px; }
            .nav-group { width: 100%; }
            .nav-group.auth { padding-left: 0; margin-left: 0; border-left: none; }
            .nav a { flex: 1 1 calc(50% - 10px); }
            .card { padding: 22px 18px; border-radius: 24px; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="topbar-inner">
                <a class="brand" href="index.php">
                    <span class="brand-mark">EEY</span>
                    <span class="brand-copy">
                        <strong>&#928;&#943;&#957;&#945;&#954;&#949;&#962; &#916;&#953;&#959;&#961;&#953;&#963;&#964;&#941;&#969;&#957;</strong>
                        <span>&#917;&#966;&#945;&#961;&#956;&#959;&#947;&#942; &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951;&#962; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957; &#954;&#945;&#953; &#949;&#953;&#948;&#953;&#954;&#959;&#964;&#942;&#964;&#969;&#957;</span>
                    </span>
                </a>
                <nav class="nav" aria-label="&#922;&#973;&#961;&#953;&#945; &#960;&#955;&#959;&#942;&#947;&#951;&#963;&#951;">
                    <div class="nav-group main">
                        <a class="active" href="index.php">&#913;&#961;&#967;&#953;&#954;&#942;</a>
                        <a href="modules/search/searchdashboard.php">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</a>
                        <?php if ($isAdmin): ?><a href="modules/admin/admindashboard.php">Admin</a><?php endif; ?>
                        <?php if ($isCandidate): ?><a href="modules/candidate/candidatedashboard.php">Candidate</a><?php endif; ?>
                    </div>
                    <div class="nav-group auth">
                        <?php if ($isGuest): ?>
                            <a class="auth-link primary" href="auth/register.php">&#917;&#947;&#947;&#961;&#945;&#966;&#942;</a>
                            <a class="auth-link" href="auth/login.php">&#931;&#973;&#957;&#948;&#949;&#963;&#951;</a>
                        <?php else: ?>
                            <a class="user-chip" href="<?php echo h($dashboardHref); ?>" aria-label="<?php echo h($dashboardLabel); ?>">
                                <span class="user-avatar"><?php echo h($userInitials); ?></span>
                                <span class="user-meta">
                                    <strong><?php echo h($userFullName); ?></strong>
                                    <span><?php echo h($userRoleLabel ?? 'Συνδεδεμένος χρήστης'); ?></span>
                                </span>
                            </a>
                            <a class="auth-link" href="auth/logout.php">&#913;&#960;&#959;&#963;&#973;&#957;&#948;&#949;&#963;&#951;</a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </header>

        <main class="page">
            <section class="hero">
                <article class="card">
                    <span class="eyebrow">&#922;&#949;&#957;&#964;&#961;&#953;&#954;&#942; &#949;&#943;&#963;&#959;&#948;&#959;&#962;</span>
                    <h1>&#917;&#966;&#945;&#961;&#956;&#959;&#947;&#942; &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951;&#962; &#960;&#953;&#957;&#940;&#954;&#969;&#957; &#948;&#953;&#959;&#961;&#953;&#963;&#964;&#941;&#969;&#957; &#956;&#949; &#959;&#961;&#947;&#945;&#957;&#969;&#956;&#941;&#957;&#951; &#954;&#945;&#953; &#954;&#945;&#952;&#945;&#961;&#942; &#949;&#956;&#960;&#949;&#953;&#961;&#943;&#945;</h1>
                    <p class="lead">&#919; &#945;&#961;&#967;&#953;&#954;&#942; &#963;&#949;&#955;&#943;&#948;&#945; &#955;&#949;&#953;&#964;&#959;&#965;&#961;&#947;&#949;&#943; &#969;&#962; &#964;&#959; &#954;&#949;&#957;&#964;&#961;&#953;&#954;&#972; &#963;&#951;&#956;&#949;&#943;&#959; &#949;&#953;&#963;&#972;&#948;&#959;&#965; &#964;&#951;&#962; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962;. &#913;&#960;&#972; &#949;&#948;&#974; &#959; &#949;&#960;&#953;&#963;&#954;&#941;&#960;&#964;&#951;&#962; &#956;&#960;&#959;&#961;&#949;&#943; &#957;&#945; &#945;&#957;&#945;&#950;&#951;&#964;&#942;&#963;&#949;&#953; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#959;&#965;&#962;, &#957;&#945; &#949;&#957;&#951;&#956;&#949;&#961;&#969;&#952;&#949;&#943; &#947;&#953;&#945; &#964;&#959;&#957; &#963;&#954;&#959;&#960;&#972; &#964;&#959;&#965; &#963;&#965;&#963;&#964;&#942;&#956;&#945;&#964;&#959;&#962;, &#957;&#945; &#949;&#947;&#947;&#961;&#945;&#966;&#949;&#943; &#954;&#945;&#953; &#957;&#945; &#960;&#949;&#961;&#940;&#963;&#949;&#953; &#963;&#964;&#959; &#954;&#945;&#964;&#940;&#955;&#955;&#951;&#955;&#959; module &#945;&#957;&#940;&#955;&#959;&#947;&#945; &#956;&#949; &#964;&#959;&#957; &#961;&#972;&#955;&#959; &#964;&#959;&#965;.</p>
                    <p class="lead">&#931;&#964;&#972;&#967;&#959;&#962; &#964;&#951;&#962; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962; &#949;&#943;&#957;&#945;&#953; &#951; &#963;&#969;&#963;&#964;&#942; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951; &#954;&#945;&#953; &#960;&#945;&#961;&#959;&#965;&#963;&#943;&#945;&#963;&#951; &#963;&#964;&#959;&#953;&#967;&#949;&#943;&#969;&#957; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;, &#951; &#945;&#963;&#966;&#945;&#955;&#942;&#962; &#948;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#974;&#957; &#954;&#945;&#953; &#951; &#958;&#949;&#954;&#940;&#952;&#945;&#961;&#951; &#948;&#953;&#940;&#954;&#961;&#953;&#963;&#951; &#945;&#957;&#940;&#956;&#949;&#963;&#945; &#963;&#949; &#948;&#951;&#956;&#972;&#963;&#953;&#945; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;, candidate &#955;&#949;&#953;&#964;&#959;&#965;&#961;&#947;&#943;&#949;&#962; &#954;&#945;&#953; admin &#948;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951;.</p>
                    <div class="actions">
                        <a class="btn btn-primary" href="modules/search/searchdashboard.php">&#924;&#949;&#964;&#940;&#946;&#945;&#963;&#951; &#963;&#964;&#951;&#957; &#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</a>
                        <?php if ($isGuest): ?>
                            <a class="btn" href="auth/register.php">&#916;&#951;&#956;&#953;&#959;&#965;&#961;&#947;&#943;&#945; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#959;&#973;</a>
                        <?php else: ?>
                            <a class="btn" href="<?php echo h($dashboardHref); ?>"><?php echo h($dashboardLabel); ?></a>
                        <?php endif; ?>
                    </div>
                </article>

                <aside class="card stats">
                    <div class="stat">
                        <span class="stat-label">&#916;&#951;&#956;&#972;&#963;&#953;&#945; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;</span>
                        <strong>Search</strong>
                        <p>&#916;&#951;&#956;&#972;&#963;&#953;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957; &#956;&#949; keyword search, &#966;&#943;&#955;&#964;&#961;&#945; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;&#962; &#954;&#945;&#953; &#963;&#965;&#957;&#959;&#960;&#964;&#953;&#954;&#942; &#963;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#942; &#949;&#953;&#954;&#972;&#957;&#945;.</p>
                    </div>
                    <div class="stat">
                        <span class="stat-label">&#921;&#948;&#953;&#969;&#964;&#953;&#954;&#942; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951;</span>
                        <strong>Candidate</strong>
                        <p>&#928;&#961;&#959;&#963;&#969;&#960;&#953;&#954;&#972; &#960;&#961;&#959;&#966;&#943;&#955; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#959;&#965;, &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951; &#964;&#951;&#962; &#945;&#943;&#964;&#951;&#963;&#951;&#962;, &#949;&#953;&#948;&#959;&#960;&#959;&#953;&#942;&#963;&#949;&#953;&#962; &#954;&#945;&#953; &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951; &#940;&#955;&#955;&#969;&#957; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;.</p>
                    </div>
                    <div class="stat">
                        <span class="stat-label">&#916;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951; &#963;&#965;&#963;&#964;&#942;&#956;&#945;&#964;&#959;&#962;</span>
                        <strong>Admin</strong>
                        <p>&#916;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951; &#967;&#961;&#951;&#963;&#964;&#974;&#957;, &#955;&#953;&#963;&#964;&#974;&#957;, &#945;&#957;&#945;&#966;&#959;&#961;&#974;&#957; &#954;&#945;&#953; &#963;&#965;&#957;&#959;&#955;&#953;&#954;&#942; &#949;&#960;&#959;&#960;&#964;&#949;&#943;&#945; &#964;&#951;&#962; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962;.</p>
                    </div>
                </aside>
            </section>

            <section class="grid">
                <article class="card module-card">
                    <span class="eyebrow">01</span>
                    <h2>Search Module</h2>
                    <p>&#919; &#948;&#951;&#956;&#972;&#963;&#953;&#945; &#949;&#957;&#972;&#964;&#951;&#964;&#945; &#964;&#959;&#965; &#963;&#965;&#963;&#964;&#942;&#956;&#945;&#964;&#959;&#962;, &#972;&#960;&#959;&#965; &#959; &#949;&#960;&#953;&#963;&#954;&#941;&#960;&#964;&#951;&#962; &#956;&#960;&#959;&#961;&#949;&#943; &#957;&#945; &#949;&#957;&#964;&#959;&#960;&#943;&#963;&#949;&#953; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#959;&#965;&#962;, &#957;&#945; &#966;&#953;&#955;&#964;&#961;&#940;&#961;&#949;&#953; &#945;&#957;&#940; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945; &#954;&#945;&#953; &#957;&#945; &#948;&#949;&#953; &#963;&#965;&#957;&#959;&#960;&#964;&#953;&#954;&#940; &#963;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#940;.</p>
                    <a class="btn btn-primary" href="modules/search/searchdashboard.php">&#902;&#957;&#959;&#953;&#947;&#956;&#945; Search</a>
                </article>
                <article class="card module-card">
                    <span class="eyebrow">02</span>
                    <h2>Authentication</h2>
                    <p>&#913;&#963;&#966;&#945;&#955;&#941;&#962; authentication &#956;&#949; &#949;&#947;&#947;&#961;&#945;&#966;&#942;, &#963;&#973;&#957;&#948;&#949;&#963;&#951; &#954;&#945;&#953; &#949;&#955;&#949;&#947;&#967;&#972;&#956;&#949;&#957;&#951; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951; &#945;&#957;&#940; &#961;&#972;&#955;&#959; &#947;&#953;&#945; &#949;&#960;&#953;&#963;&#954;&#941;&#960;&#964;&#949;&#962;, candidates &#954;&#945;&#953; administrators.</p>
                    <a class="btn" href="auth/login.php">&#931;&#973;&#957;&#948;&#949;&#963;&#951;</a>
                </article>
                <article class="card module-card">
                    <span class="eyebrow">03</span>
                    <h2>&#928;&#949;&#961;&#953;&#949;&#967;&#972;&#956;&#949;&#957;&#959; &#949;&#961;&#947;&#945;&#963;&#943;&#945;&#962;</h2>
                    <p>&#919; &#948;&#959;&#956;&#942; &#964;&#951;&#962; &#949;&#961;&#947;&#945;&#963;&#943;&#945;&#962; &#963;&#964;&#951;&#961;&#943;&#950;&#949;&#964;&#945;&#953; &#963;&#949; users, candidate profiles, specialties, &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;, &#963;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#940; &#954;&#945;&#953; &#960;&#961;&#959;&#963;&#964;&#945;&#964;&#949;&#965;&#956;&#941;&#957;&#945; dashboards &#947;&#953;&#945; &#954;&#940;&#952;&#949; &#954;&#945;&#964;&#951;&#947;&#959;&#961;&#943;&#945; &#967;&#961;&#942;&#963;&#964;&#951;.</p>
                    <?php if ($isGuest): ?>
                        <a class="btn" href="auth/register.php">&#917;&#947;&#947;&#961;&#945;&#966;&#942;</a>
                    <?php else: ?>
                        <a class="btn" href="<?php echo h($dashboardHref); ?>">Dashboard</a>
                    <?php endif; ?>
                </article>
            </section>

            <section class="grid">
                <article class="card info-card">
                    <h2>&#932;&#953; &#956;&#960;&#959;&#961;&#949;&#943; &#957;&#945; &#954;&#940;&#957;&#949;&#953; &#959; &#949;&#960;&#953;&#963;&#954;&#941;&#960;&#964;&#951;&#962;</h2>
                    <p>&#925;&#945; &#960;&#949;&#961;&#953;&#951;&#947;&#951;&#952;&#949;&#943; &#963;&#964;&#951;&#957; &#945;&#961;&#967;&#953;&#954;&#942; &#963;&#949;&#955;&#943;&#948;&#945;, &#957;&#945; &#967;&#961;&#951;&#963;&#953;&#956;&#959;&#960;&#959;&#953;&#942;&#963;&#949;&#953; &#964;&#951; &#948;&#951;&#956;&#972;&#963;&#953;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;, &#957;&#945; &#949;&#957;&#951;&#956;&#949;&#961;&#969;&#952;&#949;&#943; &#947;&#953;&#945; &#964;&#951; &#955;&#949;&#953;&#964;&#959;&#965;&#961;&#947;&#943;&#945; &#964;&#951;&#962; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962; &#954;&#945;&#953; &#957;&#945; &#948;&#951;&#956;&#953;&#959;&#965;&#961;&#947;&#942;&#963;&#949;&#953; &#957;&#941;&#959; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972;.</p>
                </article>
                <article class="card info-card">
                    <h2>&#932;&#953; &#946;&#955;&#941;&#960;&#949;&#953; &#959; candidate</h2>
                    <p>&#924;&#949;&#964;&#940; &#964;&#951; &#963;&#973;&#957;&#948;&#949;&#963;&#951; &#945;&#960;&#959;&#954;&#964;&#940; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951; &#963;&#964;&#959; &#960;&#961;&#959;&#963;&#969;&#960;&#953;&#954;&#972; &#964;&#959;&#965; &#960;&#961;&#959;&#966;&#943;&#955;, &#963;&#964;&#951;&#957; &#960;&#959;&#961;&#949;&#943;&#945; &#964;&#951;&#962; &#945;&#943;&#964;&#951;&#963;&#942;&#962; &#964;&#959;&#965;, &#963;&#964;&#953;&#962; &#949;&#953;&#948;&#959;&#960;&#959;&#953;&#942;&#963;&#949;&#953;&#962; &#964;&#959;&#965; &#954;&#945;&#953; &#963;&#964;&#951;&#957; &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951; &#940;&#955;&#955;&#969;&#957; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;.</p>
                </article>
                <article class="card info-card">
                    <h2>&#932;&#953; &#946;&#955;&#941;&#960;&#949;&#953; &#959; admin</h2>
                    <p>&#927; &#948;&#953;&#945;&#967;&#949;&#953;&#961;&#953;&#963;&#964;&#942;&#962; &#941;&#967;&#949;&#953; &#960;&#961;&#972;&#963;&#946;&#945;&#963;&#951; &#963;&#964;&#951; &#948;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951; &#967;&#961;&#951;&#963;&#964;&#974;&#957;, &#963;&#964;&#951; &#948;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951; &#955;&#953;&#963;&#964;&#974;&#957;, &#963;&#964;&#945; reports &#954;&#945;&#953; &#963;&#964;&#951; &#963;&#965;&#957;&#959;&#955;&#953;&#954;&#942; &#949;&#960;&#959;&#960;&#964;&#949;&#943;&#945; &#964;&#951;&#962; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962;.</p>
                </article>
            </section>

            <div class="footer-note">&#913;&#961;&#967;&#953;&#954;&#942; &#963;&#949;&#955;&#943;&#948;&#945; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962; &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951;&#962; &#960;&#953;&#957;&#940;&#954;&#969;&#957; &#948;&#953;&#959;&#961;&#953;&#963;&#964;&#941;&#969;&#957;.</div>
        </main>
    </div>
</body>
</html>


