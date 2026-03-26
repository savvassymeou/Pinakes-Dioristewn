<?php

session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/functions.php";

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
        ["Σοφία", "Ιωάννου", "Μιχάλης", "Ελένη", "1998-02-26", 93.10],
        ["Πέτρος", "Στυλιανού", "Αντρέας", "Δέσποινα", "1995-07-14", 86.60],
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

if ($specialtiesResult instanceof mysqli_result) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

$adminUser = null;
$adminStmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, password, created_at FROM users WHERE id = ? LIMIT 1");

if ($adminStmt) {
    $adminStmt->bind_param("i", $_SESSION["user_id"]);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $adminUser = $adminResult ? $adminResult->fetch_assoc() : null;
    $adminStmt->close();
}

if (!$adminUser) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create_user") {
        $firstName = trim($_POST["new_first_name"] ?? "");
        $lastName = trim($_POST["new_last_name"] ?? "");
        $email = trim($_POST["new_email"] ?? "");
        $phone = trim($_POST["new_phone"] ?? "");
        $role = $_POST["new_role"] ?? "candidate";
        $password = $_POST["new_password"] ?? "";

        if ($firstName === "" || $lastName === "" || $email === "" || $password === "") {
            $errorMessage = "Συμπλήρωσε όλα τα υποχρεωτικά πεδία για νέο χρήστη.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email του νέου χρήστη δεν είναι έγκυρο.";
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "Ο ρόλος του χρήστη δεν είναι έγκυρος.";
        } elseif (strlen($password) < 8) {
            $errorMessage = "Ο κωδικός του νέου χρήστη πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Υπάρχει ήδη χρήστης με αυτό το email.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");

                if ($createStmt) {
                    $createStmt->bind_param("ssssss", $firstName, $lastName, $email, $phone, $hashedPassword, $role);

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
        $phone = trim($_POST["edit_phone"] ?? "");
        $role = $_POST["edit_role"] ?? "candidate";
        $password = $_POST["edit_password"] ?? "";

        if ($editUserId <= 0 || $firstName === "" || $lastName === "" || $email === "") {
            $errorMessage = "Συμπλήρωσε σωστά τα στοιχεία επεξεργασίας χρήστη.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email του χρήστη δεν είναι έγκυρο.";
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "Ο ρόλος του χρήστη δεν είναι έγκυρος.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("si", $email, $editUserId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Το email χρησιμοποιείται ήδη από άλλο χρήστη.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                if ($password !== "") {
                    if (strlen($password) < 8) {
                        $errorMessage = "Ο νέος κωδικός χρήστη πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?");

                        if ($updateStmt) {
                            $updateStmt->bind_param("ssssssi", $firstName, $lastName, $email, $phone, $role, $hashedPassword, $editUserId);

                            if ($updateStmt->execute()) {
                                $successMessage = "Ο χρήστης ενημερώθηκε επιτυχώς.";
                            } else {
                                $errorMessage = "Η ενημέρωση του χρήστη απέτυχε.";
                            }

                            $updateStmt->close();
                        }
                    }
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ? WHERE id = ?");

                    if ($updateStmt) {
                        $updateStmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $role, $editUserId);

                        if ($updateStmt->execute()) {
                            $successMessage = "Ο χρήστης ενημερώθηκε επιτυχώς.";
                        } else {
                            $errorMessage = "Η ενημέρωση του χρήστη απέτυχε.";
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
                        throw new RuntimeException("Η διαγραφή του χρήστη απέτυχε.");
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
                    "INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'candidate')"
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

                    $userStmt->bind_param("sssss", $firstName, $lastName, $email, $phone, $defaultPassword);

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
        $phone = trim($_POST["phone"] ?? "");

        if ($firstName === "" || $lastName === "" || $email === "") {
            $errorMessage = "Συμπλήρωσε όνομα, επώνυμο και email.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email δεν είναι έγκυρο.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("si", $email, $_SESSION["user_id"]);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Το email χρησιμοποιείται ήδη από άλλο χρήστη.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");

                if ($updateStmt) {
                    $updateStmt->bind_param("ssssi", $firstName, $lastName, $email, $phone, $_SESSION["user_id"]);

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
        } elseif (!password_verify($currentPassword, $adminUser["password"])) {
            $errorMessage = "Ο τρέχων κωδικός δεν είναι σωστός.";
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = "Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "Η επιβεβαίωση του νέου κωδικού δεν ταιριάζει.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

            if ($passwordStmt) {
                $passwordStmt->bind_param("si", $hashedPassword, $_SESSION["user_id"]);

                if ($passwordStmt->execute()) {
                    $successMessage = "Ο κωδικός πρόσβασης άλλαξε επιτυχώς.";
                    $adminUser["password"] = $hashedPassword;
                } else {
                    $errorMessage = "Η αλλαγή κωδικού απέτυχε.";
                }

                $passwordStmt->close();
            }
        }
    }

    if ($successMessage !== "" || $errorMessage !== "") {
        $refreshStmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, password, created_at FROM users WHERE id = ? LIMIT 1");

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

if ($usersResult instanceof mysqli_result) {
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

if ($overviewResult instanceof mysqli_result) {
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

if ($specialtyStatsResult instanceof mysqli_result) {
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

if ($yearlyResult instanceof mysqli_result) {
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
$headerActionLabel = "Αλλαγή στοιχείων";
$headerActionHref = "#account";

require __DIR__ . "/../includes/header.php";

?>
    <main class="container">
        <section class="page-hero" aria-labelledby="pageTitle">
            <div class="hero-text">
                <h1 id="pageTitle">Admin Dashboard</h1>
                <p class="muted">
                    Διαχείριση πινάκων ανά ειδικότητα, προβολή αναφορών και ενημέρωση λογαριασμού admin
                    σε μία σελίδα, όπως ζητά η εργασία.
                </p>
            </div>

            <div class="hero-badges" aria-label="Στοιχεία λογαριασμού admin">
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

        <?php if ($successMessage !== ""): ?>
            <div class="alert alert-success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ""): ?>
            <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <section class="grid grid-admin" aria-label="Ενότητες εργασίας">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
                <h2>Manage Users</h2>
                <p>Προβολή όλων των χρηστών και πλήρης διαχείριση με δημιουργία, επεξεργασία και διαγραφή.</p>
                <div class="card-actions">
                    <a class="btn" href="#manage-users">Άνοιγμα</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
                <h2>Manage Lists</h2>
                <p>Επιλογή ειδικότητας και φόρτωση διαθέσιμων πινάκων με έτοιμη demo καταχώριση υποψηφίων.</p>
                <div class="card-actions">
                    <a class="btn" href="#manage-lists">Άνοιγμα</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
                <h2>Reports</h2>
                <p>Συγκεντρωτικά στατιστικά με πλήθος υποψηφίων, μέση ηλικία και γραφική απεικόνιση ανά ειδικότητα.</p>
                <div class="card-actions">
                    <a class="btn" href="#reports">Άνοιγμα</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">4</div>
                <h2>Account</h2>
                <p>Αλλαγή βασικών στοιχείων και κωδικού του admin μέσα από ασφαλείς φόρμες.</p>
                <div class="card-actions">
                    <a class="btn" href="#account">Άνοιγμα</a>
                </div>
            </article>
        </section>

        <section class="panel" id="manage-users" aria-labelledby="usersTitle">
            <div class="panel-head">
                <h2 id="usersTitle">Manage Users</h2>
                <p class="muted">Εδώ ο admin βλέπει όλους τους χρήστες και μπορεί να κάνει add, update και remove.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="#manage-users">
                    <input type="hidden" name="action" value="create_user">
                    <h3>Προσθήκη νέου χρήστη</h3>

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
                            <label for="new_password">Κωδικός</label>
                            <input id="new_password" name="new_password" type="password" required>
                        </div>
                    </div>

                    <button class="btn" type="submit">Δημιουργία Χρήστη</button>
                </form>

                <form class="panel panel-nested" method="post" action="#manage-users">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="edit_user_id" value="<?php echo (int) ($editingUser["id"] ?? 0); ?>">
                    <h3>Επεξεργασία χρήστη</h3>

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
                                <input id="edit_password" name="edit_password" type="password" placeholder="Άφησέ το κενό αν δεν αλλάζει">
                            </div>
                        </div>

                        <button class="btn" type="submit">Αποθήκευση Αλλαγών</button>
                    <?php else: ?>
                        <p class="muted empty-copy">Επίλεξε πρώτα έναν χρήστη από τον πίνακα για επεξεργασία.</p>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap" role="region" aria-label="Λίστα χρηστών">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Όνομα</th>
                            <th>Email</th>
                            <th>Ρόλος</th>
                            <th>Τηλέφωνο</th>
                            <th>Ειδικότητα</th>
                            <th>Εγγραφή</th>
                            <th class="right">Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users === []): ?>
                            <tr>
                                <td colspan="7" class="empty-cell">Δεν υπάρχουν χρήστες στη βάση.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["email"]); ?></td>
                                    <td><span class="pill <?php echo $row["role"] === "admin" ? "pill-admin" : "pill-user"; ?>"><?php echo h($row["role"]); ?></span></td>
                                    <td><?php echo h($row["phone"] ?? "—"); ?></td>
                                    <td><?php echo h($row["specialty_title"] ?? "—"); ?></td>
                                    <td><?php echo h(date("d/m/Y", strtotime($row["created_at"]))); ?></td>
                                    <td class="right">
                                        <div class="inline-actions">
                                            <a class="btn btn-small" href="?edit_user=<?php echo (int) $row["id"]; ?>#manage-users">Edit</a>
                                            <?php if ((int) $row["id"] !== (int) $_SESSION["user_id"]): ?>
                                                <form method="post" action="#manage-users" onsubmit="return confirm('Να διαγραφεί ο χρήστης;');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo (int) $row["id"]; ?>">
                                                    <button class="btn btn-ghost btn-small" type="submit">Delete</button>
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
                <h2 id="listsTitle">Manage Lists</h2>
                <p class="muted">
                    Ο admin επιλέγει ειδικότητα και φορτώνει πίνακα. Για να λειτουργεί άμεσα στο project,
                    η φόρτωση προσθέτει demo υποψηφίους στη βάση.
                </p>
                <p class="muted">
                    Η ενότητα είναι σχεδιασμένη ώστε να εξηγεί στον χρήστη ότι τα δεδομένα αντιστοιχούν
                    σε πίνακες διοριστέων και ειδικότητες όπως εμφανίζονται από την ΕΕΥ.
                </p>
            </div>

            <form class="form-grid" method="post" action="#manage-lists">
                <input type="hidden" name="action" value="load_list">

                <div class="form-group">
                    <label for="specialty_id">Ειδικότητα</label>
                    <select id="specialty_id" name="specialty_id" required>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $selectedSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="load_year">Έτος</label>
                    <select id="load_year" name="load_year" required>
                        <?php for ($year = (int) date("Y"); $year >= (int) date("Y") - 3; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $selectedLoadYear === $year ? "selected" : ""; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Φόρτωση Πίνακα</button>
                </div>
            </form>

            <div class="table-wrap" role="region" aria-label="Πίνακες ανά ειδικότητα">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ειδικότητα</th>
                            <th>Περιγραφή</th>
                            <th>Υποψήφιοι</th>
                            <th>Μ.Ο. Ηλικίας</th>
                            <th>Τελευταία φόρτωση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($specialtyStats === []): ?>
                            <tr>
                                <td colspan="5" class="empty-cell">Δεν υπάρχουν ειδικότητες στη βάση.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($specialtyStats as $row): ?>
                                <tr>
                                    <td><?php echo h($row["title"]); ?></td>
                                    <td><?php echo h($row["description"] ?? ""); ?></td>
                                    <td><?php echo (int) $row["candidate_count"]; ?></td>
                                    <td><?php echo $row["average_age"] !== null ? number_format((float) $row["average_age"], 1) : "-"; ?></td>
                                    <td><?php echo $row["last_loaded"] ? h(date("d/m/Y H:i", strtotime($row["last_loaded"]))) : "Δεν έγινε φόρτωση"; ?></td>
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
                <p class="muted">Στατιστικά στοιχεία και dashboard με συνολική εικόνα των πινάκων.</p>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) $overview["total_candidates"]; ?></div>
                    <div class="stat-label">Υποψήφιοι ανά ειδικότητες</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi">
                        <?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : "-"; ?>
                    </div>
                    <div class="stat-label">Μέσος όρος ηλικίας</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) $overview["new_candidates_year"]; ?></div>
                    <div class="stat-label">Νέοι υποψήφιοι το <?php echo date("Y"); ?></div>
                </div>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
                    <h3>Υποψήφιοι ανά ειδικότητα</h3>

                    <?php if ($specialtyStats === [] || $maxSpecialtyCount === 0): ?>
                        <p class="muted empty-copy">Δεν υπάρχουν ακόμη δεδομένα για γράφημα. Φόρτωσε πρώτα μία λίστα.</p>
                    <?php else: ?>
                        <div class="chart-mock" aria-label="Γράφημα υποψηφίων ανά ειδικότητα">
                            <?php foreach ($specialtyStats as $row): ?>
                                <?php
                                $count = (int) $row["candidate_count"];
                                $width = $maxSpecialtyCount > 0 ? max(12, (int) round(($count / $maxSpecialtyCount) * 100)) : 12;
                                ?>
                                <div class="bar" style="width: <?php echo $width; ?>%">
                                    <span><?php echo h($row["title"]); ?> - <?php echo $count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-card">
                    <h3>Υποψήφιοι ανά έτος</h3>

                    <?php if ($yearlyRows === []): ?>
                        <p class="muted empty-copy">Δεν υπάρχουν ακόμη υποψήφιοι για ετήσια αναφορά.</p>
                    <?php else: ?>
                        <div class="year-list">
                            <?php foreach ($yearlyRows as $yearRow): ?>
                                <div class="year-item">
                                    <span><?php echo h((string) $yearRow["report_year"]); ?></span>
                                    <strong><?php echo (int) $yearRow["candidate_count"]; ?> υποψήφιοι</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="report-note">
                        <span class="pill">Tracked: <?php echo (int) $overview["tracked_total"]; ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel" id="account" aria-labelledby="accountTitle">
            <div class="panel-head">
                <h2 id="accountTitle">Στοιχεία Admin και Κωδικός</h2>
                <p class="muted">Ενημέρωση βασικών στοιχείων και αλλαγή password από το ίδιο dashboard.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="#account">
                    <input type="hidden" name="action" value="update_profile">
                    <h3>Βασικά στοιχεία</h3>

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
                            <label for="phone">Τηλέφωνο</label>
                            <input id="phone" name="phone" type="text" value="<?php echo h($adminUser["phone"] ?? ""); ?>">
                        </div>
                    </div>

                    <button class="btn" type="submit">Αποθήκευση Στοιχείων</button>
                </form>

                <form class="panel panel-nested" method="post" action="#account">
                    <input type="hidden" name="action" value="change_password">
                    <h3>Αλλαγή κωδικού</h3>

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
    </main>

<?php require __DIR__ . "/../includes/footer.php"; ?>
