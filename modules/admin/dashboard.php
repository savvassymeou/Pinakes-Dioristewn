<?php

session_start();
require_once __DIR__ . "/../../includes/auth.php";

require_role("admin", "../../auth/login.php", "dashboard.php", "../candidate/candidatedashboard.php");

require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";

ensure_identity_number_column($conn);

function randomGreekPhone(): string
{
    return "99" . str_pad((string) random_int(100000, 999999), 6, "0", STR_PAD_LEFT);
}

function buildDemoCandidates(string $specialtyTitle): array
{
    $pool = [
        ["Ανδρέας", "Παπαδόπουλος", "Γιώργος", "Μαρία", "1989-04-17", 91.40],
        ["Ελένη", "Χριστοδούλου", "Νίκος", "Άννα", "1993-09-05", 88.20],
        ["Μάριος", "Νεοφύτου", "Κώστας", "Σταυρούλα", "1987-12-11", 84.75],
        ["Σοφία", "Δεληγιάννη", "Μιχάλης", "Ελένη", "1998-02-26", 93.10],
        ["Πέτρος", "Στυλιανού", "Ανδρέας", "Δέσποινα", "1995-07-14", 86.60],
        ["Χριστίνα", "Νικολάου", "Σάββας", "Κατερίνα", "1991-10-30", 89.35],
    ];

    shuffle($pool);
    $selected = array_slice($pool, 0, 4);

    foreach ($selected as $index => &$candidate) {
        $candidate["application_status"] = "Φορτώθηκε από λίστα " . $specialtyTitle;
        $candidate["email"] = "demo." . time() . "." . $index . "@pinakes.local";
    }
    unset($candidate);

    return $selected;
}

$successMessage = "";
$errorMessage = "";

$specialties = [];
$specialtiesResult = $conn->query("SELECT id, title, description FROM specialties ORDER BY title ASC");

if ($specialtiesResult) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

$adminUser = null;
$adminStmt = $conn->prepare("SELECT id, username, first_name, last_name, email, identity_number, phone, password_hash, created_at FROM users WHERE id = ? LIMIT 1");

if ($adminStmt) {
    $adminStmt->bind_param("i", $_SESSION["user_id"]);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $adminUser = $adminResult ? $adminResult->fetch_assoc() : null;
    $adminStmt->close();
}

