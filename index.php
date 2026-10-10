<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['logged_in'])) {
    if (strtolower($_SESSION['user_role'] ?? '') === 'employee') {
        header("Location: employee_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Asset Management & IT Service Desk</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/toast.css?v=<?= time() ?>">
</head>

<body>

<div class="login-container">

    <div class="login-card">

        <div class="logo">
            <img src="assets/images/logo.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
        </div>

        <h1 class="title" id="pageTitle">Welcome Back</h1>
        <p class="subtitle" id="pageSubtitle">Asset Management & IT Service Desk Portal</p>

        <!-- Dynamic Notice for First-Time Setup -->
        <div id="firstTimeNotice" class="first-time-badge" style="display: none;">
            <div class="badge-title">First-Time Login Setup</div>
            <div class="badge-desc">Welcome, <strong id="detectedEmpName">Employee</strong>! Please create your password to activate your account.</div>
        </div>

        <form id="loginForm" autocomplete="off">

            <!-- Identifier (Email / Username) -->
            <div class="form-group" id="identifierGroup">
                <label for="email" id="emailLabel">Username / Email Address</label>

                <div class="input-wrapper">
                    <input
                        type="text"
                        id="email"
                        placeholder="Enter your username or email"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <!-- Standard Login: Existing Password -->
            <div class="form-group" id="standardPasswordGroup">
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

            <!-- First-Time Login: Set New Password Fields (Hidden by default) -->
            <div id="firstTimeFields" style="display: none;">
                <div class="form-group">
                    <label for="newPassword">Create New Password</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="newPassword"
                            placeholder="Minimum 6 characters"
                            autocomplete="new-password"
                        >
                        <span class="toggle-password" id="toggleNewPassword">Show</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="confirmPassword"
                            placeholder="Re-enter your password"
                            autocomplete="new-password"
                        >
                        <span class="toggle-password" id="toggleConfirmPassword">Show</span>
                    </div>
                </div>

                <div class="first-time-helper">
                    <span>• Minimum 6 characters required</span>
                </div>
            </div>

            <!-- Standard Options (Remember me & Forgot Password) -->
            <div class="form-options" id="standardOptionsGroup">

                <label class="remember">
                    <input type="checkbox" id="remember">
                    Remember me
                </label>

                <a href="#" class="forgot-password">
                    Forgot Password?
                </a>

            </div>

            <!-- Back to standard login link when in setup mode -->
            <div id="cancelSetupGroup" style="display: none; margin-bottom: 14px; text-align: right;">
                <a href="javascript:void(0)" id="cancelSetupBtn" class="change-account-link">
                    ← Use different account
                </a>
            </div>

            <button type="submit" class="login-btn" id="signInBtn">
                Sign In
            </button>
        </form>

        <div class="login-footer">
            Design and Maintain by <a href="https://virosentrepreneurs.com" target="_blank">Viros</a>
        </div>

    </div>

</div>

<script src="js/toast.js?v=<?= time() ?>"></script>
<script src="js/script.js?v=<?= time() ?>"></script>

</body>
</html>