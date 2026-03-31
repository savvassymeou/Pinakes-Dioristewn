<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_role("admin", "auth/login.php", "modules/admin/dashboard.php", "modules/admin/candidate/dashboard.php");

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

$keyword = trim($_GET["keyword"] ?? "");
$specialtyId = (int) ($_GET["specialty_id"] ?? 0);
$results = [];
$totalResults = 0;
$selectedSpecialtyLabel = "Όλες";

$specialties = [];
$specialtiesResult = $conn->query("SELECT id, title FROM specialties ORDER BY title ASC");

if ($specialtiesResult) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

foreach ($specialties as $specialty) {
    if ((int) $specialty["id"] === $specialtyId) {
        $selectedSpecialtyLabel = $specialty["title"];
        break;
    }
}

$sql = "
    SELECT
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        s.title AS specialty_title,
        cp.application_status,
        cp.ranking_position,
        cp.points
    FROM candidate_profiles cp
    INNER JOIN users u ON u.id = cp.user_id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($keyword !== "") {
    $sql .= " AND (
        CONCAT(u.first_name, ' ', u.last_name) LIKE ?
        OR u.email LIKE ?
        OR COALESCE(u.phone, '') LIKE ?
        OR COALESCE(s.title, '') LIKE ?
        OR COALESCE(cp.application_status, '') LIKE ?
    )";

    $searchTerm = "%" . $keyword . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sssss";
}

if ($specialtyId > 0) {
    $sql .= " AND cp.specialty_id = ?";
    $params[] = $specialtyId;
    $types .= "i";
}

