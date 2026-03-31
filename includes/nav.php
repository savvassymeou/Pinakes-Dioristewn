<?php
$items = nav_items($currentPage ?? "home");
$isLoggedIn = current_user_role() !== null;
$dashboardItem = current_dashboard_item();
?>
<nav class="top-nav" aria-label="Κύρια πλοήγηση">
    <ul class="nav-list">
        <?php foreach ($items as $item): ?>
            <li>
                <a class="nav-link <?php echo $item["active"] ? "is-active" : ""; ?>" href="<?php echo e(path_from_root($item["href"])); ?>">
                    <?php echo e($item["label"]); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<div class="header-actions <?php echo $isLoggedIn ? "header-actions-authenticated" : ""; ?>">
    <?php if (!$isLoggedIn): ?>
        <a class="btn btn-primary" href="<?php echo e(path_from_root("auth/register.php")); ?>">Εγγραφή</a>
        <a class="btn btn-secondary" href="<?php echo e(path_from_root("auth/login.php")); ?>">Σύνδεση</a>
    <?php else: ?>
        <?php if ($dashboardItem !== null): ?>
            <a class="user-badge-link" href="<?php echo e(path_from_root($dashboardItem["href"])); ?>" aria-label="Μετάβαση στο dashboard">
                <div class="user-badge" title="<?php echo e(current_role_label() ?? "Χρήστης"); ?>">
                    <?php echo e(current_user_initials()); ?>
                </div>
            </a>
        <?php endif; ?>
        <div class="header-buttons">
            <a class="btn btn-primary" href="<?php echo e(path_from_root("auth/logout.php")); ?>">Αποσύνδεση</a>
        </div>
    <?php endif; ?>
</div>