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

// Comprehensive Enterprise Software & Licenses Dataset (UI Driven)
$initialLicenses = [
    [
        'id'            => 1,
        'name'          => 'Microsoft 365 E5',
        'publisher'     => 'Microsoft Corporation',
        'brand_code'    => 'msft',
        'category'      => 'Office & Productivity',
        'version'       => 'Cloud Enterprise',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'MS365-E5-9924-XXXX-7140',
        'total_seats'   => 120,
        'assigned_seats'=> 108,
        'vendor'        => 'Microsoft Direct',
        'po_number'     => 'PO-2026-MSFT-081',
        'purchase_date' => '2025-04-01',
        'expiry_date'   => '2027-03-31',
        'cost_per_seat' => 38,
        'total_cost'    => 54720,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Includes Teams, Exchange Online, Defender for Endpoint, and advanced compliance eDiscovery.',
        'allocations'   => [
            ['emp_id' => 1, 'name' => 'Rahul Verma', 'emp_code' => 'EMP-1041', 'dept' => 'IT & Infrastructure', 'device' => 'LAP-001 (ThinkPad T14)', 'assigned_date' => '2025-04-05'],
            ['emp_id' => 2, 'name' => 'Sneha Patel', 'emp_code' => 'EMP-1052', 'dept' => 'Finance & Accounts', 'device' => 'LAP-004 (Dell Latitude)', 'assigned_date' => '2025-04-10'],
            ['emp_id' => 3, 'name' => 'Amit Sharma', 'emp_code' => 'EMP-1011', 'dept' => 'Software Engineering', 'device' => 'LAP-009 (MacBook Pro)', 'assigned_date' => '2025-04-12']
        ]
    ],
    [
        'id'            => 2,
        'name'          => 'Adobe Creative Cloud All Apps',
        'publisher'     => 'Adobe Systems',
        'brand_code'    => 'adobe',
        'category'      => 'Design & Media',
        'version'       => 'CC 2026 Pro',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'ADBE-CCPRO-2026-XXXX-9142',
        'total_seats'   => 25,
        'assigned_seats'=> 23,
        'vendor'        => 'Adobe Systems India',
        'po_number'     => 'PO-2025-ADBE-114',
        'purchase_date' => '2025-10-28',
        'expiry_date'   => '2026-10-28', // 19 days from now!
        'cost_per_seat' => 84,
        'total_cost'    => 25200,
        'currency'      => '₹',
        'status'        => 'Expiring Soon',
        'notes'         => 'Includes Photoshop, Illustrator, Premiere Pro, After Effects, and Figma plugin integration.',
        'allocations'   => [
            ['emp_id' => 4, 'name' => 'Pooja Hegde', 'emp_code' => 'EMP-1088', 'dept' => 'Marketing & Design', 'device' => 'LAP-018 (MacBook Pro 16)', 'assigned_date' => '2025-11-01'],
            ['emp_id' => 5, 'name' => 'Kunal Joshi', 'emp_code' => 'EMP-1092', 'dept' => 'Marketing & Design', 'device' => 'DSK-003 (iMac 24)', 'assigned_date' => '2025-11-05']
        ]
    ],
    [
        'id'            => 3,
        'name'          => 'Windows 11 Pro Enterprise',
        'publisher'     => 'Microsoft Corporation',
        'brand_code'    => 'msft',
        'category'      => 'Operating Systems',
        'version'       => '23H2 / 24H2 OEM',
        'license_type'  => 'OEM / Perpetual',
        'license_key'   => 'W11ENT-VK7JG-XXXX-T83GX',
        'total_seats'   => 150,
        'assigned_seats'=> 142,
        'vendor'        => 'Redington India Ltd',
        'po_number'     => 'PO-2024-OEM-042',
        'purchase_date' => '2024-06-15',
        'expiry_date'   => 'Perpetual',
        'cost_per_seat' => 185,
        'total_cost'    => 27750,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Corporate volume license pack with BitLocker drive encryption and Windows Autopilot enrollment.',
        'allocations'   => [
            ['emp_id' => 1, 'name' => 'Rahul Verma', 'emp_code' => 'EMP-1041', 'dept' => 'IT & Infrastructure', 'device' => 'LAP-001 (ThinkPad T14)', 'assigned_date' => '2024-06-20'],
            ['emp_id' => 6, 'name' => 'Vikram Seth', 'emp_code' => 'EMP-1033', 'dept' => 'Operations & Logistics', 'device' => 'LAP-007 (HP EliteBook)', 'assigned_date' => '2024-07-02']
        ]
    ],
    [
        'id'            => 4,
        'name'          => 'JetBrains All Products Pack',
        'publisher'     => 'JetBrains s.r.o.',
        'brand_code'    => 'jb',
        'category'      => 'Developer Tools',
        'version'       => '2026 Ultimate',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'JB-ALL-2026-XXXX-5519',
        'total_seats'   => 15,
        'assigned_seats'=> 14,
        'vendor'        => 'JetBrains s.r.o.',
        'po_number'     => 'PO-2025-JB-090',
        'purchase_date' => '2025-12-15',
        'expiry_date'   => '2026-12-15',
        'cost_per_seat' => 499,
        'total_cost'    => 7485,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Full IDE suite: IntelliJ IDEA, PyCharm, WebStorm, DataGrip, and CLion for senior engineering team.',
        'allocations'   => [
            ['emp_id' => 3, 'name' => 'Amit Sharma', 'emp_code' => 'EMP-1011', 'dept' => 'Software Engineering', 'device' => 'LAP-009 (MacBook Pro)', 'assigned_date' => '2025-12-18']
        ]
    ],
    [
        'id'            => 5,
        'name'          => 'Slack Enterprise Grid',
        'publisher'     => 'Slack / Salesforce',
        'brand_code'    => 'slack',
        'category'      => 'Cloud & SaaS',
        'version'       => 'Enterprise 2026',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'SLACK-ENT-GRID-XXXX-4418',
        'total_seats'   => 200,
        'assigned_seats'=> 178,
        'vendor'        => 'Salesforce Direct',
        'po_number'     => 'PO-2026-SLACK-002',
        'purchase_date' => '2026-01-30',
        'expiry_date'   => '2027-01-30',
        'cost_per_seat' => 15,
        'total_cost'    => 36000,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Corporate communication grid with HIPAA compliance, data loss prevention, and custom automated bots.',
        'allocations'   => []
    ],
    [
        'id'            => 6,
        'name'          => 'GitHub Enterprise Cloud',
        'publisher'     => 'GitHub Inc.',
        'brand_code'    => 'github',
        'category'      => 'Developer Tools',
        'version'       => 'Enterprise Cloud',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'GH-ENT-ORG-XXXX-1984',
        'total_seats'   => 40,
        'assigned_seats'=> 38,
        'vendor'        => 'GitHub Direct',
        'po_number'     => 'PO-2025-GH-077',
        'purchase_date' => '2025-11-20',
        'expiry_date'   => '2026-11-20',
        'cost_per_seat' => 230,
        'total_cost'    => 9200,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Source code repository with GitHub Actions minutes, Advanced Security (code scanning), and Copilot seats.',
        'allocations'   => []
    ],
    [
        'id'            => 7,
        'name'          => 'CrowdStrike Falcon Complete',
        'publisher'     => 'CrowdStrike Inc.',
        'brand_code'    => 'crowdstrike',
        'category'      => 'Security & Antivirus',
        'version'       => 'Falcon EDR Pro',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'CS-FALCON-EDR-XXXX-8821',
        'total_seats'   => 180,
        'assigned_seats'=> 168,
        'vendor'        => 'Ingram Micro',
        'po_number'     => 'PO-2025-CS-062',
        'purchase_date' => '2025-10-31',
        'expiry_date'   => '2026-10-31', // 22 days from now!
        'cost_per_seat' => 95,
        'total_cost'    => 17100,
        'currency'      => '₹',
        'status'        => 'Expiring Soon',
        'notes'         => 'Next-gen Endpoint Detection and Response (EDR) with 24/7 Managed Threat Hunting for all laptops.',
        'allocations'   => []
    ],
    [
        'id'            => 8,
        'name'          => 'Autodesk AutoCAD 2026',
        'publisher'     => 'Autodesk Inc.',
        'brand_code'    => 'adobe',
        'category'      => 'Design & Media',
        'version'       => 'Commercial 2026',
        'license_type'  => 'Per-Seat / Volume',
        'license_key'   => 'ACAD-2026-XXXX-6610',
        'total_seats'   => 10,
        'assigned_seats'=> 10,
        'vendor'        => 'Tech Data Corporation',
        'po_number'     => 'PO-2026-ACAD-011',
        'purchase_date' => '2026-02-14',
        'expiry_date'   => '2027-02-14',
        'cost_per_seat' => 1950,
        'total_cost'    => 19500,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => '100% seat utilization. Dedicated 2D/3D design workstations in engineering department.',
        'allocations'   => []
    ],
    [
        'id'            => 9,
        'name'          => 'Jira Software Data Center',
        'publisher'     => 'Atlassian Pty Ltd',
        'brand_code'    => 'atlassian',
        'category'      => 'Cloud & SaaS',
        'version'       => 'Data Center 9.x',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'ATLS-JIRA-DC-XXXX-3381',
        'total_seats'   => 60,
        'assigned_seats'=> 56,
        'vendor'        => 'Atlassian Pty',
        'po_number'     => 'PO-2025-ATLS-088',
        'purchase_date' => '2025-11-05',
        'expiry_date'   => '2026-11-05', // 27 days from now!
        'cost_per_seat' => 110,
        'total_cost'    => 6600,
        'currency'      => '₹',
        'status'        => 'Expiring Soon',
        'notes'         => 'Agile project tracking, scrum boards, release management, and sprint planning for developer team.',
        'allocations'   => []
    ],
    [
        'id'            => 10,
        'name'          => 'VMware vSphere 8 Standard',
        'publisher'     => 'VMware / Broadcom',
        'brand_code'    => 'default',
        'category'      => 'Operating Systems',
        'version'       => 'vSphere 8.0',
        'license_type'  => 'Perpetual',
        'license_key'   => 'VMW-VSPHERE-XXXX-1120',
        'total_seats'   => 8,
        'assigned_seats'=> 6,
        'vendor'        => 'Redington India Ltd',
        'po_number'     => 'PO-2024-VMW-015',
        'purchase_date' => '2024-08-10',
        'expiry_date'   => 'Perpetual',
        'cost_per_seat' => 1395,
        'total_cost'    => 11160,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Server virtualization hypervisor cluster license for on-premise datacenter infrastructure.',
        'allocations'   => []
    ],
    [
        'id'            => 11,
        'name'          => 'Zoom Workplace Enterprise',
        'publisher'     => 'Zoom Video Comms',
        'brand_code'    => 'msft',
        'category'      => 'Cloud & SaaS',
        'version'       => 'Enterprise Cloud',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'ZM-ENT-WP-XXXX-9952',
        'total_seats'   => 50,
        'assigned_seats'=> 44,
        'vendor'        => 'Zoom Direct',
        'po_number'     => 'PO-2026-ZOOM-020',
        'purchase_date' => '2026-04-10',
        'expiry_date'   => '2027-04-10',
        'cost_per_seat' => 240,
        'total_cost'    => 12000,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Large meeting capacity up to 500 attendees, cloud recording, webinar capabilities, and AI Companion.',
        'allocations'   => []
    ],
    [
        'id'            => 12,
        'name'          => 'Figma Organization',
        'publisher'     => 'Figma Inc.',
        'brand_code'    => 'adobe',
        'category'      => 'Design & Media',
        'version'       => 'Org Cloud 2026',
        'license_type'  => 'SaaS Subscription',
        'license_key'   => 'FIG-ORG-2026-XXXX-7741',
        'total_seats'   => 20,
        'assigned_seats'=> 18,
        'vendor'        => 'Figma Direct',
        'po_number'     => 'PO-2026-FIG-005',
        'purchase_date' => '2026-05-15',
        'expiry_date'   => '2027-05-15',
        'cost_per_seat' => 540,
        'total_cost'    => 10800,
        'currency'      => '₹',
        'status'        => 'Active',
        'notes'         => 'Design systems, shared component libraries, dev mode inspection, and FigJam collaborative whiteboards.',
        'allocations'   => []
    ]
];

