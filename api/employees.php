<?php
/**
 * Employee Master API
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch all employees (search, department filter, location filter, status filter, meta)
 * - POST:
 *     - action = 'create': Add employee
 *     - action = 'edit': Update employee
 *     - action = 'delete': Delete employee
 *     - action = 'toggle_status': Toggle Active / Inactive
 *     - action = 'import': Batch CSV/JSON import with duplicate handling
 *     - action = 'get_next_code': Return next recommended emp_code
 */

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Auto-create table if not exists (Self-healing schema)
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='employees' AND xtype='U')
BEGIN
    CREATE TABLE employees (
        id INT IDENTITY(1,1) PRIMARY KEY,
        emp_code NVARCHAR(50) NOT NULL UNIQUE,
        first_name NVARCHAR(80) NOT NULL,
        last_name NVARCHAR(80) NULL,
        email NVARCHAR(120) NOT NULL UNIQUE,
        phone NVARCHAR(30) NULL,
        department_id INT NULL,
        location_id INT NULL,
        designation NVARCHAR(100) NULL,
        joining_date DATE NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    IF EXISTS (SELECT * FROM sysobjects WHERE name='departments' AND xtype='U')
    BEGIN
        ALTER TABLE employees ADD CONSTRAINT FK_employees_departments FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
    END

    IF EXISTS (SELECT * FROM sysobjects WHERE name='locations' AND xtype='U')
    BEGIN
        ALTER TABLE employees ADD CONSTRAINT FK_employees_locations FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL;
    END
END";
sqlsrv_query($conn, $tableSetupSql);

// Ensure password and password_hash columns exist in employees table
$passColSql = "IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('employees') AND name = 'password')
BEGIN
    ALTER TABLE employees ADD password NVARCHAR(255) NULL;
END
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('employees') AND name = 'password_hash')
BEGIN
    ALTER TABLE employees ADD password_hash NVARCHAR(255) NULL;
END";
sqlsrv_query($conn, $passColSql);

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Helper to compute next employee code
 */
function getNextEmployeeCode($connection) {
    $sql = "SELECT emp_code FROM employees WHERE emp_code LIKE 'EMP-%'";
    $stmt = sqlsrv_query($connection, $sql);
    $maxNum = 1000;
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $code = $row['emp_code'] ?? '';
            if (preg_match('/EMP-(\d+)/i', $code, $matches)) {
                $num = intval($matches[1]);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        sqlsrv_free_stmt($stmt);
    }
    return 'EMP-' . ($maxNum + 1);
}

