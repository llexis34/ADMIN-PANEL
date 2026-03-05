/* membership_page.js */

document.addEventListener("DOMContentLoaded", function () {
  // ✅ Guard page: must be logged in
  (async function guard() {
    const me = await apiMe();
    if (!me.ok) window.location.href = "signin.html";
  })();

  // Auto-fill email from login
  (async function fillEmail() {
    const me = await apiMe();
    const el = document.getElementById("autoEmail");
    if (el && me.ok && me.user?.email) el.value = me.user.email;
  })();

  // ✅ NEW: check login using PHP session (XAMPP)
  async function apiMe() {
    const res = await fetch("api/auth_me.php", { credentials: "include" });
    try { return await res.json(); } catch { return { ok: false }; }
  }

  // ✅ NEW: logout using PHP session
  async function apiLogout() {
    await fetch("api/auth_logout.php", { method: "POST", credentials: "include" });
  }

  const form = document.getElementById("membershipForm");
  const submitBtn = document.getElementById("submitBtn");
  const submitMsg = document.getElementById("submitMsg");

  const photoInput = document.getElementById("photoInput");
  const photoPreview = document.getElementById("photoPreview");

  const confirmDetails = document.getElementById("confirmDetails");
  const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

  const missingFieldsList = document.getElementById("missingFieldsList");

  // ✅ Prevent actual form submit (no refresh / no redirect)
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    });
  }

  // ✅ 2x2 photo preview (local only)
  if (photoInput && photoPreview) {
    photoInput.addEventListener("change", () => {
      const file = photoInput.files && photoInput.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      photoPreview.src = url;
      photoPreview.style.display = "block";
    });
  }

  // ✅ Logout with confirmation dialog
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", async () => {
      const sure = confirm("Are you sure you want to logout?");
      if (!sure) return;

      await apiLogout();
      window.location.href = "signin.html";
    });
  }

  // ✅ Helper: escape HTML
  function escapeHTML(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // ✅ Friendly labels for required fields (used in missing list + summary)
  const labelMap = {
    application_type: "Application Type",
    last_name: "Last Name",
    first_name: "First Name",
    home_address: "Home Address",
    gender: "Gender",
    religion: "Religion",
    birthdate: "Birthdate",
    age: "Age",
    dependents: "Dependents",
    civil_status: "Civil Status",
    education: "Educational Attainment",
    livelihood: "Hanapbuhay",
    gross_monthly_income: "Gross Monthly Income",
    mobile: "Mobile No.",
    email: "Email Address",
    tin: "TIN",
    work_address: "Work Address",
    signature: "Signature (Typed Name)",
    signature_date: "Signature Date",
    photoInput: "2x2 Photo"
  };

  // ✅ Validate required fields before opening confirm modal
  function validateRequiredFields() {
    const missing = [];

    if (!form) return missing;

    // 1) required inputs/selects/textarea
    const requiredEls = form.querySelectorAll("[required]");

    // Track radio groups so we don't add duplicates
    const radioHandled = new Set();

    requiredEls.forEach((el) => {
      const tag = (el.tagName || "").toLowerCase();
      const type = (el.getAttribute("type") || "").toLowerCase();
      const name = el.getAttribute("name") || "";

      // radio group required
      if (type === "radio" && name) {
        if (radioHandled.has(name)) return;
        radioHandled.add(name);

        const checked = form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]:checked`);
        if (!checked) {
          missing.push(labelMap[name] || name);
        }
        return;
      }

      // file required
      if (type === "file") {
        const hasFile = el.files && el.files.length > 0;
        if (!hasFile) missing.push(labelMap[el.id] || labelMap[name] || name || "File Upload");
        return;
      }

      // checkbox required (if you ever set required)
      if (type === "checkbox") {
        if (!el.checked) missing.push(labelMap[name] || name);
        return;
      }

      // normal input/select/textarea required
      const val = (el.value || "").trim();
      if (!val) {
        missing.push(labelMap[name] || name || tag);
      }
    });

    // 2) specifically require the photoInput (because it has id, not name)
    if (photoInput && photoInput.hasAttribute("required")) {
      const hasFile = photoInput.files && photoInput.files.length > 0;
      if (!hasFile) missing.push(labelMap.photoInput);
    }

    // remove duplicates
    return Array.from(new Set(missing));
  }

  // ✅ Collect form data for confirmation summary
  function collectFormData() {
    const rows = [];
    if (!form) return rows;

    const controls = form.querySelectorAll("input[name], textarea[name], select[name]");
    const handled = new Set();

    controls.forEach((el) => {
      const name = el.getAttribute("name");
      if (!name || handled.has(name)) return;

      const type = (el.getAttribute("type") || "").toLowerCase();

      if (type === "radio") {
        handled.add(name);
        const checked = form.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]:checked`);
        rows.push({
          label: labelMap[name] || name,
          value: checked ? checked.value : "(not selected)"
        });
        return;
      }

      if (type === "checkbox") {
        handled.add(name);
        const all = form.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(name)}"]`);
        if (all.length > 1) {
          const selected = Array.from(all).filter(x => x.checked).map(x => x.value || "Checked");
          rows.push({
            label: labelMap[name] || name,
            value: selected.length ? selected.join(", ") : "(none)"
          });
        } else {
          rows.push({
            label: labelMap[name] || name,
            value: el.checked ? "Yes" : "No"
          });
        }
        return;
      }

      if (type === "file") {
        handled.add(name);
        const file = el.files && el.files[0];
        rows.push({
          label: labelMap[name] || name,
          value: file ? file.name : "(no file selected)"
        });
        return;
      }

      handled.add(name);
      const value = (el.value || "").trim();
      rows.push({
        label: labelMap[name] || name,
        value: value ? value : "(empty)"
      });
    });

    // photoInput is not named in HTML; include it manually
    if (photoInput) {
      const file = photoInput.files && photoInput.files[0];
      rows.push({
        label: "2x2 Photo",
        value: file ? file.name : "(no photo selected)"
      });
    }

    return rows;
  }

  function renderSummary() {
    const rows = collectFormData();

    const html = `
      <div class="table-responsive">
        <table class="table table-sm table-bordered" style="background:#fff;">
          <thead>
            <tr style="background:#f3f4f6;">
              <th style="width:40%; font-weight:900;">Field</th>
              <th style="font-weight:900;">Value</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(r => `
              <tr>
                <td style="font-weight:800;">${escapeHTML(r.label)}</td>
                <td>${escapeHTML(r.value)}</td>
              </tr>
            `).join("")}
          </tbody>
        </table>
      </div>
    `;

    if (confirmDetails) confirmDetails.innerHTML = html;
  }

  // ✅ SUBMIT click: validate first
  if (submitBtn) {
    submitBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const missing = validateRequiredFields();

      // If missing → show incomplete modal (and DO NOT open confirm modal)
      if (missing.length) {
        if (missingFieldsList) {
          missingFieldsList.innerHTML = missing.map(m => `<li style="font-weight:800;">${escapeHTML(m)}</li>`).join("");
        }

        // Hide confirm modal if somehow triggered
        if (window.jQuery) {
          window.jQuery("#confirmSubmitModal").modal("hide");
          window.jQuery("#incompleteModal").modal("show");
        }
        return false;
      }

      // If complete → populate summary then open confirm modal
      renderSummary();
      if (window.jQuery) {
        window.jQuery("#confirmSubmitModal").modal("show");
      }

      return false;
    });
  }

  // ✅ Confirm submit (demo)
  if (confirmSubmitBtn) {
    confirmSubmitBtn.addEventListener("click", async function (e) {
      e.preventDefault();
      e.stopPropagation();

      // ✅ NEW: submit to backend (PHP + MySQL)
      try {
        const fd = new FormData(form);

        // photoInput may not have name="" so force append a field name PHP expects
        if (photoInput && photoInput.files && photoInput.files[0]) {
          fd.append("photo", photoInput.files[0]);
        }

        const res = await fetch("api/membership_submit.php", {
          method: "POST",
          body: fd,
          credentials: "include"
        });

        const data = await res.json();

        if (!data.ok) {
          alert(data.error || "Submit failed.");
          return false;
        }
      } catch (err) {
        alert("Server error. Please try again.");
        return false;
      }

      if (submitMsg) {
        submitMsg.style.display = "block";
        submitMsg.scrollIntoView({ behavior: "smooth", block: "center" });
        setTimeout(() => (submitMsg.style.display = "none"), 1800);
      }

      if (window.jQuery) {
        window.jQuery("#confirmSubmitModal").modal("hide");
      }

      return false;
    });
  }
});