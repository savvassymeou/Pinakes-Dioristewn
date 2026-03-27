<?php
$items = nav_items($currentPage ?? "home");
$isLoggedIn = current_user_role() !== null;
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
        <div aria-label="Συνδεδεμένος χρήστης" style="width:54px;height:54px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 54px;font-weight:800;letter-spacing:.04em;color:#fff;background:radial-gradient(circle at 30% 30%, #4fd686, #1f8f52 72%);border:3px solid rgba(255,255,255,.85);box-shadow:0 0 0 6px rgba(72,202,120,.18), 0 14px 30px rgba(34,146,82,.26);">
            <?php echo e(current_user_initials()); ?>
        </div>
        <div class="header-buttons">
            <a class="btn btn-primary" href="<?php echo e(path_from_root("auth/logout.php")); ?>">Αποσύνδεση</a>
        </div>
    <?php endif; ?>
</div>