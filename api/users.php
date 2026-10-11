<?php
/**
 * Users & IT Technicians Management API
 * Asset Management & IT Service Desk Portal
 *
 * Supports:
 * - GET: Fetch all active users / technicians
 * - POST: Register / Insert new IT Technician / Administrator into the users table
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check (allow preview parameter for staging/testing)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview']) && !isset($_POST['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Ensure phone column exists in users table
$colCheckSql = "IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'phone')
BEGIN
    ALTER TABLE users ADD phone NVARCHAR(50) NULL;
END";
sqlsrv_query($conn, $colCheckSql);

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: List all users / technicians
if ($method === 'GET') {
    $sql = "SELECT id, username, email, full_name, role, phone, department, is_active, last_login, created_at 
            FROM users 
            ORDER BY id ASC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to query users table.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $users = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['created_at'] instanceof DateTime) {
            $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
        }
        if ($row['last_login'] instanceof DateTime) {
            $row['last_login'] = $row['last_login']->format('Y-m-d H:i:s');
        }
        $users[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

// Handle POST: Create, Edit or Delete IT Technician / User
if ($method === 'POST') {
    $action = trim($_POST['action'] ?? 'create');

    // 1. DELETE Action
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID provided.']);
            exit;
        }

        // Protect primary admin account (ID 1 or active logged in session)
        if ($id === 1 || (!empty($_SESSION['user_id']) && $id === (int)$_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot delete active primary administrator account.']);
            exit;
        }

        $delStmt = sqlsrv_query($conn, "DELETE FROM users WHERE id = ?", [$id]);
        if ($delStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete technician from database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Technician deleted successfully.']);
        exit;
    }

    // 1.5. TOGGLE STATUS Action
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $status = ($status === 1) ? 1 : 0;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID provided.']);
            exit;
        }

        // Protect primary admin account (ID 1 or active logged in session) from deactivation
        if ($status === 0 && ($id === 1 || (!empty($_SESSION['user_id']) && $id === (int)$_SESSION['user_id']))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot deactivate the active primary administrator account.']);
            exit;
        }

        $stmt = sqlsrv_query($conn, "UPDATE users SET is_active = ?, updated_at = GETDATE() WHERE id = ?", [$status, $id]);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update account status in database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Account ' . ($status === 1 ? 'activated' : 'deactivated') . ' successfully.',
            'is_active' => $status,
            'id' => $id
        ]);
        exit;
    }

    // 2. EDIT Action
    if ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? $_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = trim($_POST['role'] ?? 'IT Support Desk');
        $phone    = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? $_POST['user_id'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? ((int)$_POST['is_active'] === 1 ? 1 : 0) : 1;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit;
        }

        // Prevent deactivating primary admin
        if ($isActive === 0 && ($id === 1 || (!empty($_SESSION['user_id']) && $id === (int)$_SESSION['user_id']))) {
            $isActive = 1; // force active
        }

        if ($fullName === '' || $email === '' || $username === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full Name, Email, and Username are required.']);
            exit;
        }

        // Check duplicate username for other users
        $chkUser = sqlsrv_query($conn, "SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ?", [$username, $id]);
        if ($chkUser && sqlsrv_has_rows($chkUser)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username "' . $username . '" is already taken by another user.']);
            exit;
        }

        // Check duplicate email for other users
        $chkEmail = sqlsrv_query($conn, "SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?", [$email, $id]);
        if ($chkEmail && sqlsrv_has_rows($chkEmail)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email "' . $email . '" is already taken by another user.']);
            exit;
        }

        if ($password !== '') {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET full_name = ?, email = ?, role = ?, phone = ?, username = ?, password_hash = ?, is_active = ?, updated_at = GETDATE() WHERE id = ?";
            $uParams = [$fullName, $email, $role, $phone, $username, $passHash, $isActive, $id];
        } else {
            $updateSql = "UPDATE users SET full_name = ?, email = ?, role = ?, phone = ?, username = ?, is_active = ?, updated_at = GETDATE() WHERE id = ?";
            $uParams = [$fullName, $email, $role, $phone, $username, $isActive, $id];
        }

        $stmt = sqlsrv_query($conn, $updateSql, $uParams);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update technician in database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Technician details updated successfully.',
            'user' => [
                'id' => $id,
                'username' => $username,
                'email' => $email,
                'full_name' => $fullName,
                'role' => $role,
                'phone' => $phone,
                'is_active' => $isActive
            ]
        ]);
        exit;
    }

    // 3. CREATE Action
    $fullName = trim($_POST['full_name'] ?? $_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? 'IT Support Desk');
    $phone    = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? $_POST['user_id'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // Validation
    if ($fullName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full Name is required.']);
        exit;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid Corporate Email is required.']);
        exit;
    }

    if ($username === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID / Username is required.']);
        exit;
    }

    if ($password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Login Password is required.']);
        exit;
    }

    // Check duplicate username
    $checkUserSql = "SELECT id FROM users WHERE LOWER(username) = LOWER(?)";
    $checkUserStmt = sqlsrv_query($conn, $checkUserSql, [$username]);
    if ($checkUserStmt && sqlsrv_has_rows($checkUserStmt)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'User ID / Username "' . $username . '" is already registered.']);
        exit;
    }
    if ($checkUserStmt) sqlsrv_free_stmt($checkUserStmt);

    // Check duplicate email
    $checkEmailSql = "SELECT id FROM users WHERE LOWER(email) = LOWER(?)";
    $checkEmailStmt = sqlsrv_query($conn, $checkEmailSql, [$email]);
    if ($checkEmailStmt && sqlsrv_has_rows($checkEmailStmt)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Corporate Email "' . $email . '" is already registered.']);
        exit;
    }
    if ($checkEmailStmt) sqlsrv_free_stmt($checkEmailStmt);

    // Hash password securely with Bcrypt
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $department = 'IT Support';

    $isActive = isset($_POST['is_active']) ? ((int)$_POST['is_active'] === 1 ? 1 : 0) : 1;

    // Insert into users table
    $insertSql = "INSERT INTO users (
        username, 
        email, 
        password_hash, 
        full_name, 
        role, 
        phone, 
        department, 
        is_active, 
        created_at, 
        updated_at, 
        is_first_login
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE(), 0)";

    $params = [$username, $email, $passwordHash, $fullName, $role, $phone, $department, $isActive];
    $stmt = sqlsrv_query($conn, $insertSql, $params);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database error while saving technician.', 
            'errors' => sqlsrv_errors()
        ]);
        exit;
    }

    // Retrieve inserted ID
    $newId = getLastInsertId($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Technician "' . $fullName . '" registered successfully in users table.',
        'user' => [
            'id' => $newId,
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'role' => $role,
            'phone' => $phone,
            'department' => $department,
            'is_active' => $isActive
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
