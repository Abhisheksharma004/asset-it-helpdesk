<?php
// VIROS IT Portal - Software & License Management Workspace
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Software & Licenses - VIROS Portal";
$active_page = "software_licenses";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/asset_return.css', 'css/software_licenses.css'];
$extra_js  = ['js/software_licenses.js'];

// Include database to fetch live employees, vendors, and departments
require_once __DIR__ . '/config/db.php';

$employeesList = [];
$departmentsList = [];
$vendorsList = [];
$assetsList = [];

if (isset($conn) && $conn !== false) {
    // 1. Fetch active employees
    $empStmt = sqlsrv_query($conn, "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.designation, d.department_name 
                                    FROM employees e 
                                    LEFT JOIN departments d ON e.department_id = d.id 
                                    WHERE e.status = 'Active' 
                                    ORDER BY e.first_name ASC");
    if ($empStmt !== false) {
        while ($e = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
            $fullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
            $employeesList[] = [
                'id'          => intval($e['id']),
                'name'        => $fullName ?: 'Employee #' . $e['id'],
                'emp_code'    => $e['emp_code'] ?? ('EMP-' . $e['id']),
                'email'       => $e['email'] ?? '',
                'designation' => $e['designation'] ?? 'Staff',
                'department'  => $e['department_name'] ?? 'General'
            ];
        }
        sqlsrv_free_stmt($empStmt);
    }

    // 2. Fetch departments
    $deptStmt = sqlsrv_query($conn, "SELECT department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    if ($deptStmt !== false) {
        while ($d = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
            $departmentsList[] = $d['department_name'];
        }
        sqlsrv_free_stmt($deptStmt);
    }

    // 3. Fetch vendors
    $vndStmt = sqlsrv_query($conn, "SELECT id, vendor_name FROM vendors WHERE status = 'Active' ORDER BY vendor_name ASC");
    if ($vndStmt !== false) {
        while ($v = sqlsrv_fetch_array($vndStmt, SQLSRV_FETCH_ASSOC)) {
            $vendorsList[] = $v['vendor_name'];
        }
        sqlsrv_free_stmt($vndStmt);
    }

    // 4. Fetch hardware assets
    $astStmt = sqlsrv_query($conn, "SELECT id, tag, name, brand, model FROM assets ORDER BY id DESC");
    if ($astStmt !== false) {
        while ($a = sqlsrv_fetch_array($astStmt, SQLSRV_FETCH_ASSOC)) {
            $assetsList[] = [
                'id'    => intval($a['id']),
                'tag'   => $a['tag'] ?? 'AST-00',
                'name'  => $a['name'] ?? 'Hardware',
                'label' => ($a['tag'] ?? '') . ' — ' . ($a['name'] ?? '')
            ];
        }
        sqlsrv_free_stmt($astStmt);
    }
}

// Default fallback lists if database entries are limited
if (empty($departmentsList)) {
    $departmentsList = ['IT & Infrastructure', 'Software Engineering', 'Human Resources', 'Finance & Accounts', 'Operations & Logistics', 'Marketing & Design'];
}
if (empty($vendorsList)) {
    $vendorsList = ['Microsoft Direct', 'Adobe Systems India', 'JetBrains s.r.o.', 'Atlassian Pty', 'Redington India Ltd', 'Ingram Micro', 'Tech Data Corporation'];
}

// Fetch Software & Licenses Dataset directly from Database
$initialLicenses = [];

if (isset($conn) && $conn !== false) {
    // Ensure table exists strictly with the fields present in the Add Software License form
    $licTableSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='software_licenses' AND xtype='U')
    BEGIN
        CREATE TABLE software_licenses (
            id INT IDENTITY(1,1) PRIMARY KEY,
            software_name NVARCHAR(200) NOT NULL,
            publisher NVARCHAR(150) NOT NULL,
            category NVARCHAR(100) NOT NULL,
            version NVARCHAR(100) NULL,
            license_type NVARCHAR(100) NOT NULL,
            license_key NVARCHAR(250) NULL,
            vendor NVARCHAR(150) NULL,
            status NVARCHAR(50) NOT NULL DEFAULT 'Active',
            purchase_date NVARCHAR(50) NULL,
            expiry_date NVARCHAR(50) NULL,
            total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
            notes NVARCHAR(MAX) NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            updated_at DATETIME NOT NULL DEFAULT GETDATE()
        );
    END";
    sqlsrv_query($conn, $licTableSql);

    $licStmt = sqlsrv_query($conn, "SELECT * FROM software_licenses ORDER BY id DESC");
    if ($licStmt !== false) {
        while ($row = sqlsrv_fetch_array($licStmt, SQLSRV_FETCH_ASSOC)) {
            $swName = $row['software_name'] ?? ($row['name'] ?? '');
            $pub = $row['publisher'] ?? '';
            $pLower = strtolower($pub);
            $brand = 'default';
            if (strpos($pLower, 'micro') !== false) $brand = 'msft';
            elseif (strpos($pLower, 'adobe') !== false) $brand = 'adobe';
            elseif (strpos($pLower, 'jet') !== false) $brand = 'jb';
            elseif (strpos($pLower, 'slack') !== false) $brand = 'slack';
            elseif (strpos($pLower, 'git') !== false) $brand = 'github';
            elseif (strpos($pLower, 'atlass') !== false) $brand = 'atlassian';
            elseif (strpos($pLower, 'crowd') !== false) $brand = 'crowdstrike';

            $expDateStr = $row['expiry_date'] ?? '';
            $rawStatus = $row['status'] ?? 'Active';
            $calc = computeLicenseExpiryStatus($expDateStr, $rawStatus);

            $initialLicenses[] = [
                'id'             => intval($row['id']),
                'name'           => $swName,
                'software_name'  => $swName,
                'publisher'      => $pub,
                'brand_code'     => $brand,
                'category'       => $row['category'] ?? '',
                'version'        => $row['version'] ?? '',
                'license_type'   => $row['license_type'] ?? '',
                'license_key'    => $row['license_key'] ?? '',
                'vendor'         => $row['vendor'] ?? '',
                'purchase_date'  => $row['purchase_date'] ?? '',
                'expiry_date'    => $expDateStr,
                'total_cost'     => floatval($row['total_cost'] ?? 0),
                'currency'       => '₹',
                'status'         => $calc['status'],
                'raw_status'     => $rawStatus,
                'days_remaining' => $calc['days'],
                'expiry_label'   => $calc['label'],
                'badge_class'    => $calc['badge_class'],
                'notes'          => $row['notes'] ?? '',
                'allocations'    => []
            ];
        }
        sqlsrv_free_stmt($licStmt);
    }
}

