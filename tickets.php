<?php
// Asset Management & IT Service Desk Portal - Admin All Support Tickets Management (UI)
// Redesigned to match the exact visual style, layout tokens, and component architecture of assets.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title  = "IT Support Tickets - VIROS Portal";
$active_page = "tickets";
$extra_css   = ['css/categories.css', 'css/assets.css', 'css/tickets.css'];
$extra_js    = ['js/tickets.js'];

// Include Database Configuration
require_once __DIR__ . '/config/db.php';

// Fetch dynamic departments from DB if available
$dbDepartments = [];
if (isset($conn) && $conn !== false) {
    $dStmt = sqlsrv_query($conn, "SELECT department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    if ($dStmt !== false) {
        while ($r = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($r['department_name'])) $dbDepartments[] = $r['department_name'];
        }
        sqlsrv_free_stmt($dStmt);
    }
}
if (empty($dbDepartments)) {
    $dbDepartments = ['Operations', 'Finance & Accounts', 'Engineering & R&D', 'Human Resources', 'Marketing & Sales', 'IT Infrastructure'];
}

// Enterprise IT Support Tickets Dataset (Format: prefix + MMYY + serial, e.g. TKT10261104)
$allTickets = [];
if (isset($conn) && $conn !== false) {
    $tSql = "SELECT * FROM support_tickets ORDER BY incident_date DESC, id DESC";
    $tStmt = sqlsrv_query($conn, $tSql);
    if ($tStmt !== false) {
        while ($row = sqlsrv_fetch_array($tStmt, SQLSRV_FETCH_ASSOC)) {
            $dt = $row['incident_date'];
            $formattedDate = '';
            if ($dt instanceof DateTime) {
                $formattedDate = $dt->format('d M Y, h:i A');
            } elseif (!empty($dt)) {
                $formattedDate = date('d M Y, h:i A', strtotime($dt));
            } else {
                $formattedDate = date('d M Y, h:i A');
            }

            $prio = $row['priority'] ?? 'Medium';
            $pClass = 'badge-medium';
            if ($prio === 'Urgent') $pClass = 'badge-urgent';
            elseif ($prio === 'High') $pClass = 'badge-high';
            elseif ($prio === 'Low') $pClass = 'badge-low';

            $st = $row['status'] ?? 'Open';
            $sClass = 'badge-status-progress';
            $sFilter = 'in-progress';
            if (stripos($st, 'open') !== false) {
                $sClass = 'badge-status-open';
                $sFilter = 'open';
            } elseif (stripos($st, 'resolved') !== false || stripos($st, 'closed') !== false) {
                $sClass = 'badge-status-resolved';
                $sFilter = 'resolved';
            }

            $empName = $row['employee_name'] ?: 'Employee Member';
            $empInitials = strtoupper(substr($empName, 0, 2));

            $allTickets[] = [
                'id'          => $row['ticket_no'],
                'subject'     => $row['subject'],
                'category'    => $row['category'] ?: 'General IT Support',
                'asset'       => $row['asset_name'] ?: 'Corporate Device',
                'priority'    => $prio,
                'p_class'     => $pClass,
                'status'      => $st,
                's_class'     => $sClass,
                's_filter'    => $sFilter,
                'date'        => $formattedDate,
                'description' => $row['description'] ?? '',
                'requester'   => [
                    'name'       => $empName,
                    'code'       => $row['emp_code'] ?: 'EMP-001',
                    'department' => $row['department'] ?: 'General Staff',
                    'email'      => strtolower(str_replace(' ', '.', $empName)) . '@viros.in',
                    'phone'      => '+91 98765 00000',
                    'location'   => 'Corporate Office',
                    'initials'   => $empInitials
                ],
                'chat_thread' => json_decode($row['chat_history'] ?? '[]', true) ?: []
            ];
        }
        sqlsrv_free_stmt($tStmt);
    }
}

