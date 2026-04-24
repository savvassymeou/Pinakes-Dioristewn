<?php

session_start();

require_once __DIR__ . "/../../includes/auth.php";
require_role("admin", "../../auth/login.php", "../../modules/admin/admindashboard.php", "../../modules/candidate/candidatedashboard.php");

require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";

$keyword = trim($_GET["keyword"] ?? "");
$specialtyId = (int) ($_GET["specialty_id"] ?? 0);
$results = [];
$totalResults = 0;
$selectedSpecialtyLabel = u('\u038c\u03bb\u03b5\u03c2');

$specialties = [];
$specialtiesStmt = $conn->prepare("SELECT id, title FROM specialties ORDER BY title ASC");

if ($specialtiesStmt) {
    $specialtiesStmt->execute();
    $specialtiesResult = $specialtiesStmt->get_result();

    if ($specialtiesResult) {
        while ($row = $specialtiesResult->fetch_assoc()) {
            $specialties[] = $row;
        }
    }

    $specialtiesStmt->close();
}

foreach ($specialties as $specialty) {
    if ((int) $specialty["id"] === $specialtyId) {
        $selectedSpecialtyLabel = $specialty["title"];
        break;
    }
}

$searchTerm = "%" . $keyword . "%";
$stmt = $conn->prepare(
    "SELECT
        up.first_name,
        up.last_name,
        u.email,
        up.phone,
        s.title AS specialty_title,
        cp.application_status,
        cp.ranking_position,
        cp.points
     FROM candidate_profiles cp
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN user_profiles up ON up.user_id = u.id
     LEFT JOIN specialties s ON s.id = cp.specialty_id
     WHERE (? = '' OR (
            CONCAT(up.first_name, ' ', up.last_name) LIKE ?
            OR u.email LIKE ?
            OR COALESCE(up.phone, '') LIKE ?
            OR COALESCE(s.title, '') LIKE ?
            OR COALESCE(cp.application_status, '') LIKE ?
        ))
       AND (? = 0 OR cp.specialty_id = ?)
     ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, up.last_name ASC, up.first_name ASC
     LIMIT 50"
);

if ($stmt) {
    $stmt->bind_param(
        "ssssssii",
        $keyword,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $specialtyId,
        $specialtyId
    );

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }

    $stmt->close();
}

