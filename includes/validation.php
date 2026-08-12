<?php
/**
 * includes/validation.php
 * Server-side validation. This is the ONLY validation that actually
 * matters for security/integrity — the JS in apply.js is just a
 * convenience layer and must never be relied on alone.
 */

class ValidationFailed extends RuntimeException
{
    /** @var array<string,string> */
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Validation failed.');
    }
}

function trimmed(array $data, string $key, string $default = ''): string
{
    return isset($data[$key]) ? trim((string) $data[$key]) : $default;
}

/**
 * Validates and returns a cleaned associative array of top-level
 * `applications` table fields. Throws ValidationFailed with a map of
 * field => message on any problem.
 */
function validate_application_fields(array $post): array
{
    $errors = [];
    $clean = [];

    $requiredText = [
        'position_applied'          => 'Position applied for',
        'first_name'                => 'First name(s)',
        'surname'                   => 'Surname',
        'nationality'                => 'Nationality',
        'current_address'           => 'Current address',
        'telephone'                  => 'Contact telephone',
        'emergency_contact_name'    => 'Emergency contact name',
        'emergency_contact_phone'   => 'Emergency contact telephone',
        'signature_name'            => 'Signature',
    ];
    foreach ($requiredText as $key => $label) {
        $value = trimmed($post, $key);
        if ($value === '') {
            $errors[$key] = "{$label} is required.";
        }
        $clean[$key] = $value;
    }

    // Date of birth
    $dob = trimmed($post, 'date_of_birth');
    if ($dob === '' || !is_valid_date($dob)) {
        $errors['date_of_birth'] = 'A valid date of birth is required.';
    }
    $clean['date_of_birth'] = $dob;

    // Age confirmation
    $clean['age_confirmation'] = !empty($post['age_confirmation']) ? 1 : 0;
    if (!$clean['age_confirmation']) {
        $errors['age_confirmation'] = 'You must confirm you are 18 or over.';
    }
    $clean['age_confirmation_initials'] = trimmed($post, 'age_confirmation_initials');
    if ($clean['age_confirmation'] && $clean['age_confirmation_initials'] === '') {
        $errors['age_confirmation_initials'] = 'Please provide your initials.';
    }

    // Email
    $email = trimmed($post, 'email');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    $clean['email'] = $email;

    // Optional address fields — pass through trimmed
    foreach ([
        'current_postcode', 'current_address_from', 'current_address_to',
        'previous_address', 'previous_postcode', 'previous_address_from', 'previous_address_to',
        'ni_number', 'driving_licence',
        'current_employer_name', 'current_employer_address', 'current_employer_postcode',
        'current_employer_phone', 'current_employer_email',
        'current_employment_start', 'current_employment_end',
        'reasonable_adjustment', 'employee_relationship_details',
        'criminal_conviction_details', 'dbs_level', 'dbs_expiry_date',
        'languages_fluent', 'languages_written', 'languages_basic',
        'admin_notes',
    ] as $optionalKey) {
        $clean[$optionalKey] = trimmed($post, $optionalKey);
    }
    $clean['dbs_expiry_date'] = $clean['dbs_expiry_date'] !== '' && is_valid_date($clean['dbs_expiry_date'])
        ? $clean['dbs_expiry_date'] : null;

    // Yes/No radio groups
    foreach ([
        'employee_relationship'      => 'Relationship to an employee',
        'criminal_conviction_status' => 'Criminal convictions question',
        'dbs_status'                 => 'DBS status question',
        'work_permit_required'       => 'UK work permit question',
    ] as $key => $label) {
        $value = trimmed($post, $key);
        if (!in_array($value, ['yes', 'no'], true)) {
            $errors[$key] = "{$label} must be answered.";
        }
        $clean[$key] = $value ?: 'no';
    }
    // dbs_online_access is optional yes/no/blank
    $dbsOnline = trimmed($post, 'dbs_online_access');
    $clean['dbs_online_access'] = in_array($dbsOnline, ['yes', 'no'], true) ? $dbsOnline : null;

    // Reference 1 required, Reference 2 optional
    $clean['ref1_manager_name']    = trimmed($post, 'ref1_manager_name');
    $clean['ref1_company_name']    = trimmed($post, 'ref1_company_name');
    if ($clean['ref1_manager_name'] === '') $errors['ref1_manager_name'] = 'Reference 1 manager name is required.';
    if ($clean['ref1_company_name'] === '') $errors['ref1_company_name'] = 'Reference 1 company name is required.';
    foreach (['ref1_job_title', 'ref1_company_address', 'ref1_company_phone', 'ref1_email', 'ref1_relationship',
              'ref2_manager_name', 'ref2_position_worked', 'ref2_company_name', 'ref2_company_address',
              'ref2_company_phone', 'ref2_company_fax', 'ref2_email', 'ref2_relationship'] as $refKey) {
        $clean[$refKey] = trimmed($post, $refKey);
    }

    // Declaration
    $clean['declaration_accepted'] = !empty($post['declaration_accepted']) ? 1 : 0;
    if (!$clean['declaration_accepted']) {
        $errors['declaration_accepted'] = 'You must accept the declaration to submit.';
    }
    $signatureDate = trimmed($post, 'signature_date');
    if ($signatureDate === '' || !is_valid_date($signatureDate)) {
        $errors['signature_date'] = 'A valid signature date is required.';
    }
    $clean['signature_date'] = $signatureDate;

    if (!empty($errors)) {
        throw new ValidationFailed($errors);
    }

    return $clean;
}

