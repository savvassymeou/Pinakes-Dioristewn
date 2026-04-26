<?php
session_start();

require_once __DIR__ . '/../../includes/auth.php';
require_role('candidate', '../../auth/login.php', '../admin/admindashboard.php', 'candidatedashboard.php');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

ensure_user_profiles_table($conn);

$candidatePage = $candidatePage ?? 'dashboard';

function candidate_value(?string $value, string $fallback = '—'): string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
}

function load_candidate(PdoConnectionAdapter $conn, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT
            u.id,
            up.first_name,
            up.last_name,
            u.email,
            up.identity_number,
            up.phone,
            u.created_at,
            cp.id AS profile_id,
            cp.father_name,
            cp.mother_name,
            cp.birth_date,
            cp.specialty_id,
            cp.application_status,
            cp.ranking_position,
            cp.points,
            cp.created_at AS profile_created_at,
            s.title AS specialty_title
        FROM users u
        LEFT JOIN user_profiles up ON up.user_id = u.id
        LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
        LEFT JOIN specialties s ON s.id = cp.specialty_id
        WHERE u.id = ?
        LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $candidate ?: null;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['candidate_success_message'])) {
    $successMessage = (string) $_SESSION['candidate_success_message'];
    unset($_SESSION['candidate_success_message']);
} elseif (($_GET['profile_saved'] ?? '') === '1') {
    $successMessage = 'Τα στοιχεία σου αποθηκεύτηκαν επιτυχώς.';
}

$specialties = fetch_all_prepared(
    $conn,
    'SELECT id, title FROM specialties ORDER BY title ASC'
);

$candidate = load_candidate($conn, $userId);
if (!$candidate) {
    session_destroy();
    header('Location: ../../auth/login.php');
    exit;
}

$notificationSettings = [
    'notify_new_list' => 1,
    'notify_rank_change' => 1,
    'notify_specialty_stats' => 0,
];

$notificationStmt = $conn->prepare(
    'SELECT notify_new_list, notify_rank_change, notify_specialty_stats
     FROM candidate_notification_settings
     WHERE user_id = ?
     LIMIT 1'
);

