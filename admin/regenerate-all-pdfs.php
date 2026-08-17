<?php
/**
 * admin/regenerate-all-pdfs.php
 * POST-only. Force-regenerates the PDF for every application matching
 * the current dashboard filters (same filter logic as
 * download-all-applications.php, so "what you're looking at" and
 * "what gets regenerated" always match).
 *
 * Unlike the individual Regenerate PDF button (which only fills in
 * missing PDFs), this ALWAYS regenerates — including applications that
 * already have a pdf_path — so it also fixes stale PDFs left over from
 * an old template, not just missing ones.
 *
 * Processes up to MAX_BULK_REGENERATE per run to stay within a single
 * request's execution time; if more remain, the summary says so and
 * you can just click it again.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/application-search.php';
require_once __DIR__ . '/../includes/generate-pdf.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

const MAX_BULK_REGENERATE = 100;

// Give this its own generous time budget — regenerating a lot of PDFs
// in one request can legitimately take a while.
set_time_limit(120);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: dashboard.php?regen_error=1');
    exit;
}

// Carry the current filters straight through into the redirect back,
// so the admin lands on the same filtered view they started from.
$redirectFilters = [
    'q'        => $_POST['q'] ?? '',
    'status'   => $_POST['status'] ?? '',
    'position' => $_POST['position'] ?? '',
];
$redirectQuery = http_build_query(array_filter($redirectFilters, fn($v) => $v !== ''));

try {
    $pdo = get_db();
    $filters = build_application_filters($_POST);

    $stmt = $pdo->prepare("SELECT id FROM applications {$filters['whereSql']} ORDER BY created_at DESC LIMIT " . MAX_BULK_REGENERATE);
    $stmt->execute($filters['params']);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $succeeded = 0;
    $failed = 0;
    foreach ($ids as $id) {
        try {
            generate_application_pdf($pdo, (int) $id);
            $succeeded++;
        } catch (Throwable $e) {
            error_log('[job-application-system] Bulk regenerate failed for application ' . $id . ': ' . $e->getMessage());
            $failed++;
        }
    }

    // Check whether more applications matched than the cap allows, so
    // the admin knows to run it again rather than assuming it's done.
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications {$filters['whereSql']}");
    $countStmt->execute($filters['params']);
    $totalMatching = (int) $countStmt->fetchColumn();
    $more = $totalMatching > MAX_BULK_REGENERATE ? '1' : '0';

    $location = 'dashboard.php?regen_success=' . $succeeded . '&regen_failed=' . $failed . '&regen_more=' . $more
        . ($redirectQuery ? '&' . $redirectQuery : '');
    header('Location: ' . $location);
    exit;

} catch (Throwable $e) {
    error_log('[job-application-system] Bulk regenerate failed entirely: ' . $e->getMessage());
    header('Location: dashboard.php?regen_error=1' . ($redirectQuery ? '&' . $redirectQuery : ''));
    exit;
}
