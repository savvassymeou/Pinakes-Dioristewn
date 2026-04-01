<?php

session_start();
require_once __DIR__ . "/../includes/auth.php";

require_login("../auth/login.php");
redirect_to_dashboard_by_role("../modules/admin/dashboard.php", "../modules/candidate/candidatedashboard.php", "../auth/login.php");