if (!$adminUser) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create_user") {
        $firstName = trim($_POST["new_first_name"] ?? "");
        $lastName = trim($_POST["new_last_name"] ?? "");
        $email = trim($_POST["new_email"] ?? "");
        $identityNumber = normalize_identity_number($_POST["new_identity_number"] ?? "");
        $phone = trim($_POST["new_phone"] ?? "");
        $username = generate_unique_username($conn, username_from_email($email));
        $role = $_POST["new_role"] ?? "candidate";
        $password = $_POST["new_password"] ?? "";

        if ($firstName === "" || $lastName === "" || $email === "" || $identityNumber === "" || $password === "") {
            $errorMessage = "Συμπλήρωσε όλα τα υποχρεωτικά πεδία για νέο χρήστη.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email δεν είναι σε έγκυρη μορφή.";
        } elseif (!is_valid_identity_number($identityNumber)) {
            $errorMessage = identity_number_validation_message();
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "Ο ρόλος που επιλέχθηκε δεν είναι έγκυρος.";
        } elseif (strlen($password) < 8) {
            $errorMessage = "Ο κωδικός του νέου χρήστη πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR identity_number = ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("ss", $email, $identityNumber);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Υπάρχει ήδη χρήστης με αυτό το email ή αριθμό ταυτότητας.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, identity_number, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                if ($createStmt) {
                    $createStmt->bind_param("ssssssss", $username, $firstName, $lastName, $email, $identityNumber, $phone, $hashedPassword, $role);

                    if ($createStmt->execute()) {
                        $successMessage = "Ο χρήστης δημιουργήθηκε επιτυχώς.";
                    } else {
                        $errorMessage = "Δεν ήταν δυνατή η δημιουργία του χρήστη.";
                    }

                    $createStmt->close();
                }
            }
        }
    } elseif ($action === "update_user") {
        $editUserId = (int) ($_POST["edit_user_id"] ?? 0);
        $firstName = trim($_POST["edit_first_name"] ?? "");
        $lastName = trim($_POST["edit_last_name"] ?? "");
        $email = trim($_POST["edit_email"] ?? "");
        $identityNumber = normalize_identity_number($_POST["edit_identity_number"] ?? "");
        $phone = trim($_POST["edit_phone"] ?? "");
        $role = $_POST["edit_role"] ?? "candidate";
        $password = $_POST["edit_password"] ?? "";

        if ($editUserId <= 0 || $firstName === "" || $lastName === "" || $email === "" || $identityNumber === "") {
            $errorMessage = "Συμπλήρωσε σωστά τα στοιχεία επεξεργασίας χρήστη.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email δεν είναι σε έγκυρη μορφή.";
        } elseif (!is_valid_identity_number($identityNumber)) {
            $errorMessage = identity_number_validation_message();
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "Ο ρόλος που επιλέχθηκε δεν είναι έγκυρος.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR identity_number = ?) AND id <> ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("ssi", $email, $identityNumber, $editUserId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Το email ή ο αριθμός ταυτότητας χρησιμοποιείται ήδη από άλλο χρήστη.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                if ($password !== "") {
                    if (strlen($password) < 8) {
                        $errorMessage = "Ο νέος κωδικός χρήστη πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, identity_number = ?, phone = ?, role = ?, password_hash = ? WHERE id = ?");

                        if ($updateStmt) {
                            $updateStmt->bind_param("sssssssi", $firstName, $lastName, $email, $identityNumber, $phone, $role, $hashedPassword, $editUserId);

                            if ($updateStmt->execute()) {
                                $successMessage = "Ο χρήστης ενημερώθηκε επιτυχώς.";
                            } else {
                                $errorMessage = "Δεν ήταν δυνατή η ενημέρωση του χρήστη.";
                            }

                            $updateStmt->close();
                        }
                    }
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, identity_number = ?, phone = ?, role = ? WHERE id = ?");

                    if ($updateStmt) {
                        $updateStmt->bind_param("ssssssi", $firstName, $lastName, $email, $identityNumber, $phone, $role, $editUserId);

                        if ($updateStmt->execute()) {
                            $successMessage = "Ο χρήστης ενημερώθηκε επιτυχώς.";
                        } else {
                            $errorMessage = "Δεν ήταν δυνατή η ενημέρωση του χρήστη.";
                        }

                        $updateStmt->close();
                    }
                }
            }
        }
    } elseif ($action === "delete_user") {
        $deleteUserId = (int) ($_POST["delete_user_id"] ?? 0);

        if ($deleteUserId <= 0) {
            $errorMessage = "Δεν επιλέχθηκε έγκυρος χρήστης για διαγραφή.";
        } elseif ($deleteUserId === (int) $_SESSION["user_id"]) {
            $errorMessage = "Δεν μπορείς να διαγράψεις τον δικό σου admin λογαριασμό.";
        } else {
            $conn->begin_transaction();

            try {
                $profileIds = [];
                $profileStmt = $conn->prepare("SELECT id FROM candidate_profiles WHERE user_id = ?");

                if ($profileStmt) {
                    $profileStmt->bind_param("i", $deleteUserId);
                    $profileStmt->execute();
                    $profileResult = $profileStmt->get_result();

                    while ($profileResult && $row = $profileResult->fetch_assoc()) {
                        $profileIds[] = (int) $row["id"];
                    }

                    $profileStmt->close();
                }

                if ($profileIds !== []) {
                    $deleteTrackedByProfileStmt = $conn->prepare("DELETE FROM tracked_candidates WHERE candidate_profile_id = ?");

                    foreach ($profileIds as $profileId) {
                        if ($deleteTrackedByProfileStmt) {
                            $deleteTrackedByProfileStmt->bind_param("i", $profileId);
                            $deleteTrackedByProfileStmt->execute();
                        }
                    }

                    if ($deleteTrackedByProfileStmt) {
                        $deleteTrackedByProfileStmt->close();
                    }

                    $deleteProfileStmt = $conn->prepare("DELETE FROM candidate_profiles WHERE user_id = ?");

                    if ($deleteProfileStmt) {
                        $deleteProfileStmt->bind_param("i", $deleteUserId);
                        $deleteProfileStmt->execute();
                        $deleteProfileStmt->close();
                    }
                }

                $deleteTrackedByUserStmt = $conn->prepare("DELETE FROM tracked_candidates WHERE user_id = ?");

                if ($deleteTrackedByUserStmt) {
                    $deleteTrackedByUserStmt->bind_param("i", $deleteUserId);
                    $deleteTrackedByUserStmt->execute();
                    $deleteTrackedByUserStmt->close();
                }

                $deleteUserStmt = $conn->prepare("DELETE FROM users WHERE id = ?");

                if ($deleteUserStmt) {
                    $deleteUserStmt->bind_param("i", $deleteUserId);

                    if (!$deleteUserStmt->execute()) {
                        throw new RuntimeException("Δεν διαγράφηκε ο χρήστης από τη βάση.");
                    }

                    $deleteUserStmt->close();
                }

                $conn->commit();
                $successMessage = "Ο χρήστης διαγράφηκε επιτυχώς.";
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = $exception->getMessage();
            }
        }
    } elseif ($action === "load_list") {
        $specialtyId = (int) ($_POST["specialty_id"] ?? 0);
        $loadYear = (int) ($_POST["load_year"] ?? date("Y"));
        $selectedSpecialty = null;

        foreach ($specialties as $specialty) {
            if ((int) $specialty["id"] === $specialtyId) {
                $selectedSpecialty = $specialty;
                break;
            }
        }

        if (!$selectedSpecialty) {
            $errorMessage = "Επίλεξε έγκυρη ειδικότητα για φόρτωση πίνακα.";
        } else {
            $demoCandidates = buildDemoCandidates($selectedSpecialty["title"]);
            $defaultPassword = password_hash("candidate123", PASSWORD_DEFAULT);

            $rankingResult = $conn->query("SELECT COALESCE(MAX(ranking_position), 0) AS max_rank FROM candidate_profiles WHERE specialty_id = " . $specialtyId);
            $maxRankRow = $rankingResult ? $rankingResult->fetch_assoc() : ["max_rank" => 0];
            $nextRank = (int) ($maxRankRow["max_rank"] ?? 0) + 1;

            $conn->begin_transaction();

            try {
                $userStmt = $conn->prepare(
                    "INSERT INTO users (username, first_name, last_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, 'candidate')"
                );
                $profileStmt = $conn->prepare(
                    "INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$userStmt || !$profileStmt) {
                throw new RuntimeException("Δεν ήταν δυνατή η προετοιμασία της φόρτωσης.");
                }

                foreach ($demoCandidates as $candidate) {
                    $firstName = $candidate[0];
                    $lastName = $candidate[1];
                    $fatherName = $candidate[2];
                    $motherName = $candidate[3];
                    $birthDate = $candidate[4];
                    $points = $candidate[5];
                    $applicationStatus = $candidate["application_status"] . " (" . $loadYear . ")";
                    $email = $candidate["email"];
                    $phone = randomGreekPhone();
                    $username = generate_unique_username($conn, username_from_email($email));

                    $userStmt->bind_param("ssssss", $username, $firstName, $lastName, $email, $phone, $defaultPassword);

                    if (!$userStmt->execute()) {
                    throw new RuntimeException("Αποτυχία δημιουργίας demo υποψηφίου.");
                    }

                    $userId = (int) $conn->insert_id;
                    $currentRank = $nextRank++;

                    $profileStmt->bind_param(
                        "isssisid",
                        $userId,
                        $fatherName,
                        $motherName,
                        $birthDate,
                        $specialtyId,
                        $applicationStatus,
                        $currentRank,
                        $points
                    );

                    if (!$profileStmt->execute()) {
                    throw new RuntimeException("Αποτυχία καταχώρισης στοιχείων υποψηφίου.");
                    }
                }

                $userStmt->close();
                $profileStmt->close();
                $conn->commit();
            $successMessage = "Φορτώθηκαν 4 demo υποψήφιοι για την ειδικότητα " . $selectedSpecialty["title"] . " για το έτος " . $loadYear . ".";
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = $exception->getMessage();
            }
        }
    } elseif ($action === "update_profile") {
        $firstName = trim($_POST["first_name"] ?? "");
        $lastName = trim($_POST["last_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $identityNumber = normalize_identity_number($_POST["identity_number"] ?? "");
        $phone = trim($_POST["phone"] ?? "");

        if ($firstName === "" || $lastName === "" || $email === "" || $identityNumber === "") {
            $errorMessage = "Συμπλήρωσε όνομα, επώνυμο, email και αριθμό ταυτότητας.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email δεν είναι σε έγκυρη μορφή.";
        } elseif (!is_valid_identity_number($identityNumber)) {
            $errorMessage = identity_number_validation_message();
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR identity_number = ?) AND id <> ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("ssi", $email, $identityNumber, $_SESSION["user_id"]);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Το email ή ο αριθμός ταυτότητας χρησιμοποιείται ήδη από άλλο χρήστη.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, identity_number = ?, phone = ? WHERE id = ?");

                if ($updateStmt) {
                    $updateStmt->bind_param("sssssi", $firstName, $lastName, $email, $identityNumber, $phone, $_SESSION["user_id"]);

                    if ($updateStmt->execute()) {
                        $_SESSION["first_name"] = $firstName;
                        $_SESSION["last_name"] = $lastName;
                        $_SESSION["email"] = $email;
                        $successMessage = "Τα βασικά στοιχεία του admin ενημερώθηκαν.";
                    } else {
                        $errorMessage = "Δεν έγινε ενημέρωση των στοιχείων.";
                    }

                    $updateStmt->close();
                }
            }
        }
    } elseif ($action === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
            $errorMessage = "Συμπλήρωσε όλα τα πεδία του κωδικού.";
        } elseif (!password_verify($currentPassword, $adminUser["password_hash"])) {
            $errorMessage = "Ο τρέχων κωδικός δεν είναι σωστός.";
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = "Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "Η επιβεβαίωση του νέου κωδικού δεν ταιριάζει.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");

            if ($passwordStmt) {
                $passwordStmt->bind_param("si", $hashedPassword, $_SESSION["user_id"]);

                if ($passwordStmt->execute()) {
                    $successMessage = "Ο κωδικός πρόσβασης άλλαξε επιτυχώς.";
                    $adminUser["password_hash"] = $hashedPassword;
                } else {
                    $errorMessage = "Η αλλαγή κωδικού απέτυχε.";
                }

                $passwordStmt->close();
            }
        }
    }

    if ($successMessage !== "" || $errorMessage !== "") {
        $refreshStmt = $conn->prepare("SELECT id, username, first_name, last_name, email, identity_number, phone, password_hash, created_at FROM users WHERE id = ? LIMIT 1");

        if ($refreshStmt) {
            $refreshStmt->bind_param("i", $_SESSION["user_id"]);
            $refreshStmt->execute();
            $refreshResult = $refreshStmt->get_result();
            $updatedAdminUser = $refreshResult ? $refreshResult->fetch_assoc() : null;

            if ($updatedAdminUser) {
                $adminUser = $updatedAdminUser;
            }

            $refreshStmt->close();
        }
    }
}

