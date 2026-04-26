<?php

session_start();
require_once __DIR__ . "/../../includes/auth.php";

require_role("admin", "../../auth/login.php", "admindashboard.php", "../candidate/candidatedashboard.php");

require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/functions.php";

ensure_user_profiles_table($conn);

function normalize_import_header(string $header): string
{
    $header = trim($header);
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = str_replace([' ', '-', '.', '/', '\\'], '_', $header);

    if (function_exists('mb_strtolower')) {
        $header = mb_strtolower($header, 'UTF-8');
    } else {
        $header = strtolower($header);
    }

    $header = strtr($header, [
        'ά' => 'α',
        'έ' => 'ε',
        'ή' => 'η',
        'ί' => 'ι',
        'ϊ' => 'ι',
        'ΐ' => 'ι',
        'ό' => 'ο',
        'ύ' => 'υ',
        'ϋ' => 'υ',
        'ΰ' => 'υ',
        'ώ' => 'ω',
    ]);

    return trim($header, "_");
}

function import_header_key(string $header): ?string
{
    $header = normalize_import_header($header);
    $map = [
        'first_name' => 'first_name',
        'firstname' => 'first_name',
        'name' => 'first_name',
        'onoma' => 'first_name',
        'last_name' => 'last_name',
        'lastname' => 'last_name',
        'surname' => 'last_name',
        'eponymo' => 'last_name',
        'father_name' => 'father_name',
        'father' => 'father_name',
        'patronymo' => 'father_name',
        'mother_name' => 'mother_name',
        'mother' => 'mother_name',
        'mitronymo' => 'mother_name',
        'birth_date' => 'birth_date',
        'birthdate' => 'birth_date',
        'date_of_birth' => 'birth_date',
        'identity_number' => 'identity_number',
        'identity' => 'identity_number',
        'id_number' => 'identity_number',
        'adt' => 'identity_number',
        'email' => 'email',
        'phone' => 'phone',
        'telephone' => 'phone',
        'ranking_position' => 'ranking_position',
        'rank' => 'ranking_position',
        'position' => 'ranking_position',
        'points' => 'points',
        'score' => 'points',
        'moria' => 'points',
        'application_status' => 'application_status',
        'status' => 'application_status',
    ];

    if (isset($map[$header])) {
        return $map[$header];
    }

    if (str_contains($header, 'ονομα') && !str_contains($header, 'πατρ') && !str_contains($header, 'μητρ')) {
        return 'first_name';
    }
    if (str_contains($header, 'επωνυμ')) {
        return 'last_name';
    }
    if (str_contains($header, 'πατρ')) {
        return 'father_name';
    }
    if (str_contains($header, 'μητρ')) {
        return 'mother_name';
    }
    if (str_contains($header, 'γενν')) {
        return 'birth_date';
    }
    if (str_contains($header, 'ταυτοτ') || str_contains($header, 'αδτ')) {
        return 'identity_number';
    }
    if (str_contains($header, 'τηλ')) {
        return 'phone';
    }
    if (str_contains($header, 'θεση') || str_contains($header, 'σειρα')) {
        return 'ranking_position';
    }
    if (str_contains($header, 'μορι') || str_contains($header, 'βαθμ')) {
        return 'points';
    }
    if (str_contains($header, 'καταστ')) {
        return 'application_status';
    }

    return null;
}

function normalize_import_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
}

function normalize_import_decimal(?string $value): ?float
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : null;
}

function generated_import_email($conn, string $firstName, string $lastName, ?string $identityNumber): string
{
    $base = $identityNumber !== null && $identityNumber !== ''
        ? 'candidate.' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $identityNumber))
        : username_from_email($firstName . '.' . $lastName . '@pinakes.local');

    return generate_unique_username($conn, $base) . '@pinakes.local';
}

function load_candidates_from_csv(string $filePath): array
{
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return [];
    }

    $firstLine = fgets($handle);
    if (!is_string($firstLine)) {
        fclose($handle);
        return [];
    }

    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    $headers = fgetcsv($handle, 10000, $delimiter);
    if (!is_array($headers)) {
        fclose($handle);
        return [];
    }

    $keys = [];
    foreach ($headers as $index => $header) {
        $key = import_header_key((string) $header);
        if ($key !== null) {
            $keys[$index] = $key;
        }
    }

    $rows = [];
    while (($data = fgetcsv($handle, 10000, $delimiter)) !== false) {
        if (!is_array($data)) {
            continue;
        }

        $row = [];
        foreach ($keys as $index => $key) {
            $row[$key] = trim((string) ($data[$index] ?? ''));
        }

        if (($row['first_name'] ?? '') !== '' && ($row['last_name'] ?? '') !== '') {
            $rows[] = $row;
        }
    }

    fclose($handle);
    return $rows;
}

$successMessage = "";
$errorMessage = "";
$createUserForm = [
    "first_name" => "",
    "last_name" => "",
    "email" => "",
    "identity_number" => "",
    "phone" => "",
    "role" => "candidate",
    "specialty_id" => 0,
];

$specialties = fetch_all_prepared(
    $conn,
    "SELECT id, title, description FROM specialties ORDER BY title ASC"
);

