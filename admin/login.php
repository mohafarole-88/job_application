<?php
/**
 * admin/login.php
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../config/database.php';

// Already logged in? Skip straight to the dashboard.
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (admin_login_is_locked_out()) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            $pdo = get_db();
            $admin = attempt_admin_login($pdo, $email, $password);
        } catch (Throwable $e) {
            error_log('[job-application-system] Admin login DB error: ' . $e->getMessage());
            $admin = null;
        }

        if ($admin) {
            admin_establish_session($admin);
            $stmt = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $admin['id']]);
            header('Location: dashboard.php');
            exit;
        }

        admin_register_failed_login();
        // Deliberately identical message whether the email doesn't exist
        // or the password is wrong — never confirm which one was correct.
        $error = 'Incorrect email or password.';
    }
}

$csrfToken = csrf_token();
$timedOut = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Sam&amp;Mun Care Ltd</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="card login-card">
    <img src="assets/images/logo-icon-lg.png" alt="Sam&amp;Mun Care Ltd" class="login-logo-img">
    <h1>Admin Login</h1>
    <p class="subtitle">Sam&amp;Mun Care Ltd — Job Application Portal</p>

    <?php if ($timedOut): ?>
      <div class="alert alert-info">You were signed out due to inactivity.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
      <div class="field-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus>
      </div>
      <div class="field-group">
        <label for="password">Password</label>
        <style>
          .pw-field-wrap { position: relative; display: block; }
          .pw-field-wrap input#password { width: 100%; padding-right: 44px; box-sizing: border-box; }
          .pw-toggle-btn {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            line-height: 0;
          }
          .pw-toggle-btn:hover { color: #374151; }
          .pw-toggle-btn:focus { outline: 2px solid #93c5fd; outline-offset: 2px; border-radius: 4px; }
          .pw-toggle-btn svg { width: 20px; height: 20px; }
          .pw-toggle-btn .pw-icon-hide { display: none; }
        </style>
        <div class="pw-field-wrap">
          <input type="password" id="password" name="password" required>
          <button type="button" class="pw-toggle-btn" id="pwToggleBtn" aria-label="Show password" aria-pressed="false">
            <svg class="pw-icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="pw-icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.6 21.6 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.6 21.6 0 0 1-2.66 3.79"></path>
              <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Sign In</button>
    </form>
  </div>
</div>
<script>
  (function () {
    var pwInput = document.getElementById('password');
    var toggleBtn = document.getElementById('pwToggleBtn');
    if (!pwInput || !toggleBtn) return;
    var showIcon = toggleBtn.querySelector('.pw-icon-show');
    var hideIcon = toggleBtn.querySelector('.pw-icon-hide');
    toggleBtn.addEventListener('click', function () {
      var isHidden = pwInput.type === 'password';
      pwInput.type = isHidden ? 'text' : 'password';
      toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      showIcon.style.display = isHidden ? 'none' : 'block';
      hideIcon.style.display = isHidden ? 'block' : 'none';
    });
  })();
</script>
</body>
</html>
