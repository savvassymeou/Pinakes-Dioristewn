<?php

session_start();

require_once __DIR__ . "/includes/functions.php";

$pageTitle = APP_NAME . " | Αρχική";
$bodyClass = "theme-search";
$currentPage = "home";
$navBase = "";
require __DIR__ . "/includes/header.php";

?>
<main class="container home-page">
    <section class="home-hero" aria-labelledby="homeTitle">
        <div class="home-copy">
            <span class="eyebrow-home">Web Application Project</span>
            <h1 id="homeTitle">Εφαρμογή Παρακολούθησης Πινάκων Διοριστέων</h1>
            <p class="muted">
                Μια ολοκληρωμένη διαδικτυακή εφαρμογή με `Admin`, `Candidate`, `Search`
                και `API` modules για αναζήτηση, παρακολούθηση και διαχείριση στοιχείων
                υποψηφίων και ειδικοτήτων.
            </p>

            <div class="hero-actions">
                <a class="btn" href="Search/searchdashboard.php">Άνοιγμα Search</a>
                <?php if (current_user_role() === null): ?>
                    <a class="btn btn-ghost" href="login.php">Σύνδεση</a>
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
                <strong>4 ενεργά modules</strong>
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

    <section class="grid grid-admin" aria-label="Κύριες ενότητες">
        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">1</div>
            <h2>Search Module</h2>
            <p>Δημόσια αναζήτηση υποψηφίων ανά όνομα και ειδικότητα, με στατιστικά και σύνδεση προς εγγραφή.</p>
            <div class="card-actions">
                <a class="btn" href="Search/searchdashboard.php">Άνοιγμα</a>
            </div>
        </article>

        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">2</div>
            <h2>Candidate Module</h2>
            <p>Προσωπικό dashboard υποψηφίου με profile, ειδοποιήσεις, track my applications και track others.</p>
            <div class="card-actions">
                <a class="btn" href="Candidate/candidatedashboard.php">Άνοιγμα</a>
            </div>
        </article>

        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">3</div>
            <h2>Admin Module</h2>
            <p>Διαχείριση χρηστών, λιστών, reports και στοιχείων admin μέσα από ενιαίο dashboard.</p>
            <div class="card-actions">
                <a class="btn" href="Admin/admindashboard.php">Άνοιγμα</a>
            </div>
        </article>

        <article class="card card-action">
            <div class="card-icon" aria-hidden="true">4</div>
            <h2>API Module</h2>
            <p>JSON endpoints για specialties, candidates και statistics ώστε να μπορεί να χρησιμοποιηθεί από τρίτες εφαρμογές.</p>
            <div class="card-actions">
                <a class="btn" href="API/api.php">Άνοιγμα</a>
            </div>
        </article>
    </section>

    <section class="panel" aria-labelledby="overviewTitle">
        <div class="panel-head">
            <h2 id="overviewTitle">Τι περιλαμβάνει η εφαρμογή</h2>
            <p class="muted">Συνοπτική εικόνα της υλοποίησης που έχεις πλέον στο project.</p>
        </div>

        <div class="reports-layout">
            <div class="chart-card">
                <h3>Χρήστες</h3>
                <div class="year-list">
                    <div class="year-item"><span>Register / Login</span><strong>Υλοποιημένα</strong></div>
                    <div class="year-item"><span>Candidate profile</span><strong>Υλοποιημένο</strong></div>
                    <div class="year-item"><span>Admin manage users</span><strong>Υλοποιημένο</strong></div>
                </div>
            </div>

            <div class="chart-card">
                <h3>Δεδομένα</h3>
                <div class="year-list">
                    <div class="year-item"><span>Search</span><strong>Από βάση</strong></div>
                    <div class="year-item"><span>Reports</span><strong>Από βάση</strong></div>
                    <div class="year-item"><span>API</span><strong>JSON endpoints</strong></div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