// Helper function: Dynamic Expiry Status Calculation from Today's Date
function computeLicenseExpiryStatus($expiryDateStr, $defaultStatus = 'Active') {
    $expStr = trim($expiryDateStr ?? '');
    if ($expStr === '' || strcasecmp($expStr, 'Perpetual') === 0 || strcasecmp($expStr, 'Lifetime') === 0) {
        return [
            'status' => 'Active',
            'days' => null,
            'label' => 'Lifetime Perpetual',
            'badge_class' => 'safe'
        ];
    }

    $expTs = strtotime($expStr);
    if ($expTs === false) {
        return [
            'status' => $defaultStatus ?: 'Active',
            'days' => null,
            'label' => $expStr,
            'badge_class' => 'safe'
        ];
    }

    $todayTs = strtotime(date('Y-m-d'));
    $diffDays = (int) floor(($expTs - $todayTs) / 86400);

    if ($diffDays < 0) {
        $absDays = abs($diffDays);
        return [
            'status' => 'Expired',
            'days' => $diffDays,
            'label' => 'Expired ' . ($absDays === 1 ? '1 day' : "{$absDays} days") . ' ago',
            'badge_class' => 'expired'
        ];
    } elseif ($diffDays <= 30) {
        return [
            'status' => 'Expiring Soon',
            'days' => $diffDays,
            'label' => ($diffDays === 0) ? 'Expires Today!' : "Expires in {$diffDays} day" . ($diffDays === 1 ? '' : 's'),
            'badge_class' => 'warn'
        ];
    } else {
        return [
            'status' => 'Active',
            'days' => $diffDays,
            'label' => "Active ({$diffDays} days left)",
            'badge_class' => 'safe'
        ];
    }
}

// Calculate Live KPI Metrics dynamically based on computed statuses
$totalLicenses = count($initialLicenses);
$activeLicensesCount = 0;
$expiringCount = 0;
$expiredCount = 0;
$totalSpendUSD = 0;

foreach ($initialLicenses as $lic) {
    $totalSpendUSD += floatval($lic['total_cost']);
    if ($lic['status'] === 'Active') {
        $activeLicensesCount++;
    } elseif ($lic['status'] === 'Expiring Soon') {
        $expiringCount++;
    } elseif ($lic['status'] === 'Expired') {
        $expiredCount++;
    }
}

