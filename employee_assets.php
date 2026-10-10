<?php
// Asset Management & IT Service Desk Portal - Dedicated Employee My Assets Page (UI)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title  = "My IT Assets & Custody - VIROS IT Portal";
$active_page = "employee_assets";
$extra_css   = ['css/employee_dashboard.css', 'css/employee_assets.css'];
$extra_js    = ['js/employee_dashboard.js', 'js/employee_assets.js'];

// Include Database Configuration
require_once __DIR__ . '/config/db.php';

// Default Active Employee Details
$activeEmployee = [
    'name'        => $_SESSION['user_name'] ?? 'Abhishek Ranjan',
    'code'        => $_SESSION['user_username'] ?? 'VE015',
    'designation' => 'Senior Software Engineer',
    'department'  => $_SESSION['user_dept'] ?? 'Information Technology',
    'email'       => $_SESSION['user_email'] ?? 'abhishekkumarranjan965@gmail.com',
    'phone'       => '+91 98765 43210',
    'location'    => 'Bangalore HQ (Floor 3)',
    'joining_date'=> '15 Jan 2024',
    'status'      => 'Active',
    'custody'     => 'Verified & Active'
];

// Fetch live employee data if connected
if (isset($conn) && $conn !== false && !empty($_SESSION['user_id'])) {
    $currStmt = sqlsrv_query(
        $conn, 
        "SELECT e.*, d.department_name, l.location_name 
         FROM employees e 
         LEFT JOIN departments d ON e.department_id = d.id 
         LEFT JOIN locations l ON e.location_id = l.id 
         WHERE e.id = ? OR LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)", 
        [$_SESSION['user_id'], $_SESSION['user_email'] ?? '', $_SESSION['user_username'] ?? '']
    );
    if ($currStmt && ($currRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC))) {
        $fullName = trim(($currRow['first_name'] ?? '') . ' ' . ($currRow['last_name'] ?? ''));
        $joinDateFormatted = '15 Jan 2024';
        if (!empty($currRow['joining_date'])) {
            $joinDateFormatted = is_object($currRow['joining_date']) 
                ? $currRow['joining_date']->format('d M Y') 
                : date('d M Y', strtotime($currRow['joining_date']));
        }

        $activeEmployee = [
            'id'           => $currRow['id'],
            'name'         => $fullName ?: ($_SESSION['user_name'] ?? 'Employee'),
            'code'         => $currRow['emp_code'] ?? 'EMP-000',
            'designation'  => $currRow['designation'] ?: 'Staff Member',
            'department'   => $currRow['department_name'] ?: ($_SESSION['user_dept'] ?? 'General'),
            'email'        => $currRow['email'] ?? $_SESSION['user_email'],
            'phone'        => $currRow['phone'] ?: '+91 98765 43210',
            'location'     => $currRow['location_name'] ?: 'Bangalore HQ (Floor 3)',
            'joining_date' => $joinDateFormatted,
            'status'       => $currRow['status'] ?? 'Active',
            'custody'      => ($currRow['status'] === 'Active') ? 'Verified & Active' : 'Inactive'
        ];
        sqlsrv_free_stmt($currStmt);
    }
}

// Compute initials for Avatar
$avatarInitials = '';
foreach (explode(' ', trim($activeEmployee['name'])) as $w) {
    if (!empty($w)) $avatarInitials .= strtoupper($w[0]);
    if (strlen($avatarInitials) >= 2) break;
}
if (empty($avatarInitials)) $avatarInitials = 'EM';

