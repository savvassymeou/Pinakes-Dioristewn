<?php require_once __DIR__ . "/functions.php"; ?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(path_from_root("assets/css/style.css") . "?v=20260425-7"); ?>">
</head>
<body class="<?php echo e($bodyClass ?? "theme-search"); ?>">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="<?php echo e(path_from_root("index.php")); ?>">
                <img
                    class="brand-logo"
                    src="<?php echo e(path_from_root("assets/images/ichnos-logo.jpg") . "?v=20260402"); ?>"
                    alt="<?php echo e(APP_NAME); ?> logo"
                >
                <span class="brand-copy">
                    <strong><?php echo e(APP_NAME); ?></strong>
                    <span><?php echo e(APP_TAGLINE); ?></span>
                </span>
            </a>
            <?php require __DIR__ . "/nav.php"; ?>
        </div>
    </header>
