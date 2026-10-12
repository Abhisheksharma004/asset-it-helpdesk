<?php
/**
 * Scrap Parts & Components Management API
 * Handles database persistence in scrap_components and updates the components table
 * VIROS IT Asset & Helpdesk Portal
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auto-create scrap_components table if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='scrap_components' AND xtype='U')
BEGIN
    CREATE TABLE scrap_components (
        id INT IDENTITY(1,1) PRIMARY KEY,
        scrap_ref NVARCHAR(50) NOT NULL UNIQUE,
        component_id INT NULL,
        component_name NVARCHAR(200) NOT NULL,
        sku NVARCHAR(50) NULL,
        serial_no NVARCHAR(100) NULL,
        category NVARCHAR(100) NULL,
        host_asset NVARCHAR(255) NULL,
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
 * Generate Next Scrap Reference: SCR + MMYY + 3DIGITSERIAL (e.g. SCR1026001)
 */
function getNextComponentScrapRef($conn) {
    $now = new DateTime();
    $prefix = 'SCR' . $now->format('my'); // e.g. SCR1026

    $sql = "SELECT scrap_ref FROM scrap_components WHERE scrap_ref LIKE ? ORDER BY scrap_ref DESC";
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
        $cSql = "SELECT COUNT(*) as cnt FROM scrap_components";
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

    $sql = "SELECT id, scrap_ref, component_id, component_name, sku, serial_no, category, host_asset, quantity, 
                   reason, bin_location, disposal_status, salvage_value, authorized_by, notes, 
                   CONVERT(VARCHAR(10), created_at, 105) AS created_date
            FROM scrap_components 
            ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $qty = (int)($row['quantity'] ?? 1);
            $val = (float)($row['salvage_value'] ?? 0);
            $st  = trim($row['disposal_status'] ?? 'In Scrap Yard');

            $stats['total_scrapped_qty'] += $qty;
            $stats['total_salvage_val'] += $val;
            if ($st === 'In Scrap Yard') {
                $stats['in_yard_qty'] += $qty;
            } elseif ($st === 'E-Waste Recycled') {
                $stats['recycled_qty'] += $qty;
            }

            // Normalize status key for frontend filtering
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
                'component_id'    => $row['component_id'] ? (int)$row['component_id'] : null,
                'name'            => $row['component_name'],
                'sku'             => $row['sku'] ?? 'N/A',
                'serial'          => $row['serial_no'] ?? 'N/A',
                'category'        => $row['category'] ?? 'General Component',
                'host_asset'      => $row['host_asset'] ?? 'Standalone Spare',
                'qty'             => $qty,
                'reason'          => $row['reason'],
                'location'        => $row['bin_location'] ?? 'Bin C-01 (Component Yard)',
                'status'          => $statusKey,
                'status_text'     => $st,
                'salvage_val'     => $val,
                'date'            => $row['created_date'],
                'approver'        => $row['authorized_by'] ?? 'Abhishek Sharma',
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
// 2. ACTION: CREATE SCRAP ENTRY & UPDATE COMPONENTS TABLE
// -------------------------------------------------------------
if ($action === 'create') {
    $componentId = isset($_POST['component_id']) ? (int)$_POST['component_id'] : 0;
    $reason      = trim($_POST['reason'] ?? 'Hardware Defective');
    $binLocation = trim($_POST['bin_location'] ?? 'Bin C-01 (Component Yard)');
    $disposalSt  = trim($_POST['disposal_status'] ?? 'In Scrap Yard');
    $salvageVal  = isset($_POST['salvage_value']) ? (float)$_POST['salvage_value'] : 0.0;
    $authBy      = trim($_POST['authorized_by'] ?? ($_SESSION['user_name'] ?? 'Abhishek Sharma'));
    $notes       = trim($_POST['notes'] ?? '');
    $hostAsset   = trim($_POST['host_asset'] ?? '');

    if ($componentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a valid component to scrap.']);
        exit;
    }

    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select or provide a scrap reason / defect.']);
        exit;
    }

    // Step A: Fetch current component from database
    $compCheckSql = "SELECT c.id, c.name, c.sku, c.serial, c.category, c.status, c.installed_asset, c.location,
                            a.tag AS host_tag, a.name AS host_name
                     FROM components c
                     LEFT JOIN assets a ON (TRY_CAST(c.installed_asset AS INT) = a.id OR c.installed_asset = a.tag)
                     WHERE c.id = ?";
    $compStmt = sqlsrv_query($conn, $compCheckSql, [$componentId]);
    if ($compStmt === false || !($comp = sqlsrv_fetch_array($compStmt, SQLSRV_FETCH_ASSOC))) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Component record not found in database.']);
        exit;
    }
    sqlsrv_free_stmt($compStmt);

    // If host_asset not provided from frontend, format it from DB
    if (empty($hostAsset)) {
        if (!empty($comp['host_tag'])) {
            $hostAsset = $comp['host_tag'] . (!empty($comp['host_name']) ? ' (' . $comp['host_name'] . ')' : '');
        } elseif (!empty($comp['installed_asset'])) {
            $hostAsset = $comp['installed_asset'];
        } else {
            $hostAsset = 'Standalone Spare';
        }
    }

    $compName = $comp['name'] ?? 'Hardware Component';
    $sku      = $comp['sku'] ?? 'N/A';
    $serialNo = $comp['serial'] ?? 'N/A';
    $category = $comp['category'] ?? 'General Component';

    // Step B: Generate unique Scrap Reference Code (SCR+MMYY+serial, e.g. SCR1026001)
    $scrapRef = getNextComponentScrapRef($conn);

    // Step C: Run Database Transaction to Insert Scrap Entry and Update Components Table
    if (sqlsrv_begin_transaction($conn) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to begin database transaction.']);
        exit;
    }

    // 1. Insert into scrap_components
    $insertSql = "INSERT INTO scrap_components 
                  (scrap_ref, component_id, component_name, sku, serial_no, category, host_asset, quantity, reason, bin_location, disposal_status, salvage_value, authorized_by, notes, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
    $insertParams = [
        $scrapRef,
        $componentId,
        $compName,
        $sku,
        $serialNo,
        $category,
        $hostAsset,
        $reason,
        $binLocation,
        $disposalSt,
        $salvageVal,
        $authBy,
        $notes
    ];

    $inStmt = sqlsrv_query($conn, $insertSql, $insertParams);
    if ($inStmt === false) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to record scrap component entry.', 'errors' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($inStmt);

    // 2. Update components table:
    // Mark status as 'Scrapped', unlink installed_asset (NULL), and set storage location to the scrap bin
    $updateCompSql = "UPDATE components 
                      SET status = 'Scrapped', 
                          installed_asset = '', 
                          location = ?, 
                          updated_at = GETDATE() 
                      WHERE id = ?";
    $updateParams = [$binLocation, $componentId];
    $upStmt = sqlsrv_query($conn, $updateCompSql, $updateParams);

    if ($upStmt === false) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update component status in components table.', 'errors' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($upStmt);

    // 3. Update related asset's components_json if this component was installed in an asset
    $assetSearchSql = "SELECT id, components_json FROM assets WHERE components_json IS NOT NULL AND components_json != ''";
    $assetStmt = sqlsrv_query($conn, $assetSearchSql);
    if ($assetStmt !== false) {
        while ($aRow = sqlsrv_fetch_array($assetStmt, SQLSRV_FETCH_ASSOC)) {
            $cJson = $aRow['components_json'];
            if (!empty($cJson)) {
                $compList = json_decode($cJson, true);
                if (is_array($compList)) {
                    $jsonModified = false;
                    foreach ($compList as &$cItem) {
                        $cItemId = intval($cItem['component_id'] ?? ($cItem['id'] ?? 0));
                        $cItemSerial = trim(strval($cItem['serial'] ?? ''));
                        $cItemSku = trim(strval($cItem['sku'] ?? ($cItem['tag'] ?? '')));

                        if (($cItemId > 0 && $cItemId === $componentId) ||
                            (!empty($serialNo) && $serialNo !== 'N/A' && $cItemSerial === $serialNo) ||
                            (!empty($sku) && $sku !== 'N/A' && $cItemSku === $sku)) {
                            $cItem['status'] = 'Scrapped';
                            $jsonModified = true;
                        }
                    }
                    unset($cItem);

                    if ($jsonModified) {
                        $updAssetSql = "UPDATE assets SET components_json = ?, updated_at = GETDATE() WHERE id = ?";
                        $updAssetStmt = sqlsrv_query($conn, $updAssetSql, [json_encode($compList), $aRow['id']]);
                        if ($updAssetStmt !== false) {
                            sqlsrv_free_stmt($updAssetStmt);
                        }
                    }
                }
            }
        }
        sqlsrv_free_stmt($assetStmt);
    }

    // Commit Transaction
    sqlsrv_commit($conn);

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
        'message' => "Component scrap entry {$scrapRef} successfully recorded and components table updated to 'Scrapped' status!",
        'data'    => [
            'ref'             => $scrapRef,
            'component_id'    => $componentId,
            'name'            => $compName,
            'sku'             => $sku,
            'serial'          => $serialNo,
            'category'        => $category,
            'host_asset'      => $hostAsset,
            'qty'             => 1,
            'reason'          => $reason,
            'location'        => $binLocation,
            'status'          => $statusKey,
            'status_text'     => $disposalSt,
            'salvage_val'     => $salvageVal,
            'date'            => date('d M Y'),
            'approver'        => $authBy,
            'notes'           => $notes,
            'new_status'      => 'Scrapped'
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
