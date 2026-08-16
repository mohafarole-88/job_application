<?php
/**
 * admin/admin-edit.php
 * With no ?id= : create form. With ?id=123 : edit form for that admin.
 * Password field is required when creating, optional when editing
 * (blank = keep the existing password).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

$pdo = get_db();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$isEdit = $editId > 0;
$errors = [];

// Values used to re-populate the form on validation failure, or to
// pre-fill it when editing.
$values = ['full_name' => '', 'email' => '', 'is_active' => 1];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, full_name, email, is_active FROM admins WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: admins.php');
        exit;
    }
    $values = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors['_form'] = 'Your session expired. Please try again.';
    } else {
        $values['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $values['email'] = trim((string) ($_POST['email'] ?? ''));
        $values['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');

        if ($values['full_name'] === '') {
            $errors['full_name'] = 'Full name is required.';
        }
        if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (!$isEdit && strlen($password) < 10) {
            $errors['password'] = 'Password must be at least 10 characters.';
        }
        if ($isEdit && $password !== '' && strlen($password) < 10) {
            $errors['password'] = 'Password must be at least 10 characters (or leave blank to keep the current one).';
        }

        // Safeguards: never let an admin lock everyone out.
        if ($isEdit && $editId === (int) $_SESSION['admin_id'] && $values['is_active'] === 0) {
            $errors['is_active'] = "You can't deactivate your own account.";
        }
        if ($isEdit && $values['is_active'] === 0 && empty($errors['is_active'])) {
            $activeCountStmt = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE is_active = 1 AND id != :id');
            $activeCountStmt->execute(['id' => $editId]);
            if ((int) $activeCountStmt->fetchColumn() === 0) {
                $errors['is_active'] = 'At least one admin account must stay active.';
            }
        }

        if (!$errors) {
            try {
                if ($isEdit) {
                    if ($password !== '') {
                        $stmt = $pdo->prepare(
                            'UPDATE admins SET full_name = :full_name, email = :email, is_active = :is_active, password_hash = :hash WHERE id = :id'
                        );
                        $stmt->execute([
                            'full_name' => $values['full_name'],
                            'email' => $values['email'],
                            'is_active' => $values['is_active'],
                            'hash' => password_hash($password, PASSWORD_DEFAULT),
                            'id' => $editId,
                        ]);
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE admins SET full_name = :full_name, email = :email, is_active = :is_active WHERE id = :id'
                        );
                        $stmt->execute([
                            'full_name' => $values['full_name'],
                            'email' => $values['email'],
                            'is_active' => $values['is_active'],
                            'id' => $editId,
                        ]);
                    }
                    header('Location: admins.php?msg=updated');
                    exit;
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO admins (full_name, email, password_hash, is_active) VALUES (:full_name, :email, :hash, :is_active)'
                    );
                    $stmt->execute([
                        'full_name' => $values['full_name'],
                        'email' => $values['email'],
                        'hash' => password_hash($password, PASSWORD_DEFAULT),
                        'is_active' => $values['is_active'],
                    ]);
                    header('Location: admins.php?msg=created');
                    exit;
                }
            } catch (PDOException $e) {
                if ((int) $e->errorInfo[1] === 1062) { // duplicate unique key (email)
                    $errors['email'] = 'That email address is already in use by another admin.';
                } else {
                    error_log('[job-application-system] Admin save failed: ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong saving this admin account.';
                }
            }
        }
    }
}

$csrfToken = csrf_token();
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
<title><?php echo $isEdit ? 'Edit Admin' : 'Add Admin'; ?> — Admin</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>

<main class="admin-shell" style="max-width: 560px;">
  <a class="detail-back" href="admins.php">&laquo; Back to admin list</a>
  <h1><?php echo $isEdit ? 'Edit Admin' : 'Add Admin'; ?></h1>

  <div class="card">
    <?php if (!empty($errors['_form'])): ?>
      <div class="alert alert-error"><?php echo esc($errors['_form']); ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo esc($csrfToken); ?>">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?php echo (int) $editId; ?>"><?php endif; ?>

      <div class="field-group">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo esc($values['full_name']); ?>" required>
        <?php if (!empty($errors['full_name'])): ?><div class="alert alert-error" style="margin-top:6px; padding:6px 10px;"><?php echo esc($errors['full_name']); ?></div><?php endif; ?>
      </div>

      <div class="field-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo esc($values['email']); ?>" required>
        <?php if (!empty($errors['email'])): ?><div class="alert alert-error" style="margin-top:6px; padding:6px 10px;"><?php echo esc($errors['email']); ?></div><?php endif; ?>
      </div>

      <div class="field-group">
        <label for="password"><?php echo $isEdit ? 'New password' : 'Password'; ?></label>
        <input type="password" id="password" name="password" <?php echo $isEdit ? 'placeholder="Leave blank to keep current password"' : 'required'; ?>>
        <?php if (!empty($errors['password'])): ?><div class="alert alert-error" style="margin-top:6px; padding:6px 10px;"><?php echo esc($errors['password']); ?></div><?php endif; ?>
      </div>

      <div class="field-group">
        <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
          <input type="checkbox" id="is_active" name="is_active" value="1" style="width:auto;"
            <?php echo $values['is_active'] ? 'checked' : ''; ?>
            <?php echo ($isEdit && $editId === (int) $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
          Active (can log in)
        </label>
        <?php if ($isEdit && $editId === (int) $_SESSION['admin_id']): ?>
          <input type="hidden" name="is_active" value="1">
          <p class="field-hint">You can't deactivate your own account while logged in as it.</p>
        <?php endif; ?>
        <?php if (!empty($errors['is_active'])): ?><div class="alert alert-error" style="margin-top:6px; padding:6px 10px;"><?php echo esc($errors['is_active']); ?></div><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save Changes' : 'Create Admin'; ?></button>
    </form>
  </div>
</main>
</body>
</html>