if (empty($allTickets)) {
    $allTickets = [
    [
        'id'        => 'TKT10261104',
        'subject'   => 'Laptop battery draining rapidly and heating during Teams meetings',
        'category'  => 'Hardware / Thermal & Battery',
        'asset'     => 'Dell Latitude 5420 (AST2026001)',
        'priority'  => 'High',
        'p_class'   => 'badge-high',
        'status'    => 'In Progress',
        's_class'   => 'badge-status-progress',
        's_filter'  => 'in-progress',
        'date'      => '09 Oct 2026, 02:30 PM',
        'description' => 'Battery drops from 100% to 20% in less than 45 minutes of video calls. Fan stays on continuously. Diagnostic requested for thermal paste or battery replacement.',
        'requester' => [
            'name'       => 'Rajesh Sharma',
            'code'       => 'EMP-104',
            'department' => 'Operations',
            'email'      => 'rajesh.sharma@viros.in',
            'phone'      => '+91 98765 43210',
            'location'   => 'HQ Building - Floor 3',
            'initials'   => 'RS'
        ],
        'chat_thread' => [
            ['author' => 'Rajesh Sharma', 'avatar' => 'RS', 'type' => 'employee', 'time' => '09 Oct 2026, 02:30 PM', 'text' => 'Fan noise gets extremely loud whenever video camera is active.'],
            ['author' => 'IT Support Service Desk', 'avatar' => 'IT', 'type' => 'tech', 'time' => '09 Oct 2026, 03:15 PM', 'text' => 'Hardware diagnostics ran. Replacement battery pack dispatched from OEM stock. Scheduled swap tomorrow morning.']
        ]
    ],
    [
        'id'        => 'TKT10261098',
        'subject'   => 'Multiple ERP login auth timeouts and SSL certificate handshake error',
        'category'  => 'Software / Enterprise ERP',
        'asset'     => 'HP EliteBook 840 (AST2026019)',
        'priority'  => 'Urgent',
        'p_class'   => 'badge-urgent',
        'status'    => 'Open / Triage',
        's_class'   => 'badge-status-open',
        's_filter'  => 'open',
        'date'      => '09 Oct 2026, 12:45 PM',
        'description' => 'User cannot process month-end accounts closure. Browser throws SEC_ERROR_UNKNOWN_ISSUER on ERP portal root gateway. Need immediate SSL trust store check.',
        'requester' => [
            'name'       => 'Priya Patel',
            'code'       => 'EMP-108',
            'department' => 'Finance & Accounts',
            'email'      => 'priya.patel@viros.in',
            'phone'      => '+91 98234 56789',
            'location'   => 'Finance Wing - 2nd Floor',
            'initials'   => 'PP'
        ],
        'chat_thread' => [
            ['author' => 'Priya Patel', 'avatar' => 'PP', 'type' => 'employee', 'time' => '09 Oct 2026, 12:45 PM', 'text' => 'Critical blocker for today\'s vendor payout approvals. Please assist urgently.']
        ]
    ],
    [
        'id'        => 'TKT10261082',
        'subject'   => 'External monitor HDMI signal flickering after workstation standby',
        'category'  => 'Hardware / External Display',
        'asset'     => 'Dell UltraSharp 27" (AST2026048)',
        'priority'  => 'Medium',
        'p_class'   => 'badge-medium',
        'status'    => 'In Progress',
        's_class'   => 'badge-status-progress',
        's_filter'  => 'in-progress',
        'date'      => '08 Oct 2026, 11:15 AM',
        'description' => 'Whenever laptop wakes from sleep/standby, the secondary HDMI monitor flickers black for 5 seconds before returning to normal. Cable has been reseated once.',
        'requester' => [
            'name'       => 'Vikram Malhotra',
            'code'       => 'EMP-115',
            'department' => 'Engineering & R&D',
            'email'      => 'vikram.m@viros.in',
            'phone'      => '+91 97123 45678',
            'location'   => 'Engineering Block - Floor 1',
            'initials'   => 'VM'
        ],
        'chat_thread' => [
            ['author' => 'IT Support Engineer', 'avatar' => 'IT', 'type' => 'tech', 'time' => '08 Oct 2026, 01:20 PM', 'text' => 'Driver update rolled out via Intune. Testing with high-speed 4K HDMI 2.1 cable today.']
        ]
    ],
    [
        'id'        => 'TKT10261075',
        'subject'   => 'Cisco AnyConnect VPN gateway disconnects repeatedly during remote access',
        'category'  => 'Network / Remote VPN',
        'asset'     => 'Lenovo ThinkPad T14 (AST2026032)',
        'priority'  => 'High',
        'p_class'   => 'badge-high',
        'status'    => 'In Progress',
        's_class'   => 'badge-status-progress',
        's_filter'  => 'in-progress',
        'date'      => '07 Oct 2026, 04:10 PM',
        'description' => 'VPN tunnel drops precisely every 15 minutes with "Tunnel connection reset by peer" alert. HR recruitment database records fail to save during candidate onboarding.',
        'requester' => [
            'name'       => 'Ananya Sen',
            'code'       => 'EMP-122',
            'department' => 'Human Resources',
            'email'      => 'ananya.sen@viros.in',
            'phone'      => '+91 99887 76655',
            'location'   => 'HR Wing - 1st Floor',
            'initials'   => 'AS'
        ],
        'chat_thread' => [
            ['author' => 'IT Network Desk', 'avatar' => 'IT', 'type' => 'tech', 'time' => '07 Oct 2026, 05:00 PM', 'text' => 'Firewall keepalive timeout extended to 120 minutes on VPN profile #4. Verified session stability.']
        ]
    ],
    [
        'id'        => 'TKT10261060',
        'subject'   => 'RAM upgrade request for 3D simulation and CAD rendering workstation',
        'category'  => 'Hardware / Memory Upgrade',
        'asset'     => 'HP ZBook Fury G8 (AST2026012)',
        'priority'  => 'Medium',
        'p_class'   => 'badge-medium',
        'status'    => 'Open / Triage',
        's_class'   => 'badge-status-open',
        's_filter'  => 'open',
        'date'      => '06 Oct 2026, 09:30 AM',
        'description' => 'Current 16GB DDR4 reaches 98% utilization during SolidWorks & Ansys simulations. Requesting additional 16GB DDR4 SO-DIMM module from available component inventory.',
        'requester' => [
            'name'       => 'Rahul Deshmukh',
            'code'       => 'EMP-130',
            'department' => 'Engineering & R&D',
            'email'      => 'rahul.d@viros.in',
            'phone'      => '+91 96543 21098',
            'location'   => 'CAD Center - Floor 2',
            'initials'   => 'RD'
        ],
        'chat_thread' => []
    ],
    [
        'id'        => 'TKT09261045',
        'subject'   => 'Replacement 65W Type-C adapter for travel docking station',
        'category'  => 'Peripherals / Cables & Power',
        'asset'     => 'Lenovo 65W AC Adapter (ACC2026009)',
        'priority'  => 'Low',
        'p_class'   => 'badge-low',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '28 Sep 2026, 03:45 PM',
        'description' => 'Original adapter wire frayed near USB-C connector tip. Issued new Lenovo genuine 65W Type-C adapter from accessory storeroom.',
        'requester' => [
            'name'       => 'Sneha Kulkarni',
            'code'       => 'EMP-102',
            'department' => 'Executive Office',
            'email'      => 'sneha.k@viros.in',
            'phone'      => '+91 98112 23344',
            'location'   => 'Executive Suite - Floor 4',
            'initials'   => 'SK'
        ],
        'chat_thread' => [
            ['author' => 'IT Support', 'avatar' => 'IT', 'type' => 'tech', 'time' => '29 Sep 2026, 11:00 AM', 'text' => 'New adapter handed over and sign-off custody receipt updated.']
        ]
    ],
    [
        'id'        => 'TKT09261031',
        'subject'   => 'Outlook archive OST mailbox corruption after Windows security patch',
        'category'  => 'Software / Email & Mailbox',
        'asset'     => 'Dell OptiPlex 7090 (AST2026077)',
        'priority'  => 'Medium',
        'p_class'   => 'badge-medium',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '24 Sep 2026, 10:20 AM',
        'description' => 'Outlook hung on "Loading Profile". OST rebuilt from Exchange Online 365 cloud mailbox. Search index catalog repopulated completely.',
        'requester' => [
            'name'       => 'Amit Singhania',
            'code'       => 'EMP-119',
            'department' => 'Marketing & Sales',
            'email'      => 'amit.s@viros.in',
            'phone'      => '+91 97223 34455',
            'location'   => 'Marketing Bay - Floor 2',
            'initials'   => 'AS'
        ],
        'chat_thread' => [
            ['author' => 'IT Service Desk', 'avatar' => 'IT', 'type' => 'tech', 'time' => '24 Sep 2026, 02:15 PM', 'text' => 'Mail sync verified. All 42GB mailbox items indexed and searchable.']
        ]
    ],
    [
        'id'        => 'TKT09261014',
        'subject'   => 'Wireless keyboard keystrokes stuttering and repeating intermittently',
        'category'  => 'Peripherals / Input Devices',
        'asset'     => 'Logitech MK345 Combo (ACC2026014)',
        'priority'  => 'Low',
        'p_class'   => 'badge-low',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '19 Sep 2026, 11:40 AM',
        'description' => '2.4GHz USB receiver was plugged into rear metallic chassis causing radio frequency shielding. Moved receiver to front USB 3.0 port and changed AAA batteries.',
        'requester' => [
            'name'       => 'Karan Joshi',
            'code'       => 'EMP-128',
            'department' => 'Operations',
            'email'      => 'karan.j@viros.in',
            'phone'      => '+91 98456 78901',
            'location'   => 'Operations Bay - Ground Floor',
            'initials'   => 'KJ'
        ],
        'chat_thread' => []
    ],
    [
        'id'        => 'TKT09261005',
        'subject'   => 'Core switch port packet drops in 2nd Floor server rack patch bay',
        'category'  => 'Network / LAN Infrastructure',
        'asset'     => 'Cisco Catalyst 2960 (AST2026090)',
        'priority'  => 'Urgent',
        'p_class'   => 'badge-urgent',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '15 Sep 2026, 08:15 AM',
        'description' => 'Port Gi0/14 duplex mismatch caused 12% packet loss for accounts department cluster. Configured forced 1000Mbps Full Duplex and re-crimped Cat6 keystone jack.',
        'requester' => [
            'name'       => 'Sunita Reddy',
            'code'       => 'EMP-105',
            'department' => 'IT Infrastructure',
            'email'      => 'sunita.r@viros.in',
            'phone'      => '+91 99001 12233',
            'location'   => 'IT NOC - 2nd Floor',
            'initials'   => 'SR'
        ],
        'chat_thread' => [
            ['author' => 'IT Infrastructure Team', 'avatar' => 'IT', 'type' => 'tech', 'time' => '15 Sep 2026, 10:30 AM', 'text' => 'Sniffer packets show 0 drops across 24-hour test period. Resolved.']
        ]
    ]
];
}

