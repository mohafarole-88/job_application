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
    <div class="logo-mark" aria-hidden="true"></div>
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
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Sign In</button>
    </form>
  </div>
</div>
</body>
</html>