// -------------------------------------------------------------
// GET REQUEST: Fetch Employees, Filters, and Lookup Meta
// -------------------------------------------------------------
if ($method === 'GET') {
    $action = trim($_GET['action'] ?? '');

    // Quick action: Next Code
    if ($action === 'get_next_code') {
        echo json_encode([
            'success' => true,
            'next_code' => getNextEmployeeCode($conn)
        ]);
        exit;
    }

    $search     = trim($_GET['search'] ?? '');
    $status     = trim($_GET['status'] ?? '');
    $department = trim($_GET['department_id'] ?? '');
    $location   = trim($_GET['location_id'] ?? '');

    $sql = "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.phone, 
                   e.department_id, e.location_id, e.designation,
                   CONVERT(VARCHAR(10), e.joining_date, 120) AS joining_date_raw,
                   CONVERT(VARCHAR(10), e.joining_date, 105) AS joining_date,
                   e.status,
                   e.password,
                   d.department_name,
                   l.location_name,
                   CONVERT(VARCHAR(10), e.created_at, 105) AS created_at,
                   CONVERT(VARCHAR(10), e.updated_at, 105) AS updated_at
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (e.emp_code LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.phone LIKE ? OR e.designation LIKE ? OR d.department_name LIKE ? OR l.location_name LIKE ?)";
        $wild = '%' . $search . '%';
        for ($i = 0; $i < 8; $i++) {
            $params[] = $wild;
        }
    }

    if ($status !== '' && $status !== 'All') {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }

    if ($department !== '' && $department !== 'All') {
        $sql .= " AND e.department_id = ?";
        $params[] = intval($department);
    }

    if ($location !== '' && $location !== 'All') {
        $sql .= " AND e.location_id = ?";
        $params[] = intval($location);
    }

    $sql .= " ORDER BY e.id DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query failed.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $employees = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (empty($row['password'])) {
            $row['password'] = trim($row['first_name'] ?? '') . '@' . trim($row['emp_code'] ?? '');
        }
        $employees[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    // Compute Overall Stats
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'departments_count' => 0
    ];

    $statSql = "SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) AS inactive,
        COUNT(DISTINCT department_id) AS depts
        FROM employees";
    $statStmt = sqlsrv_query($conn, $statSql);
    if ($statStmt !== false) {
        $sRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC);
        if ($sRow) {
            $stats['total'] = (int)($sRow['total'] ?? 0);
            $stats['active'] = (int)($sRow['active'] ?? 0);
            $stats['inactive'] = (int)($sRow['inactive'] ?? 0);
            $stats['departments_count'] = (int)($sRow['depts'] ?? 0);
        }
        sqlsrv_free_stmt($statStmt);
    }

    // Lookup Lists for UI dropdowns
    $departmentsList = [];
    $dStmt = sqlsrv_query($conn, "SELECT id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    if ($dStmt !== false) {
        while ($dRow = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC)) {
            $departmentsList[] = $dRow;
        }
        sqlsrv_free_stmt($dStmt);
    }

    $locationsList = [];
    $lStmt = sqlsrv_query($conn, "SELECT id, location_name FROM locations WHERE status = 'Active' ORDER BY location_name ASC");
    if ($lStmt !== false) {
        while ($lRow = sqlsrv_fetch_array($lStmt, SQLSRV_FETCH_ASSOC)) {
            $locationsList[] = $lRow;
        }
        sqlsrv_free_stmt($lStmt);
    }

    echo json_encode([
        'success'           => true,
        'data'              => $employees,
        'stats'             => $stats,
        'count'             => count($employees),
        'departments'       => $departmentsList,
        'locations'         => $locationsList,
        'next_emp_code'     => getNextEmployeeCode($conn)
    ]);
    exit;
}

