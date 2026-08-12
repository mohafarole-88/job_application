<?php
/**
 * admin/dashboard.php
 * Lists submitted applications with search + filters + pagination.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

$pdo = get_db();

// ---- Read filters from the query string ----
$search   = trim((string) ($_GET['q'] ?? ''));
$status   = trim((string) ($_GET['status'] ?? ''));
$position = trim((string) ($_GET['position'] ?? ''));
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 20;

$validStatuses = ['submitted', 'reviewed', 'shortlisted', 'rejected', 'archived'];
if ($status !== '' && !in_array($status, $validStatuses, true)) {
    $status = '';
}

// ---- Build the WHERE clause safely (parameterized) ----
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(first_name LIKE :search1 OR surname LIKE :search2 OR application_number LIKE :search3 OR email LIKE :search4)';
    $like = '%' . $search . '%';
    $params['search1'] = $like;
    $params['search2'] = $like;
    $params['search3'] = $like;
    $params['search4'] = $like;
}
if ($status !== '') {
    $where[] = 'status = :status';
    $params['status'] = $status;
}
if ($position !== '') {
    $where[] = 'position_applied LIKE :position';
    $params['position'] = '%' . $position . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Total count for pagination ----
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications {$whereSql}");
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// ---- Fetch the page of results ----
$sql = "SELECT id, application_number, first_name, surname, position_applied, status, created_at
        FROM applications
        {$whereSql}
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$applications = $stmt->fetchAll();

function qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return htmlspecialchars('?' . http_build_query($params), ENT_QUOTES, 'UTF-8');
}
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
<title>Applications — Admin</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>

<main class="admin-shell">
  <h1>Applications</h1>

  <div class="card">
    <form method="get" class="filter-bar">
      <div class="field-group search">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?php echo esc($search); ?>" placeholder="Name, application number, or email">
      </div>
      <div class="field-group">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">All statuses</option>
          <?php foreach ($validStatuses as $s): ?>
            <option value="<?php echo esc($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo esc(ucfirst($s)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-group">
        <label for="position">Position</label>
        <input type="text" id="position" name="position" value="<?php echo esc($position); ?>" placeholder="e.g. Care Assistant">
      </div>
      <div class="field-group" style="flex: 0;">
        <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Filter</button>
      </div>
    </form>
  </div>

  <div class="card">
    <p class="results-count">
      <?php echo $totalCount; ?> application<?php echo $totalCount === 1 ? '' : 's'; ?> found
      <?php if ($search !== '' || $status !== '' || $position !== ''): ?>
        — <a href="dashboard.php">clear filters</a>
      <?php endif; ?>
    </p>

    <?php if (!$applications): ?>
      <div class="empty-state">No applications match these filters.</div>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Application #</th>
            <th>Applicant</th>
            <th>Position</th>
            <th>Status</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $app): ?>
          <tr>
            <td>
              <a class="row-link" href="view-application.php?id=<?php echo (int) $app['id']; ?>">
                <?php echo esc($app['application_number']); ?>
              </a>
            </td>
            <td><?php echo esc($app['first_name'] . ' ' . $app['surname']); ?></td>
            <td><?php echo esc($app['position_applied']); ?></td>
            <td><span class="status-badge status-<?php echo esc($app['status']); ?>"><?php echo esc($app['status']); ?></span></td>
            <td><?php echo esc(date('d/m/Y H:i', strtotime($app['created_at']))); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?><a href="<?php echo qs(['page' => $page - 1]); ?>">&laquo; Prev</a><?php endif; ?>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p === $page): ?>
            <span class="current"><?php echo $p; ?></span>
          <?php else: ?>
            <a href="<?php echo qs(['page' => $p]); ?>"><?php echo $p; ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="<?php echo qs(['page' => $page + 1]); ?>">Next &raquo;</a><?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
