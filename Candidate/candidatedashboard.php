<?php

session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "candidate") {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

$successMessage = "";
$errorMessage = "";

$specialties = [];
$specialtiesResult = $conn->query("SELECT id, title FROM specialties ORDER BY title ASC");

if ($specialtiesResult instanceof mysqli_result) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

$candidateStmt = $conn->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.created_at,
        cp.id AS profile_id,
        cp.father_name,
        cp.mother_name,
        cp.birth_date,
        cp.specialty_id,
        cp.application_status,
        cp.ranking_position,
        cp.points,
        cp.updated_at,
        s.title AS specialty_title
    FROM users u
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    WHERE u.id = ?
    LIMIT 1
");

$candidate = null;

if ($candidateStmt) {
    $candidateStmt->bind_param("i", $_SESSION["user_id"]);
    $candidateStmt->execute();
    $candidateResult = $candidateStmt->get_result();
    $candidate = $candidateResult ? $candidateResult->fetch_assoc() : null;
    $candidateStmt->close();
}

if (!$candidate) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$notificationSettings = [
    "notify_new_list" => 1,
    "notify_rank_change" => 1,
    "notify_specialty_stats" => 0,
];

$notificationStmt = $conn->prepare("
    SELECT notify_new_list, notify_rank_change, notify_specialty_stats
    FROM candidate_notification_settings
    WHERE user_id = ?
    LIMIT 1
");

if ($notificationStmt) {
    $notificationStmt->bind_param("i", $_SESSION["user_id"]);
    $notificationStmt->execute();
    $notificationResult = $notificationStmt->get_result();
    $notificationRow = $notificationResult ? $notificationResult->fetch_assoc() : null;

    if ($notificationRow) {
        $notificationSettings = $notificationRow;
    } else {
        $insertDefaultNotificationStmt = $conn->prepare("
            INSERT INTO candidate_notification_settings (user_id, notify_new_list, notify_rank_change, notify_specialty_stats)
            VALUES (?, 1, 1, 0)
        ");

        if ($insertDefaultNotificationStmt) {
            $insertDefaultNotificationStmt->bind_param("i", $_SESSION["user_id"]);
            $insertDefaultNotificationStmt->execute();
            $insertDefaultNotificationStmt->close();
        }
    }

    $notificationStmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "update_profile") {
        $firstName = trim($_POST["first_name"] ?? "");
        $lastName = trim($_POST["last_name"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $fatherName = trim($_POST["father_name"] ?? "");
        $motherName = trim($_POST["mother_name"] ?? "");
        $birthDate = trim($_POST["birth_date"] ?? "");
        $specialtyId = (int) ($_POST["specialty_id"] ?? 0);

        if ($firstName === "" || $lastName === "") {
            $errorMessage = "Συμπλήρωσε τουλάχιστον όνομα και επώνυμο.";
        } elseif ($birthDate !== "" && strtotime($birthDate) === false) {
            $errorMessage = "Η ημερομηνία γέννησης δεν είναι έγκυρη.";
        } else {
            $validSpecialty = 0;

            foreach ($specialties as $specialty) {
                if ((int) $specialty["id"] === $specialtyId) {
                    $validSpecialty = $specialtyId;
                    break;
                }
            }

            $conn->begin_transaction();

            try {
                $userUpdateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");

                if (!$userUpdateStmt) {
                    throw new RuntimeException("Δεν ήταν δυνατή η ενημέρωση των βασικών στοιχείων.");
                }

                $userUpdateStmt->bind_param("sssi", $firstName, $lastName, $phone, $_SESSION["user_id"]);

                if (!$userUpdateStmt->execute()) {
                    throw new RuntimeException("Αποτυχία ενημέρωσης χρήστη.");
                }

                $userUpdateStmt->close();

                if ($candidate["profile_id"]) {
                    $profileUpdateStmt = $conn->prepare("
                        UPDATE candidate_profiles
                        SET father_name = ?, mother_name = ?, birth_date = ?, specialty_id = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");

                    if (!$profileUpdateStmt) {
                        throw new RuntimeException("Δεν ήταν δυνατή η ενημέρωση του candidate profile.");
                    }

                    $birthDateValue = $birthDate !== "" ? $birthDate : null;
                    $specialtyValue = $validSpecialty > 0 ? $validSpecialty : null;
                    $profileId = (int) $candidate["profile_id"];

                    $profileUpdateStmt->bind_param("sssii", $fatherName, $motherName, $birthDateValue, $specialtyValue, $profileId);

                    if (!$profileUpdateStmt->execute()) {
                        throw new RuntimeException("Αποτυχία ενημέρωσης candidate profile.");
                    }

                    $profileUpdateStmt->close();
                } else {
                    $profileInsertStmt = $conn->prepare("
                        INSERT INTO candidate_profiles
                        (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points)
                        VALUES (?, ?, ?, ?, ?, 'Προφίλ ενημερωμένο από τον χρήστη', NULL, NULL)
                    ");

                    if (!$profileInsertStmt) {
                        throw new RuntimeException("Δεν ήταν δυνατή η δημιουργία candidate profile.");
                    }

                    $birthDateValue = $birthDate !== "" ? $birthDate : null;
                    $specialtyValue = $validSpecialty > 0 ? $validSpecialty : null;

                    $profileInsertStmt->bind_param("isssi", $_SESSION["user_id"], $fatherName, $motherName, $birthDateValue, $specialtyValue);

                    if (!$profileInsertStmt->execute()) {
                        throw new RuntimeException("Αποτυχία δημιουργίας candidate profile.");
                    }

                    $profileInsertStmt->close();
                }

                $conn->commit();
                $_SESSION["first_name"] = $firstName;
                $_SESSION["last_name"] = $lastName;
                $successMessage = "Το προφίλ σου ενημερώθηκε επιτυχώς.";
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = $exception->getMessage();
            }
        }
    } elseif ($action === "save_notifications") {
        $notifyNewList = isset($_POST["notify_new_list"]) ? 1 : 0;
        $notifyRankChange = isset($_POST["notify_rank_change"]) ? 1 : 0;
        $notifySpecialtyStats = isset($_POST["notify_specialty_stats"]) ? 1 : 0;

        $notificationSaveStmt = $conn->prepare("
            INSERT INTO candidate_notification_settings (user_id, notify_new_list, notify_rank_change, notify_specialty_stats)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                notify_new_list = VALUES(notify_new_list),
                notify_rank_change = VALUES(notify_rank_change),
                notify_specialty_stats = VALUES(notify_specialty_stats)
        ");

        if ($notificationSaveStmt) {
            $notificationSaveStmt->bind_param("iiii", $_SESSION["user_id"], $notifyNewList, $notifyRankChange, $notifySpecialtyStats);

            if ($notificationSaveStmt->execute()) {
                $notificationSettings = [
                    "notify_new_list" => $notifyNewList,
                    "notify_rank_change" => $notifyRankChange,
                    "notify_specialty_stats" => $notifySpecialtyStats,
                ];
                $successMessage = "Οι ειδοποιήσεις σου ενημερώθηκαν.";
            } else {
                $errorMessage = "Δεν ήταν δυνατή η αποθήκευση των ειδοποιήσεων.";
            }

            $notificationSaveStmt->close();
        }
    } elseif ($action === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        $passwordStmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $passwordRow = null;

        if ($passwordStmt) {
            $passwordStmt->bind_param("i", $_SESSION["user_id"]);
            $passwordStmt->execute();
            $passwordResult = $passwordStmt->get_result();
            $passwordRow = $passwordResult ? $passwordResult->fetch_assoc() : null;
            $passwordStmt->close();
        }

        if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
            $errorMessage = "Συμπλήρωσε όλα τα πεδία αλλαγής κωδικού.";
        } elseif (!$passwordRow || !password_verify($currentPassword, $passwordRow["password"])) {
            $errorMessage = "Ο τρέχων κωδικός δεν είναι σωστός.";
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = "Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "Η επιβεβαίωση του νέου κωδικού δεν ταιριάζει.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePasswordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

            if ($updatePasswordStmt) {
                $updatePasswordStmt->bind_param("si", $hashedPassword, $_SESSION["user_id"]);

                if ($updatePasswordStmt->execute()) {
                    $successMessage = "Ο κωδικός σου άλλαξε επιτυχώς.";
                } else {
                    $errorMessage = "Η αλλαγή κωδικού απέτυχε.";
                }

                $updatePasswordStmt->close();
            }
        }
    } elseif ($action === "track_candidate") {
        $targetProfileId = (int) ($_POST["candidate_profile_id"] ?? 0);

        if ($targetProfileId <= 0) {
            $errorMessage = "Επίλεξε έναν υποψήφιο για παρακολούθηση.";
        } else {
            $checkTrackStmt = $conn->prepare("
                SELECT id
                FROM tracked_candidates
                WHERE user_id = ? AND candidate_profile_id = ?
                LIMIT 1
            ");

            if ($checkTrackStmt) {
                $checkTrackStmt->bind_param("ii", $_SESSION["user_id"], $targetProfileId);
                $checkTrackStmt->execute();
                $trackResult = $checkTrackStmt->get_result();

                if ($trackResult && $trackResult->num_rows > 0) {
                    $errorMessage = "Ο υποψήφιος είναι ήδη στη λίστα παρακολούθησης.";
                }

                $checkTrackStmt->close();
            }

            if ($errorMessage === "") {
                $insertTrackStmt = $conn->prepare("INSERT INTO tracked_candidates (user_id, candidate_profile_id) VALUES (?, ?)");

                if ($insertTrackStmt) {
                    $insertTrackStmt->bind_param("ii", $_SESSION["user_id"], $targetProfileId);

                    if ($insertTrackStmt->execute()) {
                        $successMessage = "Ο υποψήφιος προστέθηκε στην παρακολούθησή σου.";
                    } else {
                        $errorMessage = "Δεν ήταν δυνατή η αποθήκευση της παρακολούθησης.";
                    }

                    $insertTrackStmt->close();
                }
            }
        }
    }

    $refreshStmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.created_at,
            cp.id AS profile_id,
            cp.father_name,
            cp.mother_name,
            cp.birth_date,
            cp.specialty_id,
            cp.application_status,
            cp.ranking_position,
            cp.points,
            cp.updated_at,
            s.title AS specialty_title
        FROM users u
        LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
        LEFT JOIN specialties s ON s.id = cp.specialty_id
        WHERE u.id = ?
        LIMIT 1
    ");

    if ($refreshStmt) {
        $refreshStmt->bind_param("i", $_SESSION["user_id"]);
        $refreshStmt->execute();
        $refreshResult = $refreshStmt->get_result();
        $updatedCandidate = $refreshResult ? $refreshResult->fetch_assoc() : null;

        if ($updatedCandidate) {
            $candidate = $updatedCandidate;
        }

        $refreshStmt->close();
    }
}

$candidateAge = null;

if (!empty($candidate["birth_date"])) {
    $candidateAge = (int) date_diff(date_create($candidate["birth_date"]), date_create("today"))->y;
}

$applicationStage = "Δεν έχει ολοκληρωθεί ακόμη σύνδεση με υποψήφιο πίνακα.";
$applicationProgress = 20;

if (!empty($candidate["profile_id"]) && !empty($candidate["specialty_title"])) {
    $applicationStage = "Το προφίλ σου είναι συνδεδεμένο με ειδικότητα και μπορεί να παρακολουθεί την πορεία σου.";
    $applicationProgress = 55;
}

if ($candidate["ranking_position"] !== null) {
    $applicationStage = "Έχει εντοπιστεί θέση στον πίνακα και η παρακολούθηση της αίτησης είναι ενεργή.";
    $applicationProgress = 82;
}

$myTrackCount = 0;
$myTrackCountResult = $conn->query("SELECT COUNT(*) AS total FROM tracked_candidates WHERE user_id = " . (int) $_SESSION["user_id"]);

if ($myTrackCountResult instanceof mysqli_result) {
    $myTrackCountRow = $myTrackCountResult->fetch_assoc();
    $myTrackCount = (int) ($myTrackCountRow["total"] ?? 0);
}

$searchName = trim($_GET["search_name"] ?? "");
$searchSpecialtyId = (int) ($_GET["search_specialty_id"] ?? 0);
$searchResults = [];

$searchSql = "
    SELECT
        cp.id AS profile_id,
        u.first_name,
        u.last_name,
        s.title AS specialty_title,
        cp.ranking_position,
        cp.points,
        cp.application_status
    FROM candidate_profiles cp
    INNER JOIN users u ON u.id = cp.user_id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    WHERE u.id <> ?
";

$searchParams = [$_SESSION["user_id"]];
$searchTypes = "i";

if ($searchName !== "") {
    $searchSql .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    $searchParams[] = "%" . $searchName . "%";
    $searchTypes .= "s";
}

if ($searchSpecialtyId > 0) {
    $searchSql .= " AND cp.specialty_id = ?";
    $searchParams[] = $searchSpecialtyId;
    $searchTypes .= "i";
}

$searchSql .= " ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, u.last_name ASC LIMIT 12";

$searchStmt = $conn->prepare($searchSql);

if ($searchStmt) {
    $searchStmt->bind_param($searchTypes, ...$searchParams);
    $searchStmt->execute();
    $searchResult = $searchStmt->get_result();

    if ($searchResult instanceof mysqli_result) {
        while ($row = $searchResult->fetch_assoc()) {
            $searchResults[] = $row;
        }
    }

    $searchStmt->close();
}

$trackedRows = [];
$trackedStmt = $conn->prepare("
    SELECT
        tc.created_at,
        u.first_name,
        u.last_name,
        s.title AS specialty_title,
        cp.ranking_position,
        cp.points
    FROM tracked_candidates tc
    INNER JOIN candidate_profiles cp ON cp.id = tc.candidate_profile_id
    INNER JOIN users u ON u.id = cp.user_id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    WHERE tc.user_id = ?
    ORDER BY tc.created_at DESC
");

if ($trackedStmt) {
    $trackedStmt->bind_param("i", $_SESSION["user_id"]);
    $trackedStmt->execute();
    $trackedResult = $trackedStmt->get_result();

    if ($trackedResult instanceof mysqli_result) {
        while ($row = $trackedResult->fetch_assoc()) {
            $trackedRows[] = $row;
        }
    }

    $trackedStmt->close();
}
$pageTitle = APP_NAME . " | Candidate Dashboard";
$bodyClass = "theme-candidate";
$currentPage = "candidate";
$navBase = "../";
$headerActionLabel = "Το προφίλ μου";
$headerActionHref = "#profile";

require __DIR__ . "/../includes/header.php";

?>
    <main class="container">
        <section class="page-hero" aria-labelledby="candTitle">
            <div class="hero-text">
                <h1 id="candTitle">Καλώς ήρθες, <?php echo h($candidate["first_name"]); ?></h1>
                <p class="muted">
                    Είσαι συνδεδεμένος ως candidate και βλέπεις το προσωπικό σου dashboard με στοιχεία
                    λογαριασμού, κατάσταση προφίλ και παρακολούθηση άλλων υποψηφίων.
                </p>
            </div>

            <div class="hero-badges">
                <div class="badge">
                    <span class="badge-label">Κατάσταση</span>
                    <span class="badge-value">Συνδεδεμένος</span>
                </div>
                <div class="badge">
                    <span class="badge-label">Ειδικότητα</span>
                    <span class="badge-value"><?php echo h($candidate["specialty_title"] ?? "Δεν ορίστηκε"); ?></span>
                </div>
            </div>
        </section>

        <?php if ($successMessage !== ""): ?>
            <div class="alert alert-success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ""): ?>
            <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <section class="grid grid-admin" aria-label="Ενότητες candidate">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
                <h2>Το προφίλ μου</h2>
                <p>Βλέπεις και ενημερώνεις τα πραγματικά στοιχεία του λογαριασμού σου από τη βάση.</p>
                <div class="card-actions">
                    <a class="btn" href="#profile">Άνοιγμα</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
                <h2>Track my applications</h2>
                <p>Βλέπεις την κατάσταση της αίτησής σου με κείμενο, δείκτες και απλό timeline format.</p>
                <div class="card-actions">
                    <a class="btn" href="#track-my-applications">Άνοιγμα</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
                <h2>Track others</h2>
                <p>Αναζητάς άλλους υποψηφίους από τη βάση και τους προσθέτεις στη λίστα παρακολούθησης.</p>
                <div class="card-actions">
                    <a class="btn" href="#track-others">Άνοιγμα</a>
                </div>
            </article>
        </section>

        <section class="panel" id="profile" aria-labelledby="profileTitle">
            <div class="panel-head">
                <h2 id="profileTitle">Το Προφίλ Μου</h2>
                <p class="muted">Εδώ φαίνονται τα πραγματικά στοιχεία του συνδεδεμένου χρήστη.</p>
            </div>

            <form class="form-grid candidate-form" method="post" action="#profile">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="first_name">Όνομα</label>
                    <input id="first_name" name="first_name" type="text" value="<?php echo h($candidate["first_name"]); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Επώνυμο</label>
                    <input id="last_name" name="last_name" type="text" value="<?php echo h($candidate["last_name"]); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Τηλέφωνο</label>
                    <input id="phone" name="phone" type="text" value="<?php echo h($candidate["phone"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" value="<?php echo h($candidate["email"]); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="father_name">Όνομα πατέρα</label>
                    <input id="father_name" name="father_name" type="text" value="<?php echo h($candidate["father_name"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="mother_name">Όνομα μητέρας</label>
                    <input id="mother_name" name="mother_name" type="text" value="<?php echo h($candidate["mother_name"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="birth_date">Ημερομηνία γέννησης</label>
                    <input id="birth_date" name="birth_date" type="date" value="<?php echo h($candidate["birth_date"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="specialty_id">Ειδικότητα</label>
                    <select id="specialty_id" name="specialty_id">
                        <option value="0">Επιλογή ειδικότητας</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo (int) ($candidate["specialty_id"] ?? 0) === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Αποθήκευση</button>
                </div>
            </form>

            <div class="panel panel-nested" id="notifications" aria-labelledby="notificationTitle">
                <div class="panel-head">
                    <h3 id="notificationTitle">Ειδοποιήσεις</h3>
                    <p class="muted">Επίλεξε ποιες ενημερώσεις θέλεις να λαμβάνεις.</p>
                </div>

                <form method="post" action="#notifications">
                    <input type="hidden" name="action" value="save_notifications">

                    <div class="check-list">
                        <label class="check-item">
                            <input type="checkbox" name="notify_new_list" <?php echo (int) $notificationSettings["notify_new_list"] === 1 ? "checked" : ""; ?>>
                            <span>Φόρτωση νέας λίστας</span>
                        </label>

                        <label class="check-item">
                            <input type="checkbox" name="notify_rank_change" <?php echo (int) $notificationSettings["notify_rank_change"] === 1 ? "checked" : ""; ?>>
                            <span>Αλλαγή της θέσης μου</span>
                        </label>

                        <label class="check-item">
                            <input type="checkbox" name="notify_specialty_stats" <?php echo (int) $notificationSettings["notify_specialty_stats"] === 1 ? "checked" : ""; ?>>
                            <span>Ενημέρωση στατιστικών ειδικότητας</span>
                        </label>
                    </div>

                    <button class="btn" type="submit">Αποθήκευση Ειδοποιήσεων</button>
                </form>
            </div>

            <div class="panel panel-nested" id="candidate-password" aria-labelledby="candidatePasswordTitle">
                <div class="panel-head">
                    <h3 id="candidatePasswordTitle">Αλλαγή κωδικού</h3>
                    <p class="muted">Ο υποψήφιος μπορεί να αλλάξει τον κωδικό πρόσβασής του μέσα από το profile.</p>
                </div>

                <form method="post" action="#candidate-password">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-stack">
                        <div class="form-group">
                            <label for="current_password">Τρέχων κωδικός</label>
                            <input id="current_password" name="current_password" type="password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Νέος κωδικός</label>
                            <input id="new_password" name="new_password" type="password" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Επιβεβαίωση νέου κωδικού</label>
                            <input id="confirm_password" name="confirm_password" type="password" required>
                        </div>
                    </div>

                    <button class="btn" type="submit">Αλλαγή Κωδικού</button>
                </form>
            </div>
        </section>

        <section class="panel" id="track-my-applications" aria-labelledby="statusTitle">
            <div class="panel-head">
                <h2 id="statusTitle">Track My Applications</h2>
                <p class="muted">Σύνδεση με τον υποψήφιο πίνακα και παρακολούθηση της πορείας σου.</p>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo h($candidate["specialty_title"] ?? "—"); ?></div>
                    <div class="stat-label">Ειδικότητα</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $candidate["ranking_position"] !== null ? (int) $candidate["ranking_position"] : "—"; ?></div>
                    <div class="stat-label">Θέση στον πίνακα</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $candidate["points"] !== null ? number_format((float) $candidate["points"], 2) : "—"; ?></div>
                    <div class="stat-label">Μονάδες</div>
                </div>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
                    <h3>Στοιχεία αίτησης</h3>
                    <div class="year-list">
                        <div class="year-item"><span>Ηλικία</span><strong><?php echo $candidateAge !== null ? $candidateAge . " ετών" : "Δεν ορίστηκε"; ?></strong></div>
                        <div class="year-item"><span>Κατάσταση αίτησης</span><strong><?php echo h($candidate["application_status"] ?? "Δεν υπάρχει ακόμη"); ?></strong></div>
                        <div class="year-item"><span>Τελευταία ενημέρωση</span><strong><?php echo !empty($candidate["updated_at"]) ? h(date("d/m/Y H:i", strtotime($candidate["updated_at"]))) : "Δεν υπάρχει"; ?></strong></div>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Κείμενο και timeline</h3>
                    <p class="muted"><?php echo h($applicationStage); ?></p>
                    <div class="progress-track" aria-label="Πρόοδος αίτησης">
                        <div class="progress-value" style="width: <?php echo $applicationProgress; ?>%"></div>
                    </div>
                    <div class="year-list">
                        <div class="year-item"><span>1. Προφίλ χρήστη</span><strong><?php echo !empty($candidate["first_name"]) ? "Ολοκληρώθηκε" : "Σε εκκρεμότητα"; ?></strong></div>
                        <div class="year-item"><span>2. Σύνδεση με ειδικότητα</span><strong><?php echo !empty($candidate["specialty_title"]) ? "Ολοκληρώθηκε" : "Σε εκκρεμότητα"; ?></strong></div>
                        <div class="year-item"><span>3. Εντοπισμός στον πίνακα</span><strong><?php echo $candidate["ranking_position"] !== null ? "Ολοκληρώθηκε" : "Σε εκκρεμότητα"; ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="panel panel-nested">
                <div class="panel-head">
                    <h3>Συγκριτικά στοιχεία</h3>
                </div>
                <div class="year-list">
                    <div class="year-item"><span>Ονοματεπώνυμο</span><strong><?php echo h($candidate["first_name"] . " " . $candidate["last_name"]); ?></strong></div>
                    <div class="year-item"><span>Email</span><strong><?php echo h($candidate["email"]); ?></strong></div>
                    <div class="year-item"><span>Παρακολουθήσεις άλλων</span><strong><?php echo $myTrackCount; ?></strong></div>
                </div>
            </div>
        </section>

        <section class="panel" id="track-others" aria-labelledby="trackOthersTitle">
            <div class="panel-head">
                <h2 id="trackOthersTitle">Παρακολούθηση Άλλων Υποψηφίων</h2>
                <p class="muted">Αναζήτηση άλλων υποψηφίων και αποθήκευση στη δική σου λίστα παρακολούθησης.</p>
            </div>

            <form class="form-grid" method="get" action="#track-others">
                <div class="form-group">
                    <label for="search_name">Ονοματεπώνυμο</label>
                    <input id="search_name" name="search_name" type="text" value="<?php echo h($searchName); ?>" placeholder="π.χ. Παπαδόπουλος Γιώργος">
                </div>

                <div class="form-group">
                    <label for="search_specialty_id">Ειδικότητα</label>
                    <select id="search_specialty_id" name="search_specialty_id">
                        <option value="0">Όλες οι ειδικότητες</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $searchSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Αναζήτηση</button>
                </div>
            </form>

            <div class="table-wrap" role="region" aria-label="Αποτελέσματα αναζήτησης υποψηφίων">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Υποψήφιος</th>
                            <th>Ειδικότητα</th>
                            <th>Θέση</th>
                            <th>Μονάδες</th>
                            <th>Κατάσταση</th>
                            <th class="right">Ενέργεια</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($searchResults === []): ?>
                            <tr>
                                <td colspan="6" class="empty-cell">Δεν βρέθηκαν άλλοι υποψήφιοι με αυτά τα κριτήρια.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($searchResults as $row): ?>
                                <tr>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["specialty_title"] ?? "—"); ?></td>
                                    <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "—"; ?></td>
                                    <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "—"; ?></td>
                                    <td><?php echo h($row["application_status"] ?? "—"); ?></td>
                                    <td class="right">
                                        <form method="post" action="#track-others">
                                            <input type="hidden" name="action" value="track_candidate">
                                            <input type="hidden" name="candidate_profile_id" value="<?php echo (int) $row["profile_id"]; ?>">
                                            <button class="btn btn-small" type="submit">Παρακολούθηση</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel panel-nested">
                <div class="panel-head">
                    <h3>Η λίστα παρακολούθησής μου</h3>
                </div>

                <div class="table-wrap" role="region" aria-label="Λίστα παρακολούθησης">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Υποψήφιος</th>
                                <th>Ειδικότητα</th>
                                <th>Θέση</th>
                                <th>Μονάδες</th>
                                <th>Ημερομηνία</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($trackedRows === []): ?>
                                <tr>
                                    <td colspan="5" class="empty-cell">Δεν έχεις προσθέσει ακόμη υποψηφίους στη λίστα παρακολούθησης.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trackedRows as $row): ?>
                                    <tr>
                                        <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                        <td><?php echo h($row["specialty_title"] ?? "—"); ?></td>
                                        <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "—"; ?></td>
                                        <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "—"; ?></td>
                                        <td><?php echo h(date("d/m/Y H:i", strtotime($row["created_at"]))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

<?php require __DIR__ . "/../includes/footer.php"; ?>