// Calculate Live KPI Metrics
$totalLicenses = count($initialLicenses);
$activeLicensesCount = 0;
$expiringCount = 0;
$totalSpendUSD = 0;

foreach ($initialLicenses as $lic) {
    $totalSpendUSD += floatval($lic['total_cost']);
    if ($lic['status'] === 'Active') {
        $activeLicensesCount++;
    } elseif ($lic['status'] === 'Expiring Soon') {
        $expiringCount++;
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
                        <th style="width: 110px; text-align: right; padding-right: 14px;">Actions</th>
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
                <div class="software-logo-badge brand-msft" id="drawerLogoBadge" style="width: 44px; height: 44px; font-size: 16px;">
                    MS
                </div>
                <div>
                    <h2 style="font-size: 17px; font-weight: 800; color: #001938; margin: 0;" id="drawerSoftwareTitle">Microsoft 365 E5</h2>
                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;" id="drawerSoftwarePublisher">Microsoft Corporation &bull; Cloud Enterprise</div>
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
                    <div class="box-val" id="drawerStatModel" style="font-size: 13.5px;">SaaS Subscription</div>
                </div>
                <div class="drawer-stat-box">
                    <div class="box-lbl">License Status</div>
                    <div class="box-val" id="drawerStatStatus" style="font-size: 13.5px; color: #059669;">Active</div>
                </div>
                <div class="drawer-stat-box">
                    <div class="box-lbl">Total Spend</div>
                    <div class="box-val" id="drawerStatCost" style="font-size: 14px; color: #001938; font-weight: 800;">₹54,720</div>
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
                    <div class="info-val" id="drawerLicType">SaaS Subscription</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Category</div>
                    <div class="info-val" id="drawerLicCategory">Office &amp; Productivity</div>
                </div>
                <div class="drawer-info-item" style="grid-column: span 2;">
                    <div class="info-lbl">Product License Key</div>
                    <div class="info-val" style="font-family: monospace; background: #f8fafc; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 8px;">
                        <span id="drawerLicenseKey">MS365-E5-9924-XXXX-7140</span>
                        <button type="button" class="btn-copy-inline" onclick="copyDrawerKey()" title="Copy key">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Purchase Order / Invoice</div>
                    <div class="info-val" id="drawerPoNumber">PO-2026-MSFT-081</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Supplier / Vendor</div>
                    <div class="info-val" id="drawerVendor">Microsoft Direct</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Purchase Date</div>
                    <div class="info-val" id="drawerPurchaseDate">2025-04-01</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Expiry / Renewal Date</div>
                    <div class="info-val" id="drawerExpiryDate">2027-03-31</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Unit Cost</div>
                    <div class="info-val" id="drawerCostPerSeat">₹3,200 / seat</div>
                </div>
                <div class="drawer-info-item">
                    <div class="info-lbl">Total Annual Spend</div>
                    <div class="info-val" id="drawerTotalCost" style="color: #001938; font-weight: 800;">₹54,720.00</div>
                </div>
            </div>

            <!-- Notes & Compliance -->
            <div class="drawer-sec-title">
                <span>Notes &amp; Compliance Verification</span>
            </div>
            <p style="font-size: 12.5px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin: 0;" id="drawerNotes">
                License audited under corporate IT security guidelines. Compliant with single-tenant data isolation.
            </p>
        </div>

        <!-- Drawer Footer -->
        <div class="lic-drawer-footer">
            <button type="button" class="btn-secondary" onclick="closeLicenseDrawer()">Close</button>
            <button type="button" class="btn-primary" id="drawerEditLicBtn" onclick="editCurrentDrawerLicense()">
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


<!-- Embed Dynamic Initial JSON Data for Client Interactions -->
<script>
    window.INITIAL_LICENSES_DATA = <?php echo json_encode($initialLicenses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php include 'includes/footer.php'; ?>