// Employee Allocated Inventory Items (Rich UI Dataset)
$allocatedItems = [
    [
        'tag'       => 'AST2026001',
        'name'      => 'Dell Latitude 5420 Laptop',
        'specs'     => 'Intel Core i7-1185G7 • 16GB RAM • 512GB NVMe SSD • Windows 11 Pro',
        'category'  => 'Computing / Laptop',
        'type'      => 'hardware',
        'serial'    => 'C02G40PZMD6T',
        'date'      => '15 Jan 2025',
        'condition' => 'Excellent',
        'status'    => 'In Active Custody',
        'badge'     => 'badge-resolved',
        'icon_type' => 'laptop'
    ],
    [
        'tag'       => 'AST2026048',
        'name'      => 'Dell UltraSharp 24" USB-C Hub Monitor',
        'specs'     => '1920x1080 IPS • USB-C 90W Power Delivery • Height Adjustable Stand',
        'category'  => 'External Display',
        'type'      => 'hardware',
        'serial'    => 'CN-04D792-742',
        'date'      => '15 Jan 2025',
        'condition' => 'Excellent',
        'status'    => 'In Active Custody',
        'badge'     => 'badge-resolved',
        'icon_type' => 'monitor'
    ],
    [
        'tag'       => 'ACC-1002',
        'name'      => 'Dell WD19S USB-C Docking Station',
        'specs'     => '130W Power Delivery • Dual DisplayPort 1.4 • HDMI 2.0b • Gigabit LAN',
        'category'  => 'Docking Station',
        'type'      => 'accessory',
        'serial'    => 'WD19-98124-IN',
        'date'      => '16 Jan 2025',
        'condition' => 'Good',
        'status'    => 'Issued & In Use',
        'badge'     => 'badge-progress',
        'icon_type' => 'dock'
    ],
    [
        'tag'       => 'ACC-1009',
        'name'      => 'Logitech MX Keys Advanced Wireless Keyboard',
        'specs'     => 'Bluetooth & Logi Bolt Receiver • Smart Backlit Keys • Rechargeable USB-C',
        'category'  => 'Input Peripheral',
        'type'      => 'accessory',
        'serial'    => 'SN-829104-MX',
        'date'      => '16 Jan 2025',
        'condition' => 'Good',
        'status'    => 'Issued & In Use',
        'badge'     => 'badge-progress',
        'icon_type' => 'keyboard'
    ],
    [
        'tag'       => 'ACC-1014',
        'name'      => 'Logitech MX Master 3S Wireless Mouse',
        'specs'     => '8,000 DPI Darkfield Sensor • Quiet Clicks • MagSpeed Electromagnetic Scroll',
        'category'  => 'Input Peripheral',
        'type'      => 'accessory',
        'serial'    => 'SN-772109-MS',
        'date'      => '16 Jan 2025',
        'condition' => 'Good',
        'status'    => 'Issued & In Use',
        'badge'     => 'badge-progress',
        'icon_type' => 'mouse'
    ],
    [
        'tag'       => 'LIC-2001',
        'name'      => 'Microsoft 365 E5 Enterprise Cloud License',
        'specs'     => 'Office Apps Desktop & Web • Exchange Online • Microsoft Teams • Defender Suite',
        'category'  => 'Productivity Suite',
        'type'      => 'software',
        'serial'    => 'SSO Provisioned (' . htmlspecialchars($activeEmployee['email']) . ')',
        'date'      => '10 Jan 2025',
        'condition' => 'Valid',
        'status'    => 'Active Subscription',
        'badge'     => 'badge-resolved',
        'icon_type' => 'software'
    ],
    [
        'tag'       => 'LIC-2015',
        'name'      => 'JetBrains All Products Pack Corporate',
        'specs'     => 'IntelliJ IDEA Ultimate • PhpStorm • WebStorm • PyCharm • DataGrip',
        'category'  => 'Developer Tooling',
        'type'      => 'software',
        'serial'    => 'JB-LICENSE-KEY-9941',
        'date'      => '12 Jan 2025',
        'condition' => 'Valid',
        'status'    => 'Active Subscription',
        'badge'     => 'badge-resolved',
        'icon_type' => 'software'
    ]
];

// Calculate item counts
$totalAssetCount = count($allocatedItems);
$hardwareCount   = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'hardware'));
$accessoryCount  = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'accessory'));
$softwareCount   = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'software'));

// Layout Components
include 'includes/header.php';
include 'includes/employee_sidebar.php';
include 'includes/employee_topbar.php';
?>

