<?php
/**
 * Scrap & Decommissioned Assets Management API
 * Handles database persistence in scrap_assets and updates host assets & installed components
 * VIROS IT Asset & Helpdesk Portal
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auto-create scrap_assets table if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='scrap_assets' AND xtype='U')
BEGIN
    CREATE TABLE scrap_assets (
        id INT IDENTITY(1,1) PRIMARY KEY,
        scrap_ref NVARCHAR(50) NOT NULL UNIQUE,
        asset_id INT NULL,
        asset_tag NVARCHAR(100) NOT NULL,
        asset_name NVARCHAR(200) NOT NULL,
        category NVARCHAR(100) NULL,
        brand NVARCHAR(100) NULL,
        model NVARCHAR(100) NULL,
        serial_no NVARCHAR(100) NULL,
        origin_location NVARCHAR(150) NULL,
        reason NVARCHAR(255) NOT NULL,
        disposal_status NVARCHAR(100) NOT NULL DEFAULT 'In Scrap Yard',
        yard_location NVARCHAR(150) NULL,
        salvage_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        authorized_by NVARCHAR(150) NULL,
        notes NVARCHAR(MAX) NULL,
        components_disposition NVARCHAR(MAX) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";

if (isset($conn) && $conn !== false) {
    sqlsrv_query($conn, $tableSetupSql);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
        $action = $_POST['action'] ?? $action;
    }
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview']) && !isset($_POST['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

/**
 * Generate Next Scrap Reference: SCR + MMYY + 3DIGITSERIAL (e.g. SCR1026001)
 */
function getNextAssetScrapRef($conn) {
    $now = new DateTime();
    $prefix = 'SCR' . $now->format('my');

    $sql = "SELECT scrap_ref FROM scrap_assets WHERE scrap_ref LIKE ? ORDER BY scrap_ref DESC";
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
        $cSql = "SELECT COUNT(*) as cnt FROM scrap_assets";
        $cStmt = sqlsrv_query($conn, $cSql);
        if ($cStmt !== false && ($cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC))) {
            $maxNum = intval($cRow['cnt']);
            sqlsrv_free_stmt($cStmt);
        }
    }

    $nextNum = $maxNum + 1;
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}


// -------------------------------------------------------------
// ACTION: FETCH COMPONENTS FOR A GIVEN ASSET (AJAX Helper)
// -------------------------------------------------------------
if ($action === 'get_asset_components') {
    $assetId = trim(strval($_GET['asset_id'] ?? ($_POST['asset_id'] ?? '')));
    $assetTag = trim(strval($_GET['asset_tag'] ?? ($_POST['asset_tag'] ?? '')));

    $components = [];

    if (isset($conn) && $conn !== false && (!empty($assetId) || !empty($assetTag))) {
        // 1. From components table
        $cSql = "SELECT id, sku, serial, name, category, brand, model, specs, status, installed_asset 
                 FROM components 
                 WHERE (installed_asset = ? OR installed_asset = ?) 
                   AND (status IS NULL OR status != 'Scrapped')";
        $cStmt = sqlsrv_query($conn, $cSql, [$assetId, $assetTag]);
        if ($cStmt !== false) {
            while ($cr = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC)) {
                $components[] = [
                    'id'       => intval($cr['id']),
                    'name'     => $cr['name'] ?? 'Component',
                    'sku'      => $cr['sku'] ?? '',
                    'serial'   => $cr['serial'] ?? '',
                    'category' => $cr['category'] ?? 'Component',
                    'brand'    => $cr['brand'] ?? '',
                    'model'    => $cr['model'] ?? '',
                    'status'   => $cr['status'] ?? 'Installed'
                ];
            }
            sqlsrv_free_stmt($cStmt);
        }

        // 2. From assets.components_json
        $aSql = "SELECT components_json FROM assets WHERE id = ? OR tag = ?";
        $aStmt = sqlsrv_query($conn, $aSql, [$assetId, $assetTag]);
        if ($aStmt !== false && ($aRow = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC))) {
            $jsonStr = $aRow['components_json'];
            if (!empty($jsonStr)) {
                $decoded = json_decode($jsonStr, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $cId = intval($item['component_id'] ?? ($item['id'] ?? 0));
                        $cSerial = trim(strval($item['serial'] ?? ''));
                        
                        // Check if already in list
                        $exists = false;
                        foreach ($components as $existItem) {
                            if (($cId > 0 && $existItem['id'] === $cId) ||
                                (!empty($cSerial) && $existItem['serial'] === $cSerial)) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $components[] = [
                                'id'       => $cId,
                                'name'     => $item['name'] ?? 'Installed Component',
                                'sku'      => $item['sku'] ?? ($item['tag'] ?? ''),
                                'serial'   => $cSerial,
                                'category' => $item['category'] ?? 'Component',
                                'brand'    => $item['brand'] ?? '',
                                'model'    => $item['model'] ?? '',
                                'status'   => $item['status'] ?? 'Installed'
                            ];
                        }
                    }
                }
            }
            sqlsrv_free_stmt($aStmt);
        }
    }

    echo json_encode(['success' => true, 'components' => $components]);
    exit;
}