function is_valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

/**
 * Parses the employment[N][...] repeatable group from $_POST into a
 * clean, indexed list. Silently skips rows with no company name.
 */
function parse_employment_history(array $post): array
{
    $rows = [];
    if (empty($post['employment']) || !is_array($post['employment'])) {
        return $rows;
    }
    $order = 0;
    foreach ($post['employment'] as $entry) {
        $companyName = trim((string) ($entry['company_name'] ?? ''));
        if ($companyName === '') continue;
        $rows[] = [
            'sort_order'         => $order++,
            'company_name'       => $companyName,
            'date_from'          => trim((string) ($entry['date_from'] ?? '')),
            'date_to'            => trim((string) ($entry['date_to'] ?? '')),
            'position'           => trim((string) ($entry['position'] ?? '')),
            'reason_for_leaving' => trim((string) ($entry['reason_for_leaving'] ?? '')),
        ];
    }
    return $rows;
}

/**
 * Parses the qualifications[N][...] repeatable group.
 */
function parse_qualifications(array $post): array
{
    $rows = [];
    if (empty($post['qualifications']) || !is_array($post['qualifications'])) {
        return $rows;
    }
    $order = 0;
    foreach ($post['qualifications'] as $entry) {
        $title = trim((string) ($entry['course_title'] ?? ''));
        if ($title === '') continue;
        $rows[] = [
            'sort_order'     => $order++,
            'course_title'   => $title,
            'date_completed' => trim((string) ($entry['date_completed'] ?? '')),
            'awarding_body'  => trim((string) ($entry['awarding_body'] ?? '')),
        ];
    }
    return $rows;
}

/**
 * Parses the fixed training[course_key][...] checklist against the
 * known list of mandatory courses — ignores anything else submitted.
 */
function parse_mandatory_training(array $post): array
{
    $rows = [];
    $submitted = $post['training'] ?? [];
    foreach (MANDATORY_TRAINING_COURSES as $courseKey) {
        $entry = $submitted[$courseKey] ?? [];
        $dateCompleted = trim((string) ($entry['date_completed'] ?? ''));
        $rows[] = [
            'course_name'     => $courseKey,
            'date_completed'  => ($dateCompleted !== '' && is_valid_date($dateCompleted)) ? $dateCompleted : null,
            'needs_to_attend' => !empty($entry['needs_to_attend']) ? 1 : 0,
        ];
    }
    return $rows;
}
