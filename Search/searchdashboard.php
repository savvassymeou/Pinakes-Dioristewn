<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

function search_text(?string $value, string $fallback = '-'): string
{
    $text = trim((string) $value);
    return $text !== '' ? $text : $fallback;
}

$role = current_user_role();
$isGuest = $role === null;
$isAdmin = $role === ROLE_ADMIN;
$isCandidate = $role === ROLE_CANDIDATE;

$specialties = [];
$specialtiesResult = $conn->query('SELECT id, title FROM specialties ORDER BY title ASC');
if ($specialtiesResult) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

$searchName = trim($_GET['name'] ?? '');
$searchSpecialtyId = (int) ($_GET['specialty_id'] ?? 0);
$searchOrder = $_GET['order'] ?? 'rank_asc';
$searchResults = [];

$searchSql = "SELECT u.first_name, u.last_name, s.title AS specialty_title, cp.application_status, cp.ranking_position, cp.points, cp.created_at FROM candidate_profiles cp INNER JOIN users u ON u.id = cp.user_id LEFT JOIN specialties s ON s.id = cp.specialty_id WHERE 1 = 1";
$searchTypes = '';
$searchParams = [];
if ($searchName !== '') {
    $searchSql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR CONCAT(u.last_name, ' ', u.first_name) LIKE ?)";
    $searchTypes .= 'ssss';
    $term = '%' . $searchName . '%';
    $searchParams = [$term, $term, $term, $term];
}
if ($searchSpecialtyId > 0) {
    $searchSql .= ' AND cp.specialty_id = ?';
    $searchTypes .= 'i';
    $searchParams[] = $searchSpecialtyId;
}
switch ($searchOrder) {
    case 'name_asc': $searchSql .= ' ORDER BY u.last_name ASC, u.first_name ASC'; break;
    case 'points_desc': $searchSql .= ' ORDER BY cp.points IS NULL, cp.points DESC, u.last_name ASC'; break;
    case 'recent_desc': $searchSql .= ' ORDER BY cp.created_at DESC, u.last_name ASC'; break;
    default: $searchSql .= ' ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, u.last_name ASC';
}
$searchSql .= ' LIMIT 30';
$searchStmt = $conn->prepare($searchSql);
if ($searchStmt) {
    if ($searchParams !== []) {
        $searchStmt->bind_param($searchTypes, ...$searchParams);
    }
    $searchStmt->execute();
    $result = $searchStmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
    }
    $searchStmt->close();
}

$overview = ['total_candidates' => 0, 'specialty_count' => count($specialties), 'new_candidates_year' => 0];
$overviewResult = $conn->query('SELECT COUNT(*) AS total_candidates FROM candidate_profiles');
if ($overviewResult) {
    $overviewRow = $overviewResult->fetch_assoc();
    $overview['total_candidates'] = (int) ($overviewRow['total_candidates'] ?? 0);
}
$newCandidatesResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'candidate' AND YEAR(created_at) = YEAR(CURDATE())");
if ($newCandidatesResult) {
    $newCandidatesRow = $newCandidatesResult->fetch_assoc();
    $overview['new_candidates_year'] = (int) ($newCandidatesRow['total'] ?? 0);
}

