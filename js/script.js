

const loginForm = document.getElementById("loginForm");
const email = document.getElementById("email");
const password = document.getElementById("password");

const togglePassword = document.getElementById("togglePassword");

const errorMessage = document.getElementById("errorMessage");
const successMessage = document.getElementById("successMessage");

// Show / Hide Password
togglePassword.addEventListener("click", function () {

    if (password.type === "password") {
        password.type = "text";
        togglePassword.textContent = "Hide";
    } else {
        password.type = "password";
        togglePassword.textContent = "Show";
    }

});


// Login Form
loginForm.addEventListener("submit", function (event) {

    event.preventDefault();

    if (errorMessage) errorMessage.style.display = "none";
    if (successMessage) successMessage.style.display = "none";

    const emailValue = email.value.trim();
    const passwordValue = password.value.trim();

    // Empty field validation
    if (emailValue === "" || passwordValue === "") {
        showToast("Please enter both email and password.", "error");
        return;
    }

    // Email validation
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(emailValue)) {
        showToast("Please enter a valid email address.", "error");
        return;
    }

    // Password validation
    if (passwordValue.length < 6) {
        showToast("Password must contain at least 6 characters.", "error");
        return;
    }

    // Demo login & redirect
    showToast("Login successful! Redirecting to dashboard...", "success");

    console.log("Email:", emailValue);
    console.log("Password:", passwordValue);

    setTimeout(function () {
        window.location.href = "dashboard.php";
    }, 800);

});
