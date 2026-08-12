<?php
/**
 * admin/download-document.php?doc_id=123
 * Streams an uploaded applicant document (photo/CV/certificate).
 * Gated by admin session — unlike the applicant's own PDF download
 * (which uses a signed token since applicants have no login), admins
 * are already authenticated, so a plain session check is sufficient
 * and simpler here.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin_auth();

$docId = (int) ($_GET['doc_id'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    exit('Invalid document.');
}

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = :id');
    $stmt->execute(['id' => $docId]);
    $doc = $stmt->fetch();

    if (!$doc || !is_file($doc['storage_path'])) {
        http_response_code(404);
        exit('Document not found.');
    }

    header('Content-Type: ' . $doc['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($doc['original_filename']) . '"');
    header('Content-Length: ' . filesize($doc['storage_path']));
    header('X-Content-Type-Options: nosniff');
    readfile($doc['storage_path']);
    exit;

} catch (Throwable $e) {
    error_log('[job-application-system] Admin document download failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Something went wrong retrieving this file.');
}
