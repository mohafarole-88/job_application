<?php
/**
 * includes/process-application.php
 * Handles POST /public/apply.php submissions.
 * Included by apply.php only when REQUEST_METHOD === 'POST'.
 * Always responds with JSON via json_response()/fail_gracefully().
 *
 * Workflow (per spec §11 / §18):
 *   HTML Form -> PHP validation -> MySQL -> (PDF generation is Phase 5)
 */

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/generate-pdf.php';
require_once __DIR__ . '/download-token.php';
require_once __DIR__ . '/../config/database.php';

try {
    // ---- CSRF ----
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        error_log('[job-application-system][DEBUG] CSRF verification failed');
        json_response(['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'], 419);
    }
    error_log('[job-application-system][DEBUG] CSRF verified');

    // ---- Validate core fields (throws ValidationFailed on error) ----
    try {
        $fields = validate_application_fields($_POST);
    } catch (ValidationFailed $vf) {
        error_log('[job-application-system][DEBUG] Field validation failed: ' . json_encode($vf->errors));
        json_response(['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $vf->errors], 422);
    }
    error_log('[job-application-system][DEBUG] Field validation passed');

    $employmentHistory = parse_employment_history($_POST);
    $qualifications    = parse_qualifications($_POST);
    $mandatoryTraining = parse_mandatory_training($_POST);

    $pdo = get_db();
    error_log('[job-application-system][DEBUG] Database connection established');
    $pdo->beginTransaction();

    try {
        // ---- Application number (row-locked counter, collision-safe) ----
        $applicationNumber = generate_application_number($pdo);
        error_log('[job-application-system][DEBUG] Application number generated: ' . $applicationNumber);

        // ---- Insert primary application record ----
        $insertSql = 'INSERT INTO applications (
            application_number, position_applied, first_name, surname, date_of_birth,
            age_confirmation, age_confirmation_initials, nationality,
            current_address, current_postcode, current_address_from, current_address_to,
            previous_address, previous_postcode, previous_address_from, previous_address_to,
            telephone, email, emergency_contact_name, emergency_contact_phone,
            ni_number, driving_licence,
            current_employer_name, current_employer_address, current_employer_postcode,
            current_employer_phone, current_employer_email, current_employment_start, current_employment_end,
            reasonable_adjustment, employee_relationship, employee_relationship_details,
            criminal_conviction_status, criminal_conviction_details,
            dbs_status, dbs_level, dbs_expiry_date, dbs_online_access,
            work_permit_required, languages_fluent, languages_written, languages_basic,
            declaration_accepted, signature_name, signature_date,
            status, created_at, updated_at
        ) VALUES (
            :application_number, :position_applied, :first_name, :surname, :date_of_birth,
            :age_confirmation, :age_confirmation_initials, :nationality,
            :current_address, :current_postcode, :current_address_from, :current_address_to,
            :previous_address, :previous_postcode, :previous_address_from, :previous_address_to,
            :telephone, :email, :emergency_contact_name, :emergency_contact_phone,
            :ni_number, :driving_licence,
            :current_employer_name, :current_employer_address, :current_employer_postcode,
            :current_employer_phone, :current_employer_email, :current_employment_start, :current_employment_end,
            :reasonable_adjustment, :employee_relationship, :employee_relationship_details,
            :criminal_conviction_status, :criminal_conviction_details,
            :dbs_status, :dbs_level, :dbs_expiry_date, :dbs_online_access,
            :work_permit_required, :languages_fluent, :languages_written, :languages_basic,
            :declaration_accepted, :signature_name, :signature_date,
            "submitted", NOW(), NOW()
        )';

        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            'application_number'          => $applicationNumber,
            'position_applied'            => $fields['position_applied'],
            'first_name'                  => $fields['first_name'],
            'surname'                     => $fields['surname'],
            'date_of_birth'               => $fields['date_of_birth'],
            'age_confirmation'            => $fields['age_confirmation'],
            'age_confirmation_initials'   => $fields['age_confirmation_initials'],
            'nationality'                 => $fields['nationality'],
            'current_address'             => $fields['current_address'],
            'current_postcode'            => $fields['current_postcode'],
            'current_address_from'        => $fields['current_address_from'],
            'current_address_to'          => $fields['current_address_to'],
            'previous_address'            => $fields['previous_address'],
            'previous_postcode'           => $fields['previous_postcode'],
            'previous_address_from'       => $fields['previous_address_from'],
            'previous_address_to'         => $fields['previous_address_to'],
            'telephone'                   => $fields['telephone'],
            'email'                       => $fields['email'],
            'emergency_contact_name'      => $fields['emergency_contact_name'],
            'emergency_contact_phone'     => $fields['emergency_contact_phone'],
            'ni_number'                   => $fields['ni_number'],
            'driving_licence'             => $fields['driving_licence'],
            'current_employer_name'       => $fields['current_employer_name'],
            'current_employer_address'    => $fields['current_employer_address'],
            'current_employer_postcode'   => $fields['current_employer_postcode'],
            'current_employer_phone'      => $fields['current_employer_phone'],
            'current_employer_email'      => $fields['current_employer_email'],
            'current_employment_start'    => $fields['current_employment_start'],
            'current_employment_end'      => $fields['current_employment_end'],
            'reasonable_adjustment'       => $fields['reasonable_adjustment'],
            'employee_relationship'       => $fields['employee_relationship'],
            'employee_relationship_details' => $fields['employee_relationship_details'],
            'criminal_conviction_status'  => $fields['criminal_conviction_status'],
            'criminal_conviction_details' => $fields['criminal_conviction_details'],
            'dbs_status'                  => $fields['dbs_status'],
            'dbs_level'                   => $fields['dbs_level'],
            'dbs_expiry_date'             => $fields['dbs_expiry_date'],
            'dbs_online_access'           => $fields['dbs_online_access'],
            'work_permit_required'        => $fields['work_permit_required'],
            'languages_fluent'            => $fields['languages_fluent'],
            'languages_written'           => $fields['languages_written'],
            'languages_basic'             => $fields['languages_basic'],
            'declaration_accepted'        => $fields['declaration_accepted'],
            'signature_name'              => $fields['signature_name'],
            'signature_date'              => $fields['signature_date'],
        ]);
        $applicationId = (int) $pdo->lastInsertId();
        error_log('[job-application-system][DEBUG] Application row inserted, id=' . $applicationId);

        // ---- Employment history ----
        if ($employmentHistory) {
            $stmt = $pdo->prepare(
                'INSERT INTO employment_history (application_id, sort_order, company_name, date_from, date_to, position, reason_for_leaving)
                 VALUES (:application_id, :sort_order, :company_name, :date_from, :date_to, :position, :reason_for_leaving)'
            );
            foreach ($employmentHistory as $row) {
                $stmt->execute(['application_id' => $applicationId] + $row);
            }
        }

        // ---- Qualifications ----
        if ($qualifications) {
            $stmt = $pdo->prepare(
                'INSERT INTO qualifications (application_id, sort_order, course_title, date_completed, awarding_body)
                 VALUES (:application_id, :sort_order, :course_title, :date_completed, :awarding_body)'
            );
            foreach ($qualifications as $row) {
                $stmt->execute(['application_id' => $applicationId] + $row);
            }
        }

        // ---- Mandatory training (always all 6 rows) ----
        $stmt = $pdo->prepare(
            'INSERT INTO training (application_id, course_name, date_completed, needs_to_attend)
             VALUES (:application_id, :course_name, :date_completed, :needs_to_attend)'
        );
        foreach ($mandatoryTraining as $row) {
            $stmt->execute(['application_id' => $applicationId] + $row);
        }

        // ---- References (Reference 1 always, Reference 2 only if provided) ----
        $stmt = $pdo->prepare(
            'INSERT INTO `references` (application_id, ref_type, manager_name, job_title, position_worked, company_name, company_address, company_phone, company_fax, email, relationship)
             VALUES (:application_id, :ref_type, :manager_name, :job_title, :position_worked, :company_name, :company_address, :company_phone, :company_fax, :email, :relationship)'
        );
        $stmt->execute([
            'application_id'  => $applicationId,
            'ref_type'        => 'current',
            'manager_name'    => $fields['ref1_manager_name'],
            'job_title'       => $fields['ref1_job_title'],
            'position_worked' => null,
            'company_name'    => $fields['ref1_company_name'],
            'company_address' => $fields['ref1_company_address'],
            'company_phone'   => $fields['ref1_company_phone'],
            'company_fax'     => null,
            'email'           => $fields['ref1_email'],
            'relationship'    => $fields['ref1_relationship'],
        ]);
        if ($fields['ref2_company_name'] !== '' || $fields['ref2_manager_name'] !== '') {
            $stmt->execute([
                'application_id'  => $applicationId,
                'ref_type'        => 'previous',
                'manager_name'    => $fields['ref2_manager_name'],
                'job_title'       => null,
                'position_worked' => $fields['ref2_position_worked'],
                'company_name'    => $fields['ref2_company_name'],
                'company_address' => $fields['ref2_company_address'],
                'company_phone'   => $fields['ref2_company_phone'],
                'company_fax'     => $fields['ref2_company_fax'],
                'email'           => $fields['ref2_email'],
                'relationship'    => $fields['ref2_relationship'],
            ]);
        }

        // ---- Documents (photo, CV, certificates) ----
        $storageDir = application_storage_dir($applicationNumber);
        $docStmt = $pdo->prepare(
            'INSERT INTO documents (application_id, doc_type, original_filename, stored_filename, storage_path, mime_type, file_size_bytes)
             VALUES (:application_id, :doc_type, :original_filename, :stored_filename, :storage_path, :mime_type, :file_size_bytes)'
        );

        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $doc = handle_uploaded_file($_FILES['photo'], ALLOWED_PHOTO_MIME_TYPES, $storageDir, 'photo');
            $docStmt->execute([
                'application_id'     => $applicationId,
                'doc_type'           => $doc['doc_type'],
                'original_filename'  => $doc['original_filename'],
                'stored_filename'    => $doc['stored_filename'],
                'storage_path'       => $doc['storage_path'],
                'mime_type'          => $doc['mime_type'],
                'file_size_bytes'    => $doc['file_size_bytes'],
            ]);
        }

        if (!empty($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
            $doc = handle_uploaded_file($_FILES['cv'], ALLOWED_DOCUMENT_MIME_TYPES, $storageDir, 'cv');
            $docStmt->execute(['application_id' => $applicationId, 'doc_type' => $doc['doc_type'],
                'original_filename' => $doc['original_filename'], 'stored_filename' => $doc['stored_filename'],
                'storage_path' => $doc['storage_path'], 'mime_type' => $doc['mime_type'], 'file_size_bytes' => $doc['file_size_bytes']]);
        }

        if (!empty($_FILES['certificates'])) {
            foreach (normalize_multi_file_input($_FILES['certificates']) as $certFile) {
                $doc = handle_uploaded_file($certFile, ALLOWED_DOCUMENT_MIME_TYPES, $storageDir, 'certificate');
                $docStmt->execute(['application_id' => $applicationId, 'doc_type' => $doc['doc_type'],
                    'original_filename' => $doc['original_filename'], 'stored_filename' => $doc['stored_filename'],
                    'storage_path' => $doc['storage_path'], 'mime_type' => $doc['mime_type'], 'file_size_bytes' => $doc['file_size_bytes']]);
            }
        }

        // ---- PDF generation happens in Phase 5. pdf_path stays NULL until then. ----
        error_log('[job-application-system][DEBUG] All inserts complete, committing transaction');

        $pdo->commit();
        error_log('[job-application-system][DEBUG] Transaction committed successfully for ' . $applicationNumber);

        // ---- PDF generation (Phase 5) ----
        // Runs AFTER commit, in its own try/catch: if PDF rendering fails
        // for any reason, the application itself has still been saved
        // successfully — we don't want a template/library problem to
        // make a valid submission look like it failed.
        $pdfUrl = null;
        try {
            generate_application_pdf($pdo, $applicationId);
            $downloadToken = generate_download_token($applicationNumber);
            $pdfUrl = 'download.php?app=' . urlencode($applicationNumber) . '&token=' . urlencode($downloadToken);
            error_log('[job-application-system][DEBUG] PDF generated for ' . $applicationNumber);
        } catch (Throwable $pdfError) {
            error_log('[job-application-system][DEBUG] PDF generation failed (application still saved): ' . $pdfError->getMessage());
        }

        json_response([
            'success'            => true,
            'application_number' => $applicationNumber,
            'pdf_url'            => $pdfUrl,
        ]);

    } catch (UploadRejected $ur) {
        error_log('[job-application-system][DEBUG] Upload rejected: ' . $ur->getMessage());
        $pdo->rollBack();
        json_response(['success' => false, 'message' => $ur->getMessage()], 422);
    } catch (Throwable $inner) {
        error_log('[job-application-system][DEBUG] Exception during insert phase: ' . $inner->getMessage());
        $pdo->rollBack();
        throw $inner;
    }

} catch (Throwable $e) {
    error_log('[job-application-system][DEBUG] Outer exception caught: ' . $e->getMessage());
    fail_gracefully($e);
}
