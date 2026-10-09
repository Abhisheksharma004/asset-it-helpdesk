<?php
// Asset Management & IT Service Desk Portal - Dedicated Asset Return & Depot Restock Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Asset Return & Depot Restock - VIROS Portal";
$active_page = "asset_return";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/searchable-select.css', 'css/asset_assignment.css', 'css/asset_return.css'];
$extra_js  = ['js/asset_return.js'];

// Include database to fetch dynamic data
require_once __DIR__ . '/config/db.php';

$departmentsList = [];
$locationsList = [];
$returnedAllocations = [];
$activeAllocations = [];
$availableStockCount = 0;

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

    // 3. Count currently available assets in stock
    $stockStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM assets WHERE status = 'Available'");
    if ($stockStmt !== false && ($sRow = sqlsrv_fetch_array($stockStmt, SQLSRV_FETCH_ASSOC))) {
        $availableStockCount = intval($sRow['total']);
        sqlsrv_free_stmt($stockStmt);
    }

    // 4. Fetch processed returns from dedicated asset_returns table
    $seenAssignmentIds = [];
    $returnsSql = "SELECT id, return_slip_no, assignment_id, original_slip_no,
                          employee_id, employee_name, emp_code, department, designation, allocation_type,
                          CONVERT(VARCHAR(10), assigned_date, 120) AS assigned_date,
                          CONVERT(VARCHAR(10), return_date, 120) AS return_date,
                          storage_location, return_condition, restock_status,
                          diag_power_boot, diag_display, diag_battery, diag_keyboard_trackpad, diag_ports_audio, diag_connectivity,
                          diag_storage_wiped, diag_locks_removed, diag_data_backup, diag_body_hinges, diag_oem_charger, diag_asset_tag,
                          diag_pass_count, diag_total_count, diag_checklist_json,
                          total_returned_assets, total_returned_accessories,
                          returned_assets_json, returned_accessories_json,
                          inspection_notes, custodian_signoff, processed_by,
                          CONVERT(VARCHAR(19), created_at, 120) AS created_at
                   FROM asset_returns
                   ORDER BY id DESC";
    $retStmt = sqlsrv_query($conn, $returnsSql);
    if ($retStmt !== false) {
        while ($r = sqlsrv_fetch_array($retStmt, SQLSRV_FETCH_ASSOC)) {
            $assetsList = !empty($r['returned_assets_json']) ? (json_decode($r['returned_assets_json'], true) ?: []) : [];
            $accList = !empty($r['returned_accessories_json']) ? (json_decode($r['returned_accessories_json'], true) ?: []) : [];
            $chkList = !empty($r['diag_checklist_json']) ? (json_decode($r['diag_checklist_json'], true) ?: []) : [];

            if (!empty($r['assignment_id'])) {
                $seenAssignmentIds[] = intval($r['assignment_id']);
            }

            $returnedAllocations[] = [
                'id'                 => intval($r['id']),
                'return_slip_no'     => $r['return_slip_no'],
                'slip_no'            => $r['return_slip_no'],
                'original_slip_no'   => $r['original_slip_no'],
                'assignment_id'      => $r['assignment_id'] ? intval($r['assignment_id']) : null,
                'employee_id'        => $r['employee_id'] ? intval($r['employee_id']) : null,
                'employee_name'      => $r['employee_name'],
                'emp_code'           => $r['emp_code'] ?? 'EMP-0000',
                'department'         => $r['department'] ?? 'General',
                'designation'        => $r['designation'] ?? 'Staff',
                'allocation_type'    => $r['allocation_type'] ?? 'Permanent',
                'assigned_date'      => $r['assigned_date'] ?? '-',
                'return_date'        => $r['return_date'],
                'storage_location'   => $r['storage_location'] ?? 'Storage Depot',
                'custody_status'     => 'Returned',
                'return_condition'   => $r['return_condition'] ?? 'Good',
                'restock_status'     => $r['restock_status'] ?? 'Restocked in Stock',
                'diag_pass_count'    => intval($r['diag_pass_count'] ?? 12),
                'diag_total_count'   => intval($r['diag_total_count'] ?? 12),
                'checklist'          => $chkList,
                'diag_flags'         => [
                    'power'        => (bool)$r['diag_power_boot'],
                    'display'      => (bool)$r['diag_display'],
                    'battery'      => (bool)$r['diag_battery'],
                    'keyboard'     => (bool)$r['diag_keyboard_trackpad'],
                    'ports'        => (bool)$r['diag_ports_audio'],
                    'connectivity' => (bool)$r['diag_connectivity'],
                    'wipe'         => (bool)$r['diag_storage_wiped'],
                    'locks'        => (bool)$r['diag_locks_removed'],
                    'backup'       => (bool)$r['diag_data_backup'],
                    'chassis'      => (bool)$r['diag_body_hinges'],
                    'charger'      => (bool)$r['diag_oem_charger'],
                    'tag'          => (bool)$r['diag_asset_tag']
                ],
                'total_assets'       => intval($r['total_returned_assets']),
                'total_accessories'  => intval($r['total_returned_accessories']),
                'assets'             => $assetsList,
                'accessories'        => $accList,
                'inspection_notes'   => $r['inspection_notes'] ?? '',
                'return_notes'       => $r['inspection_notes'] ?? 'Equipment inspected and returned.',
                'processed_by'       => $r['processed_by'] ?? 'IT Administrator',
                'created_at'         => $r['created_at']
            ];
        }
        sqlsrv_free_stmt($retStmt);
    }

    // 5. Fetch all allocations to get Active assignments and legacy returns
    $allocQuery = "SELECT id, slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
                          employee_id, employee_name, emp_code, employee_email, department, designation, location,
                          CONVERT(VARCHAR(10), assigned_date, 120) AS assigned_date,
                          allocation_type,
                          CONVERT(VARCHAR(10), expected_return, 120) AS expected_return,
                          CONVERT(VARCHAR(10), return_date, 120) AS return_date,
                          custody_status, condition, return_condition,
                          total_assets, total_accessories, assets_json, accessories_json,
                          handover_by, agreement_signed, notes, return_notes
                   FROM asset_assignments
                   ORDER BY id DESC";
    
    $allocStmt = sqlsrv_query($conn, $allocQuery);
    if ($allocStmt !== false) {
        while ($row = sqlsrv_fetch_array($allocStmt, SQLSRV_FETCH_ASSOC)) {
            // Decode assets
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

            // Decode accessories
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

            $item = [
                'id'                => intval($row['id']),
                'slip_no'           => $row['slip_no'],
                'original_slip_no'  => $row['slip_no'],
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
                'return_date'       => $row['return_date'] ?: $row['assigned_date'],
                'custody_status'    => $row['custody_status'],
                'condition'         => $row['condition'] ?? 'Good',
                'return_condition'  => $row['return_condition'] ?: ($row['condition'] ?? 'Good'),
                'total_assets'      => $totalAssets,
                'total_accessories' => $totalAcc,
                'assets'            => $assetsList,
                'accessories'       => $accList,
                'handover_by'       => $row['handover_by'] ?? 'IT Administrator',
                'notes'             => $row['notes'] ?? '',
                'return_notes'      => $row['return_notes'] ?? 'Equipment inspected and returned to storage depot.'
            ];

            $isReturned = ($row['custody_status'] === 'Returned') || in_array(intval($row['id']), $seenAssignmentIds);
            $isTransferred = ($row['custody_status'] === 'Transferred') || (stripos($row['custody_status'] ?? '', 'Transferred') !== false);

            if ($isReturned) {
                if (!in_array(intval($row['id']), $seenAssignmentIds)) {
                    $returnedAllocations[] = $item;
                }
            } elseif (!$isTransferred) {
                $activeAllocations[] = $item;
            }
        }
        sqlsrv_free_stmt($allocStmt);
    }
}

