<?php
/**
 * Accessory Categories API
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch all accessory categories (optional search, status filter)
 * - POST:
 *     - action = 'create': Add category (category_name, description, status)
 *     - action = 'edit': Update category (id, category_name, description, status)
 *     - action = 'delete': Delete category
 *     - action = 'toggle_status': Toggle Active / Inactive
 */

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Auto-create table if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='accessory_categories' AND xtype='U')
BEGIN
    CREATE TABLE accessory_categories (
        id INT IDENTITY(1,1) PRIMARY KEY,
        category_name NVARCHAR(100) NOT NULL,
        description NVARCHAR(255) NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO accessory_categories (category_name, description, status) VALUES
    ('Keyboards & Mice', 'Ergonomic keyboards, wireless mice, combo sets, and trackpads', 'Active'),
    ('Docks & Hubs', 'Thunderbolt 4 docking stations, multi-port USB-C hubs, and port replicators', 'Active'),
    ('Headsets & Audio', 'ANC business headsets, wireless headphones, and conference speakerphones', 'Active'),
    ('Webcams & Video', '4K / 1080p webcams, video conferencing units, and privacy shutters', 'Active'),
    ('Chargers & Power Adapters', 'USB-C GaN fast chargers, laptop power bricks, and surge extension units', 'Active'),
    ('Cables & Display Adapters', 'HDMI, DisplayPort, Type-C cables, and high-speed thunderbolt links', 'Active'),
    ('Security Tokens & Smart Keys', 'YubiKey FIDO2 hardware security keys and smart card authentication tokens', 'Active'),
    ('Laptop Stands & Mounts', 'Ergonomic aluminum laptop stands, monitor arms, and desk mounts', 'Active');
END";
if ($conn !== false) {
    sqlsrv_query($conn, $tableSetupSql);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle GET: Fetch categories
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT id, category_name, description, status, 
                   CONVERT(VARCHAR(10), created_at, 105) AS created_at,
                   CONVERT(VARCHAR(10), updated_at, 105) AS updated_at
            FROM accessory_categories 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (category_name LIKE ? OR description LIKE ?)";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    if ($status !== '' && $status !== 'all') {
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

    $categories = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $categories[] = $row;
        if (($row['status'] ?? '') === 'Active') {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }
    $stats['total'] = count($categories);

    sqlsrv_free_stmt($stmt);

    echo json_encode([
        'success'    => true,
        'categories' => $categories,
        'stats'      => $stats
    ]);
    exit;
}

// Handle POST: Actions
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $data = is_array($jsonData) ? $jsonData : $_POST;

    $action = trim($data['action'] ?? ($_POST['action'] ?? ''));

    // 1. CREATE CATEGORY
    if ($action === 'create') {
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Duplicate check
        $checkSql = "SELECT COUNT(*) as cnt FROM accessory_categories WHERE category_name = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$name]);
        if ($checkStmt !== false) {
            $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            if ($row && $row['cnt'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'An accessory category with this name already exists.']);
                exit;
            }
            sqlsrv_free_stmt($checkStmt);
        }

        $insertSql = "INSERT INTO accessory_categories (category_name, description, status, created_at, updated_at) 
                      OUTPUT INSERTED.id 
                      VALUES (?, ?, ?, GETDATE(), GETDATE())";
        $insertParams = [$name, $desc, $status];
        $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

        if ($insertStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create accessory category.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $insertedRow = sqlsrv_fetch_array($insertStmt, SQLSRV_FETCH_ASSOC);
        $newId = $insertedRow['id'] ?? null;
        sqlsrv_free_stmt($insertStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Accessory category created successfully.',
            'category_id' => $newId
        ]);
        exit;
    }

    // 2. EDIT CATEGORY
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($id <= 0 || $name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Category ID and Name are required.']);
            exit;
        }

        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Duplicate check
        $checkSql = "SELECT COUNT(*) as cnt FROM accessory_categories WHERE category_name = ? AND id != ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$name, $id]);
        if ($checkStmt !== false) {
            $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            if ($row && $row['cnt'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Another accessory category with this name already exists.']);
                exit;
            }
            sqlsrv_free_stmt($checkStmt);
        }

        $updateSql = "UPDATE accessory_categories 
                      SET category_name = ?, description = ?, status = ?, updated_at = GETDATE() 
                      WHERE id = ?";
        $updateParams = [$name, $desc, $status, $id];
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

        if ($updateStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update accessory category.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        sqlsrv_free_stmt($updateStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Accessory category updated successfully.'
        ]);
        exit;
    }

    // 3. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid category ID.']);
            exit;
        }

        $toggleSql = "UPDATE accessory_categories 
                      SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END,
                          updated_at = GETDATE()
                      OUTPUT INSERTED.status
                      WHERE id = ?";
        $toggleStmt = sqlsrv_query($conn, $toggleSql, [$id]);

        if ($toggleStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to toggle status.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $row = sqlsrv_fetch_array($toggleStmt, SQLSRV_FETCH_ASSOC);
        $newStatus = $row['status'] ?? 'Unknown';
        sqlsrv_free_stmt($toggleStmt);

        echo json_encode([
            'success'    => true,
            'message'    => "Category status changed to {$newStatus}.",
            'new_status' => $newStatus
        ]);
        exit;
    }

    // 4. DELETE CATEGORY
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid category ID.']);
            exit;
        }

        $deleteSql = "DELETE FROM accessory_categories WHERE id = ?";
        $deleteStmt = sqlsrv_query($conn, $deleteSql, [$id]);

        if ($deleteStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete accessory category.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        sqlsrv_free_stmt($deleteStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Accessory category deleted successfully.'
        ]);
        exit;
    }

    // 5. IMPORT CATEGORIES (CSV or JSON Batch)
    if ($action === 'import') {
        $rows = [];

        // Check if categories sent as JSON array
        if (!empty($data['categories']) && is_array($data['categories'])) {
            $rows = $data['categories'];
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
                        if (strpos($c, 'name') !== false || strpos($c, 'category') !== false) $headerMap['name'] = $idx;
                        elseif (strpos($c, 'desc') !== false) $headerMap['desc'] = $idx;
                        elseif (strpos($c, 'stat') !== false) $headerMap['status'] = $idx;
                    }
                }

                while (($line = fgetcsv($handle)) !== false) {
                    if (empty($line) || (count($line) === 1 && empty($line[0]))) continue;
                    $rowName = isset($headerMap['name']) ? ($line[$headerMap['name']] ?? '') : ($line[0] ?? '');
                    $rowDesc = isset($headerMap['desc']) ? ($line[$headerMap['desc']] ?? '') : ($line[1] ?? '');
                    $rowStat = isset($headerMap['status']) ? ($line[$headerMap['status']] ?? '') : ($line[2] ?? '');
                    $rows[] = [
                        'category_name' => trim($rowName),
                        'description'   => trim($rowDesc),
                        'status'        => trim($rowStat)
                    ];
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid categories found to import.']);
            exit;
        }

        $imported = 0;
        $skipped = 0;
        $duplicateHandling = $data['duplicate_handling'] ?? 'skip'; // 'skip' or 'update'

        foreach ($rows as $row) {
            $name = trim($row['category_name'] ?? '');
            if (empty($name)) {
                $skipped++;
                continue;
            }

            $desc = trim($row['description'] ?? '');
            $rawStatus = ucfirst(strtolower(trim($row['status'] ?? '')));
            $status = in_array($rawStatus, ['Active', 'Inactive']) ? $rawStatus : 'Active';

            // Check if category name exists
            $checkSql = "SELECT id FROM accessory_categories WHERE LOWER(category_name) = LOWER(?)";
            $checkStmt = sqlsrv_query($conn, $checkSql, [$name]);
            $existing = ($checkStmt && ($erow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC))) ? $erow : null;
            if ($checkStmt) sqlsrv_free_stmt($checkStmt);

            if ($existing) {
                if ($duplicateHandling === 'update') {
                    $updateSql = "UPDATE accessory_categories SET description = ?, status = ?, updated_at = GETDATE() WHERE id = ?";
                    sqlsrv_query($conn, $updateSql, [$desc, $status, $existing['id']]);
                    $imported++;
                } else {
                    $skipped++;
                }
            } else {
                $insertSql = "INSERT INTO accessory_categories (category_name, description, status, created_at, updated_at) VALUES (?, ?, ?, GETDATE(), GETDATE())";
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
            'message' => "Import complete: {$imported} categories processed successfully." . ($skipped > 0 ? " ({$skipped} duplicate/empty skipped)" : ""),
            'imported' => $imported,
            'skipped' => $skipped
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
    exit;
}
