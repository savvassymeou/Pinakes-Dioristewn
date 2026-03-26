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
                    Δημόσια αναζήτηση υποψηφίων ανά ονοματεπώνυμο και ειδικότητα, μαζί με βασικά στατιστικά
                    για να μπορεί ο επισκέπτης να βρει χρήσιμες πληροφορίες.
                </p>
                <p class="muted">
                    Οι πληροφορίες παρουσιάζονται με απλό κείμενο, πίνακες και γραφική απεικόνιση, ώστε ο χρήστης
                    να καταλαβαίνει εύκολα τη θέση, την ειδικότητα και τη γενική εικόνα των πινάκων.
                </p>
            </div>

            <div class="hero-badges">
                <div class="badge">
                    <span class="badge-label">Πρόσβαση</span>
                    <span class="badge-value">Δημόσια</span>
                </div>
                <div class="badge">
                    <span class="badge-label">Υποψήφιοι</span>
                    <span class="badge-value"><?php echo (int) $overview["total_candidates"]; ?></span>
                </div>
            </div>
        </section>

        <section class="grid" aria-label="Ενότητες Search">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
                <h2>Αναζήτηση</h2>
                <p>Βρες υποψηφίους από τη βάση ανά όνομα και ειδικότητα.</p>
                <div class="card-actions">
                    <a class="btn" href="#search">Άνοιγμα</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
                <h2>Εγγραφή</h2>
                <p>Δημιούργησε λογαριασμό για να συνδεθείς ως candidate και να παρακολουθείς πίνακες.</p>
                <div class="card-actions">
                    <a class="btn" href="../register.php">Μετάβαση</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
                <h2>Στατιστικά</h2>
                <p>Προβολή στατιστικών ανά ειδικότητα με συγκεντρωτική μορφή και απλό γράφημα.</p>
                <div class="card-actions">
                    <a class="btn" href="#stats">Άνοιγμα</a>
                </div>
            </article>
        </section>

        <section class="panel" id="search" aria-labelledby="searchPanelTitle">
            <div class="panel-head">
                <h2 id="searchPanelTitle">Αναζήτηση Υποψηφίων</h2>
                <p class="muted">Επίλεξε φίλτρα και δες πραγματικά αποτελέσματα από τη βάση.</p>
            </div>

            <form class="form-grid" method="get" action="#search">
                <div class="form-group">
                    <label for="name">Ονοματεπώνυμο</label>
                    <input id="name" name="name" type="text" value="<?php echo h($searchName); ?>" placeholder="π.χ. Παπαδόπουλος Γιώργος">
                </div>

                <div class="form-group">
                    <label for="specialty_id">Ειδικότητα</label>
                    <select id="specialty_id" name="specialty_id">
                        <option value="0">Όλες</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $searchSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="order">Ταξινόμηση</label>
                    <select id="order" name="order">
                        <option value="rank_asc" <?php echo $searchOrder === "rank_asc" ? "selected" : ""; ?>>Θέση πίνακα</option>
                        <option value="name_asc" <?php echo $searchOrder === "name_asc" ? "selected" : ""; ?>>Αλφαβητικά</option>
                        <option value="points_desc" <?php echo $searchOrder === "points_desc" ? "selected" : ""; ?>>Μονάδες φθίνουσα</option>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Search</button>
                    <a class="btn btn-ghost" href="<?php echo $selfPath; ?>#search">Καθαρισμός</a>
                </div>
            </form>

            <div class="table-wrap" role="region" aria-label="Αποτελέσματα αναζήτησης">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Θέση</th>
                            <th>Ονοματεπώνυμο</th>
                            <th>Ειδικότητα</th>
                            <th>Μονάδες</th>
                            <th>Κατάσταση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($searchResults === []): ?>
                            <tr>
                                <td colspan="5" class="empty-cell">Δεν βρέθηκαν αποτελέσματα για τα φίλτρα που έβαλες.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($searchResults as $row): ?>
                                <tr>
                                    <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "—"; ?></td>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["specialty_title"] ?? "—"); ?></td>
                                    <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "—"; ?></td>
                                    <td><span class="pill"><?php echo h($row["application_status"] ?? "—"); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="stats" aria-labelledby="statsTitle">
            <div class="panel-head">
                <h2 id="statsTitle">Στατιστικά Ανά Ειδικότητα</h2>
                <p class="muted">Συγκεντρωτικά στοιχεία για την ειδικότητα που επιλέγεις.</p>
            </div>

            <form class="form-grid" method="get" action="#stats">
                <div class="form-group">
                    <label for="stats_specialty_id">Ειδικότητα</label>
                    <select id="stats_specialty_id" name="stats_specialty_id">
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $statsSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Προβολή</button>
                </div>
            </form>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) ($specialtyOverview["candidate_count"] ?? 0); ?></div>
                    <div class="stat-label">Υποψήφιοι (ειδικότητα)</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $specialtyOverview["average_age"] !== null ? number_format((float) $specialtyOverview["average_age"], 1) : "—"; ?></div>
                    <div class="stat-label">Μ.Ο. ηλικίας</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $specialtyOverview["average_points"] !== null ? number_format((float) $specialtyOverview["average_points"], 2) : "—"; ?></div>
                    <div class="stat-label">Μ.Ο. μονάδων</div>
                </div>
            </div>

            <div class="chart-card">
                <h3><?php echo h($selectedSpecialtyStats["title"] ?? "Ειδικότητα"); ?> ανά έτος</h3>

                <?php if ($statsRows === []): ?>
                    <p class="muted empty-copy">Δεν υπάρχουν ακόμη δεδομένα για αυτή την ειδικότητα.</p>
                <?php else: ?>
                    <div class="chart-mock" aria-label="Γράφημα υποψηφίων ανά έτος">
                        <?php foreach ($statsRows as $row): ?>
                            <?php
                            $count = (int) $row["candidate_count"];
                            $width = $maxYearlyCount > 0 ? max(16, (int) round(($count / $maxYearlyCount) * 100)) : 16;
                            ?>
                            <div class="bar" style="width: <?php echo $width; ?>%">
                                <span><?php echo h((string) $row["report_year"]); ?> - <?php echo $count; ?> υποψήφιοι</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
                    <h3>Γενική εικόνα</h3>
                    <div class="year-list">
                        <div class="year-item"><span>Σύνολο υποψηφίων</span><strong><?php echo (int) $overview["total_candidates"]; ?></strong></div>
                        <div class="year-item"><span>Μ.Ο. ηλικίας</span><strong><?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : "—"; ?></strong></div>
                        <div class="year-item"><span>Νέοι το <?php echo date("Y"); ?></span><strong><?php echo (int) $overview["new_candidates_year"]; ?></strong></div>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Σύνδεσμοι συστήματος</h3>
                    <div class="year-list">
                        <div class="year-item"><span>Login</span><strong><a href="../login.php">Άνοιγμα</a></strong></div>
                        <div class="year-item"><span>Register</span><strong><a href="../register.php">Άνοιγμα</a></strong></div>
                        <div class="year-item"><span>API</span><strong><a href="../API/api.php">Άνοιγμα</a></strong></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php require __DIR__ . "/../includes/footer.php"; ?>


