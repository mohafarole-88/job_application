<?php
/**
 * includes/admin-auth.php
 * Session-based admin authentication. No self-registration in the MVP —
 * accounts are created via scripts/create-admin.php.
 */

const ADMIN_MAX_LOGIN_ATTEMPTS = 5;
const ADMIN_LOGIN_LOCKOUT_SECONDS = 15 * 60;

/**
 * Verifies email/password against the admins table. Returns the admin
 * row (minus password_hash) on success, or null on any failure —
 * deliberately the same null for "no such email" and "wrong password"
 * so the login form can't be used to enumerate valid admin emails.
 */
function attempt_admin_login(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = :email AND is_active = 1');
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return null;
    }

    // Upgrade the stored hash transparently if PHP's default algorithm/cost changed.
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id');
        $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $admin['id']]);
    }

    unset($admin['password_hash']);
    return $admin;
}

/**
 * Simple session-scoped rate limiter for login attempts. Not a
 * substitute for a real WAF/fail2ban in production, but stops naive
 * brute-forcing from a single browser session.
 */
function admin_login_is_locked_out(): bool
{
    $attempts = $_SESSION['admin_login_attempts'] ?? 0;
    $lockedUntil = $_SESSION['admin_login_locked_until'] ?? 0;
    return $attempts >= ADMIN_MAX_LOGIN_ATTEMPTS && time() < $lockedUntil;
}

function admin_register_failed_login(): void
{
    $_SESSION['admin_login_attempts'] = ($_SESSION['admin_login_attempts'] ?? 0) + 1;
    if ($_SESSION['admin_login_attempts'] >= ADMIN_MAX_LOGIN_ATTEMPTS) {
        $_SESSION['admin_login_locked_until'] = time() + ADMIN_LOGIN_LOCKOUT_SECONDS;
    }
}

function admin_clear_login_attempts(): void
{
    unset($_SESSION['admin_login_attempts'], $_SESSION['admin_login_locked_until']);
}

/**
 * Establishes an authenticated admin session. Regenerates the session
 * ID to prevent session fixation across the privilege boundary.
 */
function admin_establish_session(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']            = $admin['id'];
    $_SESSION['admin_full_name']     = $admin['full_name'];
    $_SESSION['admin_email']         = $admin['email'];
    $_SESSION['admin_last_activity'] = time();
    admin_clear_login_attempts();
}

/**
 * Call at the top of every admin/* page. Redirects to login if not
 * authenticated, or if the session has been idle too long.
 */
function require_admin_auth(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    $lastActivity = $_SESSION['admin_last_activity'] ?? 0;
    if (time() - $lastActivity > ADMIN_SESSION_IDLE_TIMEOUT) {
        admin_logout();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['admin_last_activity'] = time();
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
