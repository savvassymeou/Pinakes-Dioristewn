<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION["role"] ?? "") === "admin") {
    header("Location: ../Admin/admindashboard.php");
    exit;
}

header("Location: ../Candidate/candidatedashboard.php");
exit;