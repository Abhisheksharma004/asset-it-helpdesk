<?php
/**
 * Login API Endpoint
 * Handles authentication separately:
 * - Administrators / System Users authenticate via `users` table
 * - Employees authenticate via `employees` table (never inserted into users)
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
    $identifier = trim($jsonData['email'] ?? $jsonData['username'] ?? '');
    $password   = (string)($jsonData['password'] ?? '');
    $remember   = !empty($jsonData['remember']);
} else {
    $identifier = trim($_POST['email'] ?? $_POST['username'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $remember   = !empty($_POST['remember']);
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
$adminSql = "SELECT id, username, email, password_hash, full_name, role, department, is_active 
             FROM users 
             WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)";
$adminStmt = sqlsrv_query($conn, $adminSql, [$identifier, $identifier]);
$adminUser = ($adminStmt !== false) ? sqlsrv_fetch_array($adminStmt, SQLSRV_FETCH_ASSOC) : null;
if ($adminStmt) sqlsrv_free_stmt($adminStmt);

if ($adminUser) {
    if ((int)$adminUser['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your account is inactive. Please contact the system administrator.'
        ]);
        exit;
    }

    if ($password === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter your password.'
        ]);
        exit;
    }

    if (!password_verify($password, $adminUser['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email/username or password.'
        ]);
        exit;
    }

    // Session setup for Administrator
    if (session_status() === PHP_SESSION_NONE) {
        if ($remember) {
            session_set_cookie_params([
                'lifetime' => 30 * 24 * 60 * 60,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
    }
    session_regenerate_id(true);

    $_SESSION['user_id']        = (int)$adminUser['id'];
    $_SESSION['user_name']      = $adminUser['full_name'];
    $_SESSION['user_username']  = $adminUser['username'] ?? '';
    $_SESSION['user_email']     = $adminUser['email'];
    $_SESSION['user_role']      = $adminUser['role'];
    $_SESSION['user_dept']      = $adminUser['department'] ?? '';
    $_SESSION['is_first_login'] = 0;
    $_SESSION['logged_in']      = true;

    sqlsrv_query($conn, "UPDATE users SET last_login = GETDATE() WHERE id = ?", [$adminUser['id']]);

    echo json_encode([
        'success'  => true,
        'message'  => 'Login successful! Redirecting...',
        'redirect' => 'dashboard.php',
        'user'     => [
            'id'    => (int)$adminUser['id'],
            'name'  => $adminUser['full_name'],
            'email' => $adminUser['email'],
            'role'  => $adminUser['role']
        ]
    ]);
    exit;
}

// 2. Check employees table (for Employees) - Standalone without users table!
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
            'message' => 'Your employee account is inactive. Please contact the IT administrator.'
        ]);
        exit;
    }

    $empFullName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));

    // Smart First Time Login Check for Employee
    $needsPasswordSetup = empty($emp['password_hash']) || ((int)($emp['is_first_login'] ?? 0) === 1);

    if ($needsPasswordSetup) {
        http_response_code(200);
        echo json_encode([
            'success'              => false,
            'require_set_password' => true,
            'identifier'           => $emp['email'],
            'email'                => $emp['email'],
            'username'             => $emp['emp_code'],
            'name'                 => $empFullName ?: 'Employee',
            'role'                 => 'Employee',
            'message'              => 'Welcome ' . ($empFullName ?: 'Employee') . '! Please set your password to activate your account.'
        ]);
        exit;
    }

    if ($password === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter your password.'
        ]);
        exit;
    }

    if (!password_verify($password, $emp['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email/username or password.'
        ]);
        exit;
    }

    // Session setup for Employee
    if (session_status() === PHP_SESSION_NONE) {
        if ($remember) {
            session_set_cookie_params([
                'lifetime' => 30 * 24 * 60 * 60,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
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

    sqlsrv_query($conn, "UPDATE employees SET updated_at = GETDATE() WHERE id = ?", [$emp['id']]);

    echo json_encode([
        'success'  => true,
        'message'  => 'Login successful! Redirecting...',
        'redirect' => 'employee_dashboard.php',
        'user'     => [
            'id'    => (int)$emp['id'],
            'name'  => $empFullName ?: 'Employee',
            'email' => $emp['email'],
            'role'  => 'Employee'
        ]
    ]);
    exit;
}

// 3. Not found in users and not found in employees
http_response_code(401);
echo json_encode([
    'success' => false,
    'message' => 'No account found matching this username or email.'
]);
exit;