$editingUserId = (int) ($_GET["edit_user"] ?? 0);
$editingUser = null;

$users = [];
$usersResult = $conn->query("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.identity_number,
        u.phone,
        u.role,
        u.created_at,
        s.title AS specialty_title,
        cp.ranking_position
    FROM users u
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    ORDER BY u.role DESC, u.created_at DESC, u.id DESC
");

if ($usersResult) {
    while ($row = $usersResult->fetch_assoc()) {
        $users[] = $row;

        if ($editingUserId > 0 && (int) $row["id"] === $editingUserId) {
            $editingUser = $row;
        }
    }
}

$overview = [
    "total_candidates" => 0,
    "average_age" => null,
    "new_candidates_year" => 0,
    "tracked_total" => 0,
];

$overviewSql = "
    SELECT
        (SELECT COUNT(*) FROM candidate_profiles) AS total_candidates,
        (SELECT AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) FROM candidate_profiles WHERE birth_date IS NOT NULL) AS average_age,
        (SELECT COUNT(*) FROM users WHERE role = 'candidate' AND YEAR(created_at) = YEAR(CURDATE())) AS new_candidates_year,
        (SELECT COUNT(*) FROM tracked_candidates) AS tracked_total
";
$overviewResult = $conn->query($overviewSql);

if ($overviewResult) {
    $overviewRow = $overviewResult->fetch_assoc();

    if ($overviewRow) {
        $overview = $overviewRow;
    }
}

