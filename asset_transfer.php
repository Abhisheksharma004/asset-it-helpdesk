<?php
// Asset Management & IT Service Desk Portal - Asset Transfer & Inter-Branch Relocation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Asset Transfer & Relocation - VIROS Portal";
$active_page = "asset_transfer";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/asset_return.css', 'css/asset_transfer.css'];
$extra_js  = ['js/asset_transfer.js'];

// Include database to fetch dynamic data
require_once __DIR__ . '/config/db.php';

$departmentsList = [];
$locationsList = [];
$employeesList = [];
$activeAllocations = [];
$activeCustodians = [];
$transfersList = [];

if (isset($conn) && $conn !== false) {
    // 1. Fetch active departments
    $deptStmt = sqlsrv_query($conn, "SELECT id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    if ($deptStmt !== false) {
        while ($d = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
            $departmentsList[] = $d['department_name'];
        }
        sqlsrv_free_stmt($deptStmt);
    }

    // 2. Fetch active locations
    $locStmt = sqlsrv_query($conn, "SELECT id, location_name FROM locations WHERE status = 'Active' ORDER BY location_name ASC");
    if ($locStmt !== false) {
        while ($l = sqlsrv_fetch_array($locStmt, SQLSRV_FETCH_ASSOC)) {
            $locationsList[] = $l['location_name'];
        }
        sqlsrv_free_stmt($locStmt);
    }

    // 3. Fetch active employees
    $empStmt = sqlsrv_query($conn, "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.phone, 
                                           e.designation, d.department_name, l.location_name
                                    FROM employees e
                                    LEFT JOIN departments d ON e.department_id = d.id
                                    LEFT JOIN locations l ON e.location_id = l.id
                                    WHERE e.status = 'Active'
                                    ORDER BY e.first_name ASC");
    if ($empStmt !== false) {
        while ($e = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
            $fullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
            $employeesList[] = [
                'id'          => intval($e['id']),
                'emp_code'    => $e['emp_code'] ?? ('EMP-' . $e['id']),
                'name'        => $fullName ?: 'Employee #' . $e['id'],
                'email'       => $e['email'] ?? '',
                'phone'       => $e['phone'] ?? '',
                'designation' => $e['designation'] ?? 'Staff',
                'department'  => $e['department_name'] ?? 'General',
                'location'    => $e['location_name'] ?? 'Corporate HQ'
            ];
        }
        sqlsrv_free_stmt($empStmt);
    }

    // Preload master asset inventory for serial and specs resolution
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

    // 4. Fetch Active allocations from asset_assignments table
    $allocQuery = "SELECT id, slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
                          employee_id, employee_name, emp_code, employee_email, department, designation, location,
                          CONVERT(VARCHAR(10), assigned_date, 120) AS assigned_date,
                          allocation_type,
                          CONVERT(VARCHAR(10), expected_return, 120) AS expected_return,
                          custody_status, condition,
                          total_assets, total_accessories, assets_json, accessories_json,
                          handover_by, notes
                   FROM asset_assignments
                   WHERE custody_status != 'Returned'
                   ORDER BY id DESC";

    $allocStmt = sqlsrv_query($conn, $allocQuery);
    if ($allocStmt !== false) {
        while ($row = sqlsrv_fetch_array($allocStmt, SQLSRV_FETCH_ASSOC)) {
            $assetsList = [];
            $hasExplicitAssetsJson = ($row['assets_json'] !== null && trim($row['assets_json']) !== '');
            if ($hasExplicitAssetsJson) {
                $decoded = json_decode($row['assets_json'], true);
                if (is_array($decoded)) {
                    $assetsList = $decoded;
                }
            }
            if (!$hasExplicitAssetsJson && !empty($row['asset_name']) && (!empty($row['asset_id']) || !empty($row['asset_tag']) || intval($row['total_assets']) > 0)) {
                $assetsList[] = [
                    'id'        => intval($row['asset_id']),
                    'tag'       => $row['asset_tag'] ?? 'AST-000',
                    'name'      => $row['asset_name'],
                    'category'  => $row['category'] ?? 'Hardware',
                    'brand'     => $row['brand'] ?? '',
                    'model'     => $row['model'] ?? '',
                    'serial'    => $row['serial'] ?? '—',
                    'specs'     => $row['specs'] ?? '',
                    'condition' => $row['condition'] ?? 'Good'
                ];
            }

            // Enrich assets with master metadata (ensure serial number is never missing or "—")
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

            $accList = [];
            if (!empty($row['accessories_json'])) {
                $decodedAcc = json_decode($row['accessories_json'], true);
                if (is_array($decodedAcc)) {
                    $accList = $decodedAcc;
                }
            }

            $totalAssets = (isset($row['total_assets']) && $row['total_assets'] !== null)
                ? intval($row['total_assets']) 
                : count($assetsList);

            $totalAcc = intval($row['total_accessories']);
            if ($totalAcc <= 0) {
                $tCount = 0;
                foreach ($accList as $ac) {
                    $tCount += isset($ac['qty']) ? intval($ac['qty']) : 1;
                }
                $totalAcc = $tCount;
            }

            $activeAllocations[] = [
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
                'custody_status'    => $row['custody_status'],
                'condition'         => $row['condition'] ?? 'Good',
                'total_assets'      => $totalAssets,
                'total_accessories' => $totalAcc,
                'assets'            => $assetsList,
                'accessories'       => $accList,
                'handover_by'       => $row['handover_by'] ?? 'IT Administrator',
                'notes'             => $row['notes'] ?? ''
            ];
        }
        sqlsrv_free_stmt($allocStmt);
    }
}

// Group active allocations by employee custodian (same unified custodian pattern)
foreach ($activeAllocations as $alloc) {
    $empKey = !empty($alloc['emp_code']) ? $alloc['emp_code'] : ($alloc['employee_name'] ?: 'EMP-' . $alloc['id']);
    if (!isset($activeCustodians[$empKey])) {
        $activeCustodians[$empKey] = [
            'emp_key'         => $empKey,
            'employee_id'     => $alloc['employee_id'],
            'employee_name'   => $alloc['employee_name'],
            'emp_code'        => $alloc['emp_code'],
            'employee_email'  => $alloc['employee_email'],
            'department'      => $alloc['department'],
            'designation'     => $alloc['designation'],
            'location'        => $alloc['location'],
            'allocation_type' => $alloc['allocation_type'],
            'assigned_date'   => $alloc['assigned_date'],
            'alloc_ids'       => [],
            'slips'           => [],
            'all_assets'      => [],
            'all_accessories' => []
        ];
    }
    if (!in_array($alloc['id'], $activeCustodians[$empKey]['alloc_ids'])) {
        $activeCustodians[$empKey]['alloc_ids'][] = $alloc['id'];
    }
    if (!in_array($alloc['slip_no'], $activeCustodians[$empKey]['slips'])) {
        $activeCustodians[$empKey]['slips'][] = $alloc['slip_no'];
    }
    foreach ($alloc['assets'] as $ast) {
        $ast['slip_no'] = $alloc['slip_no'];
        $ast['alloc_id'] = $alloc['id'];
        $activeCustodians[$empKey]['all_assets'][] = $ast;
    }
    foreach ($alloc['accessories'] as $acc) {
        if (is_array($acc)) {
            $acc['slip_no'] = $alloc['slip_no'];
            $acc['alloc_id'] = $alloc['id'];
            $activeCustodians[$empKey]['all_accessories'][] = $acc;
        } else {
            $activeCustodians[$empKey]['all_accessories'][] = [
                'name'     => $acc,
                'qty'      => 1,
                'category' => 'Accessories',
                'slip_no'  => $alloc['slip_no'],
                'alloc_id' => $alloc['id']
            ];
        }
    }
}

// 5. Fetch Active Asset Transfers from dedicated asset_transfers table
$transfersList = [];
if (isset($conn) && $conn !== false) {
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

    // Ensure optional columns exist
    $colCheckSql = "
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_transfers' AND COLUMN_NAME = 'status')
    BEGIN
        ALTER TABLE asset_transfers ADD status NVARCHAR(50) NOT NULL DEFAULT 'Completed';
    END
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'asset_transfers' AND COLUMN_NAME = 'transfer_type')
    BEGIN
        ALTER TABLE asset_transfers ADD transfer_type NVARCHAR(50) NOT NULL DEFAULT 'Employee Reassignment';
    END";
    sqlsrv_query($conn, $colCheckSql);

    $transfersQuery = "SELECT id, transfer_slip_no, 
                              CONVERT(VARCHAR(10), transfer_date, 120) AS transfer_date,
                              status, transfer_type,
                              source_employee_id, source_employee_name, source_emp_code, source_department, source_designation, source_location,
                              target_employee_id, target_employee_name, target_emp_code, target_department, target_designation, target_location,
                              total_assets, total_accessories, assets_json, accessories_json,
                              reason, processed_by,
                              CONVERT(VARCHAR(19), created_at, 120) AS created_at
                       FROM asset_transfers
                       ORDER BY id DESC";
    $trStmt = sqlsrv_query($conn, $transfersQuery);
    if ($trStmt !== false) {
        while ($row = sqlsrv_fetch_array($trStmt, SQLSRV_FETCH_ASSOC)) {
            $assets = !empty($row['assets_json']) ? (json_decode($row['assets_json'], true) ?: []) : [];
            foreach ($assets as &$ast) {
                $aId = isset($ast['id']) ? intval($ast['id']) : 0;
                $aTag = isset($ast['tag']) ? strtoupper(trim($ast['tag'])) : '';
                $master = ($aId > 0 && isset($masterAssetsMap[$aId])) 
                    ? $masterAssetsMap[$aId] 
                    : (($aTag && isset($masterAssetsByTag[$aTag])) ? $masterAssetsByTag[$aTag] : null);
                if ($master && (empty($ast['serial']) || $ast['serial'] === '—' || $ast['serial'] === '-')) {
                    $ast['serial'] = (!empty($master['serial']) && $master['serial'] !== '—') ? $master['serial'] : '—';
                }
            }
            unset($ast);
            $accessories = !empty($row['accessories_json']) ? (json_decode($row['accessories_json'], true) ?: []) : [];

            $transfersList[] = [
                'id'               => intval($row['id']),
                'transfer_slip_no' => $row['transfer_slip_no'],
                'transfer_date'    => $row['transfer_date'],
                'status'           => $row['status'] ?? 'Completed',
                'transfer_type'    => $row['transfer_type'] ?? 'Employee Reassignment',
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
        sqlsrv_free_stmt($trStmt);
    }
}

// Compute KPI Stats
$transferStats = [
    'total_transfers'    => count($transfersList),
    'completed'          => 0,
    'in_transit'         => 0,
    'pending_signoff'    => 0,
    'inter_branch'       => 0,
    'active_custodians'  => count($activeCustodians)
];

foreach ($transfersList as $t) {
    $st = $t['status'] ?? 'Completed';
    $tt = $t['transfer_type'] ?? 'Employee Reassignment';
    if ($st === 'Completed') {
        $transferStats['completed']++;
    } elseif ($st === 'In Transit') {
        $transferStats['in_transit']++;
    } elseif ($st === 'Pending Sign-off') {
        $transferStats['pending_signoff']++;
    }
    if ($tt === 'Inter-Branch Relocation') {
        $transferStats['inter_branch']++;
    }
}

// Avatar Colors Helper
$avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];
function getAvatarBgColor($name) {
    global $avatarColors;
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
    }
    return $avatarColors[abs($hash) % count($avatarColors)];
}