if ($notificationStmt) {
    $notificationStmt->bind_param('i', $userId);
    $notificationStmt->execute();
    $notificationResult = $notificationStmt->get_result();
    $notificationRow = $notificationResult ? $notificationResult->fetch_assoc() : null;
    $notificationStmt->close();

    if ($notificationRow) {
        $notificationSettings = $notificationRow;
    } else {
        $insertNotificationStmt = $conn->prepare(
            'INSERT INTO candidate_notification_settings (user_id, notify_new_list, notify_rank_change, notify_specialty_stats)
             VALUES (?, 1, 1, 0)'
        );

        if ($insertNotificationStmt) {
            $insertNotificationStmt->bind_param('i', $userId);
            $insertNotificationStmt->execute();
            $insertNotificationStmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $identityNumber = normalize_identity_number($_POST['identity_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $fatherName = trim($_POST['father_name'] ?? '');
        $motherName = trim($_POST['mother_name'] ?? '');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $specialtyId = (int) ($_POST['specialty_id'] ?? 0);

        if ($firstName === '' || $lastName === '' || $identityNumber === '') {
            $errorMessage = 'Συμπλήρωσε υποχρεωτικά όνομα, επώνυμο και αριθμό ταυτότητας.';
        } elseif (!is_valid_identity_number($identityNumber)) {
            $errorMessage = identity_number_validation_message();
        } elseif ($birthDate !== '' && strtotime($birthDate) === false) {
            $errorMessage = 'Η ημερομηνία γέννησης δεν είναι έγκυρη.';
        } else {
            $validSpecialty = null;
            foreach ($specialties as $specialty) {
                if ((int) $specialty['id'] === $specialtyId) {
                    $validSpecialty = $specialtyId;
                    break;
                }
            }

            $birthDateValue = $birthDate !== '' ? $birthDate : null;
            $phoneValue = $phone !== '' ? $phone : null;
            $fatherNameValue = $fatherName !== '' ? $fatherName : null;
            $motherNameValue = $motherName !== '' ? $motherName : null;

            $conn->begin_transaction();

            try {
                $identityCheckStmt = $conn->prepare('SELECT user_id AS id FROM user_profiles WHERE identity_number = ? AND user_id <> ? LIMIT 1');
                if (!$identityCheckStmt) {
                    throw new RuntimeException('Δεν ήταν δυνατός ο έλεγχος του αριθμού ταυτότητας.');
                }

                $identityCheckStmt->bind_param('si', $identityNumber, $userId);
                $identityCheckStmt->execute();
                $identityCheckStmt->store_result();
                $identityExists = $identityCheckStmt->num_rows > 0;
                $identityCheckStmt->close();

                if ($identityExists) {
                    throw new RuntimeException('Ο αριθμός ταυτότητας χρησιμοποιείται ήδη από άλλο χρήστη.');
                }

                $userUpdateStmt = $conn->prepare('UPDATE user_profiles SET first_name = ?, last_name = ?, identity_number = ?, phone = ? WHERE user_id = ?');
                if (!$userUpdateStmt) {
                    throw new RuntimeException('Δεν ήταν δυνατή η ενημέρωση των βασικών στοιχείων.');
                }

                $userUpdateStmt->bind_param('ssssi', $firstName, $lastName, $identityNumber, $phoneValue, $userId);
                if (!$userUpdateStmt->execute()) {
                    throw new RuntimeException('Προέκυψε σφάλμα κατά την αποθήκευση των στοιχείων σου.');
                }
                $userUpdateStmt->close();

                if (!empty($candidate['profile_id'])) {
                    $profileUpdateStmt = $conn->prepare(
                        'UPDATE candidate_profiles
                         SET father_name = ?, mother_name = ?, birth_date = ?, specialty_id = ?
                         WHERE id = ?'
                    );

                    if (!$profileUpdateStmt) {
                        throw new RuntimeException('Δεν ήταν δυνατή η ενημέρωση του προφίλ υποψηφίου.');
                    }

                    $profileId = (int) $candidate['profile_id'];
                    $profileUpdateStmt->bind_param('sssii', $fatherNameValue, $motherNameValue, $birthDateValue, $validSpecialty, $profileId);

                    if (!$profileUpdateStmt->execute()) {
                        throw new RuntimeException('Προέκυψε σφάλμα κατά την αποθήκευση του προφίλ υποψηφίου.');
                    }
                    $profileUpdateStmt->close();
                } else {
                    $profileInsertStmt = $conn->prepare(
                        'INSERT INTO candidate_profiles
                         (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points)
                         VALUES (?, ?, ?, ?, ?, ?, NULL, NULL)'
                    );

                    if (!$profileInsertStmt) {
                        throw new RuntimeException('Δεν ήταν δυνατή η δημιουργία προφίλ υποψηφίου.');
                    }

                    $defaultStatus = 'Νέα εγγραφή υποψηφίου';
                    $profileInsertStmt->bind_param('isssis', $userId, $fatherNameValue, $motherNameValue, $birthDateValue, $validSpecialty, $defaultStatus);

                    if (!$profileInsertStmt->execute()) {
                        throw new RuntimeException('Προέκυψε σφάλμα κατά τη δημιουργία του προφίλ υποψηφίου.');
                    }
                    $profileInsertStmt->close();
                }

                $conn->commit();
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['candidate_success_message'] = u('\\u03A4\\u03B1 \\u03C3\\u03C4\\u03BF\\u03B9\\u03C7\\u03B5\\u03AF\\u03B1 \\u03C3\\u03BF\\u03C5 \\u03B1\\u03C0\\u03BF\\u03B8\\u03B7\\u03BA\\u03B5\\u03CD\\u03C4\\u03B7\\u03BA\\u03B1\\u03BD \\u03B5\\u03C0\\u03B9\\u03C4\\u03C5\\u03C7\\u03CE\\u03C2.');
                header('Location: myprofile.php?profile_saved=1');
                exit;
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = u('\\u03A0\\u03B1\\u03C1\\u03BF\\u03C5\\u03C3\\u03B9\\u03AC\\u03C3\\u03C4\\u03B7\\u03BA\\u03B5 \\u03C0\\u03C1\\u03CC\\u03B2\\u03BB\\u03B7\\u03BC\\u03B1 \\u03BA\\u03B1\\u03C4\\u03AC \\u03C4\\u03B7\\u03BD \\u03B5\\u03BD\\u03B7\\u03BC\\u03AD\\u03C1\\u03C9\\u03C3\\u03B7 \\u03C4\\u03BF\\u03C5 \\u03C0\\u03C1\\u03BF\\u03C6\\u03AF\\u03BB \\u03C3\\u03BF\\u03C5. \\u0394\\u03BF\\u03BA\\u03AF\\u03BC\\u03B1\\u03C3\\u03B5 \\u03BE\\u03B1\\u03BD\\u03AC.');
            }
        }
    }

    if ($action === 'save_notifications') {
        $notifyNewList = isset($_POST['notify_new_list']) ? 1 : 0;
        $notifyRankChange = isset($_POST['notify_rank_change']) ? 1 : 0;
        $notifySpecialtyStats = isset($_POST['notify_specialty_stats']) ? 1 : 0;

        $saveNotificationStmt = $conn->prepare(
            'INSERT INTO candidate_notification_settings (user_id, notify_new_list, notify_rank_change, notify_specialty_stats)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                notify_new_list = VALUES(notify_new_list),
                notify_rank_change = VALUES(notify_rank_change),
                notify_specialty_stats = VALUES(notify_specialty_stats)'
        );

        if ($saveNotificationStmt) {
            $saveNotificationStmt->bind_param('iiii', $userId, $notifyNewList, $notifyRankChange, $notifySpecialtyStats);
            if ($saveNotificationStmt->execute()) {
                $notificationSettings = [
                    'notify_new_list' => $notifyNewList,
                    'notify_rank_change' => $notifyRankChange,
                    'notify_specialty_stats' => $notifySpecialtyStats,
                ];
                $successMessage = 'Οι ρυθμίσεις ειδοποιήσεων αποθηκεύτηκαν επιτυχώς.';
            } else {
                $errorMessage = 'Δεν ήταν δυνατή η αποθήκευση των ρυθμίσεων ειδοποιήσεων.';
            }
            $saveNotificationStmt->close();
        }
    }
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $passwordStmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $passwordRow = null;
        if ($passwordStmt) {
            $passwordStmt->bind_param('i', $userId);
            $passwordStmt->execute();
            $passwordResult = $passwordStmt->get_result();
            $passwordRow = $passwordResult ? $passwordResult->fetch_assoc() : null;
            $passwordStmt->close();
        }

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errorMessage = 'Συμπλήρωσε και τα τρία πεδία αλλαγής κωδικού.';
        } elseif (!$passwordRow || !password_verify($currentPassword, $passwordRow['password_hash'])) {
            $errorMessage = 'Ο τρέχων κωδικός πρόσβασης δεν είναι σωστός.';
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = 'Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'Η επιβεβαίωση του νέου κωδικού δεν ταιριάζει.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePasswordStmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');

            if ($updatePasswordStmt) {
                $updatePasswordStmt->bind_param('si', $hashedPassword, $userId);
                if ($updatePasswordStmt->execute()) {
                    $successMessage = 'Ο κωδικός σου άλλαξε επιτυχώς.';
                } else {
                    $errorMessage = 'Η αλλαγή κωδικού απέτυχε.';
                }
                $updatePasswordStmt->close();
            }
        }
    }

    if ($action === 'track_candidate') {
        $targetProfileId = (int) ($_POST['candidate_profile_id'] ?? 0);

        if ($targetProfileId <= 0) {
            $errorMessage = 'Επίλεξε έναν υποψήφιο για παρακολούθηση.';
        } else {
            $checkTrackStmt = $conn->prepare(
                'SELECT id FROM tracked_candidates WHERE user_id = ? AND candidate_profile_id = ? LIMIT 1'
            );

            if ($checkTrackStmt) {
                $checkTrackStmt->bind_param('ii', $userId, $targetProfileId);
                $checkTrackStmt->execute();
                $trackResult = $checkTrackStmt->get_result();
                if ($trackResult && $trackResult->num_rows > 0) {
                    $errorMessage = 'Ο υποψήφιος υπάρχει ήδη στη λίστα παρακολούθησής σου.';
                }
                $checkTrackStmt->close();
            }

            if ($errorMessage === '') {
                $insertTrackStmt = $conn->prepare('INSERT INTO tracked_candidates (user_id, candidate_profile_id) VALUES (?, ?)');
                if ($insertTrackStmt) {
                    $insertTrackStmt->bind_param('ii', $userId, $targetProfileId);
                    if ($insertTrackStmt->execute()) {
                        $successMessage = 'Ο υποψήφιος προστέθηκε στη λίστα παρακολούθησής σου.';
                    } else {
                        $errorMessage = 'Δεν ήταν δυνατή η προσθήκη του υποψηφίου στη λίστα παρακολούθησης.';
                    }
                    $insertTrackStmt->close();
                }
            }
        }
    }

    if ($action === 'remove_tracked_candidate') {
        $trackedId = (int) ($_POST['tracked_id'] ?? 0);

        if ($trackedId <= 0) {
            $errorMessage = 'Δεν ήταν δυνατή η επιλογή εγγραφής παρακολούθησης.';
        } else {
            $deleteTrackStmt = $conn->prepare('DELETE FROM tracked_candidates WHERE id = ? AND user_id = ?');
            if ($deleteTrackStmt) {
                $deleteTrackStmt->bind_param('ii', $trackedId, $userId);
                if ($deleteTrackStmt->execute() && $deleteTrackStmt->affected_rows > 0) {
                    $successMessage = 'Ο υποψήφιος αφαιρέθηκε από τη λίστα παρακολούθησής σου.';
                } else {
                    $errorMessage = 'Δεν βρέθηκε η εγγραφή παρακολούθησης που ζήτησες.';
                }
                $deleteTrackStmt->close();
            } else {
                $errorMessage = 'Δεν ήταν δυνατή η αφαίρεση από τη λίστα παρακολούθησης.';
            }
        }
    }

    $candidate = load_candidate($conn, $userId) ?: $candidate;
}

$candidateAge = null;
if (!empty($candidate['birth_date'])) {
    $candidateAge = (int) date_diff(date_create($candidate['birth_date']), date_create('today'))->y;
}

$applicationStage = 'Δεν έχει ολοκληρωθεί ακόμη η σύνδεση του προφίλ σου με υποψήφιο πίνακα.';
$applicationProgress = 20;

if (!empty($candidate['profile_id']) && !empty($candidate['specialty_title'])) {
    $applicationStage = 'Το προφίλ σου είναι συνδεδεμένο με ειδικότητα και μπορεί να παρακολουθεί την πορεία σου.';
    $applicationProgress = 55;
}

if ($candidate['ranking_position'] !== null) {
    $applicationStage = 'Έχει εντοπιστεί θέση στον πίνακα και η παρακολούθηση της αίτησής σου είναι ενεργή.';
    $applicationProgress = 82;
}

$applicationTimeline = [
    [
        'label' => 'Στοιχεία προφίλ',
        'text' => 'Βασικά προσωπικά στοιχεία και αριθμός ταυτότητας.',
        'done' => !empty($candidate['first_name']) && !empty($candidate['last_name']) && !empty($candidate['identity_number']),
    ],
    [
        'label' => 'Σύνδεση ειδικότητας',
        'text' => 'Το προφίλ έχει συνδεθεί με ειδικότητα πίνακα.',
        'done' => !empty($candidate['profile_id']) && !empty($candidate['specialty_title']),
    ],
    [
        'label' => 'Κατάσταση αίτησης',
        'text' => 'Υπάρχει διαθέσιμη κατάσταση για την τρέχουσα εγγραφή.',
        'done' => !empty($candidate['application_status']),
    ],
    [
        'label' => 'Θέση και μόρια',
        'text' => 'Έχουν καταχωριστεί θέση πίνακα ή μόρια.',
        'done' => $candidate['ranking_position'] !== null || $candidate['points'] !== null,
    ],
];

$myTrackCount = 0;
$trackCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM tracked_candidates WHERE user_id = ?');
if ($trackCountStmt) {
    $trackCountStmt->bind_param('i', $userId);
    $trackCountStmt->execute();
    $trackCountResult = $trackCountStmt->get_result();
    $trackCountRow = $trackCountResult ? $trackCountResult->fetch_assoc() : null;
    $myTrackCount = (int) ($trackCountRow['total'] ?? 0);
    $trackCountStmt->close();
}

$searchName = trim($_GET['search_name'] ?? '');
$searchSpecialtyId = (int) ($_GET['search_specialty_id'] ?? 0);
$searchResults = [];

$searchWildcard = '%' . $searchName . '%';
$searchStmt = $conn->prepare(
    'SELECT
        cp.id AS profile_id,
        up.first_name,
        up.last_name,
        s.title AS specialty_title,
        cp.ranking_position,
        cp.points,
        cp.application_status
     FROM candidate_profiles cp
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN user_profiles up ON up.user_id = u.id
     LEFT JOIN specialties s ON s.id = cp.specialty_id
     LEFT JOIN tracked_candidates tc ON tc.candidate_profile_id = cp.id AND tc.user_id = ?
     WHERE u.id <> ?
       AND tc.id IS NULL
       AND (? = "" OR up.first_name LIKE ? OR up.last_name LIKE ? OR CONCAT(up.first_name, " ", up.last_name) LIKE ?)
       AND (? = 0 OR cp.specialty_id = ?)
     ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, up.last_name ASC
     LIMIT 12'
);

if ($searchStmt) {
    $searchStmt->bind_param(
        'iissssii',
        $userId,
        $userId,
        $searchName,
        $searchWildcard,
        $searchWildcard,
        $searchWildcard,
        $searchSpecialtyId,
        $searchSpecialtyId
    );
    $searchStmt->execute();
    $searchResult = $searchStmt->get_result();
    if ($searchResult) {
        while ($row = $searchResult->fetch_assoc()) {
            $searchResults[] = $row;
        }
    }
    $searchStmt->close();
}
$trackedRows = [];
$trackedStmt = $conn->prepare(
    'SELECT
        tc.id AS tracked_id,
        tc.created_at,
        up.first_name,
        up.last_name,
        s.title AS specialty_title,
        cp.ranking_position,
        cp.points,
        cp.application_status
     FROM tracked_candidates tc
     INNER JOIN candidate_profiles cp ON cp.id = tc.candidate_profile_id
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN user_profiles up ON up.user_id = u.id
     LEFT JOIN specialties s ON s.id = cp.specialty_id
     WHERE tc.user_id = ?
     ORDER BY tc.created_at DESC'
);

if ($trackedStmt) {
    $trackedStmt->bind_param('i', $userId);
    $trackedStmt->execute();
    $trackedResult = $trackedStmt->get_result();
    if ($trackedResult) {
        while ($row = $trackedResult->fetch_assoc()) {
            $trackedRows[] = $row;
        }
    }
    $trackedStmt->close();
}

$candidatePageTitles = [
    'dashboard' => APP_NAME . ' | Πίνακας Υποψηφίου',
    'profile' => APP_NAME . ' | Το προφίλ μου',
    'applications' => APP_NAME . ' | Η πορεία της αίτησής μου',
    'others' => APP_NAME . ' | Παρακολούθηση υποψηφίων',
];

$pageTitle = $candidatePageTitles[$candidatePage] ?? $candidatePageTitles['dashboard'];

$candidateHeroMeta = [
    'dashboard' => [
        'eyebrow' => 'Προσωπικός χώρος',
        'title' => u('\u039A\u03B1\u03BB\u03CE\u03C2 \u03AE\u03C1\u03B8\u03B5\u03C2, ') . ($candidate['first_name'] ?? ''),
        'description' => 'Εδώ συγκεντρώνονται τα προσωπικά σου στοιχεία, η πορεία της αίτησής σου και οι υποψήφιοι που έχεις επιλέξει να παρακολουθείς.',
    ],
    'profile' => [
        'eyebrow' => 'Στοιχεία λογαριασμού',
        'title' => 'Το προφίλ μου',
        'description' => 'Έλεγξε και ενημέρωσε τα προσωπικά σου στοιχεία, τις προτιμήσεις ειδοποιήσεων και τον κωδικό πρόσβασης.',
    ],
    'applications' => [
        'eyebrow' => 'Πορεία αίτησης',
        'title' => 'Η πορεία της αίτησής μου',
        'description' => 'Παρακολούθησε την κατάσταση, τη θέση, τα μόρια και τα βασικά στάδια της εγγραφής σου.',
    ],
    'others' => [
        'eyebrow' => 'Παρακολούθηση',
        'title' => 'Υποψήφιοι που παρακολουθώ',
        'description' => 'Αναζήτησε υποψηφίους, σύγκρινε βασικά στοιχεία και κράτησε τη δική σου λίστα παρακολούθησης.',
    ],
];

$candidateHero = $candidateHeroMeta[$candidatePage] ?? $candidateHeroMeta['dashboard'];
$bodyClass = 'theme-candidate';
$currentPage = 'candidate';
$navBase = '../';
$headerActionLabel = 'Επεξεργασία Προφίλ';
$headerActionHref = '#profile';

require __DIR__ . '/../../includes/header.php';
?>
<main class="container">
    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success"><?php echo h($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($candidatePage !== 'dashboard'): ?>
        <div class="candidate-page-bar">
            <a class="back-link" href="candidatedashboard.php">&larr; Πίσω στον πίνακα μου</a>
            <span><?php echo h($candidateHero['title']); ?></span>
        </div>
    <?php endif; ?>

    <section class="page-hero <?php echo $candidatePage !== 'dashboard' ? 'page-hero-compact' : ''; ?>" aria-labelledby="candTitle">
        <div class="hero-text">
            <span class="eyebrow-home"><?php echo h($candidateHero['eyebrow']); ?></span>
            <h1 id="candTitle"><?php echo h($candidateHero['title']); ?></h1>
            <p class="muted"><?php echo h($candidateHero['description']); ?></p>
        </div>

        <?php if ($candidatePage === 'dashboard'): ?>
        <div class="hero-badges">
            <div class="badge">
                <span class="badge-label">Λογαριασμός</span>
                <span class="badge-value">Ενεργός</span>
            </div>
            <div class="badge">
                <span class="badge-label">Ειδικότητα</span>
                <span class="badge-value"><?php echo h(candidate_value($candidate['specialty_title'] ?? null, 'Δεν έχει οριστεί')); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($candidatePage === 'dashboard'): ?>
    <section class="hero-metrics" aria-label="Σύνοψη υποψηφίου">
        <article class="metric-card">
            <span class="metric-label">Κατάσταση αίτησης</span>
            <span class="metric-value"><?php echo h(candidate_value($candidate['application_status'] ?? null, 'Σε επεξεργασία')); ?></span>
            <p class="metric-note">Συνοπτική εικόνα της τρέχουσας πορείας της αίτησής σου.</p>
        </article>
        <article class="metric-card">
            <span class="metric-label">Θέση πίνακα</span>
            <span class="metric-value"><?php echo $candidate['ranking_position'] !== null ? (int) $candidate['ranking_position'] : '—'; ?></span>
            <p class="metric-note">Εμφανίζεται όταν είναι διαθέσιμη επίσημη κατάταξη.</p>
        </article>
        <article class="metric-card">
            <span class="metric-label">Παρακολουθήσεις</span>
            <span class="metric-value"><?php echo $myTrackCount; ?></span>
            <p class="metric-note">Υποψήφιοι που έχεις προσθέσει στη λίστα σύγκρισης.</p>
        </article>
    </section>
    <?php endif; ?>

    <?php if ($candidatePage === 'dashboard'): ?>
    <section class="section-head" aria-label="Εισαγωγή πίνακα υποψηφίου">
        <h2>Ο προσωπικός μου πίνακας</h2>
        <p>Διαχειρίσου τα στοιχεία σου, δες την πορεία της αίτησής σου και κράτησε συγκεντρωμένους τους υποψηφίους που θέλεις να παρακολουθείς.</p>
    </section>

    <section class="grid grid-admin" aria-label="Γρήγορες ενότητες υποψηφίου">
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">1</div>
            <h2>Το προφίλ μου</h2>
            <p>Έλεγξε τα προσωπικά σου στοιχεία, ενημέρωσε τηλέφωνο και ειδικότητα και κράτησε σταθερό το email επικοινωνίας.</p>
            <div class="card-actions"><a class="btn" href="myprofile.php">Προβολή προφίλ</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">2</div>
            <h2>Η πορεία της αίτησής μου</h2>
            <p>Δες συγκεντρωμένα την κατάσταση, τα μόρια, τη θέση στον πίνακα και τα στάδια που έχουν ολοκληρωθεί.</p>
            <div class="card-actions"><a class="btn" href="track_applications.php">Προβολή πορείας</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">3</div>
            <h2>Παρακολούθηση άλλων</h2>
            <p>Βρες υποψηφίους που σε ενδιαφέρουν και κράτησέ τους σε προσωπική λίστα για εύκολη σύγκριση.</p>
            <div class="card-actions"><a class="btn" href="track_others.php">Διαχείριση λίστας</a></div>
        </article>
    </section>

    <?php endif; ?>

    <?php if ($candidatePage === 'profile'): ?>
    <section class="panel" id="profile" aria-labelledby="profileTitle">
        <div class="panel-head">
            <h2 id="profileTitle">Το προφίλ μου</h2>
            <p class="muted">Τα στοιχεία αυτά χρησιμοποιούνται για την ταυτοποίηση και την παρακολούθηση της εγγραφής σου στους πίνακες.</p>
        </div>

        <form class="form-grid candidate-form" method="post" action="myprofile.php">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="first_name">Όνομα</label>
                <input id="first_name" name="first_name" type="text" value="<?php echo h($candidate['first_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="last_name">Επώνυμο</label>
                <input id="last_name" name="last_name" type="text" value="<?php echo h($candidate['last_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Τηλέφωνο</label>
                <input id="phone" name="phone" type="text" value="<?php echo h($candidate['phone'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" value="<?php echo h($candidate['email']); ?>" disabled>
            </div>

            <div class="form-group">
                <label for="identity_number">Αριθμός ταυτότητας</label>
                <input id="identity_number" name="identity_number" type="text" value="<?php echo h($candidate['identity_number'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="father_name">Όνομα πατέρα</label>
                <input id="father_name" name="father_name" type="text" value="<?php echo h($candidate['father_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="mother_name">Όνομα μητέρας</label>
                <input id="mother_name" name="mother_name" type="text" value="<?php echo h($candidate['mother_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="birth_date">Ημερομηνία γέννησης</label>
                <input id="birth_date" name="birth_date" type="date" value="<?php echo h($candidate['birth_date'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="specialty_id">Ειδικότητα</label>
                <select id="specialty_id" name="specialty_id">
                    <option value="0">Επιλογή ειδικότητας</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo (int) $specialty['id']; ?>" <?php echo (int) ($candidate['specialty_id'] ?? 0) === (int) $specialty['id'] ? 'selected' : ''; ?>>
                            <?php echo h($specialty['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group form-actions">
                <button class="btn btn-primary" type="submit">Αποθήκευση Στοιχείων</button>
            </div>
        </form>
    </section>
    <section class="split-panel" aria-label="Ρυθμίσεις λογαριασμού υποψηφίου">
        <div class="panel panel-nested" id="notifications">
            <div class="panel-head">
                <h3>Ειδοποιήσεις</h3>
                <p class="muted">Επίλεξε ποιες ειδοποιήσεις θέλεις να λαμβάνεις, όπως νέα λίστα ή αλλαγή θέσης.</p>
            </div>
            <form method="post" action="myprofile.php#notifications">
                <input type="hidden" name="action" value="save_notifications">
                <div class="check-list">
                    <label class="check-item">
                        <input type="checkbox" name="notify_new_list" <?php echo (int) $notificationSettings['notify_new_list'] === 1 ? 'checked' : ''; ?>>
                        <span>Ενημέρωση για νέες λίστες</span>
                    </label>
                    <label class="check-item">
                        <input type="checkbox" name="notify_rank_change" <?php echo (int) $notificationSettings['notify_rank_change'] === 1 ? 'checked' : ''; ?>>
                        <span>Ενημέρωση για αλλαγή θέσης</span>
                    </label>
                    <label class="check-item">
                        <input type="checkbox" name="notify_specialty_stats" <?php echo (int) $notificationSettings['notify_specialty_stats'] === 1 ? 'checked' : ''; ?>>
                        <span>Στατιστικά ειδικότητας</span>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Αποθήκευση Ρυθμίσεων</button>
            </form>
        </div>

        <div class="panel panel-nested" id="candidate-password">
            <div class="panel-head">
                <h3>Αλλαγή Κωδικού</h3>
                <p class="muted">Χρησιμοποίησε ισχυρό κωδικό και άλλαξέ τον όταν θέλεις να ανανεώσεις την ασφάλεια του λογαριασμού σου.</p>
            </div>
            <form method="post" action="myprofile.php#candidate-password">
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
                <button class="btn btn-primary" type="submit">Αλλαγή Κωδικού</button>
            </form>
        </div>
    </section>

    <?php endif; ?>

    <?php if ($candidatePage === 'applications'): ?>
    <section class="panel" id="track-my-applications" aria-labelledby="statusTitle">
        <div class="panel-head">
            <h2 id="statusTitle">Η πορεία της αίτησής μου</h2>
            <p class="muted">Συγκεντρωτική εικόνα της εγγραφής σου, με πρόοδο, βασικά στοιχεία και τελευταία διαθέσιμη ενημέρωση.</p>
        </div>

        <div class="dashboard-columns">
            <div class="chart-card">
                <h3>Στάδιο αίτησης</h3>
                <p class="muted"><?php echo h($applicationStage); ?></p>
                <div class="progress-track" aria-label="Πρόοδος αίτησης">
                    <div class="progress-value" style="width: <?php echo $applicationProgress; ?>%"></div>
                </div>
                <div class="year-list">
                    <div class="year-item"><span>Προφίλ</span><strong><?php echo !empty($candidate['profile_id']) ? 'Ολοκληρωμένο' : 'Εκκρεμεί'; ?></strong></div>
                    <div class="year-item"><span>Ειδικότητα</span><strong><?php echo !empty($candidate['specialty_title']) ? 'Συνδεδεμένη' : 'Εκκρεμεί'; ?></strong></div>
                    <div class="year-item"><span>Θέση πίνακα</span><strong><?php echo $candidate['ranking_position'] !== null ? 'Διαθέσιμη' : 'Σε αναμονή'; ?></strong></div>
                </div>
                <div class="application-timeline" aria-label="Χρονογραμμή αίτησης">
                    <?php foreach ($applicationTimeline as $step): ?>
                        <div class="timeline-step <?php echo $step['done'] ? 'is-done' : ''; ?>">
                            <span class="timeline-dot" aria-hidden="true"></span>
                            <div>
                                <strong><?php echo h($step['label']); ?></strong>
                                <p><?php echo h($step['text']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-stack">
                <div class="chart-card">
                    <h3>Βασικά στοιχεία</h3>
                    <div class="info-list">
                        <div class="info-row"><span>Ονοματεπώνυμο</span><strong><?php echo h($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong></div>
                        <div class="info-row"><span>Email</span><strong><?php echo h($candidate['email']); ?></strong></div>
                        <div class="info-row"><span>Αριθμός ταυτότητας</span><strong><?php echo h(candidate_value($candidate['identity_number'] ?? null)); ?></strong></div>
                        <div class="info-row"><span>Ηλικία</span><strong><?php echo $candidateAge !== null ? $candidateAge . ' ετών' : '—'; ?></strong></div>
                        <div class="info-row"><span>Μόρια</span><strong><?php echo $candidate['points'] !== null ? number_format((float) $candidate['points'], 2) : '—'; ?></strong></div>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Τελευταία ενημέρωση</h3>
                    <div class="info-list">
                        <div class="info-row"><span>Κατάσταση</span><strong><?php echo h(candidate_value($candidate['application_status'] ?? null, 'Δεν υπάρχει ακόμη ενημέρωση')); ?></strong></div>
                        <div class="info-row"><span>Ημερομηνία εγγραφής</span><strong><?php echo !empty($candidate['profile_created_at']) ? h(date('d/m/Y H:i', strtotime($candidate['profile_created_at']))) : 'Δεν υπάρχει'; ?></strong></div>
                        <div class="info-row"><span>Παρακολουθήσεις</span><strong><?php echo $myTrackCount; ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php endif; ?>

    <?php if ($candidatePage === 'others'): ?>
    <section class="panel" id="track-others" aria-labelledby="trackOthersTitle">
        <div class="panel-head">
            <h2 id="trackOthersTitle">Υποψήφιοι που παρακολουθώ</h2>
            <p class="muted">Αναζήτησε υποψηφίους με βάση ονοματεπώνυμο ή ειδικότητα και πρόσθεσε όσους θέλεις στη λίστα σου.</p>
        </div>

        <form class="form-grid" method="get" action="track_others.php#track-others">
            <div class="form-group">
                <label for="search_name">Ονοματεπώνυμο</label>
                <input id="search_name" name="search_name" type="text" value="<?php echo h($searchName); ?>" placeholder="π.χ. Μαρία Παπαδοπούλου">
            </div>
            <div class="form-group">
                <label for="search_specialty_id">Ειδικότητα</label>
                <select id="search_specialty_id" name="search_specialty_id">
                    <option value="0">Όλες οι ειδικότητες</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo (int) $specialty['id']; ?>" <?php echo $searchSpecialtyId === (int) $specialty['id'] ? 'selected' : ''; ?>>
                            <?php echo h($specialty['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-actions">
                <button class="btn btn-primary" type="submit">Αναζήτηση</button>
                <a class="btn btn-secondary" href="track_others.php#track-others">Καθαρισμός</a>
            </div>
        </form>

        <div class="table-titlebar">
            <h3>Αποτελέσματα Αναζήτησης</h3>
            <p class="panel-subtitle"><?php echo count($searchResults); ?> εγγραφές</p>
        </div>
        <div class="table-wrap" role="region" aria-label="Αποτελέσματα αναζήτησης υποψηφίων">
            <table class="table">
                <thead>
                    <tr>
                        <th>Υποψήφιος</th>
                        <th>Ειδικότητα</th>
                        <th>Θέση</th>
                        <th>Μόρια</th>
                        <th>Κατάσταση</th>
                        <th class="right">Ενέργεια</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($searchResults === []): ?>
                        <tr><td colspan="6" class="empty-cell">Δεν βρέθηκαν υποψήφιοι με αυτά τα κριτήρια.</td></tr>
                    <?php else: ?>
                        <?php foreach ($searchResults as $row): ?>
                            <tr>
                                <td><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo h(candidate_value($row['specialty_title'] ?? null)); ?></td>
                                <td><?php echo $row['ranking_position'] !== null ? (int) $row['ranking_position'] : '—'; ?></td>
                                <td><?php echo $row['points'] !== null ? number_format((float) $row['points'], 2) : '—'; ?></td>
                                <td><?php echo h(candidate_value($row['application_status'] ?? null)); ?></td>
                                <td class="right">
                                    <form method="post" action="track_others.php#track-others">
                                        <input type="hidden" name="action" value="track_candidate">
                                        <input type="hidden" name="candidate_profile_id" value="<?php echo (int) $row['profile_id']; ?>">
                                        <button class="btn btn-small" type="submit">Παρακολούθηση</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-titlebar">
            <h3>Η λίστα παρακολούθησής μου</h3>
            <p class="panel-subtitle">Σύνολο: <?php echo count($trackedRows); ?></p>
        </div>
        <div class="table-wrap" role="region" aria-label="Λίστα παρακολούθησης">
            <table class="table">
                <thead>
                    <tr>
                        <th>Υποψήφιος</th>
                        <th>Ειδικότητα</th>
                        <th>Θέση</th>
                        <th>Μόρια</th>
                        <th>Κατάσταση</th>
                        <th>Ημερομηνία προσθήκης</th>
                        <th class="right">Ενέργεια</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($trackedRows === []): ?>
                        <tr><td colspan="7" class="empty-cell">Δεν έχεις προσθέσει ακόμη κανέναν υποψήφιο στη λίστα παρακολούθησης.</td></tr>
                    <?php else: ?>
                        <?php foreach ($trackedRows as $row): ?>
                            <tr>
                                <td><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo h(candidate_value($row['specialty_title'] ?? null)); ?></td>
                                <td><?php echo $row['ranking_position'] !== null ? (int) $row['ranking_position'] : '—'; ?></td>
                                <td><?php echo $row['points'] !== null ? number_format((float) $row['points'], 2) : '—'; ?></td>
                                <td><?php echo h(candidate_value($row['application_status'] ?? null)); ?></td>
                                <td><?php echo h(date('d/m/Y H:i', strtotime($row['created_at']))); ?></td>
                                <td class="right">
                                    <form method="post" action="track_others.php#track-others">
                                        <input type="hidden" name="action" value="remove_tracked_candidate">
                                        <input type="hidden" name="tracked_id" value="<?php echo (int) $row['tracked_id']; ?>">
                                        <button class="btn btn-small btn-danger" type="submit">Αφαίρεση</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php if (($_GET['profile_saved'] ?? '') === '1'): ?>
<script>
    window.addEventListener('load', function () {
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    });
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