// -------------------------------------------------------------
// POST REQUEST: Create, Edit, Delete, Toggle Status, Batch Import
// -------------------------------------------------------------
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    $action = trim($data['action'] ?? 'create');

    // 1. ACTION: CREATE EMPLOYEE
    if ($action === 'create') {
        $emp_code       = trim($data['emp_code'] ?? '');
        $first_name     = trim($data['first_name'] ?? '');
        $last_name      = trim($data['last_name'] ?? '');
        $email          = strtolower(trim($data['email'] ?? ''));
        $phone          = trim($data['phone'] ?? '');
        $department_id  = !empty($data['department_id']) ? intval($data['department_id']) : null;
        $location_id    = !empty($data['location_id']) ? intval($data['location_id']) : null;
        $designation    = trim($data['designation'] ?? '');
        $joining_date   = !empty($data['joining_date']) ? trim($data['joining_date']) : null;
        $status         = trim($data['status'] ?? 'Active');
        $rawPassword    = trim($data['password'] ?? '');

        // Validation
        if ($first_name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'First Name is required.']);
            exit;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid corporate email address is required.']);
            exit;
        }

        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Auto-assign emp_code if empty
        if ($emp_code === '') {
            $emp_code = getNextEmployeeCode($conn);
        }

        // Check duplicate emp_code
        $checkCodeSql = "SELECT id FROM employees WHERE LOWER(LTRIM(RTRIM(emp_code))) = LOWER(?)";
        $checkCodeStmt = sqlsrv_query($conn, $checkCodeSql, [$emp_code]);
        if ($checkCodeStmt && sqlsrv_fetch_array($checkCodeStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Employee Code '{$emp_code}' is already registered."]);
            exit;
        }

        // Check duplicate email
        $checkEmailSql = "SELECT id FROM employees WHERE LOWER(LTRIM(RTRIM(email))) = LOWER(?)";
        $checkEmailStmt = sqlsrv_query($conn, $checkEmailSql, [$email]);
        if ($checkEmailStmt && sqlsrv_fetch_array($checkEmailStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Email '{$email}' is already in use by another employee."]);
            exit;
        }

        // Compute default password if empty: "First Name@Employee Code" e.g. "Abhishek@VE015"
        if ($rawPassword === '') {
            $rawPassword = $first_name . '@' . $emp_code;
        }
        $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);

        // Insert employee (with is_first_login = 1)
        $insertSql = "INSERT INTO employees (emp_code, first_name, last_name, email, phone, department_id, location_id, designation, joining_date, status, password, password_hash, is_first_login, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, GETDATE(), GETDATE())";
        $insertParams = [
            $emp_code, 
            $first_name, 
            ($last_name !== '' ? $last_name : null), 
            $email, 
            ($phone !== '' ? $phone : null), 
            $department_id, 
            $location_id, 
            ($designation !== '' ? $designation : null), 
            $joining_date, 
            $status,
            $rawPassword,
            $passwordHash
        ];

        $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
        if ($insertStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save employee.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = getLastInsertId($conn);

        // Fetch department name for users table
        $deptName = 'General';
        if ($department_id) {
            $dStmt = sqlsrv_query($conn, "SELECT department_name FROM departments WHERE id = ?", [$department_id]);
            if ($dStmt && ($dRow = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC))) {
                $deptName = $dRow['department_name'] ?? 'General';
            }
        }

        // Sync with users table so employee can log in immediately (with is_first_login = 1)
        $uChk = sqlsrv_query($conn, "SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)", [$email, $emp_code]);
        if ($uChk && $uRow = sqlsrv_fetch_array($uChk, SQLSRV_FETCH_ASSOC)) {
            sqlsrv_query($conn, "UPDATE users SET username = ?, password_hash = ?, full_name = ?, role = 'Employee', department = ?, is_active = ?, is_first_login = 1, updated_at = GETDATE() WHERE id = ?", [
                $emp_code,
                $passwordHash,
                trim($first_name . ' ' . $last_name),
                $deptName,
                ($status === 'Active' ? 1 : 0),
                $uRow['id']
            ]);
        } else {
            sqlsrv_query($conn, "INSERT INTO users (username, email, password_hash, full_name, role, department, is_active, is_first_login, created_at, updated_at) VALUES (?, ?, ?, ?, 'Employee', ?, ?, 1, GETDATE(), GETDATE())", [
                $emp_code,
                $email,
                $passwordHash,
                trim($first_name . ' ' . $last_name),
                $deptName,
                ($status === 'Active' ? 1 : 0)
            ]);
        }

        echo json_encode([
            'success'          => true,
            'message'          => "Employee '{$first_name} " . ($last_name ? $last_name . " " : "") . "({$emp_code})' added successfully. Default Password: {$rawPassword}",
            'employee_id'      => $newId,
            'default_password' => $rawPassword
        ]);
        exit;
    }

    // 2. ACTION: EDIT / UPDATE EMPLOYEE
    if ($action === 'edit' || $action === 'update') {
        $id             = intval($data['id'] ?? 0);
        $emp_code       = trim($data['emp_code'] ?? '');
        $first_name     = trim($data['first_name'] ?? '');
        $last_name      = trim($data['last_name'] ?? '');
        $email          = strtolower(trim($data['email'] ?? ''));
        $phone          = trim($data['phone'] ?? '');
        $department_id  = !empty($data['department_id']) ? intval($data['department_id']) : null;
        $location_id    = !empty($data['location_id']) ? intval($data['location_id']) : null;
        $designation    = trim($data['designation'] ?? '');
        $joining_date   = !empty($data['joining_date']) ? trim($data['joining_date']) : null;
        $status         = trim($data['status'] ?? 'Active');
        $rawPassword    = trim($data['password'] ?? '');

        if ($id <= 0 || $first_name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Employee ID and First Name are required.']);
            exit;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid corporate email address is required.']);
            exit;
        }

        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Fetch old employee data to update users table accurately
        $oldStmt = sqlsrv_query($conn, "SELECT emp_code, email FROM employees WHERE id = ?", [$id]);
        $oldRow = $oldStmt ? sqlsrv_fetch_array($oldStmt, SQLSRV_FETCH_ASSOC) : null;
        $oldEmail = $oldRow['email'] ?? '';
        $oldCode  = $oldRow['emp_code'] ?? '';

        // Check duplicate emp_code for other employees
        $checkCodeSql = "SELECT id FROM employees WHERE LOWER(LTRIM(RTRIM(emp_code))) = LOWER(?) AND id <> ?";
        $checkCodeStmt = sqlsrv_query($conn, $checkCodeSql, [$emp_code, $id]);
        if ($checkCodeStmt && sqlsrv_fetch_array($checkCodeStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Employee Code '{$emp_code}' belongs to another employee."]);
            exit;
        }

        // Check duplicate email for other employees
        $checkEmailSql = "SELECT id FROM employees WHERE LOWER(LTRIM(RTRIM(email))) = LOWER(?) AND id <> ?";
        $checkEmailStmt = sqlsrv_query($conn, $checkEmailSql, [$email, $id]);
        if ($checkEmailStmt && sqlsrv_fetch_array($checkEmailStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Email '{$email}' is already in use by another employee."]);
            exit;
        }

        if ($rawPassword !== '') {
            $passHash = password_hash($rawPassword, PASSWORD_DEFAULT);
            $updateSql = "UPDATE employees 
                          SET emp_code = ?, first_name = ?, last_name = ?, email = ?, phone = ?, 
                              department_id = ?, location_id = ?, designation = ?, joining_date = ?, 
                              status = ?, password = ?, password_hash = ?, updated_at = GETDATE()
                          WHERE id = ?";
            $updateParams = [
                $emp_code, $first_name, ($last_name !== '' ? $last_name : null), $email, ($phone !== '' ? $phone : null),
                $department_id, $location_id, ($designation !== '' ? $designation : null), $joining_date,
                $status, $rawPassword, $passHash, $id
            ];
        } else {
            $updateSql = "UPDATE employees 
                          SET emp_code = ?, first_name = ?, last_name = ?, email = ?, phone = ?, 
                              department_id = ?, location_id = ?, designation = ?, joining_date = ?, 
                              status = ?, updated_at = GETDATE()
                          WHERE id = ?";
            $updateParams = [
                $emp_code, $first_name, ($last_name !== '' ? $last_name : null), $email, ($phone !== '' ? $phone : null),
                $department_id, $location_id, ($designation !== '' ? $designation : null), $joining_date,
                $status, $id
            ];
        }

        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
        if ($updateStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update employee details.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        // Fetch department name
        $deptName = 'General';
        if ($department_id) {
            $dStmt = sqlsrv_query($conn, "SELECT department_name FROM departments WHERE id = ?", [$department_id]);
            if ($dStmt && ($dRow = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC))) {
                $deptName = $dRow['department_name'] ?? 'General';
            }
        }

        // Update corresponding users table entry
        $uChk = sqlsrv_query($conn, "SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)", [$email, $emp_code, $oldEmail, $oldCode]);
        if ($uChk && $uRow = sqlsrv_fetch_array($uChk, SQLSRV_FETCH_ASSOC)) {
            if ($rawPassword !== '') {
                sqlsrv_query($conn, "UPDATE users SET username = ?, email = ?, password_hash = ?, full_name = ?, department = ?, is_active = ?, updated_at = GETDATE() WHERE id = ?", [
                    $emp_code, $email, $passHash, trim($first_name . ' ' . $last_name), $deptName, ($status === 'Active' ? 1 : 0), $uRow['id']
                ]);
            } else {
                sqlsrv_query($conn, "UPDATE users SET username = ?, email = ?, full_name = ?, department = ?, is_active = ?, updated_at = GETDATE() WHERE id = ?", [
                    $emp_code, $email, trim($first_name . ' ' . $last_name), $deptName, ($status === 'Active' ? 1 : 0), $uRow['id']
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Employee details updated successfully.'
        ]);
        exit;
    }

    // 3. ACTION: DELETE EMPLOYEE
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Employee ID for deletion.']);
            exit;
        }

        // Fetch employee info first to remove from users table
        $infoStmt = sqlsrv_query($conn, "SELECT emp_code, email FROM employees WHERE id = ?", [$id]);
        $info = $infoStmt ? sqlsrv_fetch_array($infoStmt, SQLSRV_FETCH_ASSOC) : null;

        $delSql = "DELETE FROM employees WHERE id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$id]);
        if ($delStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete employee.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        // Clean up or deactivate in users table
        if ($info) {
            sqlsrv_query($conn, "DELETE FROM users WHERE (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) AND role = 'Employee'", [$info['email'], $info['emp_code']]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Employee record removed successfully.'
        ]);
        exit;
    }

    // 4. ACTION: TOGGLE STATUS (Active <-> Inactive)
    if ($action === 'toggle_status') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Employee ID.']);
            exit;
        }

        // Fetch current status
        $q = sqlsrv_query($conn, "SELECT emp_code, email, status FROM employees WHERE id = ?", [$id]);
        $row = $q ? sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC) : null;
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found.']);
            exit;
        }

        $newStatus = ($row['status'] === 'Active') ? 'Inactive' : 'Active';

        $up = sqlsrv_query($conn, "UPDATE employees SET status = ?, updated_at = GETDATE() WHERE id = ?", [$newStatus, $id]);
        if ($up === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update employee status.']);
            exit;
        }

        // Update is_active in users table
        sqlsrv_query($conn, "UPDATE users SET is_active = ?, updated_at = GETDATE() WHERE (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) AND role = 'Employee'", [
            ($newStatus === 'Active' ? 1 : 0),
            $row['email'],
            $row['emp_code']
        ]);

        echo json_encode([
            'success'    => true,
            'new_status' => $newStatus,
            'message'    => "Employee status changed to {$newStatus}."
        ]);
        exit;
    }

    // 5. ACTION: BATCH CSV / JSON IMPORT
    if ($action === 'import') {
        $rows = $data['rows'] ?? [];
        $duplicateHandling = trim($data['duplicate_handling'] ?? 'skip'); // 'skip' or 'update'

        if (!is_array($rows) || count($rows) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No employee records provided for import.']);
            exit;
        }

        // Cache departments & locations map (name -> id)
        $deptMap = [];
        $dStmt = sqlsrv_query($conn, "SELECT id, LOWER(department_name) AS name FROM departments");
        if ($dStmt) {
            while ($r = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC)) {
                $deptMap[trim($r['name'])] = $r['id'];
            }
            sqlsrv_free_stmt($dStmt);
        }

        $locMap = [];
        $lStmt = sqlsrv_query($conn, "SELECT id, LOWER(location_name) AS name FROM locations");
        if ($lStmt) {
            while ($r = sqlsrv_fetch_array($lStmt, SQLSRV_FETCH_ASSOC)) {
                $locMap[trim($r['name'])] = $r['id'];
            }
            sqlsrv_free_stmt($lStmt);
        }

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $emp_code       = trim($row['emp_code'] ?? $row['Employee ID'] ?? $row['code'] ?? '');
            $first_name     = trim($row['first_name'] ?? $row['First Name'] ?? $row['name'] ?? '');
            $last_name      = trim($row['last_name'] ?? $row['Last Name'] ?? '');
            $email          = strtolower(trim($row['email'] ?? $row['Email'] ?? ''));
            $phone          = trim($row['phone'] ?? $row['Phone'] ?? $row['Mobile'] ?? '');
            $deptInput      = trim($row['department'] ?? $row['Department'] ?? '');
            $locInput       = trim($row['location'] ?? $row['Location'] ?? $row['Branch'] ?? '');
            $designation    = trim($row['designation'] ?? $row['Designation'] ?? $row['Job Title'] ?? '');
            $joining_date   = trim($row['joining_date'] ?? $row['Joining Date'] ?? '');
            $status         = trim($row['status'] ?? $row['Status'] ?? 'Active');

            // Fallback for single full name column
            if ($first_name === '' && !empty($row['Full Name'])) {
                $nameParts = explode(' ', trim($row['Full Name']), 2);
                $first_name = $nameParts[0];
                $last_name  = $nameParts[1] ?? '';
            }

            if ($first_name === '' || $email === '') {
                $errors[] = "Row {$rowNum}: First Name and Email are required.";
                $skipped++;
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNum}: Invalid email '{$email}'.";
                $skipped++;
                continue;
            }

            if (!in_array($status, ['Active', 'Inactive'])) {
                $status = 'Active';
            }

            // Map Department ID
            $deptId = null;
            if ($deptInput !== '') {
                $lowerDept = strtolower($deptInput);
                if (isset($deptMap[$lowerDept])) {
                    $deptId = $deptMap[$lowerDept];
                } else {
                    // Try partial match
                    foreach ($deptMap as $name => $id) {
                        if (strpos($name, $lowerDept) !== false || strpos($lowerDept, $name) !== false) {
                            $deptId = $id;
                            break;
                        }
                    }
                }
            }

            // Map Location ID
            $locId = null;
            if ($locInput !== '') {
                $lowerLoc = strtolower($locInput);
                if (isset($locMap[$lowerLoc])) {
                    $locId = $locMap[$lowerLoc];
                } else {
                    foreach ($locMap as $name => $id) {
                        if (strpos($name, $lowerLoc) !== false || strpos($lowerLoc, $name) !== false) {
                            $locId = $id;
                            break;
                        }
                    }
                }
            }

            // Format date if present
            $formattedDate = null;
            if ($joining_date !== '') {
                $timestamp = strtotime($joining_date);
                if ($timestamp !== false) {
                    $formattedDate = date('Y-m-d', $timestamp);
                }
            }

            // Auto-generate code if empty
            if ($emp_code === '') {
                $emp_code = getNextEmployeeCode($conn);
            }

            // Check if employee already exists by email or emp_code
            $existingId = null;
            $chk = sqlsrv_query($conn, "SELECT id FROM employees WHERE LOWER(email) = LOWER(?) OR LOWER(emp_code) = LOWER(?)", [$email, $emp_code]);
            if ($chk && $exRow = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC)) {
                $existingId = $exRow['id'];
            }

            if ($existingId !== null) {
                if ($duplicateHandling === 'update') {
                    $uSql = "UPDATE employees 
                             SET emp_code = ?, first_name = ?, last_name = ?, phone = ?, 
                                 department_id = COALESCE(?, department_id), 
                                 location_id = COALESCE(?, location_id), 
                                 designation = ?, joining_date = COALESCE(?, joining_date), 
                                 status = ?, updated_at = GETDATE()
                             WHERE id = ?";
                    $uParams = [$emp_code, $first_name, ($last_name !== '' ? $last_name : null), ($phone !== '' ? $phone : null), $deptId, $locId, ($designation !== '' ? $designation : null), $formattedDate, $status, $existingId];
                    $uStmt = sqlsrv_query($conn, $uSql, $uParams);
                    if ($uStmt !== false) {
                        $updated++;
                    } else {
                        $errors[] = "Row {$rowNum}: Failed to update existing employee ({$email}).";
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
                continue;
            }

            // Generate default password: First Name@Employee Code
            $defaultPass = $first_name . '@' . $emp_code;
            $passHash = password_hash($defaultPass, PASSWORD_DEFAULT);

            // Insert new employee
            $iSql = "INSERT INTO employees (emp_code, first_name, last_name, email, phone, department_id, location_id, designation, joining_date, status, password, password_hash, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
            $iParams = [$emp_code, $first_name, ($last_name !== '' ? $last_name : null), $email, ($phone !== '' ? $phone : null), $deptId, $locId, ($designation !== '' ? $designation : null), $formattedDate, $status, $defaultPass, $passHash];
            $iStmt = sqlsrv_query($conn, $iSql, $iParams);
            if ($iStmt !== false) {
                $inserted++;

                // Sync with users table
                $uChk = sqlsrv_query($conn, "SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)", [$email, $emp_code]);
                if ($uChk && $uRow = sqlsrv_fetch_array($uChk, SQLSRV_FETCH_ASSOC)) {
                    sqlsrv_query($conn, "UPDATE users SET username = ?, password_hash = ?, full_name = ?, role = 'Employee', is_active = ?, updated_at = GETDATE() WHERE id = ?", [
                        $emp_code, $passHash, trim($first_name . ' ' . $last_name), ($status === 'Active' ? 1 : 0), $uRow['id']
                    ]);
                } else {
                    sqlsrv_query($conn, "INSERT INTO users (username, email, password_hash, full_name, role, department, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 'Employee', ?, ?, GETDATE(), GETDATE())", [
                        $emp_code, $email, $passHash, trim($first_name . ' ' . $last_name), ($deptInput ?: 'General'), ($status === 'Active' ? 1 : 0)
                    ]);
                }
            } else {
                $errors[] = "Row {$rowNum}: Error saving {$email}.";
                $skipped++;
            }
        }

        echo json_encode([
            'success'  => true,
            'message'  => "Import completed: {$inserted} added, {$updated} updated, {$skipped} skipped.",
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => $errors
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Unrecognized action '{$action}'."]);
    exit;
}
