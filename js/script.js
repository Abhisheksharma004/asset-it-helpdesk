// Login Page Script - Seamless Smart Login & Account Activation
document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("loginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const newPasswordInput = document.getElementById("newPassword");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const rememberCheckbox = document.getElementById("remember");
    const signInBtn = document.getElementById("signInBtn");

    const pageTitle = document.getElementById("pageTitle");
    const pageSubtitle = document.getElementById("pageSubtitle");
    const firstTimeNotice = document.getElementById("firstTimeNotice");
    const detectedEmpName = document.getElementById("detectedEmpName");

    const standardPasswordGroup = document.getElementById("standardPasswordGroup");
    const standardOptionsGroup = document.getElementById("standardOptionsGroup");
    const firstTimeFields = document.getElementById("firstTimeFields");
    const cancelSetupGroup = document.getElementById("cancelSetupGroup");
    const cancelSetupBtn = document.getElementById("cancelSetupBtn");

    let isFirstTimeMode = false;

    // Password Visibility Toggles
    function setupToggle(toggleId, targetInput) {
        const toggleBtn = document.getElementById(toggleId);
        if (toggleBtn && targetInput) {
            toggleBtn.addEventListener("click", function () {
                if (targetInput.type === "password") {
                    targetInput.type = "text";
                    toggleBtn.textContent = "Hide";
                } else {
                    targetInput.type = "password";
                    toggleBtn.textContent = "Show";
                }
            });
        }
    }

    setupToggle("togglePassword", passwordInput);
    setupToggle("toggleNewPassword", newPasswordInput);
    setupToggle("toggleConfirmPassword", confirmPasswordInput);

    // Switch to First-Time Set Password Mode
    function switchToFirstTimeMode(name, identifier) {
        isFirstTimeMode = true;
        if (pageTitle) pageTitle.textContent = "Account Setup";
        if (pageSubtitle) pageSubtitle.textContent = "Create your password to activate account";
        if (firstTimeNotice) firstTimeNotice.style.display = "block";
        if (detectedEmpName) detectedEmpName.textContent = name || "Employee";

        if (emailInput) {
            emailInput.readOnly = true;
            emailInput.style.backgroundColor = "#f8fafc";
        }

        if (standardPasswordGroup) standardPasswordGroup.style.display = "none";
        if (standardOptionsGroup) standardOptionsGroup.style.display = "none";
        if (firstTimeFields) firstTimeFields.style.display = "block";
        if (cancelSetupGroup) cancelSetupGroup.style.display = "block";

        if (signInBtn) {
            signInBtn.textContent = "Set Password & Sign In";
            signInBtn.disabled = false;
        }

        setTimeout(() => {
            if (newPasswordInput) newPasswordInput.focus();
        }, 100);
    }

    // Reset to Standard Login Mode
    function resetToStandardMode() {
        isFirstTimeMode = false;
        if (pageTitle) pageTitle.textContent = "Welcome Back";
        if (pageSubtitle) pageSubtitle.textContent = "Asset Management & IT Service Desk Portal";
        if (firstTimeNotice) firstTimeNotice.style.display = "none";

        if (emailInput) {
            emailInput.readOnly = false;
            emailInput.style.backgroundColor = "";
            emailInput.focus();
        }

        if (standardPasswordGroup) standardPasswordGroup.style.display = "block";
        if (standardOptionsGroup) standardOptionsGroup.style.display = "flex";
        if (firstTimeFields) firstTimeFields.style.display = "none";
        if (cancelSetupGroup) cancelSetupGroup.style.display = "none";

        if (signInBtn) {
            signInBtn.textContent = "Sign In";
            signInBtn.disabled = false;
        }

        if (passwordInput) passwordInput.value = "";
        if (newPasswordInput) newPasswordInput.value = "";
        if (confirmPasswordInput) confirmPasswordInput.value = "";
    }

    if (cancelSetupBtn) {
        cancelSetupBtn.addEventListener("click", resetToStandardMode);
    }

    // Optional: Smart auto-check when user finishes typing email and hits Tab or moves away
    if (emailInput) {
        emailInput.addEventListener("change", function () {
            const val = this.value.trim();
            if (val && !isFirstTimeMode && (!passwordInput || !passwordInput.value.trim())) {
                checkEmailStatus(val, false);
            }
        });
    }

    // Helper: Check Email Status via API
    function checkEmailStatus(identifier, showAlert = true) {
        return fetch("api/check_employee.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ identifier: identifier })
        })
        .then(res => res.json())
        .then(data => {
            if (data.exists && data.require_set_password) {
                switchToFirstTimeMode(data.name, data.identifier);
                if (showAlert && typeof showToast === "function") {
                    showToast(data.message || "Account found! Please set your new password.", "info");
                }
                return true;
            }
            return false;
        })
        .catch(() => false);
    }

    // Form Submission Handler
    loginForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const emailVal = emailInput ? emailInput.value.trim() : "";
        if (!emailVal) {
            showToast("Please enter your username or email.", "error");
            if (emailInput) emailInput.focus();
            return;
        }

        // --- FIRST-TIME MODE: Setting New Password ---
        if (isFirstTimeMode) {
            const newPass = newPasswordInput ? newPasswordInput.value.trim() : "";
            const confirmPass = confirmPasswordInput ? confirmPasswordInput.value.trim() : "";

            if (!newPass) {
                showToast("Please enter your new password.", "error");
                if (newPasswordInput) newPasswordInput.focus();
                return;
            }

            if (newPass.length < 6) {
                showToast("Password must be at least 6 characters long.", "error");
                if (newPasswordInput) newPasswordInput.focus();
                return;
            }

            if (newPass !== confirmPass) {
                showToast("New password and confirm password do not match.", "error");
                if (confirmPasswordInput) confirmPasswordInput.focus();
                return;
            }

            signInBtn.disabled = true;
            signInBtn.textContent = "Setting Password...";

            fetch("api/set_password.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    identifier: emailVal,
                    new_password: newPass,
                    confirm_password: confirmPass
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || "Password set successfully! Logging in...", "success");
                    setTimeout(() => {
                        window.location.href = data.redirect || "employee_dashboard.php";
                    }, 700);
                } else {
                    showToast(data.message || "Failed to set password. Please try again.", "error");
                    signInBtn.disabled = false;
                    signInBtn.textContent = "Set Password & Sign In";
                }
            })
            .catch(() => {
                showToast("Server connection error. Please try again.", "error");
                signInBtn.disabled = false;
                signInBtn.textContent = "Set Password & Sign In";
            });

            return;
        }

        // --- STANDARD LOGIN MODE ---
        const passVal = passwordInput ? passwordInput.value.trim() : "";

        // If password is left blank, perform Smart Login Check to see if this employee needs password setup
        if (passVal === "") {
            signInBtn.disabled = true;
            signInBtn.textContent = "Checking Account...";

            fetch("api/check_employee.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ identifier: emailVal })
            })
            .then(res => res.json())
            .then(data => {
                signInBtn.disabled = false;
                signInBtn.textContent = "Sign In";

                if (!data.exists) {
                    showToast(data.message || "No account found matching this username or email.", "error");
                    return;
                }

                if (data.require_set_password) {
                    switchToFirstTimeMode(data.name, data.identifier);
                    showToast(data.message || "Account found! Please set your new password.", "info");
                } else {
                    showToast("Please enter your password.", "warning");
                    if (passwordInput) passwordInput.focus();
                }
            })
            .catch(() => {
                signInBtn.disabled = false;
                signInBtn.textContent = "Sign In";
                showToast("Server error. Please try again.", "error");
            });

            return;
        }

        // If password is typed, submit standard login
        signInBtn.disabled = true;
        signInBtn.textContent = "Signing In...";

        fetch("api/login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                email: emailVal,
                password: passVal,
                remember: rememberCheckbox ? rememberCheckbox.checked : false
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.require_set_password) {
                // If user entered wrong/dummy password but account requires first-time setup
                switchToFirstTimeMode(data.name, data.identifier);
                showToast(data.message || "Please set your new password.", "info");
                return;
            }

            if (data.success) {
                showToast(data.message || "Login successful! Redirecting...", "success");
                setTimeout(() => {
                    window.location.href = data.redirect || "dashboard.php";
                }, 700);
            } else {
                showToast(data.message || "Invalid credentials. Please try again.", "error");
                signInBtn.disabled = false;
                signInBtn.textContent = "Sign In";
            }
        })
        .catch(() => {
            showToast("Server connection error. Please try again.", "error");
            signInBtn.disabled = false;
            signInBtn.textContent = "Sign In";
        });
    });
});
