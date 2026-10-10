<?php
// Asset Management & IT Service Desk Portal - Employee Self-Service Dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Direct access allowed without login for Employee Portal demo/preview
// (Session check bypassed for employee self-service view)

$page_title = "Employee IT Portal - Asset Management & IT Service Desk";
$active_page = "employee_dashboard";
$extra_css = ['css/employee_dashboard.css'];
$extra_js  = ['js/employee_dashboard.js'];

// Include Database
require_once __DIR__ . '/config/db.php';

// Fetch live employees for profile switching if available
$availableEmployees = [];
if (isset($conn) && $conn !== false) {
    $empStmt = sqlsrv_query($conn, "SELECT TOP 10 id, emp_code, first_name, last_name, email, designation, department_id FROM employees WHERE status = 'Active' ORDER BY id ASC");
    if ($empStmt !== false) {
        while ($er = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
            $fullName = trim(($er['first_name'] ?? '') . ' ' . ($er['last_name'] ?? ''));
            $availableEmployees[] = [
                'id' => $er['id'],
                'code' => $er['emp_code'] ?? ('EMP-' . $er['id']),
                'name' => !empty($fullName) ? $fullName : 'Employee ' . $er['id'],
                'email' => $er['email'] ?? '',
                'designation' => $er['designation'] ?? 'Staff Member'
            ];
        }
        sqlsrv_free_stmt($empStmt);
    }
}

// Active Employee Profile Details
$selectedEmpId = isset($_GET['emp']) ? intval($_GET['emp']) : 0;
$activeEmployee = [
    'name'        => $_SESSION['user_name'] ?? 'Abhishek Sharma',
    'code'        => 'EMP-1004',
    'designation' => 'Senior Software Engineer',
    'department'  => 'Information Technology',
    'email'       => 'abhishek.s@viros.in',
    'location'    => 'Bangalore HQ (Floor 3)',
    'custody'     => 'Verified & Active'
];

if (!empty($availableEmployees)) {
    if ($selectedEmpId > 0) {
        foreach ($availableEmployees as $ae) {
            if ($ae['id'] === $selectedEmpId) {
                $activeEmployee['name'] = $ae['name'];
                $activeEmployee['code'] = $ae['code'];
                $activeEmployee['designation'] = $ae['designation'];
                $activeEmployee['email'] = $ae['email'];
                break;
            }
        }
    } else {
        $ae = $availableEmployees[0];
        $activeEmployee['name'] = $ae['name'];
        $activeEmployee['code'] = $ae['code'];
        $activeEmployee['designation'] = $ae['designation'];
        $activeEmployee['email'] = $ae['email'];
    }
}

