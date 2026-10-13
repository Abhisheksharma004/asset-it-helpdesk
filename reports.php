<?php
// Asset Management & IT Service Desk - Unified Executive Reports & Compliance Center
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Reports & Analytics - VIROS IT Portal";
$active_page = "reports";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/reports.css'];
$extra_js  = ['js/reports.js'];

require_once __DIR__ . '/config/db.php';

// Organization Branding for Official Audit Sheets
$orgSettings = function_exists('getOrganizationSettings') ? getOrganizationSettings() : [];
$orgName = !empty($orgSettings['organization_legal_name']) ? trim($orgSettings['organization_legal_name']) : 'VIROS PORTAL';
$orgLogo = !empty($orgSettings['primary_brand_logo']) ? $orgSettings['primary_brand_logo'] : 'assets/images/logo.png';
if (!file_exists(__DIR__ . '/' . $orgLogo) && !filter_var($orgLogo, FILTER_VALIDATE_URL)) {
    $orgLogo = 'assets/images/logo.png';
}

// -------------------------------------------------------------
// 1. Hardware Assets Audit Dataset
// -------------------------------------------------------------
$assetsList = [];
$totalAssets = 0;
$inUseAssets = 0;
$availableAssets = 0;
$maintenanceAssets = 0;
$scrappedAssets = 0;
$totalAssetCost = 0;

if (isset($conn) && $conn !== false) {
    $astSql = "SELECT id, tag, name, category, brand, model, serial, status, condition, location, department, 
                      assigned_to, cost, CONVERT(VARCHAR(10), created_at, 105) AS added_date
               FROM assets 
               ORDER BY id DESC";
    $astStmt = sqlsrv_query($conn, $astSql);
    if ($astStmt !== false) {
        while ($row = sqlsrv_fetch_array($astStmt, SQLSRV_FETCH_ASSOC)) {
            $totalAssets++;
            $st = trim($row['status'] ?? 'Available');
            $costVal = floatval($row['cost'] ?? 0);
            $totalAssetCost += $costVal;

            if (stripos($st, 'in use') !== false || stripos($st, 'assign') !== false) {
                $inUseAssets++;
            } elseif (stripos($st, 'avail') !== false || stripos($st, 'ready') !== false || stripos($st, 'stock') !== false) {
                $availableAssets++;
            } elseif (stripos($st, 'maint') !== false || stripos($st, 'repair') !== false) {
                $maintenanceAssets++;
            } elseif (stripos($st, 'scrap') !== false || stripos($st, 'retired') !== false) {
                $scrappedAssets++;
            }

            $assetsList[] = $row;
        }
        sqlsrv_free_stmt($astStmt);
    }
}

// -------------------------------------------------------------
// 2. Software Licenses Dataset
// -------------------------------------------------------------
$licensesList = [];
$totalLicenses = 0;
$expiringLicCount = 0;
$totalLicCost = 0;

if (isset($conn) && $conn !== false) {
    $licTableCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='software_licenses' AND xtype='U'");
    if ($licTableCheck !== false && sqlsrv_has_rows($licTableCheck)) {
        $licSql = "SELECT id, software_name, publisher, category, version, license_type, license_key,
                          seats, expiry_date, status, total_cost,
                          CONVERT(VARCHAR(10), expiry_date, 105) AS formatted_expiry
                   FROM software_licenses
                   ORDER BY id DESC";
        $licStmt = sqlsrv_query($conn, $licSql);
        if ($licStmt !== false) {
            $now = new DateTime();
            while ($row = sqlsrv_fetch_array($licStmt, SQLSRV_FETCH_ASSOC)) {
                $totalLicenses++;
                $c = floatval($row['total_cost'] ?? 0);
                $totalLicCost += $c;

                if (!empty($row['expiry_date'])) {
                    $exp = $row['expiry_date'] instanceof DateTime ? $row['expiry_date'] : new DateTime($row['expiry_date']);
                    $diff = (int)$now->diff($exp)->format('%r%a');
                    if ($diff <= 60 && $diff >= 0) {
                        $row['is_expiring'] = true;
                        $expiringLicCount++;
                    } elseif ($diff < 0) {
                        $row['is_expired'] = true;
                    }
                }
                $licensesList[] = $row;
            }
            sqlsrv_free_stmt($licStmt);
        }
    }
}

