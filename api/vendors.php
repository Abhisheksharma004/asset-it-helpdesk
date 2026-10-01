<?php
/**
 * Vendor / Supplier Master API
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch all vendors (optional search, status filter)
 * - POST:
 *     - action = 'create': Add vendor
 *     - action = 'edit': Update vendor
 *     - action = 'delete': Delete vendor
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

// Auto-create table if not exists or add gstin column if missing
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vendors' AND xtype='U')
BEGIN
    CREATE TABLE vendors (
        id INT IDENTITY(1,1) PRIMARY KEY,
        vendor_name NVARCHAR(150) NOT NULL,
        contact_person NVARCHAR(100) NULL,
        phone NVARCHAR(30) NULL,
        email NVARCHAR(120) NULL,
        address NVARCHAR(255) NULL,
        gstin NVARCHAR(20) NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO vendors (vendor_name, contact_person, phone, email, address, gstin, status) VALUES
    ('Dell Technologies India', 'Rajesh Gupta', '+91 98201 12345', 'enterprise.sales@dell.com', 'Ambience Island, DLF Phase 3, Gurugram', '29AABCD1234F1Z5', 'Active'),
    ('Lenovo Global Technology', 'Amit Sharma', '+91 98450 67890', 'commercial@lenovo.com', 'Ferns Icon, Marathahalli Outer Ring Rd, Bangalore', '29AABCL5678G1Z2', 'Active'),
    ('HP India Sales Pvt Ltd', 'Sunil Verma', '+91 98110 54321', 'support.india@hp.com', 'Cyber City, Tower D, Gurugram', '06AAACH1234C1ZB', 'Active'),
    ('Cisco Systems India', 'Priya Menon', '+91 97400 98765', 'partners@cisco.com', 'SEZ Cessna Business Park, Bangalore', '29AABCC1122D1Z8', 'Active'),
    ('Microsoft Corporation India', 'Kavita Nair', '+91 98100 23456', 'business@microsoft.com', 'One Horizon Center, Golf Course Rd, Gurugram', '06AAACM2233E1Z4', 'Active'),
    ('Airtel Enterprise Services', 'Vikram Malhotra', '+91 98990 11223', 'telecom.corp@airtel.com', 'Bharti Crescent, Nelson Mandela Road, New Delhi', '07AABCB3344F1Z0', 'Active'),
    ('Tata Communications', 'Rohan Mehta', '+91 98210 99887', 'enterprises@tatacommunications.com', 'BKC, Bandra East, Mumbai', '27AAACT4455G1Z6', 'Active'),
    ('Canon India Pvt Ltd', 'Deepak Joshi', '+91 98300 44556', 'imaging.solutions@canon.co.in', 'Sector 32, Institutional Area, Gurugram', '06AAACC5566H1Z2', 'Active'),
    ('QuickHeal & Seqrite Antivirus', 'Sanjay Patil', '+91 98220 33445', 'corp.sales@seqrite.com', 'Shivajinagar, Pune', '27AABCQ6677I1Z8', 'Active'),
    ('Redington India Ltd', 'Anand K.', '+91 98400 55667', 'distributor@redington.in', 'Guindy, Chennai', '33AAACR7788J1Z4', 'Inactive');
END
ELSE
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('vendors') AND name = 'gstin')
    BEGIN
        ALTER TABLE vendors ADD gstin NVARCHAR(20) NULL;
    END
END";
sqlsrv_query($conn, $tableSetupSql);

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: Fetch vendors
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT id, vendor_name, contact_person, phone, email, address, gstin, status, 
                   CONVERT(VARCHAR(10), created_at, 105) AS created_at,
                   CONVERT(VARCHAR(10), updated_at, 105) AS updated_at
            FROM vendors 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (vendor_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ? OR gstin LIKE ?)";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
        $params[] = $searchWild;
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

    $vendors = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $vendors[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    // Compute stats across whole table
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0
    ];

    $statSql = "SELECT status, COUNT(*) AS count FROM vendors GROUP BY status";
    $statStmt = sqlsrv_query($conn, $statSql);
    if ($statStmt !== false) {
        while ($sRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC)) {
            $cnt = (int)$sRow['count'];
            $st = $sRow['status'] ?? '';
            $stats['total'] += $cnt;
            if ($st === 'Active') {
                $stats['active'] += $cnt;
            } elseif ($st === 'Inactive') {
                $stats['inactive'] += $cnt;
            }
        }
        sqlsrv_free_stmt($statStmt);
    }

    echo json_encode([
        'success' => true,
        'data' => $vendors,
        'stats' => $stats,
        'count' => count($vendors)
    ]);
    exit;
}

// Handle POST actions
if ($method === 'POST') {
    // Detect input payload
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    $action = trim($data['action'] ?? 'create');

    // 1. ACTION: CREATE
    if ($action === 'create') {
        $vendor_name    = trim($data['vendor_name'] ?? '');
        $contact_person = trim($data['contact_person'] ?? '');
        $phone          = trim($data['phone'] ?? '');
        $email          = trim($data['email'] ?? '');
        $address        = trim($data['address'] ?? '');
        $gstin          = strtoupper(trim($data['gstin'] ?? ''));
        $status         = trim($data['status'] ?? 'Active');

        if ($vendor_name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Vendor / Supplier Name is required.']);
            exit;
        }

        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Check for duplicate vendor_name
        $checkSql = "SELECT id FROM vendors WHERE LOWER(LTRIM(RTRIM(vendor_name))) = LOWER(?)";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$vendor_name]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Vendor '{$vendor_name}' already exists."]);
            exit;
        }

        $insertSql = "INSERT INTO vendors (vendor_name, contact_person, phone, email, address, gstin, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
        $insertParams = [$vendor_name, $contact_person, $phone, $email, $address, $gstin, $status];
        $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

        if ($insertStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add vendor.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = getLastInsertId($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Vendor added successfully.',
            'vendor_id' => $newId
        ]);
        exit;
    }

    // 2. ACTION: EDIT
    if ($action === 'edit' || $action === 'update') {
        $id             = intval($data['id'] ?? 0);
        $vendor_name    = trim($data['vendor_name'] ?? '');
        $contact_person = trim($data['contact_person'] ?? '');
        $phone          = trim($data['phone'] ?? '');
        $email          = trim($data['email'] ?? '');
        $address        = trim($data['address'] ?? '');
        $gstin          = strtoupper(trim($data['gstin'] ?? ''));
        $status         = trim($data['status'] ?? 'Active');

        if ($id <= 0 || $vendor_name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Vendor ID and Name are required.']);
            exit;
        }

        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Check for duplicate name under different ID
        $checkSql = "SELECT id FROM vendors WHERE LOWER(LTRIM(RTRIM(vendor_name))) = LOWER(?) AND id <> ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$vendor_name, $id]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Another vendor with name '{$vendor_name}' already exists."]);
            exit;
        }

        $updateSql = "UPDATE vendors 
                      SET vendor_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, gstin = ?, status = ?, updated_at = GETDATE() 
                      WHERE id = ?";
        $updateParams = [$vendor_name, $contact_person, $phone, $email, $address, $gstin, $status, $id];
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update vendor.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Vendor updated successfully.'
        ]);
        exit;
    }

    // 3. ACTION: TOGGLE STATUS
    if ($action === 'toggle_status') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
            exit;
        }

        $upSql = "UPDATE vendors 
                  SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END, 
                      updated_at = GETDATE() 
                  WHERE id = ?";
        $upStmt = sqlsrv_query($conn, $upSql, [$id]);
        if ($upStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => "Vendor status updated successfully."
        ]);
        exit;
    }

    // 4. ACTION: DELETE
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
            exit;
        }

        $delSql = "DELETE FROM vendors WHERE id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$id]);
        if ($delStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete vendor.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Vendor deleted successfully.'
        ]);
        exit;
    }

    // 5. ACTION: IMPORT
    if ($action === 'import') {
        $rows = $data['rows'] ?? $data['vendors'] ?? [];

        // Check if uploaded via standard file upload (multipart/form-data)
        if (empty($rows) && isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $handle = fopen($_FILES['file']['tmp_name'], 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle);
                if ($header !== false) {
                    $cleanHeader = array_map(function($h) {
                        return strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h)));
                    }, $header);

                    $nameIdx    = -1;
                    $personIdx  = -1;
                    $phoneIdx   = -1;
                    $emailIdx   = -1;
                    $addressIdx = -1;
                    $gstinIdx   = -1;
                    $statusIdx  = -1;

                    foreach ($cleanHeader as $idx => $col) {
                        if (strpos($col, 'vendor') !== false || strpos($col, 'supplier') !== false || strpos($col, 'company') !== false || strpos($col, 'name') !== false) {
                            if ($nameIdx === -1) $nameIdx = $idx;
                        }
                        if (strpos($col, 'person') !== false || strpos($col, 'contact') !== false || strpos($col, 'rep') !== false) {
                            $personIdx = $idx;
                        }
                        if (strpos($col, 'phone') !== false || strpos($col, 'mobile') !== false || strpos($col, 'tel') !== false) {
                            $phoneIdx = $idx;
                        }
                        if (strpos($col, 'email') !== false || strpos($col, 'mail') !== false) {
                            $emailIdx = $idx;
                        }
                        if (strpos($col, 'address') !== false || strpos($col, 'location') !== false || strpos($col, 'city') !== false || strpos($col, 'note') !== false) {
                            $addressIdx = $idx;
                        }
                        if (strpos($col, 'gst') !== false || strpos($col, 'tax') !== false) {
                            $gstinIdx = $idx;
                        }
                        if (strpos($col, 'status') !== false) {
                            $statusIdx = $idx;
                        }
                    }

                    if ($nameIdx === -1) {
                        $nameIdx = 0;
                    }

                    while (($csvRow = fgetcsv($handle)) !== false) {
                        if (empty($csvRow) || (count($csvRow) === 1 && trim($csvRow[0]) === '')) {
                            continue;
                        }
                        $rows[] = [
                            'vendor_name'    => $csvRow[$nameIdx] ?? '',
                            'contact_person' => ($personIdx >= 0) ? ($csvRow[$personIdx] ?? '') : '',
                            'phone'          => ($phoneIdx >= 0) ? ($csvRow[$phoneIdx] ?? '') : '',
                            'email'          => ($emailIdx >= 0) ? ($csvRow[$emailIdx] ?? '') : '',
                            'address'        => ($addressIdx >= 0) ? ($csvRow[$addressIdx] ?? '') : '',
                            'gstin'          => ($gstinIdx >= 0) ? ($csvRow[$gstinIdx] ?? '') : '',
                            'status'         => ($statusIdx >= 0) ? ($csvRow[$statusIdx] ?? 'Active') : 'Active'
                        ];
                    }
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid data rows found in the import file.']);
            exit;
        }

        $insertedCount = 0;
        $updatedCount  = 0;
        $skippedCount  = 0;
        $errors        = [];

        $duplicateHandling = $data['duplicate_handling'] ?? 'skip';

        $checkExistSql = "SELECT id FROM vendors WHERE LOWER(LTRIM(RTRIM(vendor_name))) = LOWER(?)";
        $insertRowSql  = "INSERT INTO vendors (vendor_name, contact_person, phone, email, address, gstin, status, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
        $updateRowSql  = "UPDATE vendors 
                          SET contact_person = ?, phone = ?, email = ?, address = ?, gstin = ?, status = ?, updated_at = GETDATE() 
                          WHERE id = ?";

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $vendor_name    = trim($row['vendor_name'] ?? '');
            $contact_person = trim($row['contact_person'] ?? '');
            $phone          = trim($row['phone'] ?? '');
            $email          = trim($row['email'] ?? '');
            $address        = trim($row['address'] ?? '');
            $gstin          = strtoupper(trim($row['gstin'] ?? ''));
            $rawStatus      = trim($row['status'] ?? 'Active');
            $status         = (strcasecmp($rawStatus, 'Inactive') === 0) ? 'Inactive' : 'Active';

            if ($vendor_name === '') {
                $skippedCount++;
                $errors[] = "Row #{$rowNum}: Skipped because Vendor Name is empty.";
                continue;
            }

            // Check if vendor already exists
            $checkStmt = sqlsrv_query($conn, $checkExistSql, [$vendor_name]);
            if ($checkStmt && $existRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
                $existId = $existRow['id'];
                sqlsrv_free_stmt($checkStmt);

                if ($duplicateHandling === 'update') {
                    $upStmt = sqlsrv_query($conn, $updateRowSql, [$contact_person, $phone, $email, $address, $gstin, $status, $existId]);
                    if ($upStmt !== false) {
                        $updatedCount++;
                    } else {
                        $errors[] = "Row #{$rowNum} ({$vendor_name}): Update failed.";
                    }
                } else {
                    $skippedCount++;
                }
            } else {
                if ($checkStmt !== false) {
                    sqlsrv_free_stmt($checkStmt);
                }
                // Insert new record
                $insStmt = sqlsrv_query($conn, $insertRowSql, [$vendor_name, $contact_person, $phone, $email, $address, $gstin, $status]);
                if ($insStmt !== false) {
                    $insertedCount++;
                } else {
                    $errors[] = "Row #{$rowNum} ({$vendor_name}): Insert failed.";
                }
            }
        }

        echo json_encode([
            'success'       => true,
            'message'       => "Import completed: {$insertedCount} added, {$updatedCount} updated, {$skippedCount} skipped.",
            'inserted'      => $insertedCount,
            'updated'       => $updatedCount,
            'skipped'       => $skippedCount,
            'total_rows'    => count($rows),
            'errors'        => $errors
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
