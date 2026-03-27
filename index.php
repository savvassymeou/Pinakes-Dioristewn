<?php

session_start();

require_once __DIR__ . "/includes/functions.php";

$pageTitle = APP_NAME . " | Î‘ÏÏ‡Î¹ÎºÎ®";
$bodyClass = "theme-search";
$currentPage = "home";
$navBase = "";
require __DIR__ . "/includes/header.php";

?>
<main class="container home-page">
    <section class="home-hero" aria-labelledby="homeTitle">
        <div class="home-copy">
            <span class="eyebrow-home">Web Application Project</span>
            <h1 id="homeTitle">Î•Ï†Î±ÏÎ¼Î¿Î³Î® Î Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ·Ï‚ Î Î¹Î½Î¬ÎºÏ‰Î½ Î”Î¹Î¿ÏÎ¹ÏƒÏ„Î­Ï‰Î½</h1>
            <p class="muted">
                ÎœÎ¹Î± Î¿Î»Î¿ÎºÎ»Î·ÏÏ‰Î¼Î­Î½Î· Î´Î¹Î±Î´Î¹ÎºÏ„Ï…Î±ÎºÎ® ÎµÏ†Î±ÏÎ¼Î¿Î³Î® Î¼Îµ `Admin`, `Candidate`, `Search`
                ÎºÎ±Î¹ `API` modules Î³Î¹Î± Î±Î½Î±Î¶Î®Ï„Î·ÏƒÎ·, Ï€Î±ÏÎ±ÎºÎ¿Î»Î¿ÏÎ¸Î·ÏƒÎ· ÎºÎ±Î¹ Î´Î¹Î±Ï‡ÎµÎ¯ÏÎ¹ÏƒÎ· ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½
                Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½ ÎºÎ±Î¹ ÎµÎ¹Î´Î¹ÎºÎ¿Ï„Î®Ï„Ï‰Î½.
            </p>

            <div class="hero-actions">
                <a class="btn" href="Search/searchdashboard.php">Î†Î½Î¿Î¹Î³Î¼Î± Search</a>
                <?php if (current_user_role() === null): ?>
                    <a class="btn btn-ghost" href="auth/login.php">Î£ÏÎ½Î´ÎµÏƒÎ·</a>
                <?php elseif (current_user_role() === ROLE_ADMIN): ?>
                    <a class="btn btn-ghost" href="Admin/admindashboard.php">Admin Dashboard</a>
                <?php else: ?>
                    <a class="btn btn-ghost" href="Candidate/candidatedashboard.php">Candidate Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="home-summary">
            <div class="summary-card">
                <span class="summary-label">Modules</span>
                <strong>4 ÎµÎ½ÎµÏÎ³Î¬ modules</strong>
            </div>
            <div class="summary-card">
                <span class="summary-label">Backend</span>
                <strong>PHP + MySQL</strong>
            </div>
            <div class="summary-card">
                <span class="summary-label">Frontend</span>
                <strong>HTML + CSS</strong>
            </div>
        </div>
    </section>

    <section class="grid grid-admin" aria-label="ÎšÏÏÎ¹ÎµÏ‚ ÎµÎ½ÏŒÏ„Î·Ï„ÎµÏ‚">
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">1</div>
            <h2>Search Module</h2>
            <p>Î”Î·Î¼ÏŒÏƒÎ¹Î± Î±Î½Î±Î¶Î®Ï„Î·ÏƒÎ· Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Ï‰Î½ Î±Î½Î¬ ÏŒÎ½Î¿Î¼Î± ÎºÎ±Î¹ ÎµÎ¹Î´Î¹ÎºÏŒÏ„Î·Ï„Î±, Î¼Îµ ÏƒÏ„Î±Ï„Î¹ÏƒÏ„Î¹ÎºÎ¬ ÎºÎ±Î¹ ÏƒÏÎ½Î´ÎµÏƒÎ· Ï€ÏÎ¿Ï‚ ÎµÎ³Î³ÏÎ±Ï†Î®.</p>
            <div class="card-actions">
                <a class="btn" href="Search/searchdashboard.php">Î†Î½Î¿Î¹Î³Î¼Î±</a>
            </div>
        </article>

        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">2</div>
            <h2>Candidate Module</h2>
            <p>Î ÏÎ¿ÏƒÏ‰Ï€Î¹ÎºÏŒ dashboard Ï…Ï€Î¿ÏˆÎ·Ï†Î¯Î¿Ï… Î¼Îµ profile, ÎµÎ¹Î´Î¿Ï€Î¿Î¹Î®ÏƒÎµÎ¹Ï‚, track my applications ÎºÎ±Î¹ track others.</p>
            <div class="card-actions">
                <a class="btn" href="Candidate/candidatedashboard.php">Î†Î½Î¿Î¹Î³Î¼Î±</a>
            </div>
        </article>

        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">3</div>
            <h2>Admin Module</h2>
            <p>Î”Î¹Î±Ï‡ÎµÎ¯ÏÎ¹ÏƒÎ· Ï‡ÏÎ·ÏƒÏ„ÏŽÎ½, Î»Î¹ÏƒÏ„ÏŽÎ½, reports ÎºÎ±Î¹ ÏƒÏ„Î¿Î¹Ï‡ÎµÎ¯Ï‰Î½ admin Î¼Î­ÏƒÎ± Î±Ï€ÏŒ ÎµÎ½Î¹Î±Î¯Î¿ dashboard.</p>
            <div class="card-actions">
                <a class="btn" href="Admin/admindashboard.php">Î†Î½Î¿Î¹Î³Î¼Î±</a>
            </div>
        </article>

        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">4</div>
            <h2>API Module</h2>
            <p>JSON endpoints Î³Î¹Î± specialties, candidates ÎºÎ±Î¹ statistics ÏŽÏƒÏ„Îµ Î½Î± Î¼Ï€Î¿ÏÎµÎ¯ Î½Î± Ï‡ÏÎ·ÏƒÎ¹Î¼Î¿Ï€Î¿Î¹Î·Î¸ÎµÎ¯ Î±Ï€ÏŒ Ï„ÏÎ¯Ï„ÎµÏ‚ ÎµÏ†Î±ÏÎ¼Î¿Î³Î­Ï‚.</p>
            <div class="card-actions">
                <a class="btn" href="API/api.php">Î†Î½Î¿Î¹Î³Î¼Î±</a>
            </div>
        </article>
    </section>

    <section class="panel" aria-labelledby="overviewTitle">
        <div class="panel-head">
            <h2 id="overviewTitle">Î¤Î¹ Ï€ÎµÏÎ¹Î»Î±Î¼Î²Î¬Î½ÎµÎ¹ Î· ÎµÏ†Î±ÏÎ¼Î¿Î³Î®</h2>
            <p class="muted">Î£Ï…Î½Î¿Ï€Ï„Î¹ÎºÎ® ÎµÎ¹ÎºÏŒÎ½Î± Ï„Î·Ï‚ Ï…Î»Î¿Ï€Î¿Î¯Î·ÏƒÎ·Ï‚ Ï€Î¿Ï… Î­Ï‡ÎµÎ¹Ï‚ Ï€Î»Î­Î¿Î½ ÏƒÏ„Î¿ project.</p>
        </div>

        <div class="reports-layout">
            <div class="chart-card">
                <h3>Î§ÏÎ®ÏƒÏ„ÎµÏ‚</h3>
                <div class="year-list">
                    <div class="year-item"><span>Register / Login</span><strong>Î¥Î»Î¿Ï€Î¿Î¹Î·Î¼Î­Î½Î±</strong></div>
                    <div class="year-item"><span>Candidate profile</span><strong>Î¥Î»Î¿Ï€Î¿Î¹Î·Î¼Î­Î½Î¿</strong></div>
                    <div class="year-item"><span>Admin manage users</span><strong>Î¥Î»Î¿Ï€Î¿Î¹Î·Î¼Î­Î½Î¿</strong></div>
                </div>
            </div>

            <div class="chart-card">
                <h3>Î”ÎµÎ´Î¿Î¼Î­Î½Î±</h3>
                <div class="year-list">
                    <div class="year-item"><span>Search</span><strong>Î‘Ï€ÏŒ Î²Î¬ÏƒÎ·</strong></div>
                    <div class="year-item"><span>Reports</span><strong>Î‘Ï€ÏŒ Î²Î¬ÏƒÎ·</strong></div>
                    <div class="year-item"><span>API</span><strong>JSON endpoints</strong></div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>

