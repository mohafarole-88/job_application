<?php
/**
 * public/download.php
 * Streams a submitted application's PDF, gated by a signed token
 * (see includes/download-token.php). No login required for the MVP —
 * the token itself is the credential, so it must never be logged,
 * cached publicly, or embedded anywhere but the success page/email.
 *
 * Usage: download.php?app=APP-2026-000123&token=...
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/download-token.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$applicationNumber = $_GET['app'] ?? '';
$token = $_GET['token'] ?? '';

if ($applicationNumber === '' || $token === '' || !verify_download_token($applicationNumber, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'This download link is invalid or has expired.';
    exit;
}

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, pdf_path FROM applications WHERE application_number = :num');
    $stmt->execute(['num' => $applicationNumber]);
    $app = $stmt->fetch();

    if (!$app || empty($app['pdf_path']) || !is_file($app['pdf_path'])) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'The requested file could not be found.';
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $applicationNumber . '.pdf"');
    header('Content-Length: ' . filesize($app['pdf_path']));
    header('X-Content-Type-Options: nosniff');
    readfile($app['pdf_path']);
    exit;

} catch (Throwable $e) {
    error_log('[job-application-system] Download failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Something went wrong retrieving your file.';
    exit;
}
