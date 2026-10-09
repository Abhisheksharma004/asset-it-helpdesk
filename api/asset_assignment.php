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
        transferred_to NVARCHAR(150) NULL,
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

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_assignments' AND COLUMN_NAME = 'transferred_to')
    BEGIN
        ALTER TABLE asset_assignments ADD transferred_to NVARCHAR(150) NULL;
    END

    ALTER TABLE asset_assignments ALTER COLUMN asset_id INT NULL;
    ALTER TABLE asset_assignments ALTER COLUMN asset_tag NVARCHAR(50) NULL;
    ALTER TABLE asset_assignments ALTER COLUMN asset_name NVARCHAR(200) NULL;
END";

sqlsrv_query($conn, $tableSetupSql);

// Ensure asset_returns table exists
$returnsTableSetupSql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='asset_returns' AND xtype='U')
BEGIN
    CREATE TABLE asset_returns (
        id INT IDENTITY(1,1) PRIMARY KEY,
        return_slip_no NVARCHAR(50) NOT NULL UNIQUE,
        assignment_id INT NULL,
        original_slip_no NVARCHAR(50) NOT NULL,
        employee_id INT NULL,
        employee_name NVARCHAR(150) NOT NULL,
        emp_code NVARCHAR(50) NULL,
        department NVARCHAR(150) NULL,
        designation NVARCHAR(100) NULL,
        allocation_type NVARCHAR(50) NULL,
        assigned_date DATE NULL,
        return_date DATE NOT NULL,
        storage_location NVARCHAR(150) NOT NULL,
        return_condition NVARCHAR(50) NOT NULL DEFAULT 'Good',
        restock_status NVARCHAR(50) NOT NULL DEFAULT 'Restocked to Stock',
        diag_power_boot BIT NOT NULL DEFAULT 1,
        diag_display BIT NOT NULL DEFAULT 1,
        diag_battery BIT NOT NULL DEFAULT 1,
        diag_keyboard_trackpad BIT NOT NULL DEFAULT 1,
        diag_ports_audio BIT NOT NULL DEFAULT 1,
        diag_connectivity BIT NOT NULL DEFAULT 1,
        diag_storage_wiped BIT NOT NULL DEFAULT 1,
        diag_locks_removed BIT NOT NULL DEFAULT 1,
        diag_data_backup BIT NOT NULL DEFAULT 1,
        diag_body_hinges BIT NOT NULL DEFAULT 1,
        diag_oem_charger BIT NOT NULL DEFAULT 1,
        diag_asset_tag BIT NOT NULL DEFAULT 1,
        diag_pass_count INT NOT NULL DEFAULT 12,
        diag_total_count INT NOT NULL DEFAULT 12,
        diag_checklist_json NVARCHAR(MAX) NULL,
        total_returned_assets INT NOT NULL DEFAULT 0,
        total_returned_accessories INT NOT NULL DEFAULT 0,
        returned_assets_json NVARCHAR(MAX) NULL,
        returned_accessories_json NVARCHAR(MAX) NULL,
        inspection_notes NVARCHAR(MAX) NULL,
        custodian_signoff BIT NOT NULL DEFAULT 1,
        processed_by NVARCHAR(150) NULL DEFAULT 'IT Administrator',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";
sqlsrv_query($conn, $returnsTableSetupSql);

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

// Helper: Generate next unique return receipt number (e.g. RET-2026-0001)
function getNextReturnSlipNumber($conn) {
    $year = date('Y');
    $sql = "SELECT ISNULL(MAX(id), 0) + 1 AS next_id FROM asset_returns";
    $stmt = sqlsrv_query($conn, $sql);
    $nextId = 1;
    if ($stmt !== false && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $nextId = intval($row['next_id']);
        sqlsrv_free_stmt($stmt);
    }
    return sprintf("RET-%s-%04d", $year, $nextId);
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

    $where = ["custody_status != 'Returned'"];
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
                     handover_by, agreement_signed, notes, return_notes, transferred_to,
                     CONVERT(VARCHAR(19), created_at, 120) AS created_at
              FROM asset_assignments
              WHERE $whereClause
              ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $query, $params);
    $items = [];

    // Preload master asset inventory for serial and hardware metadata resolution
    $masterAssetsMap = [];
    $masterAssetsByTag = [];
    $allAstStmt = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, processor, ram, storage FROM assets");
    if ($allAstStmt !== false) {
        while ($ar = sqlsrv_fetch_array($allAstStmt, SQLSRV_FETCH_ASSOC)) {
            $arId = intval($ar['id']);
            $masterAssetsMap[$arId] = $ar;
            if (!empty($ar['tag'])) {
                $masterAssetsByTag[strtoupper(trim($ar['tag']))] = $ar;
            }
        }
        sqlsrv_free_stmt($allAstStmt);
    }

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

            // Ensure serial number and specs are never missing or "—"
            foreach ($assetsList as &$ast) {
                $aId = isset($ast['id']) ? intval($ast['id']) : 0;
                $aTag = isset($ast['tag']) ? strtoupper(trim($ast['tag'])) : '';
                $master = ($aId > 0 && isset($masterAssetsMap[$aId])) 
                    ? $masterAssetsMap[$aId] 
                    : (($aTag && isset($masterAssetsByTag[$aTag])) ? $masterAssetsByTag[$aTag] : null);

                if ($master) {
                    if (empty($ast['serial']) || $ast['serial'] === '—' || $ast['serial'] === '-') {
                        $ast['serial'] = (!empty($master['serial']) && $master['serial'] !== '—') ? $master['serial'] : '—';
                    }
                    if (empty($ast['brand'])) {
                        $ast['brand'] = $master['brand'] ?? '';
                    }
                    if (empty($ast['model'])) {
                        $ast['model'] = $master['model'] ?? '';
                    }
                    if (empty($ast['category']) || $ast['category'] === 'Hardware') {
                        $ast['category'] = $master['category'] ?? 'Hardware';
                    }
                } elseif (!empty($row['serial']) && (empty($ast['serial']) || $ast['serial'] === '—')) {
                    $ast['serial'] = $row['serial'];
                }
            }
            unset($ast);

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
                'transferred_to'    => $row['transferred_to'] ?? '',
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
        'transferred'       => 0,
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
                    ISNULL(SUM(CASE WHEN custody_status = 'Transferred' THEN 1 ELSE 0 END), 0) AS transferred,
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
        $stats['transferred']      = intval($sRow['transferred']);
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

    // Assets and accessories are optional

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

    // Primary device representation for indexing (null if only accessories assigned)
    $primaryAsset = !empty($detailedAssets) ? $detailedAssets[0] : null;
    $primaryAssetId = $primaryAsset ? $primaryAsset['id'] : null;
    $primaryAssetTag = $primaryAsset ? $primaryAsset['tag'] : null;
    $primaryAssetName = $primaryAsset ? $primaryAsset['name'] : null;
    $primaryCategory = $primaryAsset ? $primaryAsset['category'] : (count($detailedAccessories) > 0 ? 'Peripherals & Accessories' : 'General IT Allocation');
    $primaryBrand = $primaryAsset ? $primaryAsset['brand'] : null;
    $primaryModel = $primaryAsset ? $primaryAsset['model'] : null;
    $primarySerial = $primaryAsset ? $primaryAsset['serial'] : null;
    $primarySpecs = $primaryAsset ? $primaryAsset['specs'] : null;
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
// ACTION: RETURN (Process asset return, store in asset_returns, restore inventory)
// -----------------------------------------------------------------------------
if ($action === 'return') {
    $allocId = isset($_POST['alloc_id']) ? intval($_POST['alloc_id']) : (isset($input['alloc_id']) ? intval($input['alloc_id']) : 0);
    $allocIds = [];
    if (!empty($input['alloc_ids']) && is_array($input['alloc_ids'])) {
        $allocIds = array_map('intval', $input['alloc_ids']);
    } elseif (!empty($_POST['alloc_ids']) && is_array($_POST['alloc_ids'])) {
        $allocIds = array_map('intval', $_POST['alloc_ids']);
    } elseif ($allocId > 0) {
        $allocIds = [$allocId];
    }
    if (empty($allocIds) && $allocId > 0) {
        $allocIds = [$allocId];
    }
    if ($allocId <= 0 && !empty($allocIds)) {
        $allocId = $allocIds[0];
    }

    $returnDate = trim($_POST['return_date'] ?? $input['return_date'] ?? date('Y-m-d'));
    $returnCondition = trim($_POST['return_condition'] ?? $input['return_condition'] ?? 'Good');
    $storageLocation = trim($_POST['storage_location'] ?? $input['storage_location'] ?? 'Storage Depot (Rack A-01)');
    $returnNotes = trim($_POST['return_notes'] ?? $input['return_notes'] ?? 'Equipment checked-in and verified.');

    if ($allocId <= 0 && empty($allocIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid allocation or custodian selection.']);
        exit;
    }

    // Fetch all related allocation records
    $inPlaceholders = implode(',', array_fill(0, count($allocIds), '?'));
    $stmt = sqlsrv_query($conn, "SELECT id, slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs, employee_id, employee_name, emp_code, employee_email, department, designation, location, allocation_type, CONVERT(VARCHAR(10), assigned_date, 120) AS assigned_date, assets_json, accessories_json, custody_status FROM asset_assignments WHERE id IN ($inPlaceholders)", $allocIds);
    $allocsList = [];
    $allSlips = [];
    if ($stmt !== false) {
        while ($aRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $allocsList[] = $aRow;
            if (!in_array($aRow['slip_no'], $allSlips)) {
                $allSlips[] = $aRow['slip_no'];
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    if (empty($allocsList)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Allocation record(s) not found.']);
        exit;
    }

    $alloc = $allocsList[0];
    $combinedSlips = implode(', ', $allSlips);

    // Diagnostic Checklist & Sign-off values from Form
    $checklist = $input['checklist'] ?? $_POST['checklist'] ?? [];
    $chkPower     = !empty($checklist['diag_power_boot']) ? 1 : 0;
    $chkDisplay   = !empty($checklist['diag_display']) ? 1 : 0;
    $chkBattery   = !empty($checklist['diag_battery']) ? 1 : 0;
    $chkKeyboard  = !empty($checklist['diag_keyboard_trackpad']) ? 1 : 0;
    $chkPorts     = !empty($checklist['diag_ports_audio']) ? 1 : 0;
    $chkConn      = !empty($checklist['diag_connectivity']) ? 1 : 0;
    $chkWipe      = !empty($checklist['diag_storage_wiped']) ? 1 : 0;
    $chkLocks     = !empty($checklist['diag_locks_removed']) ? 1 : 0;
    $chkBackup    = !empty($checklist['diag_data_backup']) ? 1 : 0;
    $chkChassis   = !empty($checklist['diag_body_hinges']) ? 1 : 0;
    $chkCharger   = !empty($checklist['diag_oem_charger']) ? 1 : 0;
    $chkTag       = !empty($checklist['diag_asset_tag']) ? 1 : 0;

    $diagPassCount = $chkPower + $chkDisplay + $chkBattery + $chkKeyboard + $chkPorts + $chkConn + $chkWipe + $chkLocks + $chkBackup + $chkChassis + $chkCharger + $chkTag;
    $diagTotalCount = 12;
    $diagChecklistJson = json_encode($checklist, JSON_UNESCAPED_UNICODE);

    $inspectionNotes = trim($input['inspection_notes'] ?? $_POST['inspection_notes'] ?? $returnNotes);
    $custodianSignoff = isset($input['custodian_signoff']) ? intval($input['custodian_signoff']) : 1;
    $processedBy = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'IT Administrator';

    $condLower = strtolower($returnCondition);
    $restockStatus = (strpos($condLower, 'repair') !== false || strpos($condLower, 'damag') !== false) 
        ? 'Under Service / Repair' 
        : 'Restocked to Stock';

    // Detailed returned assets
    $returnedAssetsInput = isset($input['returned_assets']) ? $input['returned_assets'] : (isset($_POST['returned_assets']) ? $_POST['returned_assets'] : null);
    $returnedAssetIdsInput = isset($input['returned_asset_ids']) ? $input['returned_asset_ids'] : (isset($_POST['returned_asset_ids']) ? $_POST['returned_asset_ids'] : null);

    $returnedAssetsList = [];
    $returnedAssetIds = [];

    if (is_array($returnedAssetsInput)) {
        // Client gave an explicit list of returned assets (can be empty if 0 assets were selected)
        foreach ($returnedAssetsInput as $rAst) {
            $rId = intval($rAst['id'] ?? 0);
            $returnedAssetsList[] = [
                'id'       => $rId,
                'tag'      => $rAst['tag'] ?? '',
                'name'     => $rAst['name'] ?? '',
                'category' => $rAst['category'] ?? 'Hardware',
                'brand'    => $rAst['brand'] ?? '',
                'model'    => $rAst['model'] ?? '',
                'serial'   => $rAst['serial'] ?? '—',
                'specs'    => $rAst['specs'] ?? '',
                'slip_no'  => $rAst['slip_no'] ?? ($alloc['slip_no'] ?? ''),
                'alloc_id' => intval($rAst['alloc_id'] ?? 0)
            ];
            if ($rId > 0 && !in_array($rId, $returnedAssetIds)) {
                $returnedAssetIds[] = $rId;
            }
        }
    } elseif (is_array($returnedAssetIdsInput)) {
        // Client gave explicit list of IDs
        $cleanIds = array_map('intval', $returnedAssetIdsInput);
        foreach ($allocsList as $aItem) {
            $aAssets = json_decode($aItem['assets_json'] ?? '[]', true) ?: [];
            if (empty($aAssets) && !empty($aItem['asset_id'])) {
                $aAssets[] = [
                    'id'       => intval($aItem['asset_id']),
                    'tag'      => $aItem['asset_tag'] ?? '',
                    'name'     => $aItem['asset_name'] ?? '',
                    'category' => $aItem['category'] ?? '',
                    'brand'    => $aItem['brand'] ?? '',
                    'model'    => $aItem['model'] ?? '',
                    'serial'   => $aItem['serial'] ?? '',
                    'specs'    => $aItem['specs'] ?? '',
                    'alloc_id' => intval($aItem['id'])
                ];
            }
            foreach ($aAssets as $ast) {
                $astId = intval($ast['id'] ?? 0);
                if (in_array($astId, $cleanIds)) {
                    $ast['slip_no'] = $aItem['slip_no'];
                    $ast['alloc_id'] = intval($aItem['id']);
                    $returnedAssetsList[] = $ast;
                    if ($astId > 0 && !in_array($astId, $returnedAssetIds)) {
                        $returnedAssetIds[] = $astId;
                    }
                }
            }
        }
    } else {
        // Fallback ONLY when neither parameter was passed at all (legacy full return)
        foreach ($allocsList as $aItem) {
            $aAssets = json_decode($aItem['assets_json'] ?? '[]', true) ?: [];
            if (empty($aAssets) && !empty($aItem['asset_id'])) {
                $aAssets[] = [
                    'id'       => intval($aItem['asset_id']),
                    'tag'      => $aItem['asset_tag'] ?? '',
                    'name'     => $aItem['asset_name'] ?? '',
                    'category' => $aItem['category'] ?? '',
                    'brand'    => $aItem['brand'] ?? '',
                    'model'    => $aItem['model'] ?? '',
                    'serial'   => $aItem['serial'] ?? '',
                    'specs'    => $aItem['specs'] ?? '',
                    'alloc_id' => intval($aItem['id'])
                ];
            }
            foreach ($aAssets as $ast) {
                $ast['slip_no'] = $aItem['slip_no'];
                $ast['alloc_id'] = intval($aItem['id']);
                $returnedAssetsList[] = $ast;
                $astId = intval($ast['id'] ?? 0);
                if ($astId > 0 && !in_array($astId, $returnedAssetIds)) {
                    $returnedAssetIds[] = $astId;
                }
            }
        }
    }

    // Detailed returned accessories
    $returnedAccInput = isset($input['returned_accessories']) ? $input['returned_accessories'] : (isset($_POST['returned_accessories']) ? $_POST['returned_accessories'] : null);
    $returnedAccList = [];

    if (is_array($returnedAccInput)) {
        // Client gave explicit list of returned accessories (can be empty if 0 accessories selected)
        foreach ($returnedAccInput as $rAcc) {
            if (is_array($rAcc)) {
                $returnedAccList[] = [
                    'id'       => intval($rAcc['id'] ?? 0),
                    'name'     => $rAcc['name'] ?? 'Accessory',
                    'qty'      => intval($rAcc['qty'] ?? 1),
                    'category' => $rAcc['category'] ?? 'Accessories',
                    'slip_no'  => $rAcc['slip_no'] ?? ($alloc['slip_no'] ?? ''),
                    'alloc_id' => intval($rAcc['alloc_id'] ?? 0)
                ];
            } else {
                $returnedAccList[] = [
                    'id'       => 0,
                    'name'     => (string)$rAcc,
                    'qty'      => 1,
                    'category' => 'Accessories',
                    'slip_no'  => $alloc['slip_no'] ?? '',
                    'alloc_id' => intval($alloc['id'] ?? 0)
                ];
            }
        }
    } else {
        // Fallback ONLY when returned_accessories was not passed at all
        foreach ($allocsList as $aItem) {
            $aAccs = json_decode($aItem['accessories_json'] ?? '[]', true) ?: [];
            foreach ($aAccs as $ac) {
                if (is_array($ac)) {
                    $ac['slip_no'] = $aItem['slip_no'];
                    $ac['alloc_id'] = intval($aItem['id']);
                    $returnedAccList[] = $ac;
                } else {
                    $returnedAccList[] = [
                        'id'       => 0,
                        'name'     => (string)$ac,
                        'qty'      => 1,
                        'category' => 'Accessories',
                        'slip_no'  => $aItem['slip_no'],
                        'alloc_id' => intval($aItem['id'])
                    ];
                }
            }
        }
    }

    $totalReturnedAssets = count($returnedAssetsList);
    $totalReturnedAcc = 0;
    foreach ($returnedAccList as $ac) {
        $totalReturnedAcc += (is_array($ac) && isset($ac['qty'])) ? intval($ac['qty']) : 1;
    }

    if ($totalReturnedAssets === 0 && $totalReturnedAcc === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select at least one asset or accessory being returned.']);
        exit;
    }

    $returnedAssetsJson = json_encode($returnedAssetsList, JSON_UNESCAPED_UNICODE);
    $returnedAccJson = json_encode($returnedAccList, JSON_UNESCAPED_UNICODE);

    sqlsrv_begin_transaction($conn);

    // 1. Generate Return Slip Number
    $returnSlipNo = getNextReturnSlipNumber($conn);

    // 2. Insert Record into asset_returns Table
    $retSql = "INSERT INTO asset_returns (
                    return_slip_no, assignment_id, original_slip_no,
                    employee_id, employee_name, emp_code, department, designation, allocation_type, assigned_date,
                    return_date, storage_location, return_condition, restock_status,
                    diag_power_boot, diag_display, diag_battery, diag_keyboard_trackpad, diag_ports_audio, diag_connectivity,
                    diag_storage_wiped, diag_locks_removed, diag_data_backup, diag_body_hinges, diag_oem_charger, diag_asset_tag,
                    diag_pass_count, diag_total_count, diag_checklist_json,
                    total_returned_assets, total_returned_accessories, returned_assets_json, returned_accessories_json,
                    inspection_notes, custodian_signoff, processed_by, created_at, updated_at
               )
               OUTPUT INSERTED.id
               VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, GETDATE(), GETDATE()
               )";

    $retParams = [
        $returnSlipNo, $allocId, $combinedSlips,
        $alloc['employee_id'], $alloc['employee_name'], $alloc['emp_code'], $alloc['department'], $alloc['designation'], $alloc['allocation_type'], $alloc['assigned_date'],
        $returnDate, $storageLocation, $returnCondition, $restockStatus,
        $chkPower, $chkDisplay, $chkBattery, $chkKeyboard, $chkPorts, $chkConn,
        $chkWipe, $chkLocks, $chkBackup, $chkChassis, $chkCharger, $chkTag,
        $diagPassCount, $diagTotalCount, $diagChecklistJson,
        $totalReturnedAssets, $totalReturnedAcc, $returnedAssetsJson, $returnedAccJson,
        $inspectionNotes, $custodianSignoff, $processedBy
    ];

    $retStmt = sqlsrv_query($conn, $retSql, $retParams);
    $newReturnId = 0;
    if ($retStmt !== false && ($retRow = sqlsrv_fetch_array($retStmt, SQLSRV_FETCH_ASSOC))) {
        $newReturnId = intval($retRow['id']);
        sqlsrv_free_stmt($retStmt);
    } else {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to insert return record into asset_returns database.', 'error' => sqlsrv_errors()]);
        exit;
    }

    // 3. Update affected allocations in asset_assignments (support partial returns!)
    $fullyReturnedAllocIds = [];
    $partiallyReturnedAllocs = [];

    foreach ($allocsList as $aRow) {
        $aId = intval($aRow['id']);

        // Decode original assets for this allocation
        $origAssets = [];
        if (!empty($aRow['assets_json'])) {
            $dAst = json_decode($aRow['assets_json'], true);
            if (is_array($dAst)) $origAssets = $dAst;
        }
        if (empty($origAssets) && !empty($aRow['asset_name']) && (!empty($aRow['asset_id']) || !empty($aRow['asset_tag']))) {
            $origAssets[] = [
                'id'        => intval($aRow['asset_id']),
                'tag'       => $aRow['asset_tag'] ?? 'AST-000',
                'name'      => $aRow['asset_name'],
                'category'  => $aRow['category'] ?? 'Hardware',
                'brand'     => $aRow['brand'] ?? '',
                'model'     => $aRow['model'] ?? '',
                'serial'    => $aRow['serial'] ?? '—',
                'specs'     => $aRow['specs'] ?? '',
                'condition' => $aRow['condition'] ?? 'Good'
            ];
        }

        // Decode original accessories for this allocation
        $origAccs = [];
        if (!empty($aRow['accessories_json'])) {
            $dAcc = json_decode($aRow['accessories_json'], true);
            if (is_array($dAcc)) $origAccs = $dAcc;
        }

        // Determine remaining assets
        $remAssets = [];
        foreach ($origAssets as $oa) {
            $oaId = intval($oa['id'] ?? 0);
            $oaTag = trim($oa['tag'] ?? '');
            $isReturned = false;
            foreach ($returnedAssetsList as $ra) {
                $raId = intval($ra['id'] ?? 0);
                $raTag = trim($ra['tag'] ?? '');
                $raAllocId = intval($ra['alloc_id'] ?? 0);
                if ($raAllocId > 0 && $raAllocId !== $aId) continue;
                if (($oaId > 0 && $raId === $oaId) || (!empty($oaTag) && strcasecmp($oaTag, $raTag) === 0)) {
                    $isReturned = true;
                    break;
                }
            }
            if (!$isReturned) {
                $remAssets[] = $oa;
            }
        }

        // Determine remaining accessories
        $remAccs = [];
        foreach ($origAccs as $oac) {
            $oacName = is_array($oac) ? ($oac['name'] ?? 'Item') : (string)$oac;
            $oacQty = is_array($oac) ? intval($oac['qty'] ?? 1) : 1;
            $oacCategory = is_array($oac) ? ($oac['category'] ?? 'Accessories') : 'Accessories';
            $oacId = is_array($oac) ? intval($oac['id'] ?? 0) : 0;
            $oacSku = is_array($oac) ? ($oac['sku'] ?? '') : '';

            $returnedQty = 0;
            foreach ($returnedAccList as $rac) {
                $racAllocId = intval($rac['alloc_id'] ?? 0);
                $racName = $rac['name'] ?? '';
                if ($racAllocId > 0 && $racAllocId !== $aId) continue;
                if (strcasecmp(trim($racName), trim($oacName)) === 0) {
                    $returnedQty += intval($rac['qty'] ?? 1);
                }
            }

            $leftQty = $oacQty - $returnedQty;
            if ($leftQty > 0) {
                $remAccs[] = [
                    'id'       => $oacId,
                    'sku'      => $oacSku,
                    'name'     => $oacName,
                    'category' => $oacCategory,
                    'qty'      => $leftQty
                ];
            }
        }

        $remAssetsCount = count($remAssets);
        $remAccCount = 0;
        foreach ($remAccs as $rac) {
            $remAccCount += intval($rac['qty'] ?? 1);
        }

        if ($remAssetsCount === 0 && $remAccCount === 0) {
            // Entire allocation is returned
            $fullyReturnedAllocIds[] = $aId;
            sqlsrv_query($conn, "UPDATE asset_assignments 
                                 SET custody_status = 'Returned',
                                     total_assets = 0,
                                     total_accessories = 0,
                                     assets_json = '[]',
                                     accessories_json = '[]',
                                     asset_id = NULL,
                                     asset_tag = NULL,
                                     asset_name = NULL,
                                     return_date = ?,
                                     return_condition = ?,
                                     return_notes = ?,
                                     updated_at = GETDATE()
                                 WHERE id = ?", [$returnDate, $returnCondition, $returnNotes, $aId]);
        } else {
            // Partial return! Allocation remains Active with remaining items
            $primAst = !empty($remAssets) ? $remAssets[0] : null;
            $pId = $primAst ? intval($primAst['id'] ?? 0) : null;
            $pTag = $primAst ? ($primAst['tag'] ?? null) : null;
            $pName = $primAst ? ($primAst['name'] ?? null) : null;
            $pCat = $primAst ? ($primAst['category'] ?? 'Hardware') : 'Peripherals & Accessories';

            $partiallyReturnedAllocs[] = [
                'id'                => $aId,
                'slip_no'           => $aRow['slip_no'],
                'total_assets'      => $remAssetsCount,
                'total_accessories' => $remAccCount,
                'assets'            => $remAssets,
                'accessories'       => $remAccs
            ];

            sqlsrv_query($conn, "UPDATE asset_assignments 
                                 SET total_assets = ?,
                                     total_accessories = ?,
                                     assets_json = ?,
                                     accessories_json = ?,
                                     asset_id = ?,
                                     asset_tag = ?,
                                     asset_name = ?,
                                     category = ?,
                                     updated_at = GETDATE()
                                 WHERE id = ?", [
                $remAssetsCount,
                $remAccCount,
                json_encode($remAssets, JSON_UNESCAPED_UNICODE),
                json_encode($remAccs, JSON_UNESCAPED_UNICODE),
                $pId,
                $pTag,
                $pName,
                $pCat,
                $aId
            ]);
        }
    }

    // 4. Restore returned assets to 'Available' in assets table (ONLY actually returned assets)
    $assetStatus = (strpos($condLower, 'repair') !== false) ? 'Maintenance' : ((strpos($condLower, 'damag') !== false) ? 'Damaged' : 'Available');
    $restoredAssets = [];
    foreach ($returnedAssetsList as $ast) {
        $aId = intval($ast['id'] ?? 0);
        if ($aId > 0) {
            sqlsrv_query($conn, "UPDATE assets 
                                 SET status = ?, 
                                     condition = ?, 
                                     location = ?,
                                     assigned_to = NULL, 
                                     department = NULL,
                                     updated_at = GETDATE() 
                                 WHERE id = ?", [$assetStatus, $returnCondition, $storageLocation, $aId]);

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

    // 5. Restore returned accessories stock in accessories table
    $restoredAccessories = [];
    foreach ($returnedAccList as $ac) {
        $qty = (is_array($ac) && isset($ac['qty'])) ? intval($ac['qty']) : 1;
        $acId = (is_array($ac) && isset($ac['id'])) ? intval($ac['id']) : 0;
        if ($acId <= 0 && !empty($ac['name'])) {
            $findStmt = sqlsrv_query($conn, "SELECT id FROM accessories WHERE name = ?", [$ac['name']]);
            if ($findStmt !== false && ($fRow = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC))) {
                $acId = intval($fRow['id']);
            }
            sqlsrv_free_stmt($findStmt);
        }
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

    sqlsrv_commit($conn);

    // Calculate updated available stock count
    $countAvail = 0;
    $cStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM assets WHERE status = 'Available'");
    if ($cStmt !== false && ($cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC))) {
        $countAvail = intval($cRow['total']);
        sqlsrv_free_stmt($cStmt);
    }

    echo json_encode([
        'success'                    => true,
        'message'                    => "Equipment returned successfully under receipt {$returnSlipNo}. Stored in database.",
        'return_id'                  => $newReturnId,
        'return_slip_no'             => $returnSlipNo,
        'original_slip_no'           => $alloc['slip_no'],
        'alloc_id'                   => $allocId,
        'return_date'                => $returnDate,
        'return_condition'           => $returnCondition,
        'storage_location'           => $storageLocation,
        'restock_status'             => $restockStatus,
        'diag_pass_count'            => $diagPassCount,
        'diag_total_count'           => $diagTotalCount,
        'total_returned_assets'      => $totalReturnedAssets,
        'total_returned_accessories' => $totalReturnedAcc,
        'returned_assets'            => $returnedAssetsList,
        'returned_accessories'       => $returnedAccList,
        'fully_returned_alloc_ids'   => $fullyReturnedAllocIds,
        'partially_returned_allocs'  => $partiallyReturnedAllocs,
        'restored_assets'            => $restoredAssets,
        'restored_accessories'       => $restoredAccessories,
        'available_stock_count'      => $countAvail
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