$adminUser = null;
$adminStmt = $conn->prepare("SELECT u.id, u.username, up.first_name, up.last_name, u.email, up.identity_number, up.phone, u.password_hash, u.created_at FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ? LIMIT 1");

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
        $newSpecialtyId = (int) ($_POST["new_specialty_id"] ?? 0);
        $password = $_POST["new_password"] ?? "";
        $validSpecialtyIds = array_map(static fn ($specialty) => (int) $specialty["id"], $specialties);
        $createUserForm = [
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $email,
            "identity_number" => $identityNumber,
            "phone" => $phone,
            "role" => in_array($role, ["admin", "candidate"], true) ? $role : "candidate",
            "specialty_id" => in_array($newSpecialtyId, $validSpecialtyIds, true) ? $newSpecialtyId : 0,
        ];

        if ($firstName === "" || $lastName === "" || $email === "" || $identityNumber === "" || $password === "") {
            $errorMessage = "Συμπλήρωσε όλα τα υποχρεωτικά πεδία για νέο χρήστη.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Το email δεν είναι σε έγκυρη μορφή.";
        } elseif (!is_valid_identity_number($identityNumber)) {
            $errorMessage = identity_number_validation_message();
        } elseif (!in_array($role, ["admin", "candidate"], true)) {
            $errorMessage = "Ο ρόλος που επιλέχθηκε δεν είναι έγκυρος.";
        } elseif ($role === "candidate" && $newSpecialtyId > 0 && !in_array($newSpecialtyId, $validSpecialtyIds, true)) {
            $errorMessage = u('\u0395\u03c0\u03af\u03bb\u03b5\u03be\u03b5 \u03ad\u03b3\u03ba\u03c5\u03c1\u03b7 \u03b5\u03b9\u03b4\u03b9\u03ba\u03cc\u03c4\u03b7\u03c4\u03b1 \u03b3\u03b9\u03b1 \u03c4\u03bf\u03bd \u03c5\u03c0\u03bf\u03c8\u03ae\u03c6\u03b9\u03bf.');
        } elseif (strlen($password) < 8) {
            $errorMessage = "Ο κωδικός του νέου χρήστη πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
        } else {
            $checkStmt = $conn->prepare("SELECT u.id FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.email = ? OR up.identity_number = ? LIMIT 1");

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
                $createStmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");

                if ($createStmt) {
                    $conn->begin_transaction();
                    try {
                        $createStmt->bind_param("ssss", $username, $email, $hashedPassword, $role);
                        if (!$createStmt->execute()) {
                            throw new RuntimeException("??? ???? ?????? ? ?????????? ??? ??????.");
                        }

                        $newUserId = (int) $conn->insert_id;
                        $createStmt->close();

                        $profileStmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone) VALUES (?, ?, ?, ?, ?)");
                        if (!$profileStmt) {
                            throw new RuntimeException("??? ???? ?????? ? ?????????? ?????? ??????.");
                        }

                        $profileStmt->bind_param("issss", $newUserId, $firstName, $lastName, $identityNumber, $phone);
                        if (!$profileStmt->execute()) {
                            throw new RuntimeException("??? ???? ?????? ? ?????????? ??? ????????? ??????.");
                        }

                        $profileStmt->close();

                        if ($role === "candidate") {
                            $candidateSpecialtyId = $newSpecialtyId > 0 ? $newSpecialtyId : null;
                            $candidateStatus = u('\u039d\u03ad\u03b1 \u03b5\u03b3\u03b3\u03c1\u03b1\u03c6\u03ae');
                            $candidateStmt = $conn->prepare("INSERT INTO candidate_profiles (user_id, specialty_id, application_status) VALUES (?, ?, ?)");

                            if (!$candidateStmt) {
                                throw new RuntimeException("Could not prepare candidate profile.");
                            }

                            $candidateStmt->bind_param("iis", $newUserId, $candidateSpecialtyId, $candidateStatus);
                            if (!$candidateStmt->execute()) {
                                throw new RuntimeException("Could not create candidate profile.");
                            }

                            $candidateStmt->close();
                        }

                        $conn->commit();
                        $successMessage = u('\u039f \u03c7\u03c1\u03ae\u03c3\u03c4\u03b7\u03c2 \u03b4\u03b7\u03bc\u03b9\u03bf\u03c5\u03c1\u03b3\u03ae\u03b8\u03b7\u03ba\u03b5 \u03bc\u03b5 \u03b5\u03c0\u03b9\u03c4\u03c5\u03c7\u03af\u03b1.');
                    } catch (Throwable $exception) {
                        $conn->rollback();
                        $errorMessage = u('\\u03A0\\u03B1\\u03C1\\u03BF\\u03C5\\u03C3\\u03B9\\u03AC\\u03C3\\u03C4\\u03B7\\u03BA\\u03B5 \\u03C0\\u03C1\\u03CC\\u03B2\\u03BB\\u03B7\\u03BC\\u03B1 \\u03BA\\u03B1\\u03C4\\u03AC \\u03C4\\u03B7\\u03BD \\u03BF\\u03BB\\u03BF\\u03BA\\u03BB\\u03AE\\u03C1\\u03C9\\u03C3\\u03B7 \\u03C4\\u03B7\\u03C2 \\u03B5\\u03BD\\u03AD\\u03C1\\u03B3\\u03B5\\u03B9\\u03B1\\u03C2. \\u0394\\u03BF\\u03BA\\u03AF\\u03BC\\u03B1\\u03C3\\u03B5 \\u03BE\\u03B1\\u03BD\\u03AC.');
                    }
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
            $checkStmt = $conn->prepare("SELECT u.id FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE (u.email = ? OR up.identity_number = ?) AND u.id <> ? LIMIT 1");

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
                        $updateStmt = $conn->prepare("UPDATE users SET email = ?, role = ?, password_hash = ? WHERE id = ?");

                        if ($updateStmt) {
                            $conn->begin_transaction();
                            try {
                                $updateStmt->bind_param("sssi", $email, $role, $hashedPassword, $editUserId);
                                if (!$updateStmt->execute()) {
                                    throw new RuntimeException("??? ???? ?????? ? ????????? ??? ??????.");
                                }
                                $updateStmt->close();

                                $profileStmt = $conn->prepare("UPDATE user_profiles SET first_name = ?, last_name = ?, identity_number = ?, phone = ? WHERE user_id = ?");
                                if (!$profileStmt) {
                                    throw new RuntimeException("??? ???? ?????? ? ????????? ?????? ??????.");
                                }
                                $profileStmt->bind_param("ssssi", $firstName, $lastName, $identityNumber, $phone, $editUserId);
                                if (!$profileStmt->execute()) {
                                    throw new RuntimeException("??? ???? ?????? ? ????????? ?????? ??????.");
                                }
                                $profileStmt->close();

                                $conn->commit();
                                $successMessage = u('\u039f \u03c7\u03c1\u03ae\u03c3\u03c4\u03b7\u03c2 \u03b5\u03bd\u03b7\u03bc\u03b5\u03c1\u03ce\u03b8\u03b7\u03ba\u03b5 \u03bc\u03b5 \u03b5\u03c0\u03b9\u03c4\u03c5\u03c7\u03af\u03b1.');
                            } catch (Throwable $exception) {
                                $conn->rollback();
                                $errorMessage = u('\\u03A0\\u03B1\\u03C1\\u03BF\\u03C5\\u03C3\\u03B9\\u03AC\\u03C3\\u03C4\\u03B7\\u03BA\\u03B5 \\u03C0\\u03C1\\u03CC\\u03B2\\u03BB\\u03B7\\u03BC\\u03B1 \\u03BA\\u03B1\\u03C4\\u03AC \\u03C4\\u03B7\\u03BD \\u03BF\\u03BB\\u03BF\\u03BA\\u03BB\\u03AE\\u03C1\\u03C9\\u03C3\\u03B7 \\u03C4\\u03B7\\u03C2 \\u03B5\\u03BD\\u03AD\\u03C1\\u03B3\\u03B5\\u03B9\\u03B1\\u03C2. \\u0394\\u03BF\\u03BA\\u03AF\\u03BC\\u03B1\\u03C3\\u03B5 \\u03BE\\u03B1\\u03BD\\u03AC.');
                            }
                        }
                    }
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");

                    if ($updateStmt) {
                        $conn->begin_transaction();
                        try {
                            $updateStmt->bind_param("ssi", $email, $role, $editUserId);
                            if (!$updateStmt->execute()) {
                                throw new RuntimeException("??? ???? ?????? ? ????????? ??? ??????.");
                            }
                            $updateStmt->close();

                            $profileStmt = $conn->prepare("UPDATE user_profiles SET first_name = ?, last_name = ?, identity_number = ?, phone = ? WHERE user_id = ?");
                            if (!$profileStmt) {
                                throw new RuntimeException("??? ???? ?????? ? ????????? ?????? ??????.");
                            }
                            $profileStmt->bind_param("ssssi", $firstName, $lastName, $identityNumber, $phone, $editUserId);
                            if (!$profileStmt->execute()) {
                                throw new RuntimeException("??? ???? ?????? ? ????????? ?????? ??????.");
                            }
                            $profileStmt->close();

                            $conn->commit();
                            $successMessage = u('\u039f \u03c7\u03c1\u03ae\u03c3\u03c4\u03b7\u03c2 \u03b5\u03bd\u03b7\u03bc\u03b5\u03c1\u03ce\u03b8\u03b7\u03ba\u03b5 \u03bc\u03b5 \u03b5\u03c0\u03b9\u03c4\u03c5\u03c7\u03af\u03b1.');
                        } catch (Throwable $exception) {
                            $conn->rollback();
                            $errorMessage = u('\\u03A0\\u03B1\\u03C1\\u03BF\\u03C5\\u03C3\\u03B9\\u03AC\\u03C3\\u03C4\\u03B7\\u03BA\\u03B5 \\u03C0\\u03C1\\u03CC\\u03B2\\u03BB\\u03B7\\u03BC\\u03B1 \\u03BA\\u03B1\\u03C4\\u03AC \\u03C4\\u03B7\\u03BD \\u03BF\\u03BB\\u03BF\\u03BA\\u03BB\\u03AE\\u03C1\\u03C9\\u03C3\\u03B7 \\u03C4\\u03B7\\u03C2 \\u03B5\\u03BD\\u03AD\\u03C1\\u03B3\\u03B5\\u03B9\\u03B1\\u03C2. \\u0394\\u03BF\\u03BA\\u03AF\\u03BC\\u03B1\\u03C3\\u03B5 \\u03BE\\u03B1\\u03BD\\u03AC.');
                        }
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
                $errorMessage = u('\\u03A0\\u03B1\\u03C1\\u03BF\\u03C5\\u03C3\\u03B9\\u03AC\\u03C3\\u03C4\\u03B7\\u03BA\\u03B5 \\u03C0\\u03C1\\u03CC\\u03B2\\u03BB\\u03B7\\u03BC\\u03B1 \\u03BA\\u03B1\\u03C4\\u03AC \\u03C4\\u03B7\\u03BD \\u03BF\\u03BB\\u03BF\\u03BA\\u03BB\\u03AE\\u03C1\\u03C9\\u03C3\\u03B7 \\u03C4\\u03B7\\u03C2 \\u03B5\\u03BD\\u03AD\\u03C1\\u03B3\\u03B5\\u03B9\\u03B1\\u03C2. \\u0394\\u03BF\\u03BA\\u03AF\\u03BC\\u03B1\\u03C3\\u03B5 \\u03BE\\u03B1\\u03BD\\u03AC.');
            }
        }
    } elseif ($action === "import_list_csv") {
        $specialtyId = (int) ($_POST["specialty_id"] ?? 0);
        $loadYear = (int) ($_POST["load_year"] ?? date("Y"));
        $selectedSpecialty = null;

        foreach ($specialties as $specialty) {
            if ((int) $specialty["id"] === $specialtyId) {
                $selectedSpecialty = $specialty;
                break;
            }
        }

        $uploadedFile = $_FILES["list_file"] ?? null;

        if (!$selectedSpecialty) {
            $errorMessage = u('\u0395\u03c0\u03af\u03bb\u03b5\u03be\u03b5 \u03ad\u03b3\u03ba\u03c5\u03c1\u03b7 \u03b5\u03b9\u03b4\u03b9\u03ba\u03cc\u03c4\u03b7\u03c4\u03b1 \u03b3\u03b9\u03b1 \u03c6\u03cc\u03c1\u03c4\u03c9\u03c3\u03b7 \u03c0\u03af\u03bd\u03b1\u03ba\u03b1.');
        } elseif (!is_array($uploadedFile) || (int) ($uploadedFile["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errorMessage = u('\u0395\u03c0\u03ad\u03bb\u03b5\u03be\u03b5 \u03ad\u03bd\u03b1 CSV \u03b1\u03c1\u03c7\u03b5\u03af\u03bf \u03c0\u03af\u03bd\u03b1\u03ba\u03b1 \u03b1\u03c0\u03cc \u03c4\u03b7\u03bd \u0395\u0395\u03a5.');
        } else {
            $importRows = load_candidates_from_csv((string) $uploadedFile["tmp_name"]);

            if ($importRows === []) {
                $errorMessage = u('\u0394\u03b5\u03bd \u03b2\u03c1\u03ad\u03b8\u03b7\u03ba\u03b1\u03bd \u03ad\u03b3\u03ba\u03c5\u03c1\u03b5\u03c2 \u03b5\u03b3\u03b3\u03c1\u03b1\u03c6\u03ad\u03c2. \u03a4\u03bf CSV \u03c0\u03c1\u03ad\u03c0\u03b5\u03b9 \u03bd\u03b1 \u03ad\u03c7\u03b5\u03b9 \u03c3\u03c4\u03ae\u03bb\u03b5\u03c2 first_name,last_name \u03ae \u03b1\u03bd\u03c4\u03af\u03c3\u03c4\u03bf\u03b9\u03c7\u03b5\u03c2 \u03b5\u03bb\u03bb\u03b7\u03bd\u03b9\u03ba\u03ad\u03c2 \u03b5\u03c0\u03b9\u03ba\u03b5\u03c6\u03b1\u03bb\u03af\u03b4\u03b5\u03c2.');
            } else {
                $defaultPassword = password_hash("candidate123", PASSWORD_DEFAULT);
                $inserted = 0;
                $updated = 0;
                $skipped = 0;

                $conn->begin_transaction();

                try {
                    foreach ($importRows as $row) {
                        $firstName = trim((string) ($row["first_name"] ?? ""));
                        $lastName = trim((string) ($row["last_name"] ?? ""));
                        $identityNumber = normalize_identity_number((string) ($row["identity_number"] ?? ""));
                        $identityNumber = $identityNumber !== "" ? $identityNumber : null;
                        $email = trim((string) ($row["email"] ?? ""));
                        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : generated_import_email($conn, $firstName, $lastName, $identityNumber);
                        $phone = trim((string) ($row["phone"] ?? ""));
                        $phone = $phone !== "" ? $phone : null;
                        $fatherName = trim((string) ($row["father_name"] ?? ""));
                        $fatherName = $fatherName !== "" ? $fatherName : null;
                        $motherName = trim((string) ($row["mother_name"] ?? ""));
                        $motherName = $motherName !== "" ? $motherName : null;
                        $birthDate = normalize_import_date($row["birth_date"] ?? null);
                        $rankingPosition = trim((string) ($row["ranking_position"] ?? ""));
                        $rankingPositionValue = ctype_digit($rankingPosition) ? (int) $rankingPosition : null;
                        $pointsValue = normalize_import_decimal($row["points"] ?? null);
                        $applicationStatus = trim((string) ($row["application_status"] ?? ""));
                        $applicationStatus = $applicationStatus !== ""
                            ? $applicationStatus
                            : u('\u03a6\u03bf\u03c1\u03c4\u03ce\u03b8\u03b7\u03ba\u03b5 \u03b1\u03c0\u03cc \u03c0\u03af\u03bd\u03b1\u03ba\u03b1 \u0395\u0395\u03a5') . " (" . $loadYear . ")";

                        if ($firstName === "" || $lastName === "") {
                            $skipped++;
                            continue;
                        }

                        $existingUser = null;
                        if ($identityNumber !== null) {
                            $existingUser = fetch_one_prepared(
                                $conn,
                                "SELECT u.id FROM users u INNER JOIN user_profiles up ON up.user_id = u.id WHERE up.identity_number = ? LIMIT 1",
                                "s",
                                [$identityNumber]
                            );
                        }
                        if (!$existingUser) {
                            $existingUser = fetch_one_prepared($conn, "SELECT id FROM users WHERE email = ? LIMIT 1", "s", [$email]);
                        }

                        if ($existingUser) {
                            $userId = (int) $existingUser["id"];
                            execute_prepared_statement(
                                $conn,
                                "UPDATE users SET role = 'candidate' WHERE id = ?",
                                "i",
                                [$userId]
                            );
                            execute_prepared_statement(
                                $conn,
                                "UPDATE user_profiles SET first_name = ?, last_name = ?, identity_number = ?, phone = ? WHERE user_id = ?",
                                "ssssi",
                                [$firstName, $lastName, $identityNumber, $phone, $userId]
                            );
                            $updated++;
                        } else {
                            $username = generate_unique_username($conn, username_from_email($email));
                            $userStmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'candidate')");
                            if (!$userStmt) {
                                throw new RuntimeException("Could not prepare user import.");
                            }
                            $userStmt->bind_param("sss", $username, $email, $defaultPassword);
                            if (!$userStmt->execute()) {
                                throw new RuntimeException("Could not insert imported user.");
                            }
                            $userStmt->close();
                            $userId = (int) $conn->insert_id;

                            execute_prepared_statement(
                                $conn,
                                "INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone) VALUES (?, ?, ?, ?, ?)",
                                "issss",
                                [$userId, $firstName, $lastName, $identityNumber, $phone]
                            );
                            $inserted++;
                        }

                        $profile = fetch_one_prepared($conn, "SELECT id FROM candidate_profiles WHERE user_id = ? LIMIT 1", "i", [$userId]);
                        if ($profile) {
                            execute_prepared_statement(
                                $conn,
                                "UPDATE candidate_profiles SET father_name = ?, mother_name = ?, birth_date = ?, specialty_id = ?, application_status = ?, ranking_position = ?, points = ?, created_at = CONCAT(?, '-01-01 00:00:00') WHERE id = ?",
                                "sssisisdii",
                                [$fatherName, $motherName, $birthDate, $specialtyId, $applicationStatus, $rankingPositionValue, $pointsValue, $loadYear, (int) $profile["id"]]
                            );
                        } else {
                            execute_prepared_statement(
                                $conn,
                                "INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CONCAT(?, '-01-01 00:00:00'))",
                                "isssisidi",
                                [$userId, $fatherName, $motherName, $birthDate, $specialtyId, $applicationStatus, $rankingPositionValue, $pointsValue, $loadYear]
                            );
                        }
                    }

                    $conn->commit();
                    $successMessage = u('\u0397 \u03bb\u03af\u03c3\u03c4\u03b1 \u03c6\u03bf\u03c1\u03c4\u03ce\u03b8\u03b7\u03ba\u03b5 \u03bc\u03b5 \u03b5\u03c0\u03b9\u03c4\u03c5\u03c7\u03af\u03b1.') . " Inserted: " . $inserted . ", updated: " . $updated . ", skipped: " . $skipped . ".";
                } catch (Throwable $exception) {
                    $conn->rollback();
                    $errorMessage = u('\u03a0\u03b1\u03c1\u03bf\u03c5\u03c3\u03b9\u03ac\u03c3\u03c4\u03b7\u03ba\u03b5 \u03c0\u03c1\u03cc\u03b2\u03bb\u03b7\u03bc\u03b1 \u03ba\u03b1\u03c4\u03ac \u03c4\u03b7\u03bd \u03b5\u03b9\u03c3\u03b1\u03b3\u03c9\u03b3\u03ae \u03c4\u03bf\u03c5 CSV. \u0394\u03bf\u03ba\u03af\u03bc\u03b1\u03c3\u03b5 \u03be\u03b1\u03bd\u03ac.');
                }
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
            $checkStmt = $conn->prepare("SELECT u.id FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE (u.email = ? OR up.identity_number = ?) AND u.id <> ? LIMIT 1");

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
                $updateStmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");

                if ($updateStmt) {
                    $conn->begin_transaction();
                    try {
                        $updateStmt->bind_param("si", $email, $_SESSION["user_id"]);
                        if (!$updateStmt->execute()) {
                            throw new RuntimeException("??? ????? ????????? ??? ?????????.");
                        }
                        $updateStmt->close();

                        $profileStmt = $conn->prepare("UPDATE user_profiles SET first_name = ?, last_name = ?, identity_number = ?, phone = ? WHERE user_id = ?");
                        if (!$profileStmt) {
                            throw new RuntimeException("??? ???? ?????? ? ????????? ?????? ??????.");
                        }
                        $profileStmt->bind_param("ssssi", $firstName, $lastName, $identityNumber, $phone, $_SESSION["user_id"]);
                        if (!$profileStmt->execute()) {
                            throw new RuntimeException("??? ???? ?????? ? ????????? ?????? ??????.");
                        }
                        $profileStmt->close();

                        $conn->commit();
                        $_SESSION["first_name"] = $firstName;
                        $_SESSION["last_name"] = $lastName;
                        $_SESSION["email"] = $email;
                        $successMessage = u('\u03a4\u03b1 \u03c3\u03c4\u03bf\u03b9\u03c7\u03b5\u03af\u03b1 \u03c4\u03bf\u03c5 admin \u03b5\u03bd\u03b7\u03bc\u03b5\u03c1\u03ce\u03b8\u03b7\u03ba\u03b1\u03bd \u03bc\u03b5 \u03b5\u03c0\u03b9\u03c4\u03c5\u03c7\u03af\u03b1.');
                    } catch (Throwable $exception) {
                        $conn->rollback();
                        $errorMessage = u('\\u03A0\\u03B1\\u03C1\\u03BF\\u03C5\\u03C3\\u03B9\\u03AC\\u03C3\\u03C4\\u03B7\\u03BA\\u03B5 \\u03C0\\u03C1\\u03CC\\u03B2\\u03BB\\u03B7\\u03BC\\u03B1 \\u03BA\\u03B1\\u03C4\\u03AC \\u03C4\\u03B7\\u03BD \\u03BF\\u03BB\\u03BF\\u03BA\\u03BB\\u03AE\\u03C1\\u03C9\\u03C3\\u03B7 \\u03C4\\u03B7\\u03C2 \\u03B5\\u03BD\\u03AD\\u03C1\\u03B3\\u03B5\\u03B9\\u03B1\\u03C2. \\u0394\\u03BF\\u03BA\\u03AF\\u03BC\\u03B1\\u03C3\\u03B5 \\u03BE\\u03B1\\u03BD\\u03AC.');
                    }
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
        $refreshStmt = $conn->prepare("SELECT u.id, u.username, up.first_name, up.last_name, u.email, up.identity_number, up.phone, u.password_hash, u.created_at FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ? LIMIT 1");

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

$users = fetch_all_prepared($conn, "
    SELECT
        u.id,
        up.first_name,
        up.last_name,
        u.email,
        up.identity_number,
        up.phone,
        u.role,
        u.created_at,
        s.title AS specialty_title,
        cp.ranking_position
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    LEFT JOIN specialties s ON s.id = cp.specialty_id
    ORDER BY u.role DESC, u.created_at DESC, u.id DESC
");

foreach ($users as $row) {
    if ($editingUserId > 0 && (int) $row["id"] === $editingUserId) {
        $editingUser = $row;
    }
}

$candidateListKeyword = trim((string) ($_GET["candidate_keyword"] ?? $_GET["keyword"] ?? ""));
$candidateListSpecialtyId = (int) ($_GET["candidate_specialty_id"] ?? $_GET["specialty_id"] ?? 0);
$candidateListSpecialtyLabel = u('\u038c\u03bb\u03b5\u03c2');

foreach ($specialties as $specialty) {
    if ((int) $specialty["id"] === $candidateListSpecialtyId) {
        $candidateListSpecialtyLabel = $specialty["title"];
        break;
    }
}

$candidateListSearchTerm = "%" . $candidateListKeyword . "%";
$candidateListRows = fetch_all_prepared(
    $conn,
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
     LIMIT 50",
    "ssssssii",
    [
        $candidateListKeyword,
        $candidateListSearchTerm,
        $candidateListSearchTerm,
        $candidateListSearchTerm,
        $candidateListSearchTerm,
        $candidateListSearchTerm,
        $candidateListSpecialtyId,
        $candidateListSpecialtyId,
    ]
);
$candidateListTotal = count($candidateListRows);
$candidateListHasFilters = $candidateListKeyword !== "" || $candidateListSpecialtyId > 0;

$overview = [
    "total_candidates" => 0,
    "average_age" => null,
    "new_candidates_year" => 0,
    "tracked_total" => 0,
];

$overviewRow = fetch_one_prepared(
    $conn,
    "SELECT
        (SELECT COUNT(*) FROM candidate_profiles) AS total_candidates,
        (SELECT AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) FROM candidate_profiles WHERE birth_date IS NOT NULL) AS average_age,
        (SELECT COUNT(*) FROM users WHERE role = 'candidate' AND YEAR(created_at) = YEAR(CURDATE())) AS new_candidates_year,
        (SELECT COUNT(*) FROM tracked_candidates) AS tracked_total"
);

if ($overviewRow) {
    $overview = $overviewRow;
}

$specialtyStats = fetch_all_prepared($conn, "
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
");

$yearlyRows = fetch_all_prepared($conn, "
    SELECT YEAR(created_at) AS report_year, COUNT(*) AS candidate_count
    FROM users
    WHERE role = 'candidate'
    GROUP BY YEAR(created_at)
    ORDER BY report_year DESC
");

$maxSpecialtyCount = 0;

foreach ($specialtyStats as $row) {
    $maxSpecialtyCount = max($maxSpecialtyCount, (int) $row["candidate_count"]);
}

$selectedSpecialtyId = (int) ($_POST["specialty_id"] ?? ($specialties[0]["id"] ?? 0));
$selectedLoadYear = (int) ($_POST["load_year"] ?? date("Y"));
$requestedSection = (string) ($_GET["section"] ?? "overview");
$allowedAdminSections = ["overview", "users", "lists", "candidate-list", "reports", "account"];

if (!in_array($requestedSection, $allowedAdminSections, true)) {
    $requestedSection = "overview";
}

$actionSectionMap = [
    "create_user" => "users",
    "update_user" => "users",
    "delete_user" => "users",
    "import_list_csv" => "lists",
    "update_profile" => "account",
    "change_password" => "account",
];

$postedAction = (string) ($_POST["action"] ?? "");
$currentAdminSection = $requestedSection;

if ($postedAction !== "" && isset($actionSectionMap[$postedAction])) {
    $currentAdminSection = $actionSectionMap[$postedAction];
}

if ($editingUserId > 0) {
    $currentAdminSection = "users";
}

$pageTitle = APP_NAME . " | Admin Dashboard";
$bodyClass = "theme-admin";
$currentPage = "admin";
$navBase = "../";
$headerActionLabel = "Ρυθμίσεις Λογαριασμού";
$headerActionHref = "?section=account";

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

require_once __DIR__ . "/../../includes/functions.php";
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo e(path_from_root("assets/css/style.css") . "?v=20260426-2"); ?>">
    <style>
        body.theme-admin {
            margin: 0;
            background: #f5f7fb;
            color: #14263d;
        }

        .theme-admin .admin-shell {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            min-height: 100vh !important;
            display: grid !important;
            grid-template-columns: 250px minmax(0, 1fr) !important;
            gap: 0 !important;
            align-items: stretch !important;
            padding: 0 !important;
        }

        .theme-admin .admin-sidebar {
            display: flex !important;
            flex-direction: column !important;
            position: sticky !important;
            top: 0 !important;
            height: 100vh !important;
            padding: 22px 14px !important;
            background: #ffffff !important;
            border-right: 1px solid rgba(21, 55, 92, 0.10) !important;
            gap: 12px !important;
            box-shadow: none !important;
        }

        .theme-admin .admin-sidebar-card {
            display: block !important;
            padding: 0 4px 8px !important;
            margin: 0 !important;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .theme-admin .admin-sidebar-title {
            margin: 0 0 8px !important;
            font-size: 1.25rem !important;
            line-height: 1.05 !important;
        }

        .theme-admin .admin-sidebar-nav {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 8px !important;
            padding: 8px 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .theme-admin .admin-side-link {
            display: flex !important;
            align-items: center !important;
            min-height: 40px !important;
            padding: 10px 12px !important;
            border-radius: 12px !important;
            font-weight: 800 !important;
            color: #5b6d84 !important;
            background: transparent !important;
        }

        .theme-admin .admin-side-link.is-active {
            background: #f2f4f8 !important;
            color: #14263d !important;
            box-shadow: none !important;
        }

        .theme-admin .admin-back-link {
            margin-top: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 40px !important;
            padding: 0 14px !important;
            border-radius: 999px !important;
            border: 1px solid rgba(21, 55, 92, 0.14) !important;
            background: #fff !important;
            color: #10243f !important;
            font-weight: 800 !important;
        }

        .theme-admin .admin-sidebar-card-compact {
            margin-top: auto !important;
        }

        .theme-admin .admin-content {
            display: block !important;
            min-width: 0 !important;
            padding: 24px 28px 36px !important;
        }

        .theme-admin .page-hero {
            display: flex !important;
            justify-content: space-between !important;
            gap: 14px !important;
            padding: 6px 0 12px !important;
            margin: 0 0 6px !important;
        }

        .theme-admin .section-shell {
            background: #fff !important;
            border: 1px solid rgba(21, 55, 92, 0.08) !important;
            border-radius: 28px !important;
            box-shadow: 0 10px 26px rgba(17, 39, 68, 0.06) !important;
            padding: 24px !important;
        }

        .theme-admin .section-shell + .section-shell {
            margin-top: 18px !important;
        }

        .theme-admin .section-shell .panel-head {
            margin-bottom: 18px !important;
            padding-bottom: 14px !important;
            border-bottom: 1px solid rgba(21, 55, 92, 0.08) !important;
        }

        .theme-admin .section-shell .panel-head h2 {
            margin: 0 0 8px !important;
            font-size: clamp(1.8rem, 3vw, 2.4rem) !important;
            line-height: 1.05 !important;
            letter-spacing: -0.03em !important;
        }

        .theme-admin .section-shell .panel-head .muted {
            margin: 0 !important;
            max-width: 62ch !important;
        }

        .theme-admin .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 18px !important;
            border-radius: 16px !important;
            font-weight: 800;
            box-shadow: 0 14px 28px rgba(22, 101, 52, 0.10);
        }

        .theme-admin .alert-success {
            border: 1px solid #86efac !important;
            background: #dcfce7 !important;
            color: #14532d !important;
        }

        .theme-admin .alert-success::before {
            content: "✓";
            display: inline-grid;
            place-items: center;
            width: 24px;
            height: 24px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: #16a34a;
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 900;
        }

        .theme-admin .alert-error {
            color: #7f1d1d !important;
        }

        .theme-admin .hero-badges {
            display: none !important;
        }

        .theme-admin .hero-metrics {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 14px !important;
            margin: 10px 0 18px !important;
        }

        .theme-admin .grid-admin {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 12px !important;
        }

        .theme-admin .content-panel {
            display: none !important;
            margin-top: 22px !important;
        }

        .theme-admin:not([data-admin-section="overview"]) .page-hero,
        .theme-admin:not([data-admin-section="overview"]) .hero-metrics,
        .theme-admin:not([data-admin-section="overview"]) .grid-admin {
            display: none !important;
        }

        .theme-admin[data-admin-section="users"] .content-panel.section-users,
        .theme-admin[data-admin-section="lists"] .content-panel.section-lists,
        .theme-admin[data-admin-section="candidate-list"] .content-panel.section-candidate-list,
        .theme-admin[data-admin-section="reports"] .content-panel.section-reports,
        .theme-admin[data-admin-section="account"] .content-panel.section-account {
            display: block !important;
        }

        .theme-admin .candidate-list-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 20px;
        }

        .theme-admin .summary-chip {
            min-height: 82px;
            padding: 14px 16px;
            border: 1px solid rgba(21, 55, 92, 0.08);
            border-radius: 18px;
            background: #f7f9fc;
        }

        .theme-admin .summary-chip span,
        .theme-admin .filters-title {
            color: #5d7088;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .theme-admin .summary-chip strong {
            display: block;
            margin-top: 8px;
            color: #14263d;
            font-family: "Space Grotesk", "Manrope", sans-serif;
            font-size: clamp(1.25rem, 2vw, 1.8rem);
            line-height: 1.08;
            word-break: break-word;
        }

        .theme-admin .filters-title {
            margin: 2px 0 14px;
        }

        .theme-admin .section-candidate-list .form-grid {
            grid-template-columns: minmax(260px, 1.25fr) minmax(240px, 1fr) auto;
            align-items: end;
        }

        .theme-admin .section-reports {
            font-weight: 400;
        }

        .theme-admin .section-reports .reports-summary {
            margin-bottom: 18px;
        }

        .theme-admin .section-reports .reports-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
            gap: 18px;
            align-items: start;
        }

        .theme-admin .section-reports .chart-card {
            min-height: 360px;
            padding: 18px;
            border-radius: 20px;
            background: #ffffff !important;
        }

        .theme-admin .section-reports .chart-card h3 {
            margin: 0 0 14px;
            font-size: 1rem;
            font-weight: 800;
        }

        .theme-admin .section-reports .chart-mock {
            display: grid;
            gap: 12px;
        }

        .theme-admin .section-reports .report-bar-row {
            display: grid;
            gap: 8px;
        }

        .theme-admin .section-reports .report-bar-info,
        .theme-admin .section-reports .report-year-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .theme-admin .section-reports .report-bar-info span,
        .theme-admin .section-reports .report-year-row span {
            min-width: 0;
            color: #10243f;
            font-size: 1rem;
            font-weight: 500 !important;
            line-height: 1.35;
        }

        .theme-admin .section-reports .report-bar-info strong,
        .theme-admin .section-reports .report-year-row strong {
            flex: 0 0 auto;
            color: #10243f;
            font-size: 1rem;
            font-weight: 600 !important;
            line-height: 1.35;
        }

        .theme-admin .section-reports .report-bar-track {
            height: 11px;
            overflow: hidden;
            border-radius: 999px;
            background: #eef3f8;
        }

        .theme-admin .section-reports .report-bar-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #c59335, #e0ad49);
        }

        .theme-admin .section-reports .year-list {
            display: grid;
            gap: 10px;
        }

        .theme-admin .section-reports .year-item {
            padding: 12px;
            border-radius: 16px;
            background: #f8fafc !important;
        }

        .theme-admin .section-reports .pill {
            font-weight: 700;
        }

        .theme-admin .section-reports .report-note {
            margin-top: 14px;
        }

        .theme-admin .metric-card,
        .theme-admin .card,
        .theme-admin .chart-card,
        .theme-admin .stat {
            background: #fff !important;
            border: 1px solid rgba(21, 55, 92, 0.08) !important;
            box-shadow: 0 4px 12px rgba(17, 39, 68, 0.05) !important;
        }

        @media (max-width: 980px) {
            .theme-admin .admin-shell {
            grid-template-columns: 1fr !important;
        }

            .theme-admin .admin-sidebar {
                position: static !important;
                height: auto !important;
                border-right: none !important;
                border-bottom: 1px solid rgba(21, 55, 92, 0.10) !important;
            }

            .theme-admin .admin-content {
                padding: 24px 18px 32px !important;
            }

            .theme-admin .hero-metrics,
            .theme-admin .grid-admin,
            .theme-admin .candidate-list-summary,
            .theme-admin .section-candidate-list .form-grid,
            .theme-admin .section-reports .stats,
            .theme-admin .section-reports .reports-layout {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body class="<?php echo e($bodyClass ?? "theme-admin"); ?>" data-admin-section="<?php echo h($currentAdminSection); ?>">
<?php

?>
        <main class="container admin-shell">
        <aside class="admin-sidebar" aria-label="Admin panel">
            <div class="admin-sidebar-card">
                <span class="eyebrow-home">Ichnos Admin</span>
                <h2 class="admin-sidebar-title">&#916;&#953;&#945;&#967;&#949;&#943;&#961;&#953;&#963;&#951;</h2>
            </div>

            <nav class="admin-sidebar-nav">
                <a class="admin-side-link<?php echo $currentAdminSection === "overview" ? " is-active" : ""; ?>" href="?section=overview">&#917;&#960;&#953;&#963;&#954;&#972;&#960;&#951;&#963;&#951;</a>
                <a class="admin-side-link<?php echo $currentAdminSection === "users" ? " is-active" : ""; ?>" href="?section=users">&#935;&#961;&#942;&#963;&#964;&#949;&#962;</a>
                <a class="admin-side-link<?php echo $currentAdminSection === "lists" ? " is-active" : ""; ?>" href="?section=lists">&#923;&#943;&#963;&#964;&#949;&#962;</a>
                <a class="admin-side-link<?php echo $currentAdminSection === "candidate-list" ? " is-active" : ""; ?>" href="?section=candidate-list">&#923;&#943;&#963;&#964;&#945; &#933;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;</a>
                <a class="admin-side-link<?php echo $currentAdminSection === "reports" ? " is-active" : ""; ?>" href="?section=reports">Reports</a>
                <a class="admin-side-link<?php echo $currentAdminSection === "account" ? " is-active" : ""; ?>" href="?section=account">&#923;&#959;&#947;&#945;&#961;&#953;&#945;&#963;&#956;&#972;&#962;</a>
            </nav>

            <div class="admin-sidebar-card admin-sidebar-card-compact">
                <span class="admin-sidebar-label">Admin</span>
                <strong><?php echo h($adminUser["first_name"] . " " . $adminUser["last_name"]); ?></strong>
                <span class="admin-sidebar-subtle"><?php echo h($adminUser["email"]); ?></span>
            </div>
                    <a class="admin-back-link" href="../../index.php">&#8592; Back to Website</a>
        </aside>

        <div class="admin-content">
        <section class="page-hero" id="overview" aria-labelledby="pageTitle">
            <div class="hero-text">
                <span class="eyebrow-home">Admin Control Center</span>
    <h1 id="pageTitle">Πίνακας Διαχείρισης</h1>
    <p class="muted">Η επισκόπηση παρουσιάζει συνοπτικά τις βασικές δυνατότητες του admin module: διαχείριση χρηστών, λίστες, reports και στοιχεία λογαριασμού.</p>
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
            </article>
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">2</div>
    <h2>Λίστες</h2>
    <p>Προβολή στατιστικών ανά ειδικότητα και φόρτωση πινάκων από CSV.</p>
            </article>
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">3</div>
    <h2>Reports</h2>
    <p>Συνοπτικά KPI, κατανομή υποψηφίων και χρονολογική επισκόπηση του συστήματος.</p>
            </article>
            <article class="card card-action">
                <div class="card-icon" aria-hidden="true">4</div>
    <h2>Λογαριασμός</h2>
    <p>Ενημέρωση βασικών στοιχείων και αλλαγή κωδικού πρόσβασης για τον admin λογαριασμό.</p>
            </article>
        </section>

        <section class="panel content-panel section-users section-shell" id="manage-users" aria-labelledby="usersTitle">
            <div class="panel-head">
        <h2 id="usersTitle">Διαχείριση Χρηστών</h2>
        <p class="muted">Οργάνωσε το σύνολο των χρηστών του συστήματος από ένα ενιαίο σημείο διαχείρισης.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="?section=users#manage-users">
                    <input type="hidden" name="action" value="create_user">
        <h3>Νέος Χρήστης</h3>
                    <div class="form-stack">
                        <div class="form-group">
                <label for="new_first_name">Όνομα</label>
                            <input id="new_first_name" name="new_first_name" type="text" value="<?php echo h($createUserForm["first_name"]); ?>" required>
                        </div>
                        <div class="form-group">
                <label for="new_last_name">Επώνυμο</label>
                            <input id="new_last_name" name="new_last_name" type="text" value="<?php echo h($createUserForm["last_name"]); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input id="new_email" name="new_email" type="email" value="<?php echo h($createUserForm["email"]); ?>" required>
                        </div>
                        <div class="form-group">
                <label for="new_identity_number">Αριθμός ταυτότητας</label>
                            <input id="new_identity_number" name="new_identity_number" type="text" value="<?php echo h($createUserForm["identity_number"]); ?>" required>
                        </div>
                        <div class="form-group">
                <label for="new_phone">Τηλέφωνο</label>
                            <input id="new_phone" name="new_phone" type="text" value="<?php echo h($createUserForm["phone"]); ?>">
                        </div>
                        <div class="form-group">
                <label for="new_role">Ρόλος</label>
                            <select id="new_role" name="new_role" required>
                                <option value="candidate" <?php echo $createUserForm["role"] === "candidate" ? "selected" : ""; ?>>candidate</option>
                                <option value="admin" <?php echo $createUserForm["role"] === "admin" ? "selected" : ""; ?>>admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                <label for="new_password">Κωδικός πρόσβασης</label>
                            <input id="new_password" name="new_password" type="password" required>
                        </div>
                        <div class="form-group">
                <label for="new_specialty_id">Ειδικότητα candidate</label>
                            <select id="new_specialty_id" name="new_specialty_id">
                                <option value="0">Χωρίς ειδικότητα</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo (int) $createUserForm["specialty_id"] === (int) $specialty["id"] ? "selected" : ""; ?>>
                                        <?php echo h(admin_text($specialty["title"])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
        <button class="btn btn-primary" type="submit">Δημιουργία Χρήστη</button>
                </form>

                <form class="panel panel-nested" method="post" action="?section=users#manage-users">
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
            <a class="btn btn-secondary" href="admindashboard.php?section=users#manage-users">Ακύρωση</a>
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
                                            <a class="btn btn-small" href="?section=users&edit_user=<?php echo (int) $row["id"]; ?>#manage-users">Edit</a>
                                            <?php if ((int) $row["id"] !== (int) $_SESSION["user_id"]): ?>
                                                <form method="post" action="?section=users#manage-users" onsubmit="return confirm('Να διαγραφεί ο χρήστης;');">
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

        <section class="panel content-panel section-lists section-shell" id="manage-lists" aria-labelledby="listsTitle">
            <div class="panel-head">
        <h2 id="listsTitle">Διαχείριση Λιστών</h2>
        <p class="muted">Φόρτωσε CSV πίνακα από την ΕΕΥ για μια ειδικότητα και δες συνοπτικά στατιστικά ανά λίστα.</p>
            </div>

            <form class="form-grid" method="post" action="?section=lists#manage-lists" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_list_csv">
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
                <div class="form-group">
            <label for="list_file">CSV πίνακα ΕΕΥ</label>
                    <input id="list_file" name="list_file" type="file" accept=".csv,text/csv" required>
                </div>
                <div class="form-group form-actions">
        <button class="btn btn-primary" type="submit">Φόρτωση Πίνακα</button>
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

        <section class="panel content-panel section-candidate-list section-shell" id="candidate-list" aria-labelledby="candidateListTitle">
            <div class="panel-head">
        <h2 id="candidateListTitle">&#923;&#943;&#963;&#964;&#945; &#933;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;</h2>
        <p class="muted">&#916;&#953;&#945;&#967;&#949;&#953;&#961;&#953;&#963;&#964;&#953;&#954;&#942; &#949;&#953;&#954;&#972;&#957;&#945; &#964;&#969;&#957; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957; &#956;&#949; &#966;&#943;&#955;&#964;&#961;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;&#962; &#954;&#945;&#953; &#963;&#965;&#957;&#959;&#960;&#964;&#953;&#954;&#940; &#963;&#964;&#959;&#953;&#967;&#949;&#943;&#945;.</p>
            </div>

            <div class="candidate-list-summary" aria-label="&#931;&#973;&#957;&#959;&#968;&#951; &#955;&#943;&#963;&#964;&#945;&#962;">
                <div class="summary-chip">
                    <span>&#917;&#947;&#947;&#961;&#945;&#966;&#941;&#962;</span>
                    <strong><?php echo $candidateListTotal; ?></strong>
                </div>
                <div class="summary-chip">
                    <span>&#923;&#941;&#958;&#951;-&#954;&#955;&#949;&#953;&#948;&#943;</span>
                    <strong><?php echo $candidateListKeyword !== "" ? h($candidateListKeyword) : "—"; ?></strong>
                </div>
                <div class="summary-chip">
                    <span>&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</span>
                    <strong><?php echo h(admin_text($candidateListSpecialtyLabel)); ?></strong>
                </div>
            </div>

            <h3 class="filters-title">&#934;&#943;&#955;&#964;&#961;&#945; &#945;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;&#962;</h3>
            <form class="form-grid" method="get" action="admindashboard.php#candidate-list">
                <input type="hidden" name="section" value="candidate-list">
                <div class="form-group">
                    <label for="candidate_keyword">&#923;&#941;&#958;&#951;-&#954;&#955;&#949;&#953;&#948;&#943;</label>
                    <input id="candidate_keyword" name="candidate_keyword" type="text" value="<?php echo h($candidateListKeyword); ?>" placeholder="&#960;.&#967;. &#928;&#945;&#960;&#945;&#948;&#959;&#960;&#959;&#973;&#955;&#959;&#965;, email, &#964;&#951;&#955;&#941;&#966;&#969;&#957;&#959;">
                </div>

                <div class="form-group">
                    <label for="candidate_specialty_id">&#917;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#945;</label>
                    <select id="candidate_specialty_id" name="candidate_specialty_id">
                        <option value="0">&#908;&#955;&#949;&#962; &#959;&#953; &#949;&#953;&#948;&#953;&#954;&#972;&#964;&#951;&#964;&#949;&#962;</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo (int) $specialty["id"]; ?>" <?php echo $candidateListSpecialtyId === (int) $specialty["id"] ? "selected" : ""; ?>>
                                <?php echo h(admin_text($specialty["title"])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-actions">
                    <button class="btn btn-primary" type="submit">&#913;&#957;&#945;&#950;&#942;&#964;&#951;&#963;&#951;</button>
                    <a class="btn btn-secondary" href="admindashboard.php?section=candidate-list#candidate-list">&#922;&#945;&#952;&#945;&#961;&#953;&#963;&#956;&#972;&#962;</a>
                </div>
            </form>

            <div class="table-titlebar">
                <h3>&#913;&#960;&#959;&#964;&#949;&#955;&#941;&#963;&#956;&#945;&#964;&#945; &#955;&#943;&#963;&#964;&#945;&#962;</h3>
                <p class="panel-subtitle">
                    <?php if ($candidateListHasFilters): ?>
                        &#917;&#956;&#966;&#945;&#957;&#943;&#950;&#959;&#957;&#964;&#945;&#953; &#959;&#953; &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#960;&#959;&#965; &#964;&#945;&#953;&#961;&#953;&#940;&#950;&#959;&#965;&#957; &#963;&#964;&#945; &#966;&#943;&#955;&#964;&#961;&#945;.
                    <?php else: ?>
                        &#917;&#956;&#966;&#945;&#957;&#943;&#950;&#959;&#957;&#964;&#945;&#953; &#941;&#969;&#962; 50 &#949;&#947;&#947;&#961;&#945;&#966;&#941;&#962; &#945;&#960;&#972; &#964;&#951; &#955;&#943;&#963;&#964;&#945; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;.
                    <?php endif; ?>
                </p>
            </div>

            <div class="table-wrap" role="region" aria-label="&#923;&#943;&#963;&#964;&#945; &#965;&#960;&#959;&#968;&#951;&#966;&#943;&#969;&#957;">
                <table class="table">
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
                        <?php if ($candidateListRows === []): ?>
                            <tr>
                                <td colspan="7" class="empty-cell">&#916;&#949;&#957; &#946;&#961;&#941;&#952;&#951;&#954;&#945;&#957; &#945;&#960;&#959;&#964;&#949;&#955;&#941;&#963;&#956;&#945;&#964;&#945; &#947;&#953;&#945; &#964;&#945; &#963;&#965;&#947;&#954;&#949;&#954;&#961;&#953;&#956;&#941;&#957;&#945; &#954;&#961;&#953;&#964;&#942;&#961;&#953;&#945;.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidateListRows as $row): ?>
                                <tr>
                                    <td><?php echo h(admin_text(($row["first_name"] ?? "") . " " . ($row["last_name"] ?? ""))); ?></td>
                                    <td><?php echo h($row["email"]); ?></td>
                                    <td><?php echo h(admin_text($row["phone"] ?? null)); ?></td>
                                    <td><?php echo h(admin_text($row["specialty_title"] ?? null)); ?></td>
                                    <td><span class="pill"><?php echo h(admin_text($row["application_status"] ?? null)); ?></span></td>
                                    <td><?php echo $row["ranking_position"] !== null ? (int) $row["ranking_position"] : "—"; ?></td>
                                    <td><?php echo $row["points"] !== null ? number_format((float) $row["points"], 2) : "—"; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel content-panel section-reports section-shell" id="reports" aria-labelledby="reportsTitle">
            <div class="panel-head">
        <h2 id="reportsTitle">Reports</h2>
        <p class="muted">Συνοπτική εικόνα της δραστηριότητας του συστήματος με βασικούς δείκτες και κατανομές.</p>
            </div>

            <div class="candidate-list-summary reports-summary" aria-label="Σύνοψη reports">
                <div class="summary-chip">
                    <span>Υποψήφιοι</span>
                    <strong><?php echo (int) $overview["total_candidates"]; ?></strong>
                </div>
                <div class="summary-chip">
                    <span>Μέση ηλικία</span>
                    <strong><?php echo $overview["average_age"] !== null ? number_format((float) $overview["average_age"], 1) : '—'; ?></strong>
                </div>
                <div class="summary-chip">
                    <span>Παρακολουθήσεις</span>
                    <strong><?php echo (int) $overview["tracked_total"]; ?></strong>
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
                                <div class="report-bar-row">
                                    <div class="report-bar-info">
                                        <span><?php echo h(admin_text($row["title"])); ?></span>
                                        <strong><?php echo $count; ?></strong>
                                    </div>
                                    <div class="report-bar-track" aria-hidden="true">
                                        <span class="report-bar-fill" style="width: <?php echo $width; ?>%"></span>
                                    </div>
                                </div>
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
            <div class="year-item">
                <div class="report-year-row">
                    <span><?php echo h((string) $yearRow["report_year"]); ?></span>
                    <strong><?php echo (int) $yearRow["candidate_count"]; ?> υποψήφιοι</strong>
                </div>
            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="report-note"><span class="pill">Tracked: <?php echo (int) $overview["tracked_total"]; ?></span></div>
                </div>
            </div>
        </section>

        <section class="panel content-panel section-account section-shell" id="account" aria-labelledby="accountTitle">
            <div class="panel-head">
        <h2 id="accountTitle">Λογαριασμός Admin</h2>
        <p class="muted">Ενημέρωση βασικών στοιχείων και αλλαγή κωδικού πρόσβασης χωρίς έξοδο από το dashboard.</p>
            </div>

            <div class="account-grid">
                <form class="panel panel-nested" method="post" action="?section=account#account">
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

                <form class="panel panel-nested" method="post" action="?section=account#account">
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
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var sideLinks = document.querySelectorAll(".admin-side-link[href*='?section=']");
            sideLinks.forEach(function (link) {
                link.addEventListener("click", function () {
                    sideLinks.forEach(function (item) {
                        item.classList.remove("is-active");
                    });
                    link.classList.add("is-active");
                });
            });
        });
    </script>

</body>
</html>






