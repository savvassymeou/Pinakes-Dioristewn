<?php require_once __DIR__ . "/functions.php"; ?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo e(path_from_root("assets/css/style.css")); ?>">
</head>
<body class="<?php echo e($bodyClass ?? "theme-search"); ?>">
    <header class="site-header">
        <div class="container header-row">
            <a class="brand" href="<?php echo e(path_from_root("index.php")); ?>">
                <span class="brand-mark" aria-hidden="true">EEY</span>
                <span class="brand-text"><?php echo e(APP_NAME); ?></span>
            </a>
            <?php require __DIR__ . "/nav.php"; ?>
        </div>
    </header>
