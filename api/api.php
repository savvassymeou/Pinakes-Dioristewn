<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>API Module | Πίνακες Διοριστέων</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>

<body class="theme-api">
  <header class="site-header">
    <div class="container header-row">
      <a class="brand" href="../search/searchdashboard.html">
        <span class="brand-mark" aria-hidden="true">EEY</span>
        <span class="brand-text">Πίνακες Διοριστέων</span>
      </a>

      <nav class="top-nav" aria-label="Κύρια πλοήγηση">
        <ul class="nav-list">
          <li><a class="nav-link" href="../search/searchdashboard.html">Search</a></li>
          <li><a class="nav-link" href="../candidate/candidatedashboard.html">Candidate</a></li>
          <li><a class="nav-link" href="../admin/admindashboard.html">Admin</a></li>
          <li><a class="nav-link is-active" href="./api.php">API</a></li>
        </ul>
      </nav>

      <div class="header-actions">
        <a class="btn btn-ghost" href="#endpoints">Endpoints</a>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="page-hero" aria-labelledby="apiTitle">
      <div class="hero-text">
        <h1 id="apiTitle">API Module (UI)</h1>
        <p class="muted">
          Σε επόμενη φάση θα παρέχει πραγματικά JSON endpoints για τρίτες εφαρμογές.
          Προς το παρόν παρουσιάζεται η τεκμηρίωση (mock).
        </p>
      </div>
    </section>

    <section class="panel" id="endpoints" aria-labelledby="endpointsTitle">
      <div class="panel-head">
        <h2 id="endpointsTitle">Βασικά Endpoints (Mock)</h2>
        <p class="muted">Παράδειγμα μορφής — όχι πραγματικά δεδομένα ακόμα.</p>
      </div>

      <div class="code-card">
        <h3>GET /api/specialties</h3>
        <pre><code>[
  {"id": 1, "name": "Πληροφορικής"},
  {"id": 2, "name": "Μαθηματικοί"}
]</code></pre>
      </div>

      <div class="code-card">
        <h3>GET /api/candidates?name=...&amp;specialty=...</h3>
        <pre><code>{
  "results": [
    {"rank": 152, "name": "ΠΑΡΑΔΕΙΓΜΑ ΟΝΟΜΑ", "specialty": "Πληροφορικής"}
  ]
}</code></pre>
      </div>

      <div class="code-card">
        <h3>GET /api/stats?specialty_id=1</h3>
        <pre><code>{
  "specialty": "Πληροφορικής",
  "total_candidates": 384,
  "avg_age": 35.8
}</code></pre>
      </div>
    </section>

    <section class="panel" aria-labelledby="notesTitle">
      <div class="panel-head">
        <h2 id="notesTitle">Σημείωση</h2>
      </div>
      <p>
        Για την Εργασία 1 (HTML/CSS modules) το ζητούμενο είναι το UI και η δομή.
        Τα endpoints θα υλοποιηθούν στην κύρια φάση με PHP + MySQL/MariaDB.
      </p>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-row">
      <p>© 2026 API Module — UI/Docs</p>
      <a class="footer-link" href="../search/searchdashboard.html">Επιστροφή στο Search</a>
    </div>
  </footer>
</body>
</html>
