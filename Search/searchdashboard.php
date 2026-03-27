<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

function search_text(?string $value, string $fallback = "—"): string
{
    if ($value === null) {
        return $fallback;
    }

    $text = trim($value);

    if ($text === "") {
        return $fallback;
    }

    if (preg_match('/[ÃÎÏ]/u', $text)) {
        foreach (["Windows-1252", "ISO-8859-7", "ISO-8859-1"] as $encoding) {
            $decoded = @iconv($encoding, "UTF-8//IGNORE", $text);
            if (is_string($decoded) && $decoded !== "" && !preg_match('/[ÃÎÏ]/u', $decoded)) {
                $text = $decoded;
                break;
            }
        }
    }

    return $text;
}

$specialties = [];
$specialtiesResult = $conn->query("SELECT id, title, description FROM specialties ORDER BY title ASC");
if ($specialtiesResult) {
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
        cp.points,
        cp.created_at
    FROM candidate_profiles cp
    INNER JOIN users u ON u.id = cp.user_id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    WHERE 1 = 1
";

$searchTypes = "";
$searchParams = [];

if ($searchName !== "") {
    $searchSql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR CONCAT(u.last_name, ' ', u.first_name) LIKE ?)";
    $searchTypes .= "ssss";
    $term = "%" . $searchName . "%";
    $searchParams[] = $term;
    $searchParams[] = $term;
    $searchParams[] = $term;
    $searchParams[] = $term;
}

if ($searchSpecialtyId > 0) {
    $searchSql .= " AND cp.specialty_id = ?";
    $searchTypes .= "i";
    $searchParams[] = $searchSpecialtyId;
}

switch ($searchOrder) {
    case "name_asc":
        $searchSql .= " ORDER BY u.last_name ASC, u.first_name ASC";
        break;
    case "points_desc":
        $searchSql .= " ORDER BY cp.points IS NULL, cp.points DESC, u.last_name ASC";
        break;
    case "recent_desc":
        $searchSql .= " ORDER BY cp.created_at DESC, u.last_name ASC";
        break;
    default:
        $searchSql .= " ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, u.last_name ASC, u.first_name ASC";
        break;
}

