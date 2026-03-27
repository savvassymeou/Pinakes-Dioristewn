<?php

session_start();
require_once __DIR__ . "/../includes/auth.php";

require_login("../auth/login.php");
redirect_to_dashboard_by_role("../Admin/admindashboard.php", "../Candidate/candidatedashboard.php", "../auth/login.php");
