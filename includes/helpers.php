<?php
/**
 * includes/helpers.php
 * Small shared utilities used across the applicant and admin flows.
 */

/**
 * Send a JSON response and stop execution. Used by every endpoint the
 * frontend fetch() calls talk to.
 */
function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Send a friendly, non-technical error response and log the real
 * exception server-side. The real message/file/line is NEVER exposed
 * to the client unless APP_DEBUG is explicitly true (local dev only).
 */
function fail_gracefully(Throwable $e, string $publicMessage = 'We could not submit your application. Please check your information and try again.', int $statusCode = 400): void
{
    error_log('[job-application-system] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

    $payload = ['success' => false, 'message' => $publicMessage];

    if (defined('APP_DEBUG') && APP_DEBUG) {
        $payload['debug'] = [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => basename($e->getFile()),
            'line'      => $e->getLine(),
        ];
    }

    json_response($payload, $statusCode);
}

/**
 * Atomically generate the next application number for the current year,
 * e.g. APP-2026-000125. Uses a dedicated counter table + row lock so
 * concurrent submissions never collide (see schema.sql).
 * Must be called from inside an open PDO transaction.
 */
function generate_application_number(PDO $pdo): string
{
    $year = (int) date('Y');

    $stmt = $pdo->prepare(
        'INSERT INTO application_number_seq (year, last_number)
         VALUES (:year, 1)
         ON DUPLICATE KEY UPDATE last_number = last_number + 1'
    );
    $stmt->execute(['year' => $year]);

    $stmt = $pdo->prepare('SELECT last_number FROM application_number_seq WHERE year = :year');
    $stmt->execute(['year' => $year]);
    $lastNumber = (int) $stmt->fetchColumn();

    return sprintf('APP-%d-%06d', $year, $lastNumber);
}

/**
 * Build a safe, non-guessable filename for a stored upload.
 * Never trust the original filename for anything beyond display.
 */
function safe_stored_filename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext);
    // Never allow PHP (or other executable) extensions to survive into storage.
    $blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'sh', 'exe'];
    if (in_array($ext, $blocked, true)) {
        $ext = 'bin';
    }
    $random = bin2hex(random_bytes(16));
    return $ext ? "{$random}.{$ext}" : $random;
}

/**
 * Build the storage directory for a given application number, creating
 * it if needed. Directory name is derived only from the server-generated
 * application number — never from user input — so there is no path
 * traversal surface here.
 */
function application_storage_dir(string $applicationNumber): string
{
    $safe = preg_replace('/[^A-Za-z0-9\-]/', '', $applicationNumber);
    $dir = APPLICATIONS_STORAGE . '/' . $safe;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function sanitize_name_for_filename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9]+/', '_', trim($name));
    return trim($name, '_');
}
