<?php
/**
 * scripts/create-admin.php
 * Run from the command line to create (or reset the password of) an
 * admin account. Deliberately NOT web-accessible — it lives outside
 * public/ and does nothing useful without CLI args.
 *
 * Usage:
 *   php scripts/create-admin.php "Jane Doe" jane@samandmuncare.co.uk
 * You'll be prompted for a password (hidden where the terminal supports it).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php create-admin.php \"Full Name\" email@example.com\n");
    exit(1);
}

$fullName = $argv[1];
$email = filter_var($argv[2], FILTER_VALIDATE_EMAIL);
if (!$email) {
    fwrite(STDERR, "Error: that doesn't look like a valid email address.\n");
    exit(1);
}

fwrite(STDOUT, "Password: ");
// Best-effort hide input on Linux/macOS terminals; on Windows it will just echo — that's fine for local dev setup.
if (stripos(PHP_OS, 'WIN') !== 0) {
    system('stty -echo');
}
$password = trim(fgets(STDIN));
if (stripos(PHP_OS, 'WIN') !== 0) {
    system('stty echo');
    fwrite(STDOUT, "\n");
}

if (strlen($password) < 10) {
    fwrite(STDERR, "Error: password must be at least 10 characters.\n");
    exit(1);
}

try {
    $pdo = get_db();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO admins (full_name, email, password_hash, is_active)
         VALUES (:full_name, :email, :hash, 1)
         ON DUPLICATE KEY UPDATE full_name = :full_name2, password_hash = :hash2, is_active = 1'
    );
    $stmt->execute([
        'full_name'  => $fullName,
        'email'      => $email,
        'hash'       => $hash,
        'full_name2' => $fullName,
        'hash2'      => $hash,
    ]);

    fwrite(STDOUT, "Admin account ready: {$email}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to create admin: " . $e->getMessage() . "\n");
    exit(1);
}