// -------------------------------------------------------------
// 3. IT Helpdesk Incidents & SLA Dataset
// -------------------------------------------------------------
$ticketsList = [];
$totalTickets = 0;
$openTickets = 0;
$resolvedTickets = 0;
$urgentTickets = 0;

if (isset($conn) && $conn !== false) {
    $tTableCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='support_tickets' AND xtype='U'");
    if ($tTableCheck !== false && sqlsrv_has_rows($tTableCheck)) {
        $tSql = "SELECT id, ticket_no, subject, category, priority, status, asset_name, 
                        employee_name, emp_code, department,
                        CONVERT(VARCHAR(10), incident_date, 105) AS incident_dt
                 FROM support_tickets
                 ORDER BY id DESC";
        $tStmt = sqlsrv_query($conn, $tSql);
        if ($tStmt !== false) {
            while ($row = sqlsrv_fetch_array($tStmt, SQLSRV_FETCH_ASSOC)) {
                $totalTickets++;
                $st = trim($row['status'] ?? 'Open');
                $prio = trim($row['priority'] ?? 'Medium');

                if (stripos($st, 'resolved') !== false || stripos($st, 'closed') !== false) {
                    $resolvedTickets++;
                } else {
                    $openTickets++;
                }

                if (stripos($prio, 'urgent') !== false || stripos($prio, 'high') !== false) {
                    $urgentTickets++;
                }

                $ticketsList[] = $row;
            }
            sqlsrv_free_stmt($tStmt);
        }
    }
}

// -------------------------------------------------------------
// 4. Scrap & Salvage Disposal Dataset
// -------------------------------------------------------------
$scrapList = [];
$totalScrapUnits = 0;
$totalSalvageRealized = 0;

