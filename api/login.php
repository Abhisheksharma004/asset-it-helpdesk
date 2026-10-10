<?php
/**
 * Login API Endpoint
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
    $password   = $jsonData['password'] ?? '';
    $remember   = !empty($jsonData['remember']);
} else {
    $identifier = trim($_POST['email'] ?? $_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember']);
}

// Validate input presence
if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter both username/email and password.'
    ]);
    exit;
}

// Prepare query to search by email or username
$sql = "SELECT id, username, email, password_hash, full_name, role, department, is_active, is_first_login, last_login 
        FROM users 
        WHERE email = ? OR username = ?";
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

// Check if user exists
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email/username or password.'
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

// Verify password hash
if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email/username or password.'
    ]);
    exit;
}

// Check if first-time login
$isFirstLogin = (isset($user['is_first_login']) && (int)$user['is_first_login'] === 1) || empty($user['last_login']);

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
$_SESSION['is_first_login'] = $isFirstLogin ? 1 : 0;
$_SESSION['logged_in']      = true;

// Update last login timestamp in SQL Server
$updateSql = "UPDATE users SET last_login = GETDATE() WHERE id = ?";
$updateStmt = sqlsrv_query($conn, $updateSql, [$user['id']]);
if ($updateStmt !== false) {
    sqlsrv_free_stmt($updateStmt);
}

// Redirect based on role
$isEmployee = (strtolower(trim($user['role'] ?? '')) === 'employee');
$redirectUrl = $isEmployee 
    ? ('employee_dashboard.php' . ($isFirstLogin ? '?first_login=1' : '')) 
    : 'dashboard.php';

// Return successful response
http_response_code(200);
echo json_encode([
    'success'        => true,
    'message'        => 'Login successful! Redirecting...',
    'redirect'       => $redirectUrl,
    'is_first_login' => $isFirstLogin,
    'user'           => [
        'id'             => (int)$user['id'],
        'username'       => $user['username'] ?? '',
        'name'           => $user['full_name'],
        'email'          => $user['email'],
        'role'           => $user['role'],
        'department'     => $user['department'],
        'is_first_login' => $isFirstLogin
    ]
]);
exit;