$specialtyStats = [];
$specialtyStatsSql = "
    SELECT
        s.id,
        s.title,
        s.description,
        COUNT(cp.id) AS candidate_count,
        AVG(cp.points) AS average_points,
        AVG(TIMESTAMPDIFF(YEAR, cp.birth_date, CURDATE())) AS average_age,
        MAX(cp.created_at) AS last_loaded
    FROM specialties s
    LEFT JOIN candidate_profiles cp ON cp.specialty_id = s.id
    GROUP BY s.id, s.title, s.description
    ORDER BY candidate_count DESC, s.title ASC
";
$specialtyStatsResult = $conn->query($specialtyStatsSql);

if ($specialtyStatsResult) {
    while ($row = $specialtyStatsResult->fetch_assoc()) {
        $specialtyStats[] = $row;
    }
}

$yearlyRows = [];
$yearlyResult = $conn->query("
    SELECT YEAR(created_at) AS report_year, COUNT(*) AS candidate_count
    FROM users
    WHERE role = 'candidate'
    GROUP BY YEAR(created_at)
    ORDER BY report_year DESC
");

if ($yearlyResult) {
    while ($row = $yearlyResult->fetch_assoc()) {
        $yearlyRows[] = $row;
    }
}

$maxSpecialtyCount = 0;

foreach ($specialtyStats as $row) {
    $maxSpecialtyCount = max($maxSpecialtyCount, (int) $row["candidate_count"]);
}

$selectedSpecialtyId = (int) ($_POST["specialty_id"] ?? ($specialties[0]["id"] ?? 0));
$selectedLoadYear = (int) ($_POST["load_year"] ?? date("Y"));
$pageTitle = APP_NAME . " | Admin Dashboard";
$bodyClass = "theme-admin";
$currentPage = "admin";
$navBase = "../";
$headerActionLabel = "Î¡Ï…Î¸Î¼Î¯ÏƒÎµÎ¹Ï‚ Î›Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï";
$headerActionHref = "#account";

function admin_text(?string $value, string $fallback = "—"): string
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

require __DIR__ . "/../../includes/header.php";

?>
    <main class="container">
        <section class="page-hero" aria-labelledby="pageTitle">
            <div class="hero-text">
                <span class="eyebrow-home">Admin Control Center</span>
    <h1 id="pageTitle">Πίνακας Διαχείρισης</h1>
    <p class="muted">Από εδώ διαχειρίζεσαι χρήστες, λίστες, βασικά reports και τα προσωπικά στοιχεία του admin λογαριασμού μέσα από ένα ενιαίο περιβάλλον.</p>
            </div>

            <div class="hero-badges">
                <div class="badge">
                    <span class="badge-label">Admin</span>
                    <span class="badge-value"><?php echo h($adminUser["first_name"] . " " . $adminUser["last_name"]); ?></span>
                </div>
                <div class="badge">
                    <span class="badge-label">Email</span>
                    <span class="badge-value"><?php echo h($adminUser["email"]); ?></span>
                </div>
            </div>
        </section>

<section class="hero-metrics" aria-label="Σύνοψη διαχείρισης">
            <article class="metric-card">
        <span class="metric-label">Σύνολο υποψηφίων</span>
                <span class="metric-value"><?php echo (int) $overview["total_candidates"]; ?></span>
        <p class="metric-note">Εγγραφές candidate profiles σε όλο το σύστημα.</p>
            </article>
            <article class="metric-card">
        <span class="metric-label">Νέοι υποψήφιοι <?php echo date("Y"); ?></span>
                <span class="metric-value"><?php echo (int) $overview["new_candidates_year"]; ?></span>
        <p class="metric-note">Νέες εγγραφές χρηστών με ρόλο candidate.</p>
            </article>
            <article class="metric-card">
        <span class="metric-label">Συνολικά tracked</span>
                <span class="metric-value"><?php echo (int) $overview["tracked_total"]; ?></span>
        <p class="metric-note">Σχέσεις παρακολούθησης ανάμεσα σε υποψηφίους.</p>
            </article>
        </section>

        <?php if ($successMessage !== ""): ?>
    <div class="alert alert-success"><?php echo h(admin_text($successMessage, "Η ενέργεια ολοκληρώθηκε επιτυχώς.")); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ""): ?>
    <div class="alert alert-error"><?php echo h(admin_text($errorMessage, "Παρουσιάστηκε πρόβλημα κατά την ολοκλήρωση της ενέργειας.")); ?></div>
        <?php endif; ?>

