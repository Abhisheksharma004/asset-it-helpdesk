<?php
/**
 * Set Initial Password API Endpoint (Employees Only)
 * Asset Management & IT Service Desk Portal
 * 
 * Location: api/set_password.php
 * Method: POST
 * Accepts: JSON or Form POST (identifier, new_password, confirm_password)
 * Updates strictly the `employees` table. Never touches `users` table.
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

// Lookup employee in employees table
$empSql = "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.status, e.password_hash, e.is_first_login, d.department_name 
           FROM employees e 
           LEFT JOIN departments d ON e.department_id = d.id 
           WHERE LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)";
$empStmt = sqlsrv_query($conn, $empSql, [$identifier, $identifier]);

if ($empStmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
    exit;
}

$emp = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($empStmt);

if (!$emp) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No employee account found matching this email or code.'
    ]);
    exit;
}

if (strtolower(trim($emp['status'] ?? '')) !== 'active') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your employee account is currently inactive. Please contact your IT administrator.'
    ]);
    exit;
}

// Security: Prevent overriding an already-configured password
$isAlreadySet = !empty($emp['password_hash']) && ((int)($emp['is_first_login'] ?? 0) === 0);
if ($isAlreadySet) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A password has already been set for this account. Please sign in with your password.'
    ]);
    exit;
}

// Hash password securely
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update employees table ONLY
$upEmpSql = "UPDATE employees SET password = ?, password_hash = ?, is_first_login = 0, updated_at = GETDATE() WHERE id = ?";
$upEmpStmt = sqlsrv_query($conn, $upEmpSql, [$newPassword, $hashedPassword, $emp['id']]);

if ($upEmpStmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save new password. Please try again.'
    ]);
    exit;
}
sqlsrv_free_stmt($upEmpStmt);

$empFullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));

// Automatically establish employee session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_regenerate_id(true);

$_SESSION['user_id']        = (int)$emp['id'];
$_SESSION['user_name']      = $empFullName ?: 'Employee';
$_SESSION['user_username']  = $emp['emp_code'] ?? '';
$_SESSION['user_email']     = $emp['email'];
$_SESSION['user_role']      = 'Employee';
$_SESSION['user_dept']      = $emp['department_name'] ?? 'General';
$_SESSION['is_first_login'] = 0;
$_SESSION['logged_in']      = true;

http_response_code(200);
echo json_encode([
    'success'  => true,
    'message'  => 'Password created successfully! Welcome to your IT Portal.',
    'redirect' => 'employee_dashboard.php'
]);
exit;
