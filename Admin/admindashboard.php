<?php

session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
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
        ["Î‘Î½Î´ÏÎ­Î±Ï‚", "Î Î±Ï€Î±Î´ÏŒÏ€Î¿Ï…Î»Î¿Ï‚", "Î“Î¹ÏŽÏÎ³Î¿Ï‚", "ÎœÎ±ÏÎ¯Î±", "1989-04-17", 91.40],
        ["Î•Î»Î­Î½Î·", "Î§ÏÎ¹ÏƒÏ„Î¿Î´Î¿ÏÎ»Î¿Ï…", "ÎÎ¯ÎºÎ¿Ï‚", "Î†Î½Î½Î±", "1993-09-05", 88.20],
        ["ÎœÎ¬ÏÎ¹Î¿Ï‚", "ÎÎµÎ¿Ï†ÏÏ„Î¿Ï…", "ÎšÏŽÏƒÏ„Î±Ï‚", "Î£Ï„Î±Ï…ÏÎ¿ÏÎ»Î±", "1987-12-11", 84.75],
        ["Î£Î¿Ï†Î¯Î±", "Î™Ï‰Î¬Î½Î½Î¿Ï…", "ÎœÎ¹Ï‡Î¬Î»Î·Ï‚", "Î•Î»Î­Î½Î·", "1998-02-26", 93.10],
        ["Î Î­Ï„ÏÎ¿Ï‚", "Î£Ï„Ï…Î»Î¹Î±Î½Î¿Ï", "Î‘Î½Ï„ÏÎ­Î±Ï‚", "Î”Î­ÏƒÏ€Î¿Î¹Î½Î±", "1995-07-14", 86.60],
        ["Î§ÏÎ¹ÏƒÏ„Î¯Î½Î±", "ÎÎ¹ÎºÎ¿Î»Î¬Î¿Ï…", "Î£Î¬Î²Î²Î±Ï‚", "ÎšÎ±Ï„ÎµÏÎ¯Î½Î±", "1991-10-30", 89.35],
    ];

    shuffle($pool);
    $selected = array_slice($pool, 0, 4);

    foreach ($selected as $index => &$candidate) {
        $candidate["application_status"] = "Î¦Î¿ÏÏ„ÏŽÎ¸Î·ÎºÎµ Î±Ï€ÏŒ Î»Î¯ÏƒÏ„Î± " . $specialtyTitle;
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
    header("Location: ../auth/login.php");
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
            $errorMessage = "Î£Ï…Î¼Ï€Î»Î®ÏÏ‰ÏƒÎµ ÏŒÎ»Î± Ï„Î± Ï…Ï€Î¿Ï‡ÏÎµÏ‰Ï„Î¹ÎºÎ¬ Ï€ÎµÎ´Î¯Î± Î³Î¹Î± Î½Î­Î¿ Ï‡ÏÎ®ÏƒÏ„Î·.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Î¤Î¿ email Ï„Î¿Ï… Î½Î­Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Î­Î³ÎºÏ…ÏÎ¿.";
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "ÎŸ ÏÏŒÎ»Î¿Ï‚ Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Î­Î³ÎºÏ…ÏÎ¿Ï‚.";
        } elseif (strlen($password) < 8) {
            $errorMessage = "ÎŸ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Ï„Î¿Ï… Î½Î­Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Ï€ÏÎ­Ï€ÎµÎ¹ Î½Î± Î­Ï‡ÎµÎ¹ Ï„Î¿Ï…Î»Î¬Ï‡Î¹ÏƒÏ„Î¿Î½ 8 Ï‡Î±ÏÎ±ÎºÏ„Î®ÏÎµÏ‚.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Î¥Ï€Î¬ÏÏ‡ÎµÎ¹ Î®Î´Î· Ï‡ÏÎ®ÏƒÏ„Î·Ï‚ Î¼Îµ Î±Ï…Ï„ÏŒ Ï„Î¿ email.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");

                if ($createStmt) {
                    $createStmt->bind_param("ssssss", $firstName, $lastName, $email, $phone, $hashedPassword, $role);

                    if ($createStmt->execute()) {
                        $successMessage = "ÎŸ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚ Î´Î·Î¼Î¹Î¿Ï…ÏÎ³Î®Î¸Î·ÎºÎµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
                    } else {
                        $errorMessage = "Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· Î´Î·Î¼Î¹Î¿Ï…ÏÎ³Î¯Î± Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î·.";
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
            $errorMessage = "Î£Ï…Î¼Ï€Î»Î®ÏÏ‰ÏƒÎµ ÏƒÏ‰ÏƒÏ„Î¬ Ï„Î± ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î± ÎµÏ€ÎµÎ¾ÎµÏÎ³Î±ÏƒÎ¯Î±Ï‚ Ï‡ÏÎ®ÏƒÏ„Î·.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Î¤Î¿ email Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Î­Î³ÎºÏ…ÏÎ¿.";
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "ÎŸ ÏÏŒÎ»Î¿Ï‚ Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Î­Î³ÎºÏ…ÏÎ¿Ï‚.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("si", $email, $editUserId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Î¤Î¿ email Ï‡ÏÎ·ÏƒÎ¹Î¼Î¿Ï€Î¿Î¹ÎµÎ¯Ï„Î±Î¹ Î®Î´Î· Î±Ï€ÏŒ Î¬Î»Î»Î¿ Ï‡ÏÎ®ÏƒÏ„Î·.";
                }

                $checkStmt->close();
            }

            if ($errorMessage === "") {
                if ($password !== "") {
                    if (strlen($password) < 8) {
                        $errorMessage = "ÎŸ Î½Î­Î¿Ï‚ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Ï‡ÏÎ®ÏƒÏ„Î· Ï€ÏÎ­Ï€ÎµÎ¹ Î½Î± Î­Ï‡ÎµÎ¹ Ï„Î¿Ï…Î»Î¬Ï‡Î¹ÏƒÏ„Î¿Î½ 8 Ï‡Î±ÏÎ±ÎºÏ„Î®ÏÎµÏ‚.";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?");

                        if ($updateStmt) {
                            $updateStmt->bind_param("ssssssi", $firstName, $lastName, $email, $phone, $role, $hashedPassword, $editUserId);

                            if ($updateStmt->execute()) {
                                $successMessage = "ÎŸ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚ ÎµÎ½Î·Î¼ÎµÏÏŽÎ¸Î·ÎºÎµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
                            } else {
                                $errorMessage = "Î— ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ· Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î±Ï€Î­Ï„Ï…Ï‡Îµ.";
                            }

                            $updateStmt->close();
                        }
                    }
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ? WHERE id = ?");

                    if ($updateStmt) {
                        $updateStmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $role, $editUserId);

                        if ($updateStmt->execute()) {
                            $successMessage = "ÎŸ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚ ÎµÎ½Î·Î¼ÎµÏÏŽÎ¸Î·ÎºÎµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
                        } else {
                            $errorMessage = "Î— ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ· Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î±Ï€Î­Ï„Ï…Ï‡Îµ.";
                        }

                        $updateStmt->close();
                    }
                }
            }
        }
    } elseif ($action === "delete_user") {
        $deleteUserId = (int) ($_POST["delete_user_id"] ?? 0);

        if ($deleteUserId <= 0) {
            $errorMessage = "Î”ÎµÎ½ ÎµÏ€Î¹Î»Î­Ï‡Î¸Î·ÎºÎµ Î­Î³ÎºÏ…ÏÎ¿Ï‚ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚ Î³Î¹Î± Î´Î¹Î±Î³ÏÎ±Ï†Î®.";
        } elseif ($deleteUserId === (int) $_SESSION["user_id"]) {
            $errorMessage = "Î”ÎµÎ½ Î¼Ï€Î¿ÏÎµÎ¯Ï‚ Î½Î± Î´Î¹Î±Î³ÏÎ¬ÏˆÎµÎ¹Ï‚ Ï„Î¿Î½ Î´Î¹ÎºÏŒ ÏƒÎ¿Ï… admin Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒ.";
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
                        throw new RuntimeException("Î— Î´Î¹Î±Î³ÏÎ±Ï†Î® Ï„Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î· Î±Ï€Î­Ï„Ï…Ï‡Îµ.");
                    }

                    $deleteUserStmt->close();
                }

                $conn->commit();
                $successMessage = "ÎŸ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚ Î´Î¹Î±Î³ÏÎ¬Ï†Î·ÎºÎµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
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
            $errorMessage = "Î•Ï€Î¯Î»ÎµÎ¾Îµ Î­Î³ÎºÏ…ÏÎ· ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± Î³Î¹Î± Ï†ÏŒÏÏ„Ï‰ÏƒÎ· Ï€Î¯Î½Î±ÎºÎ±.";
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
                    throw new RuntimeException("Î”ÎµÎ½ Î®Ï„Î±Î½ Î´Ï…Î½Î±Ï„Î® Î· Ï€ÏÎ¿ÎµÏ„Î¿Î¹Î¼Î±ÏƒÎ¯Î± Ï„Î·Ï‚ Ï†ÏŒÏÏ„Ï‰ÏƒÎ·Ï‚.");
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
                        throw new RuntimeException("Î‘Ï€Î¿Ï„Ï…Ï‡Î¯Î± Î´Î·Î¼Î¹Î¿Ï…ÏÎ³Î¯Î±Ï‚ demo Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï….");
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
                        throw new RuntimeException("Î‘Ï€Î¿Ï„Ï…Ï‡Î¯Î± ÎºÎ±Ï„Î±Ï‡ÏŽÏÎ¹ÏƒÎ·Ï‚ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï….");
                    }
                }

                $userStmt->close();
                $profileStmt->close();
                $conn->commit();
                $successMessage = "Î¦Î¿ÏÏ„ÏŽÎ¸Î·ÎºÎ±Î½ 4 demo Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Î³Î¹Î± Ï„Î·Î½ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± " . $selectedSpecialty["title"] . " Î³Î¹Î± Ï„Î¿ Î­Ï„Î¿Ï‚ " . $loadYear . ".";
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
            $errorMessage = "Î£Ï…Î¼Ï€Î»Î®ÏÏ‰ÏƒÎµ ÏŒÎ½Î¿Î¼Î±, ÎµÏ€ÏŽÎ½Ï…Î¼Î¿ ÎºÎ±Î¹ email.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Î¤Î¿ email Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Î­Î³ÎºÏ…ÏÎ¿.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");

            if ($checkStmt) {
                $checkStmt->bind_param("si", $email, $_SESSION["user_id"]);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $errorMessage = "Î¤Î¿ email Ï‡ÏÎ·ÏƒÎ¹Î¼Î¿Ï€Î¿Î¹ÎµÎ¯Ï„Î±Î¹ Î®Î´Î· Î±Ï€ÏŒ Î¬Î»Î»Î¿ Ï‡ÏÎ®ÏƒÏ„Î·.";
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
                        $successMessage = "Î¤Î± Î²Î±ÏƒÎ¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î± Ï„Î¿Ï… admin ÎµÎ½Î·Î¼ÎµÏÏŽÎ¸Î·ÎºÎ±Î½.";
                    } else {
                        $errorMessage = "Î”ÎµÎ½ Î­Î³Î¹Î½Îµ ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ· Ï„Ï‰Î½ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½.";
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
            $errorMessage = "Î£Ï…Î¼Ï€Î»Î®ÏÏ‰ÏƒÎµ ÏŒÎ»Î± Ï„Î± Ï€ÎµÎ´Î¯Î± Ï„Î¿Ï… ÎºÏ‰Î´Î¹ÎºÎ¿Ï.";
        } elseif (!password_verify($currentPassword, $adminUser["password"])) {
            $errorMessage = "ÎŸ Ï„ÏÎ­Ï‡Ï‰Î½ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ ÏƒÏ‰ÏƒÏ„ÏŒÏ‚.";
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = "ÎŸ Î½Î­Î¿Ï‚ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Ï€ÏÎ­Ï€ÎµÎ¹ Î½Î± Î­Ï‡ÎµÎ¹ Ï„Î¿Ï…Î»Î¬Ï‡Î¹ÏƒÏ„Î¿Î½ 8 Ï‡Î±ÏÎ±ÎºÏ„Î®ÏÎµÏ‚.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "Î— ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ· Ï„Î¿Ï… Î½Î­Î¿Ï… ÎºÏ‰Î´Î¹ÎºÎ¿Ï Î´ÎµÎ½ Ï„Î±Î¹ÏÎ¹Î¬Î¶ÎµÎ¹.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

            if ($passwordStmt) {
                $passwordStmt->bind_param("si", $hashedPassword, $_SESSION["user_id"]);

                if ($passwordStmt->execute()) {
                    $successMessage = "ÎŸ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚ Ï€ÏÏŒÏƒÎ²Î±ÏƒÎ·Ï‚ Î¬Î»Î»Î±Î¾Îµ ÎµÏ€Î¹Ï„Ï…Ï‡ÏŽÏ‚.";
                    $adminUser["password"] = $hashedPassword;
                } else {
                    $errorMessage = "Î— Î±Î»Î»Î±Î³Î® ÎºÏ‰Î´Î¹ÎºÎ¿Ï Î±Ï€Î­Ï„Ï…Ï‡Îµ.";
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
$headerActionLabel = "Î‘Î»Î»Î±Î³Î® ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½";
$headerActionHref = "#account";

require __DIR__ . "/../includes/header.php";

?>
    <main class="container">
        <section class="page-hero" aria-labelledby="pageTitle">
            <div class="hero-text">
                <h1 id="pageTitle">Admin Dashboard</h1>
                <p class="muted">
                    Î”Î¹Î±Ï‡ÎµÎ¯ÏÎ¹ÏƒÎ· Ï€Î¹Î½Î¬ÎºÏ‰Î½ Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±, Ï€ÏÎ¿Î²Î¿Î»Î® Î±Î½Î±Ï†Î¿ÏÏŽÎ½ ÎºÎ±Î¹ ÎµÎ½Î·Î¼Î­ÏÏ‰ÏƒÎ· Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï admin
                    ÏƒÎµ Î¼Î¯Î± ÏƒÎµÎ»Î¯Î´Î±, ÏŒÏ€Ï‰Ï‚ Î¶Î·Ï„Î¬ Î· ÎµÏÎ³Î±ÏƒÎ¯Î±.
                </p>
            </div>

            <div class="hero-badges" aria-label="Î£Ï„Î¿Î¹Ï‡ÎµÎ¯Î± Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï admin">
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

        <section class="grid grid-admin" aria-label="Î•Î½ÏŒÏ„Î·Ï„ÎµÏ‚ ÎµÏÎ³Î±ÏƒÎ¯Î±Ï‚">
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">1</div>
                <h2>Manage Users</h2>
                <p>Î ÏÎ¿Î²Î¿Î»Î® ÏŒÎ»Ï‰Î½ Ï„Ï‰Î½ Ï‡ÏÎ·ÏƒÏ„ÏŽÎ½ ÎºÎ±Î¹ Ï€Î»Î®ÏÎ·Ï‚ Î´Î¹Î±Ï‡ÎµÎ¯ÏÎ¹ÏƒÎ· Î¼Îµ Î´Î·Î¼Î¹Î¿Ï…ÏÎ³Î¯Î±, ÎµÏ€ÎµÎ¾ÎµÏÎ³Î±ÏƒÎ¯Î± ÎºÎ±Î¹ Î´Î¹Î±Î³ÏÎ±Ï†Î®.</p>
                <div class="card-actions">
                    <a class="btn" href="#manage-users">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
                <h2>Manage Lists</h2>
                <p>Î•Ï€Î¹Î»Î¿Î³Î® ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±Ï‚ ÎºÎ±Î¹ Ï†ÏŒÏÏ„Ï‰ÏƒÎ· Î´Î¹Î±Î¸Î­ÏƒÎ¹Î¼Ï‰Î½ Ï€Î¹Î½Î¬ÎºÏ‰Î½ Î¼Îµ Î­Ï„Î¿Î¹Î¼Î· demo ÎºÎ±Ï„Î±Ï‡ÏŽÏÎ¹ÏƒÎ· Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½.</p>
                <div class="card-actions">
                    <a class="btn" href="#manage-lists">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
                <h2>Reports</h2>
                <p>Î£Ï…Î³ÎºÎµÎ½Ï„ÏÏ‰Ï„Î¹ÎºÎ¬ ÏƒÏ„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÎ¬ Î¼Îµ Ï€Î»Î®Î¸Î¿Ï‚ Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½, Î¼Î­ÏƒÎ· Î·Î»Î¹ÎºÎ¯Î± ÎºÎ±Î¹ Î³ÏÎ±Ï†Î¹ÎºÎ® Î±Ï€ÎµÎ¹ÎºÏŒÎ½Î¹ÏƒÎ· Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±.</p>
                <div class="card-actions">
                    <a class="btn" href="#reports">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>

            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">4</div>
                <h2>Account</h2>
                <p>Î‘Î»Î»Î±Î³Î® Î²Î±ÏƒÎ¹ÎºÏŽÎ½ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½ ÎºÎ±Î¹ ÎºÏ‰Î´Î¹ÎºÎ¿Ï Ï„Î¿Ï… admin Î¼Î­ÏƒÎ± Î±Ï€ÏŒ Î±ÏƒÏ†Î±Î»ÎµÎ¯Ï‚ Ï†ÏŒÏÎ¼ÎµÏ‚.</p>
                <div class="card-actions">
                    <a class="btn" href="#account">Î†Î½Î¿Î¹Î³Î¼Î±</a>
                </div>
            </article>
        </section>

        <section class="panel" id="manage-users" aria-labelledby="usersTitle">
            <div class="panel-head">
                <h2 id="usersTitle">Manage Users</h2>
                <p class="muted">Î•Î´ÏŽ Î¿ admin Î²Î»Î­Ï€ÎµÎ¹ ÏŒÎ»Î¿Ï…Ï‚ Ï„Î¿Ï…Ï‚ Ï‡ÏÎ®ÏƒÏ„ÎµÏ‚ ÎºÎ±Î¹ Î¼Ï€Î¿ÏÎµÎ¯ Î½Î± ÎºÎ¬Î½ÎµÎ¹ add, update ÎºÎ±Î¹ remove.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="#manage-users">
                    <input type="hidden" name="action" value="create_user">
                    <h3>Î ÏÎ¿ÏƒÎ¸Î®ÎºÎ· Î½Î­Î¿Ï… Ï‡ÏÎ®ÏƒÏ„Î·</h3>

                    <div class="form-stack">
                        <div class="form-group">
                            <label for="new_first_name">ÎŒÎ½Î¿Î¼Î±</label>
                            <input id="new_first_name" name="new_first_name" type="text" required>
                        </div>

                        <div class="form-group">
                            <label for="new_last_name">Î•Ï€ÏŽÎ½Ï…Î¼Î¿</label>
                            <input id="new_last_name" name="new_last_name" type="text" required>
                        </div>

                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input id="new_email" name="new_email" type="email" required>
                        </div>

                        <div class="form-group">
                            <label for="new_phone">Î¤Î·Î»Î­Ï†Ï‰Î½Î¿</label>
                            <input id="new_phone" name="new_phone" type="text">
                        </div>

                        <div class="form-group">
                            <label for="new_role">Î¡ÏŒÎ»Î¿Ï‚</label>
                            <select id="new_role" name="new_role" required>
                                <option value="candidate">candidate</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="new_password">ÎšÏ‰Î´Î¹ÎºÏŒÏ‚</label>
                            <input id="new_password" name="new_password" type="password" required>
                        </div>
                    </div>

                    <button class="btn" type="submit">Î”Î·Î¼Î¹Î¿Ï…ÏÎ³Î¯Î± Î§ÏÎ®ÏƒÏ„Î·</button>
                </form>

                <form class="panel panel-nested" method="post" action="#manage-users">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="edit_user_id" value="<?php echo (int) ($editingUser["id"] ?? 0); ?>">
                    <h3>Î•Ï€ÎµÎ¾ÎµÏÎ³Î±ÏƒÎ¯Î± Ï‡ÏÎ®ÏƒÏ„Î·</h3>

                    <?php if ($editingUser): ?>
                        <div class="form-stack">
                            <div class="form-group">
                                <label for="edit_first_name">ÎŒÎ½Î¿Î¼Î±</label>
                                <input id="edit_first_name" name="edit_first_name" type="text" value="<?php echo h($editingUser["first_name"]); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_last_name">Î•Ï€ÏŽÎ½Ï…Î¼Î¿</label>
                                <input id="edit_last_name" name="edit_last_name" type="text" value="<?php echo h($editingUser["last_name"]); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input id="edit_email" name="edit_email" type="email" value="<?php echo h($editingUser["email"]); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_phone">Î¤Î·Î»Î­Ï†Ï‰Î½Î¿</label>
                                <input id="edit_phone" name="edit_phone" type="text" value="<?php echo h($editingUser["phone"] ?? ""); ?>">
                            </div>

                            <div class="form-group">
                                <label for="edit_role">Î¡ÏŒÎ»Î¿Ï‚</label>
                                <select id="edit_role" name="edit_role" required>
                                    <option value="candidate" <?php echo ($editingUser["role"] ?? "") === "candidate" ? "selected" : ""; ?>>candidate</option>
                                    <option value="admin" <?php echo ($editingUser["role"] ?? "") === "admin" ? "selected" : ""; ?>>admin</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="edit_password">ÎÎ­Î¿Ï‚ ÎºÏ‰Î´Î¹ÎºÏŒÏ‚</label>
                                <input id="edit_password" name="edit_password" type="password" placeholder="Î†Ï†Î·ÏƒÎ­ Ï„Î¿ ÎºÎµÎ½ÏŒ Î±Î½ Î´ÎµÎ½ Î±Î»Î»Î¬Î¶ÎµÎ¹">
                            </div>
                        </div>

                        <button class="btn" type="submit">Î‘Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· Î‘Î»Î»Î±Î³ÏŽÎ½</button>
                    <?php else: ?>
                        <p class="muted empty-copy">Î•Ï€Î¯Î»ÎµÎ¾Îµ Ï€ÏÏŽÏ„Î± Î­Î½Î±Î½ Ï‡ÏÎ®ÏƒÏ„Î· Î±Ï€ÏŒ Ï„Î¿Î½ Ï€Î¯Î½Î±ÎºÎ± Î³Î¹Î± ÎµÏ€ÎµÎ¾ÎµÏÎ³Î±ÏƒÎ¯Î±.</p>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap" role="region" aria-label="Î›Î¯ÏƒÏ„Î± Ï‡ÏÎ·ÏƒÏ„ÏŽÎ½">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ÎŒÎ½Î¿Î¼Î±</th>
                            <th>Email</th>
                            <th>Î¡ÏŒÎ»Î¿Ï‚</th>
                            <th>Î¤Î·Î»Î­Ï†Ï‰Î½Î¿</th>
                            <th>Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</th>
                            <th>Î•Î³Î³ÏÎ±Ï†Î®</th>
                            <th class="right">Î•Î½Î­ÏÎ³ÎµÎ¹ÎµÏ‚</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users === []): ?>
                            <tr>
                                <td colspan="7" class="empty-cell">Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡Î¿Ï…Î½ Ï‡ÏÎ®ÏƒÏ„ÎµÏ‚ ÏƒÏ„Î· Î²Î¬ÏƒÎ·.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td><?php echo h($row["first_name"] . " " . $row["last_name"]); ?></td>
                                    <td><?php echo h($row["email"]); ?></td>
                                    <td><span class="pill <?php echo $row["role"] === "admin" ? "pill-admin" : "pill-user"; ?>"><?php echo h($row["role"]); ?></span></td>
                                    <td><?php echo h($row["phone"] ?? "â€”"); ?></td>
                                    <td><?php echo h($row["specialty_title"] ?? "â€”"); ?></td>
                                    <td><?php echo h(date("d/m/Y", strtotime($row["created_at"]))); ?></td>
                                    <td class="right">
                                        <div class="inline-actions">
                                            <a class="btn btn-small" href="?edit_user=<?php echo (int) $row["id"]; ?>#manage-users">Edit</a>
                                            <?php if ((int) $row["id"] !== (int) $_SESSION["user_id"]): ?>
                                                <form method="post" action="#manage-users" onsubmit="return confirm('ÎÎ± Î´Î¹Î±Î³ÏÎ±Ï†ÎµÎ¯ Î¿ Ï‡ÏÎ®ÏƒÏ„Î·Ï‚;');">
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
                    ÎŸ admin ÎµÏ€Î¹Î»Î­Î³ÎµÎ¹ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î± ÎºÎ±Î¹ Ï†Î¿ÏÏ„ÏŽÎ½ÎµÎ¹ Ï€Î¯Î½Î±ÎºÎ±. Î“Î¹Î± Î½Î± Î»ÎµÎ¹Ï„Î¿Ï…ÏÎ³ÎµÎ¯ Î¬Î¼ÎµÏƒÎ± ÏƒÏ„Î¿ project,
                    Î· Ï†ÏŒÏÏ„Ï‰ÏƒÎ· Ï€ÏÎ¿ÏƒÎ¸Î­Ï„ÎµÎ¹ demo Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï…Ï‚ ÏƒÏ„Î· Î²Î¬ÏƒÎ·.
                </p>
                <p class="muted">
                    Î— ÎµÎ½ÏŒÏ„Î·Ï„Î± ÎµÎ¯Î½Î±Î¹ ÏƒÏ‡ÎµÎ´Î¹Î±ÏƒÎ¼Î­Î½Î· ÏŽÏƒÏ„Îµ Î½Î± ÎµÎ¾Î·Î³ÎµÎ¯ ÏƒÏ„Î¿Î½ Ï‡ÏÎ®ÏƒÏ„Î· ÏŒÏ„Î¹ Ï„Î± Î´ÎµÎ´Î¿Î¼Î­Î½Î± Î±Î½Ï„Î¹ÏƒÏ„Î¿Î¹Ï‡Î¿ÏÎ½
                    ÏƒÎµ Ï€Î¯Î½Î±ÎºÎµÏ‚ Î´Î¹Î¿ÏÎ¹ÏƒÏ„Î­Ï‰Î½ ÎºÎ±Î¹ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„ÎµÏ‚ ÏŒÏ€Ï‰Ï‚ ÎµÎ¼Ï†Î±Î½Î¯Î¶Î¿Î½Ï„Î±Î¹ Î±Ï€ÏŒ Ï„Î·Î½ Î•Î•Î¥.
                </p>
            </div>

            <form class="form-grid" method="post" action="#manage-lists">
                <input type="hidden" name="action" value="load_list">

                <div class="form-group">
                    <label for="specialty_id">Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</label>
                    <select id="specialty_id" name="specialty_id" required>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $selectedSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h($specialty["title"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="load_year">ÎˆÏ„Î¿Ï‚</label>
                    <select id="load_year" name="load_year" required>
                        <?php for ($year = (int) date("Y"); $year >= (int) date("Y") - 3; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $selectedLoadYear === $year ? "selected" : ""; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn" type="submit">Î¦ÏŒÏÏ„Ï‰ÏƒÎ· Î Î¯Î½Î±ÎºÎ±</button>
                </div>
            </form>

            <div class="table-wrap" role="region" aria-label="Î Î¯Î½Î±ÎºÎµÏ‚ Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Î•Î¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</th>
                            <th>Î ÎµÏÎ¹Î³ÏÎ±Ï†Î®</th>
                            <th>Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹</th>
                            <th>Îœ.ÎŸ. Î—Î»Î¹ÎºÎ¯Î±Ï‚</th>
                            <th>Î¤ÎµÎ»ÎµÏ…Ï„Î±Î¯Î± Ï†ÏŒÏÏ„Ï‰ÏƒÎ·</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($specialtyStats === []): ?>
                            <tr>
                                <td colspan="5" class="empty-cell">Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡Î¿Ï…Î½ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„ÎµÏ‚ ÏƒÏ„Î· Î²Î¬ÏƒÎ·.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($specialtyStats as $row): ?>
                                <tr>
                                    <td><?php echo h($row["title"]); ?></td>
                                    <td><?php echo h($row["description"] ?? ""); ?></td>
                                    <td><?php echo (int) $row["candidate_count"]; ?></td>
                                    <td><?php echo $row["average_age"] !== null ? number_format((float) $row["average_age"], 1) : "-"; ?></td>
                                    <td><?php echo $row["last_loaded"] ? h(date("d/m/Y H:i", strtotime($row["last_loaded"]))) : "Î”ÎµÎ½ Î­Î³Î¹Î½Îµ Ï†ÏŒÏÏ„Ï‰ÏƒÎ·"; ?></td>
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
                <p class="muted">Î£Ï„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î± ÎºÎ±Î¹ dashboard Î¼Îµ ÏƒÏ…Î½Î¿Î»Î¹ÎºÎ® ÎµÎ¹ÎºÏŒÎ½Î± Ï„Ï‰Î½ Ï€Î¹Î½Î¬ÎºÏ‰Î½.</p>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) $overview["total_candidates"]; ?></div>
                    <div class="stat-label">Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„ÎµÏ‚</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi">
                        <?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : "-"; ?>
                    </div>
                    <div class="stat-label">ÎœÎ­ÏƒÎ¿Ï‚ ÏŒÏÎ¿Ï‚ Î·Î»Î¹ÎºÎ¯Î±Ï‚</div>
                </div>
                <div class="stat">
                    <div class="stat-kpi"><?php echo (int) $overview["new_candidates_year"]; ?></div>
                    <div class="stat-label">ÎÎ­Î¿Î¹ Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Ï„Î¿ <?php echo date("Y"); ?></div>
                </div>
            </div>

            <div class="reports-layout">
                <div class="chart-card">
                    <h3>Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±</h3>

                    <?php if ($specialtyStats === [] || $maxSpecialtyCount === 0): ?>
                        <p class="muted empty-copy">Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡Î¿Ï…Î½ Î±ÎºÏŒÎ¼Î· Î´ÎµÎ´Î¿Î¼Î­Î½Î± Î³Î¹Î± Î³ÏÎ¬Ï†Î·Î¼Î±. Î¦ÏŒÏÏ„Ï‰ÏƒÎµ Ï€ÏÏŽÏ„Î± Î¼Î¯Î± Î»Î¯ÏƒÏ„Î±.</p>
                    <?php else: ?>
                        <div class="chart-mock" aria-label="Î“ÏÎ¬Ï†Î·Î¼Î± Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½ Î±Î½Î¬ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±">
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
                    <h3>Î¥Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Î±Î½Î¬ Î­Ï„Î¿Ï‚</h3>

                    <?php if ($yearlyRows === []): ?>
                        <p class="muted empty-copy">Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡Î¿Ï…Î½ Î±ÎºÏŒÎ¼Î· Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹ Î³Î¹Î± ÎµÏ„Î®ÏƒÎ¹Î± Î±Î½Î±Ï†Î¿ÏÎ¬.</p>
                    <?php else: ?>
                        <div class="year-list">
                            <?php foreach ($yearlyRows as $yearRow): ?>
                                <div class="year-item">
                                    <span><?php echo h((string) $yearRow["report_year"]); ?></span>
                                    <strong><?php echo (int) $yearRow["candidate_count"]; ?> Ï…Ï€Î¿ÏˆÎ®Ï†Î¹Î¿Î¹</strong>
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
                <h2 id="accountTitle">Î£Ï„Î¿Î¹Ï‡ÎµÎ¯Î± Admin ÎºÎ±Î¹ ÎšÏ‰Î´Î¹ÎºÏŒÏ‚</h2>
                <p class="muted">Î•Î½Î·Î¼Î­ÏÏ‰ÏƒÎ· Î²Î±ÏƒÎ¹ÎºÏŽÎ½ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½ ÎºÎ±Î¹ Î±Î»Î»Î±Î³Î® password Î±Ï€ÏŒ Ï„Î¿ Î¯Î´Î¹Î¿ dashboard.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="#account">
                    <input type="hidden" name="action" value="update_profile">
                    <h3>Î’Î±ÏƒÎ¹ÎºÎ¬ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Î±</h3>

                    <div class="form-stack">
                        <div class="form-group">
                            <label for="first_name">ÎŒÎ½Î¿Î¼Î±</label>
                            <input id="first_name" name="first_name" type="text" value="<?php echo h($adminUser["first_name"]); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Î•Ï€ÏŽÎ½Ï…Î¼Î¿</label>
                            <input id="last_name" name="last_name" type="text" value="<?php echo h($adminUser["last_name"]); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="<?php echo h($adminUser["email"]); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Î¤Î·Î»Î­Ï†Ï‰Î½Î¿</label>
                            <input id="phone" name="phone" type="text" value="<?php echo h($adminUser["phone"] ?? ""); ?>">
                        </div>
                    </div>

                    <button class="btn" type="submit">Î‘Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· Î£Ï„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½</button>
                </form>

                <form class="panel panel-nested" method="post" action="#account">
                    <input type="hidden" name="action" value="change_password">
                    <h3>Î‘Î»Î»Î±Î³Î® ÎºÏ‰Î´Î¹ÎºÎ¿Ï</h3>

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
    </main>

<?php require __DIR__ . "/../includes/footer.php"; ?>

