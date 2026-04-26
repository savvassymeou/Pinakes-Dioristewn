<?php

$query = $_GET;
$query["section"] = "candidate-list";

$target = "admindashboard.php?" . http_build_query($query) . "#candidate-list";

header("Location: " . $target);
exit;