$searchSql .= " LIMIT 30";
$searchStmt = $conn->prepare($searchSql);
if ($searchStmt) {
    if ($searchParams !== []) {
        $searchStmt->bind_param($searchTypes, ...$searchParams);
    }
    $searchStmt->execute();
    $searchResult = $searchStmt->get_result();
    if ($searchResult) {
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
    "specialty_count" => count($specialties),
];

$overviewResult = $conn->query("SELECT COUNT(*) AS total_candidates, AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) AS average_age FROM candidate_profiles");
if ($overviewResult) {
    $overviewRow = $overviewResult->fetch_assoc();
    if ($overviewRow) {
        $overview["total_candidates"] = (int) ($overviewRow["total_candidates"] ?? 0);
        $overview["average_age"] = $overviewRow["average_age"];
    }
}

$newCandidatesResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'candidate' AND YEAR(created_at) = YEAR(CURDATE())");
if ($newCandidatesResult) {
    $newCandidatesRow = $newCandidatesResult->fetch_assoc();
    $overview["new_candidates_year"] = (int) ($newCandidatesRow["total"] ?? 0);
}

$statsSpecialtyId = (int) ($_GET["stats_specialty_id"] ?? ($specialties[0]["id"] ?? 0));
$selectedSpecialtyStats = null;
foreach ($specialties as $specialty) {
    if ((int) $specialty["id"] === $statsSpecialtyId) {
        $selectedSpecialtyStats = $specialty;
        break;
    }
}

$specialtyOverview = [
    "candidate_count" => 0,
    "average_age" => null,
    "average_points" => null,
];

$yearlyRows = [];
$periodRows = [];
$maxYearlyCount = 0;
$maxPeriodCount = 0;

if ($statsSpecialtyId > 0) {
    $specialtyOverviewStmt = $conn->prepare("SELECT COUNT(*) AS candidate_count, AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) AS average_age, AVG(points) AS average_points FROM candidate_profiles WHERE specialty_id = ?");
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

    $yearlyStmt = $conn->prepare("
        SELECT YEAR(cp.created_at) AS report_year, COUNT(*) AS candidate_count, AVG(cp.points) AS average_points
        FROM candidate_profiles cp
        WHERE cp.specialty_id = ?
        GROUP BY YEAR(cp.created_at)
        ORDER BY report_year ASC
    ");
    if ($yearlyStmt) {
        $yearlyStmt->bind_param("i", $statsSpecialtyId);
        $yearlyStmt->execute();
        $yearlyResult = $yearlyStmt->get_result();
        if ($yearlyResult) {
            while ($row = $yearlyResult->fetch_assoc()) {
                $yearlyRows[] = $row;
                $maxYearlyCount = max($maxYearlyCount, (int) $row["candidate_count"]);
            }
        }
        $yearlyStmt->close();
    }

    $periodStmt = $conn->prepare("
        SELECT
            YEAR(cp.created_at) AS period_year,
            CASE WHEN MONTH(cp.created_at) BETWEEN 1 AND 6 THEN 'Α' ELSE 'Β' END AS semester_label,
            COUNT(*) AS candidate_count,
            AVG(cp.points) AS average_points
        FROM candidate_profiles cp
        WHERE cp.specialty_id = ?
        GROUP BY YEAR(cp.created_at), CASE WHEN MONTH(cp.created_at) BETWEEN 1 AND 6 THEN 'Α' ELSE 'Β' END
        ORDER BY period_year ASC, semester_label ASC
    ");
    if ($periodStmt) {
        $periodStmt->bind_param("i", $statsSpecialtyId);
        $periodStmt->execute();
        $periodResult = $periodStmt->get_result();
        if ($periodResult) {
            while ($row = $periodResult->fetch_assoc()) {
                $periodRows[] = $row;
                $maxPeriodCount = max($maxPeriodCount, (int) $row["candidate_count"]);
            }
        }
        $periodStmt->close();
    }
}

$topSpecialties = [];
$topSpecialtiesResult = $conn->query("
    SELECT s.title, COUNT(cp.id) AS candidate_count
    FROM specialties s
    LEFT JOIN candidate_profiles cp ON cp.specialty_id = s.id
    GROUP BY s.id, s.title
    ORDER BY candidate_count DESC, s.title ASC
    LIMIT 6
");
if ($topSpecialtiesResult) {
    while ($row = $topSpecialtiesResult->fetch_assoc()) {
        $topSpecialties[] = $row;
    }
}

$pageTitle = APP_NAME . " | Search";
$bodyClass = "theme-search";
$currentPage = "search";
$navBase = "../";

require __DIR__ . "/../includes/header.php";
?>
<main class="container">
    <section class="page-hero" aria-labelledby="searchTitle">
        <div class="hero-text">
            <span class="eyebrow-home">Public Search Module</span>
            <h1 id="searchTitle">Αναζήτηση, Εγγραφή και Στατιστικά σε μία δημόσια ενότητα</h1>
            <p class="muted">Το Search module είναι η δημόσια είσοδος του ευρύτερου κοινού. Εδώ ο επισκέπτης μπορεί να αναζητήσει υποψηφίους, να δει συνοπτικά στατιστικά και να οδηγηθεί στην εγγραφή.</p>
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

    <section class="stats" aria-label="Συνοπτικά στοιχεία Search module">
        <div class="stat">
            <div class="stat-kpi"><?php echo (int) $overview["total_candidates"]; ?></div>
            <div class="stat-label">Συνολικοί υποψήφιοι</div>
        </div>
        <div class="stat">
            <div class="stat-kpi"><?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : '-'; ?></div>
            <div class="stat-label">Μέσος όρος ηλικίας</div>
        </div>
        <div class="stat">
            <div class="stat-kpi"><?php echo (int) $overview["new_candidates_year"]; ?></div>
            <div class="stat-label">Νέοι υποψήφιοι το <?php echo date("Y"); ?></div>
        </div>
    </section>

    <section class="grid grid-admin" aria-label="Βασικές λειτουργίες Search module">
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">1</div>
            <h2>Αναζήτηση</h2>
            <p>Αναζήτησε με ονοματεπώνυμο, φίλτρα ειδικότητας και ταξινόμηση για να εντοπίσεις πιο εύκολα τα αποτελέσματα.</p>
            <div class="card-actions"><a class="btn btn-secondary" href="#search">Μετάβαση</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">2</div>
            <h2>Εγγραφή</h2>
            <p>Ο επισκέπτης μπορεί να περάσει άμεσα στη φόρμα εγγραφής για να αποκτήσει πλήρη πρόσβαση στις ιδιωτικές λειτουργίες.</p>
            <div class="card-actions"><a class="btn btn-secondary" href="../auth/register.php">Εγγραφή</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">3</div>
            <h2>Στατιστικά</h2>
            <p>Δες συνολικά στοιχεία, ανάλυση ανά ειδικότητα, ανά έτος και ανά περίοδο με πιο κατανοητή παρουσίαση.</p>
            <div class="card-actions"><a class="btn btn-secondary" href="#stats">Προβολή</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">4</div>
            <h2>Σύνδεση</h2>
            <p>Όσοι έχουν ήδη λογαριασμό μπορούν να συνεχίσουν από εδώ στο προσωπικό ή διαχειριστικό dashboard τους.</p>
            <div class="card-actions"><a class="btn btn-secondary" href="../auth/login.php">Σύνδεση</a></div>
        </article>
    </section>

    <section class="panel" id="search" aria-labelledby="searchPanelTitle">
        <div class="panel-head">
            <h2 id="searchPanelTitle">Αναζήτηση Υποψηφίων</h2>
            <p class="muted">Η αναζήτηση υποστηρίζει όνομα, επώνυμο, πλήρες ονοματεπώνυμο, ειδικότητα και εναλλακτική ταξινόμηση.</p>
        </div>

        <form class="form-grid" method="get" action="searchdashboard.php#search">
            <div class="form-group">
                <label for="name">Ονοματεπώνυμο</label>
                <input id="name" name="name" type="text" value="<?php echo h($searchName); ?>" placeholder="π.χ. Μαρία Παπαδοπούλου">
            </div>
            <div class="form-group">
                <label for="specialty_id">Ειδικότητα</label>
                <select id="specialty_id" name="specialty_id">
                    <option value="0">Όλες οι ειδικότητες</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $searchSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                            <?php echo h(search_text($specialty["title"])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="order">Ταξινόμηση</label>
                <select id="order" name="order">
                    <option value="rank_asc" <?php echo $searchOrder === "rank_asc" ? "selected" : ""; ?>>Θέση πίνακα</option>
                    <option value="name_asc" <?php echo $searchOrder === "name_asc" ? "selected" : ""; ?>>Αλφαβητικά</option>
                    <option value="points_desc" <?php echo $searchOrder === "points_desc" ? "selected" : ""; ?>>Μόρια φθίνουσα</option>
                    <option value="recent_desc" <?php echo $searchOrder === "recent_desc" ? "selected" : ""; ?>>Πιο πρόσφατοι</option>
                </select>
            </div>
            <div class="form-group form-actions">
                <button class="btn btn-primary" type="submit">Αναζήτηση</button>
                <a class="btn btn-secondary" href="searchdashboard.php#search">Καθαρισμός</a>
            </div>
        </form>

        <div class="table-titlebar">
            <h3>Αποτελέσματα</h3>
            <p class="panel-subtitle"><?php echo count($searchResults); ?> εγγραφές</p>
        </div>
        <div class="table-wrap" role="region" aria-label="Αποτελέσματα αναζήτησης υποψηφίων">
            <table class="table">
                <thead>
                    <tr>
                        <th>Θέση</th>
                        <th>Ονοματεπώνυμο</th>
                        <th>Ειδικότητα</th>
                        <th>Μόρια</th>
                        <th>Κατάσταση</th>
                        <th>Έτος εγγραφής</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($searchResults === []): ?>
                        <tr><td colspan="6" class="empty-cell">Δεν βρέθηκαν αποτελέσματα για τα φίλτρα που επέλεξες.</td></tr>
                    <?php else: ?>
                        <?php foreach ($searchResults as $row): ?>
                            <tr>
                                <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : '-'; ?></td>
                                <td><?php echo h($row["first_name"] . ' ' . $row["last_name"]); ?></td>
                                <td><?php echo h(search_text($row["specialty_title"] ?? null)); ?></td>
                                <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : '-'; ?></td>
                                <td><span class="pill"><?php echo h(search_text($row["application_status"] ?? null)); ?></span></td>
                                <td><?php echo !empty($row["created_at"]) ? h(date("Y", strtotime($row["created_at"]))) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="split-panel" aria-label="Εγγραφή και κατανομή ειδικοτήτων">
        <div class="panel panel-nested">
            <div class="panel-head">
                <h3>Εγγραφή στο Σύστημα</h3>
                <p class="muted">Η εγγραφή είναι διαθέσιμη δημόσια, ώστε ο ενδιαφερόμενος να μπορεί να συσχετιστεί με υποψήφιο των πινάκων διοριστέων.</p>
            </div>
            <div class="info-list">
                <div class="info-row"><span>Νέος λογαριασμός</span><strong>Candidate access</strong></div>
                <div class="info-row"><span>Authentication</span><strong>Ασφαλές login / logout</strong></div>
                <div class="info-row"><span>Μετάβαση</span><strong><a href="../auth/register.php">Άνοιγμα φόρμας εγγραφής</a></strong></div>
            </div>
            <div class="card-actions"><a class="btn btn-primary" href="../auth/register.php">Μετάβαση στην Εγγραφή</a></div>
        </div>

        <div class="panel panel-nested">
            <div class="panel-head">
                <h3>Κατανομή ανά Ειδικότητα</h3>
                <p class="muted">Μια γρήγορη συγκεντρωτική εικόνα για το πού υπάρχουν περισσότεροι υποψήφιοι.</p>
            </div>
            <?php if ($topSpecialties === []): ?>
                <div class="empty-state">Δεν υπάρχουν ακόμη διαθέσιμα δεδομένα κατανομής ανά ειδικότητα.</div>
            <?php else: ?>
                <div class="chart-mock" aria-label="Κατανομή υποψηφίων ανά ειδικότητα">
                    <?php $topMax = max(array_map(static fn($row) => (int) $row['candidate_count'], $topSpecialties)); ?>
                    <?php foreach ($topSpecialties as $row): ?>
                        <?php $width = $topMax > 0 ? max(16, (int) round(((int) $row['candidate_count'] / $topMax) * 100)) : 16; ?>
                        <div class="bar" style="width: <?php echo $width; ?>%"><span><?php echo h(search_text($row['title'])); ?> - <?php echo (int) $row['candidate_count']; ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel" id="stats" aria-labelledby="statsTitle">
        <div class="panel-head">
            <h2 id="statsTitle">Στατιστικά Ανά Ειδικότητα</h2>
            <p class="muted">Η ενότητα εμφανίζει συγκεντρωτικά στοιχεία, ανάλυση ανά έτος και ανάλυση ανά περίοδο για την επιλεγμένη ειδικότητα.</p>
        </div>

        <form class="form-grid" method="get" action="searchdashboard.php#stats">
            <div class="form-group">
                <label for="stats_specialty_id">Ειδικότητα</label>
                <select id="stats_specialty_id" name="stats_specialty_id">
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $statsSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                            <?php echo h(search_text($specialty["title"])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-actions">
                <button class="btn btn-primary" type="submit">Προβολή Στατιστικών</button>
            </div>
        </form>

        <div class="stats">
            <div class="stat">
                <div class="stat-kpi"><?php echo (int) ($specialtyOverview["candidate_count"] ?? 0); ?></div>
                <div class="stat-label">Υποψήφιοι στην ειδικότητα</div>
            </div>
            <div class="stat">
                <div class="stat-kpi"><?php echo $specialtyOverview["average_age"] !== null ? number_format((float) $specialtyOverview["average_age"], 1) : '-'; ?></div>
                <div class="stat-label">Μέσος όρος ηλικίας</div>
            </div>
            <div class="stat">
                <div class="stat-kpi"><?php echo $specialtyOverview["average_points"] !== null ? number_format((float) $specialtyOverview["average_points"], 2) : '-'; ?></div>
                <div class="stat-label">Μέσος όρος μορίων</div>
            </div>
        </div>

        <div class="reports-layout">
            <div class="chart-card">
                <h3><?php echo h(search_text($selectedSpecialtyStats["title"] ?? "Ειδικότητα")); ?> ανά έτος</h3>
                <?php if ($yearlyRows === []): ?>
                    <p class="muted empty-copy">Δεν υπάρχουν ακόμη δεδομένα ανά έτος για τη συγκεκριμένη ειδικότητα.</p>
                <?php else: ?>
                    <div class="chart-mock" aria-label="Γράφημα υποψηφίων ανά έτος">
                        <?php foreach ($yearlyRows as $row): ?>
                            <?php $count = (int) $row["candidate_count"]; $width = $maxYearlyCount > 0 ? max(16, (int) round(($count / $maxYearlyCount) * 100)) : 16; ?>
                            <div class="bar" style="width: <?php echo $width; ?>%"><span><?php echo h((string) $row["report_year"]); ?> - <?php echo $count; ?> υποψήφιοι</span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-card">
                <h3>Ανά περίοδο</h3>
                <?php if ($periodRows === []): ?>
                    <p class="muted empty-copy">Δεν υπάρχουν ακόμη δεδομένα ανά περίοδο για τη συγκεκριμένη ειδικότητα.</p>
                <?php else: ?>
                    <div class="chart-mock" aria-label="Γράφημα υποψηφίων ανά περίοδο">
                        <?php foreach ($periodRows as $row): ?>
                            <?php $count = (int) $row["candidate_count"]; $width = $maxPeriodCount > 0 ? max(16, (int) round(($count / $maxPeriodCount) * 100)) : 16; ?>
                            <div class="bar" style="width: <?php echo $width; ?>%"><span><?php echo h((string) $row["period_year"] . ' - Εξάμηνο ' . $row["semester_label"]); ?> - <?php echo $count; ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . "/../includes/footer.php"; ?>