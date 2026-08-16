<?php
/**
 * admin/admins.php
 * List all admin accounts, with links to create/edit/delete.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

$pdo = get_db();
$stmt = $pdo->query('SELECT id, full_name, email, is_active, last_login_at, created_at FROM admins ORDER BY created_at ASC');
$admins = $stmt->fetchAll();

$flashMessages = [
    'created' => ['type' => 'success', 'text' => 'Admin account created.'],
    'updated' => ['type' => 'success', 'text' => 'Admin account updated.'],
    'deleted' => ['type' => 'success', 'text' => 'Admin account deleted.'],
    'self_delete' => ['type' => 'error', 'text' => "You can't delete your own account while logged in as it."],
    'last_admin' => ['type' => 'error', 'text' => 'You can\'t delete the last remaining admin account.'],
    'self_deactivate' => ['type' => 'error', 'text' => "You can't deactivate your own account."],
    'last_active' => ['type' => 'error', 'text' => 'At least one admin account must stay active.'],
    'duplicate_email' => ['type' => 'error', 'text' => 'That email address is already in use by another admin.'],
];
$flash = $flashMessages[$_GET['msg'] ?? ''] ?? null;

function esc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Admins — Admin</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>

<main class="admin-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h1 style="margin:0;">Manage Admins</h1>
    <a class="btn btn-primary" href="admin-edit.php">+ Add Admin</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo esc($flash['text']); ?></div>
  <?php endif; ?>

  <div class="card">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Status</th>
          <th>Last login</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td>
            <?php echo esc($a['full_name']); ?>
            <?php if ((int) $a['id'] === (int) $_SESSION['admin_id']): ?>
              <span class="you-tag">You</span>
            <?php endif; ?>
          </td>
          <td><?php echo esc($a['email']); ?></td>
          <td>
            <span class="toggle-badge <?php echo $a['is_active'] ? 'is-active' : 'is-inactive'; ?>">
              <?php echo $a['is_active'] ? 'Active' : 'Inactive'; ?>
            </span>
          </td>
          <td><?php echo $a['last_login_at'] ? esc(date('d/m/Y H:i', strtotime($a['last_login_at']))) : '—'; ?></td>
          <td><?php echo esc(date('d/m/Y', strtotime($a['created_at']))); ?></td>
          <td>
            <div class="table-actions">
              <a href="admin-edit.php?id=<?php echo (int) $a['id']; ?>">Edit</a>
              <?php if ((int) $a['id'] !== (int) $_SESSION['admin_id']): ?>
                <form method="post" action="admin-delete.php" onsubmit="return confirm('Delete <?php echo esc(addslashes($a['full_name'])); ?>? This cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                  <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