// -------------------------------------------------------------
// ACTION: CREATE SCRAP ASSET RECORD
// -------------------------------------------------------------
if ($action !== 'create' || $method !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action or request method.']);
    exit;
}

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

    $assetId     = intval($_POST['asset_id'] ?? 0);
    $assetTag    = trim($_POST['asset_tag'] ?? '');
    $assetName   = trim($_POST['asset_name'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $brand       = trim($_POST['brand'] ?? '');
    $model       = trim($_POST['model'] ?? '');
    $serialNo    = trim($_POST['serial_no'] ?? '');
    $originLoc   = trim($_POST['origin_location'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');
    $disposalSt  = trim($_POST['disposal_status'] ?? 'In Scrap Yard');
    $yardLoc     = trim($_POST['yard_location'] ?? 'Yard Bay A-01 (Asset Storage)');
    $salvageVal  = floatval($_POST['salvage_value'] ?? 0.00);
    $authBy      = trim($_POST['authorized_by'] ?? ($_SESSION['user_name'] ?? 'Abhishek Sharma'));
    $notes       = trim($_POST['notes'] ?? '');

    // Component disposition actions array: [{ component_id, serial, sku, name, action: 'scrap'|'salvage' }]
    $componentsDisp = $_POST['components_disposition'] ?? [];
    if (is_string($componentsDisp)) {
        $componentsDisp = json_decode($componentsDisp, true) ?: [];
    }

    if (empty($assetTag) && empty($assetName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Asset tag and asset name are required.']);
        exit;
    }

    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Condemnation / Scrap reason is required.']);
        exit;
    }

    // Validate that the asset is not currently issued/assigned to an employee
    $chkSql = "SELECT assigned_to, status FROM assets WHERE id = ? OR tag = ?";
    $chkStmt = sqlsrv_query($conn, $chkSql, [$assetId, $assetTag]);
    if ($chkStmt !== false) {
        if ($chkRow = sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($chkRow['assigned_to']) || in_array($chkRow['status'], ['In Use', 'Assigned', 'Allocated'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'This asset is currently issued to an employee (' . ($chkRow['assigned_to'] ?: $chkRow['status']) . '). Please process asset return before scrapping.']);
                exit;
            }
        }
        sqlsrv_free_stmt($chkStmt);
    }

    // Begin Transaction
    sqlsrv_begin_transaction($conn);

    $scrapRef = getNextAssetScrapRef($conn);
    $dispJson = !empty($componentsDisp) ? json_encode($componentsDisp, JSON_UNESCAPED_UNICODE) : null;

    // 1. Insert into scrap_assets
    $insertSql = "INSERT INTO scrap_assets (
        scrap_ref, asset_id, asset_tag, asset_name, category, brand, model, serial_no,
        origin_location, reason, disposal_status, yard_location, salvage_value, authorized_by, notes,
        components_disposition, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

    $insertParams = [
        $scrapRef,
        $assetId > 0 ? $assetId : null,
        $assetTag,
        $assetName,
        $category,
        $brand,
        $model,
        $serialNo,
        $originLoc,
        $reason,
        $disposalSt,
        $yardLoc,
        $salvageVal,
        $authBy,
        $notes,
        $dispJson
    ];

    $inStmt = sqlsrv_query($conn, $insertSql, $insertParams);
    if ($inStmt === false) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to record scrap asset entry.', 'errors' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($inStmt);

    // 2. Update assets table: Mark asset status as 'Scrapped', clear assignment, update condition and location
    $updateAssetSql = "UPDATE assets 
                       SET status = 'Scrapped', 
                           location = ?, 
                           assigned_to = NULL,
                           condition = 'Scrapped',
                           updated_at = GETDATE() 
                       WHERE id = ? OR tag = ?";
    $upAssetStmt = sqlsrv_query($conn, $updateAssetSql, [$yardLoc, $assetId, $assetTag]);
    if ($upAssetStmt !== false) {
        sqlsrv_free_stmt($upAssetStmt);
    }

    // 2b. Close active assignment in asset_assignments table if any
    $closeAssignSql = "UPDATE asset_assignments 
                       SET custody_status = 'Decommissioned', 
                           return_date = GETDATE(), 
                           return_condition = 'Scrapped' 
                       WHERE (asset_id = ? OR asset_tag = ?) 
                         AND custody_status = 'Active'";
    $closeAssignStmt = sqlsrv_query($conn, $closeAssignSql, [$assetId, $assetTag]);
    if ($closeAssignStmt !== false) {
        sqlsrv_free_stmt($closeAssignStmt);
    }

    // 3. Process each installed component disposition
    $updatedComponentsList = [];
    if (!empty($componentsDisp) && is_array($componentsDisp)) {
        // Auto-create scrap_components table if not exists for scrapping parts
        $scrCompTableSetup = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='scrap_components' AND xtype='U')
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
        sqlsrv_query($conn, $scrCompTableSetup);

        foreach ($componentsDisp as $disp) {
            $cId     = intval($disp['component_id'] ?? ($disp['id'] ?? 0));
            $cSerial = trim(strval($disp['serial'] ?? ''));
            $cName   = trim(strval($disp['name'] ?? 'Installed Component'));
            $cSku    = trim(strval($disp['sku'] ?? ''));
            $cCat    = trim(strval($disp['category'] ?? 'Component'));
            $cAction = trim(strval($disp['action'] ?? 'scrap')); // 'scrap' or 'salvage'

            if ($cAction === 'scrap') {
                // Action: Scrap with Asset
                // Mark component in components table as 'Scrapped'
                if ($cId > 0) {
                    sqlsrv_query($conn, "UPDATE components SET status = 'Scrapped', installed_asset = '', location = ?, updated_at = GETDATE() WHERE id = ?", [$yardLoc, $cId]);
                } elseif (!empty($cSerial)) {
                    sqlsrv_query($conn, "UPDATE components SET status = 'Scrapped', installed_asset = '', location = ?, updated_at = GETDATE() WHERE serial = ?", [$yardLoc, $cSerial]);
                }

                // Also insert entry in scrap_components for audit trail
                $nowObj = new DateTime();
                $cRef = 'SCR-P' . $nowObj->format('my') . rand(100, 999);
                $insCompScrapSql = "INSERT INTO scrap_components (
                    scrap_ref, component_id, component_name, sku, serial_no, category, host_asset,
                    quantity, reason, bin_location, disposal_status, salvage_value, authorized_by, notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 0.00, ?, ?, GETDATE(), GETDATE())";
                $hostAssetDesc = $assetTag . (!empty($assetName) ? ' (' . $assetName . ')' : '');
                $reasonDesc = 'Scrapped alongside host asset (' . $reason . ')';
                $notesDesc = 'Decommissioned together with parent asset ' . $assetTag . ' under reference ' . $scrapRef;
                sqlsrv_query($conn, $insCompScrapSql, [
                    $cRef,
                    $cId > 0 ? $cId : null,
                    $cName,
                    $cSku,
                    $cSerial,
                    $cCat,
                    $hostAssetDesc,
                    $reasonDesc,
                    $yardLoc,
                    $disposalSt,
                    $authBy,
                    $notesDesc
                ]);

                $updatedComponentsList[] = [
                    'component_id' => $cId,
                    'serial'       => $cSerial,
                    'status'       => 'Scrapped'
                ];
            } else {
                // Action: Salvage to Stock (Detach from asset & return to inventory)
                $salvageLoc = 'Depot Shelf (Salvaged Parts)';
                if ($cId > 0) {
                    sqlsrv_query($conn, "UPDATE components SET status = 'Available', installed_asset = '', location = ?, updated_at = GETDATE() WHERE id = ?", [$salvageLoc, $cId]);
                } elseif (!empty($cSerial)) {
                    sqlsrv_query($conn, "UPDATE components SET status = 'Available', installed_asset = '', location = ?, updated_at = GETDATE() WHERE serial = ?", [$salvageLoc, $cSerial]);
                }

                $updatedComponentsList[] = [
                    'component_id' => $cId,
                    'serial'       => $cSerial,
                    'status'       => 'Available'
                ];
            }
        }
    }

    // 4. Synchronize host asset's components_json
    $assetSearchSql = "SELECT components_json FROM assets WHERE id = ? OR tag = ?";
    $assetStmt = sqlsrv_query($conn, $assetSearchSql, [$assetId, $assetTag]);
    if ($assetStmt !== false && ($aRow = sqlsrv_fetch_array($assetStmt, SQLSRV_FETCH_ASSOC))) {
        $cJson = $aRow['components_json'];
        if (!empty($cJson)) {
            $compList = json_decode($cJson, true);
            if (is_array($compList)) {
                $jsonModified = false;
                foreach ($compList as &$cItem) {
                    $cItemId = intval($cItem['component_id'] ?? ($cItem['id'] ?? 0));
                    $cItemSerial = trim(strval($cItem['serial'] ?? ''));

                    foreach ($updatedComponentsList as $updComp) {
                        if (($cItemId > 0 && $cItemId === $updComp['component_id']) ||
                            (!empty($cItemSerial) && $cItemSerial === $updComp['serial'])) {
                            $cItem['status'] = $updComp['status'];
                            $jsonModified = true;
                            break;
                        }
                    }
                }
                unset($cItem);

                if ($jsonModified) {
                    $updAssetSql = "UPDATE assets SET components_json = ?, updated_at = GETDATE() WHERE id = ? OR tag = ?";
                    sqlsrv_query($conn, $updAssetSql, [json_encode($compList), $assetId, $assetTag]);
                }
            }
        }
        sqlsrv_free_stmt($assetStmt);
    }

    // Commit Transaction
    sqlsrv_commit($conn);

    // Normalize status key for frontend
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
        'message' => 'Hardware asset and attached components successfully processed and decommissioned.',
        'data' => [
            'id'             => $assetId,
            'ref'            => $scrapRef,
            'asset_tag'      => $assetTag,
            'name'           => $assetName,
            'category'       => $category,
            'brand'          => $brand,
            'model'          => $model,
            'serial'         => $serialNo,
            'location'       => $originLoc,
            'reason'         => $reason,
            'yard'           => $yardLoc,
            'status'         => $statusKey,
            'status_text'    => $disposalSt,
            'salvage_val'    => $salvageVal,
            'date'           => (new DateTime())->format('d M Y'),
            'approver'       => $authBy,
            'notes'          => $notes,
            'components_qty' => count($updatedComponentsList)
        ]
    ]);
    exit;
