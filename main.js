/**
 * main.js - Client-Side Validation for Staff Registration
 * Provides real-time feedback and intercepts submission if input is invalid.
 * Uses Bootstrap 5 validation classes: .is-valid and .is-invalid
 */

document.addEventListener("DOMContentLoaded", () => {
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

    // Look for existing invalid-feedback div or create one
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
    // Standard email regex pattern
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
    // Re-check confirm password if user edits the main password
    if (confirmPasswordInput.value !== "") {
      validateConfirmPassword();
    }
  });
  confirmPasswordInput.addEventListener("input", validateConfirmPassword);

  // --- Form Submission Intercept ---

  form.addEventListener("submit", (event) => {
    // Run all validations
    const isNameValid = validateFullName();
    const isUserValid = validateUserName();
    const isEmailValid = validateEmail();
    const isPassValid = validatePassword();
    const isConfirmValid = validateConfirmPassword();

    // If ANY validation fails, stop form submission
    if (!isNameValid || !isUserValid || !isEmailValid || !isPassValid || !isConfirmValid) {
      event.preventDefault();
      event.stopPropagation();

      // Focus the first invalid field
      const firstInvalid = form.querySelector(".is-invalid");
      if (firstInvalid) {
        firstInvalid.focus();
      }
    }
  });
});

