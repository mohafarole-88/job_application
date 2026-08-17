<?php
/**
 * config/config.php
 * Application-wide constants. No secrets belong in version control —
 * in a real deployment, load DB credentials from environment variables
 * instead of hardcoding them here.
 */

// ---- Debug mode ----
// When true, API error responses include the real exception message/
// file/line straight in the JSON — extremely useful while developing
// locally, but a real information-disclosure risk in production.
// MUST be false (or the env var set to "false") before this ever goes live.
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// ---- Database ----
define('DB_HOST', getenv('DB_HOST') ?: 'db.fr-roub1.bengt.wasmernet.com');
define('DB_PORT', getenv('DB_PORT') ?: '20184');
define('DB_NAME', getenv('DB_NAME') ?: 'db_58f75ce3');
define('DB_USER', getenv('DB_USER') ?: 'user_431b80b1');
define('DB_PASS', getenv('DB_PASS') ?: 'pw_MSrOdNl7qRXe9q8Hsf8ch8vPgC2uHgVK');
define('DB_CHARSET', 'utf8mb4');

// ---- Storage ----
// Kept OUTSIDE the public/ webroot on purpose so uploaded documents and
// generated PDFs are never directly reachable by URL (see §19/§20 of the spec).
define('STORAGE_ROOT', dirname(__DIR__) . '/storage');
define('APPLICATIONS_STORAGE', STORAGE_ROOT . '/applications');
define('TEMP_STORAGE', STORAGE_ROOT . '/temporary');

// ---- Uploads ----
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10MB per file
define('ALLOWED_DOCUMENT_MIME_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);
define('ALLOWED_PHOTO_MIME_TYPES', ['image/jpeg', 'image/png']);

// ---- Mandatory training courses (must match schema.sql ENUM + apply.js) ----
define('MANDATORY_TRAINING_COURSES', [
    'infection_prevention_and_control',
    'moving_and_handling',
    'safeguarding',
    'health_and_safety',
    'basic_first_aid',
    'medication_administration',
]);

// ---- Session ----
define('SESSION_NAME', 'jobapp_session');
define('ADMIN_SESSION_IDLE_TIMEOUT', 30 * 60); // 30 minutes

// ---- Error display ----
// NEVER enable display_errors in production — always log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- PDF generation & secure download (Phase 5) ----
define('PDF_DOWNLOAD_SECRET', getenv('PDF_DOWNLOAD_SECRET') ?: 'CHANGE_ME_TO_A_LONG_RANDOM_VALUE_IN_PRODUCTION');
define('PDF_DOWNLOAD_TOKEN_TTL', 60 * 60 * 24 * 7); // download links valid for 7 days

define('COMPANY_NAME', 'Sam&Mun Care Ltd');
define('COMPANY_TAGLINE', 'Quality care especially for you');
define('COMPANY_ADDRESS_LINE_1', 'G07 Lock Studios,');
define('COMPANY_ADDRESS_LINE_2', '7 Corsican Sq, London E3 3YD');

// Human-readable labels for the fixed mandatory training checklist,
// keyed the same way as MANDATORY_TRAINING_COURSES.
define('MANDATORY_TRAINING_LABELS', [
    'infection_prevention_and_control' => 'Infection prevention and control',
    'moving_and_handling'              => 'Moving and Handling',
    'safeguarding'                     => 'Safeguarding',
    'health_and_safety'                => 'Health and Safety (including fire risk)',
    'basic_first_aid'                  => 'Basic First Aid',
    'medication_administration'        => 'Medication Administration',
]);
