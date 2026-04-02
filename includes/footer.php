    <footer class="site-footer">
        <div class="container footer-row">
            <div>
                <p class="footer-title"><?php echo e(APP_NAME); ?></p>
                <p class="footer-copy"><?php echo e(APP_TAGLINE); ?> με πρόσβαση για αναζήτηση, υποψηφίους και διαχείριση.</p>
            </div>
            <div class="footer-links">
                <a class="footer-link" href="<?php echo e(path_from_root("index.php")); ?>">Αρχική</a>
                <a class="footer-link" href="<?php echo e(path_from_root("modules/search/searchdashboard.php")); ?>">Search</a>
                <a class="footer-link" href="<?php echo e(path_from_root("api/api.php")); ?>">API</a>
            </div>
        </div>
    </footer>
</body>
</html>