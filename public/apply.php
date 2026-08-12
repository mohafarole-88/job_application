<?php
/**
 * public/apply.php
 * GET  -> renders the applicant-facing form (this file's HTML below).
 * POST -> delegates to includes/process-application.php, which
 *         validates, saves to MySQL, stores documents, and responds
 *         with JSON. PDF generation is added in Phase 5.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fatal errors (e.g. a missing require, a typo'd function call) don't
    // pass through normal try/catch. This shutdown handler makes sure
    // that IF one happens, the browser still gets valid JSON (instead of
    // an empty/broken body that just shows the generic banner) and the
    // real cause gets logged with a distinct, greppable prefix.
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log('[job-application-system][FATAL] ' . $error['message']
                . ' in ' . $error['file'] . ' on line ' . $error['line']);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            $payload = [
                'success' => false,
                'message' => 'A server error occurred while submitting your application.',
            ];
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $payload['debug'] = [
                    'exception' => 'FatalError',
                    'message'   => $error['message'],
                    'file'      => basename($error['file']),
                    'line'      => $error['line'],
                ];
            }
            echo json_encode($payload);
        }
    });

    error_log('[job-application-system][DEBUG] POST received, routing to process-application.php');
    require __DIR__ . '/../includes/process-application.php';
    exit; // process-application.php always responds via json_response()/fail_gracefully()
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Application Form — Sam&amp;Mun Care Ltd</title>
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>

<header class="site-header">
  <div class="logo-mark" aria-hidden="true"></div>
  <div class="brand-text">
    <strong>Sam&amp;Mun Care Ltd</strong>
    <span>Quality care especially for you</span>
  </div>
</header>

<main class="page-shell">

  <!-- ============ INTRO ============ -->
  <section class="intro-card" id="intro-card">
    <span class="confidential-tag">Confidential</span>
    <h1>Job Application Form</h1>
    <p>Please complete every section as fully as you can. You can move back and forth between steps before submitting, and you'll get a chance to review everything at the end. Fields marked <span class="required-mark">*</span> are required.</p>
  </section>

  <!-- ============ PROGRESS ============ -->
  <nav class="progress-track" id="progress-track" aria-label="Application progress"></nav>
  <p class="progress-label" id="progress-label"></p>

  <div class="form-status-banner is-error" id="form-status-banner" role="alert"></div>

  <form id="application-form" novalidate>

    <!-- STEP 1 — Position & Personal Details -->
    <section class="form-step" data-step="1" data-title="Personal Details">
      <h2>Position &amp; Personal Details</h2>
      <p class="step-intro">Tell us who you are and which role you're applying for.</p>

      <div class="field-group">
        <label for="position_applied">Position applied for <span class="required-mark">*</span></label>
        <input type="text" id="position_applied" name="position_applied" required>
      </div>

      <div class="field-group">
        <label for="photo">Photo <span class="optional-tag">(optional)</span></label>
        <input type="file" id="photo" name="photo" accept="image/*">
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="first_name">First name(s) <span class="required-mark">*</span></label>
          <input type="text" id="first_name" name="first_name" required>
        </div>
        <div class="field-group">
          <label for="surname">Surname <span class="required-mark">*</span></label>
          <input type="text" id="surname" name="surname" required>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="date_of_birth">Date of birth <span class="required-mark">*</span></label>
          <input type="date" id="date_of_birth" name="date_of_birth" required>
        </div>
        <div class="field-group">
          <label for="nationality">Nationality <span class="required-mark">*</span></label>
          <input type="text" id="nationality" name="nationality" required>
        </div>
      </div>

      <div class="checkbox-line field-group">
        <input type="checkbox" id="age_confirmation" name="age_confirmation" required>
        <div>
          <label for="age_confirmation" style="margin-bottom:2px;">I confirm that I am 18 years or over <span class="required-mark">*</span></label>
          <p>In line with the Equality Act, we only ask you to confirm you're over 18 — the statutory minimum age to provide care to vulnerable people.</p>
          <div class="field-group" style="margin-top:8px; max-width:160px;">
            <label for="age_confirmation_initials">Initials <span class="required-mark">*</span></label>
            <input type="text" id="age_confirmation_initials" name="age_confirmation_initials" maxlength="10">
          </div>
        </div>
      </div>

      <div class="field-group">
        <label for="current_address">Current address <span class="required-mark">*</span></label>
        <textarea id="current_address" name="current_address" required></textarea>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="current_postcode">Postcode</label>
          <input type="text" id="current_postcode" name="current_postcode">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="current_address_from">Date you moved to this address — from</label>
          <input type="text" id="current_address_from" name="current_address_from" placeholder="e.g. 2021">
        </div>
        <div class="field-group">
          <label for="current_address_to">to</label>
          <input type="text" id="current_address_to" name="current_address_to" placeholder="e.g. Present">
        </div>
      </div>

      <div class="field-group">
        <label for="previous_address">Previous address <span class="optional-tag">(optional)</span></label>
        <textarea id="previous_address" name="previous_address"></textarea>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="previous_postcode">Postcode</label>
          <input type="text" id="previous_postcode" name="previous_postcode">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="previous_address_from">Date you moved to this address — from</label>
          <input type="text" id="previous_address_from" name="previous_address_from">
        </div>
        <div class="field-group">
          <label for="previous_address_to">to</label>
          <input type="text" id="previous_address_to" name="previous_address_to">
        </div>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="telephone">Contact telephone <span class="required-mark">*</span></label>
          <input type="tel" id="telephone" name="telephone" required>
        </div>
        <div class="field-group">
          <label for="email">Email address <span class="required-mark">*</span></label>
          <input type="email" id="email" name="email" required>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="emergency_contact_name">Emergency contact name <span class="required-mark">*</span></label>
          <input type="text" id="emergency_contact_name" name="emergency_contact_name" required>
        </div>
        <div class="field-group">
          <label for="emergency_contact_phone">Emergency contact telephone <span class="required-mark">*</span></label>
          <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" required>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="ni_number">National Insurance number</label>
          <input type="text" id="ni_number" name="ni_number">
        </div>
        <div class="field-group">
          <label for="driving_licence">Driving licence</label>
          <input type="text" id="driving_licence" name="driving_licence" placeholder="e.g. Full UK, N/A">
        </div>
      </div>
    </section>

    <!-- STEP 2 — Current Employment -->
    <section class="form-step" data-step="2" data-title="Current Employment">
      <h2>Present or Most Recent Post</h2>
      <p class="step-intro">If this is your first role, leave this section blank and continue.</p>

      <div class="field-group">
        <label for="current_employer_name">Employer's name</label>
        <input type="text" id="current_employer_name" name="current_employer_name">
      </div>
      <div class="field-group">
        <label for="current_employer_address">Employer's address</label>
        <textarea id="current_employer_address" name="current_employer_address"></textarea>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="current_employer_postcode">Postcode</label>
          <input type="text" id="current_employer_postcode" name="current_employer_postcode">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="current_employer_phone">Telephone</label>
          <input type="tel" id="current_employer_phone" name="current_employer_phone">
        </div>
        <div class="field-group">
          <label for="current_employer_email">Email</label>
          <input type="email" id="current_employer_email" name="current_employer_email">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="current_employment_start">Start date</label>
          <input type="text" id="current_employment_start" name="current_employment_start" placeholder="e.g. Dec 2021">
        </div>
        <div class="field-group">
          <label for="current_employment_end">Left date</label>
          <input type="text" id="current_employment_end" name="current_employment_end" placeholder="Leave blank if current">
        </div>
      </div>
    </section>

    <!-- STEP 3 — Employment History -->
    <section class="form-step" data-step="3" data-title="Employment History">
      <h2>Employment History</h2>
      <p class="step-intro">List your work history from leaving school, most recent first, including any periods of non-employment, unpaid voluntary work or study.</p>
      <div id="employment-history-list"></div>
      <button type="button" class="btn-add" id="add-employment">+ Add another employment record</button>
    </section>

    <!-- STEP 4 — Qualifications & Mandatory Training -->
    <section class="form-step" data-step="4" data-title="Qualifications & Training">
      <h2>Courses, Qualifications &amp; Other Training</h2>
      <p class="step-intro">Anything relevant to this job application.</p>
      <div id="qualifications-list"></div>
      <button type="button" class="btn-add" id="add-qualification">+ Add another course / qualification</button>

      <h2 style="margin-top: var(--space-6);">Mandatory Training Courses Undertaken</h2>
      <p class="step-intro">Attendance certificates must be provided as proof of training being "in-date." If you don't have a certificate, tick "needs to attend."</p>
      <table class="training-table">
        <thead>
          <tr><th>Course</th><th>Date completed</th><th>Needs to attend</th></tr>
        </thead>
        <tbody id="mandatory-training-body"></tbody>
      </table>
    </section>

    <!-- STEP 5 — Adjustments & Relationship -->
    <section class="form-step" data-step="5" data-title="Adjustments">
      <h2>Reasonable Adjustment &amp; Relationships</h2>

      <div class="field-group">
        <label for="reasonable_adjustment">If you consider yourself disabled under the Equality Act 2010 and would like an adjustment for interview, describe what support you might need <span class="optional-tag">(optional)</span></label>
        <textarea id="reasonable_adjustment" name="reasonable_adjustment"></textarea>
      </div>

      <div class="field-group">
        <label>Are you a relative or partner of an employee of the company? <span class="required-mark">*</span></label>
        <div class="choice-group">
          <label class="choice-option"><input type="radio" name="employee_relationship" value="yes" required> Yes</label>
          <label class="choice-option"><input type="radio" name="employee_relationship" value="no" required> No</label>
        </div>
      </div>
      <div class="field-group" id="employee_relationship_details_wrap" style="display:none;">
        <label for="employee_relationship_details">Please provide their name and how you're related</label>
        <input type="text" id="employee_relationship_details" name="employee_relationship_details">
      </div>
    </section>

    <!-- STEP 6 — References -->
    <section class="form-step" data-step="6" data-title="References">
      <h2>References</h2>
      <p class="step-intro">Please read this section carefully.</p>

      <h3 style="font-size:0.95rem; color: var(--color-primary);">Reference 1 — Current or most recent employer <span class="required-mark">*</span></h3>
      <div class="field-row">
        <div class="field-group">
          <label for="ref1_manager_name">Manager name <span class="required-mark">*</span></label>
          <input type="text" id="ref1_manager_name" name="ref1_manager_name" required>
        </div>
        <div class="field-group">
          <label for="ref1_job_title">Job title of referee</label>
          <input type="text" id="ref1_job_title" name="ref1_job_title">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="ref1_company_name">Company name <span class="required-mark">*</span></label>
          <input type="text" id="ref1_company_name" name="ref1_company_name" required>
        </div>
        <div class="field-group">
          <label for="ref1_company_address">Company address</label>
          <input type="text" id="ref1_company_address" name="ref1_company_address">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="ref1_company_phone">Company telephone</label>
          <input type="tel" id="ref1_company_phone" name="ref1_company_phone">
        </div>
        <div class="field-group">
          <label for="ref1_email">Email address</label>
          <input type="email" id="ref1_email" name="ref1_email">
        </div>
      </div>
      <div class="field-group">
        <label for="ref1_relationship">Relationship to you (e.g. Line Manager)</label>
        <input type="text" id="ref1_relationship" name="ref1_relationship">
      </div>

      <h3 style="font-size:0.95rem; color: var(--color-primary); margin-top: var(--space-5);">Reference 2 — Previous employer <span class="optional-tag">(optional)</span></h3>
      <div class="field-row">
        <div class="field-group">
          <label for="ref2_manager_name">Manager name</label>
          <input type="text" id="ref2_manager_name" name="ref2_manager_name">
        </div>
        <div class="field-group">
          <label for="ref2_position_worked">Position worked</label>
          <input type="text" id="ref2_position_worked" name="ref2_position_worked">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="ref2_company_name">Company name</label>
          <input type="text" id="ref2_company_name" name="ref2_company_name">
        </div>
        <div class="field-group">
          <label for="ref2_company_address">Company address</label>
          <input type="text" id="ref2_company_address" name="ref2_company_address">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="ref2_company_phone">Company telephone</label>
          <input type="tel" id="ref2_company_phone" name="ref2_company_phone">
        </div>
        <div class="field-group">
          <label for="ref2_company_fax">Fax number</label>
          <input type="text" id="ref2_company_fax" name="ref2_company_fax">
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="ref2_email">Email address</label>
          <input type="email" id="ref2_email" name="ref2_email">
        </div>
        <div class="field-group">
          <label for="ref2_relationship">Relationship to you</label>
          <input type="text" id="ref2_relationship" name="ref2_relationship">
        </div>
      </div>
    </section>

    <!-- STEP 7 — Criminal Convictions / DBS -->
    <section class="form-step" data-step="7" data-title="DBS & Convictions">
      <h2>Criminal Convictions</h2>
      <p class="step-intro">Some posts are exempt from the Rehabilitation of Offenders Act 1974. Most posts are subject to DBS checks. A criminal conviction will not necessarily debar you from employment.</p>

      <div class="field-group">
        <label>Have you never been cautioned / reprimanded / bound over / convicted of a crime (spent or otherwise)? <span class="required-mark">*</span></label>
        <div class="choice-group">
          <label class="choice-option"><input type="radio" name="criminal_conviction_status" value="yes" required> Yes</label>
          <label class="choice-option"><input type="radio" name="criminal_conviction_status" value="no" required> No</label>
        </div>
      </div>
      <div class="field-group">
        <label for="criminal_conviction_details">If yes, please give details</label>
        <textarea id="criminal_conviction_details" name="criminal_conviction_details"></textarea>
      </div>

      <div class="field-group">
        <label>Do you have a current DBS check? <span class="required-mark">*</span></label>
        <div class="choice-group">
          <label class="choice-option"><input type="radio" name="dbs_status" value="yes" required> Yes</label>
          <label class="choice-option"><input type="radio" name="dbs_status" value="no" required> No</label>
        </div>
      </div>
      <div class="field-row" id="dbs-detail-fields" style="display:none;">
        <div class="field-group">
          <label for="dbs_level">What level?</label>
          <input type="text" id="dbs_level" name="dbs_level">
        </div>
        <div class="field-group">
          <label for="dbs_expiry_date">Expiry date</label>
          <input type="date" id="dbs_expiry_date" name="dbs_expiry_date">
        </div>
      </div>
      <div class="field-group">
        <label>Do you have access to online DBS update?</label>
        <div class="choice-group">
          <label class="choice-option"><input type="radio" name="dbs_online_access" value="yes"> Yes</label>
          <label class="choice-option"><input type="radio" name="dbs_online_access" value="no"> No</label>
        </div>
      </div>
    </section>

    <!-- STEP 8 — Entitlement to Work & Languages -->
    <section class="form-step" data-step="8" data-title="Right to Work">
      <h2>Entitlement to Work in the UK</h2>
      <div class="field-group">
        <label>Are you required to have a UK work permit? <span class="required-mark">*</span></label>
        <div class="choice-group">
          <label class="choice-option"><input type="radio" name="work_permit_required" value="yes" required> Yes</label>
          <label class="choice-option"><input type="radio" name="work_permit_required" value="no" required> No</label>
        </div>
        <p class="field-hint">Evidence of your entitlement to work will be requested at interview. No offer of employment will be made until this is validated.</p>
      </div>

      <h2 style="margin-top: var(--space-6);">Languages</h2>
      <div class="field-group">
        <label for="languages_fluent">Fluent</label>
        <input type="text" id="languages_fluent" name="languages_fluent">
      </div>
      <div class="field-group">
        <label for="languages_written">Written</label>
        <input type="text" id="languages_written" name="languages_written">
      </div>
      <div class="field-group">
        <label for="languages_basic">Basic</label>
        <input type="text" id="languages_basic" name="languages_basic">
      </div>
    </section>

    <!-- STEP 9 — Documents -->
    <section class="form-step" data-step="9" data-title="Documents">
      <h2>Supporting Documents</h2>
      <p class="step-intro">Upload your CV and any relevant certificates. Accepted formats: PDF, JPG, PNG. Max 10MB per file.</p>
      <div class="field-group">
        <label for="cv">CV <span class="optional-tag">(optional)</span></label>
        <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx">
      </div>
      <div class="field-group">
        <label for="certificates">Certificates <span class="optional-tag">(optional — select multiple)</span></label>
        <input type="file" id="certificates" name="certificates[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
      </div>
    </section>

    <!-- STEP 10 — Declaration & Signature -->
    <section class="form-step" data-step="10" data-title="Declaration">
      <h2>Declaration</h2>
      <div class="checkbox-line field-group">
        <input type="checkbox" id="declaration_accepted" name="declaration_accepted" required>
        <div>
          <label for="declaration_accepted" style="margin-bottom:2px;">I declare this information is correct <span class="required-mark">*</span></label>
          <p>I declare that the information given on this form is correct to the best of my knowledge. Information on this form may be held on computer/manual records. I understand that any false information or misinterpretation would result in my application being disqualified, or if appointed, could lead to disciplinary action including dismissal. I consent to Sam&amp;Mun Care Ltd holding this information securely. If my application is unsuccessful, the data will be held for 6 months and then destroyed.</p>
        </div>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="signature_name">Type your full name as your signature <span class="required-mark">*</span></label>
          <input type="text" id="signature_name" name="signature_name" required>
        </div>
        <div class="field-group">
          <label for="signature_date">Date <span class="required-mark">*</span></label>
          <input type="date" id="signature_date" name="signature_date" required>
        </div>
      </div>
    </section>

    <!-- STEP 11 — Review -->
    <section class="form-step" data-step="11" data-title="Review & Submit">
      <h2>Review Your Application</h2>
      <p class="step-intro">Please check everything carefully before submitting. Use "Edit" next to any section to make changes.</p>
      <div id="review-content"></div>
    </section>

    <div class="step-nav">
      <button type="button" class="btn btn-secondary" id="btn-back">Back</button>
      <button type="button" class="btn btn-primary" id="btn-next">Continue</button>
      <button type="submit" class="btn btn-primary" id="btn-submit" style="display:none;">Submit Application</button>
    </div>
  </form>

  <!-- ============ SUCCESS STATE (hidden until submit) ============ -->
  <section class="intro-card success-card" id="success-card" style="display:none;">
    <div class="success-icon" aria-hidden="true">&#10003;</div>
    <h1>Application Submitted Successfully</h1>
    <p>Thank you for submitting your application. Please keep your application number for your records.</p>
    <div class="application-number" id="success-application-number">APP-2026-000000</div>
    <p><a href="#" id="success-pdf-link">Download a copy of your submitted application (PDF)</a></p>
  </section>

</main>

<script src="assets/js/apply.js"></script>
</body>
</html>
