<?php $items = nav_items($currentPage ?? "home"); ?>
<nav class="top-nav" aria-label="ÎšÏÏÎ¹Î± Ï€Î»Î¿Î®Î³Î·ÏƒÎ·">
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

<div class="header-actions">
    <?php if (current_user_role() === null): ?>
        <a class="btn" href="<?php echo e(path_from_root("auth/register.php")); ?>">Register</a>
        <a class="btn btn-ghost" href="<?php echo e(path_from_root("auth/login.php")); ?>">Login</a>
    <?php elseif (!empty($headerActionLabel) && !empty($headerActionHref)): ?>
        <a class="btn btn-ghost" href="<?php echo e($headerActionHref); ?>"><?php echo e($headerActionLabel); ?></a>
    <?php endif; ?>
</div>


