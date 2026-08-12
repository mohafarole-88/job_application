<?php
/**
 * admin/download-all.php?id=123
 * Zips together everything on file for one application — the
 * generated PDF plus every uploaded document (photo, CV, certificates)
 * — and streams it as a single download. Admin-session gated, same as
 * download-document.php.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/application-data.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid application.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('The PHP zip extension is not enabled on this server. Enable ext-zip in php.ini and restart Apache.');
}

try {
    $pdo = get_db();
    $data = fetch_application_full($pdo, $id);
    if ($data === null) {
        http_response_code(404);
        exit('Application not found.');
    }
    $app = $data['application'];
    $documents = $data['documents'];

    if (empty($app['pdf_path']) && !$documents) {
        http_response_code(404);
        exit('There are no files on file for this application yet.');
    }

    // Build the zip in the application's own temp area, not in a
    // web-accessible location, then stream it and delete it.
    $safeNumber = preg_replace('/[^A-Za-z0-9\-]/', '', $app['application_number']);
    $tmpZipPath = TEMP_STORAGE . '/' . $safeNumber . '_' . bin2hex(random_bytes(6)) . '.zip';
    if (!is_dir(TEMP_STORAGE)) {
        mkdir(TEMP_STORAGE, 0750, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create zip archive.');
    }

    // The generated application PDF, if it exists.
    if (!empty($app['pdf_path']) && is_file($app['pdf_path'])) {
        $zip->addFile($app['pdf_path'], 'Application_Form.pdf');
    }

    // Every uploaded document, named clearly and de-duplicated if there
    // happen to be multiple of the same type (e.g. several certificates).
    $usedNames = [];
    $typeLabels = ['photo' => 'Photo', 'cv' => 'CV', 'certificate' => 'Certificate', 'other' => 'Document'];
    foreach ($documents as $doc) {
        if (!is_file($doc['storage_path'])) {
            continue; // file went missing on disk — skip rather than fail the whole zip
        }
        $ext = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
        $baseLabel = $typeLabels[$doc['doc_type']] ?? 'Document';
        $entryName = $ext ? "{$baseLabel}.{$ext}" : $baseLabel;

        // Avoid overwriting entries when there's more than one certificate, etc.
        if (isset($usedNames[$entryName])) {
            $usedNames[$entryName]++;
            $entryName = $ext
                ? "{$baseLabel}_{$usedNames[$entryName]}.{$ext}"
                : "{$baseLabel}_{$usedNames[$entryName]}";
        } else {
            $usedNames[$entryName] = 1;
        }

        $zip->addFile($doc['storage_path'], $entryName);
    }

    $zip->close();

    $downloadName = $safeNumber . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', trim($app['first_name'] . '_' . $app['surname'], '_')) . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($tmpZipPath));
    header('X-Content-Type-Options: nosniff');
    readfile($tmpZipPath);
    unlink($tmpZipPath);
    exit;

} catch (Throwable $e) {
    error_log('[job-application-system] download-all failed: ' . $e->getMessage());
    if (isset($tmpZipPath) && is_file($tmpZipPath)) {
        unlink($tmpZipPath);
    }
    http_response_code(500);
    exit('Something went wrong building the download.');
}
