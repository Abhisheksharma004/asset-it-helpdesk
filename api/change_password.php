<?php
/**
 * Change Password API Endpoint
 * Handles first-time login password setup & user password resets.
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// User must be authenticated
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

// Parse payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$currentPassword = trim($data['current_password'] ?? '');
$newPassword     = trim($data['new_password'] ?? '');
$confirmPassword = trim($data['confirm_password'] ?? '');
$userId          = intval($_SESSION['user_id']);

// Fetch user from DB
$uStmt = sqlsrv_query($conn, "SELECT id, username, email, password_hash, role, is_first_login FROM users WHERE id = ?", [$userId]);
if (!$uStmt || !($user = sqlsrv_fetch_array($uStmt, SQLSRV_FETCH_ASSOC))) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User profile not found.']);
    exit;
}
sqlsrv_free_stmt($uStmt);

// Validations
if (empty($currentPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter your current/default password.']);
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

// Verify current password
if (!password_verify($currentPassword, $user['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Current/default password is incorrect. Please check and try again.']);
    exit;
}

if ($newPassword === $currentPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as your current default password.']);
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

// 1. Update users table
$upUserSql = "UPDATE users SET password_hash = ?, is_first_login = 0, updated_at = GETDATE() WHERE id = ?";
$upUserStmt = sqlsrv_query($conn, $upUserSql, [$newHash, $userId]);
if ($upUserStmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update user password in database.', 'errors' => sqlsrv_errors()]);
    exit;
}

// 2. Update employees table if applicable
$userEmail = $user['email'] ?? ($_SESSION['user_email'] ?? '');
$userCode  = $user['username'] ?? ($_SESSION['user_username'] ?? '');

$upEmpSql = "UPDATE employees SET password = ?, password_hash = ?, is_first_login = 0, updated_at = GETDATE() WHERE LOWER(email) = LOWER(?) OR LOWER(emp_code) = LOWER(?)";
sqlsrv_query($conn, $upEmpSql, [$newPassword, $newHash, $userEmail, $userCode]);

// 3. Clear session flag
$_SESSION['is_first_login'] = 0;

echo json_encode([
    'success' => true,
    'message' => 'Your password has been changed successfully! Welcome to your IT Portal.'
]);
exit;