function getInitialsShort($name) {
    $parts = explode(' ', trim($name));
    $inits = '';
    foreach ($parts as $p) {
        if (!empty($p)) $inits .= strtoupper($p[0]);
        if (strlen($inits) >= 2) break;
    }
    return $inits ?: 'TR';
}

// Include Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- =========================================================================
     ASSET TRANSFER & RELOCATION WORKSPACE
     ========================================================================= -->
<main class="dashboard-content">

    <!-- Page Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="assets.php">Assets</a>
                <span>/</span>
                <span>Asset Transfer &amp; Relocation</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M16 3h5v5"></path>
                    <path d="M4 20L21 3"></path>
                    <path d="M21 16v5h-5"></path>
                    <path d="M15 15l6 6"></path>
                    <path d="M4 4l5 5"></path>
                </svg>
                Asset Transfer &amp; Relocation
            </h1>
            <p>Track hardware handovers between employees, departments, and inter-branch corporate transfers with gate passes.</p>
        </div>

        <div class="header-action-group">
            <button type="button" class="btn-secondary" id="exportTransfersBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button type="button" class="btn-primary" id="openInitiateTransferBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 1l4 4-4 4"></path>
                    <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                    <path d="M7 23l-4-4 4-4"></path>
                    <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                </svg>
                Initiate Asset Transfer
            </button>
        </div>
    </div>

    <!-- Top KPI Stat Cards -->
    <div class="transfer-stats-grid">
        <div class="transfer-stat-card card-total" data-tab="all">
            <div class="transfer-stat-info">
                <div class="stat-lbl">Total Transfers</div>
                <div class="stat-val" id="kpiTotalTransfers"><?php echo $transferStats['total_transfers']; ?></div>
                <div class="stat-sub">
                    <span>Gate passes recorded</span>
                </div>
            </div>
            <div class="transfer-stat-icon indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 3 21 3 21 8"></polyline>
                    <line x1="4" y1="20" x2="21" y2="3"></line>
                    <polyline points="21 16 21 21 16 21"></polyline>
                    <line x1="15" y1="15" x2="21" y2="21"></line>
                    <line x1="4" y1="4" x2="9" y2="9"></line>
                </svg>
            </div>
        </div>

        <div class="transfer-stat-card card-completed" data-tab="completed">
            <div class="transfer-stat-info">
                <div class="stat-lbl">Completed Handover</div>
                <div class="stat-val" id="kpiCompletedTransfers" style="color: #059669;"><?php echo $transferStats['completed']; ?></div>
                <div class="stat-sub">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Recipient acknowledged</span>
                </div>
            </div>
            <div class="transfer-stat-icon emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
        </div>

        <div class="transfer-stat-card card-transit" data-tab="in-transit">
            <div class="transfer-stat-info">
                <div class="stat-lbl">In Transit / Logistics</div>
                <div class="stat-val" id="kpiInTransitTransfers" style="color: #d97706;"><?php echo $transferStats['in_transit']; ?></div>
                <div class="stat-sub">
                    <span>Dispatched via courier</span>
                </div>
            </div>
            <div class="transfer-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
            </div>
        </div>

        <div class="transfer-stat-card card-custodians" data-tab="custodians">
            <div class="transfer-stat-info">
                <div class="stat-lbl">Active Custodians</div>
                <div class="stat-val" id="kpiActiveCustodians" style="color: var(--cyan-primary);"><?php echo $transferStats['active_custodians']; ?></div>
                <div class="stat-sub">
                    <span>Eligible for transfer</span>
                </div>
            </div>
            <div class="transfer-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
        </div>
    </div>


    <!-- Search & Filter Toolbar (Matches asset_return.php) -->
    <div class="asset-toolbar" style="margin-bottom: 16px;">
        <div class="toolbar-left" style="flex-wrap: wrap;">
            <!-- Live Search -->
            <div class="asset-search-wrapper" style="min-width: 280px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="transferSearchInput" placeholder="Search by Transfer Slip, Tag, Custodian, Branch...">
            </div>


        </div>

        <div class="toolbar-right">
            <span style="font-size: 12px; color: var(--text-muted);" id="transferTableCountText">Showing <?php echo count($transfersList); ?> records</span>
        </div>
    </div>

    <!-- Main Transfers Table Card -->
    <div class="transfers-table-card">
        <div class="transfers-table-header">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                Transfer &amp; Relocation Records
            </h2>
            <div id="transferTableCountText" style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                Showing <?php echo count($transfersList); ?> transfer records
            </div>
        </div>

        <div class="transfers-table-container">
            <table class="transfers-table">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllTransfers" class="custom-checkbox">
                        </th>
                        <th style="width: 140px;">Transfer Slip &amp; Date</th>
                        <th style="min-width: 200px;">Transferred Equipment</th>
                        <th style="min-width: 180px;">Source Custodian / Branch</th>
                        <th style="width: 50px; text-align: center;">Route</th>
                        <th style="min-width: 180px;">Target Custodian / Branch</th>
                        <th style="width: 90px; text-align: center; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>
                <tbody id="transfersTbody">
                    <?php if (empty($transfersList)): ?>
                        <tr>
                            <td colspan="7">
                                <div style="padding: 42px 20px; text-align: center; color: var(--text-muted);">
                                    <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.7;">🔄</div>
                                    <div style="font-size: 15px; font-weight: 700; color: var(--navy-primary);">No Transfer Records Found</div>
                                    <div style="font-size: 12.5px; margin-top: 4px;">No asset transfer records currently available. Click &quot;Initiate Asset Transfer&quot; above to create one.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                    <?php foreach ($transfersList as $t): 
                        $totalAst = count($t['assets']);
                        $totalAcc = 0;
                        foreach ($t['accessories'] as $ac) {
                            $totalAcc += is_array($ac) ? intval($ac['qty'] ?? 1) : 1;
                        }

                        $srcBg = getAvatarBgColor($t['source_custodian']['name']);
                        $srcInit = getInitialsShort($t['source_custodian']['name']);

                        $tgtBg = getAvatarBgColor($t['target_custodian']['name']);
                        $tgtInit = getInitialsShort($t['target_custodian']['name']);
                    ?>
                        <tr data-id="<?php echo $t['id']; ?>">
                            <td style="text-align: center;">
                                <input type="checkbox" class="custom-checkbox row-select-checkbox" value="<?php echo $t['id']; ?>">
                            </td>
                            <td>
                                <div class="slip-cell">
                                    <span class="transfer-slip-badge" onclick="openTransferDrawer(<?php echo $t['id']; ?>)">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line></svg>
                                        <?php echo htmlspecialchars($t['transfer_slip_no']); ?>
                                    </span>
                                    <div class="slip-date">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <?php echo htmlspecialchars($t['transfer_date']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="count-pill-wrap" style="display: flex; gap: 6px; margin-bottom: 4px;">
                                        <?php if ($totalAst > 0): ?>
                                            <span class="kitna-badge asset-kitna-badge">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                                <strong><?php echo $totalAst; ?> Asset</strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($totalAcc > 0): ?>
                                            <span class="kitna-badge acc-kitna-badge">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"></rect></svg>
                                                <strong><?php echo $totalAcc; ?> Acc</strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chips-compact-list">
                                        <?php foreach ($t['assets'] as $a): ?>
                                            <span class="chip-device" title="<?php echo htmlspecialchars($a['name']); ?> (SN: <?php echo htmlspecialchars($a['serial']); ?>)">
                                                <span class="chip-tag"><?php echo htmlspecialchars($a['tag']); ?></span>
                                                <span class="chip-device-name"><?php echo htmlspecialchars($a['name']); ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="route-custodian-cell">
                                    <div class="route-avatar" style="background: <?php echo $srcBg; ?>;">
                                        <?php echo htmlspecialchars($srcInit); ?>
                                    </div>
                                    <div class="route-info">
                                        <div class="route-name">
                                            <?php echo htmlspecialchars($t['source_custodian']['name']); ?>
                                            <span class="emp-code-badge"><?php echo htmlspecialchars($t['source_custodian']['emp_code']); ?></span>
                                        </div>
                                        <div class="route-meta">
                                            <?php echo htmlspecialchars($t['source_custodian']['department']); ?> • <?php echo htmlspecialchars($t['source_custodian']['location']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="route-arrow-pill" title="Transferred to">➔</span>
                            </td>
                            <td>
                                <div class="route-custodian-cell">
                                    <div class="route-avatar" style="background: <?php echo $tgtBg; ?>;">
                                        <?php echo htmlspecialchars($tgtInit); ?>
                                    </div>
                                    <div class="route-info">
                                        <div class="route-name">
                                            <?php echo htmlspecialchars($t['target_custodian']['name']); ?>
                                            <span class="emp-code-badge"><?php echo htmlspecialchars($t['target_custodian']['emp_code']); ?></span>
                                        </div>
                                        <div class="route-meta">
                                            <?php echo htmlspecialchars($t['target_custodian']['department']); ?> • <?php echo htmlspecialchars($t['target_custodian']['location']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div class="action-btn-group" style="display: inline-flex; flex-direction: row; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;">
                                    <button type="button" class="action-icon-btn" title="View Transfer Details" onclick="openTransferDrawer(<?php echo $t['id']; ?>)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn" title="Print Transfer Slip" onclick="printTransferReceipt(<?php echo $t['id']; ?>)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- =========================================================================
     MODAL: INITIATE ASSET TRANSFER & RELOCATION
     ========================================================================= -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-box-large">
        <div class="modal-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M17 1l4 4-4 4"></path>
                    <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                    <path d="M7 23l-4-4 4-4"></path>
                    <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                </svg>
                Initiate Asset Transfer &amp; Relocation
            </h3>
            <button type="button" class="modal-close-btn" id="closeTransferModalBtn">&times;</button>
        </div>

        <!-- 3-Step Wizard Navigation Tabs -->
        <div class="modal-tabs-nav">
            <button type="button" class="modal-tab-btn active" data-tab="source">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Source Custodian
            </button>
            <button type="button" class="modal-tab-btn" data-tab="equipment">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                Equipment Checklist
                <span class="badge-pill" id="transferTabBadge" style="display: none;">0</span>
            </button>
            <button type="button" class="modal-tab-btn" data-tab="destination">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Target Destination
            </button>
        </div>

        <form id="transferForm">
            <div class="modal-body">
                <!-- Tab Pane 1: Source Custodian & Setup -->
                <div class="modal-tab-pane active" id="modal_pane_source">
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label for="selectSourceCustodian">Choose Source Employee Custodian *</label>
                        <select id="selectSourceCustodian" required style="width: 100%;">
                            <option value="">-- Select Source Custodian --</option>
                            <?php foreach ($activeCustodians as $cust): ?>
                                <option value="<?php echo htmlspecialchars($cust['emp_key']); ?>"
                                        data-emp-id="<?php echo htmlspecialchars($cust['employee_id']); ?>"
                                        data-emp-key="<?php echo htmlspecialchars($cust['emp_key']); ?>"
                                        data-emp-name="<?php echo htmlspecialchars($cust['employee_name']); ?>"
                                        data-emp-code="<?php echo htmlspecialchars($cust['emp_code']); ?>"
                                        data-dept="<?php echo htmlspecialchars($cust['department']); ?>"
                                        data-desig="<?php echo htmlspecialchars($cust['designation'] ?? 'Staff'); ?>"
                                        data-loc="<?php echo htmlspecialchars($cust['location'] ?? 'HO DELHI'); ?>"
                                        data-slips='<?php echo json_encode($cust['slips'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                        data-assets='<?php echo json_encode($cust['all_assets'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                        data-acc='<?php echo json_encode($cust['all_accessories'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                    <?php echo htmlspecialchars($cust['employee_name']); ?> (<?php echo htmlspecialchars($cust['emp_code']); ?> • <?php echo htmlspecialchars($cust['department']); ?>) — <?php echo count($cust['all_assets']); ?> Asset(s), <?php echo count($cust['all_accessories']); ?> Acc
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Live Source Custodian Summary Card -->
                    <div class="preview-summary-card" id="sourcePreviewBox" style="display: none; margin-bottom: 16px;">
                        <div class="preview-summary-title">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Source Custodian Summary
                        </div>
                        <div class="preview-summary-content">
                            <div class="preview-avatar" id="sourceAvatar" style="background: var(--cyan-primary);">ST</div>
                            <div class="preview-details">
                                <div class="preview-row-top">
                                    <span class="preview-name" id="sourceName">-</span>
                                    <span class="preview-badge-code" id="sourceCode">-</span>
                                    <span class="preview-badge-branch" id="sourceBranchBadge">Branch: HO DELHI</span>
                                </div>
                                <div class="preview-subtext" id="sourceMeta">-</div>
                                <div class="preview-slips-row">
                                    <span>Active Slips:</span>
                                    <div class="preview-slips-list" id="sourceSlipsBadges"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label for="modalTransferDate">Transfer Date *</label>
                        <input type="date" id="modalTransferDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- Tab Pane 2: Equipment & Accessories Checklist -->
                <div class="modal-tab-pane" id="modal_pane_equipment">
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Select Equipment &amp; Accessories To Transfer</span>
                            <span class="asset-count-badge" id="transferSelectionSummary" style="background: #e0f2fe; color: #0284c7; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700;">Select items</span>
                        </label>

                        <div class="return-items-table-wrap">
                            <table class="return-items-table">
                                <thead>
                                    <tr>
                                        <th style="width: 36px; text-align: center;">
                                            <input type="checkbox" id="selectAllTransferItems" checked title="Select All">
                                        </th>
                                        <th style="width: 130px;">Handover Slip</th>
                                        <th>Item &amp; Description</th>
                                        <th style="width: 85px; text-align: center;">Type</th>
                                        <th style="width: 145px;">Tag / Serial No</th>
                                        <th style="width: 50px; text-align: center;">Qty</th>
                                        <th style="width: 90px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="transferItemsTbody">
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; font-style: italic;">
                                            Please choose a source employee custodian in Step 1 to load equipment checklist.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 3: Target Destination & Sign-off -->
                <div class="modal-tab-pane" id="modal_pane_destination">
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label for="selectTargetEmployee">Target Recipient Employee *</label>
                        <select id="selectTargetEmployee" required style="width: 100%;">
                            <option value="">-- Select Recipient Employee --</option>
                            <?php foreach ($employeesList as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['id']); ?>"
                                        data-name="<?php echo htmlspecialchars($emp['name']); ?>"
                                        data-code="<?php echo htmlspecialchars($emp['emp_code']); ?>"
                                        data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                                        data-desig="<?php echo htmlspecialchars($emp['designation']); ?>"
                                        data-loc="<?php echo htmlspecialchars($emp['location']); ?>">
                                    <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['emp_code']); ?> • <?php echo htmlspecialchars($emp['department']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Live Target Recipient Summary Card -->
                    <div class="preview-summary-card" id="targetPreviewBox" style="display: none; margin-bottom: 16px;">
                        <div class="preview-summary-title">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                            Target Recipient Summary
                        </div>
                        <div class="preview-summary-content">
                            <div class="preview-avatar" id="targetAvatar" style="background: #10b981;">TR</div>
                            <div class="preview-details">
                                <div class="preview-row-top">
                                    <span class="preview-name" id="targetName">-</span>
                                    <span class="preview-badge-code" id="targetCode">-</span>
                                    <span class="preview-badge-branch" id="targetBranchBadge">Branch: HO DELHI</span>
                                </div>
                                <div class="preview-subtext" id="targetMeta">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label for="modalTransferNotes">Transfer Reason &amp; Handover Notes</label>
                        <textarea id="modalTransferNotes" rows="2" placeholder="e.g. Role reassignment, project reallocation, inter-branch deployment..."></textarea>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="transferSignoffCheck" class="custom-checkbox" checked>
                        <label for="transferSignoffCheck" style="margin: 0; font-size: 12.5px; color: var(--navy-primary); font-weight: 600; cursor: pointer;">
                            I verify that physical hardware verification and custodian sign-off policies have been satisfied.
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelTransferModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="submitTransferBtn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirm &amp; Complete Transfer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     DRAWER: TRANSFER SLIP & RELOCATION DETAILS (SLIDE-OVER)
     ========================================================================= -->
<div class="drawer-backdrop" id="transferDrawerBackdrop"></div>
<aside class="transfer-drawer" id="transferDrawer">
    <div class="drawer-header">
        <h3>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);">
                <polyline points="16 3 21 3 21 8"></polyline>
                <line x1="4" y1="20" x2="21" y2="3"></line>
            </svg>
            Transfer Slip: <span id="drawerTransferSlipTitle" style="color: #4f46e5; margin-left: 4px;">TRF-2026-0001</span>
        </h3>
        <button type="button" class="modal-close-btn" id="closeTransferDrawerBtn">&times;</button>
    </div>

    <div class="drawer-body">
        <!-- Route Comparison (From -> To) -->
        <div class="route-comparison-card">
            <div class="route-column">
                <div class="route-label">From (Source)</div>
                <div class="route-person" id="drawerSrcPerson">-</div>
                <div class="route-dept" id="drawerSrcDept">-</div>
            </div>
            <div style="font-size: 20px; color: var(--cyan-primary); font-weight: 800; text-align: center;">➔</div>
            <div class="route-column">
                <div class="route-label">To (Recipient)</div>
                <div class="route-person" id="drawerTgtPerson">-</div>
                <div class="route-dept" id="drawerTgtDept">-</div>
            </div>
        </div>

        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Transfer Execution Date</div>
                <div style="font-size: 13.5px; font-weight: 700; color: var(--navy-primary); margin-top: 2px;" id="drawerDate">-</div>
            </div>
            <span class="badge-pill" id="drawerItemCountBadge" style="background:#e0f2fe; color:#0284c7; font-weight:700; font-size:11.5px; padding:3px 10px; border-radius:10px;">1 Item</span>
        </div>

        <!-- Transferred Equipment List -->
        <div style="margin-bottom: 20px;">
            <div style="font-size: 13px; font-weight: 700; color: var(--navy-primary); margin-bottom: 10px;">
                Transferred Hardware &amp; Peripherals
            </div>
            <div id="drawerEquipmentList" style="display: flex; flex-direction: column; gap: 8px;"></div>
        </div>

        <!-- Notes / Reason -->
        <div>
            <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">Transfer Reason &amp; Remarks</div>
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;" id="drawerNotes">
                -
            </div>
        </div>
    </div>

    <div class="modal-footer" style="background: #ffffff; padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between;">
        <button type="button" class="btn-secondary" id="drawerPrintBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Transfer Slip
        </button>
        <button type="button" class="btn-primary" onclick="closeTransferDrawer()">Close</button>
    </div>
</aside>

<!-- Embedded Client JSON Data -->
<script>
    window.TRANSFER_DATA = <?php echo json_encode($transfersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.ACTIVE_CUSTODIANS = <?php echo json_encode(array_values($activeCustodians), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.EMPLOYEES_DATA = <?php echo json_encode($employeesList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Layout Footer
include 'includes/footer.php';
?>
