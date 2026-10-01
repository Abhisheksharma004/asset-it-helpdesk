<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
<link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/toast.css">
</head>

<body>

<div class="login-container">

    <div class="login-card">

        <div class="logo">
            <img src="assets/images/logo.png" alt="" style="width: 100%;">
        </div>

        <h1 class="title">Welcome Back</h1>
        <p class="subtitle">Asset Management & IT Service Desk Portal</p>

        <div class="error-message" id="errorMessage"></div>
        <div class="success-message" id="successMessage"></div>

        <form id="loginForm">

            <div class="form-group">
                <label for="email">Username / Email Address</label>

                <div class="input-wrapper">
                    <input
                        type="text"
                        id="email"
                        placeholder="Enter your username or email"
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                    >

                    <span
                        class="toggle-password"
                        id="togglePassword"
                    >
                        Show
                    </span>
                </div>
            </div>

            <div class="form-options">

                <label class="remember">
                    <input type="checkbox" id="remember">
                    Remember me
                </label>

                <a href="#" class="forgot-password">
                    Forgot Password?
                </a>

            </div>

            <button type="submit" class="login-btn">
                Sign In
            </button>

        </form>

        <div class="login-footer">
            Design and Maintain by <a href="https://virosentrepreneurs.com" target="_blank">Viros</a>
        </div>

    </div>

</div>

<script src="js/toast.js"></script>
<script src="js/script.js"></script>

</body>
</html>