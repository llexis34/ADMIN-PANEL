document.addEventListener("DOMContentLoaded", function () {
  restoreRememberedEmail();
  handleSigninForm();
  handleMembershipForm();
  setupPasswordToggle("password", "togglePass");
  setupPasswordToggle("confirmPassword", "toggleConfirmPass");
  setupSuccessModal();
  setupErrorModal();
  headerScrollFX();
});
function restoreRememberedEmail() {
  const emailEl = document.getElementById("email");
  const rememberEl = document.getElementById("rememberMe");

  if (!emailEl || !rememberEl) return;

  const savedEmail = localStorage.getItem("limcoma_saved_email");
  if (savedEmail) {
    emailEl.value = savedEmail;
    rememberEl.checked = true;
  }
}

// FIX: replaced hardcoded credential check with real PHP API call
async function handleSigninForm() {
  const signinForm = document.getElementById("signinForm");
  if (!signinForm) return;

  signinForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    const emailEl = document.getElementById("email");
    const passEl = document.getElementById("password");
    const rememberEl = document.getElementById("rememberMe");
    const okBox = document.getElementById("loginOk");
    const errBox = document.getElementById("loginErr");

    if (!emailEl || !passEl) return;

    const email = emailEl.value.trim();
    const pass = passEl.value;

    if (okBox) okBox.style.display = "none";
    if (errBox) errBox.style.display = "none";

    try {
      const res = await fetch("api/auth_login.php", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: email, password: pass })
      });

      const data = await res.json();

      if (!data.ok) {
        if (errBox) errBox.style.display = "block";
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }

      // Keep sessionStorage in sync for JS-side guard in membership_page.js
      sessionStorage.setItem("limcoma_logged_in", "true");
      sessionStorage.setItem("limcoma_user_email", email);

      if (rememberEl && rememberEl.checked) {
        localStorage.setItem("limcoma_saved_email", email);
      } else {
        localStorage.removeItem("limcoma_saved_email");
      }

      if (okBox) okBox.style.display = "block";

      setTimeout(function () {
        window.location.href = "membership_page.html";
      }, 650);

    } catch (err) {
      if (errBox) errBox.style.display = "block";
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  });
}

// FIX: replaced success-modal-only handler with real PHP register API call
async function handleMembershipForm() {
  const membershipForm = document.getElementById("membershipForm");
  if (!membershipForm) return;

  membershipForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const statusMsg = document.getElementById("statusMsg");
    const errorMsg = document.getElementById("errorMsg");

    if (statusMsg) statusMsg.style.display = "none";
    if (errorMsg) errorMsg.style.display = "none";

    if (!passwordInput || !confirmPasswordInput) return;

    if (passwordInput.value !== confirmPasswordInput.value) {
      if (errorMsg) errorMsg.style.display = "block";
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    try {
      const fd = new FormData(this);
      const res = await fetch("api/auth_register.php", {
        method: "POST",
        credentials: "include",
        body: fd
      });

      const data = await res.json();

      if (!data.ok) {
        showErrorModal(data.error || "Registration failed. Please try again.");
        return;
      }
      if (statusMsg) statusMsg.style.display = "block";
      showSuccessModal();
      membershipForm.reset();

    } catch (err) {
      showErrorModal("Server error. Please try again.");
    }
  });
}

function setupPasswordToggle(inputId, buttonId) {
  const input = document.getElementById(inputId);
  const button = document.getElementById(buttonId);

  if (!input || !button) return;

  button.addEventListener("click", function () {
    const isHidden = input.type === "password";
    input.type = isHidden ? "text" : "password";
    button.innerHTML = isHidden
      ? '<i class="fas fa-eye-slash"></i>'
      : '<i class="fas fa-eye"></i>';
  });
}

function headerScrollFX() {
  const topbar = document.querySelector(".topbar");
  if (!topbar) return;

  function onScroll() {
    if (window.scrollY > 8) {
      topbar.classList.add("is-scrolled");
    } else {
      topbar.classList.remove("is-scrolled");
    }
  }

  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
}

function setupSuccessModal() {
  const modal = document.getElementById("successModal");
  if (!modal) return;

  const backdrop = modal.querySelector(".success-backdrop");
  const stayHereBtn = document.getElementById("stayHereBtn");
  const goSigninBtn = document.getElementById("goSigninBtn");

  if (backdrop) {
    backdrop.addEventListener("click", hideSuccessModal);
  }

  if (stayHereBtn) {
    stayHereBtn.addEventListener("click", hideSuccessModal);
  }

  if (goSigninBtn) {
    goSigninBtn.addEventListener("click", function () {
      window.location.href = "signin.html";
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      hideSuccessModal();
    }
  });
}

function setupErrorModal() {
  const modal = document.getElementById("errorModal");
  if (!modal) return;

  const backdrop = modal.querySelector(".success-backdrop");
  const closeBtn = document.getElementById("closeErrorModalBtn");

  if (backdrop) {
    backdrop.addEventListener("click", hideErrorModal);
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", hideErrorModal);
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      hideErrorModal();
    }
  });
}

function showErrorModal(message) {
  const modal = document.getElementById("errorModal");
  const msg = document.getElementById("errorModalMessage");

  if (!modal || !msg) return;

  msg.textContent = message || "Something went wrong. Please try again.";
  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
}

function hideErrorModal() {
  const modal = document.getElementById("errorModal");
  if (!modal) return;

  modal.classList.remove("show");
  modal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");
}

function showSuccessModal() {
  const modal = document.getElementById("successModal");
  if (!modal) return;

  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
}

function hideSuccessModal() {
  const modal = document.getElementById("successModal");
  const statusMsg = document.getElementById("statusMsg");

  if (!modal) return;

  modal.classList.remove("show");
  modal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");

  if (statusMsg) {
    statusMsg.style.display = "none";
  }
}