$statsSpecialtyId = (int) ($_GET['stats_specialty_id'] ?? ($specialties[0]['id'] ?? 0));
$specialtyOverview = ['candidate_count' => 0, 'average_points' => null];
$yearlyRows = [];
$periodRows = [];
$maxYearlyCount = 0;
$maxPeriodCount = 0;
if ($statsSpecialtyId > 0) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS candidate_count, AVG(points) AS average_points FROM candidate_profiles WHERE specialty_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $statsSpecialtyId);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        if ($row) { $specialtyOverview = $row; }
        $stmt->close();
    }
    $stmt = $conn->prepare('SELECT YEAR(created_at) AS report_year, COUNT(*) AS candidate_count FROM candidate_profiles WHERE specialty_id = ? GROUP BY YEAR(created_at) ORDER BY report_year ASC');
    if ($stmt) {
        $stmt->bind_param('i', $statsSpecialtyId);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) {
            while ($row = $r->fetch_assoc()) { $yearlyRows[] = $row; $maxYearlyCount = max($maxYearlyCount, (int) $row['candidate_count']); }
        }
        $stmt->close();
    }
    $stmt = $conn->prepare('SELECT YEAR(created_at) AS period_year, CASE WHEN MONTH(created_at) BETWEEN 1 AND 6 THEN "A" ELSE "B" END AS semester_code, COUNT(*) AS candidate_count FROM candidate_profiles WHERE specialty_id = ? GROUP BY YEAR(created_at), CASE WHEN MONTH(created_at) BETWEEN 1 AND 6 THEN "A" ELSE "B" END ORDER BY period_year ASC, semester_code ASC');
    if ($stmt) {
        $stmt->bind_param('i', $statsSpecialtyId);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) {
            while ($row = $r->fetch_assoc()) { $periodRows[] = $row; $maxPeriodCount = max($maxPeriodCount, (int) $row['candidate_count']); }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h(APP_NAME); ?> | &#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#eef3f8;--bg-accent:#dce7f5;--panel:rgba(255,255,255,.96);--panel-border:rgba(21,55,92,.12);--text:#14263d;--muted:#5d7088;--accent:#b8862f;--accent-2:#d9ab55;--field:#f7f9fc;--field-border:#cfdae8;--shadow:0 24px 60px rgba(17,39,68,.14)}
*{box-sizing:border-box} body{margin:0;min-height:100vh;font-family:"Manrope",sans-serif;color:var(--text);background:radial-gradient(circle at top,rgba(185,134,47,.16),transparent 22%),radial-gradient(circle at left,rgba(52,103,168,.10),transparent 26%),linear-gradient(180deg,var(--bg) 0%,var(--bg-accent) 100%)} a{text-decoration:none;color:inherit}
.shell{width:min(1200px,calc(100% - 32px));margin:0 auto 40px;padding-top:24px}.topbar{width:100vw;margin-left:calc(50% - 50vw);padding:16px 0;background:rgba(255,255,255,.92);border-top:1px solid var(--panel-border);border-bottom:1px solid var(--panel-border);box-shadow:0 18px 40px rgba(17,39,68,.10);backdrop-filter:blur(12px);position:sticky;top:0;z-index:10}.topbar-inner{width:min(1200px,calc(100% - 32px));margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px}
.brand{display:flex;align-items:center;gap:12px;min-width:0}.brand-mark{width:44px;height:44px;display:grid;place-items:center;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;font-family:"Space Grotesk",sans-serif;font-weight:800;box-shadow:0 14px 28px rgba(184,134,47,.22);flex-shrink:0}.brand-copy strong{display:block;font-size:1rem}.brand-copy span{display:block;margin-top:2px;color:var(--muted);font-size:.92rem;font-weight:600}
.nav{display:flex;align-items:center;gap:26px;flex-wrap:wrap;justify-content:flex-end}.nav-group{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.nav-group.auth{padding-left:28px;margin-left:18px;border-left:1px solid rgba(21,55,92,.14)}.nav a{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:16px;border:1px solid var(--field-border);background:rgba(255,255,255,.86);color:var(--text);font-weight:800}.nav a.auth-link{min-height:42px;padding:0 16px;font-size:.95rem;background:#fff;box-shadow:0 12px 24px rgba(17,39,68,.08)}.nav a.auth-link.primary{min-height:42px;padding:0 16px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;border-color:transparent;box-shadow:0 18px 32px rgba(184,134,47,.20)}.nav a.active{background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;border-color:transparent;box-shadow:0 18px 32px rgba(184,134,47,.18)}
.page{margin-top:26px;display:grid;gap:24px}.hero,.split{display:grid;grid-template-columns:1.1fr .9fr;gap:24px}.card{background:var(--panel);border:1px solid var(--panel-border);box-shadow:var(--shadow);border-radius:30px;padding:30px;backdrop-filter:blur(12px)}.eyebrow{display:inline-flex;align-items:center;margin-bottom:16px;padding:7px 12px;border-radius:999px;background:rgba(184,134,47,.12);color:#7a5720;font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}h1{margin:0 0 14px;font-family:"Space Grotesk",sans-serif;font-size:clamp(2.2rem,4.8vw,3.8rem);line-height:1.04}h2{margin:0 0 8px;font-size:1.45rem}.lead,.copy,.metric p,.empty,.footer-note{color:var(--muted);line-height:1.7}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:14px 18px;border-radius:16px;font-weight:800;border:1px solid var(--field-border);background:var(--field);color:var(--text)}.btn-primary{border:none;color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 18px 32px rgba(184,134,47,.24)}
.stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.metric,.tile{padding:18px 20px;border-radius:20px;background:var(--field);border:1px solid var(--field-border)}.metric span,.tile span{display:block;color:var(--muted);font-size:.92rem;margin-bottom:8px}.metric strong,.tile strong{display:block;font-family:"Space Grotesk",sans-serif;font-size:1.8rem;margin-bottom:4px}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:16px}.field-full{grid-column:1 / -1}label{display:block;margin:0 0 8px;font-weight:800}input,select{width:100%;padding:15px 16px;border-radius:16px;border:1px solid var(--field-border);background:var(--field);color:var(--text);font-size:1rem;font-family:inherit}.table-wrap{overflow:auto;margin-top:18px}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:14px 12px;text-align:left;border-bottom:1px solid #e3ebf4}th{color:var(--muted);font-size:.86rem;text-transform:uppercase;letter-spacing:.04em}.pill{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:rgba(184,134,47,.12);color:#7a5720;font-weight:700;font-size:.9rem}
.bar-list{display:grid;gap:12px;margin-top:16px}.bar-row{display:grid;gap:6px}.bar-meta{display:flex;justify-content:space-between;gap:12px;font-weight:700}.bar-track{width:100%;height:14px;border-radius:999px;background:#e8eff7;overflow:hidden}.bar-fill{height:100%;background:linear-gradient(135deg,var(--accent),var(--accent-2))}
@media (max-width:980px){.topbar{position:static}.hero,.split,.stats-grid{grid-template-columns:1fr}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:760px){.shell{width:min(100%,calc(100% - 22px));margin-top:12px}.topbar-inner{align-items:flex-start;flex-direction:column}.nav{width:100%;justify-content:flex-start;gap:12px}.nav-group{width:100%}.nav-group.auth{padding-left:0;margin-left:0;border-left:none}.nav a{flex:1 1 calc(50% - 10px)}.card{padding:22px 18px;border-radius:24px}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="shell">
<header class="topbar"><div class="topbar-inner"><a class="brand" href="../index.php"><span class="brand-mark">EEY</span><span class="brand-copy"><strong>&#928;&#943;&#957;&#945;&#954;&#949;&#962; &#916;&#953;&#959;&#961;&#953;&#963;&#964;&#941;&#969;&#957;</strong><span>&#917;&#966;&#945;&#961;&#956;&#959;&#947;&#942; &#960;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#951;&#962; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957; &#954;&#945;&#953; &#949;&#953;&#948;&#953;&#954;&#959;&#964;&#942;&#964;&#969;&#957;</span></span></a><nav class="nav" aria-label="&#922;&#973;&#961;&#953;&#945; &#960;&#955;&#959;&#942;&#947;&#951;&#963;&#951;"><div class="nav-group main"><a href="../index.php">&#913;&#961;&#967;&#953;&#954;&#942;</a><a class="active" href="searchdashboard.php">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</a><?php if ($isAdmin): ?><a href="../modules/admin/dashboard.php">Admin</a><?php endif; ?><?php if ($isCandidate): ?><a href="../modules/admin/candidate/dashboard.php">Candidate</a><?php endif; ?></div><div class="nav-group auth"><?php if ($isGuest): ?><a class="auth-link primary" href="../auth/register.php">&#917;&#947;&#947;&#961;&#945;&#966;&#942;</a><a class="auth-link" href="../auth/login.php">&#931;&#973;&#957;&#948;&#949;&#963;&#951;</a><?php else: ?><a class="auth-link" href="../auth/logout.php">&#913;&#960;&#959;&#963;&#973;&#957;&#948;&#949;&#963;&#951;</a><?php endif; ?></div></nav></div></header>
<main class="page">
<section class="hero"><article class="card"><span class="eyebrow">Public Search Module</span><h1>&#916;&#951;&#956;&#972;&#963;&#953;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957; &#954;&#945;&#953; &#963;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#940; &#963;&#949; &#941;&#957;&#945; &#963;&#951;&#956;&#949;&#943;&#959;</h1><p class="lead">&#919; &#963;&#949;&#955;&#943;&#948;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;&#962; &#949;&#943;&#957;&#945;&#953; &#951; &#948;&#951;&#956;&#972;&#963;&#953;&#945; &#949;&#943;&#963;&#959;&#948;&#959;&#962; &#964;&#951;&#962; &#949;&#966;&#945;&#961;&#956;&#959;&#947;&#942;&#962; &#954;&#945;&#953; &#963;&#965;&#957;&#948;&#965;&#940;&#950;&#949;&#953; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;, &#966;&#943;&#955;&#964;&#961;&#945; &#954;&#945;&#953; &#963;&#965;&#947;&#954;&#949;&#957;&#964;&#961;&#969;&#964;&#953;&#954;&#940; &#963;&#964;&#959;&#953;&#967;&#949;&#943;&#945;.</p><p class="lead">&#927; &#949;&#960;&#953;&#963;&#954;&#941;&#960;&#964;&#951;&#962; &#956;&#960;&#959;&#961;&#949;&#943; &#957;&#945; &#945;&#957;&#945;&#950;&#951;&#964;&#942;&#963;&#949;&#953; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#959;&#965;&#962; &#956;&#949; &#946;&#940;&#963;&#951; &#964;&#959; &#959;&#957;&#959;&#956;&#945;&#964;&#949;&#960;&#974;&#957;&#965;&#956;&#959; &#942; &#964;&#951;&#957; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945; &#954;&#945;&#953; &#957;&#945; &#960;&#949;&#961;&#940;&#963;&#949;&#953; &#963;&#964;&#951;&#957; &#949;&#947;&#947;&#961;&#945;&#966;&#942;.</p><div class="actions"><a class="btn btn-primary" href="#search-form">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;</a><a class="btn" href="#stats">&#928;&#961;&#959;&#946;&#959;&#955;&#942; &#963;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#974;&#957;</a></div></article><aside class="stats-grid"><div class="metric"><span>&#931;&#965;&#957;&#959;&#955;&#953;&#954;&#959;&#943; &#965;&#960;&#959;&#968;&#942;&#966;&#953;&#959;&#953;</span><strong><?php echo (int) $overview['total_candidates']; ?></strong><p>&#922;&#945;&#964;&#945;&#947;&#961;&#945;&#956;&#956;&#941;&#957;&#949;&#962; &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#963;&#964;&#951; &#946;&#940;&#963;&#951;.</p></div><div class="metric"><span>&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#949;&#962;</span><strong><?php echo (int) $overview['specialty_count']; ?></strong><p>&#916;&#953;&#945;&#952;&#941;&#963;&#953;&#956;&#949;&#962; &#954;&#945;&#964;&#951;&#947;&#959;&#961;&#943;&#949;&#962; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;&#962;.</p></div><div class="metric"><span>&#925;&#941;&#959;&#953; &#965;&#960;&#959;&#968;&#942;&#966;&#953;&#959;&#953; <?php echo date('Y'); ?></span><strong><?php echo (int) $overview['new_candidates_year']; ?></strong><p>&#925;&#941;&#949;&#962; candidate &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#964;&#959;&#965; &#964;&#961;&#941;&#967;&#959;&#957;&#964;&#959;&#962; &#941;&#964;&#959;&#965;&#962;.</p></div></aside></section>
<section class="card" id="search-form"><h2>&#934;&#972;&#961;&#956;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;&#962;</h2><p class="copy">&#931;&#965;&#956;&#960;&#955;&#942;&#961;&#969;&#963;&#949; &#972;&#957;&#959;&#956;&#945;, &#949;&#960;&#974;&#957;&#965;&#956;&#959; &#942; &#959;&#957;&#959;&#956;&#945;&#964;&#949;&#960;&#974;&#957;&#965;&#956;&#959; &#954;&#945;&#953; &#963;&#965;&#957;&#948;&#973;&#945;&#963;&#949; &#964;&#959; &#956;&#949; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945; &#942; &#964;&#945;&#958;&#953;&#957;&#972;&#956;&#951;&#963;&#951;.</p><form class="form-grid" method="get" action="searchdashboard.php#search-form"><div class="field-full"><label for="name">&#923;&#941;&#958;&#951; &#954;&#955;&#949;&#953;&#948;&#943;</label><input id="name" name="name" type="text" value="<?php echo h($searchName); ?>" placeholder="&#960;.&#967;. &#924;&#945;&#961;&#943;&#945; &#928;&#945;&#960;&#945;&#948;&#959;&#960;&#959;&#973;&#955;&#959;&#965;"></div><div><label for="specialty_id">&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</label><select id="specialty_id" name="specialty_id"><option value="0">&#908;&#955;&#949;&#962; &#959;&#953; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#949;&#962;</option><?php foreach ($specialties as $specialty): ?><option value="<?php echo (int) $specialty['id']; ?>" <?php echo $searchSpecialtyId === (int) $specialty['id'] ? 'selected' : ''; ?>><?php echo h(search_text($specialty['title'])); ?></option><?php endforeach; ?></select></div><div><label for="order">&#932;&#945;&#958;&#953;&#957;&#972;&#956;&#951;&#963;&#951;</label><select id="order" name="order"><option value="rank_asc" <?php echo $searchOrder === 'rank_asc' ? 'selected' : ''; ?>>&#920;&#941;&#963;&#951; &#960;&#943;&#957;&#945;&#954;&#945;</option><option value="name_asc" <?php echo $searchOrder === 'name_asc' ? 'selected' : ''; ?>>&#913;&#955;&#966;&#945;&#946;&#951;&#964;&#953;&#954;&#940;</option><option value="points_desc" <?php echo $searchOrder === 'points_desc' ? 'selected' : ''; ?>>&#924;&#972;&#961;&#953;&#945; &#966;&#952;&#943;&#957;&#959;&#965;&#963;&#945;</option><option value="recent_desc" <?php echo $searchOrder === 'recent_desc' ? 'selected' : ''; ?>>&#928;&#953;&#959; &#960;&#961;&#972;&#963;&#966;&#945;&#964;&#959;&#953;</option></select></div><div><label>&nbsp;</label><button class="btn btn-primary" type="submit" style="width:100%;">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</button></div></form><div class="table-wrap"><table><thead><tr><th>&#920;&#941;&#963;&#951;</th><th>&#927;&#957;&#959;&#956;&#945;&#964;&#949;&#960;&#974;&#957;&#965;&#956;&#959;</th><th>&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</th><th>&#924;&#972;&#961;&#953;&#945;</th><th>&#922;&#945;&#964;&#940;&#963;&#964;&#945;&#963;&#951;</th><th>&#904;&#964;&#959;&#962; &#949;&#947;&#947;&#961;&#945;&#966;&#942;&#962;</th></tr></thead><tbody><?php if ($searchResults === []): ?><tr><td colspan="6" class="empty">&#916;&#949;&#957; &#946;&#961;&#941;&#952;&#951;&#954;&#945;&#957; &#945;&#960;&#959;&#964;&#949;&#955;&#941;&#963;&#956;&#945;&#964;&#945; &#947;&#953;&#945; &#964;&#945; &#966;&#943;&#955;&#964;&#961;&#945; &#960;&#959;&#965; &#949;&#960;&#941;&#955;&#949;&#958;&#949;&#962;.</td></tr><?php else: ?><?php foreach ($searchResults as $row): ?><tr><td><?php echo $row['ranking_position'] !== null ? (int) $row['ranking_position'] : '-'; ?></td><td><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></td><td><?php echo h(search_text($row['specialty_title'] ?? null)); ?></td><td><?php echo $row['points'] !== null ? number_format((float) $row['points'], 2) : '-'; ?></td><td><span class="pill"><?php echo h(search_text($row['application_status'] ?? null)); ?></span></td><td><?php echo !empty($row['created_at']) ? h(date('Y', strtotime($row['created_at']))) : '-'; ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>
<section class="split"><article class="card"><h2>&#917;&#947;&#947;&#961;&#945;&#966;&#942; &#963;&#964;&#959; &#963;&#973;&#963;&#964;&#951;&#956;&#945;</h2><p class="copy">&#917;&#947;&#947;&#961;&#940;&#966;&#959;&#957;&#964;&#945;&#953; &#972;&#963;&#959;&#953; &#952;&#941;&#955;&#959;&#965;&#957; &#957;&#945; &#945;&#960;&#959;&#954;&#964;&#942;&#963;&#959;&#965;&#957; candidate &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972; &#954;&#945;&#953; &#957;&#945; &#963;&#965;&#957;&#948;&#941;&#963;&#959;&#965;&#957; &#964;&#959; &#960;&#961;&#959;&#966;&#943;&#955; &#964;&#959;&#965;&#962; &#956;&#949; &#964;&#959;&#957; &#965;&#960;&#959;&#968;&#942;&#966;&#953;&#959; &#964;&#969;&#957; &#960;&#953;&#957;&#940;&#954;&#969;&#957;.</p><div class="actions"><a class="btn btn-primary" href="../auth/register.php">&#924;&#949;&#964;&#940;&#946;&#945;&#963;&#951; &#963;&#964;&#951;&#957; &#917;&#947;&#947;&#961;&#945;&#966;&#942;</a><a class="btn" href="../auth/login.php">&#904;&#967;&#969; &#942;&#948;&#951; &#955;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972;</a></div></article><article class="card"><h2>&#922;&#945;&#964;&#940;&#964;&#945;&#958;&#951; &#945;&#957;&#940; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</h2><p class="copy">&#915;&#961;&#942;&#947;&#959;&#961;&#951; &#963;&#965;&#947;&#954;&#949;&#957;&#964;&#961;&#969;&#964;&#953;&#954;&#942; &#949;&#953;&#954;&#972;&#957;&#945; &#964;&#969;&#957; &#949;&#953;&#948;&#953;&#954;&#959;&#964;&#942;&#964;&#969;&#957; &#956;&#949; &#964;&#953;&#962; &#960;&#949;&#961;&#953;&#963;&#963;&#972;&#964;&#949;&#961;&#949;&#962; &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962;.</p><?php $topSpecialties = []; $topResult = $conn->query('SELECT s.title, COUNT(cp.id) AS candidate_count FROM specialties s LEFT JOIN candidate_profiles cp ON cp.specialty_id = s.id GROUP BY s.id, s.title ORDER BY candidate_count DESC, s.title ASC LIMIT 6'); if ($topResult) { while ($row = $topResult->fetch_assoc()) { $topSpecialties[] = $row; } } ?><?php if ($topSpecialties === []): ?><p class="empty">&#916;&#949;&#957; &#965;&#960;&#940;&#961;&#967;&#959;&#965;&#957; &#945;&#954;&#972;&#956;&#951; &#948;&#949;&#948;&#959;&#956;&#941;&#957;&#945; &#947;&#953;&#945; &#960;&#961;&#959;&#946;&#959;&#955;&#942;.</p><?php else: ?><div class="bar-list"><?php $topMax = max(array_map(static fn($row) => (int) $row['candidate_count'], $topSpecialties)); ?><?php foreach ($topSpecialties as $row): ?><?php $count = (int) $row['candidate_count']; $width = $topMax > 0 ? max(12, (int) round(($count / $topMax) * 100)) : 12; ?><div class="bar-row"><div class="bar-meta"><span><?php echo h(search_text($row['title'])); ?></span><strong><?php echo $count; ?></strong></div><div class="bar-track"><div class="bar-fill" style="width: <?php echo $width; ?>%"></div></div></div><?php endforeach; ?></div><?php endif; ?></article></section>
<section class="card" id="stats"><h2>&#931;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#940; &#945;&#957;&#940; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</h2><p class="copy">&#931;&#965;&#947;&#954;&#949;&#957;&#964;&#961;&#969;&#964;&#953;&#954;&#940; &#963;&#964;&#959;&#953;&#967;&#949;&#943;&#945; &#954;&#945;&#953; &#945;&#957;&#940;&#955;&#965;&#963;&#951; &#945;&#957;&#940; &#941;&#964;&#959;&#962; &#954;&#945;&#953; &#960;&#949;&#961;&#943;&#959;&#948;&#959;.</p><form class="form-grid" method="get" action="searchdashboard.php#stats"><div><label for="stats_specialty_id">&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</label><select id="stats_specialty_id" name="stats_specialty_id"><?php foreach ($specialties as $specialty): ?><option value="<?php echo (int) $specialty['id']; ?>" <?php echo $statsSpecialtyId === (int) $specialty['id'] ? 'selected' : ''; ?>><?php echo h(search_text($specialty['title'])); ?></option><?php endforeach; ?></select></div><div><label>&#933;&#960;&#959;&#968;&#942;&#966;&#953;&#959;&#953; &#963;&#964;&#951;&#957; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</label><input type="text" value="<?php echo (int) ($specialtyOverview['candidate_count'] ?? 0); ?>" readonly></div><div><label>&#924;&#941;&#963;&#959;&#962; &#972;&#961;&#959;&#962; &#956;&#959;&#961;&#943;&#969;&#957;</label><input type="text" value="<?php echo $specialtyOverview['average_points'] !== null ? number_format((float) $specialtyOverview['average_points'], 2) : '-'; ?>" readonly></div><div><label>&nbsp;</label><button class="btn btn-primary" type="submit" style="width:100%;">&#917;&#957;&#951;&#956;&#941;&#961;&#969;&#963;&#951;</button></div></form><div class="split" style="margin-top:20px;"><article class="tile"><span>&#913;&#957;&#940; &#941;&#964;&#959;&#962;</span><strong><?php echo h(search_text(($specialties[array_search($statsSpecialtyId, array_column($specialties, 'id'))]['title'] ?? null), '-')); ?></strong><?php if ($yearlyRows === []): ?><p class="empty">&#916;&#949;&#957; &#965;&#960;&#940;&#961;&#967;&#959;&#965;&#957; &#945;&#954;&#972;&#956;&#951; &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962;.</p><?php else: ?><div class="bar-list"><?php foreach ($yearlyRows as $row): ?><?php $count = (int) $row['candidate_count']; $width = $maxYearlyCount > 0 ? max(12, (int) round(($count / $maxYearlyCount) * 100)) : 12; ?><div class="bar-row"><div class="bar-meta"><span><?php echo h((string) $row['report_year']); ?></span><strong><?php echo $count; ?></strong></div><div class="bar-track"><div class="bar-fill" style="width: <?php echo $width; ?>%"></div></div></div><?php endforeach; ?></div><?php endif; ?></article><article class="tile"><span>&#913;&#957;&#940; &#960;&#949;&#961;&#943;&#959;&#948;&#959;</span><strong><?php echo h(search_text(($specialties[array_search($statsSpecialtyId, array_column($specialties, 'id'))]['title'] ?? null), '-')); ?></strong><?php if ($periodRows === []): ?><p class="empty">&#916;&#949;&#957; &#965;&#960;&#940;&#961;&#967;&#959;&#965;&#957; &#945;&#954;&#972;&#956;&#951; &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962;.</p><?php else: ?><div class="bar-list"><?php foreach ($periodRows as $row): ?><?php $count = (int) $row['candidate_count']; $width = $maxPeriodCount > 0 ? max(12, (int) round(($count / $maxPeriodCount) * 100)) : 12; $semesterLabel = $row['semester_code'] === 'A' ? '&#913;&#39;' : '&#914;&#39;'; ?><div class="bar-row"><div class="bar-meta"><span><?php echo h((string) $row['period_year']); ?> / <?php echo $semesterLabel; ?></span><strong><?php echo $count; ?></strong></div><div class="bar-track"><div class="bar-fill" style="width: <?php echo $width; ?>%"></div></div></div><?php endforeach; ?></div><?php endif; ?></article></div></section><p class="footer-note">&#932;&#959; Search Module &#963;&#965;&#957;&#948;&#965;&#940;&#950;&#949;&#953; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;, &#966;&#943;&#955;&#964;&#961;&#945; &#954;&#945;&#953; &#963;&#964;&#945;&#964;&#953;&#963;&#964;&#953;&#954;&#940; &#963;&#949; &#949;&#957;&#953;&#945;&#943;&#945; &#949;&#956;&#960;&#949;&#953;&#961;&#943;&#945;.</p></main></div></body></html>

