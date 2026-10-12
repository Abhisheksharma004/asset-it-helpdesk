<?php
/**
 * Scrap Accessories Management API
 * Handles database persistence and updates the accessories table stock
 * Asset Management & IT Service Desk Portal
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auto-create table if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='scrap_accessories' AND xtype='U')
BEGIN
    CREATE TABLE scrap_accessories (
        id INT IDENTITY(1,1) PRIMARY KEY,
        scrap_ref NVARCHAR(50) NOT NULL UNIQUE,
        accessory_id INT NULL,
        accessory_name NVARCHAR(200) NOT NULL,
        sku NVARCHAR(50) NULL,
        category NVARCHAR(100) NULL,
        quantity INT NOT NULL DEFAULT 1,
        reason NVARCHAR(255) NOT NULL,
        bin_location NVARCHAR(150) NULL,
        disposal_status NVARCHAR(100) NOT NULL DEFAULT 'In Scrap Yard',
        salvage_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        authorized_by NVARCHAR(150) NULL,
        notes NVARCHAR(MAX) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";

if (isset($conn) && $conn !== false) {
    sqlsrv_query($conn, $tableSetupSql);
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

/**
 * Generate Next Scrap Reference: SCR + MMYY + 3DIGITSERIAL (e.g. SCR1026002)
 */
function getNextScrapRef($conn) {
    $now = new DateTime();
    $prefix = 'SCR' . $now->format('my'); // e.g. SCR1026

    $sql = "SELECT scrap_ref FROM scrap_accessories WHERE scrap_ref LIKE ? ORDER BY scrap_ref DESC";
    $stmt = sqlsrv_query($conn, $sql, [$prefix . '%']);

    $maxNum = 0;
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ref = $row['scrap_ref'];
            $serialPart = substr($ref, strlen($prefix));
            if (is_numeric($serialPart)) {
                $num = intval($serialPart);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    if ($maxNum === 0) {
        $cSql = "SELECT COUNT(*) as cnt FROM scrap_accessories";
        $cStmt = sqlsrv_query($conn, $cSql);
        if ($cStmt !== false && ($cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC))) {
            $maxNum = intval($cRow['cnt']);
            sqlsrv_free_stmt($cStmt);
        }
    }

    $nextNum = $maxNum + 1;
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
        $action = $_POST['action'] ?? $action;
    }
}

