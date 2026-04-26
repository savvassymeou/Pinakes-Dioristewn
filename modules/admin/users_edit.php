<?php
// Legacy route kept for compatibility; user management lives in admindashboard.php.

$target = 'admindashboard.php#manage-users';
if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $target = 'admindashboard.php?edit_user=' . (int) $_GET['id'] . '#manage-users';
}

header('Location: ' . $target);
exit;
