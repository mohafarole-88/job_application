<?php
/**
 * admin/regenerate-pdf.php
 * POST-only. Re-runs PDF generation for a single application on
 * demand — useful for applications that were saved successfully but
 * whose PDF failed to generate at submission time (e.g. a missing
 * Dompdf install, a template bug that's since been fixed, etc.).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/generate-pdf.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if (!verify_csrf_token($_POST['csrf_token'] ?? null) || $id <= 0) {
    header('Location: view-application.php?id=' . $id . '&pdf=error');
    exit;
}

try {
    $pdo = get_db();
    generate_application_pdf($pdo, $id);
    header('Location: view-application.php?id=' . $id . '&pdf=regenerated');
    exit;
} catch (Throwable $e) {
    error_log('[job-application-system] Manual PDF regeneration failed for application ' . $id . ': ' . $e->getMessage());
    header('Location: view-application.php?id=' . $id . '&pdf=error');
    exit;
}
