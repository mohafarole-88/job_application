/* ============================================================
   Sam&Mun Care Ltd — Job Application Form
   Vanilla JS wizard controller
   Phase 3: frontend only. Submission currently POSTs to apply.php
   as multipart/form-data; Phase 4 wires up the PHP handler that
   validates, saves to MySQL, stores documents, and generates the PDF.
   ============================================================ */
(function () {
  "use strict";

  const form = document.getElementById("application-form");
  const steps = Array.from(document.querySelectorAll(".form-step"));
  const progressTrack = document.getElementById("progress-track");
  const progressLabel = document.getElementById("progress-label");
  const btnBack = document.getElementById("btn-back");
  const btnNext = document.getElementById("btn-next");
  const btnSubmit = document.getElementById("btn-submit");
  const statusBanner = document.getElementById("form-status-banner");

  let currentStep = 0;

  const MANDATORY_TRAINING_COURSES = [
    { key: "infection_prevention_and_control", label: "Infection prevention and control" },
    { key: "moving_and_handling", label: "Moving and Handling" },
    { key: "safeguarding", label: "Safeguarding" },
    { key: "health_and_safety", label: "Health and Safety (including fire risk)" },
    { key: "basic_first_aid", label: "Basic First Aid" },
    { key: "medication_administration", label: "Medication Administration" }
  ];

  /* ---------------- Progress track ---------------- */
  function buildProgressTrack() {
    progressTrack.innerHTML = "";
    steps.forEach((step, i) => {
      const stepEl = document.createElement("div");
      stepEl.className = "progress-step";
      stepEl.dataset.index = i;
      stepEl.innerHTML = `<span class="progress-dot">${i + 1}</span>`;
      progressTrack.appendChild(stepEl);
      if (i < steps.length - 1) {
        const connector = document.createElement("div");
        connector.className = "progress-connector";
        progressTrack.appendChild(connector);
      }
    });
  }

  function updateProgress() {
    const dots = progressTrack.querySelectorAll(".progress-step");
    const connectors = progressTrack.querySelectorAll(".progress-connector");
    dots.forEach((dot, i) => {
      dot.classList.toggle("is-active", i === currentStep);
      dot.classList.toggle("is-complete", i < currentStep);
    });
    connectors.forEach((c, i) => c.classList.toggle("is-complete", i < currentStep));
    progressLabel.innerHTML = `Step ${currentStep + 1} of ${steps.length} — <strong>${steps[currentStep].dataset.title}</strong>`;
  }

  /* ---------------- Step navigation ---------------- */
  function showStep(index) {
    steps.forEach((s, i) => s.classList.toggle("is-active", i === index));
    currentStep = index;
    updateProgress();
    btnBack.style.display = index === 0 ? "none" : "inline-block";
    const isLast = index === steps.length - 1;
    btnNext.style.display = isLast ? "none" : "inline-block";
    btnSubmit.style.display = isLast ? "inline-block" : "none";
    if (isLast) renderReview();
    window.scrollTo({ top: form.offsetTop - 20, behavior: "smooth" });
    hideBanner();
  }

  function validateStep(index) {
    const step = steps[index];
    const fields = step.querySelectorAll("input, select, textarea");
    let valid = true;
    let firstInvalid = null;

    // group radios by name so we only report one error per group
    const seenRadioGroups = new Set();

    fields.forEach((field) => {
      clearFieldError(field);
      if (field.type === "radio") {
        if (seenRadioGroups.has(field.name)) return;
        seenRadioGroups.add(field.name);
        const group = step.querySelectorAll(`input[name="${field.name}"]`);
        const isRequired = Array.from(group).some((g) => g.required);
        const checked = Array.from(group).some((g) => g.checked);
        if (isRequired && !checked) {
          valid = false;
          if (!firstInvalid) firstInvalid = field;
          showFieldError(field, "Please choose an option.");
        }
        return;
      }
      if (!field.checkValidity()) {
        valid = false;
        if (!firstInvalid) firstInvalid = field;
        showFieldError(field, field.validationMessage || "Please complete this field.");
      }
    });

    if (!valid && firstInvalid) {
      firstInvalid.focus();
    }
    return valid;
  }

  function showFieldError(field, message) {
    field.classList.add("field-error");
    let msgEl = field.parentElement.querySelector(".error-message");
    if (!msgEl) {
      msgEl = document.createElement("div");
      msgEl.className = "error-message";
      field.parentElement.appendChild(msgEl);
    }
    msgEl.textContent = message;
    msgEl.classList.add("is-visible");
  }

  function clearFieldError(field) {
    field.classList.remove("field-error");
    const msgEl = field.parentElement.querySelector(".error-message");
    if (msgEl) msgEl.classList.remove("is-visible");
  }

  function showBanner(message) {
    statusBanner.textContent = message;
    statusBanner.classList.add("is-visible");
  }
  function hideBanner() {
    statusBanner.classList.remove("is-visible");
  }

  btnNext.addEventListener("click", () => {
    if (!validateStep(currentStep)) {
      showBanner("Please fix the highlighted fields before continuing.");
      return;
    }
    if (currentStep < steps.length - 1) showStep(currentStep + 1);
  });

  btnBack.addEventListener("click", () => {
    if (currentStep > 0) showStep(currentStep - 1);
  });

  /* ---------------- Conditional fields ---------------- */
  form.addEventListener("change", (e) => {
    if (e.target.name === "employee_relationship") {
      document.getElementById("employee_relationship_details_wrap").style.display =
        e.target.value === "yes" ? "block" : "none";
    }
    if (e.target.name === "dbs_status") {
      document.getElementById("dbs-detail-fields").style.display =
        e.target.value === "yes" ? "flex" : "none";
    }
  });

  /* ---------------- Repeatable: Employment History ---------------- */
  let employmentCount = 0;
  const employmentList = document.getElementById("employment-history-list");
  document.getElementById("add-employment").addEventListener("click", () => addEmploymentRow());

  function addEmploymentRow() {
    employmentCount++;
    const idx = employmentCount;
    const wrap = document.createElement("div");
    wrap.className = "repeat-item";
    wrap.dataset.employmentIndex = idx;
    wrap.innerHTML = `
      <div class="repeat-item-header">
        <h3>Employment ${idx}</h3>
        ${idx > 1 ? '<button type="button" class="btn-remove">Remove</button>' : ""}
      </div>
      <div class="field-group">
        <label for="emp_company_${idx}">Employer / company name ${idx === 1 ? '<span class="required-mark">*</span>' : ""}</label>
        <input type="text" id="emp_company_${idx}" name="employment[${idx}][company_name]" ${idx === 1 ? "required" : ""}>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="emp_from_${idx}">Date from</label>
          <input type="text" id="emp_from_${idx}" name="employment[${idx}][date_from]" placeholder="e.g. Dec 2021">
        </div>
        <div class="field-group">
          <label for="emp_to_${idx}">Date to</label>
          <input type="text" id="emp_to_${idx}" name="employment[${idx}][date_to]" placeholder="e.g. Oct 2023">
        </div>
      </div>
      <div class="field-group">
        <label for="emp_position_${idx}">Position</label>
        <input type="text" id="emp_position_${idx}" name="employment[${idx}][position]">
      </div>
      <div class="field-group">
        <label for="emp_reason_${idx}">Reason for leaving</label>
        <input type="text" id="emp_reason_${idx}" name="employment[${idx}][reason_for_leaving]">
      </div>
    `;
    employmentList.appendChild(wrap);
    const removeBtn = wrap.querySelector(".btn-remove");
    if (removeBtn) removeBtn.addEventListener("click", () => wrap.remove());
  }

  /* ---------------- Repeatable: Qualifications ---------------- */
  let qualificationCount = 0;
  const qualificationList = document.getElementById("qualifications-list");
  document.getElementById("add-qualification").addEventListener("click", () => addQualificationRow());

  function addQualificationRow() {
    qualificationCount++;
    const idx = qualificationCount;
    const wrap = document.createElement("div");
    wrap.className = "repeat-item";
    wrap.innerHTML = `
      <div class="repeat-item-header">
        <h3>Course / Qualification ${idx}</h3>
        ${idx > 1 ? '<button type="button" class="btn-remove">Remove</button>' : ""}
      </div>
      <div class="field-row">
        <div class="field-group">
          <label for="qual_title_${idx}">Course / training</label>
          <input type="text" id="qual_title_${idx}" name="qualifications[${idx}][course_title]">
        </div>
        <div class="field-group">
          <label for="qual_date_${idx}">Date</label>
          <input type="text" id="qual_date_${idx}" name="qualifications[${idx}][date_completed]" placeholder="e.g. Oct 2024">
        </div>
      </div>
      <div class="field-group">
        <label for="qual_body_${idx}">Awarding body / qualification</label>
        <input type="text" id="qual_body_${idx}" name="qualifications[${idx}][awarding_body]">
      </div>
    `;
    qualificationList.appendChild(wrap);
    const removeBtn = wrap.querySelector(".btn-remove");
    if (removeBtn) removeBtn.addEventListener("click", () => wrap.remove());
  }

  /* ---------------- Fixed: Mandatory Training ---------------- */
  function buildMandatoryTrainingRows() {
    const body = document.getElementById("mandatory-training-body");
    body.innerHTML = "";
    MANDATORY_TRAINING_COURSES.forEach((course) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${course.label}</td>
        <td><input type="date" name="training[${course.key}][date_completed]"></td>
        <td><input type="checkbox" name="training[${course.key}][needs_to_attend]" value="1"></td>
      `;
      body.appendChild(row);
    });
  }

  /* ---------------- Review step ---------------- */
  function renderReview() {
    const data = new FormData(form);
    const get = (name) => (data.get(name) || "").toString().trim();
    const yn = (name) => {
      const v = get(name);
      return v === "yes" ? "Yes" : v === "no" ? "No" : "\u2014";
    };

    const sections = [
      {
        title: "Position & Personal Details",
        stepIndex: 0,
        rows: [
          ["Position applied for", get("position_applied")],
          ["Name", `${get("first_name")} ${get("surname")}`],
          ["Date of birth", get("date_of_birth")],
          ["18+ confirmed", document.getElementById("age_confirmation").checked ? `Yes (${get("age_confirmation_initials")})` : "No"],
          ["Nationality", get("nationality")],
          ["Current address", get("current_address")],
          ["Contact telephone", get("telephone")],
          ["Email", get("email")],
          ["Emergency contact", `${get("emergency_contact_name")} — ${get("emergency_contact_phone")}`]
        ]
      },
      {
        title: "Current Employment",
        stepIndex: 1,
        rows: [
          ["Employer", get("current_employer_name") || "\u2014"],
          ["Start / left", `${get("current_employment_start") || "\u2014"} to ${get("current_employment_end") || "\u2014"}`]
        ]
      },
      {
        title: "Employment History",
        stepIndex: 2,
        rows: [["Records entered", String(employmentList.children.length)]]
      },
      {
        title: "Qualifications & Training",
        stepIndex: 3,
        rows: [["Courses entered", String(qualificationList.children.length)]]
      },
      {
        title: "Adjustments & Relationships",
        stepIndex: 4,
        rows: [
          ["Related to an employee?", yn("employee_relationship")],
          ["Reasonable adjustment notes", get("reasonable_adjustment") || "\u2014"]
        ]
      },
      {
        title: "References",
        stepIndex: 5,
        rows: [
          ["Reference 1", get("ref1_company_name") ? `${get("ref1_manager_name")} — ${get("ref1_company_name")}` : "\u2014"],
          ["Reference 2", get("ref2_company_name") ? `${get("ref2_manager_name")} — ${get("ref2_company_name")}` : "Not provided"]
        ]
      },
      {
        title: "DBS & Convictions",
        stepIndex: 6,
        rows: [
          ["Never cautioned / convicted", yn("criminal_conviction_status")],
          ["Current DBS check", yn("dbs_status")]
        ]
      },
      {
        title: "Right to Work & Languages",
        stepIndex: 7,
        rows: [
          ["UK work permit required", yn("work_permit_required")],
          ["Languages (fluent)", get("languages_fluent") || "\u2014"]
        ]
      },
      {
        title: "Documents",
        stepIndex: 8,
        rows: [
          ["CV attached", document.getElementById("cv").files.length ? "Yes" : "No"],
          ["Certificates attached", String(document.getElementById("certificates").files.length)]
        ]
      },
      {
        title: "Declaration",
        stepIndex: 9,
        rows: [
          ["Declaration accepted", document.getElementById("declaration_accepted").checked ? "Yes" : "No"],
          ["Signature", get("signature_name") || "\u2014"],
          ["Date", get("signature_date") || "\u2014"]
        ]
      }
    ];

    const container = document.getElementById("review-content");
    container.innerHTML = "";
    sections.forEach((section) => {
      const el = document.createElement("div");
      el.className = "review-section";
      const rowsHtml = section.rows
        .map(([label, value]) => `<div class="review-row"><dt>${label}</dt><dd>${escapeHtml(value)}</dd></div>`)
        .join("");
      el.innerHTML = `
        <h3>${section.title} <button type="button" class="review-edit-link" data-goto="${section.stepIndex}">Edit</button></h3>
        <dl style="margin:0;">${rowsHtml}</dl>
      `;
      container.appendChild(el);
    });

    container.querySelectorAll(".review-edit-link").forEach((btn) => {
      btn.addEventListener("click", () => showStep(parseInt(btn.dataset.goto, 10)));
    });
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  /**
   * Server validation errors come back as { field_name: "message" }.
   * Jump to the first step containing a bad field and highlight it,
   * so a 422 response is actually actionable instead of a dead-end banner.
   */
  function applyServerFieldErrors(errors) {
    let firstStepWithError = null;
    let firstField = null;

    Object.keys(errors).forEach((fieldName) => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (!field) return; // e.g. a radio-group name with no exact id match — still shown in the banner
      const step = field.closest(".form-step");
      const stepIndex = step ? parseInt(step.dataset.step, 10) - 1 : null;
      if (stepIndex !== null && (firstStepWithError === null || stepIndex < firstStepWithError)) {
        firstStepWithError = stepIndex;
        firstField = field;
      }
      showFieldError(field, errors[fieldName]);
    });

    if (firstStepWithError !== null) {
      showStep(firstStepWithError);
      if (firstField) firstField.focus();
    }
  }

  /* ---------------- Submit ---------------- */
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!validateStep(steps.length - 1)) return;

    btnSubmit.disabled = true;
    btnSubmit.textContent = "Submitting...";
    hideBanner();

    try {
      const formData = new FormData(form);
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
      formData.append("csrf_token", csrfToken);
      const response = await fetch("apply.php", {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      let result;
      try {
        result = await response.json();
      } catch (parseErr) {
        // Body wasn't valid JSON at all — a real server crash, not a
        // normal validation failure. See the PHP error log for the cause.
        throw new Error("SERVER_CRASH");
      }

      if (!result.success) {
        if (result.errors && typeof result.errors === "object") {
          applyServerFieldErrors(result.errors);
        }
        const debugSuffix = result.debug
          ? ` [DEBUG: ${result.debug.exception}: ${result.debug.message} in ${result.debug.file}:${result.debug.line}]`
          : "";
        throw new Error((result.message || "Submission failed") + debugSuffix);
      }

      document.getElementById("intro-card").style.display = "none";
      document.getElementById("progress-track").style.display = "none";
      progressLabel.style.display = "none";
      form.style.display = "none";
      document.getElementById("success-card").style.display = "block";
      document.getElementById("success-application-number").textContent = result.application_number;
      if (result.pdf_url) {
        document.getElementById("success-pdf-link").href = result.pdf_url;
      }
    } catch (err) {
      const message = err.message === "SERVER_CRASH"
        ? "Something went wrong on our end submitting your application. Please try again in a moment."
        : (err.message || "We could not submit your application. Please check your information and try again.");
      showBanner(message);
      btnSubmit.disabled = false;
      btnSubmit.textContent = "Submit Application";
    }
  });

  /* ---------------- Init ---------------- */
  buildProgressTrack();
  buildMandatoryTrainingRows();
  addEmploymentRow();
  addQualificationRow();
  showStep(0);
})();
