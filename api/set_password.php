<?php
/**
 * Set Initial Password API Endpoint
 * Asset Management & IT Service Desk Portal
 * 
 * Location: api/set_password.php
 * Method: POST
 * Accepts: JSON or Form POST (identifier, new_password, confirm_password)
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Only POST requests are supported.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Parse incoming request payload
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $identifier      = trim($jsonData['identifier'] ?? $jsonData['email'] ?? $jsonData['username'] ?? '');
    $newPassword     = (string)($jsonData['new_password'] ?? '');
    $confirmPassword = (string)($jsonData['confirm_password'] ?? '');
} else {
    $identifier      = trim($_POST['identifier'] ?? $_POST['email'] ?? $_POST['username'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
}

if (empty($identifier)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Employee email or code is required.'
    ]);
    exit;
}

if (empty($newPassword)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your new password.'
    ]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters long.'
    ]);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'New password and confirm password do not match.'
    ]);
    exit;
}

// 1. Check user record
$sql = "SELECT id, username, email, password_hash, full_name, role, department, is_active, is_first_login 
        FROM users 
        WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)";
$stmt = sqlsrv_query($conn, $sql, [$identifier, $identifier]);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
    exit;
}

$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

// 2. If not found in users, check employees table
if (!$user) {
    $empSql = "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.status, e.password_hash, e.is_first_login, d.department_name 
               FROM employees e 
               LEFT JOIN departments d ON e.department_id = d.id 
               WHERE LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)";
    $empStmt = sqlsrv_query($conn, $empSql, [$identifier, $identifier]);
    if ($empStmt && ($emp = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC))) {
        sqlsrv_free_stmt($empStmt);
        $fullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
        $isActive = (strtolower($emp['status'] ?? '') === 'active') ? 1 : 0;
        
        $insUserSql = "INSERT INTO users (username, email, password_hash, full_name, role, department, is_active, is_first_login, created_at, updated_at) 
                       VALUES (?, ?, NULL, ?, 'Employee', ?, ?, 1, GETDATE(), GETDATE())";
        $insStmt = sqlsrv_query($conn, $insUserSql, [
            $emp['emp_code'] ?: $emp['email'],
            $emp['email'],
            $fullName ?: 'Employee',
            $emp['department_name'] ?? 'General',
            $isActive
        ]);
        if ($insStmt) {
            sqlsrv_free_stmt($insStmt);
            $getStmt = sqlsrv_query($conn, "SELECT TOP 1 id, username, email, password_hash, full_name, role, department, is_active, is_first_login FROM users WHERE LOWER(email) = LOWER(?)", [$emp['email']]);
            if ($getStmt && ($newUser = sqlsrv_fetch_array($getStmt, SQLSRV_FETCH_ASSOC))) {
                $user = $newUser;
                sqlsrv_free_stmt($getStmt);
            }
        }
    }
}

if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No account found matching this email or username.'
    ]);
    exit;
}

if ((int)$user['is_active'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your account is currently inactive. Please contact your IT administrator.'
    ]);
    exit;
}

// Security: Prevent overriding an already-configured password for established accounts
$isAlreadySet = !empty($user['password_hash']) && ((int)($user['is_first_login'] ?? 0) === 0);
if ($isAlreadySet) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A password has already been set for this account. Please sign in or use Forgot Password.'
    ]);
    exit;
}

// Hash password securely
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update users table
$upUserSql = "UPDATE users SET password_hash = ?, is_first_login = 0, last_login = GETDATE(), updated_at = GETDATE() WHERE id = ?";
$upUserStmt = sqlsrv_query($conn, $upUserSql, [$hashedPassword, $user['id']]);

if ($upUserStmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save new password. Please try again.'
    ]);
    exit;
}
sqlsrv_free_stmt($upUserStmt);

// Keep employees table synchronized
$empEmail = $user['email'];
$empUsername = $user['username'] ?? '';
$upEmpSql = "UPDATE employees SET password = ?, password_hash = ?, is_first_login = 0, updated_at = GETDATE() WHERE LOWER(email) = LOWER(?) OR LOWER(emp_code) = LOWER(?)";
$upEmpStmt = sqlsrv_query($conn, $upEmpSql, [$newPassword, $hashedPassword, $empEmail, $empUsername]);
if ($upEmpStmt !== false) {
    sqlsrv_free_stmt($upEmpStmt);
}

// Automatically create user session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_regenerate_id(true);

$_SESSION['user_id']        = (int)$user['id'];
$_SESSION['user_name']      = $user['full_name'];
$_SESSION['user_username']  = $user['username'] ?? '';
$_SESSION['user_email']     = $user['email'];
$_SESSION['user_role']      = $user['role'];
$_SESSION['user_dept']      = $user['department'] ?? '';
$_SESSION['is_first_login'] = 0;
$_SESSION['logged_in']      = true;

$isEmployee = (strtolower(trim($user['role'] ?? '')) === 'employee');
$redirectUrl = $isEmployee ? 'employee_dashboard.php' : 'dashboard.php';

http_response_code(200);
echo json_encode([
    'success'  => true,
    'message'  => 'Password created successfully! Welcome to your IT Portal.',
    'redirect' => $redirectUrl
]);
exit;