<!-- My Assets Dedicated Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb -->
    <nav class="assets-breadcrumb">
        <a href="employee_dashboard.php">Home</a>
        <span>/</span>
        <a href="employee_dashboard.php">My Dashboard</a>
        <span>/</span>
        <span class="current">My Allocated Assets</span>
    </nav>

    <!-- Hero Asset Banner Card -->
    <div class="assets-hero-card">
        <div class="assets-hero-left">
            <div class="assets-hero-icon-box">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <div class="assets-hero-info">
                <h1>My Allocated IT Assets</h1>
                <p>
                    Official computing hardware, workstation peripherals, and corporate software licenses registered in the custody of 
                    <strong><?php echo htmlspecialchars($activeEmployee['name']); ?></strong> (<?php echo htmlspecialchars($activeEmployee['code']); ?>).
                </p>
                <div class="assets-tags-wrap">
                    <span class="asset-hero-tag">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="22.01"></line><line x1="15" y1="22" x2="15" y2="22.01"></line></svg>
                        <?php echo htmlspecialchars($activeEmployee['department']); ?>
                    </span>
                    <span class="asset-hero-tag">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?php echo htmlspecialchars($activeEmployee['location']); ?>
                    </span>
                    <span class="asset-hero-tag status-active">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                        Custody Verified & Compliant
                    </span>
                </div>
            </div>
        </div>

        <div class="assets-hero-actions">
            <button type="button" class="btn-hero-action" onclick="openEquipmentRequestModal()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Request Equipment
            </button>
            <a href="employee_profile.php" class="btn-hero-action secondary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                View Employee Profile
            </a>
        </div>
    </div>

    <!-- Metric Summary Stats Row (SVG Icons Only) -->
    <div class="assets-stats-grid">
        <div class="assets-stat-card">
            <div class="assets-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $totalAssetCount; ?> Total</div>
                <div class="lbl">Active Items in Custody</div>
            </div>
        </div>

        <div class="assets-stat-card">
            <div class="assets-stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                    <line x1="2" y1="20" x2="22" y2="20"></line>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $hardwareCount; ?> Devices</div>
                <div class="lbl">Computing Laptops & Displays</div>
            </div>
        </div>

        <div class="assets-stat-card">
            <div class="assets-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="6" y1="8" x2="6.01" y2="8"></line>
                    <line x1="10" y1="8" x2="10.01" y2="8"></line>
                    <line x1="14" y1="8" x2="14.01" y2="8"></line>
                    <line x1="18" y1="8" x2="18.01" y2="8"></line>
                    <line x1="7" y1="16" x2="17" y2="16"></line>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $accessoryCount; ?> Peripherals</div>
                <div class="lbl">Docks, Keyboards & Mouse</div>
            </div>
        </div>

        <div class="assets-stat-card">
            <div class="assets-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="7.5" cy="15.5" r="5.5"></circle>
                    <path d="m21 2-9.6 9.6"></path>
                    <path d="m15.5 7.5 3 3"></path>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $softwareCount; ?> Software</div>
                <div class="lbl">Corporate Cloud Licenses</div>
            </div>
        </div>
    </div>

    <!-- Main Assets Inventory Card -->
    <div class="assets-main-card">
        <div class="assets-card-header">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                Registered IT Inventory & Custody Receipts
            </h2>

            <button type="button" class="btn-primary" onclick="openEquipmentRequestModal()" style="padding: 8px 16px; font-size: 12.5px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Requisition New Asset
            </button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="assets-toolbar">
            <div class="assets-filter-pills">
                <button type="button" class="assets-pill-btn active" data-filter="all">
                    All Allocated Assets <span class="assets-pill-count"><?php echo $totalAssetCount; ?></span>
                </button>
                <button type="button" class="assets-pill-btn" data-filter="hardware">
                    Hardware Units <span class="assets-pill-count"><?php echo $hardwareCount; ?></span>
                </button>
                <button type="button" class="assets-pill-btn" data-filter="accessory">
                    Workstation Peripherals <span class="assets-pill-count"><?php echo $accessoryCount; ?></span>
                </button>
                <button type="button" class="assets-pill-btn" data-filter="software">
                    Software Licenses <span class="assets-pill-count"><?php echo $softwareCount; ?></span>
                </button>
            </div>

            <div class="assets-search-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" class="assets-search-input" id="assetSearchInput" placeholder="Search by Tag, Name, Serial or Model...">
            </div>
        </div>

        <!-- Responsive Table -->
        <div class="table-responsive">
            <table class="custom-table" id="empAssetsTable">
                <thead>
                    <tr>
                        <th style="width: 28%;">Item Name & Tag</th>
                        <th style="width: 25%;">Category & Specifications</th>
                        <th style="width: 17%;">Serial / Key</th>
                        <th style="width: 12%;">Assigned Date</th>
                        <th style="width: 10%;">Custody Status</th>
                        <th style="width: 8%; text-align: right; padding-right: 24px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocatedItems as $item): ?>
                        <tr class="emp-asset-row" data-type="<?php echo $item['type']; ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="asset-item-icon-box">
                                        <?php if ($item['icon_type'] === 'laptop'): ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>
                                        <?php elseif ($item['icon_type'] === 'monitor'): ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                        <?php elseif ($item['icon_type'] === 'dock'): ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v6m4-6v6M8 8h8a2 2 0 0 1 2 2v2a6 6 0 0 1-12 0v-2a2 2 0 0 1 2-2zm4 10v4"></path></svg>
                                        <?php elseif ($item['icon_type'] === 'keyboard'): ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="6" y1="8" x2="6.01" y2="8"></line><line x1="10" y1="8" x2="10.01" y2="8"></line><line x1="14" y1="8" x2="14.01" y2="8"></line><line x1="18" y1="8" x2="18.01" y2="8"></line><line x1="7" y1="16" x2="17" y2="16"></line></svg>
                                        <?php elseif ($item['icon_type'] === 'mouse'): ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="7"></rect><line x1="12" y1="6" x2="12" y2="10"></line></svg>
                                        <?php else: ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3"></path></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--navy-primary); font-size: 13.5px;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </div>
                                        <div style="margin-top: 3px;">
                                            <span class="asset-tag-pill"><?php echo htmlspecialchars($item['tag']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div style="font-size: 13px; font-weight: 600; color: var(--navy-primary);">
                                    <?php echo htmlspecialchars($item['category']); ?>
                                </div>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px; line-height: 1.35;">
                                    <?php echo htmlspecialchars($item['specs']); ?>
                                </div>
                            </td>

                            <td>
                                <span class="asset-serial-pill">
                                    <?php echo htmlspecialchars($item['serial']); ?>
                                </span>
                            </td>

                            <td>
                                <span style="font-size: 12.5px; font-weight: 500; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($item['date']); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo $item['badge']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($item['status']); ?>
                                </span>
                            </td>

                            <td style="text-align: right; padding-right: 24px;">
                                <button type="button" class="btn-action-slip" 
                                    onclick='openHandoverSlipModal(<?php echo json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    title="View & Print Official Handover Slip">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    Handover Slip
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Empty Search State -->
            <div id="assetsEmptyState" class="assets-empty-state" style="display: none;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <div style="font-size: 14px; font-weight: 700; color: var(--navy-primary);">No Matching Assets Found</div>
                <div style="font-size: 12px; margin-top: 4px;">Try searching with another asset tag, model name or serial number.</div>
            </div>
        </div>
    </div>

</main>

<!-- Official IT Handover Custody Slip Modal -->
<div class="modal-overlay" id="handoverSlipModal" style="display: none; z-index: 9999;">
    <div class="modal-box" style="max-width: 680px; border-top: 4px solid var(--navy-primary);">
        <div class="modal-header" style="padding: 18px 24px 14px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(0, 147, 167, 0.12); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--navy-primary);">
                        IT Asset Handover & Custody Document
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                        Official custody record issued by VIROS Enterprise IT Infrastructure
                    </p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('handoverSlipModal')">&times;</button>
        </div>

        <div class="modal-body" style="padding: 24px; max-height: 75vh; overflow-y: auto;">
            <!-- Printable Custody Document Sheet -->
            <div class="slip-doc-box" id="printableSlipSheet">
                <div class="slip-doc-header">
                    <div class="slip-doc-brand">
                        <h3>VIROS IT SERVICES • ASSET CUSTODY SLIP</h3>
                        <p>Corporate IT Infrastructure & Endpoint Asset Management</p>
                    </div>
                    <div class="slip-doc-ref">
                        <div>Document No:</div>
                        <strong id="slipAssetDocId">HS-2026001</strong>
                    </div>
                </div>

                <div class="slip-info-grid">
                    <div class="slip-info-item">
                        <div class="lbl">Custody Recipient</div>
                        <div class="val"><?php echo htmlspecialchars($activeEmployee['name']); ?></div>
                    </div>
                    <div class="slip-info-item">
                        <div class="lbl">Employee ID / Code</div>
                        <div class="val"><?php echo htmlspecialchars($activeEmployee['code']); ?></div>
                    </div>
                    <div class="slip-info-item">
                        <div class="lbl">Assigned Department</div>
                        <div class="val"><?php echo htmlspecialchars($activeEmployee['department']); ?></div>
                    </div>
                    <div class="slip-info-item">
                        <div class="lbl">Primary Location / Bay</div>
                        <div class="val"><?php echo htmlspecialchars($activeEmployee['location']); ?></div>
                    </div>
                </div>

                <div class="slip-item-specs-box">
                    <div style="font-size: 11px; font-weight: 700; color: var(--cyan-primary); text-transform: uppercase; margin-bottom: 8px;">
                        Allocated Hardware / Software Details
                    </div>
                    <div style="font-size: 15px; font-weight: 800; color: var(--navy-primary);" id="slipAssetName">
                        Dell Latitude 5420 Laptop
                    </div>
                    <div style="margin-top: 6px; font-size: 12px; color: var(--text-secondary); line-height: 1.4;" id="slipAssetSpecs">
                        Intel Core i7-1185G7 • 16GB RAM • 512GB SSD
                    </div>

                    <div style="display: flex; gap: 20px; margin-top: 12px; font-size: 12px; border-top: 1px solid #f1f5f9; padding-top: 10px;">
                        <div>
                            <span style="color: var(--text-muted);">Asset Tag:</span> 
                            <strong style="font-family: monospace; color: var(--navy-primary);" id="slipAssetTag">AST2026001</strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted);">Serial Number:</span> 
                            <strong style="font-family: monospace; color: var(--navy-primary);" id="slipAssetSerial">C02G40PZMD6T</strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted);">Condition:</span> 
                            <strong style="color: #059669;" id="slipAssetCondition">Excellent</strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted);">Issued Date:</span> 
                            <strong style="color: var(--navy-primary);" id="slipAssetDate">15 Jan 2025</strong>
                        </div>
                    </div>
                </div>

                <div class="slip-terms-box">
                    <strong>Custody Compliance Agreement:</strong> The equipment/license detailed above is provided exclusively for official corporate business purposes. The user is responsible for appropriate security, physical safeguarding, and policy adherence under the VIROS Information Security Standard. Any hardware malfunction or loss must be reported immediately to the IT Helpdesk.
                </div>

                <div class="slip-signature-row">
                    <div class="slip-sig-box">
                        <div class="slip-sig-line"></div>
                        <div class="slip-sig-text">IT Asset Custodian</div>
                    </div>
                    <div style="text-align: center; color: var(--text-muted); font-size: 10.5px;">
                        Digital Verification Seal<br>
                        <strong style="color: #059669;">[ VERIFIED & ACTIVE ]</strong>
                    </div>
                    <div class="slip-sig-box">
                        <div class="slip-sig-line"></div>
                        <div class="slip-sig-text">Employee Signature</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="font-size: 12px; color: var(--text-muted);">
                Valid document verification timestamp: <?php echo date('d M Y'); ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-secondary" onclick="closeModal('handoverSlipModal')">Close</button>
                <button type="button" class="btn-primary" onclick="printHandoverSlip()" style="padding: 9px 18px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print / Download Slip
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Request Equipment / Requisition Modal -->
<div class="modal-overlay" id="equipmentRequestModal" style="display: none; z-index: 9999;">
    <div class="modal-box" style="max-width: 520px; border-top: 4px solid var(--cyan-primary);">
        <div class="modal-header" style="padding: 18px 24px 14px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(0, 147, 167, 0.12); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--navy-primary);">
                        Request Equipment or Asset Requisition
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                        Submit hardware, accessory, or software license requisition to IT Ops.
                    </p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('equipmentRequestModal')">&times;</button>
        </div>

        <form id="equipmentRequestForm">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Asset Category *
                    </label>
                    <select id="reqCategory" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                        <option value="">Select Asset Category</option>
                        <option value="Laptop / Workstation">Laptop / Workstation Upgrade</option>
                        <option value="External Monitor">External Monitor / Display</option>
                        <option value="Docking Station">Universal Docking Station</option>
                        <option value="Keyboard & Mouse">Wireless Keyboard / Ergonomic Mouse</option>
                        <option value="Headset / Audio">Noise Cancelling Headset</option>
                        <option value="Software License">Software Tool License (JetBrains / M365 / Cloud)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Requirement / Preferred Specification *
                    </label>
                    <input type="text" id="reqItemName" required placeholder="e.g. Dual 4K USB-C Dock or 32GB RAM Laptop" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Business Justification / Reason *
                    </label>
                    <textarea id="reqReason" required rows="3" placeholder="Briefly describe project requirement or reason for upgrade..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px; box-sizing: border-box; outline: none; resize: vertical;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Priority / Urgency
                    </label>
                    <select id="reqPriority" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                        <option value="Medium">Standard - Within 3 to 5 business days</option>
                        <option value="High">Urgent - Client project dependency (1-2 days)</option>
                        <option value="Low">Low - Planned future requirement</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="btn-secondary" onclick="closeModal('equipmentRequestModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="padding: 9px 22px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Submit Request</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Layout Footer
include 'includes/footer.php';
?>
