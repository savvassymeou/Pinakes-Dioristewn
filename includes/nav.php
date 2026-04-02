<?php
$items = nav_items($currentPage ?? "home");
$isLoggedIn = current_user_role() !== null;
$dashboardItem = current_dashboard_item();
$userRoleLabel = current_role_label();
$accountLabel = u('\u039F \u03BB\u03BF\u03B3\u03B1\u03C1\u03B9\u03B1\u03C3\u03BC\u03CC\u03C2 \u03BC\u03BF\u03C5');
$logoutLabel = u('\u0391\u03C0\u03BF\u03C3\u03CD\u03BD\u03B4\u03B5\u03C3\u03B7');
$registerLabel = u('\u0395\u03B3\u03B3\u03C1\u03B1\u03C6\u03AE');
$loginLabel = u('\u03A3\u03CD\u03BD\u03B4\u03B5\u03C3\u03B7');
$navAria = u('\u039A\u03CD\u03C1\u03B9\u03B1 \u03C0\u03BB\u03BF\u03AE\u03B3\u03B7\u03C3\u03B7');
$fallbackUser = u('\u03A3\u03C5\u03BD\u03B4\u03B5\u03B4\u03B5\u03BC\u03AD\u03BD\u03BF\u03C2 \u03C7\u03C1\u03AE\u03C3\u03C4\u03B7\u03C2');
?>
<nav class="top-nav" aria-label="<?php echo e($navAria); ?>">
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
        <a class="btn btn-primary" href="<?php echo e(path_from_root("auth/register.php")); ?>"><?php echo e($registerLabel); ?></a>
        <a class="btn btn-secondary" href="<?php echo e(path_from_root("auth/login.php")); ?>"><?php echo e($loginLabel); ?></a>
    <?php else: ?>
        <div class="profile-menu">
            <button type="button" class="profile-trigger" aria-haspopup="menu">
                <span class="profile-role-badge"><?php echo e($userRoleLabel ?? $fallbackUser); ?></span>
                <span class="profile-trigger-avatar"><?php echo e(current_user_initials()); ?></span>
            </button>

            <div class="profile-dropdown" role="menu">
                <?php if ($dashboardItem !== null): ?>
                    <a class="profile-dropdown-item" href="<?php echo e(path_from_root($dashboardItem["href"])); ?>" role="menuitem">
                        <span class="profile-dropdown-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.33 0-6 1.79-6 4v1h12v-1c0-2.21-2.67-4-6-4Z"/></svg>
                        </span>
                        <span><?php echo e($accountLabel); ?></span>
                    </a>
                <?php endif; ?>
                <a class="profile-dropdown-item profile-dropdown-item-logout" href="<?php echo e(path_from_root("auth/logout.php")); ?>" role="menuitem">
                    <span class="profile-dropdown-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><path d="M10 17v-2h4V9h-4V7l-5 5 5 5Z"/><path d="M14 5h5v14h-5v-2h3V7h-3V5Z"/></svg>
                        </span>
                        <span><?php echo e($logoutLabel); ?></span>
                    </a>
            </div>
        </div>
    <?php endif; ?>
</div>
