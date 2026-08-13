<?php
/**
 * admin/download-all-applications.php
 * Zips together the generated PDF for every application matching the
 * current dashboard filters (or all applications, if none are set)
 * into a single archive. Respects the exact same search/status/position
 * filters as the dashboard list — see includes/application-search.php —
 * so "what you're looking at" and "what you export" always match.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/application-search.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('The PHP zip extension is not enabled on this server. Enable ext-zip in php.ini and restart Apache.');
}

// Sanity cap: building an enormous zip on a shared-hosting-style PHP
// process can exhaust memory/time limits. Ask the admin to narrow their
// filters rather than silently failing partway through on huge exports.
const MAX_BULK_EXPORT_APPLICATIONS = 500;

try {
    $pdo = get_db();
    $filters = build_application_filters($_GET);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications {$filters['whereSql']}");
    $countStmt->execute($filters['params']);
    $totalMatching = (int) $countStmt->fetchColumn();

    if ($totalMatching === 0) {
        http_response_code(404);
        exit('No applications match the current filters.');
    }
    if ($totalMatching > MAX_BULK_EXPORT_APPLICATIONS) {
        http_response_code(413);
        exit("That's {$totalMatching} applications — please narrow your search/filters to " . MAX_BULK_EXPORT_APPLICATIONS . ' or fewer before exporting in bulk.');
    }

    $stmt = $pdo->prepare(
        "SELECT id, application_number, first_name, surname, position_applied, status, pdf_path
         FROM applications
         {$filters['whereSql']}
         ORDER BY created_at DESC"
    );
    $stmt->execute($filters['params']);
    $applications = $stmt->fetchAll();

    if (!is_dir(TEMP_STORAGE)) {
        mkdir(TEMP_STORAGE, 0750, true);
    }
    $tmpZipPath = TEMP_STORAGE . '/bulk_export_' . bin2hex(random_bytes(8)) . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create zip archive.');
    }

    $included = 0;
    $skipped = [];
    $usedNames = [];

    foreach ($applications as $app) {
        if (empty($app['pdf_path']) || !is_file($app['pdf_path'])) {
            $skipped[] = $app['application_number'] . ' — ' . $app['first_name'] . ' ' . $app['surname'] . ' (no PDF generated yet)';
            continue;
        }

        $safeNumber = preg_replace('/[^A-Za-z0-9\-]/', '', $app['application_number']);
        $safeName = preg_replace('/[^A-Za-z0-9]+/', '_', trim($app['first_name'] . '_' . $app['surname'], '_'));
        $entryName = "{$safeNumber}_{$safeName}.pdf";

        // Guard against duplicate entry names (shouldn't happen since
        // application_number is unique, but stay safe regardless).
        if (isset($usedNames[$entryName])) {
            $usedNames[$entryName]++;
            $entryName = "{$safeNumber}_{$safeName}_{$usedNames[$entryName]}.pdf";
        } else {
            $usedNames[$entryName] = 1;
        }

        $zip->addFile($app['pdf_path'], $entryName);
        $included++;
    }

    // A short manifest is genuinely useful here — if 40 PDFs land in one
    // zip, the admin has no way to tell which applications (if any) got
    // silently left out without opening every single one.
    $manifestLines = [];
    $manifestLines[] = 'Bulk export generated ' . date('d/m/Y H:i');
    $manifestLines[] = "Filters: " .
        ($filters['search'] !== '' ? "search=\"{$filters['search']}\" " : '') .
        ($filters['status'] !== '' ? "status={$filters['status']} " : '') .
        ($filters['position'] !== '' ? "position=\"{$filters['position']}\" " : '');
    if (trim(end($manifestLines)) === 'Filters:') {
        $manifestLines[count($manifestLines) - 1] = 'Filters: none (all applications)';
    }
    $manifestLines[] = '';
    $manifestLines[] = "{$included} of {$totalMatching} matching application(s) included.";
    if ($skipped) {
        $manifestLines[] = '';
        $manifestLines[] = 'Skipped (no PDF generated yet):';
        foreach ($skipped as $line) {
            $manifestLines[] = '  - ' . $line;
        }
    }
    $zip->addFromString('README.txt', implode("\n", $manifestLines));

    $zip->close();

    if ($included === 0) {
        unlink($tmpZipPath);
        http_response_code(404);
        exit('None of the matching applications have a generated PDF yet.');
    }

    $downloadName = 'applications_export_' . date('Y-m-d_His') . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($tmpZipPath));
    header('X-Content-Type-Options: nosniff');
    readfile($tmpZipPath);
    unlink($tmpZipPath);
    exit;

} catch (Throwable $e) {
    error_log('[job-application-system] Bulk export failed: ' . $e->getMessage());
    if (isset($tmpZipPath) && is_file($tmpZipPath)) {
        unlink($tmpZipPath);
    }
    http_response_code(500);
    exit('Something went wrong building the export.');
}
