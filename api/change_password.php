<?php
/**
 * Change Password API Endpoint
 * Handles voluntary password changes for:
 * - Employees (updates `employees` table only)
 * - Administrators / Staff (updates `users` table only)
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$currentPassword = trim($data['current_password'] ?? '');
$newPassword     = trim($data['new_password'] ?? '');
$confirmPassword = trim($data['confirm_password'] ?? '');
$userId          = intval($_SESSION['user_id']);
$isEmployee      = (strtolower(trim($_SESSION['user_role'] ?? '')) === 'employee');

if (empty($currentPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter your current password.']);
    exit;
}

if (empty($newPassword) || strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match.']);
    exit;
}

if ($newPassword === $currentPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as your current password.']);
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT);

if ($isEmployee) {
    // 1. Employee: Fetch & verify against employees table
    if ($userId <= 0 && !empty($_SESSION['user_email'])) {
        $fStmt = sqlsrv_query($conn, "SELECT id FROM employees WHERE LOWER(email) = LOWER(?)", [$_SESSION['user_email']]);
        if ($fStmt && ($fRow = sqlsrv_fetch_array($fStmt, SQLSRV_FETCH_ASSOC))) {
            $userId = intval($fRow['id']);
            $_SESSION['user_id'] = $userId;
        }
        if ($fStmt) sqlsrv_free_stmt($fStmt);
    }

    $stmt = sqlsrv_query($conn, "SELECT id, password, password_hash, status FROM employees WHERE id = ?", [$userId]);
    if (!$stmt || !($emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee record not found.']);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    $isCurrentMatch = false;
    if (!empty($emp['password_hash']) && password_verify($currentPassword, $emp['password_hash'])) {
        $isCurrentMatch = true;
    } elseif (!empty($emp['password']) && $emp['password'] === $currentPassword) {
        $isCurrentMatch = true;
    }

    if (!$isCurrentMatch) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $upStmt = sqlsrv_query($conn, "UPDATE employees SET password = ?, password_hash = ?, is_first_login = 0, updated_at = GETDATE() WHERE id = ?", [$newPassword, $newHash, $userId]);
    if ($upStmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update employee password.']);
        exit;
    }
    sqlsrv_free_stmt($upStmt);
} else {
    // 2. Administrator / System User: Fetch & verify against users table
    $stmt = sqlsrv_query($conn, "SELECT id, password_hash FROM users WHERE id = ?", [$userId]);
    if (!$stmt || !($usr = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    if (!password_verify($currentPassword, $usr['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $upStmt = sqlsrv_query($conn, "UPDATE users SET password_hash = ?, is_first_login = 0, updated_at = GETDATE() WHERE id = ?", [$newHash, $userId]);
    if ($upStmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
        exit;
    }
    sqlsrv_free_stmt($upStmt);
}

$_SESSION['is_first_login'] = 0;

echo json_encode([
    'success' => true,
    'message' => 'Your password has been changed successfully!'
]);
exit;
