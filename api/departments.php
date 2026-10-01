<?php
/**
 * Departments API
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch all departments (optional search, status filter)
 * - POST:
 *     - action = 'create': Add department (name, description, status)
 *     - action = 'edit': Update department (id, name, description, status)
 *     - action = 'delete': Delete department
 *     - action = 'toggle_status': Toggle Active / Inactive
 *     - action = 'import': Batch CSV/JSON import with duplicate handling
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

// Auto-create table if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='departments' AND xtype='U')
BEGIN
    CREATE TABLE departments (
        id INT IDENTITY(1,1) PRIMARY KEY,
        department_name NVARCHAR(100) NOT NULL,
        description NVARCHAR(255) NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO departments (department_name, description, status) VALUES
    ('Information Technology (IT)', 'Enterprise IT infrastructure, systems, and network operations', 'Active'),
    ('Software Engineering', 'Application development, QA testing, and cloud DevOps', 'Active'),
    ('Human Resources (HR)', 'Talent acquisition, employee lifecycle, and culture', 'Active'),
    ('Finance & Accounts', 'Financial planning, accounting, payroll, and auditing', 'Active'),
    ('Sales & Business Development', 'Client acquisition, key accounts, and revenue generation', 'Active'),
    ('Marketing & Communications', 'Brand strategy, digital campaigns, and PR', 'Active'),
    ('Operations & Logistics', 'Supply chain, asset warehousing, and facility dispatch', 'Active'),
    ('Customer Support & Helpdesk', 'Client assistance, ticket resolution, and service delivery', 'Active'),
    ('Administration & Facilities', 'Office premises, workplace security, and utilities', 'Active');
END";
sqlsrv_query($conn, $tableSetupSql);

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: Fetch departments
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT id, department_name, description, status, 
                   CONVERT(VARCHAR(10), created_at, 105) AS created_at,
                   CONVERT(VARCHAR(10), updated_at, 105) AS updated_at
            FROM departments 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (department_name LIKE ? OR description LIKE ?)";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    if ($status !== '' && $status !== 'All') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query failed.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $departments = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $departments[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    // Compute stats
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0
    ];

    $statsSql = "SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) AS inactive
        FROM departments";
    $statsStmt = sqlsrv_query($conn, $statsSql);
    if ($statsStmt !== false) {
        $sRow = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC);
        if ($sRow) {
            $stats = [
                'total' => (int)($sRow['total'] ?? 0),
                'active' => (int)($sRow['active'] ?? 0),
                'inactive' => (int)($sRow['inactive'] ?? 0),
            ];
        }
        sqlsrv_free_stmt($statsStmt);
    }

    echo json_encode([
        'success' => true,
        'data' => $departments,
        'stats' => $stats
    ]);
    exit;
}

// Handle POST: Actions (create, edit, delete, toggle_status, import)
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? 'create';

    // 1. CREATE DEPARTMENT
    if ($action === 'create') {
        $name        = trim($data['department_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $status      = trim($data['status'] ?? 'Active');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department Name is required.']);
            exit;
        }

        // Duplicate name check
        $checkSql = "SELECT id FROM departments WHERE LOWER(department_name) = LOWER(?)";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$name]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Department '{$name}' already exists."]);
            exit;
        }

        $insertSql = "INSERT INTO departments (department_name, description, status, created_at, updated_at) 
                      VALUES (?, ?, ?, GETDATE(), GETDATE())";
        $insertParams = [$name, $description, $status];
        $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

        if ($insertStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add department.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = getLastInsertId($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully.',
            'department_id' => $newId
        ]);
        exit;
    }

    // 2. EDIT / UPDATE DEPARTMENT
    if ($action === 'edit' || $action === 'update') {
        $id          = (int)($data['id'] ?? 0);
        $name        = trim($data['department_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $status      = trim($data['status'] ?? 'Active');

        if ($id <= 0 || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Department ID and Name are required.']);
            exit;
        }

        // Duplicate name check on other records
        $checkSql = "SELECT id FROM departments WHERE LOWER(department_name) = LOWER(?) AND id != ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$name, $id]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Department '{$name}' already exists."]);
            exit;
        }

        $updateSql = "UPDATE departments 
                      SET department_name = ?, description = ?, status = ?, updated_at = GETDATE() 
                      WHERE id = ?";
        $updateParams = [$name, $description, $status, $id];
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update department.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Department updated successfully.'
        ]);
        exit;
    }

    // 3. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Department ID.']);
            exit;
        }

        $toggleSql = "UPDATE departments 
                      SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END,
                          updated_at = GETDATE()
                      WHERE id = ?";
        $toggleStmt = sqlsrv_query($conn, $toggleSql, [$id]);

        if ($toggleStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to toggle status.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Department status updated successfully.'
        ]);
        exit;
    }

    // 4. DELETE DEPARTMENT
    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Department ID.']);
            exit;
        }

        $deleteSql = "DELETE FROM departments WHERE id = ?";
        $deleteStmt = sqlsrv_query($conn, $deleteSql, [$id]);

        if ($deleteStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete department.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Department deleted successfully.'
        ]);
        exit;
    }

    // 5. IMPORT DEPARTMENTS (CSV / JSON Batch)
    if ($action === 'import') {
        $rows = [];

        // Check if rows sent as JSON array
        if (!empty($data['departments']) && is_array($data['departments'])) {
            $rows = $data['departments'];
        } 
        // Or if uploaded via multipart file
        elseif (isset($_FILES['csv_file']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                $headerMap = [];
                if ($header) {
                    foreach ($header as $idx => $colName) {
                        $c = strtolower(trim(str_replace([' ', '_', '-', '"'], '', $colName)));
                        if (strpos($c, 'name') !== false || strpos($c, 'dep') !== false) $headerMap['name'] = $idx;
                        elseif (strpos($c, 'desc') !== false || strpos($c, 'detail') !== false) $headerMap['desc'] = $idx;
                        elseif (strpos($c, 'stat') !== false) $headerMap['status'] = $idx;
                    }
                }

                while (($line = fgetcsv($handle)) !== false) {
                    if (empty($line) || (count($line) === 1 && empty($line[0]))) continue;
                    $rowName = isset($headerMap['name']) ? ($line[$headerMap['name']] ?? '') : ($line[0] ?? '');
                    $rowDesc = isset($headerMap['desc']) ? ($line[$headerMap['desc']] ?? '') : ($line[1] ?? '');
                    $rowStat = isset($headerMap['status']) ? ($line[$headerMap['status']] ?? '') : ($line[2] ?? '');
                    $rows[] = [
                        'department_name' => trim($rowName),
                        'description'     => trim($rowDesc),
                        'status'          => trim($rowStat)
                    ];
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid departments found to import.']);
            exit;
        }

        $imported = 0;
        $skipped = 0;
        $duplicateHandling = $data['duplicate_handling'] ?? 'skip'; // 'skip' or 'update'

        foreach ($rows as $row) {
            $name = trim($row['department_name'] ?? '');
            if (empty($name)) {
                $skipped++;
                continue;
            }

            $desc = trim($row['description'] ?? '');
            $rawStatus = ucfirst(strtolower(trim($row['status'] ?? '')));
            $status = in_array($rawStatus, ['Active', 'Inactive']) ? $rawStatus : 'Active';

            // Check if department name exists
            $checkSql = "SELECT id FROM departments WHERE LOWER(department_name) = LOWER(?)";
            $checkStmt = sqlsrv_query($conn, $checkSql, [$name]);
            $existing = ($checkStmt && ($erow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC))) ? $erow : null;
            if ($checkStmt) sqlsrv_free_stmt($checkStmt);

            if ($existing) {
                if ($duplicateHandling === 'update') {
                    $updateSql = "UPDATE departments SET description = ?, status = ?, updated_at = GETDATE() WHERE id = ?";
                    sqlsrv_query($conn, $updateSql, [$desc, $status, $existing['id']]);
                    $imported++;
                } else {
                    $skipped++;
                }
            } else {
                $insertSql = "INSERT INTO departments (department_name, description, status, created_at, updated_at) VALUES (?, ?, ?, GETDATE(), GETDATE())";
                $res = sqlsrv_query($conn, $insertSql, [$name, $desc, $status]);
                if ($res !== false) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Import complete: {$imported} departments processed successfully." . ($skipped > 0 ? " ({$skipped} duplicate/empty skipped)" : ""),
            'imported' => $imported,
            'skipped' => $skipped
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