// Calculate KPI Counts
$totalCount = count($allTickets);
$openCount = 0;
$inProgressCount = 0;
$urgentCount = 0;
$resolvedCount = 0;

foreach ($allTickets as $t) {
    if ($t['s_filter'] === 'open') $openCount++;
    if ($t['s_filter'] === 'in-progress') $inProgressCount++;
    if ($t['s_filter'] === 'resolved') $resolvedCount++;
    if ($t['priority'] === 'Urgent') $urgentCount++;
}

// Categories list for filter
$ticketCategories = ['Hardware / Thermal & Battery', 'Software / Enterprise ERP', 'Hardware / External Display', 'Network / Remote VPN', 'Hardware / Memory Upgrade', 'Peripherals / Cables & Power', 'Software / Email & Mailbox', 'Peripherals / Input Devices', 'Network / LAN Infrastructure'];

// Include Global Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Support Tickets Page Content (Structured exactly like assets.php) -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar (assets.php style) -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Support Tickets</span>
                <span>/</span>
                <span>All Tickets</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
                IT Support Tickets & Service Desk
            </h1>
            <p>Monitor, triage, assign, and resolve technical incidents across all departments and employees.</p>
        </div>

        <div class="page-header-actions">
            <!-- Export CSV Button (assets.php btn-secondary style) -->
            <button type="button" class="btn-secondary" id="exportTicketsBtn" title="Export tickets to CSV" onclick="exportTicketsCSV()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Export CSV</span>
            </button>
        </div>
    </div>

    <!-- Executive Metric KPI Strip (exact asset-stats-grid architecture from assets.php) -->
    <div class="asset-stats-grid">
        <!-- 1. Total Tickets -->
        <div class="asset-stat-card card-total" onclick="resetAllFilters()" title="Click to view all tickets">
            <div class="asset-stat-info">
                <div class="stat-lbl">Total Tickets</div>
                <div class="stat-val" id="statTotalTickets"><?php echo $totalCount; ?></div>
                <div class="stat-sub">
                    <span class="stat-trend-up">All Incidents</span>
                </div>
            </div>
            <div class="asset-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
        </div>

        <!-- 2. Open / Triage Queue -->
        <div class="asset-stat-card card-available" onclick="filterByStatusTab('open')" title="Click to filter Open tickets">
            <div class="asset-stat-info">
                <div class="stat-lbl">Open / Triage</div>
                <div class="stat-val" id="statOpenTickets"><?php echo $openCount; ?></div>
                <div class="stat-sub">Awaiting action</div>
            </div>
            <div class="asset-stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>

        <!-- 3. In Progress Diagnostics -->
        <div class="asset-stat-card card-maintenance" onclick="filterByStatusTab('in-progress')" title="Click to filter In Progress tickets">
            <div class="asset-stat-info">
                <div class="stat-lbl">In Progress</div>
                <div class="stat-val" id="statProgressTickets"><?php echo $inProgressCount; ?></div>
                <div class="stat-sub">Active Diagnostics</div>
            </div>
            <div class="asset-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
        </div>

        <!-- 4. Critical / Urgent Escalations -->
        <div class="asset-stat-card card-expiring" onclick="filterByUrgency('urgent')" title="Click to filter Urgent tickets">
            <div class="asset-stat-info">
                <div class="stat-lbl">Critical / Urgent</div>
                <div class="stat-val" id="statUrgentTickets" style="color: #ea580c;"><?php echo $urgentCount; ?></div>
                <div class="stat-sub">Immediate SLA</div>
            </div>
            <div class="asset-stat-icon orange">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
        </div>

        <!-- 5. Resolved & Closed -->
        <div class="asset-stat-card card-in-use" onclick="filterByStatusTab('resolved')" title="Click to filter Resolved tickets">
            <div class="asset-stat-info">
                <div class="stat-lbl">Resolved / Closed</div>
                <div class="stat-val" id="statResolvedTickets"><?php echo $resolvedCount; ?></div>
                <div class="stat-sub">
                    <span style="color: var(--success); font-weight: 600;">94.6%</span> SLA Rate
                </div>
            </div>
            <div class="asset-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
        </div>
    </div>

    <!-- Status Tabs Navigation (exact .asset-status-tabs from assets.php) -->
    <div class="asset-status-tabs">
        <button type="button" class="status-tab-btn active" data-status="all">
            All Tickets <span class="status-tab-badge"><?php echo $totalCount; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="open">
            Open / Triage <span class="status-tab-badge"><?php echo $openCount; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="in-progress">
            In Progress <span class="status-tab-badge"><?php echo $inProgressCount; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="resolved">
            Resolved & Closed <span class="status-tab-badge"><?php echo $resolvedCount; ?></span>
        </button>
    </div>

    <!-- Toolbar: Search, Filters & Controls (exact .asset-toolbar from assets.php) -->
    <div class="asset-toolbar">
        <div class="toolbar-left">
            <!-- Search Input -->
            <div class="asset-search-wrapper" style="width: 320px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="ticketSearchInput" placeholder="Search by Ticket ID, Employee, Subject, Device...">
            </div>

            <!-- Department Filter -->
            <select class="asset-filter-select" id="deptFilter">
                <option value="all">All Departments</option>
                <?php foreach ($dbDepartments as $dept): ?>
                    <option value="<?php echo htmlspecialchars(strtolower($dept)); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Urgency Filter -->
            <select class="asset-filter-select" id="urgencyFilter">
                <option value="all">All Urgencies</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>

            <!-- Category Filter -->
            <select class="asset-filter-select" id="categoryFilter">
                <option value="all">All Categories</option>
                <?php foreach ($ticketCategories as $c): ?>
                    <option value="<?php echo htmlspecialchars(strtolower($c)); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="toolbar-right">
            <!-- Reset Filters Button -->
            <button type="button" class="btn-secondary" onclick="resetAllFilters()" style="padding: 8px 14px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                <span>Reset Filters</span>
            </button>
        </div>
    </div>

    <!-- Table Card Container (exact .asset-table-card from assets.php) -->
    <div class="asset-table-card" id="ticketTableView">
        <div class="asset-table-responsive">
            <table class="asset-data-table" id="ticketsDataTable">
                <thead>
                    <tr>
                        <th style="width: 15%;">Ticket ID & Date</th>
                        <th style="width: 20%;">Employee / Requester</th>
                        <th style="width: 30%;">Issue Subject & Description</th>
                        <th style="width: 11%;">Urgency</th>
                        <th style="width: 14%;">Affected Device</th>
                        <th style="width: 10%;">Status</th>
                        <th style="text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="ticketTableBody">
                    <?php foreach ($allTickets as $tkt): ?>
                        <tr class="ticket-data-row" 
                            data-id="<?php echo htmlspecialchars($tkt['id']); ?>"
                            data-status="<?php echo htmlspecialchars($tkt['s_filter']); ?>"
                            data-dept="<?php echo htmlspecialchars(strtolower($tkt['requester']['department'] ?? '')); ?>"
                            data-urgency="<?php echo htmlspecialchars(strtolower($tkt['priority'])); ?>"
                            data-category="<?php echo htmlspecialchars(strtolower($tkt['category'])); ?>">

                            <!-- 1. Merged Ticket ID & Date -->
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="asset-tag-badge" style="align-self: flex-start; cursor: default;"><?php echo htmlspecialchars($tkt['id']); ?></span>
                                    <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 500; white-space: nowrap;">
                                        <?php echo htmlspecialchars($tkt['date']); ?>
                                    </span>
                                </div>
                            </td>

                            <!-- 2. Employee / Requester (assignee-cell style from assets.php) -->
                            <td>
                                <div class="assignee-cell">
                                    <div class="assignee-avatar" style="background: #002d5c; color: #ffffff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php echo htmlspecialchars($tkt['requester']['initials'] ?? 'EM'); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary); font-size: 13px;">
                                            <?php echo htmlspecialchars($tkt['requester']['name']); ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); display: flex; align-items: center; gap: 5px; margin-top: 1px;">
                                            <span><?php echo htmlspecialchars($tkt['requester']['code']); ?></span>
                                            <span>•</span>
                                            <span style="background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 10.5px; color: var(--text-secondary); border: 1px solid #e2e8f0;">
                                                <?php echo htmlspecialchars($tkt['requester']['department']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Issue Subject & Description -->
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 3px; max-width: 360px;">
                                    <div class="asset-name-title" style="font-size: 13px; line-height: 1.35;" onclick='openAdminTicketDrawer(<?php echo json_encode($tkt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                        <?php echo htmlspecialchars($tkt['subject']); ?>
                                    </div>
                                    <?php if (!empty($tkt['description'])): ?>
                                        <div style="font-size: 11.5px; color: var(--text-secondary); display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4;" title="<?php echo htmlspecialchars($tkt['description']); ?>">
                                            <?php echo htmlspecialchars($tkt['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span style="font-size: 11px; color: var(--cyan-primary); font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        <?php echo htmlspecialchars($tkt['category']); ?>
                                    </span>
                                </div>
                            </td>

                            <!-- 4. Urgency Level -->
                            <td class="col-ticket-urgency">
                                <span class="badge <?php echo $tkt['p_class']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($tkt['priority']); ?>
                                </span>
                            </td>

                            <!-- 5. Affected Asset / Device (category-pill style from assets.php) -->
                            <td>
                                <span class="category-pill" style="font-size: 11.5px; font-weight: 500;" title="<?php echo htmlspecialchars($tkt['asset']); ?>">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line></svg>
                                    <?php echo htmlspecialchars($tkt['asset']); ?>
                                </span>
                            </td>

                            <!-- 6. Status -->
                            <td class="col-ticket-status">
                                <span class="badge <?php echo $tkt['s_class']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($tkt['status']); ?>
                                </span>
                            </td>

                            <!-- 7. Actions -->
                            <td style="text-align: right; padding-right: 20px;">
                                <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;"
                                    onclick='openAdminTicketDrawer(<?php echo json_encode($tkt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    title="View Details & Chat with Requester">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    <span>Manage & Chat</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Empty State -->
            <div id="ticketsEmptyState" style="display: none; padding: 48px 20px; text-align: center; color: var(--text-muted);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #cbd5e1; margin-bottom: 12px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <div style="font-size: 14.5px; font-weight: 700; color: var(--navy-primary);">No Matching Tickets Found</div>
                <div style="font-size: 12.5px; margin-top: 4px;">Adjust your filters or search keywords above.</div>
            </div>
        </div>
    </div>

    <!-- Pagination Footer (exact .assets-pagination-card from assets.php) -->
    <div class="assets-pagination-card">
        <div class="pagination-info" id="paginationInfo">
            Showing <strong>1</strong> to <strong><?php echo $totalCount; ?></strong> of <strong><?php echo $totalCount; ?></strong> support tickets
        </div>
        <div class="pagination-controls" id="paginationControls">
            <button type="button" class="page-btn disabled" disabled>&laquo;</button>
            <button type="button" class="page-btn active">1</button>
            <button type="button" class="page-btn disabled" disabled>&raquo;</button>
        </div>
    </div>

</main>


<!-- =========================================================================
     SLIDE-OVER DRAWER (Asset Drawer Architecture from assets.php)
     ========================================================================= -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeAdminTicketDrawer()"></div>

<aside class="asset-drawer" id="adminTicketDrawer">
    <!-- Drawer Header (assets.php style) -->
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerTicketId" style="font-size: 12px; font-weight: 700;">TKT10261104</span>
                    <span class="badge badge-status-progress" id="drawerTicketStatusBadge">In Progress</span>
                    <span class="serial-badge" id="drawerTicketDateBadge" style="font-size: 11.5px;">09 Oct 2026, 02:30 PM</span>
                </div>
                <h3 id="drawerTicketSubject" style="margin-top: 6px; font-size: 16px; font-weight: 700; color: var(--navy-primary); line-height: 1.35;">
                    Ticket Subject
                </h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" onclick="closeAdminTicketDrawer()" title="Close Drawer">&times;</button>
    </div>

    <!-- Drawer Navigation Tabs (assets.php tab architecture) -->
    <div class="drawer-tabs">
        <button type="button" class="drawer-tab active" data-tab="overview" onclick="switchDrawerTab('overview')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Overview & Triage
        </button>
        <button type="button" class="drawer-tab" data-tab="chat" onclick="switchDrawerTab('chat')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            Support Conversation <span id="drawerChatCountBadge" style="margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">2</span>
        </button>
    </div>

    <!-- Drawer Content Panes -->
    <div class="drawer-content" style="flex: 1; overflow-y: auto; padding: 22px;">

        <!-- PANE 1: Overview & Triage (active by default) -->
        <div class="drawer-tab-pane active" id="pane_overview" style="display: block;">
            
            <!-- Requester Profile Section (.drawer-section from assets.php) -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Employee / Requester Profile
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Requester Name</div>
                        <div class="value" id="drawerReqName" style="font-weight: 700; color: var(--navy-primary);">Rajesh Sharma</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Employee Code</div>
                        <div class="value" id="drawerReqCode" style="font-family: monospace;">EMP-104</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Department</div>
                        <div class="value" id="drawerReqDept">Operations</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Office Location</div>
                        <div class="value" id="drawerReqLocation">HQ Building - Floor 3</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Official Email</div>
                        <div class="value" id="drawerReqEmail">rajesh.sharma@viros.in</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Contact Phone</div>
                        <div class="value" id="drawerReqPhone">+91 98765 43210</div>
                    </div>
                </div>
            </div>

            <!-- Incident & Device Attributes (.drawer-section) -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    Incident & Device Attributes
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Current Status</div>
                        <div class="value"><span class="badge badge-status-progress" id="drawerSpecStatus">In Progress</span></div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Urgency Priority</div>
                        <div class="value"><span class="badge badge-high" id="drawerSpecUrgency">High</span></div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Affected Device</div>
                        <div class="value" id="drawerSpecAsset" style="font-weight: 600; color: var(--navy-primary);">Dell Latitude 5420 (AST2026001)</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Incident Category</div>
                        <div class="value" id="drawerSpecCategory">Hardware / Thermal & Battery</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Reported Timestamp</div>
                        <div class="value" id="drawerSpecDate">09 Oct 2026, 02:30 PM</div>
                    </div>
                </div>
            </div>

            <!-- Full Issue Description (.drawer-section) -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Detailed Incident Description
                </div>
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 14px 16px; font-size: 13px; line-height: 1.6; color: var(--text-primary); white-space: pre-wrap; word-break: break-word;" id="drawerFullDesc">
                    Description goes here...
                </div>
            </div>

            <!-- Administrative Triage Controls (.drawer-section) -->
            <div class="drawer-section" style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px;">
                <div class="drawer-section-title" style="color: #1e40af; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Administrative Triage Actions
                    </div>
                    <span style="font-size: 11px; color: #3b82f6; font-weight: 600;">Live Action</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label for="triageStatusSelect" style="display: block; font-size: 11.5px; font-weight: 600; color: #1e3a8a; margin-bottom: 4px;">Update Status</label>
                        <select class="asset-filter-select" id="triageStatusSelect" style="width: 100%; border-color: #93c5fd; font-size: 12.5px;">
                            <option value="open">Open / Triage</option>
                            <option value="in-progress">In Progress</option>
                            <option value="resolved">Resolved & Closed</option>
                        </select>
                    </div>

                    <div>
                        <label for="triagePrioritySelect" style="display: block; font-size: 11.5px; font-weight: 600; color: #1e3a8a; margin-bottom: 4px;">Escalate Urgency</label>
                        <select class="asset-filter-select" id="triagePrioritySelect" style="width: 100%; border-color: #93c5fd; font-size: 12.5px;">
                            <option value="Urgent">Urgent (Immediate SLA)</option>
                            <option value="High">High Priority</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low Priority</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn-primary" id="btnUpdateAdminTriage" style="padding: 7px 16px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Save & Update Triage</span>
                </button>
            </div>

        </div>

        <!-- PANE 2: Support Conversation Thread (hidden by default) -->
        <div class="drawer-tab-pane" id="pane_chat" style="display: none;">
            <div class="ticket-chat-container">
                <div class="ticket-chat-header">
                    <div class="ticket-chat-header-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <span>Support Conversation & Updates</span>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 5px;">
                        <span style="color: #10b981; font-size: 10px;">●</span> Active Thread
                    </div>
                </div>

                <!-- Scrollable Chat Stream (with real date & time) -->
                <div class="ticket-chat-stream" id="adminChatStream" style="max-height: 420px; min-height: 260px; overflow-y: auto; padding: 18px 16px; display: flex; flex-direction: column; gap: 16px; background: #ffffff;">
                    <!-- Populated dynamically via js/tickets.js -->
                </div>

                <!-- Chat Bottom Input Bar for Admin -->
                <div class="chat-input-container" style="padding: 12px 16px; background: #f8fafc; border-top: 1px solid var(--border-color);">
                    <form id="adminDrawerChatForm" style="display: flex; gap: 10px; align-items: center; background: #ffffff; border: 1.5px solid var(--border-color); border-radius: 8px; padding: 5px 8px 5px 14px;">
                        <input type="text" id="adminDrawerChatInput" required placeholder="Reply to employee as IT Administrator..." style="flex: 1; border: none; outline: none; font-size: 13px; color: var(--navy-primary); background: transparent;">
                        <button type="submit" class="btn-primary" style="padding: 8px 18px; border-radius: 6px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            <span>Send Reply</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Drawer Footer (matching assets.php) -->
    <div style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: #f8fafc; flex-shrink: 0;">
        <span style="font-size: 11.5px; color: var(--text-muted);">VIROS Enterprise IT Service Desk Engine</span>
        <button type="button" class="btn-secondary" onclick="closeAdminTicketDrawer()" style="padding: 8px 20px;">Close Drawer</button>
    </div>
</aside>

<?php
// Layout Footer
include 'includes/footer.php';
?>