<section class="grid grid-admin" aria-label="Ενότητες διαχείρισης">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
    <h2>Χρήστες</h2>
    <p>Δημιουργία, επεξεργασία και διαγραφή χρηστών με άμεση πρόσβαση στον πλήρη πίνακα.</p>
    <div class="card-actions"><a class="btn" href="#manage-users">Άνοιγμα</a></div>
            </article>
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
    <h2>Λίστες</h2>
    <p>Προβολή στατιστικών ανά ειδικότητα και φόρτωση demo υποψηφίων για δοκιμές.</p>
    <div class="card-actions"><a class="btn" href="#manage-lists">Άνοιγμα</a></div>
            </article>
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
    <h2>Reports</h2>
    <p>Συνοπτικά KPI, κατανομή υποψηφίων και χρονολογική επισκόπηση του συστήματος.</p>
    <div class="card-actions"><a class="btn" href="#reports">Άνοιγμα</a></div>
            </article>
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">4</div>
    <h2>Λογαριασμός</h2>
    <p>Ενημέρωση βασικών στοιχείων και αλλαγή κωδικού πρόσβασης για τον admin λογαριασμό.</p>
    <div class="card-actions"><a class="btn" href="#account">Άνοιγμα</a></div>
            </article>
        </section>

        <section class="panel" id="manage-users" aria-labelledby="usersTitle">
            <div class="panel-head">
        <h2 id="usersTitle">Διαχείριση Χρηστών</h2>
        <p class="muted">Οργάνωσε το σύνολο των χρηστών του συστήματος από ένα ενιαίο σημείο διαχείρισης.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="#manage-users">
                    <input type="hidden" name="action" value="create_user">
        <h3>Νέος Χρήστης</h3>
                    <div class="form-stack">
                        <div class="form-group">
                <label for="new_first_name">Όνομα</label>
                            <input id="new_first_name" name="new_first_name" type="text" required>
                        </div>
                        <div class="form-group">
                <label for="new_last_name">Επώνυμο</label>
                            <input id="new_last_name" name="new_last_name" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input id="new_email" name="new_email" type="email" required>
                        </div>
                        <div class="form-group">
                <label for="new_identity_number">Αριθμός ταυτότητας</label>
                            <input id="new_identity_number" name="new_identity_number" type="text" required>
                        </div>
                        <div class="form-group">
                <label for="new_phone">Τηλέφωνο</label>
                            <input id="new_phone" name="new_phone" type="text">
                        </div>
                        <div class="form-group">
                <label for="new_role">Ρόλος</label>
                            <select id="new_role" name="new_role" required>
                                <option value="candidate">candidate</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                <label for="new_password">Κωδικός πρόσβασης</label>
                            <input id="new_password" name="new_password" type="password" required>
                        </div>
                    </div>
        <button class="btn btn-primary" type="submit">Δημιουργία Χρήστη</button>
                </form>

                <form class="panel panel-nested" method="post" action="#manage-users">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="edit_user_id" value="<?php echo (int) ($editingUser["id"] ?? 0); ?>">
        <h3>Επεξεργασία Χρήστη</h3>

                    <?php if ($editingUser): ?>
                        <div class="form-stack">
                            <div class="form-group">
                <label for="edit_first_name">Όνομα</label>
                                <input id="edit_first_name" name="edit_first_name" type="text" value="<?php echo h($editingUser["first_name"]); ?>" required>
                            </div>
                            <div class="form-group">
                <label for="edit_last_name">Επώνυμο</label>
                                <input id="edit_last_name" name="edit_last_name" type="text" value="<?php echo h($editingUser["last_name"]); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input id="edit_email" name="edit_email" type="email" value="<?php echo h($editingUser["email"]); ?>" required>
                            </div>
                            <div class="form-group">
                <label for="edit_identity_number">Αριθμός ταυτότητας</label>
                                <input id="edit_identity_number" name="edit_identity_number" type="text" value="<?php echo h($editingUser["identity_number"] ?? ""); ?>" required>
                            </div>
                            <div class="form-group">
                <label for="edit_phone">Τηλέφωνο</label>
                                <input id="edit_phone" name="edit_phone" type="text" value="<?php echo h($editingUser["phone"] ?? ""); ?>">
                            </div>
                            <div class="form-group">
                <label for="edit_role">Ρόλος</label>
                                <select id="edit_role" name="edit_role" required>
                                    <option value="candidate" <?php echo ($editingUser["role"] ?? "") === "candidate" ? "selected" : ""; ?>>candidate</option>
                                    <option value="admin" <?php echo ($editingUser["role"] ?? "") === "admin" ? "selected" : ""; ?>>admin</option>
                                </select>
                            </div>
                            <div class="form-group">
                <label for="edit_password">Νέος κωδικός</label>
                <input id="edit_password" name="edit_password" type="password" placeholder="Άφησέ το κενό για να μείνει ο ίδιος">
                            </div>
                        </div>
                        <div class="card-actions">
            <button class="btn btn-primary" type="submit">Αποθήκευση Αλλαγών</button>
            <a class="btn btn-secondary" href="dashboard.php#manage-users">Ακύρωση</a>
                        </div>
                    <?php else: ?>
        <div class="empty-state">Επίλεξε έναν χρήστη από τον παρακάτω πίνακα για να φορτωθούν τα στοιχεία του προς επεξεργασία.</div>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-titlebar">
    <h3>Πίνακας χρηστών</h3>
    <p class="panel-subtitle">Σύνολο: <?php echo count($users); ?> εγγραφές</p>
            </div>
