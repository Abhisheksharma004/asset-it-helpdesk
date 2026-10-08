<?php
// Asset Management & IT Service Desk Portal - Asset Assignment & Allocation Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Asset Assignment & Allocation - VIROS Portal";
$active_page = "asset_assignment";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/searchable-select.css', 'css/asset_assignment.css'];
$extra_js  = ['js/asset_assignment.js'];

// Include database to fetch dynamic data
require_once __DIR__ . '/config/db.php';

$departmentsList = [];
$locationsList = [];
$employeesList = [];
$availableAssets = [];
$availableAccessories = [];
$initialAssignments = [];

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

    // 4. Fetch available / in-stock assets for assignment dropdown
    $availStmt = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, location, specs = (processor + ' • ' + ram) 
                                      FROM assets 
                                      WHERE status = 'Available' 
                                      ORDER BY id DESC");
    if ($availStmt !== false) {
        while ($a = sqlsrv_fetch_array($availStmt, SQLSRV_FETCH_ASSOC)) {
            $availableAssets[] = [
                'id'        => intval($a['id']),
                'tag'       => $a['tag'],
                'name'      => $a['name'],
                'category'  => $a['category'],
                'brand'     => $a['brand'],
                'model'     => $a['model'] ?? '',
                'serial'    => $a['serial'] ?? '',
                'condition' => $a['condition'] ?? 'Good',
                'location'  => $a['location'] ?? 'Storage Depot',
                'specs'     => $a['specs'] ?? ''
            ];
        }
        sqlsrv_free_stmt($availStmt);
    }

    // 4b. Fetch available / in-stock accessories for assignment dropdown
    $accStmt = sqlsrv_query($conn, "SELECT id, sku, name, category, branch_location, brand, model, total_qty, in_stock, deployed, location 
                                    FROM accessories 
                                    WHERE in_stock > 0 
                                    ORDER BY category ASC, name ASC");
    if ($accStmt !== false) {
        while ($ac = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC)) {
            $availableAccessories[] = [
                'id'       => intval($ac['id']),
                'sku'      => $ac['sku'],
                'name'     => $ac['name'],
                'category' => $ac['category'],
                'brand'    => $ac['brand'],
                'model'    => $ac['model'] ?? '',
                'in_stock' => intval($ac['in_stock']),
                'location' => $ac['location'] ?? ''
            ];
        }
        sqlsrv_free_stmt($accStmt);
    }

    // 5. Fetch live assignments directly from real asset_assignments table in database
    $assignedStmt = sqlsrv_query($conn, "SELECT id, slip_no, asset_id, asset_tag, asset_name, category, brand, model, serial, specs,
                                                employee_id, employee_name, emp_code, employee_email, department, designation, location,
                                                CONVERT(VARCHAR(10), assigned_date, 120) AS assigned_date,
                                                allocation_type,
                                                CONVERT(VARCHAR(10), expected_return, 120) AS expected_return,
                                                CONVERT(VARCHAR(10), return_date, 120) AS return_date,
                                                custody_status, condition, return_condition,
                                                total_assets, total_accessories, assets_json, accessories_json,
                                                handover_by, agreement_signed, notes, return_notes
                                         FROM asset_assignments
                                         ORDER BY id DESC");
    if ($assignedStmt !== false) {
        while ($row = sqlsrv_fetch_array($assignedStmt, SQLSRV_FETCH_ASSOC)) {
            // Assets decode
            $assetsList = [];
            if (!empty($row['assets_json'])) {
                $decodedAssets = json_decode($row['assets_json'], true);
                if (is_array($decodedAssets)) {
                    $assetsList = $decodedAssets;
                }
            }
            if (empty($assetsList) && !empty($row['asset_name'])) {
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

            // Accessories decode
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
                $tCount = 0;
                foreach ($accList as $ac) {
                    $tCount += isset($ac['qty']) ? intval($ac['qty']) : 1;
                }
                $totalAccessories = $tCount;
            }

            $initialAssignments[] = [
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
        sqlsrv_free_stmt($assignedStmt);
    }
}

// Live assignments, assets, and employees are sourced directly from database tables
// Compute live metrics
$stats = [
    'total'             => count($initialAssignments),
    'permanent'         => 0,
    'temporary'         => 0,
    'remote'            => 0,
    'due_soon'          => 0,
    'total_assets'      => 0,
    'total_accessories' => 0,
    'available'         => count($availableAssets)
];

foreach ($initialAssignments as $item) {
    if ($item['custody_status'] !== 'Returned') {
        $stats['total_assets'] += $item['total_assets'];
        $stats['total_accessories'] += $item['total_accessories'];
    }

    $type = $item['allocation_type'];
    if ($type === 'Permanent') {
        $stats['permanent']++;
    } elseif ($type === 'Temporary Loaner') {
        $stats['temporary']++;
    } elseif ($type === 'Remote / WFH') {
        $stats['remote']++;
    }

    if ($item['custody_status'] === 'Due Soon' || $item['custody_status'] === 'Overdue') {
        $stats['due_soon']++;
    }
}

// Include Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Asset Assignment & Custody Management Content -->
<main class="dashboard-content">

    <!-- Page Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="assets.php">Assets</a>
                <span>/</span>
                <span>Asset Assignment & Allocation</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <polyline points="16 11 18 13 22 9"></polyline>
                </svg>
                Asset Assignment & Custody
            </h1>
            <p>Assign hardware devices to employees, manage custody lifecycles, loaner equipment, and digital handover slips.</p>
        </div>
        <div class="header-action-group">
            <button type="button" class="btn-secondary" id="exportAllocationsBtn" title="Export Allocations to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export CSV
            </button>
            <button type="button" class="btn-primary" id="openAssignModalBtn" title="Assign Device to Staff">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Assign New Asset</span>
            </button>
        </div>
    </div>

    <!-- KPI Metric Stat Cards -->
    <div class="asset-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
        <!-- Total Active Allocations -->
        <div class="alloc-stat-card card-active" data-filter-tab="all" title="View all active handover slips">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Active Handover Slips</div>
                <div class="stat-val" id="kpiTotalAllocated"><?php echo $stats['total']; ?></div>
                <div class="stat-sub">
                    <span style="color: #10b981; font-weight: 600;">●</span> Active custodian agreements
                </div>
            </div>
            <div class="alloc-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
            </div>
        </div>

        <!-- Total Assets Deployed -->
        <div class="alloc-stat-card card-permanent" title="Total hardware assets allocated to staff">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Deployed Assets</div>
                <div class="stat-val" id="kpiDeployedAssets" style="color: #4f46e5;"><?php echo $stats['total_assets']; ?></div>
                <div class="stat-sub">Hardware units with staff</div>
            </div>
            <div class="alloc-stat-icon indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <!-- Total Accessories Deployed -->
        <div class="alloc-stat-card" style="border-left: 4px solid #059669;" title="Total accessories deployed to employees">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Deployed Accessories</div>
                <div class="stat-val" id="kpiDeployedAccessories" style="color: #059669;"><?php echo $stats['total_accessories']; ?></div>
                <div class="stat-sub">Peripherals & cables allocated</div>
            </div>
            <div class="alloc-stat-icon emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
        </div>

        <!-- Temporary / Loaner Devices -->
        <div class="alloc-stat-card card-temporary" data-filter-tab="temporary" title="Filter temporary loaners & project devices">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Loaners / Due Soon</div>
                <div class="stat-val" id="kpiTemporaryAllocated" style="color: #d97706;"><?php echo $stats['temporary']; ?></div>
                <div class="stat-sub">
                    <span style="color: #ea580c; font-weight: 600;"><?php echo $stats['due_soon']; ?> Due / Overdue</span>
                </div>
            </div>
            <div class="alloc-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>

        <!-- Ready to Assign / In Stock -->
        <div class="alloc-stat-card card-available" id="cardAvailableInStock" title="View available unallocated hardware">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Ready to Assign</div>
                <div class="stat-val" id="kpiAvailableAssets" style="color: #0891b2;"><?php echo $stats['available']; ?></div>
                <div class="stat-sub">In stock in IT storage depot</div>
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

    <!-- Allocation Status Navigation Tabs -->
    <div class="asset-status-tabs">
        <button type="button" class="status-tab-btn active" data-tab="all">
            All Allocations
            <span class="status-tab-badge" id="tabCountAll"><?php echo $stats['total']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="permanent">
            Permanent
            <span class="status-tab-badge" id="tabCountPermanent"><?php echo $stats['permanent']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="temporary">
            Temporary / Loaner
            <span class="status-tab-badge" id="tabCountTemporary"><?php echo $stats['temporary']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="remote">
            Remote / WFH
            <span class="status-tab-badge" id="tabCountRemote"><?php echo $stats['remote']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="due_soon" style="border-left: 2px solid #fdba74;">
            Due Soon / Overdue
            <span class="status-tab-badge" id="tabCountDueSoon" style="background: #ea580c; color: #fff;"><?php echo $stats['due_soon']; ?></span>
        </button>
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
                <input type="text" id="allocSearchInput" placeholder="Search by Asset Tag, Device, Employee, Emp Code...">
            </div>

            <!-- Department Filter -->
            <select class="asset-filter-select" id="allocDeptFilter">
                <option value="all">All Departments</option>
                <?php foreach ($departmentsList as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Allocation Type Filter -->
            <select class="asset-filter-select" id="allocTypeFilter">
                <option value="all">All Allocation Types</option>
                <option value="Permanent">Permanent Handover</option>
                <option value="Temporary Loaner">Temporary / Loaner</option>
                <option value="Remote / WFH">Remote / WFH</option>
                <option value="Project Deployment">Project Deployment</option>
            </select>

            <!-- Custody Status Filter -->
            <select class="asset-filter-select" id="allocStatusFilter">
                <option value="all">All Custody States</option>
                <option value="Active">Active Custody</option>
                <option value="Due Soon">Due Soon (<30d)</option>
                <option value="Overdue">Overdue</option>
            </select>

            <!-- Reset Button -->
            <button type="button" class="btn-secondary" id="resetAllocFiltersBtn" style="padding: 7px 12px; font-size: 12.5px;" title="Reset Filters">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Reset
            </button>
        </div>

        <div class="toolbar-right">
            <span style="font-size: 12px; color: var(--text-muted);" id="allocTableCountText">Showing <?php echo count($initialAssignments); ?> records</span>
        </div>
    </div>

    <!-- Active Allocations Table Card -->
    <div class="asset-table-card">
        <div class="asset-table-responsive">
            <table class="asset-data-table">
                <thead>
                    <tr>
                        <th style="width: 36px;">
                            <input type="checkbox" class="custom-checkbox" id="selectAllAlloc">
                        </th>
                        <th style="min-width: 130px;">Handover Slip</th>
                        <th style="min-width: 220px;">Custodian / Employee</th>
                        <th style="min-width: 230px;">Assigned Assets (Hardware)</th>
                        <th style="min-width: 210px;">Assigned Accessories</th>
                        <th style="min-width: 140px;">Allocation Terms</th>
                        <th style="min-width: 120px;">Custody Status</th>
                        <th style="text-align: right; padding-right: 18px; min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="allocationsTbody">
                    <?php foreach ($initialAssignments as $row): 
                        $typePill = 'permanent';
                        if ($row['allocation_type'] === 'Temporary Loaner') $typePill = 'temporary';
                        elseif ($row['allocation_type'] === 'Remote / WFH') $typePill = 'remote';
                        elseif ($row['allocation_type'] === 'Project Deployment') $typePill = 'project';

                        $custodyBadge = 'status-active';
                        $statusText = 'In Custody';
                        if ($row['custody_status'] === 'Due Soon') {
                            $custodyBadge = 'status-due-soon';
                            $statusText = 'Due Soon';
                        } elseif ($row['custody_status'] === 'Overdue') {
                            $custodyBadge = 'status-overdue';
                            $statusText = 'Overdue';
                        } elseif ($row['custody_status'] === 'Returned') {
                            $custodyBadge = 'status-returned';
                            $statusText = 'Returned';
                        }
                    ?>
                        <tr data-id="<?php echo $row['id']; ?>">
                            <td>
                                <input type="checkbox" class="custom-checkbox row-select-checkbox" value="<?php echo $row['id']; ?>">
                            </td>
                            <td>
                                <div class="slip-cell">
                                    <span class="slip-badge" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                        <?php echo htmlspecialchars($row['slip_no']); ?>
                                    </span>
                                    <div class="slip-date">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <?php echo htmlspecialchars($row['assigned_date']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="custodian-cell">
                                    <?php 
                                        $avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];
                                        $parts = explode(' ', trim($row['employee_name']));
                                        $inits = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                                        $cHash = abs(crc32($row['employee_name'] . ($row['emp_code'] ?? '')));
                                        $colorBg = $avatarColors[$cHash % count($avatarColors)];
                                    ?>
                                    <div class="custodian-avatar" style="background: <?php echo $colorBg; ?>;">
                                        <?php echo htmlspecialchars($inits ?: 'ST'); ?>
                                    </div>
                                    <div class="custodian-info">
                                        <div class="custodian-name">
                                            <?php echo htmlspecialchars($row['employee_name']); ?>
                                            <span class="emp-code-badge"><?php echo htmlspecialchars($row['emp_code']); ?></span>
                                        </div>
                                        <div class="custodian-sub">
                                            <?php echo htmlspecialchars($row['designation']); ?> • <strong><?php echo htmlspecialchars($row['department']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="count-pill-wrap">
                                        <?php if ($row['total_assets'] > 0): ?>
                                            <span class="kitna-badge asset-kitna-badge">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                                <strong><?php echo $row['total_assets']; ?> <?php echo $row['total_assets'] === 1 ? 'Asset' : 'Assets'; ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="kitna-badge zero-kitna-badge">0 Assets</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chips-compact-list">
                                        <?php 
                                            $displayAssets = array_slice($row['assets'], 0, 2);
                                            foreach ($displayAssets as $ast): 
                                        ?>
                                            <span class="chip-device" title="<?php echo htmlspecialchars($ast['name']); ?> (SN: <?php echo htmlspecialchars($ast['serial'] ?? '—'); ?>)">
                                                <span class="chip-tag"><?php echo htmlspecialchars($ast['tag']); ?></span>
                                                <span class="chip-device-name"><?php echo htmlspecialchars($ast['name']); ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($row['assets']) > 2): ?>
                                            <span class="chip-more-count" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)">+<?php echo count($row['assets']) - 2; ?> more device(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="count-pill-wrap">
                                        <?php if ($row['total_accessories'] > 0): ?>
                                            <span class="kitna-badge acc-kitna-badge">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                                <strong><?php echo $row['total_accessories']; ?> <?php echo $row['total_accessories'] === 1 ? 'Accessory' : 'Accessories'; ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="kitna-badge zero-kitna-badge">0 Accessories</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chips-compact-list">
                                        <?php 
                                            $displayAcc = array_slice($row['accessories'], 0, 2);
                                            foreach ($displayAcc as $acc): 
                                                $accName = is_array($acc) ? ($acc['name'] ?? 'Item') : $acc;
                                                $accQty = is_array($acc) && !empty($acc['qty']) ? intval($acc['qty']) : 1;
                                        ?>
                                            <span class="chip-acc-tag" title="<?php echo htmlspecialchars($accName); ?>">
                                                <span><?php echo htmlspecialchars($accName); ?></span>
                                                <span class="chip-acc-qty"><?php echo $accQty; ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($row['accessories']) > 2): ?>
                                            <span class="chip-more-count" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)">+<?php echo count($row['accessories']) - 2; ?> more item(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 3px; align-items: flex-start;">
                                    <span class="alloc-pill <?php echo $typePill; ?>">
                                        <?php echo htmlspecialchars($row['allocation_type']); ?>
                                    </span>
                                    <?php if (!empty($row['expected_return'])): ?>
                                        <span style="font-size: 11px; color: #ea580c; font-weight: 600;">
                                            Exp: <?php echo htmlspecialchars($row['expected_return']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: var(--text-muted);">Permanent</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="custody-badge <?php echo $custodyBadge; ?>">
                                    <span class="dot"></span>
                                    <span><?php echo $statusText; ?></span>
                                </div>
                                <?php if (!empty($row['agreement_signed'])): ?>
                                    <div class="slip-signed-note">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <span>Slip Verified</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                    <button type="button" class="action-icon-btn btn-view" title="View Custody Details & Handover Slip" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-return" title="Return Asset (Check-In to Inventory)" onclick="openReturnModal(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-transfer" title="Transfer to Another Custodian" onclick="openTransferModal(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-qr" title="Print Handover Slip Receipt" onclick="openSlipModal(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($initialAssignments)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="table-empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                                    <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.7;">📦</div>
                                    <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">No Allocations Found</div>
                                    <div style="font-size: 12.5px; margin-top: 4px;">No active asset assignments have been created yet.</div>
                                    <button type="button" class="btn-primary" style="margin-top: 14px; padding: 7px 16px; font-size: 13px;" onclick="document.getElementById('openAssignModalBtn')?.click()">Assign New Asset</button>
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
     SLIDE-OVER DRAWER (Custody & Handover Details)
     ========================================================================= -->
<div class="drawer-backdrop" id="assignmentDrawerBackdrop"></div>

<aside class="asset-drawer" id="assignmentDrawer">
    <div class="drawer-header" style="padding: 18px 22px; border-bottom: 1px solid var(--border-color); background: #ffffff; display: flex; align-items: flex-start; justify-content: space-between;">
        <div class="drawer-header-left" style="display: flex; align-items: center; gap: 14px; width: calc(100% - 40px); min-width: 0;">
            <!-- Employee Avatar (Matching Employee Master design) -->
            <div class="emp-avatar" id="drawerHeaderAvatar" style="width: 48px; height: 48px; font-size: 16px; font-weight: 700; border-radius: 50%; color: #ffffff; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,25,56,0.18); display: flex; align-items: center; justify-content: center; background: var(--cyan-primary);">
                --
            </div>
            
            <!-- Employee Details in Header -->
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                    <span class="slip-badge" id="drawerSlipNo" style="font-size: 11.5px; font-weight: 700; padding: 2px 8px;">-</span>
                    <span class="custody-badge status-active" id="drawerStatusBadge"><span class="dot"></span>In Custody</span>
                    <span class="emp-code-badge" id="drawerHeaderEmpCode" style="font-size: 11px; padding: 2px 7px;">-</span>
                </div>
                <h3 id="drawerHeaderEmpName" style="margin: 0; font-size: 17.5px; font-weight: 700; color: var(--navy-primary); line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</h3>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="drawerHeaderEmpMeta">-</div>
                <div style="font-size: 11.5px; color: var(--cyan-primary); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="drawerHeaderEmpEmail">-</div>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeAssignmentDrawerBtn" title="Close Drawer" style="flex-shrink: 0; margin-left: 10px;">&times;</button>
    </div>

    <!-- Drawer Tabs -->
    <div class="drawer-tabs">
        <button type="button" class="drawer-tab active" data-tab="custody_overview">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Custody Overview
        </button>
        <button type="button" class="drawer-tab" data-tab="device_specs">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            Device Specs
        </button>
        <button type="button" class="drawer-tab" data-tab="handover_timeline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            Custody Timeline
        </button>
    </div>

    <!-- Drawer Content -->
    <div class="drawer-content">
        <!-- Tab 1: Overview -->
        <div class="drawer-tab-pane active" id="pane_custody_overview">
            
            <!-- 1. Allocated Hardware Assets Checklist (Asset Detail - NICHE) -->
            <div class="drawer-section">
                <div class="drawer-section-title" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                        <span>Assigned Hardware Assets (<span id="drawerAssetCount">0</span>)</span>
                    </div>
                </div>
                <div id="drawerAssetsWrap" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 2. Bundled Accessories Checklist (Accessories Detail - NICHE) -->
            <div class="drawer-section">
                <div class="drawer-section-title" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        <span>Assigned Accessories (<span id="drawerAccCount">0</span>)</span>
                    </div>
                </div>
                <div id="drawerAccessoriesWrap" class="accessory-chip-grid" style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 3. Handover Terms & Scope -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Handover Terms & Allocation
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Allocation Type</div>
                        <div class="value" id="drawerAllocType">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Handover Date</div>
                        <div class="value" id="drawerAssignedDate">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Expected Return</div>
                        <div class="value" id="drawerExpectedReturn">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Initial Condition</div>
                        <div class="value" id="drawerCondition">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Assigned Branch</div>
                        <div class="value" id="drawerLocation">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Issued By</div>
                        <div class="value" id="drawerHandoverBy">-</div>
                    </div>
                </div>
            </div>

            <!-- 4. Handover Agreement & Notes -->
            <div class="drawer-section">
                <div class="drawer-section-title">Handover Agreement & Notes</div>
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;" id="drawerNotes">
                    Device verified and allocated in good physical condition. Custodian has agreed to corporate IT acceptable use policy.
                </div>
            </div>

        </div>

        <!-- Tab 2: Specs -->
        <div class="drawer-tab-pane" id="pane_device_specs">
            <div id="drawerSpecsListWrap">
                <div class="drawer-section">
                    <div class="drawer-section-title">Hardware Specifications</div>
                    <div class="drawer-spec-grid">
                        <div class="drawer-spec-item">
                            <div class="label">Asset Tag</div>
                            <div class="value" id="specTag" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">-</div>
                        </div>
                        <div class="drawer-spec-item">
                            <div class="label">Category</div>
                            <div class="value" id="specCategory">-</div>
                        </div>
                        <div class="drawer-spec-item">
                            <div class="label">Brand</div>
                            <div class="value" id="specBrand">-</div>
                        </div>
                        <div class="drawer-spec-item">
                            <div class="label">Model</div>
                            <div class="value" id="specModel">-</div>
                        </div>
                        <div class="drawer-spec-item">
                            <div class="label">Serial Number</div>
                            <div class="value" id="specSerial" style="font-family: monospace;">-</div>
                        </div>
                        <div class="drawer-spec-item">
                            <div class="label">Hardware Specs</div>
                            <div class="value" id="specDetails">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Timeline -->
        <div class="drawer-tab-pane" id="pane_handover_timeline">
            <div class="drawer-section">
                <div class="drawer-section-title">Custody History Timeline</div>
                <div id="drawerTimelineWrap">
                    <!-- Timeline items generated dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div class="drawer-footer" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        <button type="button" class="btn-danger" style="padding: 7px 12px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 5px;" onclick="if(window.activeAllocId) openReturnModal(window.activeAllocId)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
            Return Asset
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn-secondary" style="padding: 7px 12px; font-size: 12.5px;" onclick="if(window.activeAllocId) openSlipModal(window.activeAllocId)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Handover Slip
            </button>
            <button type="button" class="btn-primary" style="padding: 7px 12px; font-size: 12.5px;" onclick="if(window.activeAllocId) openTransferModal(window.activeAllocId)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line></svg>
                Transfer Custody
            </button>
        </div>
    </div>
</aside>

<!-- =========================================================================
     MODAL 1: ASSIGN ASSET TO EMPLOYEE (WIZARD MODAL)
     ========================================================================= -->
<div class="modal-overlay" id="assignAssetModal" style="display: none;">
    <div class="modal-box" style="max-width: 780px;">
        <div class="modal-header">
            <h3 id="assignAssetModalTitle">Assign Asset to Employee</h3>
            <button type="button" class="modal-close-btn" id="closeAssignModalBtn">&times;</button>
        </div>

        <!-- Modal Tab Headers (Exact pattern from assets.php) -->
        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-btn active" data-tab="employee">Employee Information</button>
            <button type="button" class="modal-tab-btn" data-tab="assets">Asset Detail <span id="assignTabAssetBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span></button>
            <button type="button" class="modal-tab-btn" data-tab="accessories">Accessories Detail <span id="assignTabAccBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span></button>
        </div>

        <form id="assignAssetForm">
            <div class="modal-body">
                
                <!-- Tab Pane 1: Employee Information -->
                <div class="modal-tab-pane active" id="modal_pane_employee">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="assignEmployeeSelect">Employee / Staff *</label>
                            <select id="assignEmployeeSelect" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employeesList as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['id']); ?>" 
                                            data-name="<?php echo htmlspecialchars($emp['name']); ?>"
                                            data-code="<?php echo htmlspecialchars($emp['emp_code']); ?>"
                                            data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                            data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                                            data-desig="<?php echo htmlspecialchars($emp['designation']); ?>">
                                        <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['emp_code']); ?> - <?php echo htmlspecialchars($emp['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="assignAllocationType">Allocation Type *</label>
                            <select id="assignAllocationType" required>
                                <option value="Permanent" selected>Permanent Handover</option>
                                <option value="Temporary Loaner">Temporary / Loaner Device</option>
                                <option value="Remote / WFH">Remote / WFH Allocation</option>
                                <option value="Project Deployment">Project-Specific Deployment</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Employee Card Preview -->
                    <div class="preview-summary-card" id="empPreviewBox" style="display: none; margin-bottom: 16px;">
                        <div class="preview-summary-title">Custodian Summary</div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="custodian-avatar" id="empPreviewAvatar" style="width: 42px; height: 42px; flex-shrink: 0;">ST</div>
                            <div style="min-width: 0; overflow: hidden;">
                                <div style="font-size: 14px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="empPreviewName">-</div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="empPreviewMeta">-</div>
                                <div style="font-size: 11.5px; color: var(--cyan-primary); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="empPreviewEmail">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="assignLocation">Deployment Location *</label>
                            <select id="assignLocation" required>
                                <?php foreach ($locationsList as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="assignHandoverDate">Handover Date *</label>
                            <input type="date" id="assignHandoverDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="modal-form-group" id="expectedReturnGroup" style="display: none;">
                        <label for="assignExpectedReturn">Expected Return Date * <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Required for Temporary Loaners)</span></label>
                        <input type="date" id="assignExpectedReturn" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" style="max-width: 50%;">
                    </div>
                </div>

                <!-- Tab Pane 2: Asset Detail -->
                <div class="modal-tab-pane" id="modal_pane_assets">
                    <div class="modal-form-group">
                        <label for="assignAssetSelect">Choose In-Stock Asset to Add *</label>
                        <div class="batch-asset-picker-row">
                            <select id="assignAssetSelect">
                                <option value="">-- Choose In-Stock Asset to Add --</option>
                                <?php foreach ($availableAssets as $av): ?>
                                    <option value="<?php echo htmlspecialchars($av['id']); ?>"
                                            data-tag="<?php echo htmlspecialchars($av['tag']); ?>"
                                            data-name="<?php echo htmlspecialchars($av['name']); ?>"
                                            data-category="<?php echo htmlspecialchars($av['category']); ?>"
                                            data-brand="<?php echo htmlspecialchars($av['brand']); ?>"
                                            data-serial="<?php echo htmlspecialchars($av['serial']); ?>"
                                            data-condition="<?php echo htmlspecialchars($av['condition']); ?>"
                                            data-specs="<?php echo htmlspecialchars($av['specs']); ?>">
                                        <?php echo htmlspecialchars($av['tag']); ?> • <?php echo htmlspecialchars($av['name']); ?> (SN: <?php echo htmlspecialchars($av['serial'] ?: 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-add-comp-row" id="addAssetToBatchBtn" title="Add device to allocation list">+</button>
                        </div>
                    </div>

                    <!-- Selected Batch Assets List Container -->
                    <div class="modal-form-group" style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Selected Devices for Allocation</span>
                            <span class="asset-count-badge" id="selectedAssetCountBadge">0 Devices</span>
                        </label>
                        <div class="batch-asset-list" id="batchAssetsList" style="max-height: 180px;">
                            <div class="batch-asset-empty" id="batchEmptyState">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <span>No assets selected yet. Pick an asset above and click <strong>+ Add</strong>.</span>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label>Default Handover Condition *</label>
                        <div style="display: flex; gap: 16px; margin-top: 6px; flex-wrap: wrap;">
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; font-size: 13px;">
                                <input type="radio" name="handoverCondition" value="Brand New" checked> Brand New
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; font-size: 13px;">
                                <input type="radio" name="handoverCondition" value="Excellent"> Excellent
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; font-size: 13px;">
                                <input type="radio" name="handoverCondition" value="Good"> Good
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 3: Accessories Detail -->
                <div class="modal-tab-pane" id="modal_pane_accessories">
                    <!-- Included Accessories & Peripherals -->
                    <div class="modal-form-group">
                        <label for="assignAccessorySelect">Choose In-Stock Accessory / Peripheral to Add</label>
                        <div class="batch-asset-picker-row">
                            <select id="assignAccessorySelect">
                                <option value="">-- Choose In-Stock Accessory to Add --</option>
                                <?php foreach ($availableAccessories as $ac): ?>
                                    <option value="<?php echo htmlspecialchars($ac['id']); ?>"
                                            data-sku="<?php echo htmlspecialchars($ac['sku']); ?>"
                                            data-name="<?php echo htmlspecialchars($ac['name']); ?>"
                                            data-category="<?php echo htmlspecialchars($ac['category']); ?>"
                                            data-brand="<?php echo htmlspecialchars($ac['brand'] ?? ''); ?>"
                                            data-model="<?php echo htmlspecialchars($ac['model'] ?? ''); ?>"
                                            data-instock="<?php echo htmlspecialchars($ac['in_stock']); ?>">
                                        <?php echo htmlspecialchars($ac['sku']); ?> • <?php echo htmlspecialchars($ac['name']); ?> (In Stock: <?php echo htmlspecialchars($ac['in_stock']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-add-comp-row" id="addAccessoryToBatchBtn" title="Add accessory to allocation list">+</button>
                        </div>
                    </div>

                    <!-- Selected Batch Accessories List Container -->
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Selected Accessories for Allocation</span>
                            <span class="asset-count-badge" id="selectedAccCountBadge">0 Items</span>
                        </label>
                        <div class="batch-asset-list" id="batchAccessoriesList" style="max-height: 140px;">
                            <div class="batch-asset-empty" id="accEmptyState">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                <span>No accessories added yet. Pick an accessory above and click <strong>+ Add</strong>.</span>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="assignNotes">Handover Remarks / Asset Notes</label>
                        <textarea id="assignNotes" rows="2" placeholder="e.g. Delivered with clean image, antivirus active, no cosmetic scratches."></textarea>
                    </div>

                    <!-- Handover Policy Agreement Checkbox -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; margin-top: 10px; display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" id="assignPolicyCheck" required style="margin-top: 3px; accent-color: #16a34a; cursor: pointer; flex-shrink: 0;">
                        <label for="assignPolicyCheck" style="font-size: 12px; color: #166534; margin: 0; cursor: pointer; line-height: 1.4;">
                            <strong>Custodian Policy Sign-Off:</strong> Employee has physically received the hardware equipment in working order and agrees to comply with the organization's IT Asset Security & Acceptable Usage Policy.
                        </label>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAssignBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="submitAssignBtn">Confirm & Complete Handover</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: RETURN ASSET TO INVENTORY (CHECK-IN)
     ========================================================================= -->
<div class="modal-overlay" id="returnAssetModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0;">Return Asset to Inventory</h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Check-in equipment from employee back to storage depot</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="closeReturnModalBtn">&times;</button>
        </div>
        <form id="returnAssetForm">
            <input type="hidden" id="returnAllocId">
            <div class="modal-body">
                
                <div class="preview-summary-card" style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span class="asset-tag-badge" id="returnTagText">-</span>
                        <span class="emp-code-badge" id="returnEmpCodeText">-</span>
                    </div>
                    <div style="font-weight: 700; font-size: 14px; color: var(--text-primary);" id="returnAssetNameText">-</div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                        Returning Custodian: <strong id="returnEmpNameText">-</strong>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="returnDate">Return Date *</label>
                        <input type="date" id="returnDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="returnCondition">Returned Condition *</label>
                        <select id="returnCondition" required>
                            <option value="Excellent">Excellent (No defects)</option>
                            <option value="Good" selected>Good (Minor wear)</option>
                            <option value="Needs Repair">Needs Repair / Maintenance</option>
                            <option value="Damaged">Damaged / Non-functional</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="returnStorageLocation">Storage Shelf / Rack Depot *</label>
                    <input type="text" id="returnStorageLocation" value="Storage Depot (Shelf 1)" placeholder="e.g. Storage Depot Rack A-02" required>
                </div>

                <div class="modal-form-group">
                    <label for="returnChecklistNotes">Inspection & Diagnostic Notes</label>
                    <textarea id="returnChecklistNotes" rows="3" placeholder="Inspected charger, bag, power-on test passed, hard drive wiped."></textarea>
                </div>

            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn-secondary" id="cancelReturnBtn">Cancel</button>
                <button type="submit" class="btn-primary" style="background: #2563eb; border-color: #1d4ed8;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm Return & Check-In
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: TRANSFER ASSET TO ANOTHER CUSTODIAN
     ========================================================================= -->
<div class="modal-overlay" id="transferAssetModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line></svg>
                </div>
                <div>
                    <h3 style="margin: 0;">Transfer Asset Custody</h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Reassign device from current custodian to a new employee</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="closeTransferModalBtn">&times;</button>
        </div>
        <form id="transferAssetForm">
            <input type="hidden" id="transferAllocId">
            <div class="modal-body">
                
                <div class="preview-summary-card" style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span class="asset-tag-badge" id="transferTagText">-</span>
                        <span style="font-size: 11px; color: var(--text-muted);">Current Custodian</span>
                    </div>
                    <div style="font-weight: 700; font-size: 14px; color: var(--text-primary);" id="transferAssetNameText">-</div>
                    <div style="font-size: 12.5px; color: #4338ca; margin-top: 3px;">
                        Current: <strong id="transferOldEmpText">-</strong>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="transferNewEmpSelect">New Custodian (Employee) *</label>
                    <select id="transferNewEmpSelect" required style="width: 100%;">
                        <option value="">-- Choose New Custodian --</option>
                        <?php foreach ($employeesList as $emp): ?>
                            <option value="<?php echo htmlspecialchars($emp['id']); ?>" data-name="<?php echo htmlspecialchars($emp['name']); ?>" data-code="<?php echo htmlspecialchars($emp['emp_code']); ?>" data-dept="<?php echo htmlspecialchars($emp['department']); ?>">
                                <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['emp_code']); ?> - <?php echo htmlspecialchars($emp['department']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="transferEffectiveDate">Effective Transfer Date *</label>
                        <input type="date" id="transferEffectiveDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="transferReason">Transfer Reason *</label>
                        <select id="transferReason" required>
                            <option value="Department Transfer">Department Transfer</option>
                            <option value="Role Reassignment" selected>Role Reassignment</option>
                            <option value="Project Handover">Project Handover</option>
                            <option value="Employee Departure">Employee Departure Handover</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="transferNotes">Transfer Notes / Authorizer</label>
                    <textarea id="transferNotes" rows="2" placeholder="e.g. Authorized by Department Head. Device transferred in good working condition."></textarea>
                </div>

            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn-secondary" id="cancelTransferBtn">Cancel</button>
                <button type="submit" class="btn-primary" style="background: #7c3aed; border-color: #6d28d9;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm Custody Transfer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: PRINTABLE HANDOVER SLIP RECEIPT
     ========================================================================= -->
<div class="modal-overlay" id="slipModal">
    <div class="modal-box" style="max-width: 820px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h3>Equipment Handover Slip</h3>
            <button type="button" class="modal-close-btn" id="closeSlipModalBtn">&times;</button>
        </div>
        <div class="modal-body" style="flex: 1 1 auto; overflow-y: auto; padding: 20px;">
            <div class="handover-slip-sheet" id="handoverSlipContent" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 24px 28px; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.45;">
                
                <!-- 1. Document Header Table -->
                <table class="slip-doc-table slip-header-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 2px solid #001938;">
                    <tr>
                        <td style="width: 22%; text-align: center; vertical-align: middle; padding: 12px; border-right: 1px solid #cbd5e1;">
                            <img src="assets/images/logo.png" alt="Company Logo" style="max-height: 48px; max-width: 110px; object-fit: contain;">
                        </td>
                        <td style="text-align: center; vertical-align: middle; padding: 12px;">
                            <div style="font-size: 18px; font-weight: 800; color: #001938; letter-spacing: 0.5px; text-transform: uppercase;">VIROS PORTAL</div>
                            <div style="font-size: 12px; font-weight: 600; color: #475569; margin-top: 2px;">IT Asset Management & Helpdesk</div>
                            <div style="font-size: 13px; font-weight: 800; color: var(--cyan-primary); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; border-top: 1px solid #cbd5e1; display: inline-block; padding-top: 3px;">
                                EQUIPMENT HANDOVER & CUSTODY SLIP
                            </div>
                        </td>
                        <td style="width: 30%; vertical-align: middle; font-size: 11.5px; line-height: 1.6; background: #f8fafc; border-left: 1px solid #cbd5e1; padding: 10px 14px;">
                            <div><strong>Slip No:</strong> <span id="slipNumber" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">-</span></div>
                            <div><strong>Handover Date:</strong> <span id="slipDate">-</span></div>
                            <div><strong>Allocation Type:</strong> <span id="slipAllocType" style="font-weight: 600;">-</span></div>
                            <div><strong>Custody Status:</strong> <span id="slipCustodyStatus" style="font-weight: 700; color: #166534;">In Custody</span></div>
                        </td>
                    </tr>
                </table>

                <!-- 2. Custodian / Employee Details Table -->
                <div class="slip-table-heading" style="font-size: 11.5px; font-weight: 700; color: #001938; background: #f1f5f9; padding: 5px 10px; margin: 12px 0 0; text-transform: uppercase; border: 1px solid #cbd5e1; border-bottom: none; border-left: 3px solid var(--cyan-primary);">
                    1. Custodian / Employee Information
                </div>
                <table class="slip-doc-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11.5px;">
                    <tr>
                        <td class="slip-lbl" style="width: 18%; background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Employee Name</td>
                        <td class="slip-val" style="width: 32%; font-weight: 700; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipEmpName">-</td>
                        <td class="slip-lbl" style="width: 18%; background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Employee ID</td>
                        <td class="slip-val" style="width: 32%; font-family: monospace; font-weight: 700; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipEmpCode">-</td>
                    </tr>
                    <tr>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Designation</td>
                        <td class="slip-val" style="font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipEmpDesig">-</td>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Department</td>
                        <td class="slip-val" style="font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipEmpDept">-</td>
                    </tr>
                    <tr>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Email Address</td>
                        <td class="slip-val" style="font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipEmpEmail">-</td>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Branch / Location</td>
                        <td class="slip-val" style="font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipEmpLocation">-</td>
                    </tr>
                </table>

                <!-- 3. Assigned Hardware Assets Table -->
                <div class="slip-table-heading" style="font-size: 11.5px; font-weight: 700; color: #001938; background: #f1f5f9; padding: 5px 10px; margin: 12px 0 0; text-transform: uppercase; border: 1px solid #cbd5e1; border-bottom: none; border-left: 3px solid var(--cyan-primary);">
                    2. Assigned Hardware Assets Details (<span id="slipAssetCount">0</span>)
                </div>
                <table class="slip-doc-table" id="slipAssetsTable" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11.5px;">
                    <thead>
                        <tr class="slip-th-row" style="background: #f1f5f9;">
                            <th style="width: 38px; text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">#</th>
                            <th style="width: 105px; padding: 6px 8px; border: 1px solid #cbd5e1;">Asset Tag</th>
                            <th style="padding: 6px 8px; border: 1px solid #cbd5e1;">Asset / Device Name</th>
                            <th style="width: 105px; padding: 6px 8px; border: 1px solid #cbd5e1;">Category</th>
                            <th style="width: 115px; padding: 6px 8px; border: 1px solid #cbd5e1;">Brand & Model</th>
                            <th style="width: 120px; padding: 6px 8px; border: 1px solid #cbd5e1;">Serial Number</th>
                            <th style="width: 75px; text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">Condition</th>
                        </tr>
                    </thead>
                    <tbody id="slipAssetsTbody">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>

                <!-- 4. Assigned Accessories Table -->
                <div class="slip-table-heading" style="font-size: 11.5px; font-weight: 700; color: #001938; background: #f1f5f9; padding: 5px 10px; margin: 12px 0 0; text-transform: uppercase; border: 1px solid #cbd5e1; border-bottom: none; border-left: 3px solid var(--cyan-primary);">
                    3. Assigned Accessories & Peripherals (<span id="slipAccCount">0</span>)
                </div>
                <table class="slip-doc-table" id="slipAccessoriesTable" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11.5px;">
                    <thead>
                        <tr class="slip-th-row" style="background: #f1f5f9;">
                            <th style="width: 38px; text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">#</th>
                            <th style="padding: 6px 8px; border: 1px solid #cbd5e1;">Accessory Item</th>
                            <th style="width: 160px; padding: 6px 8px; border: 1px solid #cbd5e1;">Category / Classification</th>
                            <th style="width: 65px; text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">Qty</th>
                            <th style="width: 90px; text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">Condition</th>
                        </tr>
                    </thead>
                    <tbody id="slipAccTbody">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>

                <!-- 5. Handover Terms & Scope Table -->
                <div class="slip-table-heading" style="font-size: 11.5px; font-weight: 700; color: #001938; background: #f1f5f9; padding: 5px 10px; margin: 12px 0 0; text-transform: uppercase; border: 1px solid #cbd5e1; border-bottom: none; border-left: 3px solid var(--cyan-primary);">
                    4. Handover Terms & Authorization
                </div>
                <table class="slip-doc-table" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11.5px;">
                    <tr>
                        <td class="slip-lbl" style="width: 20%; background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Allocation Type</td>
                        <td class="slip-val" style="width: 30%; font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipTermsAllocType">-</td>
                        <td class="slip-lbl" style="width: 20%; background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Handover Date</td>
                        <td class="slip-val" style="width: 30%; font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipTermsAssignedDate">-</td>
                    </tr>
                    <tr>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Expected Return</td>
                        <td class="slip-val" style="font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipTermsExpectedReturn">-</td>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Issued By (IT Official)</td>
                        <td class="slip-val" style="font-weight: 600; color: #0f172a; padding: 6px 10px; border: 1px solid #cbd5e1;" id="slipTermsIssuedBy">-</td>
                    </tr>
                    <tr>
                        <td class="slip-lbl" style="background: #f8fafc; font-weight: 600; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">Agreement & Notes</td>
                        <td class="slip-val" colspan="3" id="slipTermsNotes" style="font-size: 11px; line-height: 1.45; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1;">
                            Equipment verified and allocated in good physical condition. Custodian agreed to corporate IT acceptable use policy.
                        </td>
                    </tr>
                </table>

                <!-- 6. Custody Declaration & Signatures Table -->
                <table class="slip-doc-table slip-sign-table" style="width: 100%; border-collapse: collapse; margin-top: 14px; border: 1px solid #94a3b8;">
                    <tr>
                        <td colspan="2" style="font-size: 10.5px; color: #475569; padding: 8px 12px; background: #f8fafc; line-height: 1.45; border-bottom: 1px solid #94a3b8;">
                            <strong>Declaration & Acceptance:</strong> I hereby acknowledge receipt of the hardware and accessories listed above in sound physical and working condition. I agree to abide by the company IT Acceptable Use Policy and accept full responsibility for their care and custody.
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; padding: 36px 16px 10px; vertical-align: bottom; border-right: 1px solid #cbd5e1;">
                            <div style="border-top: 1.5px solid #001938; padding-top: 5px; font-size: 11.5px; font-weight: 700; color: #001938;">
                                Employee / Custodian Signature
                            </div>
                            <div style="font-size: 10.5px; color: #64748b; margin-top: 2px;" id="slipSignEmpName">
                                Signature & Date
                            </div>
                        </td>
                        <td style="width: 50%; padding: 36px 16px 10px; vertical-align: bottom; text-align: right;">
                            <div style="border-top: 1.5px solid #001938; padding-top: 5px; font-size: 11.5px; font-weight: 700; color: #001938;">
                                Authorized IT Official / Seal
                            </div>
                            <div style="font-size: 10.5px; color: #64748b; margin-top: 2px;">
                                IT Asset Management Dept
                            </div>
                        </td>
                    </tr>
                </table>

            </div>
        </div>
        <div class="modal-footer" style="flex-shrink: 0; display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid #e2e8f0; background: #ffffff;">
            <button type="button" class="btn-secondary" id="closeSlipBtn">Close</button>
            <button type="button" class="btn-primary" onclick="printSlipReceipt()" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Handover Slip
            </button>
        </div>
    </div>
</div>

<!-- Embedded JS Data -->
<script>
    window.INITIAL_ASSIGNMENTS = <?php echo json_encode($initialAssignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.AVAILABLE_ASSETS = <?php echo json_encode($availableAssets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.EMPLOYEES_LIST = <?php echo json_encode($employeesList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Layout Footer
include 'includes/footer.php';
?>
