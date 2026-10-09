<?php
/**
 * VIROS IT Asset & Service Desk Portal
 * Asset Transfer & Inter-Branch Relocation API (api/asset_transfer.php)
 * 
 * Manages custodian-to-custodian equipment transfers, updates asset assignments,
 * logs full audit trail into the dedicated `asset_transfers` table.
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect/reject unauthenticated guests (allow preview if flag set)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// -----------------------------------------------------------------------------
// 1. Ensure Table Schema Exists (Dedicated asset_transfers Table)
// -----------------------------------------------------------------------------
$tableSetupSql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='asset_transfers' AND xtype='U')
BEGIN
    CREATE TABLE asset_transfers (
        id INT IDENTITY(1,1) PRIMARY KEY,
        transfer_slip_no NVARCHAR(50) NOT NULL UNIQUE,
        transfer_date DATE NOT NULL DEFAULT GETDATE(),
        source_employee_id INT NULL,
        source_employee_name NVARCHAR(150) NOT NULL,
        source_emp_code NVARCHAR(50) NULL,
        source_department NVARCHAR(150) NULL,
        source_designation NVARCHAR(100) NULL,
        source_location NVARCHAR(150) NULL,
        target_employee_id INT NULL,
        target_employee_name NVARCHAR(150) NOT NULL,
        target_emp_code NVARCHAR(50) NULL,
        target_department NVARCHAR(150) NULL,
        target_designation NVARCHAR(100) NULL,
        target_location NVARCHAR(150) NULL,
        total_assets INT NOT NULL DEFAULT 0,
        total_accessories INT NOT NULL DEFAULT 0,
        assets_json NVARCHAR(MAX) NULL,
        accessories_json NVARCHAR(MAX) NULL,
        reason NVARCHAR(MAX) NULL,
        processed_by NVARCHAR(150) NULL DEFAULT 'IT Administrator',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";

sqlsrv_query($conn, $tableSetupSql);

// Ensure transferred_to column exists on asset_assignments table
sqlsrv_query($conn, "
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_assignments' AND COLUMN_NAME = 'transferred_to')
BEGIN
    ALTER TABLE asset_assignments ADD transferred_to NVARCHAR(150) NULL;
END");

// -----------------------------------------------------------------------------
// Helper: Generate next unique transfer slip number (TRF-YYYY-XXXX)
// -----------------------------------------------------------------------------
function getNextTransferSlipNumber($conn) {
    $year = date('Y');
    $sql = "SELECT ISNULL(MAX(id), 0) + 1 AS next_id FROM asset_transfers";
    $stmt = sqlsrv_query($conn, $sql);
    $nextId = 1;
    if ($stmt !== false && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $nextId = intval($row['next_id']);
        sqlsrv_free_stmt($stmt);
    }
    return sprintf("TRF-%s-%04d", $year, $nextId);
}

// Helper: Generate next unique assignment slip number (SLIP-YYYY-XXXX)
function getNextAssignmentSlipNumber($conn) {
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

// Parse request payload
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

if (empty($action) && isset($input['action'])) {
    $action = trim($input['action']);
}
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = 'transfer';
}
if (empty($action)) {
    $action = 'list';
}

// -----------------------------------------------------------------------------
// ACTION: LIST / GET (Fetch all transfers)
// -----------------------------------------------------------------------------
if ($action === 'list' || $action === 'get') {
    $search = trim($_GET['search'] ?? '');
    $where = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(transfer_slip_no LIKE ? OR source_employee_name LIKE ? OR target_employee_name LIKE ? OR source_emp_code LIKE ? OR target_emp_code LIKE ?)";
        $wildcard = "%{$search}%";
        $params = [$wildcard, $wildcard, $wildcard, $wildcard, $wildcard];
    }

    $whereClause = implode(' AND ', $where);
    $query = "SELECT id, transfer_slip_no,
                     CONVERT(VARCHAR(10), transfer_date, 120) AS transfer_date,
                     source_employee_id, source_employee_name, source_emp_code, source_department, source_designation, source_location,
                     target_employee_id, target_employee_name, target_emp_code, target_department, target_designation, target_location,
                     total_assets, total_accessories, assets_json, accessories_json,
                     reason, processed_by,
                     CONVERT(VARCHAR(19), created_at, 120) AS created_at
              FROM asset_transfers
              WHERE $whereClause
              ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $query, $params);
    $items = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $assets = !empty($row['assets_json']) ? (json_decode($row['assets_json'], true) ?: []) : [];
            $accessories = !empty($row['accessories_json']) ? (json_decode($row['accessories_json'], true) ?: []) : [];

            $items[] = [
                'id'               => intval($row['id']),
                'transfer_slip_no' => $row['transfer_slip_no'],
                'transfer_date'    => $row['transfer_date'],
                'source_custodian' => [
                    'id'          => $row['source_employee_id'] ? intval($row['source_employee_id']) : null,
                    'name'        => $row['source_employee_name'],
                    'emp_code'    => $row['source_emp_code'] ?? 'EMP',
                    'department'  => $row['source_department'] ?? 'General',
                    'designation' => $row['source_designation'] ?? 'Staff',
                    'location'    => $row['source_location'] ?? 'Corporate HQ'
                ],
                'target_custodian' => [
                    'id'          => $row['target_employee_id'] ? intval($row['target_employee_id']) : null,
                    'name'        => $row['target_employee_name'],
                    'emp_code'    => $row['target_emp_code'] ?? 'EMP',
                    'department'  => $row['target_department'] ?? 'General',
                    'designation' => $row['target_designation'] ?? 'Staff',
                    'location'    => $row['target_location'] ?? 'Corporate HQ'
                ],
                'total_assets'      => intval($row['total_assets']),
                'total_accessories' => intval($row['total_accessories']),
                'assets'            => $assets,
                'accessories'       => $accessories,
                'reason'            => $row['reason'] ?? '',
                'processed_by'      => $row['processed_by'] ?? 'IT Administrator',
                'created_at'        => $row['created_at']
            ];
        }
        sqlsrv_free_stmt($stmt);
    }

    echo json_encode(['success' => true, 'data' => $items, 'total' => count($items)]);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION: TRANSFER / CREATE (Store in asset_transfers and update custodian)
// -----------------------------------------------------------------------------
if ($action === 'transfer' || $action === 'create') {
    // 1. Source Custodian Data
    $srcEmpId   = isset($input['source_employee_id']) ? intval($input['source_employee_id']) : 0;
    $srcEmpName = trim($input['source_employee_name'] ?? '');
    $srcEmpCode = trim($input['source_emp_code'] ?? '');
    $srcDept    = trim($input['source_department'] ?? 'General');
    $srcDesig   = trim($input['source_designation'] ?? 'Staff');
    $srcLoc     = trim($input['source_location'] ?? 'Corporate HQ');

    // 2. Target Recipient Data
    $tgtEmpId   = isset($input['target_employee_id']) ? intval($input['target_employee_id']) : 0;
    $tgtEmpName = trim($input['target_employee_name'] ?? '');
    $tgtEmpCode = trim($input['target_emp_code'] ?? '');
    $tgtDept    = trim($input['target_department'] ?? 'General');
    $tgtDesig   = trim($input['target_designation'] ?? 'Staff');
    $tgtLoc     = trim($input['target_location'] ?? 'Corporate HQ');

    // 3. Transfer Details
    $transferDate = trim($input['transfer_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transferDate)) {
        $transferDate = date('Y-m-d');
    }
    $reason = trim($input['reason'] ?? '');
    $processedBy = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'IT Administrator';

    // 4. Equipment Lists
    $transferredAssets = is_array($input['assets'] ?? null) ? $input['assets'] : [];
    $transferredAccessories = is_array($input['accessories'] ?? null) ? $input['accessories'] : [];

    if (empty($srcEmpName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Source employee custodian is required.']);
        exit;
    }

    if (empty($tgtEmpName) || $tgtEmpId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Target recipient employee is required.']);
        exit;
    }

    if (count($transferredAssets) === 0 && count($transferredAccessories) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select at least one asset or accessory to transfer.']);
        exit;
    }

    // Begin DB Transaction
    sqlsrv_begin_transaction($conn);

    try {
        // A. Generate unique transfer slip number
        $transferSlipNo = getNextTransferSlipNumber($conn);

        $totalAssets = count($transferredAssets);
        $totalAcc = 0;
        foreach ($transferredAccessories as $acc) {
            $totalAcc += (is_array($acc) && isset($acc['qty'])) ? intval($acc['qty']) : 1;
        }

        // Resolve full master metadata (serial, brand, model, specs) from assets table
        $resolvedAssets = [];
        $primaryAssetId   = null;
        $primaryAssetTag  = null;
        $primaryAssetName = null;
        $primaryCategory  = null;
        $primaryBrand     = null;
        $primaryModel     = null;
        $primarySerial    = null;
        $primarySpecs     = null;

        foreach ($transferredAssets as $ast) {
            $astId = isset($ast['id']) ? intval($ast['id']) : 0;
            $astTag = trim($ast['tag'] ?? '');

            $realAst = null;
            if ($astId > 0) {
                $qAst = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, processor, ram, storage FROM assets WHERE id = ?", [$astId]);
                if ($qAst !== false && ($rA = sqlsrv_fetch_array($qAst, SQLSRV_FETCH_ASSOC))) {
                    $realAst = $rA;
                    sqlsrv_free_stmt($qAst);
                }
            }
            if (!$realAst && !empty($astTag)) {
                $qAst = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, processor, ram, storage FROM assets WHERE tag = ?", [$astTag]);
                if ($qAst !== false && ($rA = sqlsrv_fetch_array($qAst, SQLSRV_FETCH_ASSOC))) {
                    $realAst = $rA;
                    sqlsrv_free_stmt($qAst);
                }
            }

            $resolvedId       = $astId ?: ($realAst['id'] ?? null);
            $resolvedTag      = !empty($realAst['tag']) ? $realAst['tag'] : $astTag;
            $resolvedName     = !empty($realAst['name']) ? $realAst['name'] : ($ast['name'] ?? 'Device');
            $resolvedCategory = !empty($realAst['category']) ? $realAst['category'] : ($ast['category'] ?? 'Hardware');
            $resolvedBrand    = !empty($realAst['brand']) ? $realAst['brand'] : ($ast['brand'] ?? '');
            $resolvedModel    = !empty($realAst['model']) ? $realAst['model'] : ($ast['model'] ?? '');
            $resolvedSerial   = (!empty($realAst['serial']) && $realAst['serial'] !== '—') 
                                    ? $realAst['serial'] 
                                    : ((!empty($ast['serial']) && $ast['serial'] !== '—') ? $ast['serial'] : '—');
            
            $specsParts = [];
            if (!empty($realAst['processor'])) $specsParts[] = $realAst['processor'];
            if (!empty($realAst['ram'])) $specsParts[] = $realAst['ram'];
            if (!empty($realAst['storage'])) $specsParts[] = $realAst['storage'];
            $resolvedSpecs = !empty($specsParts) ? implode(' • ', $specsParts) : ($ast['specs'] ?? '');

            $resolvedAssets[] = [
                'id'        => $resolvedId,
                'tag'       => $resolvedTag,
                'name'      => $resolvedName,
                'category'  => $resolvedCategory,
                'brand'     => $resolvedBrand,
                'model'     => $resolvedModel,
                'serial'    => $resolvedSerial,
                'specs'     => $resolvedSpecs,
                'condition' => $realAst['condition'] ?? ($ast['condition'] ?? 'Good')
            ];

            if ($primaryAssetId === null) {
                $primaryAssetId   = $resolvedId;
                $primaryAssetTag  = $resolvedTag;
                $primaryAssetName = $resolvedName;
                $primaryCategory  = $resolvedCategory;
                $primaryBrand     = $resolvedBrand;
                $primaryModel     = $resolvedModel;
                $primarySerial    = ($resolvedSerial !== '—') ? $resolvedSerial : null;
                $primarySpecs     = $resolvedSpecs ?: null;
            }
        }

        $assetsJson = json_encode($resolvedAssets, JSON_UNESCAPED_UNICODE);
        $accJson = json_encode($transferredAccessories, JSON_UNESCAPED_UNICODE);

        // B. Insert into asset_transfers table
        $insertTransferSql = "INSERT INTO asset_transfers (
            transfer_slip_no, transfer_date,
            source_employee_id, source_employee_name, source_emp_code, source_department, source_designation, source_location,
            target_employee_id, target_employee_name, target_emp_code, target_department, target_designation, target_location,
            total_assets, total_accessories, assets_json, accessories_json,
            reason, processed_by, created_at, updated_at
        )
        OUTPUT INSERTED.id
        VALUES (
            ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, GETDATE(), GETDATE()
        )";

        $transferParams = [
            $transferSlipNo, $transferDate,
            $srcEmpId > 0 ? $srcEmpId : null, $srcEmpName, $srcEmpCode, $srcDept, $srcDesig, $srcLoc,
            $tgtEmpId > 0 ? $tgtEmpId : null, $tgtEmpName, $tgtEmpCode, $tgtDept, $tgtDesig, $tgtLoc,
            $totalAssets, $totalAcc, $assetsJson, $accJson,
            $reason, $processedBy
        ];

        $trStmt = sqlsrv_query($conn, $insertTransferSql, $transferParams);
        if ($trStmt === false) {
            throw new Exception('Failed to insert transfer record: ' . print_r(sqlsrv_errors(), true));
        }

        $newTransferId = 0;
        if ($trRow = sqlsrv_fetch_array($trStmt, SQLSRV_FETCH_ASSOC)) {
            $newTransferId = intval($trRow['id']);
        }
        sqlsrv_free_stmt($trStmt);

        // C. Update Custody in 'assets' table for hardware units
        $transferredAssetIds = [];
        foreach ($resolvedAssets as $ast) {
            $astId = isset($ast['id']) ? intval($ast['id']) : 0;
            if ($astId > 0) {
                $transferredAssetIds[] = $astId;
                $updateAssetSql = "UPDATE assets 
                                   SET assigned_to = ?, department = ?, location = ?, status = 'Deployed'
                                   WHERE id = ?";
                $astStmt = sqlsrv_query($conn, $updateAssetSql, [$tgtEmpName, $tgtDept, $tgtLoc, $astId]);
                if ($astStmt !== false) {
                    sqlsrv_free_stmt($astStmt);
                }
            }
        }

        // D. Create New Active Allocation Slip in 'asset_assignments' for Target Recipient
        $newAssignmentSlip = getNextAssignmentSlipNumber($conn);
        $assignNotes = "Transferred from {$srcEmpName} ({$srcEmpCode}) under Transfer Slip {$transferSlipNo} on {$transferDate}.";
        if (!empty($reason)) {
            $assignNotes .= " Reason: " . $reason;
        }

        $newAssignSql = "INSERT INTO asset_assignments (
            slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
            employee_id, employee_name, emp_code, department, designation, location,
            assigned_date, allocation_type, custody_status, condition,
            total_assets, total_accessories, assets_json, accessories_json,
            handover_by, agreement_signed, notes, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, 'Permanent', 'Active', 'Good',
            ?, ?, ?, ?,
            ?, 1, ?, GETDATE(), GETDATE()
        )";

        $assignParams = [
            $newAssignmentSlip,
            $primaryAssetId,
            $primaryAssetTag,
            $primaryAssetName,
            $primaryCategory,
            $primaryBrand,
            $primaryModel,
            $primarySerial,
            $primarySpecs,
            $tgtEmpId > 0 ? $tgtEmpId : null,
            $tgtEmpName,
            $tgtEmpCode,
            $tgtDept,
            $tgtDesig,
            $tgtLoc,
            $transferDate,
            $totalAssets,
            $totalAcc,
            $assetsJson,
            $accJson,
            $processedBy,
            $assignNotes
        ];

        $assignStmt = sqlsrv_query($conn, $newAssignSql, $assignParams);
        if ($assignStmt === false) {
            throw new Exception('Failed to create target custodian assignment record: ' . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($assignStmt);

        // E. Update / Relieve Source Custodian's active assignment(s)
        if (!empty($transferredAssetIds)) {
            // Find active assignments belonging to source employee
            $srcAllocSql = "SELECT id, total_assets, total_accessories, assets_json, accessories_json 
                            FROM asset_assignments 
                            WHERE custody_status != 'Returned' AND (employee_id = ? OR emp_code = ? OR employee_name = ?)";
            $srcAllocStmt = sqlsrv_query($conn, $srcAllocSql, [$srcEmpId, $srcEmpCode, $srcEmpName]);
            if ($srcAllocStmt !== false) {
                while ($sa = sqlsrv_fetch_array($srcAllocStmt, SQLSRV_FETCH_ASSOC)) {
                    $saId = intval($sa['id']);
                    $curAssets = !empty($sa['assets_json']) ? (json_decode($sa['assets_json'], true) ?: []) : [];
                    $curAcc = !empty($sa['accessories_json']) ? (json_decode($sa['accessories_json'], true) ?: []) : [];

                    // Filter out transferred asset IDs
                    $remainingAssets = [];
                    foreach ($curAssets as $item) {
                        $itemId = isset($item['id']) ? intval($item['id']) : 0;
                        if (!in_array($itemId, $transferredAssetIds)) {
                            $remainingAssets[] = $item;
                        }
                    }

                    // Filter accessories if transferred
                    $transferredAccNames = array_map(function($a) {
                        return is_array($a) ? ($a['name'] ?? '') : (string)$a;
                    }, $transferredAccessories);

                    $remainingAcc = [];
                    foreach ($curAcc as $accItem) {
                        $aName = is_array($accItem) ? ($accItem['name'] ?? '') : (string)$accItem;
                        if (!in_array($aName, $transferredAccNames)) {
                            $remainingAcc[] = $accItem;
                        }
                    }

                    $remAssetCount = count($remainingAssets);
                    $remAccCount = count($remainingAcc);

                    if ($remAssetCount === 0 && $remAccCount === 0) {
                        // Entire assignment transferred out
                        $updSa = sqlsrv_query($conn, "UPDATE asset_assignments SET custody_status = 'Transferred', transferred_to = ?, return_date = ?, return_notes = ?, updated_at = GETDATE() WHERE id = ?", [
                            $tgtEmpName,
                            $transferDate,
                            "Transferred to {$tgtEmpName} via {$transferSlipNo}",
                            $saId
                        ]);
                        if ($updSa !== false) sqlsrv_free_stmt($updSa);
                    } else {
                        // Partially transferred, update remaining items
                        $updSa = sqlsrv_query($conn, "UPDATE asset_assignments SET total_assets = ?, total_accessories = ?, assets_json = ?, accessories_json = ?, updated_at = GETDATE() WHERE id = ?", [
                            $remAssetCount,
                            $remAccCount,
                            json_encode($remainingAssets, JSON_UNESCAPED_UNICODE),
                            json_encode($remainingAcc, JSON_UNESCAPED_UNICODE),
                            $saId
                        ]);
                        if ($updSa !== false) sqlsrv_free_stmt($updSa);
                    }
                }
                sqlsrv_free_stmt($srcAllocStmt);
            }
        }

        // Commit all changes atomically
        sqlsrv_commit($conn);

        $createdRecord = [
            'id'               => $newTransferId,
            'transfer_slip_no' => $transferSlipNo,
            'transfer_date'    => $transferDate,
            'source_custodian' => [
                'id'          => $srcEmpId,
                'name'        => $srcEmpName,
                'emp_code'    => $srcEmpCode,
                'department'  => $srcDept,
                'designation' => $srcDesig,
                'location'    => $srcLoc
            ],
            'target_custodian' => [
                'id'          => $tgtEmpId,
                'name'        => $tgtEmpName,
                'emp_code'    => $tgtEmpCode,
                'department'  => $tgtDept,
                'designation' => $tgtDesig,
                'location'    => $tgtLoc
            ],
            'total_assets'      => $totalAssets,
            'total_accessories' => $totalAcc,
            'assets'            => $transferredAssets,
            'accessories'       => $transferredAccessories,
            'reason'            => $reason,
            'processed_by'      => $processedBy,
            'target_assignment' => $newAssignmentSlip,
            'created_at'        => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'success'          => true,
            'message'          => "Asset transfer successfully executed and recorded under slip {$transferSlipNo}.",
            'transfer_slip_no' => $transferSlipNo,
            'data'             => $createdRecord
        ]);
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;
