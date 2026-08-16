<?php
/**
 * admin/admin-delete.php
 * POST-only. Deletes an admin account, with two safeguards:
 *   - You can't delete your own account while logged in as it.
 *   - You can't delete the last remaining admin account.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admins.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: admins.php?msg=error');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id === (int) $_SESSION['admin_id']) {
    header('Location: admins.php?msg=self_delete');
    exit;
}

try {
    $pdo = get_db();

    $totalCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($totalCount <= 1) {
        header('Location: admins.php?msg=last_admin');
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM admins WHERE id = :id');
    $stmt->execute(['id' => $id]);

    header('Location: admins.php?msg=deleted');
    exit;
} catch (Throwable $e) {
    error_log('[job-application-system] Admin delete failed: ' . $e->getMessage());
    header('Location: admins.php?msg=error');
    exit;
}
