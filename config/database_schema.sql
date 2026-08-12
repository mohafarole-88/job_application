-- ============================================================
-- Job Application Management System — Database Schema
-- Phase 2 — MySQL / MariaDB
-- Built from Phase 1 field mapping (phase1-field-mapping.md)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- admins
-- ------------------------------------------------------------
CREATE TABLE admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,   -- password_hash() output
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- applications  (primary record — one row per applicant submission)
-- ------------------------------------------------------------
CREATE TABLE applications (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_number          VARCHAR(20) NOT NULL,           -- APP-2026-000125

    -- Position
    position_applied            VARCHAR(150) NOT NULL,          -- free text (confirmed)

    -- Personal details
    first_name                  VARCHAR(100) NOT NULL,
    surname                     VARCHAR(100) NOT NULL,
    date_of_birth               DATE NOT NULL,                  -- confirmed: collected
    age_confirmation             TINYINT(1) NOT NULL DEFAULT 0,  -- 18+ tick box (kept alongside DOB)
    age_confirmation_initials   VARCHAR(10) NULL,
    nationality                 VARCHAR(100) NOT NULL,

    -- Current address
    current_address             TEXT NOT NULL,
    current_postcode            VARCHAR(20) NULL,
    current_address_from        VARCHAR(20) NULL,               -- form uses year/free text, not strict dates
    current_address_to          VARCHAR(20) NULL,

    -- Previous address
    previous_address            TEXT NULL,
    previous_postcode           VARCHAR(20) NULL,
    previous_address_from       VARCHAR(20) NULL,
    previous_address_to         VARCHAR(20) NULL,

    -- Contact
    telephone                   VARCHAR(30) NOT NULL,
    email                       VARCHAR(190) NOT NULL,
    emergency_contact_name      VARCHAR(150) NOT NULL,
    emergency_contact_phone     VARCHAR(30) NOT NULL,
    ni_number                   VARCHAR(20) NULL,
    driving_licence             VARCHAR(50) NULL,

    -- Current/most recent employment
    current_employer_name       VARCHAR(150) NULL,
    current_employer_address    TEXT NULL,
    current_employer_postcode   VARCHAR(20) NULL,
    current_employer_phone      VARCHAR(30) NULL,
    current_employer_email      VARCHAR(190) NULL,
    current_employment_start    VARCHAR(20) NULL,
    current_employment_end      VARCHAR(20) NULL,

    -- Reasonable adjustment / relationship to employee
    reasonable_adjustment       TEXT NULL,
    employee_relationship       ENUM('yes','no') NOT NULL DEFAULT 'no',
    employee_relationship_details VARCHAR(255) NULL,

    -- Criminal convictions / DBS
    criminal_conviction_status  ENUM('yes','no') NOT NULL DEFAULT 'no',
    criminal_conviction_details TEXT NULL,
    dbs_status                  ENUM('yes','no') NOT NULL DEFAULT 'no',
    dbs_level                   VARCHAR(50) NULL,
    dbs_expiry_date             DATE NULL,
    dbs_online_access           ENUM('yes','no') NULL,

    -- Entitlement to work
    work_permit_required        ENUM('yes','no') NOT NULL DEFAULT 'no',

    -- Languages
    languages_fluent            VARCHAR(255) NULL,
    languages_written           VARCHAR(255) NULL,
    languages_basic             VARCHAR(255) NULL,

    -- Declaration & signature
    declaration_accepted        TINYINT(1) NOT NULL DEFAULT 0,
    signature_name              VARCHAR(150) NOT NULL,          -- typed name (confirmed)
    signature_date              DATE NOT NULL,

    -- Admin workflow (kept minimal — no scoring UI per MVP scope)
    admin_notes                 TEXT NULL,

    -- System
    status                      ENUM('submitted','reviewed','shortlisted','rejected','archived') NOT NULL DEFAULT 'submitted',
    pdf_path                    VARCHAR(255) NULL,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_applications_number (application_number),
    KEY idx_applications_position (position_applied),
    KEY idx_applications_status (status),
    KEY idx_applications_created (created_at),
    KEY idx_applications_name (surname, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- employment_history  (repeatable, unbounded)
-- ------------------------------------------------------------
CREATE TABLE employment_history (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id      INT UNSIGNED NOT NULL,
    sort_order           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    company_name        VARCHAR(150) NOT NULL,
    date_from            VARCHAR(20) NULL,
    date_to               VARCHAR(20) NULL,
    position             VARCHAR(150) NULL,
    reason_for_leaving   VARCHAR(255) NULL,

    CONSTRAINT fk_employment_history_application
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    KEY idx_employment_history_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- qualifications  (repeatable — courses/qualifications/training)
-- ------------------------------------------------------------
CREATE TABLE qualifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id      INT UNSIGNED NOT NULL,
    sort_order           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    course_title         VARCHAR(150) NOT NULL,
    date_completed        VARCHAR(20) NULL,
    awarding_body         VARCHAR(150) NULL,

    CONSTRAINT fk_qualifications_application
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    KEY idx_qualifications_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- training  (fixed 6-row mandatory training checklist — not user-repeatable)
-- ------------------------------------------------------------
CREATE TABLE training (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id      INT UNSIGNED NOT NULL,
    course_name         ENUM(
                            'infection_prevention_and_control',
                            'moving_and_handling',
                            'safeguarding',
                            'health_and_safety',
                            'basic_first_aid',
                            'medication_administration'
                         ) NOT NULL,
    date_completed        DATE NULL,
    needs_to_attend      TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_training_application
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    UNIQUE KEY uq_training_app_course (application_id, course_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- references  (fixed at exactly 2 rows per application: current + previous)
-- ------------------------------------------------------------
CREATE TABLE `references` (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id      INT UNSIGNED NOT NULL,
    ref_type             ENUM('current','previous') NOT NULL,
    manager_name         VARCHAR(150) NULL,
    job_title             VARCHAR(150) NULL,   -- used for ref_type='current' ("Job title of Referee")
    position_worked      VARCHAR(150) NULL,    -- used for ref_type='previous' ("Position worked")
    company_name         VARCHAR(150) NULL,
    company_address      VARCHAR(255) NULL,
    company_phone        VARCHAR(30) NULL,
    company_fax          VARCHAR(30) NULL,     -- ref_type='previous' only
    email                VARCHAR(190) NULL,
    relationship          VARCHAR(100) NULL,

    CONSTRAINT fk_references_application
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    UNIQUE KEY uq_references_app_type (application_id, ref_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- documents  (uploaded files: photo, CV, certificates, etc.)
-- ------------------------------------------------------------
CREATE TABLE documents (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id      INT UNSIGNED NOT NULL,
    doc_type             ENUM('photo','cv','certificate','other') NOT NULL DEFAULT 'other',
    original_filename    VARCHAR(255) NOT NULL,   -- stored for display only, never trusted for paths
    stored_filename      VARCHAR(255) NOT NULL,   -- randomly generated safe filename
    storage_path         VARCHAR(255) NOT NULL,   -- e.g. storage/applications/APP-2026-000001/cv.pdf
    mime_type             VARCHAR(100) NOT NULL,
    file_size_bytes       INT UNSIGNED NOT NULL,
    uploaded_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_documents_application
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    KEY idx_documents_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- eo_monitoring  (Religion — confirmed: separate, optional, anonymous)
-- Deliberately has NO foreign key back to applications, so it can
-- never be joined to a named applicant record.
-- ------------------------------------------------------------
CREATE TABLE eo_monitoring (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    position_applied VARCHAR(150) NULL,   -- optional aggregate context only, no name/contact info
    religion         VARCHAR(50) NULL,
    submitted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- application_number sequence helper
-- A dedicated counter table avoids race conditions from
-- SELECT MAX(...)+1 under concurrent submissions.
-- ------------------------------------------------------------
CREATE TABLE application_number_seq (
    year            SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    last_number     INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usage pattern (in PHP, inside a transaction):
--   INSERT INTO application_number_seq (year, last_number) VALUES (YEAR(NOW()), 1)
--     ON DUPLICATE KEY UPDATE last_number = last_number + 1;
--   SELECT last_number FROM application_number_seq WHERE year = YEAR(NOW());
--   -- format as APP-{year}-{last_number zero-padded to 6 digits}

SET FOREIGN_KEY_CHECKS = 1;
