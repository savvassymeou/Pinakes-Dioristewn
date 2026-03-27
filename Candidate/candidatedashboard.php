<?php

session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "candidate") {
    header("Location: ../auth/login.php");
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
    header("Location: ../auth/login.php");
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
            $errorMessage = "Î£Ï…Î¼Ï€Î»Î®ÏÏ‰ÏƒÎµ Ï„Î¿Ï…Î»Î¬Ï‡Î¹ÏƒÏ„Î¿Î½ ÏŒÎ½Î¿Î¼Î± ÎºÎ±Î¹ ÎµÏ€ÏŽÎ½Ï…Î¼Î¿.";
        } elseif ($birthDate !== "" && strtotime($birthDate) === false) {
            $errorMessage = "Î— Î·Î¼ÎµÏÎ¿Î¼Î·Î½Î¯Î± Î³Î­Î½Î½Î·ÏƒÎ·Ï‚ Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Î­Î³ÎºÏ…ÏÎ·.";
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
                    throw new RuntimeException("Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ· Ï„Ï‰Î½ Î²Î±ÏƒÎ¹ÎºÏŽÎ½ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½.");
                }

                $userUpdateStmt->bind_param("sssi", $firstName, $lastName, $phone, $_SESSION["user_id"]);

                if (!$userUpdateStmt->execute()) {
                    throw new RuntimeException("Î‘Ï€Î¿Ï„Ï…Ï‡Î¯Î± ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ·Ï‚ Ï‡ÏÎ®ÏƒÏ„Î·.");
                }

                $userUpdateStmt->close();

                if ($candidate["profile_id"]) {
                    $profileUpdateStmt = $conn->prepare("
                        UPDATE candidate_profiles
                        SET father_name = ?, mother_name = ?, birth_date = ?, specialty_id = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");

                    if (!$profileUpdateStmt) {
                        throw new RuntimeException("Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ· Ï„Î¿Ï… candidate profile.");
                    }

                    $birthDateValue = $birthDate !== "" ? $birthDate : null;
                    $specialtyValue = $validSpecialty > 0 ? $validSpecialty : null;
                    $profileId = (int) $candidate["profile_id"];

                    $profileUpdateStmt->bind_param("sssii", $fatherName, $motherName, $birthDateValue, $specialtyValue, $profileId);

                    if (!$profileUpdateStmt->execute()) {
                        throw new RuntimeException("Î‘Ï€Î¿Ï„Ï…Ï‡Î¯Î± ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ·Ï‚ candidate profile.");
                    }

                    $profileUpdateStmt->close();
                } else {
                    $profileInsertStmt = $conn->prepare("
                        INSERT INTO candidate_profiles
                        (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points)
                        VALUES (?, ?, ?, ?, ?, 'Î ÏÎ¿Ï†Î¯Î» ÎµÎ½Î·Î¼ÎµÏÏ‰Î¼Î­Î½Î¿ Î±Ï€ÏŒ Ï„Î¿Î½ Ï‡ÏÎ®ÏƒÏ„Î·', NULL, NULL)
                    ");

                    if (!$profileInsertStmt) {
                        throw new RuntimeException("Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· Î´Î·Î¼Î¹Î¿Ï…ÏÎ³Î¯Î± candidate profile.");
                    }

                    $birthDateValue = $birthDate !== "" ? $birthDate : null;
                    $specialtyValue = $validSpecialty > 0 ? $validSpecialty : null;

                    $profileInsertStmt->bind_param("isssi", $_SESSION["user_id"], $fatherName, $motherName, $birthDateValue, $specialtyValue);

                    if (!$profileInsertStmt->execute()) {
                        throw new RuntimeException("Î‘Ï€Î¿Ï„Ï…Ï‡Î¯Î± Î´Î·Î¼Î¹Î¿Ï…ÏÎ³Î¯Î±Ï‚ candidate profile.");
                    }

                    $profileInsertStmt->close();
                }

                $conn->commit();
                $_SESSION["first_name"] = $firstName;
                $_SESSION["last_name"] = $lastName;
                $successMessage = "Î¤Î¿ Ï€ÏÎ¿Ï†Î¯Î» ÏƒÎ¿Ï… ÎµÎ½Î·Î¼ÎµÏÏŽÎ¸Î·ÎºÎµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
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
                $successMessage = "ÎŸÎ¹ ÎµÎ¹Î´Î¿Ï€Î¿Î¹Î®ÏƒÎµÎ¹Ï‚ ÏƒÎ¿Ï… ÎµÎ½Î·Î¼ÎµÏÏŽÎ¸Î·ÎºÎ±Î½.";
            } else {
                $errorMessage = "Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· Î±Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· Ï„Ï‰Î½ ÎµÎ¹Î´Î¿Ï€Î¿Î¹Î®ÏƒÎµÏ‰Î½.";
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
            $errorMessage = "Î£Ï…Î¼Ï€Î»Î®ÏÏ‰ÏƒÎµ ÏŒÎ»Î± Ï„Î± Ï€ÎµÎ´Î¯Î± Î±Î»Î»Î±Î³Î®Ï‚ ÎºÏ‰Î´Î¹ÎºÎ¿Ï.";
        } elseif (!$passwordRow || !password_verify($currentPassword, $passwordRow["password"])) {
            $errorMessage = "ÎŸ Ï„ÏÎ­Ï‡Ï‰Î½ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ ÏƒÏ‰ÏƒÏ„ÏŒÏ‚.";
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = "ÎŸ Î½Î­Î¿Ï‚ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Ï€ÏÎ­Ï€ÎµÎ¹ Î½Î± Î­Ï‡ÎµÎ¹ Ï„Î¿Ï…Î»Î¬Ï‡Î¹ÏƒÏ„Î¿Î½ 8 Ï‡Î±ÏÎ±ÎºÏ„Î®ÏÎµÏ‚.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "Î— ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ· Ï„Î¿Ï… Î½Î­Î¿Ï… ÎºÏ‰Î´Î¹ÎºÎ¿Ï Î´ÎµÎ½ Ï„Î±Î¹ÏÎ¹Î¬Î¶ÎµÎ¹.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePasswordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

            if ($updatePasswordStmt) {
                $updatePasswordStmt->bind_param("si", $hashedPassword, $_SESSION["user_id"]);

                if ($updatePasswordStmt->execute()) {
                    $successMessage = "ÎŸ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ ÏƒÎ¿Ï… Î¬Î»Î»Î±Î¾Îµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
                } else {
                    $errorMessage = "Î— Î±Î»Î»Î±Î³Î® ÎºÏ‰Î´Î¹ÎºÎ¿Ï Î±Ï€Î­Ï„Ï…Ï‡Îµ.";
                }

                $updatePasswordStmt->close();
            }
        }
    } elseif ($action === "track_candidate") {
        $targetProfileId = (int) ($_POST["candidate_profile_id"] ?? 0);

        if ($targetProfileId <= 0) {
            $errorMessage = "Î•Ï€Î¯Î»ÎµÎ¾Îµ Î­Î½Î±Î½ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿ Î³Î¹Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·.";
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
                    $errorMessage = "ÎŸ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Ï‚ ÎµÎ¯Î½Î±Î¹ Î®Î´Î· ÏƒÏ„Î· Î»Î¯ÏƒÏ„Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚.";
                }

                $checkTrackStmt->close();
            }

            if ($errorMessage === "") {
                $insertTrackStmt = $conn->prepare("INSERT INTO tracked_candidates (user_id, candidate_profile_id) VALUES (?, ?)");

                if ($insertTrackStmt) {
                    $insertTrackStmt->bind_param("ii", $_SESSION["user_id"], $targetProfileId);

                    if ($insertTrackStmt->execute()) {
                        $successMessage = "ÎŸ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Ï‚ Ï€ÏÎ¿ÏƒÏ„Î­Î¸Î·ÎºÎµ ÏƒÏ„Î·Î½ Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ® ÏƒÎ¿Ï….";
                    } else {
                        $errorMessage = "Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· Î±Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· Ï„Î·Ï‚ Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚.";
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

$applicationStage = "Î”ÎµÎ½ Î­Ï‡ÎµÎ¹ Î¿Î»Î¿ÎºÎ»Î·ÏÏ‰Î¸ÎµÎ¯ Î±ÎºÏŒÎ¼Î· ÏƒÏÎ½Î´ÎµÏƒÎ· Î¼Îµ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿ Ï€Î¯Î½Î±ÎºÎ±.";
$applicationProgress = 20;

if (!empty($candidate["profile_id"]) && !empty($candidate["specialty_title"])) {
    $applicationStage = "Î¤Î¿ Ï€ÏÎ¿Ï†Î¯Î» ÏƒÎ¿Ï… ÎµÎ¯Î½Î±Î¹ ÏƒÏ…Î½Î´ÎµÎ´ÎµÎ¼Î­Î½Î¿ Î¼Îµ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± ÎºÎ±Î¹ Î¼Ï€Î¿ÏÎµÎ¯ Î½Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿Ï…Î¸ÎµÎ¯ Ï„Î·Î½ Ï€Î¿ÏÎµÎ¯Î± ÏƒÎ¿Ï….";
    $applicationProgress = 55;
}

if ($candidate["ranking_position"] !== null) {
    $applicationStage = "ÎˆÏ‡ÎµÎ¹ ÎµÎ½Ï„Î¿Ï€Î¹ÏƒÏ„ÎµÎ¯ Î¸Î­ÏƒÎ· ÏƒÏ„Î¿Î½ Ï€Î¯Î½Î±ÎºÎ± ÎºÎ±Î¹ Î· Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ· Ï„Î·Ï‚ Î±Î¯Ï„Î·ÏƒÎ·Ï‚ ÎµÎ¯Î½Î±Î¹ ÎµÎ½ÎµÏÎ³Î®.";
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
$headerActionLabel = "Î¤Î¿ Ï€ÏÎ¿Ï†Î¯Î» Î¼Î¿Ï…";
$headerActionHref = "#profile";

require __DIR__ . "/../includes/header.php";

?>
    <main class="container">
        <section class="page-hero" aria-labelledby="candTitle">
            <div class="hero-text">
                <h1 id="candTitle">ÎšÎ±Î»ÏŽÏ‚ Î®ÏÎ¸ÎµÏ‚, <?php echo h($candidate["first_name"]); ?></h1>
                <p class="muted">
                    Î•Î¯ÏƒÎ±Î¹ ÏƒÏ…Î½Î´ÎµÎ´ÎµÎ¼Î­Î½Î¿Ï‚ Ï‰Ï‚ candidate ÎºÎ±Î¹ Î²Î»Î­Ï€ÎµÎ¹Ï‚ Ï„Î¿ Ï€ÏÎ¿ÏƒÏ‰Ï€Î¹ÎºÏŒ ÏƒÎ¿Ï… dashboard Î¼Îµ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î±
                    Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï, ÎºÎ±Ï„Î¬ÏƒÏ„Î±ÏƒÎ· Ï€ÏÎ¿Ï†Î¯Î» ÎºÎ±Î¹ Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ· Î¬Î»Î»Ï‰Î½ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½.
                </p>
            </div>

            <div class="hero-badges">
                <div class="badge">
                    <span class="badge-label">ÎšÎ±Ï„Î¬ÏƒÏ„Î±ÏƒÎ·</span>
                    <span class="badge-value">Î£Ï…Î½Î´ÎµÎ´ÎµÎ¼Î­Î½Î¿Ï‚</span>
                </div>
                <div class="badge">
                    <span class="badge-label">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</span>
                    <span class="badge-value"><?php echo h($candidate["specialty_title"] ?? "Î”ÎµÎ½ Î¿ÏÎ¯ÏƒÏ„Î·ÎºÎµ"); ?></span>
                </div>
            </div>
        </section>

        <?php if ($successMessage !== ""): ?>
            <div class="alert alert-success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ""): ?>
            <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <section class="grid grid-admin" aria-label="Î•Î½ÏŒÏ„Î·Ï„ÎµÏ‚ candidate">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
                <h2>Î¤Î¿ Ï€ÏÎ¿Ï†Î¯Î» Î¼Î¿Ï…</h2>
                <p>Î’Î»Î­Ï€ÎµÎ¹Ï‚ ÎºÎ±Î¹ ÎµÎ½Î·Î¼ÎµÏÏŽÎ½ÎµÎ¹Ï‚ Ï„Î± Ï€ÏÎ±Î³Î¼Î±Ï„Î¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î± Ï„Î¿Ï… Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï ÏƒÎ¿Ï… Î±Ï€ÏŒ Ï„Î· Î²Î¬ÏƒÎ·.</p>
                <div class="card-actions">
                    <a class="btn" href="#profile">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
                <h2>Track my applications</h2>
                <p>Î’Î»Î­Ï€ÎµÎ¹Ï‚ Ï„Î·Î½ ÎºÎ±Ï„Î¬ÏƒÏ„Î±ÏƒÎ· Ï„Î·Ï‚ Î±Î¯Ï„Î·ÏƒÎ®Ï‚ ÏƒÎ¿Ï… Î¼Îµ ÎºÎµÎ¯Î¼ÎµÎ½Î¿, Î´ÎµÎ¯ÎºÏ„ÎµÏ‚ ÎºÎ±Î¹ Î±Ï€Î»ÏŒ timeline format.</p>
                <div class="card-actions">
                    <a class="btn" href="#track-my-applications">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
                <h2>Track others</h2>
                <p>Î‘Î½Î±Î¶Î·Ï„Î¬Ï‚ Î¬Î»Î»Î¿Ï…Ï‚ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï…Ï‚ Î±Ï€ÏŒ Ï„Î· Î²Î¬ÏƒÎ· ÎºÎ±Î¹ Ï„Î¿Ï…Ï‚ Ï€ÏÎ¿ÏƒÎ¸Î­Ï„ÎµÎ¹Ï‚ ÏƒÏ„Î· Î»Î¯ÏƒÏ„Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚.</p>
                <div class="card-actions">
                    <a class="btn" href="#track-others">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>
        </section>

        <section class="panel" id="profile" aria-labelledby="profileTitle">
            <div class="panel-head">
                <h2 id="profileTitle">Î¤Î¿ Î ÏÎ¿Ï†Î¯Î» ÎœÎ¿Ï…</h2>
                <p class="muted">Î•Î´ÏŽ Ï†Î±Î¯Î½Î¿Î½Ï„Î±Î¹ Ï„Î± Ï€ÏÎ±Î³Î¼Î±Ï„Î¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î± Ï„Î¿Ï… ÏƒÏ…Î½Î´ÎµÎ´ÎµÎ¼Î­Î½Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î·.</p>
            </div>

            <form class="form-grid candidate-form" method="post" action="#profile">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="first_name">ÎŒÎ½Î¿Î¼Î±</label>
                    <input id="first_name" name="first_name" type="text" value="<?php echo h($candidate["first_name"]); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Î•Ï€ÏŽÎ½Ï…Î¼Î¿</label>
                    <input id="last_name" name="last_name" type="text" value="<?php echo h($candidate["last_name"]); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Î¤Î·Î»Î­Ï†Ï‰Î½Î¿</label>
                    <input id="phone" name="phone" type="text" value="<?php echo h($candidate["phone"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" value="<?php echo h($candidate["email"]); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="father_name">ÎŒÎ½Î¿Î¼Î± Ï€Î±Ï„Î­ÏÎ±</label>
                    <input id="father_name" name="father_name" type="text" value="<?php echo h($candidate["father_name"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="mother_name">ÎŒÎ½Î¿Î¼Î± Î¼Î·Ï„Î­ÏÎ±Ï‚</label>
                    <input id="mother_name" name="mother_name" type="text" value="<?php echo h($candidate["mother_name"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="birth_date">Î—Î¼ÎµÏÎ¿Î¼Î·Î½Î¯Î± Î³Î­Î½Î½Î·ÏƒÎ·Ï‚</label>
                    <input id="birth_date" name="birth_date" type="date" value="<?php echo h($candidate["birth_date"] ?? ""); ?>">
                </div>

                <div class="form-group">
                    <label for="specialty_id">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</label>
                    <select id="specialty_id" name="specialty_id">
                        <option value="0">Î•Ï€Î¹Î»Î¿Î³Î® ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±Ï‚</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo (int) ($candidate["specialty_id"] ?? 0) === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Î‘Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ·</button>
                </div>
            </form>

            <div class="panel panel-nested" id="notifications" aria-labelledby="notificationTitle">
                <div class="panel-head">
                    <h3 id="notificationTitle">Î•Î¹Î´Î¿Ï€Î¿Î¹Î®ÏƒÎµÎ¹Ï‚</h3>
                    <p class="muted">Î•Ï€Î¯Î»ÎµÎ¾Îµ Ï€Î¿Î¹ÎµÏ‚ ÎµÎ½Î·Î¼ÎµÏÏŽÏƒÎµÎ¹Ï‚ Î¸Î­Î»ÎµÎ¹Ï‚ Î½Î± Î»Î±Î¼Î²Î¬Î½ÎµÎ¹Ï‚.</p>
                </div>

                <form method="post" action="#notifications">
                    <input type="hidden" name="action" value="save_notifications">

                    <div class="check-list">
                        <label class="check-item">
                            <input type="checkbox" name="notify_new_list" <?php echo (int) $notificationSettings["notify_new_list"] === 1 ? "checked" : ""; ?>>
                            <span>Î¦ÏŒÏÏ„Ï‰ÏƒÎ· Î½Î­Î±Ï‚ Î»Î¯ÏƒÏ„Î±Ï‚</span>
                        </label>

                        <label class="check-item">
                            <input type="checkbox" name="notify_rank_change" <?php echo (int) $notificationSettings["notify_rank_change"] === 1 ? "checked" : ""; ?>>
                            <span>Î‘Î»Î»Î±Î³Î® Ï„Î·Ï‚ Î¸Î­ÏƒÎ·Ï‚ Î¼Î¿Ï…</span>
                        </label>

                        <label class="check-item">
                            <input type="checkbox" name="notify_specialty_stats" <?php echo (int) $notificationSettings["notify_specialty_stats"] === 1 ? "checked" : ""; ?>>
                            <span>Î•Î½Î·Î¼Î­ÏÏ‰ÏƒÎ· ÏƒÏ„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÏŽÎ½ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±Ï‚</span>
                        </label>
                    </div>

                    <button class="btn" type="submit">Î‘Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· Î•Î¹Î´Î¿Ï€Î¿Î¹Î®ÏƒÎµÏ‰Î½</button>
                </form>
            </div>

            <div class="panel panel-nested" id="candidate-password" aria-labelledby="candidatePasswordTitle">
                <div class="panel-head">
                    <h3 id="candidatePasswordTitle">Î‘Î»Î»Î±Î³Î® ÎºÏ‰Î´Î¹ÎºÎ¿Ï</h3>
                    <p class="muted">ÎŸ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Ï‚ Î¼Ï€Î¿ÏÎµÎ¯ Î½Î± Î±Î»Î»Î¬Î¾ÎµÎ¹ Ï„Î¿Î½ ÎºÏ‰Î´Î¹ÎºÏŒ Ï€ÏÏŒÏƒÎ²Î±ÏƒÎ®Ï‚ Ï„Î¿Ï… Î¼Î­ÏƒÎ± Î±Ï€ÏŒ Ï„Î¿ profile.</p>
                </div>

                <form method="post" action="#candidate-password">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-stack">
                        <div class="form-group">
                            <label for="current_password">Î¤ÏÎ­Ï‡Ï‰Î½ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚</label>
                            <input id="current_password" name="current_password" type="password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">ÎÎ­Î¿Ï‚ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚</label>
                            <input id="new_password" name="new_password" type="password" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Î•Ï€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ· Î½Î­Î¿Ï… ÎºÏ‰Î´Î¹ÎºÎ¿Ï</label>
                            <input id="confirm_password" name="confirm_password" type="password" required>
                        </div>
                    </div>

                    <button class="btn" type="submit">Î‘Î»Î»Î±Î³Î® ÎšÏ‰Î´Î¹ÎºÎ¿Ï</button>
                </form>
            </div>
        </section>

        <section class="panel" id="track-my-applications" aria-labelledby="statusTitle">
            <div class="panel-head">
                <h2 id="statusTitle">Track My Applications</h2>
                <p class="muted">Î£ÏÎ½Î´ÎµÏƒÎ· Î¼Îµ Ï„Î¿Î½ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿ Ï€Î¯Î½Î±ÎºÎ± ÎºÎ±Î¹ Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ· Ï„Î·Ï‚ Ï€Î¿ÏÎµÎ¯Î±Ï‚ ÏƒÎ¿Ï….</p>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo h($candidate["specialty_title"] ?? "â€”"); ?></div>
                    <div class="stat-label">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $candidate["ranking_position"] !== null ? (int) $candidate["ranking_position"] : "â€”"; ?></div>
                    <div class="stat-label">Î˜Î­ÏƒÎ· ÏƒÏ„Î¿Î½ Ï€Î¯Î½Î±ÎºÎ±</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo $candidate["points"] !== null ? number_format((float) $candidate["points"], 2) : "â€”"; ?></div>
                    <div class="stat-label">ÎœÎ¿Î½Î¬Î´ÎµÏ‚</div>
                </div>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
                    <h3>Î£Ï„Î¿Î¹Ï‡ÎµÎ¯Î± Î±Î¯Ï„Î·ÏƒÎ·Ï‚</h3>
                    <div class="year-list">
                        <div class="year-item"><span>Î—Î»Î¹ÎºÎ¯Î±</span><strong><?php echo $candidateAge !== null ? $candidateAge . " ÎµÏ„ÏŽÎ½" : "Î”ÎµÎ½ Î¿ÏÎ¯ÏƒÏ„Î·ÎºÎµ"; ?></strong></div>
                        <div class="year-item"><span>ÎšÎ±Ï„Î¬ÏƒÏ„Î±ÏƒÎ· Î±Î¯Ï„Î·ÏƒÎ·Ï‚</span><strong><?php echo h($candidate["application_status"] ?? "Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡ÎµÎ¹ Î±ÎºÏŒÎ¼Î·"); ?></strong></div>
                        <div class="year-item"><span>Î¤ÎµÎ»ÎµÏ…Ï„Î±Î¯Î± ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ·</span><strong><?php echo !empty($candidate["updated_at"]) ? h(date("d/m/Y H:i", strtotime($candidate["updated_at"]))) : "Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡ÎµÎ¹"; ?></strong></div>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>ÎšÎµÎ¯Î¼ÎµÎ½Î¿ ÎºÎ±Î¹ timeline</h3>
                    <p class="muted"><?php echo h($applicationStage); ?></p>
                    <div class="progress-track" aria-label="Î ÏÏŒÎ¿Î´Î¿Ï‚ Î±Î¯Ï„Î·ÏƒÎ·Ï‚">
                        <div class="progress-value" style="width: <?php echo $applicationProgress; ?>%"></div>
                    </div>
                    <div class="year-list">
                        <div class="year-item"><span>1. Î ÏÎ¿Ï†Î¯Î» Ï‡ÏÎ®ÏƒÏ„Î·</span><strong><?php echo !empty($candidate["first_name"]) ? "ÎŸÎ»Î¿ÎºÎ»Î·ÏÏŽÎ¸Î·ÎºÎµ" : "Î£Îµ ÎµÎºÎºÏÎµÎ¼ÏŒÏ„Î·Ï„Î±"; ?></strong></div>
                        <div class="year-item"><span>2. Î£ÏÎ½Î´ÎµÏƒÎ· Î¼Îµ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</span><strong><?php echo !empty($candidate["specialty_title"]) ? "ÎŸÎ»Î¿ÎºÎ»Î·ÏÏŽÎ¸Î·ÎºÎµ" : "Î£Îµ ÎµÎºÎºÏÎµÎ¼ÏŒÏ„Î·Ï„Î±"; ?></strong></div>
                        <div class="year-item"><span>3. Î•Î½Ï„Î¿Ï€Î¹ÏƒÎ¼ÏŒÏ‚ ÏƒÏ„Î¿Î½ Ï€Î¯Î½Î±ÎºÎ±</span><strong><?php echo $candidate["ranking_position"] !== null ? "ÎŸÎ»Î¿ÎºÎ»Î·ÏÏŽÎ¸Î·ÎºÎµ" : "Î£Îµ ÎµÎºÎºÏÎµÎ¼ÏŒÏ„Î·Ï„Î±"; ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="panel panel-nested">
                <div class="panel-head">
                    <h3>Î£Ï…Î³ÎºÏÎ¹Ï„Î¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î±</h3>
                </div>
                <div class="year-list">
                    <div class="year-item"><span>ÎŸÎ½Î¿Î¼Î±Ï„ÎµÏ€ÏŽÎ½Ï…Î¼Î¿</span><strong><?php echo h($candidate["first_name"] . " " . $candidate["last_name"]); ?></strong></div>
                    <div class="year-item"><span>Email</span><strong><?php echo h($candidate["email"]); ?></strong></div>
                    <div class="year-item"><span>Î Î±ÏÎ±ÎºÎ¿Î»Î¿Ï…Î¸Î®ÏƒÎµÎ¹Ï‚ Î¬Î»Î»Ï‰Î½</span><strong><?php echo $myTrackCount; ?></strong></div>
                </div>
            </div>
        </section>

        <section class="panel" id="track-others" aria-labelledby="trackOthersTitle">
            <div class="panel-head">
                <h2 id="trackOthersTitle">Î Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ· Î†Î»Î»Ï‰Î½ Î¥Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½</h2>
                <p class="muted">Î‘Î½Î±Î¶Î®Ï„Î·ÏƒÎ· Î¬Î»Î»Ï‰Î½ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½ ÎºÎ±Î¹ Î±Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· ÏƒÏ„Î· Î´Î¹ÎºÎ® ÏƒÎ¿Ï… Î»Î¯ÏƒÏ„Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚.</p>
            </div>

            <form class="form-grid" method="get" action="#track-others">
                <div class="form-group">
                    <label for="search_name">ÎŸÎ½Î¿Î¼Î±Ï„ÎµÏ€ÏŽÎ½Ï…Î¼Î¿</label>
                    <input id="search_name" name="search_name" type="text" value="<?php echo h($searchName); ?>" placeholder="Ï€.Ï‡. Î Î±Ï€Î±Î´ÏŒÏ€Î¿Ï…Î»Î¿Ï‚ Î“Î¹ÏŽÏÎ³Î¿Ï‚">
                </div>

                <div class="form-group">
                    <label for="search_specialty_id">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</label>
                    <select id="search_specialty_id" name="search_specialty_id">
                        <option value="0">ÎŒÎ»ÎµÏ‚ Î¿Î¹ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„ÎµÏ‚</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $searchSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Î‘Î½Î±Î¶Î®Ï„Î·ÏƒÎ·</button>
                </div>
            </form>

            <div class="table-wrap" role="region" aria-label="Î‘Ï€Î¿Ï„ÎµÎ»Î­ÏƒÎ¼Î±Ï„Î± Î±Î½Î±Î¶Î®Ï„Î·ÏƒÎ·Ï‚ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Ï‚</th>
                            <th>Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</th>
                            <th>Î˜Î­ÏƒÎ·</th>
                            <th>ÎœÎ¿Î½Î¬Î´ÎµÏ‚</th>
                            <th>ÎšÎ±Ï„Î¬ÏƒÏ„Î±ÏƒÎ·</th>
                            <th class="right">Î•Î½Î­ÏÎ³ÎµÎ¹Î±</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($searchResults === []): ?>
                            <tr>
                                <td colspan="6" class="empty-cell">Î”ÎµÎ½ Î²ÏÎ­Î¸Î·ÎºÎ±Î½ Î¬Î»Î»Î¿Î¹ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Î¼Îµ Î±Ï…Ï„Î¬ Ï„Î± ÎºÏÎ¹Ï„Î®ÏÎ¹Î±.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($searchResults as $row): ?>
                                <tr>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["specialty_title"] ?? "â€”"); ?></td>
                                    <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "â€”"; ?></td>
                                    <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "â€”"; ?></td>
                                    <td><?php echo h($row["application_status"] ?? "â€”"); ?></td>
                                    <td class="right">
                                        <form method="post" action="#track-others">
                                            <input type="hidden" name="action" value="track_candidate">
                                            <input type="hidden" name="candidate_profile_id" value="<?php echo (int) $row["profile_id"]; ?>">
                                            <button class="btn btn-small" type="submit">Î Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·</button>
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
                    <h3>Î— Î»Î¯ÏƒÏ„Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ®Ï‚ Î¼Î¿Ï…</h3>
                </div>

                <div class="table-wrap" role="region" aria-label="Î›Î¯ÏƒÏ„Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Ï‚</th>
                                <th>Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</th>
                                <th>Î˜Î­ÏƒÎ·</th>
                                <th>ÎœÎ¿Î½Î¬Î´ÎµÏ‚</th>
                                <th>Î—Î¼ÎµÏÎ¿Î¼Î·Î½Î¯Î±</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($trackedRows === []): ?>
                                <tr>
                                    <td colspan="5" class="empty-cell">Î”ÎµÎ½ Î­Ï‡ÎµÎ¹Ï‚ Ï€ÏÎ¿ÏƒÎ¸Î­ÏƒÎµÎ¹ Î±ÎºÏŒÎ¼Î· Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï…Ï‚ ÏƒÏ„Î· Î»Î¯ÏƒÏ„Î± Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trackedRows as $row): ?>
                                    <tr>
                                        <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                        <td><?php echo h($row["specialty_title"] ?? "â€”"); ?></td>
                                        <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "â€”"; ?></td>
                                        <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "â€”"; ?></td>
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

