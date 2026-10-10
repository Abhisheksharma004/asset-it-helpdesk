<?php
/**
 * Check Employee API Endpoint (Smart Login Check)
 * Location: api/check_employee.php
 * Method: POST
 * Accepts: JSON or Form POST (identifier/email/username)
 * Employees are validated strictly against `employees` table without inserting into `users`.
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $identifier = trim($jsonData['identifier'] ?? $jsonData['email'] ?? $jsonData['username'] ?? '');
} else {
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? $_POST['username'] ?? '');
}

if (empty($identifier)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your username or email address.'
    ]);
    exit;
}

// 1. Check users table (for Administrator & System Staff)
$userSql = "SELECT id, username, email, full_name, role, is_active 
            FROM users 
            WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)";
$userStmt = sqlsrv_query($conn, $userSql, [$identifier, $identifier]);
$systemUser = ($userStmt !== false) ? sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC) : null;
if ($userStmt) sqlsrv_free_stmt($userStmt);

if ($systemUser) {
    if ((int)$systemUser['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'exists'  => true,
            'message' => 'Your account is inactive. Please contact the administrator.'
        ]);
        exit;
    }

    // System users always authenticate with standard password
    http_response_code(200);
    echo json_encode([
        'success'              => true,
        'exists'               => true,
        'require_set_password' => false,
        'identifier'           => $systemUser['email'] ?: ($systemUser['username'] ?? $identifier),
        'email'                => $systemUser['email'],
        'username'             => $systemUser['username'] ?? '',
        'name'                 => $systemUser['full_name'] ?? 'User',
        'role'                 => $systemUser['role'] ?? 'Administrator',
        'message'              => 'Account verified.'
    ]);
    exit;
}

// 2. Check employees table (for Employees)
$empSql = "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.status, e.password_hash, e.is_first_login, d.department_name 
           FROM employees e 
           LEFT JOIN departments d ON e.department_id = d.id 
           WHERE LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)";
$empStmt = sqlsrv_query($conn, $empSql, [$identifier, $identifier]);
$emp = ($empStmt !== false) ? sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC) : null;
if ($empStmt) sqlsrv_free_stmt($empStmt);

if ($emp) {
    if (strtolower(trim($emp['status'] ?? '')) !== 'active') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'exists'  => true,
            'message' => 'Your employee account is inactive. Please contact the IT administrator.'
        ]);
        exit;
    }

    $empFullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
    $needsPasswordSetup = empty($emp['password_hash']) || ((int)($emp['is_first_login'] ?? 0) === 1);

    http_response_code(200);
    echo json_encode([
        'success'              => true,
        'exists'               => true,
        'require_set_password' => $needsPasswordSetup,
        'identifier'           => $emp['email'],
        'email'                => $emp['email'],
        'username'             => $emp['emp_code'],
        'name'                 => $empFullName ?: 'Employee',
        'role'                 => 'Employee',
        'message'              => $needsPasswordSetup 
            ? ('Welcome ' . ($empFullName ?: 'Employee') . '! Please set your password to activate your account.')
            : 'Account verified.'
    ]);
    exit;
}

// 3. Not found
http_response_code(404);
echo json_encode([
    'success' => false,
    'exists'  => false,
    'message' => 'No account found matching this username or email.'
]);
exit;
