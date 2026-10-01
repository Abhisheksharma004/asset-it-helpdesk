

const loginForm = document.getElementById("loginForm");
const email = document.getElementById("email");
const password = document.getElementById("password");

const togglePassword = document.getElementById("togglePassword");



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



    const emailValue = email.value.trim();
    const passwordValue = password.value.trim();

    // Empty field validation
    if (emailValue === "" || passwordValue === "") {
        showToast("Please enter both email and password.", "error");
        return;
    }

    // If identifier contains @, validate email format
    if (emailValue.includes("@")) {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(emailValue)) {
            showToast("Please enter a valid email address.", "error");
            return;
        }
    }

    // Password minimum length check
    if (passwordValue.length < 4) {
        showToast("Password must contain at least 4 characters.", "error");
        return;
    }

    const submitBtn = loginForm.querySelector("button[type='submit']");
    const originalBtnText = submitBtn ? submitBtn.textContent : "Sign In";
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Signing in...";
    }

    const remember = document.getElementById("remember");

    // Call Login API
    fetch("api/login.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            email: emailValue,
            password: passwordValue,
            remember: remember ? remember.checked : false
        })
    })
    .then(async (response) => {
        const data = await response.json();
        return { status: response.status, data: data };
    })
    .then((result) => {
        if (result.data && result.data.success) {
            showToast(result.data.message || "Login successful! Redirecting...", "success");
            setTimeout(function () {
                window.location.href = result.data.redirect || "dashboard.php";
            }, 800);
        } else {
            const errorMsg = (result.data && result.data.message) 
                ? result.data.message 
                : "Invalid credentials. Please try again.";

            showToast(errorMsg, "error");



            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        }
    })
    .catch((error) => {
        console.error("Login API Error:", error);
        showToast("Unable to connect to login server. Please try again.", "error");

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    });

});
