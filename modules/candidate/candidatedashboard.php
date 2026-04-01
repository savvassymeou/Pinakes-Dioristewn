<?php
session_start();

require_once __DIR__ . '/../../includes/auth.php';
require_role('candidate', '../../auth/login.php', '../admin/dashboard.php', 'candidatedashboard.php');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

ensure_identity_number_column($conn);

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
            u.first_name,
            u.last_name,
            u.email,
            u.identity_number,
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
            cp.created_at AS profile_created_at,
            s.title AS specialty_title
        FROM users u
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

$specialties = [];
$specialtiesResult = $conn->query('SELECT id, title FROM specialties ORDER BY title ASC');
if ($specialtiesResult) {
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row;
    }
}

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
                $identityCheckStmt = $conn->prepare('SELECT id FROM users WHERE identity_number = ? AND id <> ? LIMIT 1');
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

                $userUpdateStmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, identity_number = ?, phone = ? WHERE id = ?');
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
                $successMessage = 'Το προφίλ σου ενημερώθηκε επιτυχώς.';
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = $exception->getMessage();
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

$searchSql = 'SELECT
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
WHERE u.id <> ?';

$searchTypes = 'i';
$searchParams = [$userId];

if ($searchName !== '') {
    $searchSql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
    $searchWildcard = '%' . $searchName . '%';
    $searchParams[] = $searchWildcard;
    $searchParams[] = $searchWildcard;
    $searchParams[] = $searchWildcard;
    $searchTypes .= 'sss';
}

if ($searchSpecialtyId > 0) {
    $searchSql .= ' AND cp.specialty_id = ?';
    $searchParams[] = $searchSpecialtyId;
    $searchTypes .= 'i';
}

$searchSql .= ' ORDER BY cp.ranking_position IS NULL, cp.ranking_position ASC, u.last_name ASC LIMIT 12';
$searchStmt = $conn->prepare($searchSql);