// Include Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- =========================================================================
     SOFTWARE & LICENSES MANAGEMENT WORKSPACE
     ========================================================================= -->
<main class="dashboard-content">

    <!-- Page Header Bar -->
    <div class="software-page-header">
        <div class="software-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="assets.php">Assets</a>
                <span>/</span>
                <span>Software Licenses</span>
            </div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                    <path d="M12 7v4"></path>
                    <circle cx="12" cy="12" r="0.5"></circle>
                </svg>
                Software &amp; License Management
            </h1>
            <p>Track software licenses, SaaS subscriptions, license keys, seat allocations, expiration dates, and vendor compliance.</p>
        </div>

        <div class="software-header-actions">
            <button type="button" class="btn-secondary" id="exportLicensesBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button type="button" class="btn-secondary" id="openRenewalLogsBtn" title="View all software license renewal history and audit logs">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Renewal Logs
            </button>
            <button type="button" class="btn-secondary" id="openRenewLicenseBtn" style="color: #0369a1; border-color: #bae6fd; background: #f0f9ff; font-weight: 600;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
                Renew License
            </button>
            <button type="button" class="btn-primary" id="openAddLicenseBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Software License
            </button>
        </div>
    </div>

    <!-- Top KPI Stats Grid -->
    <div class="lic-stats-grid">
        <!-- Total Licenses -->
        <div class="lic-stat-card card-total" data-filter-tab="all" title="Click to view all licenses">
            <div class="lic-stat-info">
                <div class="stat-lbl">Total Licenses</div>
                <div class="stat-val" id="kpiTotalLicenses"><?php echo $totalLicenses; ?></div>
                <div class="stat-sub">
                    <span>Registered software catalog</span>
                </div>
            </div>
            <div class="lic-stat-icon indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <!-- Active Licenses -->
        <div class="lic-stat-card card-in-use" data-filter-tab="all" title="Click to view active licenses">
            <div class="lic-stat-info">
                <div class="stat-lbl">Active Licenses</div>
                <div class="stat-val" id="kpiActiveLicenses" style="color: #059669;"><?php echo $activeLicensesCount; ?></div>
                <div class="stat-sub">
                    <span style="font-weight: 700; color: #059669;">● Compliant</span>
                    <span>in deployment</span>
                </div>
            </div>
            <div class="lic-stat-icon emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
        </div>

        <!-- Expiring Soon -->
        <div class="lic-stat-card card-expiring" data-filter-tab="expiring" title="Click to view licenses expiring in 30 days">
            <div class="lic-stat-info">
                <div class="stat-lbl">Expiring Soon</div>
                <div class="stat-val" id="kpiExpiringCount" style="color: #d97706;"><?php echo $expiringCount; ?></div>
                <div class="stat-sub">
                    <span style="color: #ea580c; font-weight: 700;">Within 30 days</span>
                </div>
            </div>
            <div class="lic-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>

        <!-- Total Spend -->
        <div class="lic-stat-card card-cost" title="Annual License Valuation">
            <div class="lic-stat-info">
                <div class="stat-lbl">Annual Cost</div>
                <div class="stat-val" id="kpiTotalCost" style="color: #001938;">₹<?php echo number_format($totalSpendUSD, 0); ?></div>
                <div class="stat-sub">
                    <span>Enterprise software budget</span>
                </div>
            </div>
            <div class="lic-stat-icon navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
        </div>
    </div>


    <!-- Search & Filter Toolbar -->
    <div class="asset-toolbar" style="margin-bottom: 16px;">
        <div class="toolbar-left" style="flex-wrap: wrap;">
            <!-- Live Search -->
            <div class="asset-search-wrapper" style="min-width: 290px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="licSearchInput" placeholder="Search by Software, Publisher, Key, Category...">
            </div>

            <!-- Category Filter -->
            <select class="asset-filter-select" id="licCategoryFilter">
                <option value="all">All Categories</option>
                <option value="Office & Productivity">Office &amp; Productivity</option>
                <option value="Developer Tools">Developer Tools</option>
                <option value="Design & Media">Design &amp; Media</option>
                <option value="Operating Systems">Operating Systems</option>
                <option value="Security & Antivirus">Security &amp; Antivirus</option>
                <option value="Cloud & SaaS">Cloud &amp; SaaS</option>
            </select>

            <!-- License Type Filter -->
            <select class="asset-filter-select" id="licTypeFilter">
                <option value="all">All License Types</option>
                <option value="SaaS Subscription">SaaS Subscription</option>
                <option value="Per-Seat / Volume">Per-Seat / Volume</option>
                <option value="OEM / Perpetual">OEM / Perpetual</option>
                <option value="Perpetual">Perpetual</option>
            </select>

            <!-- Status Filter -->
            <select class="asset-filter-select" id="licStatusFilter">
                <option value="all">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Expiring Soon">Expiring Soon</option>
                <option value="Expired">Expired</option>
            </select>

            <!-- Reset Filter Button -->
            <button type="button" class="btn-secondary" id="resetLicFiltersBtn" style="padding: 7px 12px; font-size: 12.5px;" title="Reset Filters">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Reset
            </button>
        </div>

        <div class="toolbar-right">
            <span style="font-size: 12.5px; color: var(--text-muted);" id="licTableCountText">Showing <?php echo $totalLicenses; ?> licenses</span>
        </div>
    </div>

    <!-- Software Licenses Data Table Card -->
    <div class="asset-table-card">
        <div class="asset-table-responsive">
            <table class="asset-data-table" id="licensesTable">
                <thead>
                    <tr>
                        <th style="width: 36px;">
                            <input type="checkbox" class="custom-checkbox" id="selectAllLic">
                        </th>
                        <th style="min-width: 250px;">Software &amp; Publisher</th>
                        <th style="min-width: 150px;">License Type</th>
                        <th style="min-width: 175px;">Product Key</th>
                        <th style="min-width: 140px;">Renewal / Expiry</th>
                        <th style="min-width: 140px;">Vendor &amp; Spend</th>
                        <th style="min-width: 120px;">Status</th>
                        <th style="width: 140px; text-align: right; padding-right: 14px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="licensesTbody">
                    <?php foreach ($initialLicenses as $lic): 
                        $brandClass = 'brand-default';
                        if ($lic['brand_code'] === 'msft') $brandClass = 'brand-msft';
                        elseif ($lic['brand_code'] === 'adobe') $brandClass = 'brand-adobe';
                        elseif ($lic['brand_code'] === 'jb') $brandClass = 'brand-jb';
                        elseif ($lic['brand_code'] === 'slack') $brandClass = 'brand-slack';
                        elseif ($lic['brand_code'] === 'github') $brandClass = 'brand-github';
                        elseif ($lic['brand_code'] === 'atlassian') $brandClass = 'brand-atlassian';
                        elseif ($lic['brand_code'] === 'crowdstrike') $brandClass = 'brand-crowdstrike';

                        $initialLogo = strtoupper(substr($lic['name'], 0, 2));

                        $statusBadgeClass = 'lic-badge-active';
                        if ($lic['status'] === 'Expiring Soon') $statusBadgeClass = 'lic-badge-expiring';
                        elseif ($lic['status'] === 'Expired') $statusBadgeClass = 'lic-badge-expired';
                    ?>
                        <tr data-lic-id="<?php echo $lic['id']; ?>">
                            <td>
                                <input type="checkbox" class="custom-checkbox row-select-checkbox" value="<?php echo $lic['id']; ?>">
                            </td>
                            <td>
                                <div class="software-cell">
                                    <div class="software-logo-badge <?php echo $brandClass; ?>">
                                        <?php echo $initialLogo; ?>
                                    </div>
                                    <div class="software-info">
                                        <div class="software-info-title">
                                            <a href="javascript:void(0)" onclick="openLicenseDrawer(<?php echo $lic['id']; ?>)" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($lic['name']); ?>
                                            </a>
                                        </div>
                                        <div class="software-info-sub">
                                            <span><?php echo htmlspecialchars($lic['publisher']); ?></span>
                                            <span>&bull;</span>
                                            <span class="lic-category-tag"><?php echo htmlspecialchars($lic['category']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="type-pill type-pill-saas">
                                    <?php echo htmlspecialchars($lic['license_type']); ?>
                                </span>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                                    <?php echo htmlspecialchars($lic['version']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="license-key-wrapper" title="Click to copy key">
                                    <span><?php echo htmlspecialchars($lic['license_key']); ?></span>
                                    <button type="button" class="btn-copy-inline" onclick="copyLicenseKey('<?php echo htmlspecialchars($lic['license_key']); ?>')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="expiry-cell">
                                    <div class="date-str"><?php echo htmlspecialchars($lic['expiry_date']); ?></div>
                                    <?php if ($lic['status'] === 'Expiring Soon'): ?>
                                        <span class="days-pill warn">&#9888; Renewal Due Soon</span>
                                    <?php elseif ($lic['expiry_date'] === 'Perpetual'): ?>
                                        <span class="days-pill safe">Lifetime Perpetual</span>
                                    <?php else: ?>
                                        <span class="days-pill safe">Active License</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 12.5px; color: #0f172a;">
                                    ₹<?php echo number_format($lic['total_cost'], 0); ?>
                                </div>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?php echo htmlspecialchars($lic['vendor']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="lic-badge <?php echo $statusBadgeClass; ?>">
                                    <span class="dot"></span>
                                    <?php echo htmlspecialchars($lic['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                    <button type="button" class="action-icon-btn btn-view" title="View License Details" onclick="openLicenseDrawer(<?php echo $lic['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-renew <?php echo ($lic['status'] === 'Expiring Soon') ? 'due-pulse' : ''; ?>" title="Renew License" onclick="openRenewModal(<?php echo $lic['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-view" title="Edit License" onclick="editLicense(<?php echo $lic['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Pagination & Footer -->
        <div class="asset-table-footer" style="padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Showing 1 to <?php echo $totalLicenses; ?> of <?php echo $totalLicenses; ?> software items
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="btn-secondary" style="padding: 5px 12px; font-size: 12px;" disabled>Previous</button>
                <button type="button" class="btn-primary" style="padding: 5px 12px; font-size: 12px;">1</button>
                <button type="button" class="btn-secondary" style="padding: 5px 12px; font-size: 12px;" disabled>Next</button>
            </div>
        </div>
    </div>

</main>

<!-- =========================================================================
     SLIDE-OVER DRAWER: SOFTWARE DETAILS & SEAT ALLOCATION DIRECTORY
     ========================================================================= -->
<div class="lic-drawer-overlay" id="licenseDrawerOverlay" onclick="closeLicenseDrawer(event)">
    <div class="lic-drawer" id="licenseDrawer" onclick="event.stopPropagation()">
        <!-- Drawer Header -->
        <div class="lic-drawer-header">
            <div class="lic-drawer-title-wrap">
                <div class="software-logo-badge brand-default" id="drawerLogoBadge" style="width: 44px; height: 44px; font-size: 16px;">
                    SW
                </div>
                <div>
                    <h2 style="font-size: 17px; font-weight: 800; color: #001938; margin: 0;" id="drawerSoftwareTitle">—</h2>
                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;" id="drawerSoftwarePublisher">—</div>
                </div>
            </div>
            <button type="button" class="lic-drawer-close-btn" onclick="closeLicenseDrawer()" title="Close Drawer">&times;</button>
        </div>

        <!-- Drawer Body -->
        <div class="lic-drawer-body">
            <!-- Top Stats -->
            <div class="drawer-stats-cards">
                <div class="drawer-stat-box">
                    <div class="box-lbl">License Model</div>
                    <div class="box-val" id="drawerStatModel" style="font-size: 13.5px;">—</div>
                </div>
                <div class="drawer-stat-box">
                    <div class="box-lbl">License Status</div>
                    <div class="box-val" id="drawerStatStatus" style="font-size: 13.5px;">—</div>
                </div>
                <div class="drawer-stat-box">
                    <div class="box-lbl">Total Spend</div>
                    <div class="box-val" id="drawerStatCost" style="font-size: 14px; color: #001938; font-weight: 800;">—</div>
                </div>
            </div>

            <!-- License Information Grid -->
            <div class="drawer-sec-title">
                <span>License Details &amp; Purchasing</span>
                <span class="lic-badge lic-badge-active" id="drawerStatusBadge"><span class="dot"></span> Active</span>
            </div>
            <div class="drawer-info-grid">
                <div class="drawer-info-item">
                    <div class="info-lbl">License Model</div>
                    <div class="info-val" id="drawerLicType">—</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Category</div>
                    <div class="info-val" id="drawerLicCategory">—</div>
                </div>
                <div class="drawer-info-item" style="grid-column: span 2;">
                    <div class="info-lbl">Product License Key</div>
                    <div class="info-val" style="font-family: monospace; background: #f8fafc; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 8px;">
                        <span id="drawerLicenseKey">—</span>
                        <button type="button" class="btn-copy-inline" onclick="copyDrawerKey()" title="Copy key">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Purchase Order / Invoice</div>
                    <div class="info-val" id="drawerPoNumber">—</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Supplier / Vendor</div>
                    <div class="info-val" id="drawerVendor">—</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Purchase Date</div>
                    <div class="info-val" id="drawerPurchaseDate">—</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Expiry / Renewal Date</div>
                    <div class="info-val" id="drawerExpiryDate">—</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Unit Cost</div>
                    <div class="info-val" id="drawerCostPerSeat">—</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Total Annual Spend</div>
                    <div class="info-val" id="drawerTotalCost" style="color: #001938; font-weight: 800;">—</div>
                </div>
            </div>

            <!-- Notes & Compliance -->
            <div class="drawer-sec-title">
                <span>Notes &amp; Remarks</span>
            </div>
            <p style="font-size: 12.5px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin: 0 0 20px 0;" id="drawerNotes">—</p>

            <!-- Renewal History & Audit Logs -->
            <div class="drawer-sec-title" style="margin-top: 15px;">
                <span>Renewal History &amp; Audit Trail</span>
                <span id="drawerRenewalLogCount" style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px; font-weight: 700;">0 Logs</span>
            </div>
            <div id="drawerRenewalLogsContainer" class="drawer-renewal-logs-wrap">
                <div class="renewal-empty-state">
                    No renewal audit records recorded yet.
                </div>
            </div>
        </div>

        <!-- Drawer Footer -->
        <div class="lic-drawer-footer">
            <button type="button" class="btn-secondary" onclick="closeLicenseDrawer()">Close</button>
            <button type="button" class="btn-primary" id="drawerRenewLicBtn" onclick="renewCurrentDrawerLicense()" style="background: linear-gradient(135deg, #0284c7, #0369a1); border-color: #0284c7;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="margin-right: 4px;">
                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
                Renew License
            </button>
            <button type="button" class="btn-secondary" id="drawerEditLicBtn" onclick="editCurrentDrawerLicense()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                Edit License
            </button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 1: ADD / EDIT SOFTWARE LICENSE (CLEAN & SIMPLE DESIGN)
     ========================================================================= -->
<div class="modal-overlay" id="addLicenseModalOverlay" style="display: none;">
    <div class="modal-box" style="max-width: 660px;">
        <div class="modal-header">
            <h3 id="modalLicenseTitle">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                <span>Add Software License</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeAddLicenseModal()" title="Close">&times;</button>
        </div>

        <form id="licenseForm" onsubmit="saveLicenseForm(event)">
            <input type="hidden" id="editLicenseId" value="">
            <div class="modal-body" style="padding: 20px 24px;">
                
                <!-- Row 1: Name & Publisher -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="formSoftwareName">Software Name <span class="req">*</span></label>
                        <input type="text" id="formSoftwareName" placeholder="e.g. Adobe Creative Cloud, Windows 11" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="formPublisher">Publisher / Manufacturer <span class="req">*</span></label>
                        <input type="text" id="formPublisher" placeholder="e.g. Microsoft, Adobe, JetBrains" required>
                    </div>
                </div>

                <!-- Row 2: Category & Version -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="formCategory">Category <span class="req">*</span></label>
                        <select id="formCategory" required>
                            <option value="Office & Productivity">Office &amp; Productivity</option>
                            <option value="Developer Tools">Developer Tools</option>
                            <option value="Design & Media">Design &amp; Media</option>
                            <option value="Operating Systems">Operating Systems</option>
                            <option value="Security & Antivirus">Security &amp; Antivirus</option>
                            <option value="Cloud & SaaS">Cloud &amp; SaaS</option>
                            <option value="Utilities & IT Ops">Utilities &amp; IT Ops</option>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="formVersion">Version / Edition</label>
                        <input type="text" id="formVersion" placeholder="e.g. 2026 Enterprise, Pro, v24">
                    </div>
                </div>

                <!-- Row 3: License Model & License Key -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="formLicenseType">License Type <span class="req">*</span></label>
                        <select id="formLicenseType" required>
                            <option value="SaaS Subscription">SaaS Subscription</option>
                            <option value="Per-Seat / Volume">Per-Seat / Volume</option>
                            <option value="OEM / Perpetual">OEM / Perpetual</option>
                            <option value="Perpetual">Perpetual (One-Time)</option>
                            <option value="Enterprise Agreement">Enterprise Agreement</option>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="formLicenseKey">
                            <span>License Key</span>
                            <a href="javascript:void(0)" onclick="generateSampleKey()" style="font-size: 11px; color: var(--cyan-primary); text-decoration: none; font-weight: 600;">+ Auto-Generate</a>
                        </label>
                        <input type="text" id="formLicenseKey" placeholder="XXXX-XXXX-XXXX-XXXX" style="font-family: monospace;">
                    </div>
                </div>

                <!-- Row 4: Vendor & Status -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="formVendor">Vendor / Supplier</label>
                        <select id="formVendor">
                            <option value="">-- Select Vendor --</option>
                            <?php foreach ($vendorsList as $vnd): ?>
                                <option value="<?php echo htmlspecialchars($vnd); ?>"><?php echo htmlspecialchars($vnd); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="formStatus">Status</label>
                        <select id="formStatus">
                            <option value="Active">Active</option>
                            <option value="Expiring Soon">Expiring Soon</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>
                </div>

                <!-- Row 5: Purchase Date & Expiry Date -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="formPurchaseDate">Purchase Date</label>
                        <input type="date" id="formPurchaseDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="modal-form-group">
                        <label for="formExpiryDate">Expiry / Renewal Date</label>
                        <input type="date" id="formExpiryDate">
                        <small style="font-size: 11px; color: #64748b; margin-top: 2px;">Leave empty if lifetime / perpetual.</small>
                    </div>
                </div>

                <!-- Row 6: Total Cost -->
                <div class="modal-form-group">
                    <label for="formCost">Total Cost (₹ / INR)</label>
                    <input type="number" step="0.01" id="formCost" placeholder="e.g. 5000" value="0">
                </div>

                <!-- Row 7: Notes -->
                <div class="modal-form-group" style="margin-bottom: 0;">
                    <label for="formNotes">Notes &amp; Remarks</label>
                    <textarea id="formNotes" rows="2" placeholder="e.g. Assigned to team. Single-tenant installation."></textarea>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="padding: 14px 24px;">
                <button type="button" class="btn-secondary" onclick="closeAddLicenseModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="saveLicenseBtn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Save License
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: RENEW SOFTWARE LICENSE (ENTERPRISE SUBSCRIPTION RENEWAL)
     ========================================================================= -->
<div class="modal-overlay" id="renewLicenseModalOverlay" style="display: none;">
    <div class="modal-box" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="modalRenewTitle" style="color: #0369a1;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: #0284c7;">
                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
                <span>Renew Software License</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeRenewLicenseModal()" title="Close">&times;</button>
        </div>

        <form id="renewLicenseForm" onsubmit="saveRenewLicenseForm(event)">
            <input type="hidden" id="renewLicenseId" value="">
            <div class="modal-body" style="padding: 20px 24px;">

                <!-- Software Selection Dropdown (if opening from header or changing) -->
                <div class="modal-form-group" id="renewSelectGroup">
                    <label for="renewSelectLicense">Select Software License to Renew <span class="req">*</span></label>
                    <select id="renewSelectLicense" onchange="onRenewLicenseSelectChange(this.value)">
                        <?php foreach ($initialLicenses as $licOpt): ?>
                            <option value="<?php echo $licOpt['id']; ?>">
                                <?php echo htmlspecialchars($licOpt['name']); ?> (<?php echo htmlspecialchars($licOpt['publisher']); ?>) — Exp: <?php echo htmlspecialchars($licOpt['expiry_date']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Selected Software License Summary Card -->
                <div class="renew-summary-card">
                    <div class="renew-summary-left">
                        <div class="software-logo-badge brand-default" id="renewLogoBadge" style="width: 44px; height: 44px; font-size: 15px;">
                            SW
                        </div>
                        <div>
                            <div class="renew-summary-title" id="renewSummaryName">—</div>
                            <div class="renew-summary-sub" id="renewSummaryPublisher">—</div>
                        </div>
                    </div>
                    <div class="renew-summary-stats">
                        <div class="renew-stat-badge">
                            <span class="stat-label">Current Expiry</span>
                            <span class="stat-value" id="renewSummaryCurrentExpiry" style="color: #ea580c;">—</span>
                        </div>
                        <div class="renew-stat-badge">
                            <span class="stat-label">Annual Spend</span>
                            <span class="stat-value" id="renewSummaryCurrentCost">—</span>
                        </div>
                    </div>
                </div>

                <!-- Row 1: New Expiry Date & Renewal Cost -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="renewNewExpiryDate">New Expiry / Renewal Date <span class="req">*</span></label>
                        <input type="date" id="renewNewExpiryDate" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="renewCost">Renewal Cost / Spend (₹ / INR) <span class="req">*</span></label>
                        <input type="number" step="0.01" id="renewCost" placeholder="0" required>
                    </div>
                </div>

                <!-- Row 2: Renewal PO / Invoice & Vendor -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="renewPoNumber">
                            <span>Renewal PO / Invoice No.</span>
                            <a href="javascript:void(0)" onclick="generateRenewPo()" style="font-size: 11px; color: #0284c7; text-decoration: none; font-weight: 600;">+ Auto-Generate</a>
                        </label>
                        <input type="text" id="renewPoNumber" placeholder="PO Number">
                    </div>
                    <div class="modal-form-group">
                        <label for="renewVendor">Vendor / Reseller</label>
                        <select id="renewVendor">
                            <option value="">-- Select Vendor --</option>
                            <?php foreach ($vendorsList as $vnd): ?>
                                <option value="<?php echo htmlspecialchars($vnd); ?>"><?php echo htmlspecialchars($vnd); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 3: Product Key Update & Status -->
                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="renewLicenseKey">
                            <span>Updated Product Key (Optional)</span>
                            <a href="javascript:void(0)" onclick="generateSampleRenewKey()" style="font-size: 11px; color: #0284c7; text-decoration: none; font-weight: 600;">+ New Key</a>
                        </label>
                        <input type="text" id="renewLicenseKey" placeholder="Leave empty to retain existing key" style="font-family: monospace;">
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 5px;">
                            <span>Current (Old) Key: </span>
                            <code id="renewCurrentKeyDisplay" style="font-family: monospace; font-weight: 700; color: #0f172a; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">—</code>
                        </div>
                    </div>
                    <div class="modal-form-group">
                        <label for="renewStatus">Post-Renewal Status</label>
                        <select id="renewStatus">
                            <option value="Active" selected>Active</option>
                            <option value="Expiring Soon">Expiring Soon</option>
                        </select>
                    </div>
                </div>

                <!-- Row 4: Renewal Remarks -->
                <div class="modal-form-group" style="margin-bottom: 0;">
                    <label for="renewNotes">Renewal Notes &amp; Approvals</label>
                    <textarea id="renewNotes" rows="2" placeholder="Renewal notes or justification"></textarea>
                </div>

                <!-- Impact Notice -->
                <div class="renew-notice-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color: #16a34a; flex-shrink: 0;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span><strong>Compliance Notice:</strong> Confirming renewal will extend license validity, archive the previous key into audit logs, and reset status to <strong>Active</strong>.</span>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="padding: 14px 24px;">
                <button type="button" class="btn-secondary" onclick="closeRenewLicenseModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="saveRenewBtn" style="background: linear-gradient(135deg, #0284c7, #0369a1); border-color: #0284c7;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                    </svg>
                    Confirm &amp; Renew License
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: ALL SOFTWARE LICENSE RENEWAL AUDIT LOGS
     ========================================================================= -->
<div class="modal-overlay" id="allRenewalLogsModalOverlay" style="display: none;">
    <div class="modal-box" style="max-width: 980px; width: 96%;">
        <div class="modal-header">
            <h3 style="color: #0369a1;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: #0284c7;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <span>Software License Renewal Audit Logs (Key History &amp; Extensions)</span>
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeAllRenewalLogsModal()" title="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 18px 22px; max-height: 72vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 12px; flex-wrap: wrap;">
                <input type="text" id="renewalLogSearchInput" placeholder="Filter logs by software, product key, renewed by, or notes..." 
                       style="flex: 1; min-width: 250px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;"
                       oninput="filterRenewalLogsTable()">
                <button type="button" class="btn-secondary" onclick="loadAllRenewalLogs(true)" style="font-size: 12px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Refresh Logs
                </button>
            </div>
            <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <table class="lic-table" id="allRenewalLogsTable" style="width: 100%; font-size: 12px; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 700; text-align: left;">
                            <th style="padding: 10px 10px;">ID</th>
                            <th style="padding: 10px 10px;">Software</th>
                            <th style="padding: 10px 10px;">Renewal Date</th>
                            <th style="padding: 10px 10px;">Expiry Timeline</th>
                            <th style="padding: 10px 10px;">Old Key (Previous)</th>
                            <th style="padding: 10px 10px;">Active Key (New)</th>
                            <th style="padding: 10px 10px; text-align: right;">Cost (₹)</th>
                            <th style="padding: 10px 10px;">Renewed By</th>
                            <th style="padding: 10px 10px;">Notes</th>
                        </tr>
                    </thead>
                    <tbody id="allRenewalLogsTableBody">
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 24px; color: #64748b;">Loading renewal audit trail...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="padding: 12px 22px;">
            <button type="button" class="btn-secondary" onclick="closeAllRenewalLogsModal()">Close</button>
        </div>
    </div>
</div>


<!-- Embed Dynamic Initial JSON Data for Client Interactions -->
<script>
    window.INITIAL_LICENSES_DATA = <?php echo json_encode($initialLicenses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php include 'includes/footer.php'; ?>
