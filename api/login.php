<?php
/**
 * Login API Endpoint - With Smart Login Check
 * Asset Management & IT Service Desk Portal
 * 
 * Location: api/login.php
 * Method: POST
 * Accepts: JSON or Form POST (email/username, password, remember)
 */

header('Content-Type: application/json; charset=UTF-8');

// Ensure only POST method is accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Only POST requests are supported.'
    ]);
    exit;
}

// Include database configuration
require_once __DIR__ . '/../config/db.php';

// Parse incoming request payload (support both JSON body and standard Form POST)
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $identifier = trim($jsonData['email'] ?? $jsonData['username'] ?? '');
    $password   = (string)($jsonData['password'] ?? '');
    $remember   = !empty($jsonData['remember']);
} else {
    $identifier = trim($_POST['email'] ?? $_POST['username'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $remember   = !empty($_POST['remember']);
}

// Validate identifier presence
if (empty($identifier)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your username or email address.'
    ]);
    exit;
}

// 1. Prepare query to search users table by email or username
$sql = "SELECT id, username, email, password_hash, full_name, role, department, is_active, is_first_login, last_login 
        FROM users 
        WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)";
$params = [$identifier, $identifier];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
    exit;
}

$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

// 2. If user not in `users` table, check `employees` directory
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
        
        // Auto-provision user account with Employee role
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
            $getStmt = sqlsrv_query($conn, "SELECT TOP 1 id, username, email, password_hash, full_name, role, department, is_active, is_first_login, last_login FROM users WHERE LOWER(email) = LOWER(?)", [$emp['email']]);
            if ($getStmt && ($newUser = sqlsrv_fetch_array($getStmt, SQLSRV_FETCH_ASSOC))) {
                $user = $newUser;
                sqlsrv_free_stmt($getStmt);
            }
        }
    }
}

// Check if user exists
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No account found matching this username or email.'
    ]);
    exit;
}

// Check if account is active
if ((int)$user['is_active'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your account is inactive. Please contact the IT administrator.'
    ]);
    exit;
}

// --- SMART LOGIN CHECK ---
// If user has no password set or has first-time login flag, trigger password setup
$needsPasswordSetup = empty($user['password_hash']) || ((int)($user['is_first_login'] ?? 0) === 1);

if ($needsPasswordSetup) {
    http_response_code(200);
    echo json_encode([
        'success'              => false,
        'require_set_password' => true,
        'identifier'           => $user['email'] ?: ($user['username'] ?? $identifier),
        'email'                => $user['email'],
        'username'             => $user['username'] ?? '',
        'name'                 => $user['full_name'] ?? 'Employee',
        'message'              => 'Welcome ' . ($user['full_name'] ?? 'Employee') . '! Please set your password to activate your account.'
    ]);
    exit;
}

// If password hash exists, user must supply valid password
if ($password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your password.'
    ]);
    exit;
}

// Verify password hash
if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email/username or password.'
    ]);
    exit;
}

// Handle Session Management
if (session_status() === PHP_SESSION_NONE) {
    if ($remember) {
        // Set cookie lifetime to 30 days if 'remember me' is checked
        $lifetime = 30 * 24 * 60 * 60;
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

// Prevent session fixation
session_regenerate_id(true);

// Store user session data
$_SESSION['user_id']        = (int)$user['id'];
$_SESSION['user_name']      = $user['full_name'];
$_SESSION['user_username']  = $user['username'] ?? '';
$_SESSION['user_email']     = $user['email'];
$_SESSION['user_role']      = $user['role'];
$_SESSION['user_dept']      = $user['department'] ?? '';
$_SESSION['is_first_login'] = 0;
$_SESSION['logged_in']      = true;

// Update last login timestamp in SQL Server
$updateSql = "UPDATE users SET last_login = GETDATE() WHERE id = ?";
$updateStmt = sqlsrv_query($conn, $updateSql, [$user['id']]);
if ($updateStmt !== false) {
    sqlsrv_free_stmt($updateStmt);
}

// Redirect based on role
$isEmployee = (strtolower(trim($user['role'] ?? '')) === 'employee');
$redirectUrl = $isEmployee ? 'employee_dashboard.php' : 'dashboard.php';

// Return successful response
http_response_code(200);
echo json_encode([
    'success'        => true,
    'message'        => 'Login successful! Redirecting...',
    'redirect'       => $redirectUrl,
    'is_first_login' => false,
    'user'           => [
        'id'             => (int)$user['id'],
        'username'       => $user['username'] ?? '',
        'name'           => $user['full_name'],
        'email'          => $user['email'],
        'role'           => $user['role'],
        'department'     => $user['department'],
        'is_first_login' => false
    ]
]);
exit;
