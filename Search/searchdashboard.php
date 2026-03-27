<?php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

$specialties = [];
$specialtiesResult = $conn->query("SELECT id, title, description FROM specialties ORDER BY title ASC");

if ($specialtiesResult instanceof mysqli_result) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

$searchName = trim($_GET["name"] ?? "");
$searchSpecialtyId = (int) ($_GET["specialty_id"] ?? 0);
$searchOrder = $_GET["order"] ?? "rank_asc";
$searchResults = [];

$searchSql = "
    SELECT
        u.first_name,
        u.last_name,
        s.title AS specialty_title,
        cp.application_status,
        cp.ranking_position,
        cp.points
    FROM candidate_profiles cp
    INNER JOIN users u ON u.id = cp.user_id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    WHERE 1=1
";

$searchTypes = "";
$searchParams = [];

if ($searchName !== "") {
    $searchSql .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    $searchTypes .= "s";
    $searchParams[] = "%" . $searchName . "%";
}

if ($searchSpecialtyId > 0) {
    $searchSql .= " AND cp.specialty_id = ?";
    $searchTypes .= "i";
    $searchParams[] = $searchSpecialtyId;
}

if ($searchOrder === "name_asc") {
    $searchSql .= " ORDER BY u.last_name ASC, u.first_name ASC";
} elseif ($searchOrder === "points_desc") {
    $searchSql .= " ORDER BY cp.points IS NULL, cp.points DESC, u.last_name ASC";
} else {
    $searchSql .= " ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, u.last_name ASC, u.first_name ASC";
}

$searchSql .= " LIMIT 25";
$searchStmt = $conn->prepare($searchSql);

if ($searchStmt) {
    if ($searchParams !== []) {
        $searchStmt->bind_param($searchTypes, ...$searchParams);
    }

    $searchStmt->execute();
    $searchResult = $searchStmt->get_result();

    if ($searchResult instanceof mysqli_result) {
        while ($row = $searchResult->fetch_assoc()) {
            $searchResults[] = $row;
        }
    }

    $searchStmt->close();
}

$overview = [
    "total_candidates" => 0,
    "average_age" => null,
    "new_candidates_year" => 0,
];

$overviewResult = $conn->query("
    SELECT
        COUNT(*) AS total_candidates,
        AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) AS average_age
    FROM candidate_profiles
");

if ($overviewResult instanceof mysqli_result) {
    $overviewRow = $overviewResult->fetch_assoc();

    if ($overviewRow) {
        $overview["total_candidates"] = (int) ($overviewRow["total_candidates"] ?? 0);
        $overview["average_age"] = $overviewRow["average_age"];
    }
}

$newCandidatesResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'candidate' AND YEAR(created_at) = YEAR(CURDATE())");

if ($newCandidatesResult instanceof mysqli_result) {
    $newCandidatesRow = $newCandidatesResult->fetch_assoc();
    $overview["new_candidates_year"] = (int) ($newCandidatesRow["total"] ?? 0);
}

$statsSpecialtyId = (int) ($_GET["stats_specialty_id"] ?? ($specialties[0]["id"] ?? 0));
$statsRows = [];
$selectedSpecialtyStats = null;
$yearlyStats = [];
$maxYearlyCount = 0;

foreach ($specialties as $specialty) {
    if ((int) $specialty["id"] === $statsSpecialtyId) {
        $selectedSpecialtyStats = $specialty;
        break;
    }
}