// Employee Allocated Inventory Items
$allocatedItems = [
    [
        'tag'       => 'AST2026001',
        'name'      => 'Dell Latitude 5420 Laptop',
        'specs'     => 'Intel Core i7-1185G7 • 16GB RAM • 512GB NVMe SSD',
        'category'  => 'Computing / Laptop',
        'type'      => 'hardware',
        'serial'    => 'C02G40PZMD6T',
        'date'      => '15 Jan 2025',
        'condition' => 'Good',
        'status'    => 'In Active Custody',
        'badge'     => 'badge-resolved'
    ],
    [
        'tag'       => 'AST2026048',
        'name'      => 'Dell UltraSharp 24" USB-C Hub Monitor',
        'specs'     => '1920x1080 IPS • USB-C 90W PD • Height Adjust',
        'category'  => 'External Display',
        'type'      => 'hardware',
        'serial'    => 'CN-04D792-742',
        'date'      => '15 Jan 2025',
        'condition' => 'Excellent',
        'status'    => 'In Active Custody',
        'badge'     => 'badge-resolved'
    ],
    [
        'tag'       => 'ACC-1002',
        'name'      => 'Dell WD19S USB-C Docking Station',
        'specs'     => '130W Power Delivery • Dual DisplayPort • HDMI',
        'category'  => 'Docking Station',
        'type'      => 'accessory',
        'serial'    => 'WD19-98124-IN',
        'date'      => '16 Jan 2025',
        'condition' => 'Good',
        'status'    => 'Issued & In Use',
        'badge'     => 'badge-progress'
    ],
    [
        'tag'       => 'ACC-1009',
        'name'      => 'Logitech MX Keys Advanced Wireless Keyboard',
        'specs'     => 'Bluetooth & Bolt Receiver • Smart Backlit Keys',
        'category'  => 'Input Peripheral',
        'type'      => 'accessory',
        'serial'    => 'SN-829104-MX',
        'date'      => '16 Jan 2025',
        'condition' => 'Good',
        'status'    => 'Issued & In Use',
        'badge'     => 'badge-progress'
    ],
    [
        'tag'       => 'ACC-1014',
        'name'      => 'Logitech MX Master 3S Wireless Mouse',
        'specs'     => '8K DPI Sensor • Quiet Clicks • MagSpeed Scroll',
        'category'  => 'Input Peripheral',
        'type'      => 'accessory',
        'serial'    => 'SN-772109-MS',
        'date'      => '16 Jan 2025',
        'condition' => 'Good',
        'status'    => 'Issued & In Use',
        'badge'     => 'badge-progress'
    ],
    [
        'tag'       => 'LIC-2001',
        'name'      => 'Microsoft 365 E5 Enterprise Cloud License',
        'specs'     => 'Office Apps • Exchange • Teams • SharePoint • Defender',
        'category'  => 'Productivity Suite',
        'type'      => 'software',
        'serial'    => 'SSO Provisioned (' . htmlspecialchars($activeEmployee['email']) . ')',
        'date'      => '10 Jan 2025',
        'condition' => 'Valid',
        'status'    => 'Active Subscription',
        'badge'     => 'badge-resolved'
    ],
    [
        'tag'       => 'LIC-2015',
        'name'      => 'Adobe Creative Cloud All Apps Plan',
        'specs'     => 'Photoshop • Illustrator • Premiere • Acrobat Pro',
        'category'  => 'Design & Media',
        'type'      => 'software',
        'serial'    => 'Named User SSO License',
        'date'      => '20 Feb 2025',
        'condition' => 'Valid',
        'status'    => 'Active Subscription',
        'badge'     => 'badge-resolved'
    ],
    [
        'tag'       => 'LIC-2023',
        'name'      => 'JetBrains All Products Developer Pack',
        'specs'     => 'IntelliJ IDEA • PhpStorm • WebStorm • DataGrip',
        'category'  => 'Developer Tool',
        'type'      => 'software',
        'serial'    => 'JB-ENT-48912-LIC',
        'date'      => '01 Mar 2025',
        'condition' => 'Expiring Soon (60d)',
        'status'    => 'Commercial Seat',
        'badge'     => 'badge-urgent'
    ],
    [
        'tag'       => 'LIC-2030',
        'name'      => 'Slack Enterprise Grid Seat',
        'specs'     => 'Unlimited Channels • Canvas • Huddles • 99.99% SLA',
        'category'  => 'Collaboration',
        'type'      => 'software',
        'serial'    => 'Enterprise SSO Account',
        'date'      => '10 Jan 2025',
        'condition' => 'Valid',
        'status'    => 'Active Subscription',
        'badge'     => 'badge-resolved'
    ]
];

// Calculate counts
$hardwareCount = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'hardware'));
$accessoryCount = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'accessory'));
$softwareCount = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'software'));
$totalItemsCount = count($allocatedItems);

// Recent Support Requests for this employee
$recentTickets = [
    [
        'id'        => 'TKT-1082',
        'subject'   => 'External monitor HDMI signal flickering after standby',
        'asset'     => 'Dell UltraSharp 24" (AST2026048)',
        'priority'  => 'Medium',
        'p_class'   => 'badge-medium',
        'status'    => 'In Progress',
        's_class'   => 'badge-progress',
        'date'      => '08 Oct 2026',
        'tech'      => 'Deepak Patel (IT Support Desk)'
    ],
    [
        'id'        => 'TKT-1045',
        'subject'   => 'Request for USB-C Multiport Display Adapter for meeting room',
        'asset'     => 'General Accessory Request',
        'priority'  => 'Low',
        'p_class'   => 'badge-open',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-resolved',
        'date'      => '12 Sep 2026',
        'tech'      => 'IT Procurement Team'
    ]
];

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/employee_sidebar.php';
include 'includes/employee_topbar.php';
?>