<div class="table-wrap" role="region" aria-label="Λίστα χρηστών">
                <table class="table">
                    <thead>
                        <tr>
            <th>Όνομα</th>
                            <th>Email</th>
            <th>Ταυτότητα</th>
            <th>Ρόλος</th>
            <th>Ειδικότητα</th>
            <th>Θέση</th>
            <th>Ημερομηνία</th>
            <th class="right">Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users === []): ?>
            <tr><td colspan="8" class="empty-cell">Δεν υπάρχουν χρήστες για εμφάνιση.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["email"]); ?></td>
                                    <td><?php echo h($row["identity_number"] ?? "—"); ?></td>
                                    <td><span class="pill"><?php echo h($row["role"]); ?></span></td>
                                    <td><?php echo h(admin_text($row["specialty_title"] ?? null)); ?></td>
                <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : '—'; ?></td>
                                    <td><?php echo h(date("d/m/Y", strtotime($row["created_at"]))); ?></td>
                                    <td class="right">
                                        <div class="inline-actions">
                                            <a class="btn btn-small" href="?edit_user=<?php echo (int) $row["id"]; ?>#manage-users">Edit</a>
                                            <?php if ((int) $row["id"] !== (int) $_SESSION["user_id"]): ?>
                                                <form method="post" action="#manage-users" onsubmit="return confirm('ÎÎ± Î´Î¹Î±Î³ÏÎ±Ï†ÎµÎ¯ Î¿ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚;');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo (int) $row["id"]; ?>">
                                                    <button class="btn btn-small" type="submit">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="manage-lists" aria-labelledby="listsTitle">
            <div class="panel-head">
        <h2 id="listsTitle">Διαχείριση Λιστών</h2>
        <p class="muted">Φόρτωσε demo υποψηφίους για μια ειδικότητα και δες συνοπτικά στατιστικά ανά λίστα.</p>
            </div>

            <form class="form-grid" method="post" action="#manage-lists">
                <input type="hidden" name="action" value="load_list">
                <div class="form-group">
            <label for="specialty_id">Ειδικότητα</label>
                    <select id="specialty_id" name="specialty_id" required>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $selectedSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h(admin_text($specialty["title"])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
            <label for="load_year">Έτος</label>
                    <select id="load_year" name="load_year" required>
                        <?php for ($year = (int) date("Y"); $year >= (int) date("Y") - 3; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $selectedLoadYear === $year ? "selected" : ""; ?>><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group form-actions">
        <button class="btn btn-primary" type="submit">Φόρτωση Demo Υποψηφίων</button>
                </div>
            </form>

            <div class="table-titlebar">
    <h3>Στατιστικά ανά ειδικότητα</h3>
    <p class="panel-subtitle"><?php echo count($specialtyStats); ?> ειδικότητες</p>
            </div>
<div class="table-wrap" role="region" aria-label="Πίνακες ανά ειδικότητα">
                <table class="table">
                    <thead>
                        <tr>
            <th>Ειδικότητα</th>
            <th>Περιγραφή</th>
            <th>Υποψήφιοι</th>
            <th>Μέσος όρος ηλικίας</th>
            <th>Μέσος όρος μορίων</th>
            <th>Τελευταία φόρτωση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($specialtyStats === []): ?>
            <tr><td colspan="6" class="empty-cell">Δεν υπάρχουν ακόμη στατιστικά για εμφάνιση.</td></tr>
                        <?php else: ?>
                            <?php foreach ($specialtyStats as $row): ?>
                                <tr>
                                    <td><?php echo h(admin_text($row["title"])); ?></td>
                <td><?php echo h(admin_text($row["description"] ?? null, "—")); ?></td>
                                    <td><?php echo (int) $row["candidate_count"]; ?></td>
                <td><?php echo $row["average_age"] !== null ? number_format((float) $row["average_age"], 1) : '—'; ?></td>
                <td><?php echo $row["average_points"] !== null ? number_format((float) $row["average_points"], 2) : '—'; ?></td>
                <td><?php echo $row["last_loaded"] ? h(date("d/m/Y H:i", strtotime($row["last_loaded"]))) : 'Δεν υπάρχει φόρτωση'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="reports" aria-labelledby="reportsTitle">
            <div class="panel-head">
        <h2 id="reportsTitle">Reports</h2>
        <p class="muted">Συνοπτική εικόνα της δραστηριότητας του συστήματος με βασικούς δείκτες και κατανομές.</p>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) $overview["total_candidates"]; ?></div>
        <div class="stat-label">Σύνολο candidate profiles</div>
                </div>
                <div class="stat">
        <div class="stat-kpi"><?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : '—'; ?></div>
        <div class="stat-label">Μέσος όρος ηλικίας</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) $overview["tracked_total"]; ?></div>
                    <div class="stat-label">Î£Ï…Î½Î¿Î»Î¹ÎºÎ¬ tracked relationships</div>
                </div>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
        <h3>Υποψήφιοι ανά ειδικότητα</h3>
                    <?php if ($specialtyStats === [] || $maxSpecialtyCount === 0): ?>
        <p class="empty-copy">Δεν υπάρχουν αρκετά δεδομένα για γράφημα αυτή τη στιγμή.</p>
                    <?php else: ?>
        <div class="chart-mock" aria-label="Γράφημα υποψηφίων ανά ειδικότητα">
                            <?php foreach ($specialtyStats as $row): ?>
                                <?php $count = (int) $row["candidate_count"]; $width = $maxSpecialtyCount > 0 ? max(12, (int) round(($count / $maxSpecialtyCount) * 100)) : 12; ?>
                                <div class="bar" style="width: <?php echo $width; ?>%"><span><?php echo h(admin_text($row["title"])); ?> - <?php echo $count; ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-card">
        <h3>Υποψήφιοι ανά έτος</h3>
                    <?php if ($yearlyRows === []): ?>
        <p class="empty-copy">Δεν υπάρχουν ακόμη εγγραφές ανά έτος για εμφάνιση.</p>
                    <?php else: ?>
                        <div class="year-list">
                            <?php foreach ($yearlyRows as $yearRow): ?>
            <div class="year-item"><span><?php echo h((string) $yearRow["report_year"]); ?></span><strong><?php echo (int) $yearRow["candidate_count"]; ?> υποψήφιοι</strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="report-note"><span class="pill">Tracked: <?php echo (int) $overview["tracked_total"]; ?></span></div>
                </div>
            </div>
        </section>

        <section class="panel" id="account" aria-labelledby="accountTitle">
            <div class="panel-head">
        <h2 id="accountTitle">Λογαριασμός Admin</h2>
        <p class="muted">Ενημέρωση βασικών στοιχείων και αλλαγή κωδικού πρόσβασης χωρίς έξοδο από το dashboard.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="#account">
                    <input type="hidden" name="action" value="update_profile">
        <h3>Βασικά Στοιχεία</h3>
                    <div class="form-stack">
                        <div class="form-group">
                <label for="first_name">Όνομα</label>
                            <input id="first_name" name="first_name" type="text" value="<?php echo h($adminUser["first_name"]); ?>" required>
                        </div>
                        <div class="form-group">
                <label for="last_name">Επώνυμο</label>
                            <input id="last_name" name="last_name" type="text" value="<?php echo h($adminUser["last_name"]); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="<?php echo h($adminUser["email"]); ?>" required>
                        </div>
                        <div class="form-group">
                <label for="identity_number">Αριθμός ταυτότητας</label>
                            <input id="identity_number" name="identity_number" type="text" value="<?php echo h($adminUser["identity_number"] ?? ""); ?>" required>
                        </div>
                        <div class="form-group">
                <label for="phone">Τηλέφωνο</label>
                            <input id="phone" name="phone" type="text" value="<?php echo h($adminUser["phone"] ?? ""); ?>">
                        </div>
                    </div>
        <button class="btn btn-primary" type="submit">Αποθήκευση Στοιχείων</button>
                </form>

                <form class="panel panel-nested" method="post" action="#account">
                    <input type="hidden" name="action" value="change_password">
        <h3>Αλλαγή Κωδικού</h3>
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
        <button class="btn btn-primary" type="submit">Αλλαγή Κωδικού</button>
                </form>
            </div>
        </section>
    </main>

<?php require __DIR__ . "/../../includes/footer.php"; ?>