if ($statsSpecialtyId > 0) {
    $statsStmt = $conn->prepare("
        SELECT
            YEAR(cp.created_at) AS report_year,
            COUNT(*) AS candidate_count,
            AVG(cp.points) AS average_points,
            AVG(TIMESTAMPDIFF(YEAR, cp.birth_date, CURDATE())) AS average_age
        FROM candidate_profiles cp
        WHERE cp.specialty_id = ?
        GROUP BY YEAR(cp.created_at)
        ORDER BY report_year ASC
    ");

    if ($statsStmt) {
        $statsStmt->bind_param("i", $statsSpecialtyId);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();

        if ($statsResult instanceof mysqli_result) {
            while ($row = $statsResult->fetch_assoc()) {
                $statsRows[] = $row;
                $maxYearlyCount = max($maxYearlyCount, (int) $row["candidate_count"]);
            }
        }

        $statsStmt->close();
    }
}

$specialtyOverview = [
    "candidate_count" => 0,
    "average_age" => null,
    "average_points" => null,
];

if ($statsSpecialtyId > 0) {
    $specialtyOverviewStmt = $conn->prepare("
        SELECT
            COUNT(*) AS candidate_count,
            AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) AS average_age,
            AVG(points) AS average_points
        FROM candidate_profiles
        WHERE specialty_id = ?
    ");

    if ($specialtyOverviewStmt) {
        $specialtyOverviewStmt->bind_param("i", $statsSpecialtyId);
        $specialtyOverviewStmt->execute();
        $specialtyOverviewResult = $specialtyOverviewStmt->get_result();
        $specialtyOverviewRow = $specialtyOverviewResult ? $specialtyOverviewResult->fetch_assoc() : null;

        if ($specialtyOverviewRow) {
            $specialtyOverview = $specialtyOverviewRow;
        }

        $specialtyOverviewStmt->close();
    }
}
$pageTitle = APP_NAME . " | Search Module";
$bodyClass = "theme-search";
$currentPage = "search";
$navBase = "../";

require __DIR__ . "/../includes/header.php";

?>
    <main class="container">
        <section class="page-hero" aria-labelledby="searchTitle">
            <div class="hero-text">
                <h1 id="searchTitle">Search Module</h1>
                <p class="muted">
                    Î”Î·Î¼ÏŒÏƒÎ¹Î± Î±Î½Î±Î¶Î®Ï„Î·ÏƒÎ· Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½ Î±Î½Î¬ Î¿Î½Î¿Î¼Î±Ï„ÎµÏ€ÏŽÎ½Ï…Î¼Î¿ ÎºÎ±Î¹ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±, Î¼Î±Î¶Î¯ Î¼Îµ Î²Î±ÏƒÎ¹ÎºÎ¬ ÏƒÏ„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÎ¬
                    Î³Î¹Î± Î½Î± Î¼Ï€Î¿ÏÎµÎ¯ Î¿ ÎµÏ€Î¹ÏƒÎºÎ­Ï€Ï„Î·Ï‚ Î½Î± Î²ÏÎµÎ¹ Ï‡ÏÎ®ÏƒÎ¹Î¼ÎµÏ‚ Ï€Î»Î·ÏÎ¿Ï†Î¿ÏÎ¯ÎµÏ‚.
                </p>
                <p class="muted">
                    ÎŸÎ¹ Ï€Î»Î·ÏÎ¿Ï†Î¿ÏÎ¯ÎµÏ‚ Ï€Î±ÏÎ¿Ï…ÏƒÎ¹Î¬Î¶Î¿Î½Ï„Î±Î¹ Î¼Îµ Î±Ï€Î»ÏŒ ÎºÎµÎ¯Î¼ÎµÎ½Î¿, Ï€Î¯Î½Î±ÎºÎµÏ‚ ÎºÎ±Î¹ Î³ÏÎ±Ï†Î¹ÎºÎ® Î±Ï€ÎµÎ¹ÎºÏŒÎ½Î¹ÏƒÎ·, ÏŽÏƒÏ„Îµ Î¿ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚
                    Î½Î± ÎºÎ±Ï„Î±Î»Î±Î²Î±Î¯Î½ÎµÎ¹ ÎµÏÎºÎ¿Î»Î± Ï„Î· Î¸Î­ÏƒÎ·, Ï„Î·Î½ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± ÎºÎ±Î¹ Ï„Î· Î³ÎµÎ½Î¹ÎºÎ® ÎµÎ¹ÎºÏŒÎ½Î± Ï„Ï‰Î½ Ï€Î¹Î½Î¬ÎºÏ‰Î½.
                </p>
            </div>

            <div class="hero-badges">
                <div class="badge">
                    <span class="badge-label">Î ÏÏŒÏƒÎ²Î±ÏƒÎ·</span>
                    <span class="badge-value">Î”Î·Î¼ÏŒÏƒÎ¹Î±</span>
                </div>
                <div class="badge">
                    <span class="badge-label">Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹</span>
                    <span class="badge-value"><?php echo (int) $overview["total_candidates"]; ?></span>
                </div>
            </div>
        </section>

        <section class="grid" aria-label="Î•Î½ÏŒÏ„Î·Ï„ÎµÏ‚ Search">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
                <h2>Î‘Î½Î±Î¶Î®Ï„Î·ÏƒÎ·</h2>
                <p>Î’ÏÎµÏ‚ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï…Ï‚ Î±Ï€ÏŒ Ï„Î· Î²Î¬ÏƒÎ· Î±Î½Î¬ ÏŒÎ½Î¿Î¼Î± ÎºÎ±Î¹ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±.</p>
                <div class="card-actions">
                    <a class="btn" href="#search">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
                <h2>Î•Î³Î³ÏÎ±Ï†Î®</h2>
                <p>Î”Î·Î¼Î¹Î¿ÏÏÎ³Î·ÏƒÎµ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒ Î³Î¹Î± Î½Î± ÏƒÏ…Î½Î´ÎµÎ¸ÎµÎ¯Ï‚ Ï‰Ï‚ candidate ÎºÎ±Î¹ Î½Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿Ï…Î¸ÎµÎ¯Ï‚ Ï€Î¯Î½Î±ÎºÎµÏ‚.</p>
                <div class="card-actions">
                    <a class="btn" href="../auth/register.php">ÎœÎµÏ„Î¬Î²Î±ÏƒÎ·</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
                <h2>Î£Ï„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÎ¬</h2>
                <p>Î ÏÎ¿Î²Î¿Î»Î® ÏƒÏ„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÏŽÎ½ Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± Î¼Îµ ÏƒÏ…Î³ÎºÎµÎ½Ï„ÏÏ‰Ï„Î¹ÎºÎ® Î¼Î¿ÏÏ†Î® ÎºÎ±Î¹ Î±Ï€Î»ÏŒ Î³ÏÎ¬Ï†Î·Î¼Î±.</p>
                <div class="card-actions">
                    <a class="btn" href="#stats">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>
        </section>

        <section class="panel" id="search" aria-labelledby="searchPanelTitle">
            <div class="panel-head">
                <h2 id="searchPanelTitle">Î‘Î½Î±Î¶Î®Ï„Î·ÏƒÎ· Î¥Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½</h2>
                <p class="muted">Î•Ï€Î¯Î»ÎµÎ¾Îµ Ï†Î¯Î»Ï„ÏÎ± ÎºÎ±Î¹ Î´ÎµÏ‚ Ï€ÏÎ±Î³Î¼Î±Ï„Î¹ÎºÎ¬ Î±Ï€Î¿Ï„ÎµÎ»Î­ÏƒÎ¼Î±Ï„Î± Î±Ï€ÏŒ Ï„Î· Î²Î¬ÏƒÎ·.</p>
            </div>

            <form class="form-grid" method="get" action="#search">
                <div class="form-group">
                    <label for="name">ÎŸÎ½Î¿Î¼Î±Ï„ÎµÏ€ÏŽÎ½Ï…Î¼Î¿</label>
                    <input id="name" name="name" type="text" value="<?php echo h($searchName); ?>" placeholder="Ï€.Ï‡. Î Î±Ï€Î±Î´ÏŒÏ€Î¿Ï…Î»Î¿Ï‚ Î“Î¹ÏŽÏÎ³Î¿Ï‚">
                </div>

                <div class="form-group">
                    <label for="specialty_id">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</label>
                    <select id="specialty_id" name="specialty_id">
                        <option value="0">ÎŒÎ»ÎµÏ‚</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $searchSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="order">Î¤Î±Î¾Î¹Î½ÏŒÎ¼Î·ÏƒÎ·</label>
                    <select id="order" name="order">
                        <option value="rank_asc" <?php echo $searchOrder === "rank_asc" ? "selected" : ""; ?>>Î˜Î­ÏƒÎ· Ï€Î¯Î½Î±ÎºÎ±</option>
                        <option value="name_asc" <?php echo $searchOrder === "name_asc" ? "selected" : ""; ?>>Î‘Î»Ï†Î±Î²Î·Ï„Î¹ÎºÎ¬</option>
                        <option value="points_desc" <?php echo $searchOrder === "points_desc" ? "selected" : ""; ?>>ÎœÎ¿Î½Î¬Î´ÎµÏ‚ Ï†Î¸Î¯Î½Î¿Ï…ÏƒÎ±</option>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Search</button>
                    <a class="btn btn-ghost" href="<?php echo $selfPath; ?>#search">ÎšÎ±Î¸Î±ÏÎ¹ÏƒÎ¼ÏŒÏ‚</a>
                </div>
            </form>

            <div class="table-wrap" role="region" aria-label="Î‘Ï€Î¿Ï„ÎµÎ»Î­ÏƒÎ¼Î±Ï„Î± Î±Î½Î±Î¶Î®Ï„Î·ÏƒÎ·Ï‚">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Î˜Î­ÏƒÎ·</th>
                            <th>ÎŸÎ½Î¿Î¼Î±Ï„ÎµÏ€ÏŽÎ½Ï…Î¼Î¿</th>
                            <th>Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</th>
                            <th>ÎœÎ¿Î½Î¬Î´ÎµÏ‚</th>
                            <th>ÎšÎ±Ï„Î¬ÏƒÏ„Î±ÏƒÎ·</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($searchResults === []): ?>
                            <tr>
                                <td colspan="5" class="empty-cell">Î”ÎµÎ½ Î²ÏÎ­Î¸Î·ÎºÎ±Î½ Î±Ï€Î¿Ï„ÎµÎ»Î­ÏƒÎ¼Î±Ï„Î± Î³Î¹Î± Ï„Î± Ï†Î¯Î»Ï„ÏÎ± Ï€Î¿Ï… Î­Î²Î±Î»ÎµÏ‚.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($searchResults as $row): ?>
                                <tr>
                                    <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "â€”"; ?></td>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["specialty_title"] ?? "â€”"); ?></td>
                                    <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "â€”"; ?></td>
                                    <td><span class="pill"><?php echo h($row["application_status"] ?? "â€”"); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="stats" aria-labelledby="statsTitle">
            <div class="panel-head">
                <h2 id="statsTitle">Î£Ï„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÎ¬ Î‘Î½Î¬ Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</h2>
                <p class="muted">Î£Ï…Î³ÎºÎµÎ½Ï„ÏÏ‰Ï„Î¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î± Î³Î¹Î± Ï„Î·Î½ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± Ï€Î¿Ï… ÎµÏ€Î¹Î»Î­Î³ÎµÎ¹Ï‚.</p>
            </div>

            <form class="form-grid" method="get" action="#stats">
                <div class="form-group">
                    <label for="stats_specialty_id">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</label>
                    <select id="stats_specialty_id" name="stats_specialty_id">
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $statsSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Î ÏÎ¿Î²Î¿Î»Î®</button>
                </div>
            </form>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) ($specialtyOverview["candidate_count"] ?? 0); ?></div>
                    <div class="stat-label">Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ (ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±)</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $specialtyOverview["average_age"] !== null ? number_format((float) $specialtyOverview["average_age"], 1) : "â€”"; ?></div>
                    <div class="stat-label">Îœ.ÎŸ. Î·Î»Î¹ÎºÎ¯Î±Ï‚</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $specialtyOverview["average_points"] !== null ? number_format((float) $specialtyOverview["average_points"], 2) : "â€”"; ?></div>
                    <div class="stat-label">Îœ.ÎŸ. Î¼Î¿Î½Î¬Î´Ï‰Î½</div>
                </div>
            </div>

            <div class="chart-card">
                <h3><?php echo h($selectedSpecialtyStats["title"] ?? "Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±"); ?> Î±Î½Î¬ Î­Ï„Î¿Ï‚</h3>

                <?php if ($statsRows === []): ?>
                    <p class="muted empty-copy">Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡Î¿Ï…Î½ Î±ÎºÏŒÎ¼Î· Î´ÎµÎ´Î¿Î¼Î­Î½Î± Î³Î¹Î± Î±Ï…Ï„Î® Ï„Î·Î½ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±.</p>
                <?php else: ?>
                    <div class="chart-mock" aria-label="Î“ÏÎ¬Ï†Î·Î¼Î± Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½ Î±Î½Î¬ Î­Ï„Î¿Ï‚">
                        <?php foreach ($statsRows as $row): ?>
                            <?php
                            $count = (int) $row["candidate_count"];
                            $width = $maxYearlyCount > 0 ? max(16, (int) round(($count / $maxYearlyCount) * 100)) : 16;
                            ?>
                            <div class="bar" style="width: <?php echo $width; ?>%">
                                <span><?php echo h((string) $row["report_year"]); ?> - <?php echo $count; ?> Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
                    <h3>Î“ÎµÎ½Î¹ÎºÎ® ÎµÎ¹ÎºÏŒÎ½Î±</h3>
                    <div class="year-list">
                        <div class="year-item"><span>Î£ÏÎ½Î¿Î»Î¿ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½</span><strong><?php echo (int) $overview["total_candidates"]; ?></strong></div>
                        <div class="year-item"><span>Îœ.ÎŸ. Î·Î»Î¹ÎºÎ¯Î±Ï‚</span><strong><?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : "â€”"; ?></strong></div>
                        <div class="year-item"><span>ÎÎ­Î¿Î¹ Ï„Î¿ <?php echo date("Y"); ?></span><strong><?php echo (int) $overview["new_candidates_year"]; ?></strong></div>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Î£ÏÎ½Î´ÎµÏƒÎ¼Î¿Î¹ ÏƒÏ…ÏƒÏ„Î®Î¼Î±Ï„Î¿Ï‚</h3>
                    <div class="year-list">
                        <div class="year-item"><span>Login</span><strong><a href="../auth/login.php">Î†Î½Î¿Î¹Î³Î¼Î±</a></strong></div>
                        <div class="year-item"><span>Register</span><strong><a href="../auth/register.php">Î†Î½Î¿Î¹Î³Î¼Î±</a></strong></div>
                        <div class="year-item"><span>API</span><strong><a href="../API/api.php">Î†Î½Î¿Î¹Î³Î¼Î±</a></strong></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php require __DIR__ . "/../includes/footer.php"; ?>




