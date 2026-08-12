<?php
/**
 * index.php (project root)
 * Simple landing page: two entry points, applicant vs admin.
 * Reuses the applicant form's design tokens (public/assets/css/main.css)
 * for visual consistency rather than duplicating a third stylesheet.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sam&amp;Mun Care Ltd — Job Application Portal</title>
<link rel="stylesheet" href="public/assets/css/main.css">
<style>
  .landing-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-4);
  }
  .landing-card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    padding: var(--space-7) var(--space-6);
    max-width: 440px;
    width: 100%;
    text-align: center;
  }
  .landing-card .logo-mark {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
    position: relative;
    margin: 0 auto var(--space-4);
  }
  .landing-card .logo-mark::after {
    content: "";
    position: absolute;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--color-primary-dark);
    opacity: 0.35;
    top: 8px;
    left: 20px;
  }
  .landing-card h1 { font-size: 1.5rem; margin-bottom: var(--space-2); }
  .landing-card p.tagline {
    color: var(--color-text-muted);
    font-size: 0.95rem;
    margin-bottom: var(--space-6);
  }
  .landing-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
  }
  .landing-actions .btn {
    display: block;
    text-decoration: none;
    text-align: center;
  }
</style>
</head>
<body>

<div class="landing-shell">
  <div class="landing-card">
    <div class="logo-mark" aria-hidden="true"></div>
    <h1>Sam&amp;Mun Care Ltd</h1>
    <p class="tagline">Quality care especially for you — Job Application Portal</p>

    <div class="landing-actions">
      <a href="public/apply.php" class="btn btn-primary">Start Your Application</a>
      <a href="admin/login.php" class="btn btn-secondary">Admin Login</a>
    </div>
  </div>
</div>

</body>
</html>
