<?php
/**
 * Asset Assignment & Custody Management API
 * VIROS IT Asset & Service Desk Portal
 * 
 * Manages equipment handover slips, batch assets and accessories assignment,
 * check-in/return to stock, and custodian transfers.
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection
require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// -----------------------------------------------------------------------------
// 1. Ensure Table Schema Exists (Self-Healing & Auto-Migrating)
// -----------------------------------------------------------------------------
$tableSetupSql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='asset_assignments' AND xtype='U')
BEGIN
    CREATE TABLE asset_assignments (
        id INT IDENTITY(1,1) PRIMARY KEY,
        slip_no NVARCHAR(50) NOT NULL UNIQUE,
        asset_id INT NULL,
        asset_tag NVARCHAR(50) NULL,
        asset_name NVARCHAR(200) NULL,
        category NVARCHAR(100) NULL,
        brand NVARCHAR(100) NULL,
        model NVARCHAR(150) NULL,
        serial NVARCHAR(100) NULL,
        specs NVARCHAR(255) NULL,
        employee_id INT NULL,
        employee_name NVARCHAR(150) NOT NULL,
        emp_code NVARCHAR(50) NULL,
        employee_email NVARCHAR(120) NULL,
        department NVARCHAR(150) NULL,
        designation NVARCHAR(100) NULL,
        location NVARCHAR(150) NULL,
        assigned_date DATE NOT NULL DEFAULT GETDATE(),
        allocation_type NVARCHAR(50) NOT NULL DEFAULT 'Permanent',
        expected_return DATE NULL,
        return_date DATE NULL,
        custody_status NVARCHAR(50) NOT NULL DEFAULT 'Active',
        condition NVARCHAR(50) NOT NULL DEFAULT 'Good',
        return_condition NVARCHAR(50) NULL,
        total_assets INT NOT NULL DEFAULT 0,
        total_accessories INT NOT NULL DEFAULT 0,
        assets_json NVARCHAR(MAX) NULL,
        accessories_json NVARCHAR(MAX) NULL,
        handover_by NVARCHAR(150) NULL DEFAULT 'IT Administrator',
        agreement_signed BIT NOT NULL DEFAULT 1,
        notes NVARCHAR(MAX) NULL,
        return_notes NVARCHAR(MAX) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END
ELSE
BEGIN
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_assignments' AND COLUMN_NAME = 'assets_json')
    BEGIN
        ALTER TABLE asset_assignments ADD assets_json NVARCHAR(MAX) NULL;
    END

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_assignments' AND COLUMN_NAME = 'total_assets')
    BEGIN
        ALTER TABLE asset_assignments ADD total_assets INT NOT NULL DEFAULT 0;
    END

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_assignments' AND COLUMN_NAME = 'total_accessories')
    BEGIN
        ALTER TABLE asset_assignments ADD total_accessories INT NOT NULL DEFAULT 0;
    END

    ALTER TABLE asset_assignments ALTER COLUMN asset_id INT NULL;
    ALTER TABLE asset_assignments ALTER COLUMN asset_tag NVARCHAR(50) NULL;
    ALTER TABLE asset_assignments ALTER COLUMN asset_name NVARCHAR(200) NULL;
END";

sqlsrv_query($conn, $tableSetupSql);

// -----------------------------------------------------------------------------
// 2. Read Request
// -----------------------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_POST['action'] ?? $input['action'] ?? ($_GET['action'] ?? 'get');

// Helper: Generate next unique slip number (e.g. SLIP-2026-0001)
function getNextSlipNumber($conn) {
    $year = date('Y');
    $sql = "SELECT ISNULL(MAX(id), 0) + 1 AS next_id FROM asset_assignments";
    $stmt = sqlsrv_query($conn, $sql);
    $nextId = 1;
    if ($stmt !== false && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $nextId = intval($row['next_id']);
        sqlsrv_free_stmt($stmt);
    }
    return sprintf("SLIP-%s-%04d", $year, $nextId);
}

// -----------------------------------------------------------------------------
// ACTION: GET / LIST (Retrieve allocations per handover slip)
// -----------------------------------------------------------------------------
if ($action === 'get' || $action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $tab    = trim($_GET['tab'] ?? 'all');
    $dept   = trim($_GET['dept'] ?? 'all');
    $type   = trim($_GET['type'] ?? 'all');
    $status = trim($_GET['status'] ?? 'all');

    $where = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(slip_no LIKE ? OR employee_name LIKE ? OR emp_code LIKE ? OR department LIKE ? OR assets_json LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($tab === 'permanent') {
        $where[] = "allocation_type = 'Permanent'";
    } elseif ($tab === 'temporary') {
        $where[] = "allocation_type = 'Temporary Loaner'";
    } elseif ($tab === 'remote') {
        $where[] = "allocation_type = 'Remote / WFH'";
    } elseif ($tab === 'due_soon') {
        $where[] = "(custody_status = 'Due Soon' OR custody_status = 'Overdue')";
    }

    if ($dept !== '' && $dept !== 'all') {
        $where[] = "department = ?";
        $params[] = $dept;
    }

    if ($type !== '' && $type !== 'all') {
        $where[] = "allocation_type = ?";
        $params[] = $type;
    }

    if ($status !== '' && $status !== 'all') {
        $where[] = "custody_status = ?";
        $params[] = $status;
    }

    $whereClause = implode(' AND ', $where);
    $query = "SELECT id, slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
                     employee_id, employee_name, emp_code, employee_email, department, designation, location,
                     CONVERT(VARCHAR(10), assigned_date, 120) AS assigned_date,
                     allocation_type,
                     CONVERT(VARCHAR(10), expected_return, 120) AS expected_return,
                     CONVERT(VARCHAR(10), return_date, 120) AS return_date,
                     custody_status, condition, return_condition,
                     total_assets, total_accessories, assets_json, accessories_json,
                     handover_by, agreement_signed, notes, return_notes,
                     CONVERT(VARCHAR(19), created_at, 120) AS created_at
              FROM asset_assignments
              WHERE $whereClause
              ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $query, $params);
    $items = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Decode assets
            $assetsList = [];
            if (!empty($row['assets_json'])) {
                $decodedAssets = json_decode($row['assets_json'], true);
                if (is_array($decodedAssets)) {
                    $assetsList = $decodedAssets;
                }
            }
            // Fallback for single asset
            if (empty($assetsList) && !empty($row['asset_name'])) {
                $assetsList[] = [
                    'id'        => intval($row['asset_id']),
                    'tag'       => $row['asset_tag'],
                    'name'      => $row['asset_name'],
                    'category'  => $row['category'] ?? 'Hardware',
                    'brand'     => $row['brand'] ?? '',
                    'model'     => $row['model'] ?? '',
                    'serial'    => $row['serial'] ?? '—',
                    'specs'     => $row['specs'] ?? '',
                    'condition' => $row['condition'] ?? 'Good'
                ];
            }

            // Decode accessories
            $accList = [];
            if (!empty($row['accessories_json'])) {
                $decodedAcc = json_decode($row['accessories_json'], true);
                if (is_array($decodedAcc)) {
                    $accList = $decodedAcc;
                }
            }

            $totalAssets = intval($row['total_assets']);
            if ($totalAssets <= 0) {
                $totalAssets = count($assetsList);
            }

            $totalAccessories = intval($row['total_accessories']);
            if ($totalAccessories <= 0) {
                $totalAccCount = 0;
                foreach ($accList as $ac) {
                    $totalAccCount += isset($ac['qty']) ? intval($ac['qty']) : 1;
                }
                $totalAccessories = $totalAccCount;
            }

            $items[] = [
                'id'                => intval($row['id']),
                'slip_no'           => $row['slip_no'],
                'employee_id'       => $row['employee_id'] ? intval($row['employee_id']) : null,
                'employee_name'     => $row['employee_name'],
                'emp_code'          => $row['emp_code'] ?? 'EMP-0000',
                'employee_email'    => $row['employee_email'] ?? '',
                'department'        => $row['department'] ?? 'General',
                'designation'       => $row['designation'] ?? 'Staff',
                'location'          => $row['location'] ?? 'Corporate HQ',
                'assigned_date'     => $row['assigned_date'],
                'allocation_type'   => $row['allocation_type'],
                'expected_return'   => $row['expected_return'],
                'return_date'       => $row['return_date'],
                'custody_status'    => $row['custody_status'],
                'condition'         => $row['condition'] ?? 'Good',
                'total_assets'      => $totalAssets,
                'total_accessories' => $totalAccessories,
                'assets'            => $assetsList,
                'accessories'       => $accList,
                'handover_by'       => $row['handover_by'] ?? 'IT Administrator',
                'agreement_signed'  => (bool)$row['agreement_signed'],
                'notes'             => $row['notes'] ?? '',
                'return_notes'      => $row['return_notes'] ?? ''
            ];
        }
        sqlsrv_free_stmt($stmt);
    }

    // Live Metrics
    $stats = [
        'total'             => 0,
        'permanent'         => 0,
        'temporary'         => 0,
        'remote'            => 0,
        'due_soon'          => 0,
        'deployed_assets'   => 0,
        'deployed_acc'      => 0,
        'available_assets'  => 0
    ];

    $statQuery = "SELECT 
                    COUNT(*) AS total,
                    ISNULL(SUM(CASE WHEN allocation_type = 'Permanent' AND custody_status != 'Returned' THEN 1 ELSE 0 END), 0) AS permanent,
                    ISNULL(SUM(CASE WHEN allocation_type = 'Temporary Loaner' AND custody_status != 'Returned' THEN 1 ELSE 0 END), 0) AS temporary,
                    ISNULL(SUM(CASE WHEN allocation_type = 'Remote / WFH' AND custody_status != 'Returned' THEN 1 ELSE 0 END), 0) AS remote,
                    ISNULL(SUM(CASE WHEN (custody_status = 'Due Soon' OR custody_status = 'Overdue') THEN 1 ELSE 0 END), 0) AS due_soon,
                    ISNULL(SUM(CASE WHEN custody_status != 'Returned' THEN total_assets ELSE 0 END), 0) AS deployed_assets,
                    ISNULL(SUM(CASE WHEN custody_status != 'Returned' THEN total_accessories ELSE 0 END), 0) AS deployed_acc
                  FROM asset_assignments
                  WHERE custody_status != 'Returned'";
    $statStmt = sqlsrv_query($conn, $statQuery);
    if ($statStmt !== false && ($sRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total']            = intval($sRow['total']);
        $stats['permanent']        = intval($sRow['permanent']);
        $stats['temporary']        = intval($sRow['temporary']);
        $stats['remote']           = intval($sRow['remote']);
        $stats['due_soon']         = intval($sRow['due_soon']);
        $stats['deployed_assets']  = intval($sRow['deployed_assets']);
        $stats['deployed_acc']     = intval($sRow['deployed_acc']);
        sqlsrv_free_stmt($statStmt);
    }

    // Available assets count
    $availStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS avail FROM assets WHERE status = 'Available'");
    if ($availStmt !== false && ($avRow = sqlsrv_fetch_array($availStmt, SQLSRV_FETCH_ASSOC))) {
        $stats['available_assets'] = intval($avRow['avail']);
        sqlsrv_free_stmt($availStmt);
    }

    echo json_encode([
        'success'   => true,
        'count'     => count($items),
        'data'      => $items,
        'stats'     => $stats,
        'next_slip' => getNextSlipNumber($conn)
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION: ASSIGN / CREATE (Store Handover Slip with multiple Assets & Accessories)
// -----------------------------------------------------------------------------
if ($action === 'assign' || $action === 'create') {
    $empId           = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : (isset($input['employee_id']) ? intval($input['employee_id']) : 0);
    $empName         = trim($_POST['employee_name'] ?? $input['employee_name'] ?? '');
    $empCode         = trim($_POST['emp_code'] ?? $input['emp_code'] ?? '');
    $empEmail        = trim($_POST['employee_email'] ?? $input['employee_email'] ?? '');
    $empDept         = trim($_POST['department'] ?? $input['department'] ?? '');
    $empDesig        = trim($_POST['designation'] ?? $input['designation'] ?? '');
    $location        = trim($_POST['location'] ?? $input['location'] ?? 'Corporate HQ - Mumbai');
    $allocType       = trim($_POST['allocation_type'] ?? $input['allocation_type'] ?? 'Permanent');
    $handoverDate    = trim($_POST['handover_date'] ?? $input['handover_date'] ?? date('Y-m-d'));
    $expectedReturn  = trim($_POST['expected_return'] ?? $input['expected_return'] ?? '');
    $defaultCondition= trim($_POST['condition'] ?? $input['condition'] ?? 'Brand New');
    $notes           = trim($_POST['notes'] ?? $input['notes'] ?? '');
    $handoverBy      = trim($_POST['handover_by'] ?? $input['handover_by'] ?? 'Abhishek Sharma (IT Admin)');
    $agreementSigned = isset($_POST['agreement_signed']) ? intval($_POST['agreement_signed']) : (isset($input['agreement_signed']) ? intval($input['agreement_signed']) : 1);

    if (empty($empName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select an employee / custodian.']);
        exit;
    }

    // Extract Assets array
    $assets = [];
    if (!empty($_POST['assets'])) {
        $assets = is_array($_POST['assets']) ? $_POST['assets'] : (json_decode($_POST['assets'], true) ?? []);
    } elseif (!empty($input['assets'])) {
        $assets = is_array($input['assets']) ? $input['assets'] : (json_decode($input['assets'], true) ?? []);
    }

    // Extract Accessories array
    $accessories = [];
    if (!empty($_POST['accessories'])) {
        $accessories = is_array($_POST['accessories']) ? $_POST['accessories'] : (json_decode($_POST['accessories'], true) ?? []);
    } elseif (!empty($input['accessories'])) {
        $accessories = is_array($input['accessories']) ? $input['accessories'] : (json_decode($input['accessories'], true) ?? []);
    }

    if (empty($assets) && empty($accessories)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select at least one asset or accessory to allocate.']);
        exit;
    }

    // Begin SQL Transaction
    sqlsrv_begin_transaction($conn);

    $detailedAssets = [];
    $assetIdsToUpdate = [];

    foreach ($assets as $assetItem) {
        $assetId = intval($assetItem['id'] ?? 0);
        if ($assetId <= 0) continue;

        $aStmt = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, processor, ram, storage, condition FROM assets WHERE id = ?", [$assetId]);
        if ($aStmt !== false && ($dbAsset = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC))) {
            $detailedAssets[] = [
                'id'        => intval($dbAsset['id']),
                'tag'       => $dbAsset['tag'],
                'name'      => $dbAsset['name'],
                'category'  => $dbAsset['category'] ?? 'Hardware',
                'brand'     => $dbAsset['brand'] ?? '',
                'model'     => $dbAsset['model'] ?? '',
                'serial'    => $dbAsset['serial'] ?? '—',
                'specs'     => trim(($dbAsset['processor'] ?? '') . ' • ' . ($dbAsset['ram'] ?? '') . ' • ' . ($dbAsset['storage'] ?? '')),
                'condition' => !empty($assetItem['condition']) ? $assetItem['condition'] : ($defaultCondition ?: 'Good')
            ];
            $assetIdsToUpdate[] = $assetId;
            sqlsrv_free_stmt($aStmt);
        }
    }

    // Format Accessories & Deduct stock
    $detailedAccessories = [];
    $totalAccCount = 0;

    foreach ($accessories as $accItem) {
        $accId = intval($accItem['id'] ?? 0);
        $qty = max(1, intval($accItem['qty'] ?? 1));

        if ($accId > 0) {
            $acStmt = sqlsrv_query($conn, "SELECT id, sku, name, category, brand, in_stock FROM accessories WHERE id = ?", [$accId]);
            if ($acStmt !== false && ($dbAc = sqlsrv_fetch_array($acStmt, SQLSRV_FETCH_ASSOC))) {
                $detailedAccessories[] = [
                    'id'       => intval($dbAc['id']),
                    'sku'      => $dbAc['sku'],
                    'name'     => $dbAc['name'],
                    'category' => $dbAc['category'] ?? 'Accessories',
                    'brand'    => $dbAc['brand'] ?? '',
                    'qty'      => $qty
                ];
                $totalAccCount += $qty;
                // Deduct stock in accessories table
                sqlsrv_query($conn, "UPDATE accessories 
                                     SET in_stock = CASE WHEN in_stock >= ? THEN in_stock - ? ELSE 0 END, 
                                         deployed = deployed + ?, 
                                         updated_at = GETDATE() 
                                     WHERE id = ?", [$qty, $qty, $qty, $accId]);
                sqlsrv_free_stmt($acStmt);
                continue;
            }
        }

        // Custom/text accessory
        if (!empty($accItem['name'])) {
            $detailedAccessories[] = [
                'id'       => $accId,
                'sku'      => $accItem['sku'] ?? 'ACC-GEN',
                'name'     => $accItem['name'],
                'category' => $accItem['category'] ?? 'Accessories',
                'brand'    => '',
                'qty'      => $qty
            ];
            $totalAccCount += $qty;
        }
    }

    $totalAssetsCount = count($detailedAssets);

    // Primary device representation for indexing
    $primaryAsset = !empty($detailedAssets) ? $detailedAssets[0] : null;
    $primaryAssetId = $primaryAsset ? $primaryAsset['id'] : null;
    $primaryAssetTag = $primaryAsset ? $primaryAsset['tag'] : null;
    $primaryAssetName = $primaryAsset ? $primaryAsset['name'] : (count($detailedAccessories) > 0 ? ($detailedAccessories[0]['name'] . ' Package') : 'Accessories Bundle');
    $primaryCategory = $primaryAsset ? $primaryAsset['category'] : 'Peripherals & Accessories';
    $primaryBrand = $primaryAsset ? $primaryAsset['brand'] : '';
    $primaryModel = $primaryAsset ? $primaryAsset['model'] : '';
    $primarySerial = $primaryAsset ? $primaryAsset['serial'] : '—';
    $primarySpecs = $primaryAsset ? $primaryAsset['specs'] : '';
    $primaryCondition = $primaryAsset ? $primaryAsset['condition'] : $defaultCondition;

    $custodyStatus = 'Active';
    if ($allocType === 'Temporary Loaner' || $allocType === 'Project Deployment') {
        $custodyStatus = 'Due Soon';
    }
    $expReturnVal = !empty($expectedReturn) ? $expectedReturn : null;

    $slipNo = getNextSlipNumber($conn);
    $assetsJson = json_encode($detailedAssets);
    $accJson = json_encode($detailedAccessories);

    $insertSql = "INSERT INTO asset_assignments (
                    slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
                    employee_id, employee_name, emp_code, employee_email, department, designation, location,
                    assigned_date, allocation_type, expected_return, custody_status, condition,
                    total_assets, total_accessories, assets_json, accessories_json,
                    handover_by, agreement_signed, notes
                  )
                  OUTPUT INSERTED.id
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $insertParams = [
        $slipNo, $primaryAssetId, $primaryAssetTag, $primaryAssetName, $primaryCategory, $primaryBrand, $primaryModel, $primarySerial, $primarySpecs,
        $empId > 0 ? $empId : null, $empName, $empCode, $empEmail, $empDept, $empDesig, $location,
        $handoverDate, $allocType, $expReturnVal, $custodyStatus, $primaryCondition,
        $totalAssetsCount, $totalAccCount, $assetsJson, $accJson,
        $handoverBy, $agreementSigned, $notes
    ];

    $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
    $newAllocId = 0;
    if ($insertStmt !== false && ($insRow = sqlsrv_fetch_array($insertStmt, SQLSRV_FETCH_ASSOC))) {
        $newAllocId = intval($insRow['id']);
        sqlsrv_free_stmt($insertStmt);
    } else {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create assignment record.']);
        exit;
    }

    // Update all assigned assets in assets table to 'In Use'
    foreach ($assetIdsToUpdate as $aId) {
        sqlsrv_query($conn, "UPDATE assets 
                             SET status = 'In Use', 
                                 assigned_to = ?, 
                                 department = ?, 
                                 location = ?, 
                                 condition = ?, 
                                 updated_at = GETDATE() 
                             WHERE id = ?", [$empName, $empDept, $location, $primaryCondition, $aId]);
    }

    sqlsrv_commit($conn);

    $createdRecord = [
        'id'                => $newAllocId,
        'slip_no'           => $slipNo,
        'employee_id'       => $empId > 0 ? $empId : null,
        'employee_name'     => $empName,
        'emp_code'          => $empCode,
        'employee_email'    => $empEmail,
        'department'        => $empDept,
        'designation'       => $empDesig,
        'location'          => $location,
        'assigned_date'     => $handoverDate,
        'allocation_type'   => $allocType,
        'expected_return'   => $expReturnVal,
        'custody_status'    => $custodyStatus,
        'condition'         => $primaryCondition,
        'total_assets'      => $totalAssetsCount,
        'total_accessories' => $totalAccCount,
        'assets'            => $detailedAssets,
        'accessories'       => $detailedAccessories,
        'handover_by'       => $handoverBy,
        'agreement_signed'  => (bool)$agreementSigned,
        'notes'             => $notes
    ];

    echo json_encode([
        'success'       => true,
        'message'       => "Equipment successfully allocated to {$empName} under slip {$slipNo}.",
        'data'          => $createdRecord,
        'new_id'        => $newAllocId,
        'slip_no'       => $slipNo
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION: RETURN (Process asset return, check-in equipment, restore inventory)
// -----------------------------------------------------------------------------
if ($action === 'return') {
    $allocId = isset($_POST['alloc_id']) ? intval($_POST['alloc_id']) : (isset($input['alloc_id']) ? intval($input['alloc_id']) : 0);
    $returnDate = trim($_POST['return_date'] ?? $input['return_date'] ?? date('Y-m-d'));
    $returnCondition = trim($_POST['return_condition'] ?? $input['return_condition'] ?? 'Good');
    $storageLocation = trim($_POST['storage_location'] ?? $input['storage_location'] ?? 'Storage Depot');
    $returnNotes = trim($_POST['return_notes'] ?? $input['return_notes'] ?? 'Equipment checked-in and verified.');

    if ($allocId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid allocation ID.']);
        exit;
    }

    $stmt = sqlsrv_query($conn, "SELECT id, slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs, assets_json, accessories_json, custody_status FROM asset_assignments WHERE id = ?", [$allocId]);
    $alloc = ($stmt !== false) ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    if (!$alloc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Allocation record not found.']);
        exit;
    }

    sqlsrv_begin_transaction($conn);

    // 1. Mark custody_status = 'Returned'
    $updAlloc = sqlsrv_query($conn, "UPDATE asset_assignments 
                                     SET custody_status = 'Returned',
                                         return_date = ?,
                                         return_condition = ?,
                                         return_notes = ?,
                                         updated_at = GETDATE()
                                     WHERE id = ?", [$returnDate, $returnCondition, $returnNotes, $allocId]);

    // 2. Restore all assigned assets to 'Available' in assets table
    $assetsList = [];
    if (!empty($alloc['assets_json'])) {
        $assetsList = json_decode($alloc['assets_json'], true) ?: [];
    }
    if (empty($assetsList) && !empty($alloc['asset_id'])) {
        $assetsList[] = [
            'id'       => intval($alloc['asset_id']),
            'tag'      => $alloc['asset_tag'] ?? '',
            'name'     => $alloc['asset_name'] ?? '',
            'category' => $alloc['category'] ?? '',
            'brand'    => $alloc['brand'] ?? '',
            'model'    => $alloc['model'] ?? '',
            'serial'   => $alloc['serial'] ?? '',
            'specs'    => $alloc['specs'] ?? ''
        ];
    }

    $restoredAssets = [];
    foreach ($assetsList as $ast) {
        $aId = intval($ast['id'] ?? 0);
        if ($aId > 0) {
            sqlsrv_query($conn, "UPDATE assets 
                                 SET status = 'Available', 
                                     condition = ?, 
                                     location = ?,
                                     assigned_to = NULL, 
                                     department = NULL,
                                     updated_at = GETDATE() 
                                 WHERE id = ?", [$returnCondition, $storageLocation, $aId]);

            // Query the restored asset's latest attributes
            $fStmt = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, location, specs = (ISNULL(processor,'') + ' • ' + ISNULL(ram,'')) FROM assets WHERE id = ?", [$aId]);
            if ($fStmt !== false && ($aRow = sqlsrv_fetch_array($fStmt, SQLSRV_FETCH_ASSOC))) {
                $restoredAssets[] = [
                    'id'        => intval($aRow['id']),
                    'tag'       => $aRow['tag'],
                    'name'      => $aRow['name'],
                    'category'  => $aRow['category'],
                    'brand'     => $aRow['brand'],
                    'model'     => $aRow['model'] ?? '',
                    'serial'    => $aRow['serial'] ?? '',
                    'condition' => $aRow['condition'] ?? $returnCondition,
                    'location'  => $aRow['location'] ?? $storageLocation,
                    'specs'     => trim($aRow['specs'] ?? '', " •")
                ];
                sqlsrv_free_stmt($fStmt);
            }
        }
    }

    // 3. Restore all accessories stock
    $restoredAccessories = [];
    if (!empty($alloc['accessories_json'])) {
        $accs = json_decode($alloc['accessories_json'], true) ?: [];
        foreach ($accs as $ac) {
            $qty = isset($ac['qty']) ? intval($ac['qty']) : 1;
            $acId = intval($ac['id'] ?? 0);
            if ($acId > 0) {
                sqlsrv_query($conn, "UPDATE accessories 
                                     SET in_stock = in_stock + ?, 
                                         deployed = CASE WHEN deployed >= ? THEN deployed - ? ELSE 0 END, 
                                         updated_at = GETDATE() 
                                     WHERE id = ?", [$qty, $qty, $qty, $acId]);

                $acStmt = sqlsrv_query($conn, "SELECT id, sku, name, category, brand, model, in_stock, location FROM accessories WHERE id = ?", [$acId]);
                if ($acStmt !== false && ($acRow = sqlsrv_fetch_array($acStmt, SQLSRV_FETCH_ASSOC))) {
                    $restoredAccessories[] = [
                        'id'       => intval($acRow['id']),
                        'sku'      => $acRow['sku'],
                        'name'     => $acRow['name'],
                        'category' => $acRow['category'],
                        'brand'    => $acRow['brand'] ?? '',
                        'model'    => $acRow['model'] ?? '',
                        'in_stock' => intval($acRow['in_stock']),
                        'location' => $acRow['location'] ?? ''
                    ];
                    sqlsrv_free_stmt($acStmt);
                }
            }
        }
    }

    sqlsrv_commit($conn);

    // Calculate updated available stock count
    $countAvail = 0;
    $cStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM assets WHERE status = 'Available'");
    if ($cStmt !== false && ($cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC))) {
        $countAvail = intval($cRow['total']);
        sqlsrv_free_stmt($cStmt);
    }

    $msg = count($restoredAssets) > 1 
        ? count($restoredAssets) . ' assets returned successfully and added back to available stock.' 
        : 'Asset returned successfully and added back to available stock.';

    echo json_encode([
        'success'               => true,
        'message'               => $msg,
        'alloc_id'              => $allocId,
        'return_date'           => $returnDate,
        'return_condition'      => $returnCondition,
        'restored_assets'       => $restoredAssets,
        'restored_accessories'  => $restoredAccessories,
        'available_stock_count' => $countAvail
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION: GET AVAILABLE ASSETS (Fetch current in-stock assets)
// -----------------------------------------------------------------------------
if ($action === 'get_available_assets') {
    $availList = [];
    $availStmt = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, location, specs = (ISNULL(processor,'') + ' • ' + ISNULL(ram,'')) 
                                      FROM assets 
                                      WHERE status = 'Available' 
                                      ORDER BY id DESC");
    if ($availStmt !== false) {
        while ($a = sqlsrv_fetch_array($availStmt, SQLSRV_FETCH_ASSOC)) {
            $availList[] = [
                'id'        => intval($a['id']),
                'tag'       => $a['tag'],
                'name'      => $a['name'],
                'category'  => $a['category'],
                'brand'     => $a['brand'],
                'model'     => $a['model'] ?? '',
                'serial'    => $a['serial'] ?? '',
                'condition' => $a['condition'] ?? 'Good',
                'location'  => $a['location'] ?? 'Storage Depot',
                'specs'     => trim($a['specs'] ?? '', " •")
            ];
        }
        sqlsrv_free_stmt($availStmt);
    }
    echo json_encode(['success' => true, 'count' => count($availList), 'data' => $availList]);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION: TRANSFER (Transfer equipment custody to another employee)
// -----------------------------------------------------------------------------
if ($action === 'transfer') {
    $allocId = isset($_POST['alloc_id']) ? intval($_POST['alloc_id']) : (isset($input['alloc_id']) ? intval($input['alloc_id']) : 0);
    $newEmpId = isset($_POST['new_employee_id']) ? intval($_POST['new_employee_id']) : (isset($input['new_employee_id']) ? intval($input['new_employee_id']) : 0);
    $newEmpName = trim($_POST['new_employee_name'] ?? $input['new_employee_name'] ?? '');
    $newEmpCode = trim($_POST['new_emp_code'] ?? $input['new_emp_code'] ?? '');
    $newDept = trim($_POST['new_dept'] ?? $input['new_dept'] ?? '');
    $newLocation = trim($_POST['new_location'] ?? $input['new_location'] ?? '');
    $transferDate = trim($_POST['transfer_date'] ?? $input['transfer_date'] ?? date('Y-m-d'));
    $transferNotes = trim($_POST['transfer_notes'] ?? $input['transfer_notes'] ?? '');

    if ($allocId <= 0 || empty($newEmpName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a valid new custodian.']);
        exit;
    }

    $stmt = sqlsrv_query($conn, "SELECT * FROM asset_assignments WHERE id = ?", [$allocId]);
    $prev = ($stmt !== false) ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    if (!$prev) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Allocation record not found.']);
        exit;
    }

    sqlsrv_begin_transaction($conn);

    // 1. Mark previous record as Transferred
    sqlsrv_query($conn, "UPDATE asset_assignments 
                         SET custody_status = 'Transferred',
                             return_date = ?,
                             notes = ISNULL(notes, '') + ' | Transferred to ' + ?,
                             updated_at = GETDATE()
                         WHERE id = ?", [$transferDate, $newEmpName, $allocId]);

    // 2. Insert new slip record for new employee
    $newSlip = getNextSlipNumber($conn);
    $insSql = "INSERT INTO asset_assignments (
                slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
                employee_id, employee_name, emp_code, employee_email, department, designation, location,
                assigned_date, allocation_type, expected_return, custody_status, condition,
                total_assets, total_accessories, assets_json, accessories_json,
                handover_by, agreement_signed, notes
               )
               OUTPUT INSERTED.id
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insParams = [
        $newSlip, $prev['asset_id'], $prev['asset_tag'], $prev['asset_name'], $prev['category'], $prev['brand'], $prev['model'], $prev['serial'], $prev['specs'],
        $newEmpId > 0 ? $newEmpId : null, $newEmpName, $newEmpCode, '', $newDept, 'Team Member', $newLocation ?: $prev['location'],
        $transferDate, $prev['allocation_type'], $prev['expected_return'], 'Active', $prev['condition'],
        $prev['total_assets'], $prev['total_accessories'], $prev['assets_json'], $prev['accessories_json'],
        'IT Administrator (Transfer)', 1, $transferNotes ?: ('Transferred custody from ' . $prev['employee_name'])
    ];

    $insStmt = sqlsrv_query($conn, $insSql, $insParams);
    $newId = 0;
    if ($insStmt !== false && ($insR = sqlsrv_fetch_array($insStmt, SQLSRV_FETCH_ASSOC))) {
        $newId = intval($insR['id']);
        sqlsrv_free_stmt($insStmt);
    }

    // 3. Update assets table with new assignee
    $assetsList = [];
    if (!empty($prev['assets_json'])) {
        $assetsList = json_decode($prev['assets_json'], true) ?: [];
    }
    if (empty($assetsList) && !empty($prev['asset_id'])) {
        $assetsList[] = ['id' => intval($prev['asset_id'])];
    }

    foreach ($assetsList as $ast) {
        $aId = intval($ast['id'] ?? 0);
        if ($aId > 0) {
            sqlsrv_query($conn, "UPDATE assets 
                                 SET assigned_to = ?, 
                                     department = ?, 
                                     location = ?, 
                                     updated_at = GETDATE() 
                                 WHERE id = ?", [$newEmpName, $newDept, $newLocation ?: $prev['location'], $aId]);
        }
    }

    sqlsrv_commit($conn);

    echo json_encode([
        'success'  => true,
        'message'  => "Custody transferred successfully to {$newEmpName}.",
        'new_id'   => $newId,
        'new_slip' => $newSlip
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