<!-- Employee Dashboard Body Content -->
<main class="dashboard-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                <span style="background: rgba(0, 147, 167, 0.35); border: 1px solid var(--cyan-primary); color: #e0f2fe; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.5px;">Employee Self-Service Portal</span>
            </div>
            <h1>Welcome, <?php echo htmlspecialchars($activeEmployee['name']); ?>!</h1>
            <p>
                Emp Code: <strong><?php echo htmlspecialchars($activeEmployee['code']); ?></strong> • 
                <?php echo htmlspecialchars($activeEmployee['designation']); ?> • 
                <?php echo htmlspecialchars($activeEmployee['department']); ?>
            </p>
        </div>
        <div class="welcome-stats" style="align-items: center; flex-wrap: wrap;">
            <?php if (!empty($availableEmployees)): ?>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-size: 10.5px; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; font-weight: 600;">Switch Demo Employee:</span>
                    <select id="employeeProfileSelector" style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); color: #ffffff; padding: 6px 12px; border-radius: 6px; font-size: 12px; outline: none; cursor: pointer;">
                        <?php foreach ($availableEmployees as $ae): ?>
                            <option value="<?php echo $ae['id']; ?>" style="color: #001938;" <?php echo ($ae['code'] === $activeEmployee['code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ae['name'] . ' (' . $ae['code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="welcome-badge">
                <div class="num" style="display: flex; align-items: center; justify-content: center; gap: 4px; color: #34d399;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Verified
                </div>
                <div class="lbl">Custody Status</div>
            </div>
            <div class="welcome-badge">
                <div class="num"><?php echo $totalItemsCount; ?> Items</div>
                <div class="lbl">Hardware & Licenses</div>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="metrics-grid">
        
        <!-- Metric 1: Allocated Hardware -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">My Assigned Devices</span>
                <div class="metric-icon-wrap cyan">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $hardwareCount; ?> Devices</div>
            <div class="metric-footer">
                <span class="trend-up">● In Active Custody</span>
                <span>• Laptop & Display</span>
            </div>
        </div>

        <!-- Metric 2: Issued Accessories -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Issued Accessories</span>
                <div class="metric-icon-wrap warning">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $accessoryCount; ?> Peripherals</div>
            <div class="metric-footer">
                <span class="trend-neutral">● Allocated</span>
                <span>• Dock, Keyboard & Mouse</span>
            </div>
        </div>

        <!-- Metric 3: Software Licenses -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Active Software Licenses</span>
                <div class="metric-icon-wrap navy">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $softwareCount; ?> Apps</div>
            <div class="metric-footer">
                <span class="trend-up">● Valid Subscriptions</span>
                <span>• M365, Adobe, IDE</span>
            </div>
        </div>

        <!-- Metric 4: Open Support Tickets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">My Support Requests</span>
                <div class="metric-icon-wrap success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo count($recentTickets); ?> Tickets</div>
            <div class="metric-footer">
                <span class="trend-neutral">1 In Progress</span>
                <span>• SLA: Today</span>
            </div>
        </div>

    </div>

    <!-- Dashboard Main Grid: Table & Widgets -->
    <div class="dashboard-grid">

        <!-- Left Column: Assigned Inventory & Open Tickets -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Main Content Card: My Assigned IT Equipment -->
            <div class="content-card" id="secMyAssets">
                <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h2>My Allocated IT Equipment & Software</h2>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                            Hardware and digital resources registered to your custody
                        </div>
                    </div>
                    
                    <div class="card-header-actions" style="flex-wrap: wrap;">
                        <!-- Category Filter Tabs -->
                        <div class="emp-filter-tabs">
                            <button type="button" class="emp-tab-btn active" data-filter="all">
                                All <span class="emp-tab-count"><?php echo $totalItemsCount; ?></span>
                            </button>
                            <button type="button" class="emp-tab-btn" data-filter="hardware">
                                💻 Hardware <span class="emp-tab-count"><?php echo $hardwareCount; ?></span>
                            </button>
                            <button type="button" class="emp-tab-btn" data-filter="accessory">
                                🎧 Accessories <span class="emp-tab-count"><?php echo $accessoryCount; ?></span>
                            </button>
                            <button type="button" class="emp-tab-btn" data-filter="software">
                                🔑 Software <span class="emp-tab-count"><?php echo $softwareCount; ?></span>
                            </button>
                        </div>

                        <!-- Request New Item Button -->
                        <button type="button" class="quick-action-btn" onclick="openEmployeeRequestModal()" style="height: 32px; padding: 0 12px; font-size: 12px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Request Equipment
                        </button>
                    </div>
                </div>

                <!-- Table Search Toolbar -->
                <div style="padding: 12px 20px; border-bottom: 1px solid var(--border-color); background: #f8fafc; display: flex; align-items: center; gap: 10px;">
                    <div style="position: relative; flex: 1; max-width: 380px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 10px; top: 9px; color: var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="empItemSearchInput" placeholder="Search by Tag, Name, Serial or Model..." style="width: 100%; border: 1px solid var(--border-color); border-radius: 6px; padding: 6px 12px 6px 32px; font-size: 12.5px; outline: none; background: #ffffff;">
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-left: auto;">
                        Click <strong>Handover Slip</strong> to view or print official custody document.
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="empAllocatedTable">
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th>Category / Specs</th>
                                <th>Serial / Key</th>
                                <th>Assigned Date</th>
                                <th>Status / Condition</th>
                                <th style="text-align: right; padding-right: 18px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allocatedItems as $item): ?>
                                <tr class="emp-item-row" data-type="<?php echo $item['type']; ?>">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-weight: 700; color: var(--navy-primary); font-family: monospace; font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                                <?php echo htmlspecialchars($item['tag']); ?>
                                            </span>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary); font-size: 13px;">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12.5px; font-weight: 600; color: var(--navy-primary);">
                                            <?php echo htmlspecialchars($item['category']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 1px;">
                                            <?php echo htmlspecialchars($item['specs']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; font-size: 11.5px; color: var(--text-secondary); background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <?php echo htmlspecialchars($item['serial']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 12px; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($item['date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $item['badge']; ?>" style="font-size: 11px;">
                                            <?php echo htmlspecialchars($item['status']); ?>
                                        </span>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                            Condition: <strong><?php echo htmlspecialchars($item['condition']); ?></strong>
                                        </div>
                                    </td>
                                    <td style="text-align: right; padding-right: 18px;">
                                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                                            <?php if ($item['type'] === 'hardware'): ?>
                                                <button type="button" class="card-btn" title="View Official Handover Slip" onclick="openEmployeeHandoverSlip('<?php echo $item['tag']; ?>')" style="padding: 4px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                                    Handover Slip
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="card-btn" title="Report Fault or Issue" onclick="openEmployeeTicketModal('<?php echo $item['tag'] . ' - ' . $item['name']; ?>')" style="padding: 4px 8px; font-size: 11px; color: var(--warning); border-color: rgba(245, 158, 11, 0.4); display: inline-flex; align-items: center; gap: 4px;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                                Report Issue
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr id="empNoItemsRow" style="display: none;">
                                <td colspan="6" style="text-align: center; padding: 36px 16px; color: var(--text-secondary);">
                                    <div style="font-size: 28px; margin-bottom: 6px;">📦</div>
                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;">No matching items found</div>
                                    <div style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">Try adjusting your category tab filter or search query.</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Content Card 2: My Support Tickets -->
            <div class="content-card" id="secMyTickets">
                <div class="card-header">
                    <div>
                        <h2>My IT Support Requests & Tickets</h2>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                            Track repair requests, peripheral orders, and service desk tickets
                        </div>
                    </div>
                    <div class="card-header-actions">
                        <button type="button" class="quick-action-btn" onclick="openEmployeeTicketModal()" style="height: 32px; padding: 0 12px; font-size: 12px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Raise New Ticket
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Subject / Description</th>
                                <th>Associated Asset</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date / Assigned To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $t): ?>
                                <tr>
                                    <td>
                                        <span class="ticket-id" style="font-weight: 700; color: var(--navy-primary); font-family: monospace;">
                                            <?php echo htmlspecialchars($t['id']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ticket-subject"><?php echo htmlspecialchars($t['subject']); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 12px; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($t['asset']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $t['p_class']; ?>"><?php echo htmlspecialchars($t['priority']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $t['s_class']; ?>"><?php echo htmlspecialchars($t['status']); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($t['date']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($t['tech']); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column: Self-Service Widgets & Information -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Widget 1: Quick Self-Service Actions -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Quick Self-Service Actions</h2>
                </div>
                <div class="emp-quick-actions-grid">
                    <!-- Action Tile 1: Report Fault -->
                    <div class="emp-action-tile" onclick="openEmployeeTicketModal()">
                        <div class="tile-icon amber">⚠️</div>
                        <div class="tile-title">Report Device Fault</div>
                        <div class="tile-desc">Hardware defect, broken display, battery drain</div>
                    </div>

                    <!-- Action Tile 2: Request Peripheral -->
                    <div class="emp-action-tile" onclick="openEmployeeRequestModal('Accessory / Peripheral')">
                        <div class="tile-icon cyan">🎧</div>
                        <div class="tile-title">Request Accessory</div>
                        <div class="tile-desc">Keyboard, mouse, USB-C adapter, or headset</div>
                    </div>

                    <!-- Action Tile 3: Request Software -->
                    <div class="emp-action-tile" onclick="openEmployeeRequestModal('Software License')">
                        <div class="tile-icon navy">🔑</div>
                        <div class="tile-title">Software Access</div>
                        <div class="tile-desc">Request Figma, JetBrains, or Cloud tools</div>
                    </div>

                    <!-- Action Tile 4: Handover Slip -->
                    <div class="emp-action-tile" onclick="openEmployeeHandoverSlip()">
                        <div class="tile-icon green">📄</div>
                        <div class="tile-title">Handover Slip</div>
                        <div class="tile-desc">View and print official custodian agreement</div>
                    </div>
                </div>
            </div>

            <!-- Widget 2: Custody Distribution (Matching Dashboard style) -->
            <div class="content-card">
                <div class="card-header">
                    <h2>My Custody Distribution</h2>
                </div>
                <div class="asset-category-list">
                    <div class="category-row">
                        <div class="category-meta">
                            <span>Laptops & Computing</span>
                            <strong>1 Unit (40%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-cyan" style="width: 40%;"></div>
                        </div>
                    </div>

                    <div class="category-row">
                        <div class="category-meta">
                            <span>Displays & Visuals</span>
                            <strong>1 Unit (30%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-navy" style="width: 30%;"></div>
                        </div>
                    </div>

                    <div class="category-row">
                        <div class="category-meta">
                            <span>Peripherals & Input</span>
                            <strong>3 Items (20%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-green" style="width: 20%;"></div>
                        </div>
                    </div>

                    <div class="category-row">
                        <div class="category-meta">
                            <span>Enterprise Software</span>
                            <strong>4 Apps (10%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-amber" style="width: 10%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 3: IT Support Contact & Desk Info -->
            <div class="content-card">
                <div class="card-header">
                    <h2>IT Support & Desk Information</h2>
                </div>
                <div class="it-support-card-body">
                    <div class="it-support-item">
                        <div class="it-support-icon">🏢</div>
                        <div class="it-support-text">
                            <div class="it-support-label">IT Service Desk Location</div>
                            <div class="it-support-val">Floor 2, Hub A, Bangalore HQ</div>
                        </div>
                    </div>

                    <div class="it-support-item">
                        <div class="it-support-icon">📞</div>
                        <div class="it-support-text">
                            <div class="it-support-label">Internal Phone Extension</div>
                            <div class="it-support-val">Ext: 204 • Helpdesk Hotline</div>
                        </div>
                    </div>

                    <div class="it-support-item">
                        <div class="it-support-icon">✉️</div>
                        <div class="it-support-text">
                            <div class="it-support-label">Support Email</div>
                            <div class="it-support-val">itsupport@viros.in</div>
                        </div>
                    </div>

                    <div class="it-support-item">
                        <div class="it-support-icon">🕒</div>
                        <div class="it-support-text">
                            <div class="it-support-label">Operating Hours</div>
                            <div class="it-support-val">Mon – Fri, 9:00 AM – 6:30 PM IST</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 4: Recent Custody Activity -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Custody Activity</h2>
                </div>
                <div class="activity-feed">
                    <div class="activity-item">
                        <div class="activity-dot">💻</div>
                        <div class="activity-body">
                            <div class="activity-text">
                                <strong>Dell Latitude 5420</strong> custody confirmed & handover acknowledged.
                            </div>
                            <div class="activity-time">15 Jan 2025 • Hardware Allocation</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-dot">🎧</div>
                        <div class="activity-body">
                            <div class="activity-text">
                                <strong>Logitech MX Master 3S</strong> & <strong>MX Keys</strong> issued.
                            </div>
                            <div class="activity-time">16 Jan 2025 • Accessory Provisioning</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-dot">🔑</div>
                        <div class="activity-body">
                            <div class="activity-text">
                                <strong>Adobe Creative Cloud</strong> enterprise seat assigned.
                            </div>
                            <div class="activity-time">20 Feb 2025 • License Provisioning</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-dot">🎫</div>
                        <div class="activity-body">
                            <div class="activity-text">
                                Ticket <strong>#TKT-1082</strong> registered with IT Helpdesk.
                            </div>
                            <div class="activity-time">08 Oct 2026 • In Progress</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</main>

<!-- =========================================================================
     MODAL 1: RAISE IT SUPPORT TICKET / REPORT FAULT
     ========================================================================= -->
<div class="modal-overlay" id="employeeTicketModal" style="display: none;">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 6px; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    🎫
                </div>
                <h3 style="margin: 0;">Raise IT Support Ticket</h3>
            </div>
            <button type="button" class="modal-close-btn">&times;</button>
        </div>
        <form id="employeeTicketForm">
            <div class="modal-body" style="padding: 18px 20px;">
                <div class="modal-form-group">
                    <label>Affected Asset / Device</label>
                    <select class="modal-select" id="ticketAssetSelect" required>
                        <option value="">-- Select Allocated Device or General --</option>
                        <?php foreach ($allocatedItems as $it): ?>
                            <option value="<?php echo htmlspecialchars($it['tag'] . ' - ' . $it['name']); ?>">
                                <?php echo htmlspecialchars($it['tag'] . ' — ' . $it['name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="General Workstation / Network Issue">General Workstation / Network / VPN Issue</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label>Issue Subject</label>
                    <input type="text" class="modal-input" id="ticketSubjectInput" placeholder="Brief description of the problem..." required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label>Issue Category</label>
                        <select class="modal-select" required>
                            <option value="Hardware Glitch">Hardware Glitch</option>
                            <option value="Screen / Display Failure">Screen / Display Failure</option>
                            <option value="Battery / Power Issue">Battery / Power Issue</option>
                            <option value="Operating System / Blue Screen">Operating System / Blue Screen</option>
                            <option value="Network / VPN / Internet">Network / VPN / Internet</option>
                            <option value="Software License / Activation">Software License / Activation</option>
                        </select>
                    </div>

                    <div class="modal-form-group">
                        <label>Urgency Level</label>
                        <select class="modal-select" required>
                            <option value="Normal">Normal (Response < 4h)</option>
                            <option value="High">High (Impacting Work)</option>
                            <option value="Critical">Critical (System Down)</option>
                            <option value="Low">Low (General Query)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label>Detailed Description</label>
                    <textarea class="modal-textarea" placeholder="Please describe what happened, any error messages, and steps to reproduce..." rows="3" required></textarea>
                </div>

                <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 10px; text-align: center; font-size: 12px; color: var(--text-secondary);">
                    📎 Drag & drop screenshot or error log here (optional)
                </div>
            </div>
            <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="btn-secondary modal-cancel-btn">Cancel</button>
                <button type="submit" class="btn-primary">Submit Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: REQUEST NEW EQUIPMENT / ACCESSORY / SOFTWARE
     ========================================================================= -->
<div class="modal-overlay" id="employeeRequestModal" style="display: none;">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 6px; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    📦
                </div>
                <h3 style="margin: 0;">Request IT Equipment or License</h3>
            </div>
            <button type="button" class="modal-close-btn">&times;</button>
        </div>
        <form id="employeeRequestForm">
            <div class="modal-body" style="padding: 18px 20px;">
                <div class="modal-form-group">
                    <label>Item Type</label>
                    <select class="modal-select" id="requestCategorySelect" required>
                        <option value="Accessory / Peripheral">Accessory / Peripheral (Monitor, Dock, Keyboard, Mouse)</option>
                        <option value="Software License">Software License / Cloud Tool</option>
                        <option value="Hardware Upgrade">Hardware Upgrade (RAM, SSD, Laptop Replacement)</option>
                        <option value="Temporary Loaner Device">Temporary Loaner Device (Project / Travel)</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label>Item Required</label>
                    <input type="text" class="modal-input" id="requestItemNameInput" placeholder="e.g. Ergonomic Keyboard, Type-C Multiport Dongle, Figma Seat..." required>
                </div>

                <div class="modal-form-group">
                    <label>Business Justification</label>
                    <textarea class="modal-textarea" placeholder="Why is this equipment or license required for your day-to-day responsibilities?" rows="3" required></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label>Required By Date</label>
                        <input type="date" class="modal-input" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                    </div>

                    <div class="modal-form-group">
                        <label>Delivery / Pickup Location</label>
                        <select class="modal-select">
                            <option value="Bangalore HQ">Bangalore HQ Desk</option>
                            <option value="Remote / WFH Address">Remote / Home Address</option>
                            <option value="Branch Office">Branch Office</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="btn-secondary modal-cancel-btn">Cancel</button>
                <button type="submit" class="btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: VIEW / PRINT OFFICIAL HANDOVER SLIP
     ========================================================================= -->
<div class="modal-overlay" id="employeeHandoverSlipModal" style="display: none;">
    <div class="modal-box" style="max-width: 720px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 6px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    📄
                </div>
                <h3 style="margin: 0;">Equipment Custody Handover Slip</h3>
            </div>
            <button type="button" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div id="handoverSlipPrintArea" class="handover-slip-sheet">
                
                <div class="slip-header-top">
                    <div class="slip-company-info">
                        <h3>VIROS IT SOLUTIONS & SERVICES</h3>
                        <p>Enterprise IT Asset Management & Service Desk Division</p>
                        <p style="font-size: 11px; color: #94a3b8;">Document Form: IT-AST-HND-2025/V1</p>
                    </div>
                    <div class="slip-title-box">
                        <div class="slip-doc-no">SLIP #AST-HO-2025-0819</div>
                        <div class="slip-date">Date Issued: 15-Jan-2025</div>
                    </div>
                </div>

                <div class="slip-grid-meta">
                    <div class="slip-grid-item">
                        <span>Employee Name:</span>
                        <strong><?php echo htmlspecialchars($activeEmployee['name']); ?></strong>
                    </div>
                    <div class="slip-grid-item">
                        <span>Employee Code:</span>
                        <strong><?php echo htmlspecialchars($activeEmployee['code']); ?></strong>
                    </div>
                    <div class="slip-grid-item">
                        <span>Department / Designation:</span>
                        <strong><?php echo htmlspecialchars($activeEmployee['department'] . ' • ' . $activeEmployee['designation']); ?></strong>
                    </div>
                    <div class="slip-grid-item">
                        <span>Allocation Type:</span>
                        <strong>Permanent Handover (Workstation & Remote)</strong>
                    </div>
                </div>

                <div style="font-size: 12.5px; font-weight: 700; color: var(--navy-primary); margin-bottom: 8px;">Allocated Equipment Schedule:</div>
                <table class="slip-table">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Description</th>
                            <th>Serial Number</th>
                            <th>Condition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>AST2026001</strong></td>
                            <td>Dell Latitude 5420 Laptop (Core i7, 16GB, 512GB)</td>
                            <td><code>C02G40PZMD6T</code></td>
                            <td>Good</td>
                        </tr>
                        <tr>
                            <td><strong>AST2026048</strong></td>
                            <td>Dell UltraSharp 24" USB-C Monitor U2422HE</td>
                            <td><code>CN-04D792-742</code></td>
                            <td>Excellent</td>
                        </tr>
                        <tr>
                            <td><strong>ACC-1002</strong></td>
                            <td>Dell WD19S USB-C Docking Station 130W</td>
                            <td><code>WD19-98124-IN</code></td>
                            <td>Good</td>
                        </tr>
                        <tr>
                            <td><strong>ACC-1009</strong></td>
                            <td>Logitech MX Keys Wireless Keyboard</td>
                            <td><code>SN-829104-MX</code></td>
                            <td>Good</td>
                        </tr>
                    </tbody>
                </table>

                <div class="slip-terms-box">
                    <strong>Custodian Declaration:</strong> I hereby acknowledge receipt of the hardware and accessories listed above in sound operational condition. I agree to exercise reasonable care in safeguarding the equipment in compliance with company IT asset security policies.
                </div>

                <div class="slip-signatures-row">
                    <div class="slip-sig-box">
                        <div style="font-size: 11px; color: #64748b;">Issued By (IT Asset Admin):</div>
                        <div class="slip-sig-line">Authorized Signatory / IT Dept</div>
                    </div>
                    <div class="slip-sig-box">
                        <div style="font-size: 11px; color: #64748b;">Received & Acknowledged By:</div>
                        <div class="slip-sig-line"><?php echo htmlspecialchars($activeEmployee['name']); ?></div>
                    </div>
                </div>

            </div>
        </div>
        <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="font-size: 12px; color: var(--text-muted);">
                Verified Digital Custody Record • Stored in Database
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-secondary modal-cancel-btn">Close</button>
                <button type="button" class="btn-primary" onclick="printHandoverSlip()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print Handover Slip
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: EMPLOYEE PROFILE DETAILS
     ========================================================================= -->
<div class="modal-overlay" id="employeeProfileModal" style="display: none;">
    <div class="modal-box" style="max-width: 580px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 6px; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    👤
                </div>
                <h3 style="margin: 0;">Employee Profile & IT Custody</h3>
            </div>
            <button type="button" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <!-- Profile Header Card -->
            <div style="display: flex; align-items: center; gap: 16px; background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%); border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--cyan-primary); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0, 147, 167, 0.25);">
                    <?php echo htmlspecialchars($emp_initials ?? 'EP'); ?>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 17px; font-weight: 700; color: var(--navy-primary);"><?php echo htmlspecialchars($activeEmployee['name']); ?></div>
                    <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                        <?php echo htmlspecialchars($activeEmployee['designation']); ?> • <strong><?php echo htmlspecialchars($activeEmployee['code']); ?></strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                        <span class="badge badge-resolved" style="font-size: 11px;">Active Employee</span>
                        <span style="font-size: 11.5px; color: #059669; font-weight: 600;">● Custody Verified</span>
                    </div>
                </div>
            </div>

            <!-- Profile Details Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block;">Department</span>
                    <strong style="font-size: 13px; color: var(--navy-primary);"><?php echo htmlspecialchars($activeEmployee['department']); ?></strong>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block;">Official Email</span>
                    <strong style="font-size: 13px; color: var(--navy-primary);"><?php echo htmlspecialchars($activeEmployee['email']); ?></strong>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block;">Office Location</span>
                    <strong style="font-size: 13px; color: var(--navy-primary);"><?php echo htmlspecialchars($activeEmployee['location']); ?></strong>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block;">Allocation Status</span>
                    <strong style="font-size: 13px; color: #059669;"><?php echo htmlspecialchars($activeEmployee['custody']); ?></strong>
                </div>
            </div>

            <!-- Summary Chips -->
            <div style="background: #f1f5f9; border-radius: 8px; padding: 12px 16px;">
                <div style="font-size: 12px; font-weight: 700; color: var(--navy-primary); margin-bottom: 8px;">Custody Allocation Summary:</div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <span style="background: #ffffff; border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 16px; font-size: 12px; font-weight: 600; color: var(--text-primary);">💻 <?php echo $hardwareCount; ?> Hardware Devices</span>
                    <span style="background: #ffffff; border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 16px; font-size: 12px; font-weight: 600; color: var(--text-primary);">🎧 <?php echo $accessoryCount; ?> Peripherals</span>
                    <span style="background: #ffffff; border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 16px; font-size: 12px; font-weight: 600; color: var(--text-primary);">🔑 <?php echo $softwareCount; ?> Software Licenses</span>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="font-size: 11.5px; color: var(--text-muted);">
                Need to update details? Contact HR / IT Administrator.
            </div>
            <button type="button" class="btn-primary modal-cancel-btn">Close</button>
        </div>
    </div>
</div>

<?php
// Include Layout Footer
include 'includes/footer.php';
?>