$totalResults = count($results);
$hasFilters = $keyword !== "" || $specialtyId > 0;
$pageTitle = APP_NAME . " | " . u('\u039b\u03af\u03c3\u03c4\u03b1 \u03a5\u03c0\u03bf\u03c8\u03b7\u03c6\u03af\u03c9\u03bd');
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --border: rgba(21, 55, 92, 0.10);
            --text: #14263d;
            --muted: #617792;
            --accent: #b8862f;
            --accent-soft: rgba(184, 134, 47, 0.12);
            --shadow: 0 6px 18px rgba(17, 39, 68, 0.06);
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background: var(--bg);
        }

        a { color: inherit; text-decoration: none; }
        h1, h2, h3 { font-family: "Space Grotesk", "Manrope", sans-serif; }

        .admin-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
        }

        .admin-sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 22px 14px;
            background: #fff;
            border-right: 1px solid var(--border);
        }

        .admin-sidebar-card {
            padding: 0 4px 8px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #8a611f;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .admin-sidebar-title {
            margin: 12px 0 8px;
            font-size: 1.25rem;
            line-height: 1.05;
        }

        .admin-sidebar-copy,
        .admin-sidebar-subtle,
        .admin-sidebar-label {
            color: var(--muted);
        }

        .admin-sidebar-nav {
            display: grid;
            gap: 8px;
            padding: 8px 0;
        }

        .admin-side-link {
            display: flex;
            align-items: center;
            min-height: 40px;
            padding: 10px 12px;
            border-radius: 12px;
            font-weight: 800;
            color: #5b6d84;
        }

        .admin-side-link.is-active {
            background: #f2f4f8;
            color: var(--text);
        }

        .admin-identity {
            padding: 0 4px 8px;
            line-height: 1.55;
        }

        .admin-identity strong {
            font-size: 0.98rem;
        }

        .admin-back-link,
        .admin-logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(21, 55, 92, 0.14);
            background: #fff;
            color: #10243f;
            font-weight: 800;
            box-shadow: 0 8px 22px rgba(17, 39, 68, 0.08);
        }

        .admin-content {
            min-width: 0;
            padding: 24px 28px 36px;
        }

        .page-hero {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 6px 0 12px;
            margin-bottom: 6px;
        }

        .page-hero h1 {
            margin: 12px 0 10px;
            font-size: clamp(2.2rem, 4vw, 3rem);
            line-height: 1;
        }

        .intro {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            max-width: 62ch;
        }

        .hero-badges {
            display: grid;
            gap: 12px;
            min-width: 260px;
        }

        .badge,
        .stat,
        .panel,
        .filters,
        .results {
            background: var(--surface);
            border: 1px solid rgba(21, 55, 92, 0.08);
            box-shadow: var(--shadow);
        }

        .badge,
        .stat {
            border-radius: 20px;
            padding: 16px 18px;
        }

        .badge-label,
        .stat-label {
            display: block;
            color: var(--muted);
            font-size: 0.84rem;
            margin-bottom: 6px;
        }

        .badge-value,
        .stat-kpi {
            display: block;
            font-size: 0.98rem;
            font-weight: 800;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .panel {
            border-radius: 26px;
            padding: 24px;
        }

        .filters,
        .results {
            margin-top: 18px;
            padding: 22px;
            border-radius: 24px;
        }

        .section-head h2 {
            margin: 0 0 8px;
            font-size: 1.35rem;
        }

        .section-head p {
            margin: 0 0 18px;
            color: var(--muted);
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr auto;
            gap: 16px;
            align-items: end;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 800;
        }

        input,
        select {
            width: 100%;
            padding: 13px 15px;
            border-radius: 16px;
            border: 1px solid #cfdae8;
            background: #f7f9fc;
            color: var(--text);
            font: inherit;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 138px;
            min-height: 46px;
            padding: 0 16px;
            border-radius: 16px;
            font-weight: 800;
            border: 1px solid rgba(21, 55, 92, 0.10);
            background: #fff;
            color: var(--text);
        }

        .btn-primary {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, #c59335, #e0ad49);
            box-shadow: 0 16px 28px rgba(184, 134, 47, 0.20);
        }

        .table-wrap {
            overflow: auto;
            border-radius: 20px;
            border: 1px solid rgba(21, 55, 92, 0.08);
            background: #fff;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(21, 55, 92, 0.08);
        }

        th {
            background: #f4f7fb;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .candidate-name {
            font-weight: 800;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(184, 134, 47, 0.12);
            color: #8a611f;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .empty-state {
            padding: 28px 18px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 980px) {
            .admin-shell {
                grid-template-columns: 1fr;
            }

            .admin-sidebar {
                position: static;
                height: auto;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .page-hero,
            .form-grid,
            .stats {
                grid-template-columns: 1fr;
            }

            .page-hero {
                flex-direction: column;
            }

            .hero-badges {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="admin-shell">
        <aside class="admin-sidebar" aria-label="Admin panel">
            <div class="admin-sidebar-card">
                <span class="eyebrow">Ichnos Admin</span>
                <h2 class="admin-sidebar-title">&#916;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951;</h2>
</div>

            <nav class="admin-sidebar-nav">
                <a class="admin-side-link" href="admindashboard.php#overview">&#917;&#960;&#953;&#963;&#954;&#972;&#960;&#951;&#963;&#951;</a>
                <a class="admin-side-link" href="admindashboard.php#manage-users">&#935;&#961;&#942;&#963;&#964;&#949;&#962;</a>
                <a class="admin-side-link" href="admindashboard.php#manage-lists">&#923;&#943;&#963;&#964;&#949;&#962;</a>
                <a class="admin-side-link is-active" href="list.php">&#923;&#943;&#963;&#964;&#945; &#933;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;</a>
                <a class="admin-side-link" href="admindashboard.php#reports">Reports</a>
                <a class="admin-side-link" href="admindashboard.php#account">&#923;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972;&#962;</a>
            </nav>

            <div class="admin-identity">
                <span class="admin-sidebar-label">Admin</span><br>
                <strong><?php echo h(current_user_full_name()); ?></strong><br>
                <span class="admin-sidebar-subtle"><?php echo h($_SESSION['email'] ?? ''); ?></span>
            </div>

            <a class="admin-back-link" href="../../index.php">&#8592; Back to Website</a>
            <a class="admin-logout-link" href="../../auth/logout.php">&#913;&#960;&#959;&#963;&#973;&#957;&#948;&#949;&#963;&#951;</a>
        </aside>

        <div class="admin-content">
            <section class="page-hero" aria-labelledby="pageTitle">
                <div>
                    <span class="eyebrow">Admin List</span>
                    <h1 id="pageTitle">&#923;&#943;&#963;&#964;&#945; &#933;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;</h1>
                    <p class="intro">
                        &#928;&#945;&#961;&#945;&#954;&#959;&#955;&#959;&#973;&#952;&#951;&#963;&#949; &#964;&#951; &#955;&#943;&#963;&#964;&#945; &#948;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951;&#962; &#947;&#953;&#945; &#964;&#959;&#957; &#961;&#972;&#955;&#959; admin, &#956;&#949; keyword search &#963;&#949; &#959;&#957;&#959;&#956;&#945;&#964;&#949;&#960;&#974;&#957;&#965;&#956;&#959;, email, &#964;&#951;&#955;&#941;&#966;&#969;&#957;&#959;, &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945; &#954;&#945;&#953; &#954;&#945;&#964;&#940;&#963;&#964;&#945;&#963;&#951; &#945;&#943;&#964;&#951;&#963;&#951;&#962;.
                    </p>
                </div>

                <div class="hero-badges">
                    <div class="badge">
                        <span class="badge-label">&#928;&#961;&#972;&#963;&#946;&#945;&#963;&#951;</span>
                        <span class="badge-value">&#924;&#972;&#957;&#959; &#916;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951;</span>
                    </div>
                    <div class="badge">
                        <span class="badge-label">&#913;&#960;&#959;&#964;&#949;&#955;&#941;&#963;&#956;&#945;&#964;&#945;</span>
                        <span class="badge-value"><?php echo $totalResults; ?></span>
                    </div>
                </div>
            </section>

            <section class="stats" aria-label="&#931;&#973;&#957;&#959;&#968;&#951; &#955;&#943;&#963;&#964;&#945;&#962;">
                <div class="stat">
                    <span class="stat-label">&#917;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#960;&#959;&#965; &#949;&#956;&#966;&#945;&#957;&#943;&#950;&#959;&#957;&#964;&#945;&#953;</span>
                    <span class="stat-kpi"><?php echo $totalResults; ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label">&#932;&#961;&#941;&#967;&#959;&#965;&#963;&#945; &#955;&#941;&#958;&#951;-&#954;&#955;&#949;&#953;&#948;&#943;</span>
                    <span class="stat-kpi"><?php echo $keyword !== "" ? h($keyword) : "-"; ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label">&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</span>
                    <span class="stat-kpi"><?php echo h($selectedSpecialtyLabel); ?></span>
                </div>
            </section>

            <section class="filters" aria-labelledby="filterTitle">
                <div class="section-head">
                    <h2 id="filterTitle">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951; &#963;&#964;&#951; &#955;&#943;&#963;&#964;&#945;</h2>
                    <p>&#935;&#961;&#951;&#963;&#953;&#956;&#959;&#960;&#959;&#943;&#951;&#963;&#949; &#966;&#943;&#955;&#964;&#961;&#945; &#947;&#953;&#945; &#957;&#945; &#949;&#957;&#964;&#959;&#960;&#943;&#963;&#949;&#953;&#962; &#947;&#961;&#942;&#947;&#959;&#961;&#945; &#963;&#965;&#947;&#954;&#949;&#954;&#961;&#953;&#956;&#941;&#957;&#959;&#965;&#962; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#959;&#965;&#962;.</p>
                </div>

                <form class="form-grid" method="get" action="list.php">
                    <div>
                        <label for="keyword">&#923;&#941;&#958;&#951;-&#954;&#955;&#949;&#953;&#948;&#943;</label>
                        <input id="keyword" name="keyword" type="text" value="<?php echo h($keyword); ?>" placeholder="&#960;.&#967;. &#928;&#945;&#960;&#945;&#948;&#959;&#960;&#959;&#973;&#955;&#959;&#965;, email, &#964;&#951;&#955;&#941;&#966;&#969;&#957;&#959;">
                    </div>

                    <div>
                        <label for="specialty_id">&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</label>
                        <select id="specialty_id" name="specialty_id">
                            <option value="0">&#908;&#955;&#949;&#962; &#959;&#953; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#949;&#962;</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo (int) $specialty['id']; ?>" <?php echo $specialtyId === (int) $specialty['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($specialty['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="actions">
                        <button class="btn btn-primary" type="submit">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</button>
                        <a class="btn" href="list.php">&#922;&#945;&#952;&#945;&#961;&#953;&#963;&#956;&#972;&#962;</a>
                    </div>
                </form>
            </section>

            <section class="results" aria-labelledby="resultsTitle">
                <div class="section-head">
                    <h2 id="resultsTitle">&#913;&#960;&#959;&#964;&#949;&#955;&#941;&#963;&#956;&#945;&#964;&#945; &#955;&#943;&#963;&#964;&#945;&#962;</h2>
                    <p>
                        <?php if ($hasFilters): ?>
                            &#917;&#956;&#966;&#945;&#957;&#943;&#950;&#959;&#957;&#964;&#945;&#953; &#959;&#953; &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#960;&#959;&#965; &#964;&#945;&#953;&#961;&#953;&#940;&#950;&#959;&#965;&#957; &#963;&#964;&#945; &#966;&#943;&#955;&#964;&#961;&#945; &#960;&#959;&#965; &#949;&#960;&#941;&#955;&#949;&#958;&#949;&#962;.
                        <?php else: ?>
                            &#917;&#956;&#966;&#945;&#957;&#943;&#950;&#959;&#957;&#964;&#945;&#953; &#941;&#969;&#962; 50 &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#945;&#960;&#972; &#964;&#951; &#955;&#943;&#963;&#964;&#945; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="table-wrap" role="region" aria-label="&#923;&#943;&#963;&#964;&#945; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;">
                    <table>
                        <thead>
                            <tr>
                                <th>&#933;&#960;&#959;&#968;&#942;&#966;&#953;&#959;&#962;</th>
                                <th>Email</th>
                                <th>&#932;&#951;&#955;&#941;&#966;&#969;&#957;&#959;</th>
                                <th>&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</th>
                                <th>&#922;&#945;&#964;&#940;&#963;&#964;&#945;&#963;&#951;</th>
                                <th>&#920;&#941;&#963;&#951;</th>
                                <th>&#924;&#959;&#957;&#940;&#948;&#949;&#962;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($results === []): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">&#916;&#949;&#957; &#946;&#961;&#941;&#952;&#951;&#954;&#945;&#957; &#945;&#960;&#959;&#964;&#949;&#955;&#941;&#963;&#956;&#945;&#964;&#945; &#947;&#953;&#945; &#964;&#945; &#963;&#965;&#947;&#954;&#949;&#954;&#961;&#953;&#956;&#941;&#957;&#945; &#954;&#961;&#953;&#964;&#942;&#961;&#953;&#945;.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results as $row): ?>
                                    <tr>
                                        <td><div class="candidate-name"><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></div></td>
                                        <td><?php echo h($row['email']); ?></td>
                                        <td><?php echo h($row['phone'] ?? '-'); ?></td>
                                        <td><?php echo h($row['specialty_title'] ?? '-'); ?></td>
                                        <td><span class="pill"><?php echo h($row['application_status'] ?? '-'); ?></span></td>
                                        <td><?php echo $row['ranking_position'] !== null ? (int) $row['ranking_position'] : '-'; ?></td>
                                        <td><?php echo $row['points'] !== null ? number_format((float) $row['points'], 2) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>
</html>


