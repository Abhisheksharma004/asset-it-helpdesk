<?php
/**
 * Check Employee API Endpoint (Smart Login Check)
 * Location: api/check_employee.php
 * Method: POST
 * Accepts: JSON or Form POST (identifier/email/username)
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

// 1. Search in users table
$sql = "SELECT id, username, email, password_hash, full_name, role, department, is_active, is_first_login 
        FROM users 
        WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)";
$stmt = sqlsrv_query($conn, $sql, [$identifier, $identifier]);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    exit;
}

$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

// 2. If not in users, check employees table
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
        'exists'  => false,
        'message' => 'No account found matching this username or email.'
    ]);
    exit;
}

if ((int)$user['is_active'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'exists'  => true,
        'message' => 'Your account is inactive. Please contact the IT administrator.'
    ]);
    exit;
}

$needsPasswordSetup = empty($user['password_hash']) || ((int)($user['is_first_login'] ?? 0) === 1);

http_response_code(200);
echo json_encode([
    'success'              => true,
    'exists'               => true,
    'require_set_password' => $needsPasswordSetup,
    'identifier'           => $user['email'] ?: ($user['username'] ?? $identifier),
    'email'                => $user['email'],
    'username'             => $user['username'] ?? '',
    'name'                 => $user['full_name'] ?? 'Employee',
    'role'                 => $user['role'] ?? 'Employee',
    'message'              => $needsPasswordSetup 
        ? ('Welcome ' . ($user['full_name'] ?? 'Employee') . '! Please set your password to activate your account.')
        : 'Account verified.'
]);
exit;
