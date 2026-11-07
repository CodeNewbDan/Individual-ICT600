/**
 * main.js - Client-Side Scripts for Staff Portal
 * 1. Dual-layer validation for Staff Registration (register.php)
 * 2. Real-time Live Search for Dashboard Projects Table (dashboard.php)
 */

document.addEventListener("DOMContentLoaded", () => {
  initRegistrationValidation();
  initLiveSearch();
});

// ==========================================
// 1. REGISTRATION FORM VALIDATION (register.php)
// ==========================================
function initRegistrationValidation() {
  const form = document.getElementById("registerForm");

  // Only run if the registration form exists on the current page
  if (!form) return;

  const fullNameInput = document.getElementById("fullName");
  const userNameInput = document.getElementById("userName");
  const emailInput = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirmPassword");

  // --- Helper Functions for Bootstrap UI Feedback ---

  function setInvalid(input, message) {
    input.classList.remove("is-valid");
    input.classList.add("is-invalid");

    let feedback = input.parentElement.querySelector(".invalid-feedback");
    if (!feedback) {
      feedback = document.createElement("div");
      feedback.className = "invalid-feedback";
      input.parentElement.appendChild(feedback);
    }
    feedback.textContent = message;
    return false;
  }

  function setValid(input) {
    input.classList.remove("is-invalid");
    input.classList.add("is-valid");

    const feedback = input.parentElement.querySelector(".invalid-feedback");
    if (feedback) {
      feedback.textContent = "";
    }
    return true;
  }

  // --- Field-Specific Validation Functions (Mirrors PHP Rules) ---

  function validateFullName() {
    const val = fullNameInput.value.trim();
    if (val === "") {
      return setInvalid(fullNameInput, "Full Name is required.");
    }
    if (val.length < 2 || val.length > 100) {
      return setInvalid(fullNameInput, "Full Name must be between 2 and 100 characters.");
    }
    return setValid(fullNameInput);
  }

  function validateUserName() {
    const val = userNameInput.value.trim();
    const regex = /^[a-zA-Z0-9_]{3,30}$/;
    if (val === "") {
      return setInvalid(userNameInput, "Username is required.");
    }
    if (!regex.test(val)) {
      return setInvalid(
        userNameInput,
        "Username must be 3-30 characters (letters, numbers, and underscores only)."
      );
    }
    return setValid(userNameInput);
  }

  function validateEmail() {
    const val = emailInput.value.trim();
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (val === "") {
      return setInvalid(emailInput, "Email address is required.");
    }
    if (!regex.test(val)) {
      return setInvalid(emailInput, "Please enter a valid email address (e.g. user@example.com).");
    }
    return setValid(emailInput);
  }

  function validatePassword() {
    const val = passwordInput.value;
    const hasUpper = /[A-Z]/.test(val);
    const hasNumber = /[0-9]/.test(val);

    if (val === "") {
      return setInvalid(passwordInput, "Password is required.");
    }
    if (val.length < 8) {
      return setInvalid(passwordInput, "Password must be at least 8 characters long.");
    }
    if (!hasUpper || !hasNumber) {
      return setInvalid(passwordInput, "Password must contain at least one uppercase letter and one number.");
    }
    return setValid(passwordInput);
  }

  function validateConfirmPassword() {
    const pass = passwordInput.value;
    const confirm = confirmPasswordInput.value;

    if (confirm === "") {
      return setInvalid(confirmPasswordInput, "Please confirm your password.");
    }
    if (confirm !== pass) {
      return setInvalid(confirmPasswordInput, "Passwords do not match.");
    }
    return setValid(confirmPasswordInput);
  }

  // --- Real-Time Validation on User Input / Blur ---

  fullNameInput.addEventListener("input", validateFullName);
  userNameInput.addEventListener("input", validateUserName);
  emailInput.addEventListener("input", validateEmail);
  passwordInput.addEventListener("input", () => {
    validatePassword();
    if (confirmPasswordInput.value !== "") {
      validateConfirmPassword();
    }
  });
  confirmPasswordInput.addEventListener("input", validateConfirmPassword);

  // --- Form Submission Intercept ---

  form.addEventListener("submit", (event) => {
    const isNameValid = validateFullName();
    const isUserValid = validateUserName();
    const isEmailValid = validateEmail();
    const isPassValid = validatePassword();
    const isConfirmValid = validateConfirmPassword();

    if (!isNameValid || !isUserValid || !isEmailValid || !isPassValid || !isConfirmValid) {
      event.preventDefault();
      event.stopPropagation();

      const firstInvalid = form.querySelector(".is-invalid");
      if (firstInvalid) {
        firstInvalid.focus();
      }
    }
  });
}

// ==========================================
// 2. LIVE TABLE SEARCH (dashboard.php)
// ==========================================
function initLiveSearch() {
  const searchInput = document.getElementById("projectSearch");
  const table = document.getElementById("projectTable");

  // Only run if the search bar and project table exist on the current page
  if (!searchInput || !table) return;

  const tbody = table.querySelector("tbody");
  if (!tbody) return;

  // Dynamically create a "No matching records" placeholder row
  let noMatchRow = document.getElementById("noSearchMatchRow");
  if (!noMatchRow) {
    noMatchRow = document.createElement("tr");
    noMatchRow.id = "noSearchMatchRow";
    noMatchRow.style.display = "none";
    noMatchRow.innerHTML = `
      <td colspan="8" class="text-center py-4 text-muted">
        <i class="bi bi-search fs-3 d-block mb-1 text-muted"></i>
        <span class="fw-semibold">No projects match your search keyword.</span>
      </td>
    `;
    tbody.appendChild(noMatchRow);
  }

  searchInput.addEventListener("input", function () {
    const query = this.value.toLowerCase().trim();
    const rows = tbody.querySelectorAll("tr");
    let matchCount = 0;

    rows.forEach((row) => {
      // Skip the "no match" row itself or empty-state row with <= 1 cell
      if (row.id === "noSearchMatchRow" || row.cells.length <= 1) return;

      const rowText = row.textContent.toLowerCase();

      // Show row if it contains the search keyword, otherwise hide
      if (query === "" || rowText.includes(query)) {
        row.style.display = "";
        matchCount++;
      } else {
        row.style.display = "none";
      }
    });

    // If a search query is typed and no rows matched, show the "No projects match" message!
    if (query !== "" && matchCount === 0) {
      noMatchRow.style.display = "";
    } else {
      noMatchRow.style.display = "none";
    }
  });
}
