<?php if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_id'])) { http_response_code(403); exit; } ?>
<header class="admin-header">
  <div class="brand">
    <img src="assets/images/logo-icon.png" alt="Sam&amp;Mun Care Ltd" class="header-logo-img">
    <strong>Sam&amp;Mun Care Ltd — Admin</strong>
  </div>
  <div class="who">
    <span><?php echo htmlspecialchars($_SESSION['admin_full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
    <a href="logout.php" class="logout-link">Sign out</a>
  </div>
</header>