if (isset($conn) && $conn !== false) {
    // 1. Scrap Components
    $scTableCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='scrap_components' AND xtype='U'");
    if ($scTableCheck !== false && sqlsrv_has_rows($scTableCheck)) {
        $scq = sqlsrv_query($conn, "SELECT scrap_ref, component_name AS item_name, 'Internal Component' AS item_type, host_asset, reason, bin_location, disposal_status, salvage_value, authorized_by, CONVERT(VARCHAR(10), created_at, 105) AS scrap_date FROM scrap_components ORDER BY id DESC");
        if ($scq !== false) {
            while ($r = sqlsrv_fetch_array($scq, SQLSRV_FETCH_ASSOC)) {
                $totalScrapUnits++;
                $totalSalvageRealized += floatval($r['salvage_value'] ?? 0);
                $scrapList[] = $r;
            }
            sqlsrv_free_stmt($scq);
        }
    }

    // 2. Scrap Assets
    $saTableCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='scrap_assets' AND xtype='U'");
    if ($saTableCheck !== false && sqlsrv_has_rows($saTableCheck)) {
        $saq = sqlsrv_query($conn, "SELECT scrap_ref, asset_name AS item_name, 'Full Hardware Asset' AS item_type, asset_tag AS host_asset, reason, yard_location AS bin_location, disposal_status, salvage_value, authorized_by, CONVERT(VARCHAR(10), created_at, 105) AS scrap_date FROM scrap_assets ORDER BY id DESC");
        if ($saq !== false) {
            while ($r = sqlsrv_fetch_array($saq, SQLSRV_FETCH_ASSOC)) {
                $totalScrapUnits++;
                $totalSalvageRealized += floatval($r['salvage_value'] ?? 0);
                $scrapList[] = $r;
            }
            sqlsrv_free_stmt($saq);
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<main class="dashboard-content">

    <!-- Print Only Document Header -->
    <div class="print-only-header">
        <table class="print-header-tbl">
            <tr>
                <td style="width: 25%;">
                    <img src="<?php echo htmlspecialchars($orgLogo); ?>" alt="Logo" style="max-height: 55px; max-width: 160px; object-fit: contain;">
                </td>
                <td style="text-align: center;">
                    <h2 style="margin: 0; font-size: 19px; font-weight: 800; color: #001938; text-transform: uppercase;"><?php echo htmlspecialchars($orgName); ?></h2>
                    <div style="font-size: 12px; color: #475569; margin-top: 2px;">Enterprise IT Asset Management &amp; Service Desk</div>
                    <div style="font-size: 11px; font-weight: 700; color: #0093a7; margin-top: 4px; letter-spacing: 0.5px;">OFFICIAL EXECUTIVE IT ASSET &amp; COMPLIANCE AUDIT REPORT</div>
                </td>
                <td style="width: 30%; text-align: right; font-size: 11px; color: #334155;">
                    <div><strong>Generated On:</strong> <?php echo date('d M Y, h:i A'); ?></div>
                    <div><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Authorized Administrator'); ?></div>
                    <div><strong>Audit Status:</strong> Verified Active Master</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Page Header Bar -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px;">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Reports &amp; Analytics</span>
                <span>/</span>
                <span>Audit Center</span>
            </div>
            <h1 style="font-size: 22px; font-weight: 700; color: var(--navy-deep); margin: 4px 0 0;">Executive IT Reports &amp; Audit Center</h1>
            <div class="reports-header-meta">
                <span class="reports-badge-pill">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Real-time MS SQL Master Sync
                </span>
                <span style="font-size: 13px; color: var(--text-secondary);">Comprehensive analytical records across hardware inventory, software licenses, service desk &amp; scrap disposal</span>
            </div>
        </div>

        <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-shrink: 0;">
            <button type="button" onclick="window.print()" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--navy-deep);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Audit Slip
            </button>
            <button type="button" onclick="exportActiveReportCSV()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 6px; background: var(--cyan-primary); color: #fff; border: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
        </div>
    </div>

    <!-- Executive KPI Metric Cards Strip -->
    <div class="reports-kpi-grid">
        <!-- 1. Assets KPI -->
        <div class="reports-kpi-card kpi-assets">
            <div class="reports-kpi-icon icon-cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <div class="reports-kpi-body">
                <div class="reports-kpi-label">Hardware Assets Registry</div>
                <div class="reports-kpi-value"><?php echo number_format($totalAssets); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Units</span></div>
                <div class="reports-kpi-sub">
                    <span class="reports-pill-sm pill-green"><?php echo $inUseAssets; ?> In Use</span>
                    <span class="reports-pill-sm pill-cyan"><?php echo $availableAssets; ?> In Stock</span>
                    <?php if ($totalAssetCost > 0): ?>
                        <span style="font-weight: 600; color: var(--navy-deep); margin-left: auto;">₹<?php echo number_format($totalAssetCost); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. Licenses KPI -->
        <div class="reports-kpi-card kpi-licenses">
            <div class="reports-kpi-icon icon-amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <div class="reports-kpi-body">
                <div class="reports-kpi-label">Software Licenses &amp; SAAS</div>
                <div class="reports-kpi-value"><?php echo number_format($totalLicenses); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Subscriptions</span></div>
                <div class="reports-kpi-sub">
                    <?php if ($expiringLicCount > 0): ?>
                        <span class="reports-pill-sm pill-red"><?php echo $expiringLicCount; ?> Expiring Soon</span>
                    <?php else: ?>
                        <span class="reports-pill-sm pill-green">All Compliant</span>
                    <?php endif; ?>
                    <?php if ($totalLicCost > 0): ?>
                        <span style="font-weight: 600; color: var(--navy-deep); margin-left: auto;">₹<?php echo number_format($totalLicCost); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. Helpdesk Incident Tickets KPI -->
        <div class="reports-kpi-card kpi-tickets">
            <div class="reports-kpi-icon icon-indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
            </div>
            <div class="reports-kpi-body">
                <div class="reports-kpi-label">IT Helpdesk Incidents &amp; SLA</div>
                <div class="reports-kpi-value"><?php echo number_format($totalTickets); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Tickets</span></div>
                <div class="reports-kpi-sub">
                    <span class="reports-pill-sm pill-green"><?php echo $resolvedTickets; ?> Resolved</span>
                    <span class="reports-pill-sm <?php echo $openTickets > 0 ? 'pill-amber' : 'pill-green'; ?>"><?php echo $openTickets; ?> Open</span>
                    <?php if ($urgentTickets > 0): ?>
                        <span class="reports-pill-sm pill-red"><?php echo $urgentTickets; ?> High/Urgent</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. Scrap & Salvage KPI -->
        <div class="reports-kpi-card kpi-scrap">
            <div class="reports-kpi-icon icon-green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
            </div>
            <div class="reports-kpi-body">
                <div class="reports-kpi-label">Decommission &amp; Salvage</div>
                <div class="reports-kpi-value"><?php echo number_format($totalScrapUnits); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Items</span></div>
                <div class="reports-kpi-sub">
                    <span class="reports-pill-sm pill-green">Salvaged Cash Realized</span>
                    <span style="font-weight: 700; color: #047857; margin-left: auto;">₹<?php echo number_format($totalSalvageRealized); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Reports Card Container -->
    <div class="reports-table-card">

        <!-- Module Tabs Navigation -->
        <div class="reports-module-tabs-wrap">
            <div class="reports-module-tabs">
                <button type="button" class="report-module-tab active" data-module="assets">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    Hardware Assets Audit Log
                    <span class="report-tab-badge"><?php echo count($assetsList); ?></span>
                </button>
                <button type="button" class="report-module-tab" data-module="licenses">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Software Licenses &amp; Renewals
                    <span class="report-tab-badge"><?php echo count($licensesList); ?></span>
                </button>
                <button type="button" class="report-module-tab" data-module="tickets">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    IT Helpdesk &amp; Incident SLA
                    <span class="report-tab-badge"><?php echo count($ticketsList); ?></span>
                </button>
                <button type="button" class="report-module-tab" data-module="scrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                    Scrap &amp; Salvage Disposal Register
                    <span class="report-tab-badge"><?php echo count($scrapList); ?></span>
                </button>
            </div>
        </div>

        <!-- Unified Toolbar Row -->
        <div class="reports-toolbar-row">
            <div class="reports-toolbar-left" id="reportsSubFilterContainer">
                <button type="button" class="reports-sub-filter-btn active" data-filter="all">All Assets</button>
                <button type="button" class="reports-sub-filter-btn" data-filter="in use">In Use</button>
                <button type="button" class="reports-sub-filter-btn" data-filter="available">In Stock</button>
                <button type="button" class="reports-sub-filter-btn" data-filter="maintenance">Maintenance</button>
                <button type="button" class="reports-sub-filter-btn" data-filter="scrapped">Scrapped</button>
            </div>

            <div class="reports-toolbar-right">
                <div class="reports-search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--text-secondary); flex-shrink: 0;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="reportSearchInput" placeholder="Live search table...">
                </div>

                <select id="reportCategoryFilter" class="reports-select-filter">
                    <option value="">All Categories / Types</option>
                </select>
            </div>
        </div>

        <!-- ==================== PANE 1: HARDWARE ASSETS AUDIT LOG ==================== -->
        <div class="report-module-pane" data-module="assets">
            <div class="table-responsive">
                <table class="reports-data-table" id="reportAssetsTable">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Device Name &amp; Model</th>
                            <th>Category</th>
                            <th>Serial Number</th>
                            <th>Assigned Custodian</th>
                            <th>Department &amp; Location</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Cost (₹)</th>
                            <th>Logged Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assetsList)): ?>
                            <?php foreach ($assetsList as $a): ?>
                                <?php 
                                    $st = trim($a['status'] ?? 'Available');
                                    $stClass = 'status-available';
                                    if (stripos($st, 'in use') !== false || stripos($st, 'assign') !== false) $stClass = 'status-inuse';
                                    elseif (stripos($st, 'maint') !== false) $stClass = 'status-maintenance';
                                    elseif (stripos($st, 'scrap') !== false || stripos($st, 'retired') !== false) $stClass = 'status-scrapped';
                                    
                                    $custodian = !empty($a['assigned_to']) ? $a['assigned_to'] : 'Unallocated';
                                    $dept = !empty($a['department']) ? $a['department'] : 'General';
                                    $loc = !empty($a['location']) ? $a['location'] : 'Corporate HQ';
                                    $cost = floatval($a['cost'] ?? 0);
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($st); ?>" data-category="<?php echo htmlspecialchars($a['category'] ?? 'General'); ?>">
                                    <td><span class="reports-code-tag"><?php echo htmlspecialchars($a['tag']); ?></span></td>
                                    <td>
                                        <strong style="color: var(--navy-deep);"><?php echo htmlspecialchars($a['name']); ?></strong>
                                        <?php if (!empty($a['brand']) || !empty($a['model'])): ?>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">
                                                <?php echo htmlspecialchars(trim(($a['brand'] ?? '') . ' ' . ($a['model'] ?? ''))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($a['category'] ?? 'General'); ?></span></td>
                                    <td><span class="reports-serial-tag"><?php echo htmlspecialchars(!empty($a['serial']) ? $a['serial'] : 'N/A'); ?></span></td>
                                    <td>
                                        <?php if ($custodian !== 'Unallocated'): ?>
                                            <span style="font-weight: 600; color: var(--navy-deep); display: inline-flex; align-items: center; gap: 5px;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                <?php echo htmlspecialchars($custodian); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-style: italic;">Unallocated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($dept); ?></div>
                                        <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($loc); ?></div>
                                    </td>
                                    <td><span style="font-size: 11.5px;"><?php echo htmlspecialchars($a['condition'] ?? 'Good'); ?></span></td>
                                    <td><span class="reports-status-badge <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                    <td style="font-weight: 600; color: var(--navy-deep);">₹<?php echo number_format($cost); ?></td>
                                    <td style="color: var(--text-secondary); font-size: 12px;"><?php echo htmlspecialchars($a['added_date'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-records-row">
                                <td colspan="10" style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--navy-deep); margin-bottom: 4px;">No Hardware Assets Registered</div>
                                    <div style="font-size: 12.5px;">Add devices in the Asset Management section to populate audit records.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="reports-table-footer">
                <div>Showing <strong class="reports-count-display"><?php echo count($assetsList); ?></strong> hardware asset records</div>
                <div>Capital Inventory Valuation: <strong style="color: var(--navy-deep);">₹<?php echo number_format($totalAssetCost); ?></strong></div>
            </div>
        </div>

        <!-- ==================== PANE 2: SOFTWARE LICENSES & RENEWALS ==================== -->
        <div class="report-module-pane" data-module="licenses" style="display: none;">
            <div class="table-responsive">
                <table class="reports-data-table" id="reportLicensesTable">
                    <thead>
                        <tr>
                            <th>Software / Product</th>
                            <th>Publisher</th>
                            <th>Category</th>
                            <th>Version</th>
                            <th>License Type</th>
                            <th>Seats Allocated</th>
                            <th>Expiry Date</th>
                            <th>Compliance Status</th>
                            <th>Annual Cost (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($licensesList)): ?>
                            <?php foreach ($licensesList as $lic): ?>
                                <?php 
                                    $st = trim($lic['status'] ?? 'Active');
                                    $stClass = 'status-active';
                                    if (!empty($lic['is_expired'])) {
                                        $st = 'Expired';
                                        $stClass = 'status-expired';
                                    } elseif (!empty($lic['is_expiring'])) {
                                        $st = 'Expiring Soon';
                                        $stClass = 'status-maintenance';
                                    }
                                    $cost = floatval($lic['total_cost'] ?? 0);
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($st); ?>" data-category="<?php echo htmlspecialchars($lic['category'] ?? 'Application'); ?>">
                                    <td><strong style="color: var(--navy-deep);"><?php echo htmlspecialchars($lic['software_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($lic['publisher'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($lic['category'] ?? 'Software'); ?></td>
                                    <td><span class="reports-serial-tag"><?php echo htmlspecialchars($lic['version'] ?? 'Standard'); ?></span></td>
                                    <td><?php echo htmlspecialchars($lic['license_type'] ?? 'Subscription'); ?></td>
                                    <td><strong><?php echo intval($lic['seats'] ?? 1); ?></strong> Seats</td>
                                    <td style="color: var(--text-secondary); font-size: 12px;"><?php echo htmlspecialchars($lic['formatted_expiry'] ?? 'Perpetual'); ?></td>
                                    <td><span class="reports-status-badge <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                    <td style="font-weight: 600; color: var(--navy-deep);">₹<?php echo number_format($cost); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-records-row">
                                <td colspan="9" style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--navy-deep); margin-bottom: 4px;">No Software Licenses Found</div>
                                    <div style="font-size: 12.5px;">Register corporate applications in the Software Licenses register to track compliance.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="reports-table-footer">
                <div>Showing <strong class="reports-count-display"><?php echo count($licensesList); ?></strong> software licenses</div>
                <div>Total Software Expenditure: <strong style="color: var(--navy-deep);">₹<?php echo number_format($totalLicCost); ?></strong></div>
            </div>
        </div>

        <!-- ==================== PANE 3: IT HELPDESK & INCIDENTS ==================== -->
        <div class="report-module-pane" data-module="tickets" style="display: none;">
            <div class="table-responsive">
                <table class="reports-data-table" id="reportTicketsTable">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Issue Summary</th>
                            <th>Category</th>
                            <th>Affected Device</th>
                            <th>Requester Staff</th>
                            <th>Department</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Incident Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ticketsList)): ?>
                            <?php foreach ($ticketsList as $t): ?>
                                <?php 
                                    $st = trim($t['status'] ?? 'Open');
                                    $stClass = 'status-open';
                                    if (stripos($st, 'resolved') !== false || stripos($st, 'closed') !== false) $stClass = 'status-resolved';
                                    elseif (stripos($st, 'progress') !== false || stripos($st, 'pending') !== false) $stClass = 'status-progress';

                                    $prio = trim($t['priority'] ?? 'Medium');
                                    $prioClass = 'pill-cyan';
                                    if (stripos($prio, 'urgent') !== false || stripos($prio, 'high') !== false) $prioClass = 'pill-red';
                                    elseif (stripos($prio, 'medium') !== false) $prioClass = 'pill-amber';
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($st); ?>" data-category="<?php echo htmlspecialchars($t['category'] ?? 'IT Support'); ?>">
                                    <td><span class="reports-code-tag"><?php echo htmlspecialchars($t['ticket_no']); ?></span></td>
                                    <td><strong style="color: var(--navy-deep);"><?php echo htmlspecialchars($t['subject']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($t['category'] ?? 'General'); ?></td>
                                    <td><span class="reports-serial-tag"><?php echo htmlspecialchars($t['asset_name'] ?? 'General IT'); ?></span></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--navy-deep);"><?php echo htmlspecialchars($t['employee_name'] ?? 'Staff'); ?></div>
                                        <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($t['emp_code'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['department'] ?? 'General'); ?></td>
                                    <td><span class="reports-pill-sm <?php echo $prioClass; ?>"><?php echo htmlspecialchars($prio); ?></span></td>
                                    <td><span class="reports-status-badge <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                    <td style="color: var(--text-secondary); font-size: 12px;"><?php echo htmlspecialchars($t['incident_dt'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-records-row">
                                <td colspan="9" style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--navy-deep); margin-bottom: 4px;">No IT Tickets Logged</div>
                                    <div style="font-size: 12.5px;">Tickets submitted via Support Desk will automatically appear in this audit log.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="reports-table-footer">
                <div>Showing <strong class="reports-count-display"><?php echo count($ticketsList); ?></strong> support desk tickets</div>
                <div>SLA Resolution Status: <strong style="color: #059669;"><?php echo $resolvedTickets; ?> Resolved</strong> / <strong style="color: #d97706;"><?php echo $openTickets; ?> Pending</strong></div>
            </div>
        </div>

        <!-- ==================== PANE 4: SCRAP & SALVAGE DISPOSAL ==================== -->
        <div class="report-module-pane" data-module="scrap" style="display: none;">
            <div class="table-responsive">
                <table class="reports-data-table" id="reportScrapTable">
                    <thead>
                        <tr>
                            <th>Ref Code</th>
                            <th>Decommissioned Hardware</th>
                            <th>Item Classification</th>
                            <th>Dismantled From / Tag</th>
                            <th>Defect Reason</th>
                            <th>Storage Bin / Location</th>
                            <th>Disposal Target</th>
                            <th>Salvage Realization (₹)</th>
                            <th>Authorized By</th>
                            <th>Disposal Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($scrapList)): ?>
                            <?php foreach ($scrapList as $s): ?>
                                <?php 
                                    $st = trim($s['disposal_status'] ?? 'In Scrap Yard');
                                    $stClass = 'status-yard';
                                    if (stripos($st, 'recycle') !== false || stripos($st, 'e-waste') !== false) $stClass = 'status-resolved';
                                    elseif (stripos($st, 'sold') !== false) $stClass = 'status-inuse';
                                    elseif (stripos($st, 'written') !== false) $stClass = 'status-scrapped';
                                    $val = floatval($s['salvage_value'] ?? 0);
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($st); ?>" data-category="<?php echo htmlspecialchars($s['item_type']); ?>">
                                    <td><span class="reports-code-tag"><?php echo htmlspecialchars($s['scrap_ref']); ?></span></td>
                                    <td><strong style="color: var(--navy-deep);"><?php echo htmlspecialchars($s['item_name']); ?></strong></td>
                                    <td><span style="font-size: 11.5px; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($s['item_type']); ?></span></td>
                                    <td><span class="reports-serial-tag"><?php echo htmlspecialchars(!empty($s['host_asset']) ? $s['host_asset'] : 'Standalone'); ?></span></td>
                                    <td style="color: #b45309; font-size: 12px; font-weight: 500;"><?php echo htmlspecialchars($s['reason']); ?></td>
                                    <td style="color: var(--text-secondary); font-size: 12px;"><?php echo htmlspecialchars($s['bin_location'] ?? 'Scrap Yard'); ?></td>
                                    <td><span class="reports-status-badge <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                    <td style="font-weight: 700; color: var(--navy-deep);">₹<?php echo number_format($val); ?></td>
                                    <td style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($s['authorized_by'] ?? 'Abhishek Sharma'); ?></td>
                                    <td style="color: var(--text-secondary); font-size: 12px;"><?php echo htmlspecialchars($s['scrap_date'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-records-row">
                                <td colspan="10" style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
                                    <div style="font-weight: 600; font-size: 14px; color: var(--navy-deep); margin-bottom: 4px;">No Decommissioned Items Recorded</div>
                                    <div style="font-size: 12.5px;">Record scrapped assets and parts in the Scrap / Dismantle section to track salvage value.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="reports-table-footer">
                <div>Showing <strong class="reports-count-display"><?php echo count($scrapList); ?></strong> decommissioned hardware items</div>
                <div>Realized Scrap Salvage: <strong style="color: #047857;">₹<?php echo number_format($totalSalvageRealized); ?></strong></div>
            </div>
        </div>

    </div>

    <!-- Official Audit Tripartite Signature Block (Visible in Print View) -->
    <table class="print-signatures-tbl">
        <tr>
            <td style="width: 33%;">
                <div class="sig-line">Prepared By: IT Operations Officer</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Signature: ______________________<br>Date: ______________________</div>
            </td>
            <td style="width: 34%; text-align: center;">
                <div class="sig-line">Verified By: Internal IT Auditor</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Signature: ______________________<br>Date: ______________________</div>
            </td>
            <td style="width: 33%; text-align: right;">
                <div class="sig-line">Approved By: Head of IT / Management</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Signature: ______________________<br>Date: ______________________</div>
            </td>
        </tr>
    </table>

</main>

<?php include 'includes/footer.php'; ?>
