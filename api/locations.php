<?php
/**
 * Locations / Branch Master API
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch all locations (optional search, status filter)
 * - POST:
 *     - action = 'create': Add location (name, description, status)
 *     - action = 'edit': Update location (id, name, description, status)
 *     - action = 'delete': Delete location
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
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='locations' AND xtype='U')
BEGIN
    CREATE TABLE locations (
        id INT IDENTITY(1,1) PRIMARY KEY,
        location_name NVARCHAR(100) NOT NULL,
        description NVARCHAR(255) NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO locations (location_name, description, status) VALUES
    ('Corporate HQ - Mumbai', 'BKC Corporate Park, Tower 2, 8th & 9th Floor', 'Active'),
    ('Tech Hub - Bangalore', 'Electronic City Phase 1, Silicon Valley Campus', 'Active'),
    ('Branch Office - Delhi NCR', 'Cyber City DLF Phase 2, Building 10, Gurgaon', 'Active'),
    ('Delivery Center - Hyderabad', 'HITEC City, Mindspace IT Park, 4th Floor', 'Active'),
    ('Development Center - Pune', 'Hinjewadi Phase 2, Rajiv Gandhi Infotech Park', 'Active'),
    ('Operations Center - Chennai', 'OMR IT Corridor, Tidel Park, 3rd Floor', 'Active'),
    ('Regional Hub - Kolkata', 'Sector V, Salt Lake Electronics Complex', 'Active'),
    ('Support Center - Ahmedabad', 'SG Highway, Titanium City Center', 'Active'),
    ('Disaster Recovery Site - Jaipur', 'Sitapura Industrial Area, Tier III Data Facility', 'Active');
END";
sqlsrv_query($conn, $tableSetupSql);

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: Fetch locations
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT id, location_name, description, status, 
                   CONVERT(VARCHAR(10), created_at, 105) AS created_at,
                   CONVERT(VARCHAR(10), updated_at, 105) AS updated_at
            FROM locations 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (location_name LIKE ? OR description LIKE ?)";
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

    $locations = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $locations[] = $row;
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
        FROM locations";
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
        'data' => $locations,
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

    // 1. CREATE LOCATION
    if ($action === 'create') {
        $name        = trim($data['location_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $status      = trim($data['status'] ?? 'Active');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Location / Branch Name is required.']);
            exit;
        }

        // Duplicate name check
        $checkSql = "SELECT id FROM locations WHERE LOWER(location_name) = LOWER(?)";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$name]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Location '{$name}' already exists."]);
            exit;
        }

        $insertSql = "INSERT INTO locations (location_name, description, status, created_at, updated_at) 
                      VALUES (?, ?, ?, GETDATE(), GETDATE())";
        $insertParams = [$name, $description, $status];
        $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

        if ($insertStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add location.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = getLastInsertId($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Branch / Location added successfully.',
            'location_id' => $newId
        ]);
        exit;
    }

    // 2. EDIT / UPDATE LOCATION
    if ($action === 'edit' || $action === 'update') {
        $id          = (int)($data['id'] ?? 0);
        $name        = trim($data['location_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $status      = trim($data['status'] ?? 'Active');

        if ($id <= 0 || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Location ID and Name are required.']);
            exit;
        }

        // Duplicate name check on other records
        $checkSql = "SELECT id FROM locations WHERE LOWER(location_name) = LOWER(?) AND id != ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$name, $id]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Location '{$name}' already exists."]);
            exit;
        }

        $updateSql = "UPDATE locations 
                      SET location_name = ?, description = ?, status = ?, updated_at = GETDATE() 
                      WHERE id = ?";
        $updateParams = [$name, $description, $status, $id];
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update location.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Branch / Location updated successfully.'
        ]);
        exit;
    }

    // 3. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Location ID.']);
            exit;
        }

        $toggleSql = "UPDATE locations 
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
            'message' => 'Location status updated successfully.'
        ]);
        exit;
    }

    // 4. DELETE LOCATION
    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Location ID.']);
            exit;
        }

        $deleteSql = "DELETE FROM locations WHERE id = ?";
        $deleteStmt = sqlsrv_query($conn, $deleteSql, [$id]);

        if ($deleteStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete location.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Branch / Location deleted successfully.'
        ]);
        exit;
    }

    // 5. IMPORT LOCATIONS (CSV / JSON Batch)
    if ($action === 'import') {
        $rows = [];

        // Check if rows sent as JSON array
        if (!empty($data['locations']) && is_array($data['locations'])) {
            $rows = $data['locations'];
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
                        if (strpos($c, 'name') !== false || strpos($c, 'location') !== false || strpos($c, 'branch') !== false) $headerMap['name'] = $idx;
                        elseif (strpos($c, 'desc') !== false || strpos($c, 'address') !== false || strpos($c, 'detail') !== false) $headerMap['desc'] = $idx;
                        elseif (strpos($c, 'stat') !== false) $headerMap['status'] = $idx;
                    }
                }

                while (($line = fgetcsv($handle)) !== false) {
                    if (empty($line) || (count($line) === 1 && empty($line[0]))) continue;
                    $rowName = isset($headerMap['name']) ? ($line[$headerMap['name']] ?? '') : ($line[0] ?? '');
                    $rowDesc = isset($headerMap['desc']) ? ($line[$headerMap['desc']] ?? '') : ($line[1] ?? '');
                    $rowStat = isset($headerMap['status']) ? ($line[$headerMap['status']] ?? '') : ($line[2] ?? '');
                    $rows[] = [
                        'location_name' => trim($rowName),
                        'description'   => trim($rowDesc),
                        'status'        => trim($rowStat)
                    ];
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid locations found to import.']);
            exit;
        }

        $imported = 0;
        $skipped = 0;
        $duplicateHandling = $data['duplicate_handling'] ?? 'skip'; // 'skip' or 'update'

        foreach ($rows as $row) {
            $name = trim($row['location_name'] ?? '');
            if (empty($name)) {
                $skipped++;
                continue;
            }

            $desc = trim($row['description'] ?? '');
            $rawStatus = ucfirst(strtolower(trim($row['status'] ?? '')));
            $status = in_array($rawStatus, ['Active', 'Inactive']) ? $rawStatus : 'Active';

            // Check if location name exists
            $checkSql = "SELECT id FROM locations WHERE LOWER(location_name) = LOWER(?)";
            $checkStmt = sqlsrv_query($conn, $checkSql, [$name]);
            $existing = ($checkStmt && ($erow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC))) ? $erow : null;
            if ($checkStmt) sqlsrv_free_stmt($checkStmt);

            if ($existing) {
                if ($duplicateHandling === 'update') {
                    $updateSql = "UPDATE locations SET description = ?, status = ?, updated_at = GETDATE() WHERE id = ?";
                    sqlsrv_query($conn, $updateSql, [$desc, $status, $existing['id']]);
                    $imported++;
                } else {
                    $skipped++;
                }
            } else {
                $insertSql = "INSERT INTO locations (location_name, description, status, created_at, updated_at) VALUES (?, ?, ?, GETDATE(), GETDATE())";
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
            'message' => "Import complete: {$imported} locations processed successfully." . ($skipped > 0 ? " ({$skipped} duplicate/empty skipped)" : ""),
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
