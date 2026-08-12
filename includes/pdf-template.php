<?php
/**
 * includes/pdf-template.php
 * Builds the HTML that Dompdf renders into the submitted-application PDF.
 * Visual rules are deliberately matched to the completed example PDF
 * from Phase 1 (see phase1-field-mapping.md → "Visual Rendering Notes"):
 *   1. label + value inline on the same line, not label-then-newline
 *   2. booleans render as a green check icon, not "Yes"/text
 *   3. optional/blank sections render as empty cells, no "N/A" filler
 *   4. Religion is never shown here — it's deliberately out of scope
 */

function pdf_esc(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Renders "Label: value" inline, matching the benchmark's inline style. */
function pdf_field(string $label, ?string $value): string
{
    $value = trim((string) $value);
    return '<strong>' . pdf_esc($label) . ':</strong> ' . pdf_esc($value);
}

/** Green check icon for boolean/tick fields, empty otherwise — never "Yes"/"X". */
function pdf_check(bool $checked): string
{
    return $checked ? '<span class="check-icon">&#10003;</span>' : '';
}

function pdf_yesno(?string $value): string
{
    return $value === 'yes' ? 'Yes' : ($value === 'no' ? 'No' : '');
}

/**
 * @param array      $app          Row from `applications`
 * @param array      $employment   Rows from `employment_history`, ordered
 * @param array      $qualifications Rows from `qualifications`, ordered
 * @param array      $training     Rows from `training`, one per MANDATORY_TRAINING_COURSES key
 * @param array      $references   Rows from `references` (ref_type => row)
 * @param array|null $photo        Row from `documents` where doc_type='photo', or null
 */
function render_application_pdf_html(array $app, array $employment, array $qualifications, array $training, array $references, ?array $photo): string
{
    $ref1 = $references['current'] ?? null;
    $ref2 = $references['previous'] ?? null;

    ob_start();
    ?>
    <html>
    <head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 70px 40px 60px 40px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #1C2624;
        }
        .pdf-header {
            position: fixed;
            top: -55px;
            left: 0;
            right: 0;
            height: 45px;
            text-align: center;
        }
        .pdf-header .brand {
            font-size: 14px;
            font-weight: bold;
            color: #154C50;
        }
        .pdf-header .tagline {
            font-size: 8px;
            color: #5C6B67;
        }
        .pdf-footer {
            position: fixed;
            bottom: -45px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #5C6B67;
        }
        h1.form-title {
            text-align: center;
            font-size: 15px;
            margin: 0 0 2px 0;
        }
        .confidential {
            font-weight: bold;
            font-size: 9px;
            letter-spacing: 1px;
        }
        .app-number {
            text-align: right;
            font-size: 9px;
            color: #5C6B67;
            margin-bottom: 4px;
        }
        table.section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.section td, table.section th {
            border: 1px solid #B9C6C1;
            padding: 4px 6px;
            vertical-align: top;
        }
        table.section.keep-together {
            page-break-inside: avoid;
        }
        table.section th.section-title {
            background: #E4F0EF;
            font-size: 10.5px;
            text-align: left;
            font-weight: bold;
        }
        .col-2 { width: 50%; }
        .check-icon {
            color: #2E7D5B;
            font-weight: bold;
        }
        .photo-box {
            float: right;
            width: 80px;
            height: 100px;
            border: 1px solid #B9C6C1;
            text-align: center;
            font-size: 8px;
            color: #5C6B67;
            padding: 2px;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .signature-name {
            font-family: 'DejaVu Sans', sans-serif;
            font-style: italic;
            font-size: 13px;
        }
        .small-note {
            font-size: 8.5px;
            color: #5C6B67;
        }
    </style>
    </head>
    <body>

    <div class="pdf-header">
        <div class="brand"><?php echo pdf_esc(COMPANY_NAME); ?></div>
        <div class="tagline"><?php echo pdf_esc(COMPANY_TAGLINE); ?></div>
    </div>
    <div class="pdf-footer">
        <?php echo pdf_esc(COMPANY_ADDRESS_LINE_1); ?><br>
        <?php echo pdf_esc(COMPANY_ADDRESS_LINE_2); ?>
    </div>

    <div class="confidential">CONFIDENTIAL</div>
    <h1 class="form-title">JOB APPLICATION FORM</h1>
    <div class="app-number">Application No: <?php echo pdf_esc($app['application_number']); ?> &nbsp;|&nbsp; Submitted: <?php echo pdf_esc(date('d/m/Y', strtotime($app['created_at']))); ?></div>

    <?php if ($photo): ?>
        <div class="photo-box"><img src="<?php echo pdf_esc($photo['storage_path']); ?>" alt="Photo"></div>
    <?php endif; ?>

    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Position &amp; Personal Details</th></tr>
        <tr><td colspan="2"><?php echo pdf_field('Position applied for', $app['position_applied']); ?></td></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('First name(s)', $app['first_name']); ?></td>
            <td class="col-2"><?php echo pdf_field('Surname', $app['surname']); ?></td>
        </tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Date of birth', $app['date_of_birth'] ? date('d/m/Y', strtotime($app['date_of_birth'])) : ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Nationality', $app['nationality']); ?></td>
        </tr>
        <tr><td colspan="2"><?php echo pdf_field('Current address', $app['current_address']); ?></td></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Postcode', $app['current_postcode']); ?></td>
            <td class="col-2"><?php echo pdf_field('Moved', trim(($app['current_address_from'] ?: '') . ' to ' . ($app['current_address_to'] ?: ''), ' to')); ?></td>
        </tr>
        <?php if (!empty($app['previous_address'])): ?>
        <tr><td colspan="2"><?php echo pdf_field('Previous address', $app['previous_address']); ?></td></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Postcode', $app['previous_postcode']); ?></td>
            <td class="col-2"><?php echo pdf_field('Moved', trim(($app['previous_address_from'] ?: '') . ' to ' . ($app['previous_address_to'] ?: ''), ' to')); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="col-2"><?php echo pdf_field('Contact telephone', $app['telephone']); ?></td>
            <td class="col-2"><?php echo pdf_field('Email address', $app['email']); ?></td>
        </tr>
        <tr><td colspan="2"><?php echo pdf_field('Emergency contact', $app['emergency_contact_name'] . ' — ' . $app['emergency_contact_phone']); ?></td></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('National Insurance number', $app['ni_number']); ?></td>
            <td class="col-2"><?php echo pdf_field('Driving licence', $app['driving_licence']); ?></td>
        </tr>
        <tr>
            <td colspan="2">
                <?php echo pdf_check((bool) $app['age_confirmation']); ?>
                <strong>I confirm that I am 18 years or over</strong>
                &nbsp;&nbsp;Initials: <?php echo pdf_esc($app['age_confirmation_initials']); ?>
            </td>
        </tr>
    </table>

    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Present or Most Recent Post</th></tr>
        <tr><td colspan="2"><?php echo pdf_field("Employer's name", $app['current_employer_name']); ?></td></tr>
        <tr><td colspan="2"><?php echo pdf_field("Employer's address", $app['current_employer_address'] . ($app['current_employer_postcode'] ? ', ' . $app['current_employer_postcode'] : '')); ?></td></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Telephone', $app['current_employer_phone']); ?></td>
            <td class="col-2"><?php echo pdf_field('Email', $app['current_employer_email']); ?></td>
        </tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Start date', $app['current_employment_start']); ?></td>
            <td class="col-2"><?php echo pdf_field('Left date', $app['current_employment_end']); ?></td>
        </tr>
    </table>

    <?php if ($employment): ?>
    <table class="section">
        <tr><th class="section-title" colspan="5">Employment History</th></tr>
        <tr>
            <th>Company</th><th>Date from</th><th>Date to</th><th>Position</th><th>Reason for leaving</th>
        </tr>
        <?php foreach ($employment as $row): ?>
        <tr>
            <td><?php echo pdf_esc($row['company_name']); ?></td>
            <td><?php echo pdf_esc($row['date_from']); ?></td>
            <td><?php echo pdf_esc($row['date_to']); ?></td>
            <td><?php echo pdf_esc($row['position']); ?></td>
            <td><?php echo pdf_esc($row['reason_for_leaving']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($qualifications): ?>
    <table class="section">
        <tr><th class="section-title" colspan="3">Courses, Qualifications &amp; Other Training</th></tr>
        <tr><th>Course/Training</th><th>Date</th><th>Awarding Body/Qualification</th></tr>
        <?php foreach ($qualifications as $row): ?>
        <tr>
            <td><?php echo pdf_esc($row['course_title']); ?></td>
            <td><?php echo pdf_esc($row['date_completed']); ?></td>
            <td><?php echo pdf_esc($row['awarding_body']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <table class="section">
        <tr><th class="section-title" colspan="3">Mandatory Training Courses Undertaken</th></tr>
        <tr><th>Course/Training</th><th>Date</th><th>Needs to Attend</th></tr>
        <?php foreach (MANDATORY_TRAINING_LABELS as $key => $label):
            $row = $training[$key] ?? ['date_completed' => null, 'needs_to_attend' => 0]; ?>
        <tr>
            <td><?php echo pdf_esc($label); ?></td>
            <td><?php echo pdf_esc($row['date_completed'] ? date('d/m/Y', strtotime($row['date_completed'])) : ''); ?></td>
            <td style="text-align:center;"><?php echo pdf_check((bool) $row['needs_to_attend']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if (!empty($app['reasonable_adjustment']) || $app['employee_relationship'] === 'yes'): ?>
    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Reasonable Adjustment &amp; Relationships</th></tr>
        <?php if (!empty($app['reasonable_adjustment'])): ?>
        <tr><td colspan="2"><?php echo pdf_field('Reasonable adjustment', $app['reasonable_adjustment']); ?></td></tr>
        <?php endif; ?>
        <tr>
            <td colspan="2">
                <?php echo pdf_field('Relative or partner of an employee?', pdf_yesno($app['employee_relationship'])); ?>
                <?php if ($app['employee_relationship'] === 'yes'): ?>
                    &nbsp;&nbsp;<?php echo pdf_field('Details', $app['employee_relationship_details']); ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Reference 1 — Current or Most Recent Employer</th></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Manager name', $ref1['manager_name'] ?? ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Job title of referee', $ref1['job_title'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Company name', $ref1['company_name'] ?? ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Company address', $ref1['company_address'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Company telephone', $ref1['company_phone'] ?? ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Email', $ref1['email'] ?? ''); ?></td>
        </tr>
        <tr><td colspan="2"><?php echo pdf_field('Relationship to you', $ref1['relationship'] ?? ''); ?></td></tr>
    </table>

    <?php if ($ref2): ?>
    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Reference 2 — Previous Employer</th></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Manager name', $ref2['manager_name'] ?? ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Position worked', $ref2['position_worked'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Company name', $ref2['company_name'] ?? ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Company address', $ref2['company_address'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Company telephone', $ref2['company_phone'] ?? ''); ?></td>
            <td class="col-2"><?php echo pdf_field('Email', $ref2['email'] ?? ''); ?></td>
        </tr>
        <tr><td colspan="2"><?php echo pdf_field('Relationship to you', $ref2['relationship'] ?? ''); ?></td></tr>
    </table>
    <?php endif; ?>

    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Criminal Convictions</th></tr>
        <tr><td colspan="2"><?php echo pdf_field('Never cautioned/reprimanded/bound over/convicted', pdf_yesno($app['criminal_conviction_status'])); ?></td></tr>
        <?php if (!empty($app['criminal_conviction_details'])): ?>
        <tr><td colspan="2"><?php echo pdf_field('Details', $app['criminal_conviction_details']); ?></td></tr>
        <?php endif; ?>
        <tr>
            <td class="col-2"><?php echo pdf_field('Current DBS check', pdf_yesno($app['dbs_status'])); ?></td>
            <td class="col-2"><?php echo pdf_field('Access to online DBS update', pdf_yesno($app['dbs_online_access'])); ?></td>
        </tr>
        <?php if ($app['dbs_status'] === 'yes'): ?>
        <tr>
            <td class="col-2"><?php echo pdf_field('DBS level', $app['dbs_level']); ?></td>
            <td class="col-2"><?php echo pdf_field('Expiry date', $app['dbs_expiry_date'] ? date('d/m/Y', strtotime($app['dbs_expiry_date'])) : ''); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Entitlement to Work in the UK &amp; Languages</th></tr>
        <tr><td colspan="2"><?php echo pdf_field('Required to have a UK work permit?', pdf_yesno($app['work_permit_required'])); ?></td></tr>
        <tr>
            <td class="col-2"><?php echo pdf_field('Fluent', $app['languages_fluent']); ?></td>
            <td class="col-2"><?php echo pdf_field('Written', $app['languages_written']); ?></td>
        </tr>
        <tr><td colspan="2"><?php echo pdf_field('Basic', $app['languages_basic']); ?></td></tr>
    </table>

    <table class="section keep-together">
        <tr><th class="section-title" colspan="2">Declaration</th></tr>
        <tr>
            <td colspan="2" class="small-note">
                I declare that the information given on this form is correct to the best of my knowledge.
                Information on this form may be held on computer/manual records.
            </td>
        </tr>
        <tr>
            <td class="col-2">
                <strong>Applicant signature:</strong><br>
                <span class="signature-name"><?php echo pdf_esc($app['signature_name']); ?></span>
            </td>
            <td class="col-2"><?php echo pdf_field('Date', $app['signature_date'] ? date('d/m/Y', strtotime($app['signature_date'])) : ''); ?></td>
        </tr>
    </table>

    </body>
    </html>
    <?php
    return ob_get_clean();
}
