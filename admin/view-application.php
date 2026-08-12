<?php
/**
 * admin/view-application.php?id=123
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/application-data.php';
require_once __DIR__ . '/../includes/download-token.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$pdo = get_db();
$data = fetch_application_full($pdo, $id);
if ($data === null) {
    http_response_code(404);
    echo 'Application not found.';
    exit;
}

$app = $data['application'];
$employment = $data['employment'];
$qualifications = $data['qualifications'];
$training = $data['training'];
$references = $data['references'];
$documents = $data['documents'];

$ref1 = $references['current'] ?? null;
$ref2 = $references['previous'] ?? null;

$pdfDownloadUrl = null;
if (!empty($app['pdf_path']) && is_file($app['pdf_path'])) {
    $pdfDownloadUrl = 'download.php?app=' . urlencode($app['application_number'])
        . '&token=' . urlencode(generate_download_token($app['application_number']));
}

function esc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function row(string $label, ?string $value): string
{
    $value = trim((string) $value);
    return '<div class="detail-row"><dt>' . esc($label) . '</dt><dd>' . ($value !== '' ? esc($value) : '&mdash;') . '</dd></div>';
}
function yn(?string $v): string
{
    return $v === 'yes' ? 'Yes' : ($v === 'no' ? 'No' : '—');
}

$docTypeLabels = ['photo' => 'Photo', 'cv' => 'CV', 'certificate' => 'Certificate', 'other' => 'Document'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($app['first_name'] . ' ' . $app['surname']); ?> — <?php echo esc($app['application_number']); ?></title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>

<main class="admin-shell">
  <a class="detail-back" href="dashboard.php">&laquo; Back to applications</a>

  <div class="card">
    <div class="detail-header">
      <div>
        <h1><?php echo esc($app['first_name'] . ' ' . $app['surname']); ?></h1>
        <div class="detail-meta">
          <?php echo esc($app['application_number']); ?> &nbsp;|&nbsp;
          Applied for <?php echo esc($app['position_applied']); ?> &nbsp;|&nbsp;
          Submitted <?php echo esc(date('d/m/Y H:i', strtotime($app['created_at']))); ?>
        </div>
      </div>
      <div style="text-align:right;">
        <span class="status-badge status-<?php echo esc($app['status']); ?>"><?php echo esc($app['status']); ?></span>
        <?php if ($pdfDownloadUrl): ?>
          <div style="margin-top:10px;"><a class="btn btn-secondary" href="<?php echo esc($pdfDownloadUrl); ?>">Download PDF</a></div>
        <?php else: ?>
          <div class="detail-meta" style="margin-top:10px;">PDF not yet generated</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card detail-section">
    <h3>Position &amp; Personal Details</h3>
    <?php
      echo row('Date of birth', $app['date_of_birth'] ? date('d/m/Y', strtotime($app['date_of_birth'])) : '');
      echo row('18+ confirmed', $app['age_confirmation'] ? 'Yes (' . $app['age_confirmation_initials'] . ')' : 'No');
      echo row('Nationality', $app['nationality']);
      echo row('Current address', $app['current_address'] . ($app['current_postcode'] ? ', ' . $app['current_postcode'] : ''));
      echo row('Previous address', $app['previous_address']);
      echo row('Telephone', $app['telephone']);
      echo row('Email', $app['email']);
      echo row('Emergency contact', trim($app['emergency_contact_name'] . ' — ' . $app['emergency_contact_phone'], ' —'));
      echo row('National Insurance number', $app['ni_number']);
      echo row('Driving licence', $app['driving_licence']);
    ?>
  </div>

  <div class="card detail-section">
    <h3>Present or Most Recent Post</h3>
    <?php
      echo row("Employer's name", $app['current_employer_name']);
      echo row("Employer's address", trim(($app['current_employer_address'] ?? '') . ' ' . ($app['current_employer_postcode'] ?? '')));
      echo row('Telephone', $app['current_employer_phone']);
      echo row('Email', $app['current_employer_email']);
      echo row('Employed', trim(($app['current_employment_start'] ?: '') . ' to ' . ($app['current_employment_end'] ?: ''), ' to'));
    ?>
  </div>

  <?php if ($employment): ?>
  <div class="card detail-section">
    <h3>Employment History</h3>
    <table class="mini-table">
      <thead><tr><th>Company</th><th>From</th><th>To</th><th>Position</th><th>Reason for leaving</th></tr></thead>
      <tbody>
        <?php foreach ($employment as $e): ?>
        <tr>
          <td><?php echo esc($e['company_name']); ?></td>
          <td><?php echo esc($e['date_from']); ?></td>
          <td><?php echo esc($e['date_to']); ?></td>
          <td><?php echo esc($e['position']); ?></td>
          <td><?php echo esc($e['reason_for_leaving']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($qualifications): ?>
  <div class="card detail-section">
    <h3>Qualifications &amp; Training</h3>
    <table class="mini-table">
      <thead><tr><th>Course</th><th>Date</th><th>Awarding body</th></tr></thead>
      <tbody>
        <?php foreach ($qualifications as $q): ?>
        <tr>
          <td><?php echo esc($q['course_title']); ?></td>
          <td><?php echo esc($q['date_completed']); ?></td>
          <td><?php echo esc($q['awarding_body']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="card detail-section">
    <h3>Mandatory Training</h3>
    <table class="mini-table">
      <thead><tr><th>Course</th><th>Date completed</th><th>Needs to attend</th></tr></thead>
      <tbody>
        <?php foreach (MANDATORY_TRAINING_LABELS as $key => $label):
          $t = $training[$key] ?? ['date_completed' => null, 'needs_to_attend' => 0]; ?>
        <tr>
          <td><?php echo esc($label); ?></td>
          <td><?php echo esc($t['date_completed'] ? date('d/m/Y', strtotime($t['date_completed'])) : '—'); ?></td>
          <td><?php echo $t['needs_to_attend'] ? 'Yes' : 'No'; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($app['reasonable_adjustment']) || $app['employee_relationship'] === 'yes'): ?>
  <div class="card detail-section">
    <h3>Adjustments &amp; Relationships</h3>
    <?php
      if (!empty($app['reasonable_adjustment'])) echo row('Reasonable adjustment', $app['reasonable_adjustment']);
      echo row('Related to an employee?', yn($app['employee_relationship']));
      if ($app['employee_relationship'] === 'yes') echo row('Details', $app['employee_relationship_details']);
    ?>
  </div>
  <?php endif; ?>

  <div class="card detail-section">
    <h3>References</h3>
    <strong>Reference 1 — Current/Most Recent Employer</strong>
    <?php
      echo row('Manager', ($ref1['manager_name'] ?? '') . (!empty($ref1['job_title']) ? ' (' . $ref1['job_title'] . ')' : ''));
      echo row('Company', trim(($ref1['company_name'] ?? '') . ' — ' . ($ref1['company_address'] ?? ''), ' —'));
      echo row('Contact', trim(($ref1['company_phone'] ?? '') . ' / ' . ($ref1['email'] ?? ''), ' /'));
      echo row('Relationship', $ref1['relationship'] ?? '');
    ?>
    <?php if ($ref2): ?>
      <strong style="display:block; margin-top:14px;">Reference 2 — Previous Employer</strong>
      <?php
        echo row('Manager', ($ref2['manager_name'] ?? '') . (!empty($ref2['position_worked']) ? ' (' . $ref2['position_worked'] . ')' : ''));
        echo row('Company', trim(($ref2['company_name'] ?? '') . ' — ' . ($ref2['company_address'] ?? ''), ' —'));
        echo row('Contact', trim(($ref2['company_phone'] ?? '') . ' / ' . ($ref2['email'] ?? ''), ' /'));
        echo row('Relationship', $ref2['relationship'] ?? '');
      ?>
    <?php endif; ?>
  </div>

  <div class="card detail-section">
    <h3>Criminal Convictions &amp; DBS</h3>
    <?php
      echo row('Never cautioned/convicted', yn($app['criminal_conviction_status']));
      if (!empty($app['criminal_conviction_details'])) echo row('Details', $app['criminal_conviction_details']);
      echo row('Current DBS check', yn($app['dbs_status']));
      if ($app['dbs_status'] === 'yes') {
          echo row('DBS level', $app['dbs_level']);
          echo row('DBS expiry', $app['dbs_expiry_date'] ? date('d/m/Y', strtotime($app['dbs_expiry_date'])) : '');
      }
      echo row('Online DBS update access', yn($app['dbs_online_access']));
    ?>
  </div>

  <div class="card detail-section">
    <h3>Right to Work &amp; Languages</h3>
    <?php
      echo row('UK work permit required?', yn($app['work_permit_required']));
      echo row('Languages', trim('Fluent: ' . ($app['languages_fluent'] ?: '—') . '  |  Written: ' . ($app['languages_written'] ?: '—') . '  |  Basic: ' . ($app['languages_basic'] ?: '—')));
    ?>
  </div>

  <div class="card detail-section">
    <h3>Declaration</h3>
    <?php
      echo row('Declaration accepted', $app['declaration_accepted'] ? 'Yes' : 'No');
      echo row('Signature', $app['signature_name']);
      echo row('Date', $app['signature_date'] ? date('d/m/Y', strtotime($app['signature_date'])) : '');
    ?>
  </div>

  <?php if ($documents): ?>
  <div class="card detail-section">
    <h3>Uploaded Documents</h3>
    <ul class="doc-link-list">
      <?php foreach ($documents as $doc): ?>
      <li>
        <a href="download-document.php?doc_id=<?php echo (int) $doc['id']; ?>">
          <?php echo esc($docTypeLabels[$doc['doc_type']] ?? 'Document'); ?> — <?php echo esc($doc['original_filename']); ?>
        </a>
        <span class="doc-meta">(<?php echo esc(number_format($doc['file_size_bytes'] / 1024, 1)); ?> KB)</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</main>
</body>
</html>