if ($searchStmt) {
    $searchStmt->bind_param($searchTypes, ...$searchParams);
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

$pageTitle = APP_NAME . ' | Candidate Dashboard';
$bodyClass = 'theme-candidate';
$currentPage = 'candidate';
$navBase = '../';
$headerActionLabel = 'Επεξεργασία Προφίλ';
$headerActionHref = '#profile';

require __DIR__ . '/../../includes/header.php';
?>
<main class="container">
    <section class="page-hero" aria-labelledby="candTitle">
        <div class="hero-text">
            <span class="eyebrow-home">Candidate Workspace</span>
            <h1 id="candTitle">Καλώς ήρθες, <?php echo h($candidate['first_name']); ?></h1>
            <p class="muted">Το Candidate Dashboard είναι ο ιδιωτικός χώρος του υποψηφίου. Από εδώ οδηγείσαι στις 3 βασικές ενότητες του module: My Profile, Track My Applications και Track Others.</p>
        </div>

        <div class="hero-badges">
            <div class="badge">
                <span class="badge-label">Ρόλος</span>
                <span class="badge-value">Υποψήφιος</span>
            </div>
            <div class="badge">
                <span class="badge-label">Ειδικότητα</span>
                <span class="badge-value"><?php echo h(candidate_value($candidate['specialty_title'] ?? null, 'Δεν έχει οριστεί')); ?></span>
            </div>
        </div>
    </section>

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

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success"><?php echo h($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
    <?php endif; ?>

    <section class="section-head" aria-label="Candidate dashboard intro">
        <h2>Dashboard Υποψηφίου</h2>
        <p>Οι 3 βασικές ενότητες του Candidate Module είναι οι παρακάτω. Από εδώ ο υποψήφιος μεταβαίνει στο προφίλ του, στην πορεία της αίτησής του και στην παρακολούθηση άλλων υποψηφίων.</p>
    </section>

    <section class="grid grid-admin" aria-label="Γρήγορες ενότητες υποψηφίου">
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">1</div>
            <h2>My Profile</h2>
            <p>Δες και ενημέρωσε όνομα, επώνυμο και τηλέφωνο, ενώ το email παραμένει σταθερό ως στοιχείο επικοινωνίας.</p>
            <div class="card-actions"><a class="btn" href="#profile">Άνοιγμα</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">2</div>
            <h2>Track My Applications</h2>
            <p>Παρακολούθησε την κατάσταση της αίτησής σου, τα μόρια, τη θέση σου στον πίνακα και τη συνολική πρόοδο.</p>
            <div class="card-actions"><a class="btn" href="#track-my-applications">Άνοιγμα</a></div>
        </article>
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">3</div>
            <h2>Track Others</h2>
            <p>Επίλεξε άλλους υποψηφίους για παρακολούθηση και κράτησέ τους στη δική σου λίστα σύγκρισης.</p>
            <div class="card-actions"><a class="btn" href="#track-others">Άνοιγμα</a></div>
        </article>
    </section>

    <section class="panel" id="profile" aria-labelledby="profileTitle">
        <div class="panel-head">
            <h2 id="profileTitle">My Profile</h2>
            <p class="muted">Συμπλήρωσε και ενημέρωσε το προσωπικό σου προφίλ όπως πρέπει να εμφανίζεται στην εφαρμογή.</p>
        </div>

        <form class="form-grid candidate-form" method="post" action="#profile">
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
            <form method="post" action="#notifications">
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
                <p class="muted">Στο τέλος του candidate module μπορείς να αλλάξεις τον κωδικό πρόσβασής σου, όπως ζητά η εκφώνηση.</p>
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
                <button class="btn btn-primary" type="submit">Αλλαγή Κωδικού</button>
            </form>
        </div>
    </section>

    <section class="panel" id="track-my-applications" aria-labelledby="statusTitle">
        <div class="panel-head">
            <h2 id="statusTitle">Track My Applications</h2>
            <p class="muted">Συγκεντρωτική εικόνα της πορείας σου με πρόοδο, βασικά στοιχεία και τελευταία ενημέρωση.</p>
        </div>

        <div class="dashboard-columns">
            <div class="chart-card">
                <h3>Στάδιο Υποψηφιότητας</h3>
                <p class="muted"><?php echo h($applicationStage); ?></p>
                <div class="progress-track" aria-label="Πρόοδος αίτησης">
                    <div class="progress-value" style="width: <?php echo $applicationProgress; ?>%"></div>
                </div>
                <div class="year-list">
                    <div class="year-item"><span>Προφίλ</span><strong><?php echo !empty($candidate['profile_id']) ? 'Ολοκληρωμένο' : 'Εκκρεμεί'; ?></strong></div>
                    <div class="year-item"><span>Ειδικότητα</span><strong><?php echo !empty($candidate['specialty_title']) ? 'Συνδεδεμένη' : 'Εκκρεμεί'; ?></strong></div>
                    <div class="year-item"><span>Θέση πίνακα</span><strong><?php echo $candidate['ranking_position'] !== null ? 'Διαθέσιμη' : 'Σε αναμονή'; ?></strong></div>
                </div>
            </div>

            <div class="section-stack">
                <div class="chart-card">
                    <h3>Βασικά Στοιχεία</h3>
                    <div class="info-list">
                        <div class="info-row"><span>Ονοματεπώνυμο</span><strong><?php echo h($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong></div>
                        <div class="info-row"><span>Email</span><strong><?php echo h($candidate['email']); ?></strong></div>
                        <div class="info-row"><span>Αριθμός ταυτότητας</span><strong><?php echo h(candidate_value($candidate['identity_number'] ?? null)); ?></strong></div>
                        <div class="info-row"><span>Ηλικία</span><strong><?php echo $candidateAge !== null ? $candidateAge . ' ετών' : '—'; ?></strong></div>
                        <div class="info-row"><span>Μόρια</span><strong><?php echo $candidate['points'] !== null ? number_format((float) $candidate['points'], 2) : '—'; ?></strong></div>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Τελευταία Ενημέρωση</h3>
                    <div class="info-list">
                        <div class="info-row"><span>Κατάσταση</span><strong><?php echo h(candidate_value($candidate['application_status'] ?? null, 'Δεν υπάρχει ακόμη ενημέρωση')); ?></strong></div>
                        <div class="info-row"><span>Ημερομηνία προφίλ</span><strong><?php echo !empty($candidate['profile_created_at']) ? h(date('d/m/Y H:i', strtotime($candidate['profile_created_at']))) : 'Δεν υπάρχει'; ?></strong></div>
                        <div class="info-row"><span>Παρακολουθήσεις</span><strong><?php echo $myTrackCount; ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel" id="track-others" aria-labelledby="trackOthersTitle">
        <div class="panel-head">
            <h2 id="trackOthersTitle">Track Others</h2>
            <p class="muted">Αναζήτησε άλλους υποψηφίους, σύγκρινε βασικά στοιχεία και πρόσθεσέ τους στη λίστα παρακολούθησής σου.</p>
        </div>

        <form class="form-grid" method="get" action="#track-others">
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
                <a class="btn btn-secondary" href="candidatedashboard.php#track-others">Καθαρισμός</a>
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
                                    <form method="post" action="#track-others">
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
            <h3>Η Λίστα Παρακολούθησής Μου</h3>
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
                        <th>Ημερομηνία προσθήκης</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($trackedRows === []): ?>
                        <tr><td colspan="5" class="empty-cell">Δεν έχεις προσθέσει ακόμη κανέναν υποψήφιο στη λίστα παρακολούθησης.</td></tr>
                    <?php else: ?>
                        <?php foreach ($trackedRows as $row): ?>
                            <tr>
                                <td><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo h(candidate_value($row['specialty_title'] ?? null)); ?></td>
                                <td><?php echo $row['ranking_position'] !== null ? (int) $row['ranking_position'] : '—'; ?></td>
                                <td><?php echo $row['points'] !== null ? number_format((float) $row['points'], 2) : '—'; ?></td>
                                <td><?php echo h(date('d/m/Y H:i', strtotime($row['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>


