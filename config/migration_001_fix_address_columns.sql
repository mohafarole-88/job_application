-- Run this in phpMyAdmin's SQL tab, on the job_application_system database.
-- Fixes: these three fields are <textarea> on the applicant form (multi-line,
-- free text) but were defined as VARCHAR(255) in the original schema, which
-- rejects or truncates longer addresses. TEXT removes the length ceiling.

ALTER TABLE applications
    MODIFY COLUMN current_address TEXT NOT NULL,
    MODIFY COLUMN previous_address TEXT NULL,
    MODIFY COLUMN current_employer_address TEXT NULL;