// Compute unique active custodians grouping all their slips, assets & accessories
$activeCustodians = [];
foreach ($activeAllocations as $alloc) {
    $empKey = !empty($alloc['emp_code']) ? $alloc['emp_code'] : ($alloc['employee_id'] ? 'ID_'.$alloc['employee_id'] : $alloc['employee_name']);
    if (!isset($activeCustodians[$empKey])) {
        $activeCustodians[$empKey] = [
            'emp_key'        => $empKey,
            'employee_id'    => $alloc['employee_id'],
            'employee_name'  => $alloc['employee_name'],
            'emp_code'       => $alloc['emp_code'],
            'employee_email' => $alloc['employee_email'] ?? '',
            'department'     => $alloc['department'],
            'designation'    => $alloc['designation'],
            'location'       => $alloc['location'] ?? 'Corporate HQ',
            'allocation_type'=> $alloc['allocation_type'] ?? 'Permanent',
            'assigned_date'  => $alloc['assigned_date'],
            'alloc_ids'      => [],
            'slips'          => [],
            'all_assets'     => [],
            'all_accessories'=> []
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

// Keep only custodians who actually hold hardware equipment or accessories
$activeCustodians = array_values(array_filter($activeCustodians, function ($cust) {
    return (count($cust['all_assets']) > 0 || count($cust['all_accessories']) > 0);
}));

// Compute live return metrics
$returnStats = [
    'total_returned'     => count($returnedAllocations),
    'restocked_good'     => 0,
    'needs_repair'       => 0,
    'active_custodians'  => count($activeCustodians),
    'available_stock'    => $availableStockCount
];

foreach ($returnedAllocations as $ret) {
    $c = strtolower($ret['return_condition'] ?? '');
    if (strpos($c, 'repair') !== false || strpos($c, 'damaged') !== false) {
        $returnStats['needs_repair']++;
    } else {
        $returnStats['restocked_good']++;
    }
}

// Avatar Colors Helper
$avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];

// Include Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- =========================================================================
     ASSET RETURN & RESTOCK WORKSPACE
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
                <span>Asset Return & Restock</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <polyline points="9 14 4 9 9 4"></polyline>
                    <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                </svg>
                Asset Return & Depot Restock
            </h1>
            <p>Manage hardware check-in from employees, diagnostic condition grading, and depot inventory restock.</p>
        </div>
        <div class="header-action-group">
            <button type="button" class="btn-secondary" id="exportReturnsBtn" title="Export Return History to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export CSV
            </button>
            <button type="button" class="btn-secondary" onclick="window.location.href='asset_assignment.php'" title="Go to Asset Assignment Workspace">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                <span>Assign Asset</span>
            </button>
            <button type="button" class="btn-primary" id="openProcessReturnBtn" title="Process new hardware return from employee">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 14 4 9 9 4"></polyline>
                    <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                </svg>
                <span>Process New Return</span>
            </button>
        </div>
    </div>

    <!-- KPI Metric Stat Cards -->
    <div class="asset-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
        <!-- Processed Returns -->
        <div class="alloc-stat-card card-active" data-filter-tab="all" title="View all completed returns">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Processed Returns</div>
                <div class="stat-val" id="kpiTotalReturns"><?php echo $returnStats['total_returned']; ?></div>
                <div class="stat-sub">
                    <span style="color: #10b981; font-weight: 600;">●</span> Total check-in receipts
                </div>
            </div>
            <div class="alloc-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 14 4 9 9 4"></polyline>
                    <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                </svg>
            </div>
        </div>

        <!-- Restocked to Available Stock -->
        <div class="alloc-stat-card" style="border-left: 4px solid #059669;" data-filter-tab="restocked" title="Returned in Good condition and added back to stock">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Restocked to Stock</div>
                <div class="stat-val" id="kpiRestockedGood" style="color: #059669;"><?php echo $returnStats['restocked_good']; ?></div>
                <div class="stat-sub">
                    <span style="color: #10b981; font-weight: 600;">●</span> Ready to re-assign
                </div>
            </div>
            <div class="alloc-stat-icon emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
        </div>

        <!-- Needs Repair / Maintenance -->
        <div class="alloc-stat-card card-temporary" data-filter-tab="repair" title="Returned with defects or maintenance needed">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Needs Repair / Service</div>
                <div class="stat-val" id="kpiNeedsRepair" style="color: #d97706;"><?php echo $returnStats['needs_repair']; ?></div>
                <div class="stat-sub">
                    <span style="color: #ea580c; font-weight: 600;">●</span> Diagnostics / Service
                </div>
            </div>
            <div class="alloc-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
        </div>

        <!-- In-Stock Available Hardware -->
        <div class="alloc-stat-card card-available" id="cardAvailableInStock" title="Click to view all available in-stock devices ready for assignment" style="cursor: pointer;">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Depot Stock Available</div>
                <div class="stat-val" id="kpiAvailableDepot" style="color: #0891b2;"><?php echo $returnStats['available_stock']; ?></div>
                <div class="stat-sub">
                    <span style="color: #10b981; font-weight: 600;">●</span> In stock ready to deploy
                </div>
            </div>
            <div class="alloc-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
        </div>
    </div>


    <!-- Search & Filter Toolbar -->
    <div class="asset-toolbar" style="margin-bottom: 16px;">
        <div class="toolbar-left" style="flex-wrap: wrap;">
            <!-- Live Search -->
            <div class="asset-search-wrapper" style="min-width: 280px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="returnSearchInput" placeholder="Search by Return Slip, Employee, Asset Tag, Serial...">
            </div>

            <!-- Department Filter -->
            <select class="asset-filter-select" id="returnDeptFilter">
                <option value="all">All Departments</option>
                <?php foreach ($departmentsList as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Condition Filter -->
            <select class="asset-filter-select" id="returnConditionFilter">
                <option value="all">All Return Conditions</option>
                <option value="Brand New">Brand New</option>
                <option value="Excellent">Excellent</option>
                <option value="Good">Good</option>
                <option value="Needs Repair">Needs Repair</option>
                <option value="Damaged">Damaged</option>
            </select>

            <!-- Reset Button -->
            <button type="button" class="btn-secondary" id="resetReturnFiltersBtn" style="padding: 7px 12px; font-size: 12.5px;" title="Reset Filters">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Reset
            </button>
        </div>

        <div class="toolbar-right">
            <span style="font-size: 12px; color: var(--text-muted);" id="returnTableCountText">Showing <?php echo count($returnedAllocations); ?> records</span>
        </div>
    </div>

    <!-- Returns Registry Data Table Card -->
    <div class="asset-table-card">
        <div class="asset-table-responsive">
            <table class="asset-data-table">
                <thead>
                    <tr>
                        <th style="width: 36px;">
                            <input type="checkbox" class="custom-checkbox" id="selectAllReturns">
                        </th>
                        <th style="min-width: 140px;">Return Slip</th>
                        <th style="min-width: 220px;">Returning Custodian</th>
                        <th style="min-width: 230px;">Returned Assets</th>
                        <th style="min-width: 190px;">Returned Accessories</th>
                        <th style="min-width: 130px;">Return Date</th>
                        <th style="min-width: 130px;">Condition</th>
                        <th style="min-width: 140px;">Stock Status</th>
                        <th style="text-align: right; padding-right: 18px; min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="returnsTbody">
                    <?php foreach ($returnedAllocations as $ret): 
                        $c = strtolower($ret['return_condition'] ?? 'good');
                        $condClass = 'condition-good';
                        if (strpos($c, 'brand') !== false || strpos($c, 'excel') !== false) {
                            $condClass = 'condition-excellent';
                        } elseif (strpos($c, 'repair') !== false) {
                            $condClass = 'condition-repair';
                        } elseif (strpos($c, 'damag') !== false) {
                            $condClass = 'condition-damaged';
                        }

                        $isRestocked = (strpos($c, 'repair') === false && strpos($c, 'damag') === false);
                        $parts = explode(' ', trim($ret['employee_name']));
                        $inits = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                        $cHash = abs(crc32($ret['employee_name'] . ($ret['emp_code'] ?? '')));
                        $colorBg = $avatarColors[$cHash % count($avatarColors)];
                        $totalAssets = count($ret['assets']);
                        $totalAcc = intval($ret['total_accessories']);
                    ?>
                        <tr data-id="<?php echo $ret['id']; ?>">
                            <td>
                                <input type="checkbox" class="custom-checkbox row-select-checkbox" value="<?php echo $ret['id']; ?>">
                            </td>
                            <td>
                                <div class="slip-cell">
                                    <span class="slip-badge" onclick="openReturnDrawer(<?php echo $ret['id']; ?>)">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                                        <?php echo htmlspecialchars($ret['slip_no']); ?>
                                    </span>
                                    <div class="slip-date">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <?php echo htmlspecialchars($ret['return_date']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="custodian-cell">
                                    <div class="custodian-avatar" style="background: <?php echo $colorBg; ?>;">
                                        <?php echo htmlspecialchars($inits ?: 'ST'); ?>
                                    </div>
                                    <div class="custodian-info">
                                        <div class="custodian-name">
                                            <?php echo htmlspecialchars($ret['employee_name']); ?>
                                            <span class="emp-code-badge"><?php echo htmlspecialchars($ret['emp_code']); ?></span>
                                        </div>
                                        <div class="custodian-sub">
                                            <?php echo htmlspecialchars($ret['designation']); ?> • <strong><?php echo htmlspecialchars($ret['department']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="count-pill-wrap">
                                        <?php if ($totalAssets > 0): ?>
                                            <span class="kitna-badge asset-kitna-badge">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                                <strong><?php echo $totalAssets; ?> <?php echo $totalAssets === 1 ? 'Asset' : 'Assets'; ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="kitna-badge zero-kitna-badge">0 Assets</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chips-compact-list">
                                        <?php 
                                            $displayAst = array_slice($ret['assets'], 0, 2);
                                            foreach ($displayAst as $a): 
                                        ?>
                                            <span class="chip-device" title="<?php echo htmlspecialchars($a['name']); ?> (SN: <?php echo htmlspecialchars($a['serial'] ?? '—'); ?>)">
                                                <span class="chip-tag"><?php echo htmlspecialchars($a['tag']); ?></span>
                                                <span class="chip-device-name"><?php echo htmlspecialchars($a['name']); ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($ret['assets']) > 2): ?>
                                            <span class="chip-more-count" onclick="openReturnDrawer(<?php echo $ret['id']; ?>)">+<?php echo count($ret['assets']) - 2; ?> more device(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="count-pill-wrap">
                                        <?php if ($totalAcc > 0): ?>
                                            <span class="kitna-badge acc-kitna-badge">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                                <strong><?php echo $totalAcc; ?> <?php echo $totalAcc === 1 ? 'Accessory' : 'Accessories'; ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="kitna-badge zero-kitna-badge">0 Accessories</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chips-compact-list">
                                        <?php 
                                            $displayAcc = array_slice($ret['accessories'], 0, 2);
                                            foreach ($displayAcc as $acc): 
                                                $accName = is_array($acc) ? ($acc['name'] ?? 'Item') : $acc;
                                                $accQty = is_array($acc) && !empty($acc['qty']) ? intval($acc['qty']) : 1;
                                        ?>
                                            <span class="chip-acc-tag" title="<?php echo htmlspecialchars($accName); ?>">
                                                <span><?php echo htmlspecialchars($accName); ?></span>
                                                <span class="chip-acc-qty"><?php echo $accQty; ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($ret['accessories']) > 2): ?>
                                            <span class="chip-more-count" onclick="openReturnDrawer(<?php echo $ret['id']; ?>)">+<?php echo count($ret['accessories']) - 2; ?> more item(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 3px; align-items: flex-start;">
                                    <span style="font-weight: 600; font-size: 12.5px; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($ret['return_date']); ?>
                                    </span>
                                    <span style="font-size: 11px; color: var(--text-muted);">
                                        From: <?php echo htmlspecialchars($ret['assigned_date']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="condition-badge <?php echo $condClass; ?>">
                                    <span style="font-size: 8px;">●</span>
                                    <?php echo htmlspecialchars($ret['return_condition'] ?: 'Good'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isRestocked): ?>
                                    <div class="custody-badge status-active">
                                        <span class="dot" style="background: #10b981;"></span>
                                        <span>Restocked</span>
                                    </div>
                                <?php else: ?>
                                    <div class="custody-badge status-overdue">
                                        <span class="dot" style="background: #ef4444;"></span>
                                        <span>Under Service</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                    <button type="button" class="action-icon-btn btn-view" title="View Inspection Details" onclick="openReturnDrawer(<?php echo $ret['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-qr" title="Print Equipment Return Receipt" onclick="printReturnReceipt(<?php echo $ret['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($returnedAllocations)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="table-empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8" style="margin-bottom: 8px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                    <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">No Equipment Returns Processed Yet</div>
                                    <div style="font-size: 12.5px; margin-top: 4px;">Returned assets from custodians will automatically show up here and restock into inventory.</div>
                                    <button type="button" class="btn-primary" style="margin-top: 14px; padding: 7px 16px; font-size: 13px;" onclick="document.getElementById('openProcessReturnBtn')?.click()">Process New Return</button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- =========================================================================
     MODAL: PROCESS NEW ASSET RETURN (MATCHING ASSET ASSIGNMENT MODAL DESIGN)
     ========================================================================= -->
<div class="modal-overlay" id="processReturnModal" style="display: none;">
    <div class="modal-box" style="max-width: 780px;">
        <div class="modal-header">
            <h3 id="processReturnModalTitle">Process Asset Return & Restock</h3>
            <button type="button" class="modal-close-btn" id="closeProcessReturnModalBtn">&times;</button>
        </div>

        <!-- Modal Tab Headers (Exact pattern from asset_assignment.php) -->
        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-btn active" data-tab="custodian">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                Custodian &amp; Return Info
            </button>
            <button type="button" class="modal-tab-btn" data-tab="equipment">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                Equipment Checklist
                <span id="returnTabItemBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span>
            </button>
            <button type="button" class="modal-tab-btn" data-tab="diagnostics">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                Diagnostic &amp; Restock
            </button>
        </div>

        <form id="processReturnForm">
            <div class="modal-body">
                
                <!-- Tab Pane 1: Custodian & Return Details -->
                <div class="modal-tab-pane active" id="modal_pane_custodian">
                    <!-- Choose employee custodian to return from -->
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label for="selectActiveAlloc">Choose Active Employee Custodian *</label>
                        <select id="selectActiveAlloc" required style="width: 100%;">
                            <option value="">-- Select Employee Custodian --</option>
                            <?php foreach ($activeCustodians as $cust): ?>
                                <option value="<?php echo htmlspecialchars($cust['emp_key']); ?>"
                                        data-emp-key="<?php echo htmlspecialchars($cust['emp_key']); ?>"
                                        data-emp-name="<?php echo htmlspecialchars($cust['employee_name']); ?>"
                                        data-emp-code="<?php echo htmlspecialchars($cust['emp_code']); ?>"
                                        data-dept="<?php echo htmlspecialchars($cust['department']); ?>"
                                        data-desig="<?php echo htmlspecialchars($cust['designation'] ?? 'Staff'); ?>"
                                        data-slips='<?php echo json_encode($cust['slips'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                        data-alloc-ids='<?php echo json_encode($cust['alloc_ids'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                        data-assets='<?php echo json_encode($cust['all_assets'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                        data-acc='<?php echo json_encode($cust['all_accessories'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                    <?php echo htmlspecialchars($cust['employee_name']); ?> (<?php echo htmlspecialchars($cust['emp_code']); ?> • <?php echo htmlspecialchars($cust['department']); ?>) — <?php echo count($cust['slips']); ?> Slip(s) (<?php echo count($cust['all_assets']); ?> Assets, <?php echo count($cust['all_accessories']); ?> Acc)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Live Custodian Card Preview -->
                    <div class="preview-summary-card" id="allocPreviewBox" style="display: none; margin-bottom: 16px;">
                        <div class="preview-summary-title">Custodian Summary</div>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div class="custodian-avatar" id="previewEmpAvatar" style="width: 44px; height: 44px; font-size: 14px; flex-shrink: 0; background: var(--cyan-primary); color: #fff;">ST</div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span style="font-size: 14.5px; font-weight: 700; color: var(--navy-primary);" id="previewEmpName">-</span>
                                    <span class="emp-code-badge" id="previewEmpCode">-</span>
                                    <span class="alloc-pill permanent" id="previewAllocType">Active Custodian</span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;" id="previewEmpMeta">-</div>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span>Active Slips:</span>
                                    <div id="previewSlipsBadges" style="display: inline-flex; gap: 4px; flex-wrap: wrap;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalReturnDate">Return Date *</label>
                            <input type="date" id="modalReturnDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalStorageDepot">Storage Shelf / Depot Location *</label>
                            <input type="text" id="modalStorageDepot" value="Storage Depot (Rack A-01)" placeholder="e.g. Storage Depot Shelf B-04" required>
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 2: Equipment Checklist Table -->
                <div class="modal-tab-pane" id="modal_pane_equipment">
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Assigned Equipment &amp; Accessories Checklist</span>
                            <span class="asset-count-badge" id="previewSelectionSummary" style="background: #e0f2fe; color: #0284c7; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700;">All items selected</span>
                        </label>

                        <!-- Clean Table Layout for Items -->
                        <div class="return-items-table-wrap">
                            <table class="return-items-table">
                                <thead>
                                    <tr>
                                        <th style="width: 36px; text-align: center;">
                                            <input type="checkbox" id="selectAllReturnItems" checked title="Select All">
                                        </th>
                                        <th style="width: 130px;">Handover Slip</th>
                                        <th>Item &amp; Description</th>
                                        <th style="width: 85px; text-align: center;">Type</th>
                                        <th style="width: 145px;">Tag / Serial No</th>
                                        <th style="width: 50px; text-align: center;">Qty</th>
                                        <th style="width: 85px; text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="previewItemsTbody">
                                    <!-- Dynamic rows rendered by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Handover Notes (if any) -->
                    <div id="previewNotesWrap" style="display: none; margin-top: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px; color: var(--text-secondary);">
                        <strong style="color: var(--navy-primary);">Initial Handover Notes:</strong> <span id="previewNotesText">-</span>
                    </div>
                </div>

                <!-- Tab Pane 3: Diagnostics & Restock Quality -->
                <div class="modal-tab-pane" id="modal_pane_diagnostics">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalReturnCondition">Diagnostic & Physical Condition *</label>
                            <select id="modalReturnCondition" required>
                                <option value="Excellent">Excellent (No scratches, fully functional)</option>
                                <option value="Good" selected>Good (Minor wear, ready to deploy)</option>
                                <option value="Needs Repair">Needs Repair / Maintenance</option>
                                <option value="Damaged">Damaged / Non-functional</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label>Inventory Restock Automation</label>
                            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 8px 12px; font-size: 12px; color: #065f46; display: flex; align-items: center; gap: 8px; height: 38px; box-sizing: border-box;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Restocks to Available status in Depot</span>
                            </div>
                        </div>
                    </div>

                    <!-- Diagnostic Inspection Checklist -->
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                            <label style="margin-bottom: 0; font-weight: 700;">Pre-Restock Diagnostic & Quality Checklist</label>
                            <div style="display: flex; gap: 10px; font-size: 11.5px;">
                                <button type="button" id="btnCheckAllDiag" style="background: none; border: none; color: var(--cyan-primary); font-weight: 700; cursor: pointer; padding: 0;">
                                    Check All
                                </button>
                                <span style="color: #cbd5e1;">|</span>
                                <button type="button" id="btnUncheckAllDiag" style="background: none; border: none; color: #64748b; font-weight: 600; cursor: pointer; padding: 0;">
                                    Uncheck All
                                </button>
                            </div>
                        </div>

                        <div class="diagnostic-checklist-container">
                            <!-- Group 1: Hardware & Functional -->
                            <div class="diag-group">
                                <div class="diag-group-header">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                    Hardware & Functional Diagnostics
                                </div>
                                <div class="diag-group-items">
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkPower" class="diag-check" data-label="Power & Boot Test" checked> Power-on & boot test passed
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkDisplay" class="diag-check" data-label="Display Panel" checked> Display panel intact (no cracks / lines)
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkBattery" class="diag-check" data-label="Battery Health" checked> Battery healthy & holds charge
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkKeyboard" class="diag-check" data-label="Keyboard & Trackpad" checked> Keyboard & trackpad responsive
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkPorts" class="diag-check" data-label="I/O Ports & Audio" checked> USB, HDMI & Audio ports working
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkConnectivity" class="diag-check" data-label="Wi-Fi & Cam" checked> Wi-Fi, Bluetooth & webcam OK
                                    </label>
                                </div>
                            </div>

                            <!-- Group 2: Data Sanitization & Security -->
                            <div class="diag-group">
                                <div class="diag-group-header">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    Data Sanitization & Security
                                </div>
                                <div class="diag-group-items">
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkWipe" class="diag-check" data-label="Storage Wiped" checked> Storage drive wiped & clean OS reset
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkLocks" class="diag-check" data-label="Locks Removed" checked> BitLocker, BIOS & PIN locks removed
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkBackup" class="diag-check" data-label="Data Backup" checked> Employee data backup confirmed
                                    </label>
                                </div>
                            </div>

                            <!-- Group 3: Physical Condition & Kits -->
                            <div class="diag-group">
                                <div class="diag-group-header">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                                    Physical Condition & Kits
                                </div>
                                <div class="diag-group-items">
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkChassis" class="diag-check" data-label="Chassis & Hinges" checked> Body & hinges clean (no cracks/dents)
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkCharger" class="diag-check" data-label="OEM Charger" checked> OEM power adapter & cable returned
                                    </label>
                                    <label class="diagnostic-check-item">
                                        <input type="checkbox" id="chkAssetTag" class="diag-check" data-label="Asset Tag Barcode" checked> Asset barcode tag intact & verified
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="modalReturnNotes">Inspection Remarks / Handover Notes</label>
                        <textarea id="modalReturnNotes" rows="2" placeholder="e.g. Returned in clean condition, full diagnostic passed, wiped and added to Depot Shelf A."></textarea>
                    </div>

                    <!-- Sign-Off Agreement (Matching assignPolicyCheck in assignAssetModal) -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; margin-top: 10px; display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" id="returnPolicyCheck" required checked style="margin-top: 3px; accent-color: #16a34a; cursor: pointer; flex-shrink: 0;">
                        <label for="returnPolicyCheck" style="font-size: 12px; color: #166534; margin: 0; cursor: pointer; line-height: 1.4;">
                            <strong>IT Custodian Sign-Off:</strong> Devices and accessories have been physically inspected, diagnostic checklist verified, and are authorized for inventory check-in.
                        </label>
                    </div>
                </div>

            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn-secondary" id="cancelProcessReturnBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="submitProcessReturnBtn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm &amp; Check-In to Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     SLIDE-OVER DRAWER: RETURN INSPECTION DETAILS
     ========================================================================= -->
<div class="drawer-backdrop" id="returnDrawerBackdrop"></div>

<aside class="asset-drawer" id="returnDrawer">
    <div class="drawer-header" style="padding: 18px 22px; border-bottom: 1px solid var(--border-color); background: #ffffff; display: flex; align-items: flex-start; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 14px; width: calc(100% - 40px);">
            <div class="emp-avatar" id="drawerAvatar" style="width: 48px; height: 48px; font-size: 16px; font-weight: 700; border-radius: 50%; color: #ffffff; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #059669;">
                ST
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                    <span class="return-badge-slip" id="drawerSlipNo">-</span>
                    <span class="restock-pill available" id="drawerStockBadge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> Restocked in Stock</span>
                </div>
                <h3 id="drawerEmpName" style="margin: 0; font-size: 17px; font-weight: 700; color: var(--navy-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</h3>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="drawerEmpMeta">-</div>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeReturnDrawerBtn">&times;</button>
    </div>

    <div class="drawer-content" style="padding: 20px;">
        <!-- Restocked Hardware Units -->
        <div class="drawer-section">
            <div class="drawer-section-title" style="display: flex; align-items: center; justify-content: space-between;">
                <span>Restocked Hardware Devices (<span id="drawerDeviceCount">0</span>)</span>
            </div>
            <div id="drawerDevicesWrap" style="display: flex; flex-direction: column; gap: 10px;"></div>
        </div>

        <!-- Returned Accessories -->
        <div class="drawer-section">
            <div class="drawer-section-title">
                <span>Restocked Accessories</span>
            </div>
            <div id="drawerAccWrap" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
        </div>

        <!-- Inspection & Diagnostic Terms -->
        <div class="drawer-section">
            <div class="drawer-section-title">Return & Diagnostic Details</div>
            <div class="drawer-spec-grid">
                <div class="drawer-spec-item">
                    <div class="label">Return Date</div>
                    <div class="value" id="drawerReturnDate">-</div>
                </div>
                <div class="drawer-spec-item">
                    <div class="label">Initial Handover</div>
                    <div class="value" id="drawerAssignedDate">-</div>
                </div>
                <div class="drawer-spec-item">
                    <div class="label">Inspection Condition</div>
                    <div class="value" id="drawerCondition">-</div>
                </div>
                <div class="drawer-spec-item">
                    <div class="label">Allocation Type</div>
                    <div class="value" id="drawerAllocType">-</div>
                </div>
                <div class="drawer-spec-item">
                    <div class="label">Depot Storage Shelf</div>
                    <div class="value" id="drawerLocation">-</div>
                </div>
                <div class="drawer-spec-item">
                    <div class="label">Diagnostic Quality</div>
                    <div class="value" id="drawerDiagRate" style="color: #059669; font-weight: 700;">12 / 12 Passed</div>
                </div>
            </div>
        </div>

        <!-- Inspection Remarks -->
        <div class="drawer-section">
            <div class="drawer-section-title">Inspection & Diagnostic Notes</div>
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;" id="drawerNotes">
                -
            </div>
        </div>
    </div>

    <div class="drawer-footer" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-top: 1px solid var(--border-color); background: #ffffff;">
        <button type="button" class="btn-secondary" id="drawerPrintBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Return Slip
        </button>
        <button type="button" class="btn-primary" id="drawerAssignBtn" style="background: #059669; border-color: #047857;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Reassign to Staff
        </button>
    </div>
</aside>

<!-- Embedded JS Data -->
<script>
    window.RETURNED_DATA = <?php echo json_encode($returnedAllocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.ACTIVE_DATA = <?php echo json_encode($activeAllocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.ACTIVE_CUSTODIANS = <?php echo json_encode(array_values($activeCustodians), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Layout Footer
include 'includes/footer.php';
?>