// -------------------------------------------------------------
// 1. ACTION: LIST RECORDS & STATS
// -------------------------------------------------------------
if ($action === 'list') {
    $records = [];
    $stats = [
        'total_scrapped_qty' => 0,
        'in_yard_qty' => 0,
        'recycled_qty' => 0,
        'total_salvage_val' => 0.0,
        'total_records' => 0
    ];

    $sql = "SELECT id, scrap_ref, accessory_id, accessory_name, sku, category, quantity, 
                   reason, bin_location, disposal_status, salvage_value, authorized_by, notes, 
                   CONVERT(VARCHAR(10), created_at, 105) AS created_date
            FROM scrap_accessories 
            ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $qty = (int)($row['quantity'] ?? 1);
            $val = (float)($row['salvage_value'] ?? 0);
            $st = trim($row['disposal_status'] ?? 'In Scrap Yard');

            $stats['total_scrapped_qty'] += $qty;
            $stats['total_salvage_val'] += $val;
            if ($st === 'In Scrap Yard') {
                $stats['in_yard_qty'] += $qty;
            } elseif ($st === 'E-Waste Recycled') {
                $stats['recycled_qty'] += $qty;
            }

            // Normalise status key for frontend filtering
            $statusKey = 'yard';
            if (stripos($st, 'recycled') !== false || stripos($st, 'e-waste') !== false) {
                $statusKey = 'recycled';
            } elseif (stripos($st, 'sold') !== false) {
                $statusKey = 'sold';
            } elseif (stripos($st, 'written') !== false) {
                $statusKey = 'written-off';
            }

            $records[] = [
                'id'              => (int)$row['id'],
                'ref'             => $row['scrap_ref'],
                'accessory_id'    => $row['accessory_id'] ? (int)$row['accessory_id'] : null,
                'name'            => $row['accessory_name'],
                'sku'             => $row['sku'] ?? 'N/A',
                'category'        => $row['category'] ?? 'General',
                'qty'             => $qty,
                'reason'          => $row['reason'],
                'location'        => $row['bin_location'] ?? 'IT Scrap Bin',
                'status'          => $statusKey,
                'status_text'     => $st,
                'salvage_val'     => $val,
                'date'            => $row['created_date'],
                'approver'        => $row['authorized_by'] ?? 'IT Admin',
                'notes'           => $row['notes'] ?? ''
            ];
        }
        $stats['total_records'] = count($records);
        sqlsrv_free_stmt($stmt);
    }

    echo json_encode([
        'success' => true,
        'stats'   => $stats,
        'records' => $records
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. ACTION: CREATE SCRAP ENTRY & UPDATE ACCESSORIES STOCK
// -------------------------------------------------------------
if ($action === 'create') {
    $accessoryId = isset($_POST['accessory_id']) ? (int)$_POST['accessory_id'] : 0;
    $quantity    = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $reason      = trim($_POST['reason'] ?? 'Physically Damaged');
    $binLocation = trim($_POST['bin_location'] ?? 'IT Scrap Yard Bin 1');
    $disposalSt  = trim($_POST['disposal_status'] ?? 'In Scrap Yard');
    $salvageVal  = isset($_POST['salvage_value']) ? (float)$_POST['salvage_value'] : 0.0;
    $authBy      = trim($_POST['authorized_by'] ?? ($_SESSION['user_name'] ?? 'Abhishek Sharma'));
    $notes       = trim($_POST['notes'] ?? '');

    if ($accessoryId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a valid accessory.']);
        exit;
    }

    if ($quantity <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Scrap quantity must be at least 1 unit.']);
        exit;
    }

    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select or provide a scrap reason / defect.']);
        exit;
    }

    // Step A: Fetch current accessory data and lock check
    $accCheckSql = "SELECT id, name, sku, category, in_stock, total_qty FROM accessories WHERE id = ?";
    $accStmt = sqlsrv_query($conn, $accCheckSql, [$accessoryId]);
    if ($accStmt === false || !($acc = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Accessory record not found in database.']);
        exit;
    }
    sqlsrv_free_stmt($accStmt);

    $currentInStock  = (int)($acc['in_stock'] ?? 0);
    $currentTotalQty = (int)($acc['total_qty'] ?? 0);

    // Step B: Strict Stock Verification
    if ($quantity > $currentInStock) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Cannot scrap {$quantity} units. Only {$currentInStock} units available in stock!"
        ]);
        exit;
    }

    // Step C: Generate unique Scrap Reference Code (SCR+MMYY+serial, e.g. SCR1026002)
    $scrapRef = getNextScrapRef($conn);

    // Step D: Run Database Transaction to Insert Scrap Entry and Update Accessories Table
    if (sqlsrv_begin_transaction($conn) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to begin database transaction.']);
        exit;
    }

    // 1. Insert into scrap_accessories
    $insertSql = "INSERT INTO scrap_accessories 
                  (scrap_ref, accessory_id, accessory_name, sku, category, quantity, reason, bin_location, disposal_status, salvage_value, authorized_by, notes, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
    $insertParams = [
        $scrapRef,
        $accessoryId,
        $acc['name'],
        $acc['sku'],
        $acc['category'],
        $quantity,
        $reason,
        $binLocation,
        $disposalSt,
        $salvageVal,
        $authBy,
        $notes
    ];
    $insStmt = sqlsrv_query($conn, $insertSql, $insertParams);

    if ($insStmt === false) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to insert scrap record into database.', 'errors' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($insStmt);

    // 2. Update accessories table: deduct in_stock and total_qty
    $updateAccSql = "UPDATE accessories 
                     SET in_stock = in_stock - ?,
                         total_qty = CASE WHEN total_qty >= ? THEN total_qty - ? ELSE 0 END,
                         updated_at = GETDATE()
                     WHERE id = ?";
    $updateParams = [$quantity, $quantity, $quantity, $accessoryId];
    $upStmt = sqlsrv_query($conn, $updateAccSql, $updateParams);

    if ($upStmt === false) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update accessories table stock.', 'errors' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($upStmt);

    // Commit Transaction
    sqlsrv_commit($conn);

    $newInStock = max(0, $currentInStock - $quantity);
    $newTotalQty = max(0, $currentTotalQty - $quantity);

    // Normalize status key
    $statusKey = 'yard';
    if (stripos($disposalSt, 'recycled') !== false || stripos($disposalSt, 'e-waste') !== false) {
        $statusKey = 'recycled';
    } elseif (stripos($disposalSt, 'sold') !== false) {
        $statusKey = 'sold';
    } elseif (stripos($disposalSt, 'written') !== false) {
        $statusKey = 'written-off';
    }

    echo json_encode([
        'success' => true,
        'message' => "Scrap entry {$scrapRef} successfully recorded and accessories stock updated!",
        'data'    => [
            'ref'             => $scrapRef,
            'accessory_id'    => $accessoryId,
            'name'            => $acc['name'],
            'sku'             => $acc['sku'],
            'category'        => $acc['category'],
            'qty'             => $quantity,
            'reason'          => $reason,
            'location'        => $binLocation,
            'status'          => $statusKey,
            'status_text'     => $disposalSt,
            'salvage_val'     => $salvageVal,
            'date'            => date('d M Y'),
            'approver'        => $authBy,
            'notes'           => $notes,
            'new_in_stock'    => $newInStock,
            'new_total_qty'   => $newTotalQty
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