$sql .= " ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, u.last_name ASC, u.first_name ASC LIMIT 50";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }

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

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λίστα Υποψηφίων | Πίνακες Διοριστέων</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eef3f8;
            --bg-accent: #dce7f5;
            --panel: rgba(255, 255, 255, 0.96);
            --panel-border: rgba(21, 55, 92, 0.12);
            --text: #14263d;
            --muted: #5d7088;
            --accent: #b8862f;
            --accent-2: #d9ab55;
            --accent-dark: #7a5720;
            --field: #f7f9fc;
            --field-border: #cfdae8;
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

        a { color: inherit; text-decoration: none; }

        .page {
            min-height: 100vh;
            padding: 28px 18px 40px;
        }

        .shell {
            width: min(1200px, 100%);
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            font-family: "Space Grotesk", sans-serif;
            box-shadow: 0 14px 28px rgba(184, 134, 47, 0.22);
            font-weight: 700;
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

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 14px;
            border: 1px solid var(--panel-border);
            background: rgba(255, 255, 255, 0.68);
            color: var(--text);
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .action-link:hover {
            transform: translateY(-1px);
            background: #fff;
            box-shadow: 0 12px 28px rgba(17, 39, 68, 0.10);
        }

        .panel {
            border-radius: 28px;
            background: var(--panel);
            border: 1px solid var(--panel-border);
            box-shadow: var(--shadow);
            padding: 28px;
        }

        .hero {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 26px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            margin-bottom: 16px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(184, 134, 47, 0.12);
            color: var(--accent-dark);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 10px;
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(2rem, 4vw, 2.9rem);
            line-height: 1.02;
        }

        .intro {
            margin: 0;
            color: var(--muted);
            line-height: 1.68;
            max-width: 64ch;
        }

        .hero-badges {
            min-width: 240px;
            display: grid;
            gap: 12px;
        }

        .badge,
        .stat {
            border: 1px solid var(--panel-border);
            background: rgba(247, 249, 252, 0.9);
            border-radius: 20px;
            padding: 14px 16px;
        }

        .badge-label,
        .stat-label {
            display: block;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .badge-value,
        .stat-kpi {
            display: block;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .filters,
        .results {
            margin-top: 18px;
            padding: 22px;
            border-radius: 24px;
            background: rgba(247, 249, 252, 0.72);
            border: 1px solid var(--panel-border);
        }

        .section-head h2 {
            margin: 0 0 8px;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.45rem;
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
            margin: 0 0 8px;
            font-weight: 800;
        }

        input,
        select {
            width: 100%;
            padding: 15px 16px;
            border-radius: 16px;
            border: 1px solid var(--field-border);
            background: var(--field);
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        input::placeholder { color: #8b9cb0; }

        input:focus,
        select:focus {
            outline: none;
            border-color: rgba(184, 134, 47, 0.72);
            box-shadow: 0 0 0 4px rgba(184, 134, 47, 0.12);
            transform: translateY(-1px);
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
            min-width: 140px;
            padding: 14px 16px;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-size: 0.98rem;
            font-weight: 800;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 32px rgba(184, 134, 47, 0.24);
        }

        .btn-secondary {
            color: var(--text);
            background: #fff;
            border: 1px solid var(--field-border);
            box-shadow: 0 14px 28px rgba(17, 39, 68, 0.08);
        }

        .btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.02);
        }

        .table-wrap {
            overflow: auto;
            border-radius: 20px;
            border: 1px solid var(--panel-border);
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(21, 55, 92, 0.08);
        }

        th {
            background: #f4f7fb;
            color: var(--muted);
            font-size: 0.84rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        tbody tr:hover {
            background: rgba(220, 231, 245, 0.26);
        }

        .candidate-name {
            font-weight: 800;
        }

        .subtle {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(184, 134, 47, 0.12);
            color: var(--accent-dark);
            font-size: 0.84rem;
            font-weight: 700;
        }

        .empty-state {
            padding: 28px 18px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 920px) {
            .hero {
                flex-direction: column;
            }

            .hero-badges {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .page {
                padding: 18px 12px 28px;
            }

            .panel,
            .filters,
            .results {
                padding: 20px 16px;
                border-radius: 22px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-badges {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="shell">
            <div class="topbar">
                <div class="brand">
                    <span class="brand-mark">EEY</span>
                    <div class="brand-copy">
                        <strong>Πίνακες Διοριστέων</strong>
                        <span>Admin εργαλεία διαχείρισης λίστας</span>
                    </div>
                </div>

                <div class="top-actions">
                    <a class="action-link" href="modules/admin/dashboard.php">Επιστροφή στο Admin</a>
                    <a class="action-link" href="auth/logout.php">Αποσύνδεση</a>
                </div>
            </div>

            <section class="panel">
                <div class="hero">
                    <div>
                        <span class="eyebrow">Admin List</span>
                        <h1>Λίστα Υποψηφίων</h1>
                        <p class="intro">
                            Προστατευμένη σελίδα διαχείρισης για τον admin, με keyword search σε ονοματεπώνυμο, email, τηλέφωνο, ειδικότητα και κατάσταση αίτησης.
                        </p>
                    </div>

                    <div class="hero-badges">
                        <div class="badge">
                            <span class="badge-label">Πρόσβαση</span>
                            <span class="badge-value">Μόνο Admin</span>
                        </div>
                        <div class="badge">
                            <span class="badge-label">Αποτελέσματα</span>
                            <span class="badge-value"><?php echo $totalResults; ?></span>
                        </div>
                    </div>
                </div>

                <section class="stats" aria-label="Σύνοψη λίστας">
                    <div class="stat">
                        <span class="stat-label">Εγγραφές που εμφανίζονται</span>
                        <span class="stat-kpi"><?php echo $totalResults; ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Τρέχουσα λέξη-κλειδί</span>
                        <span class="stat-kpi"><?php echo $keyword !== "" ? h($keyword) : "-"; ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Ειδικότητα</span>
                        <span class="stat-kpi"><?php echo h($selectedSpecialtyLabel); ?></span>
                    </div>
                </section>

                <section class="filters" aria-labelledby="filterTitle">
                    <div class="section-head">
                        <h2 id="filterTitle">Αναζήτηση στη λίστα</h2>
                        <p>Χρησιμοποίησε φίλτρα για να εντοπίσεις γρήγορα συγκεκριμένους υποψηφίους.</p>
                    </div>

                    <form class="form-grid" method="get" action="list.php">
                        <div>
                            <label for="keyword">Λέξη-κλειδί</label>
                            <input id="keyword" name="keyword" type="text" value="<?php echo h($keyword); ?>" placeholder="π.χ. Παπαδοπούλου, email, τηλέφωνο">
                        </div>

                        <div>
                            <label for="specialty_id">Ειδικότητα</label>
                            <select id="specialty_id" name="specialty_id">
                                <option value="0">Όλες οι ειδικότητες</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo (int) $specialty['id']; ?>" <?php echo $specialtyId === (int) $specialty['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($specialty['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="actions">
                            <button class="btn btn-primary" type="submit">Αναζήτηση</button>
                            <a class="btn btn-secondary" href="list.php">Καθαρισμός</a>
                        </div>
                    </form>
                </section>

                <section class="results" aria-labelledby="resultsTitle">
                    <div class="section-head">
                        <h2 id="resultsTitle">Αποτελέσματα λίστας</h2>
                        <p>
                            <?php if ($hasFilters): ?>
                                Εμφανίζονται οι εγγραφές που ταιριάζουν στα φίλτρα που επέλεξες.
                            <?php else: ?>
                                Εμφανίζονται έως 50 εγγραφές από τη λίστα υποψηφίων.
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="table-wrap" role="region" aria-label="Λίστα υποψηφίων">
                        <table>
                            <thead>
                                <tr>
                                    <th>Υποψήφιος</th>
                                    <th>Email</th>
                                    <th>Τηλέφωνο</th>
                                    <th>Ειδικότητα</th>
                                    <th>Κατάσταση</th>
                                    <th>Θέση</th>
                                    <th>Μονάδες</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($results === []): ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">Δεν βρέθηκαν αποτελέσματα για τα κριτήρια που έδωσες.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td>
                                                <div class="candidate-name"><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            </td>
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
            </section>
        </div>
    </main>
</body>
</html>

