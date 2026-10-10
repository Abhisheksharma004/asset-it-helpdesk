<?php
// Asset Management & IT Service Desk Portal - Dedicated Employee Support Tickets Page (UI)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title  = "My IT Support Tickets - VIROS IT Portal";
$active_page = "employee_tickets";
$extra_css   = ['css/employee_dashboard.css', 'css/employee_tickets.css'];
$extra_js    = ['js/employee_dashboard.js', 'js/employee_tickets.js'];

// Include Database Configuration
require_once __DIR__ . '/config/db.php';

// Dynamically resolve target employee (Session or GET request or Top 1 in DB)
$targetEmpId   = !empty($_GET['id']) ? intval($_GET['id']) : (!empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);
$targetEmpMail = !empty($_GET['email']) ? trim($_GET['email']) : (!empty($_SESSION['user_email']) ? trim($_SESSION['user_email']) : '');
$targetEmpCode = !empty($_GET['emp']) ? trim($_GET['emp']) : (!empty($_SESSION['user_username']) ? trim($_SESSION['user_username']) : '');

$activeEmployee = null;

if (isset($conn) && $conn !== false) {
    $currStmt = null;

    if ($targetEmpId > 0 || !empty($targetEmpMail) || !empty($targetEmpCode)) {
        $currStmt = sqlsrv_query(
            $conn, 
            "SELECT e.*, d.department_name, l.location_name 
             FROM employees e 
             LEFT JOIN departments d ON e.department_id = d.id 
             LEFT JOIN locations l ON e.location_id = l.id 
             WHERE e.id = ? OR LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)", 
            [$targetEmpId, $targetEmpMail, $targetEmpCode]
        );
    }

    if ($currStmt && ($row = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC))) {
        $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $activeEmployee = [
            'id'           => $row['id'],
            'name'         => $fullName ?: 'Employee Member',
            'code'         => $row['emp_code'] ?? 'EMP-001',
            'designation'  => !empty($row['designation']) ? $row['designation'] : 'Staff Member',
            'department'   => !empty($row['department_name']) ? $row['department_name'] : 'General Staff',
            'email'        => !empty($row['email']) ? $row['email'] : 'employee@viros.in',
            'phone'        => !empty($row['phone']) ? $row['phone'] : 'Not Provided',
            'location'     => !empty($row['location_name']) ? $row['location_name'] : 'Corporate HQ',
            'status'       => $row['status'] ?? 'Active'
        ];
        sqlsrv_free_stmt($currStmt);
    } else {
        $fallbackStmt = sqlsrv_query($conn, "SELECT TOP 1 e.*, d.department_name, l.location_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN locations l ON e.location_id = l.id WHERE e.status = 'Active' ORDER BY e.id ASC");
        if ($fallbackStmt && ($fb = sqlsrv_fetch_array($fallbackStmt, SQLSRV_FETCH_ASSOC))) {
            $fullName = trim(($fb['first_name'] ?? '') . ' ' . ($fb['last_name'] ?? ''));
            $activeEmployee = [
                'id'           => $fb['id'],
                'name'         => $fullName ?: 'Employee Member',
                'code'         => $fb['emp_code'] ?? 'EMP-001',
                'designation'  => !empty($fb['designation']) ? $fb['designation'] : 'Staff Member',
                'department'   => !empty($fb['department_name']) ? $fb['department_name'] : 'General Staff',
                'email'        => !empty($fb['email']) ? $fb['email'] : 'employee@viros.in',
                'phone'        => !empty($fb['phone']) ? $fb['phone'] : 'Not Provided',
                'location'     => !empty($fb['location_name']) ? $fb['location_name'] : 'Corporate HQ',
                'status'       => $fb['status'] ?? 'Active'
            ];
            sqlsrv_free_stmt($fallbackStmt);
        }
    }
}

// Fallback in case database was unreachable
if (!$activeEmployee) {
    $activeEmployee = [
        'id'           => 1,
        'name'         => $_SESSION['user_name'] ?? 'Employee Member',
        'code'         => $_SESSION['user_username'] ?? 'EMP-001',
        'designation'  => 'Staff Member',
        'department'   => $_SESSION['user_dept'] ?? 'General Staff',
        'email'        => $_SESSION['user_email'] ?? 'employee@viros.in',
        'phone'        => 'Not Provided',
        'location'     => 'Corporate Office',
        'status'       => 'Active'
    ];
}

// Fetch Employee Custody Assets for the "Affected Asset" selector in New Ticket Modal
$employeeAssets = [];
if (isset($conn) && $conn !== false && !empty($activeEmployee['id'])) {
    $assignSql = "SELECT a.asset_tag, a.asset_name, a.category 
                  FROM asset_assignments a 
                  WHERE (a.employee_id = ? OR LOWER(a.employee_email) = LOWER(?) OR LOWER(a.emp_code) = LOWER(?))
                    AND a.custody_status = 'Active'";
    $assignStmt = sqlsrv_query($conn, $assignSql, [$activeEmployee['id'], $activeEmployee['email'], $activeEmployee['code']]);
    if ($assignStmt) {
        while ($ar = sqlsrv_fetch_array($assignStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($ar['asset_tag'])) {
                $employeeAssets[] = [
                    'tag'  => $ar['asset_tag'],
                    'name' => $ar['asset_name'] ?: 'Corporate Device'
                ];
            }
        }
        sqlsrv_free_stmt($assignStmt);
    }
}

// Support Tickets UI Data (Curated IT incident & service requests for this employee)
$supportTickets = [
    [
        'id'        => 'TKT-2026-1104',
        'subject'   => 'Laptop battery draining rapidly and heating during Teams meetings',
        'category'  => 'Hardware / Thermal & Battery',
        'asset'     => !empty($employeeAssets[0]['name']) ? ($employeeAssets[0]['name'] . ' (' . $employeeAssets[0]['tag'] . ')') : 'Dell Latitude 5420 (AST2026001)',
        'priority'  => 'High',
        'p_class'   => 'badge-high',
        'status'    => 'In Progress',
        's_class'   => 'badge-status-progress',
        's_filter'  => 'in-progress',
        'date'      => '09 Oct 2026',
        'tech'      => 'Rajesh Verma (Hardware Support)',
        'tech_init' => 'RV',
        'description' => 'Battery drops from 100% to 20% in less than 45 minutes of video calls. Fan stays on continuously. Diagnostic requested for thermal paste or battery replacement.'
    ],
    [
        'id'        => 'TKT-2026-1082',
        'subject'   => 'External monitor HDMI signal flickering after workstation standby',
        'category'  => 'Hardware / External Display',
        'asset'     => 'Dell UltraSharp 24" (AST2026048)',
        'priority'  => 'Medium',
        'p_class'   => 'badge-medium',
        'status'    => 'In Progress',
        's_class'   => 'badge-status-progress',
        's_filter'  => 'in-progress',
        'date'      => '08 Oct 2026',
        'tech'      => 'Deepak Patel (IT Helpdesk)',
        'tech_init' => 'DP',
        'description' => 'Whenever laptop wakes from sleep/standby, the secondary HDMI monitor flickers black for 5 seconds before returning to normal. Cable has been reseated once.'
    ],
    [
        'id'        => 'TKT-2026-1045',
        'subject'   => 'Request for USB-C Multiport Display Adapter for meeting room',
        'category'  => 'Peripherals / Cables & Docks',
        'asset'     => 'Workstation Peripheral Accessory',
        'priority'  => 'Low',
        'p_class'   => 'badge-low',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '12 Sep 2026',
        'tech'      => 'IT Procurement Team',
        'tech_init' => 'IP',
        'description' => 'Need USB-C to HDMI/VGA adapter to connect laptop to conference room projector for client presentations.'
    ],
    [
        'id'        => 'TKT-2026-0988',
        'subject'   => 'VPN Client failing with TLS handshake timeout on home broadband',
        'category'  => 'Network & Remote Access',
        'asset'     => 'GlobalProtect Enterprise VPN Gateway',
        'priority'  => 'Medium',
        'p_class'   => 'badge-medium',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '04 Aug 2026',
        'tech'      => 'Neha Gupta (Network Admin)',
        'tech_init' => 'NG',
        'description' => 'VPN gateway Bangalore-HQ was failing to authenticate on Airtel home fiber. Resolved after MTU adjustment and DNS cache flush.'
    ],
    [
        'id'        => 'TKT-2026-0912',
        'subject'   => 'Dual-Factor Authentication (2FA) reset on corporate phone change',
        'category'  => 'Identity & Access Management',
        'asset'     => 'Microsoft Authenticator SSO',
        'priority'  => 'Urgent',
        'p_class'   => 'badge-urgent',
        'status'    => 'Resolved & Closed',
        's_class'   => 'badge-status-resolved',
        's_filter'  => 'resolved',
        'date'      => '18 Jun 2026',
        'tech'      => 'Security Operations Team',
        'tech_init' => 'SO',
        'description' => 'Migrated to new corporate mobile device. Need temporary bypass code to register Microsoft Authenticator application.'
    ]
];

// Compute summary counters
$totalTicketsCount   = count($supportTickets);
$inProgressCount     = count(array_filter($supportTickets, fn($t) => $t['s_filter'] === 'in-progress'));
$resolvedCount       = count(array_filter($supportTickets, fn($t) => $t['s_filter'] === 'resolved'));
$urgentHighCount     = count(array_filter($supportTickets, fn($t) => in_array($t['priority'], ['Urgent', 'High'])));

// Layout Components
include 'includes/header.php';
include 'includes/employee_sidebar.php';
include 'includes/employee_topbar.php';
?>

<!-- Dedicated Support Tickets Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb Navigation -->
    <nav class="tickets-breadcrumb">
        <a href="employee_dashboard.php">Home</a>
        <span>/</span>
        <a href="employee_dashboard.php">My Dashboard</a>
        <span>/</span>
        <span class="current">My Support Tickets</span>
    </nav>

    <!-- Top Header Bar with Action -->
    <div class="tickets-header-bar">
        <div class="tickets-header-title">
            <h1>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
                My IT Support Tickets
            </h1>
            <p>
                Track, report, and manage your IT equipment issues and technical assistance requests.
            </p>
        </div>

        <button type="button" class="btn-new-ticket" onclick="openEmployeeTicketModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Raise New Ticket
        </button>
    </div>

    <!-- Metric Summary Stats Row (SVG Icons Only) -->
    <div class="tickets-stats-grid">
        <div class="tickets-stat-card">
            <div class="tickets-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <div class="tickets-stat-text">
                <div class="val" id="statTotalTickets"><?php echo $totalTicketsCount; ?></div>
                <div class="lbl">Total Support Tickets</div>
            </div>
        </div>

        <div class="tickets-stat-card">
            <div class="tickets-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="tickets-stat-text">
                <div class="val" id="statOpenTickets"><?php echo $inProgressCount; ?></div>
                <div class="lbl">Active & In Progress</div>
            </div>
        </div>

        <div class="tickets-stat-card">
            <div class="tickets-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="tickets-stat-text">
                <div class="val"><?php echo $resolvedCount; ?></div>
                <div class="lbl">Resolved & Closed</div>
            </div>
        </div>

        <div class="tickets-stat-card">
            <div class="tickets-stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <div class="tickets-stat-text">
                <div class="val">4.2h</div>
                <div class="lbl">Average First Response</div>
            </div>
        </div>
    </div>

    <!-- Main Tickets Management Card -->
    <div class="tickets-main-card">
        <div class="tickets-card-header">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
                Registered Support Tickets & Service Requests
            </h2>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="tickets-toolbar">
            <div class="tickets-filter-pills">
                <button type="button" class="tickets-pill-btn active" data-status="all">
                    All Tickets <span class="tickets-pill-count"><?php echo $totalTicketsCount; ?></span>
                </button>
                <button type="button" class="tickets-pill-btn" data-status="in-progress">
                    In Progress <span class="tickets-pill-count"><?php echo $inProgressCount; ?></span>
                </button>
                <button type="button" class="tickets-pill-btn" data-status="resolved">
                    Resolved & Closed <span class="tickets-pill-count"><?php echo $resolvedCount; ?></span>
                </button>
            </div>

            <div class="tickets-search-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" class="tickets-search-input" id="ticketSearchInput" placeholder="Search by Ticket ID, Subject, Technician or Asset...">
            </div>
        </div>

        <!-- Responsive Tickets Table -->
        <div class="table-responsive">
            <table class="custom-table" id="empTicketsTable">
                <thead>
                    <tr>
                        <th style="width: 12%;">Ticket ID</th>
                        <th style="width: 28%;">Issue Subject & Category</th>
                        <th style="width: 20%;">Associated Asset</th>
                        <th style="width: 9%;">Priority</th>
                        <th style="width: 11%;">Status</th>
                        <th style="width: 10%;">Logged Date</th>
                        <th style="width: 10%; text-align: right; padding-right: 24px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supportTickets as $tkt): ?>
                        <tr class="emp-ticket-row" data-status="<?php echo $tkt['s_filter']; ?>">
                            <td>
                                <span class="ticket-id-pill"><?php echo htmlspecialchars($tkt['id']); ?></span>
                            </td>

                            <td>
                                <div class="ticket-subject-title">
                                    <?php echo htmlspecialchars($tkt['subject']); ?>
                                </div>
                                <div class="ticket-meta-subtitle">
                                    <span><?php echo htmlspecialchars($tkt['category']); ?></span>
                                </div>
                            </td>

                            <td>
                                <span class="ticket-asset-pill">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line></svg>
                                    <?php echo htmlspecialchars($tkt['asset']); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo $tkt['p_class']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($tkt['priority']); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo $tkt['s_class']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($tkt['status']); ?>
                                </span>
                            </td>

                            <td>
                                <span style="font-size: 12.5px; font-weight: 500; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($tkt['date']); ?>
                                </span>
                            </td>

                            <td style="text-align: right; padding-right: 24px;">
                                <button type="button" class="btn-ticket-view" 
                                    onclick='viewTicketDetails(<?php echo json_encode($tkt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    title="View Ticket Details and Activity Trail">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Empty Search State -->
            <div id="ticketsEmptyState" class="assets-empty-state" style="display: none; padding: 48px 20px; text-align: center; color: var(--text-muted);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #cbd5e1; margin-bottom: 12px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <div style="font-size: 14px; font-weight: 700; color: var(--navy-primary);">No Matching Support Tickets Found</div>
                <div style="font-size: 12px; margin-top: 4px;">Try searching with another keyword or adjust your status filter above.</div>
            </div>
        </div>
    </div>

</main>


<!-- Ticket Details & Conversation Timeline Modal -->
<div class="modal-overlay" id="ticketDetailsModal" style="display: none; z-index: 9999;">
    <div class="modal-box" style="max-width: 650px; border-top: 4px solid var(--cyan-primary);">
        <div class="modal-header" style="padding: 18px 24px 14px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="ticket-id-pill" id="detTicketId" style="font-size: 13px; padding: 4px 10px;">TKT-2026-1082</span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--navy-primary);" id="detTicketSubject">
                        Ticket Subject
                    </h3>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('ticketDetailsModal')">&times;</button>
        </div>

        <div class="modal-body" style="padding: 24px; max-height: 75vh; overflow-y: auto;">
            
            <!-- Quick Meta Attributes Bar -->
            <div style="display: flex; gap: 14px; flex-wrap: wrap; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
                <div style="flex: 1; min-width: 120px;">
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Status</div>
                    <div style="margin-top: 4px;"><span class="badge badge-status-progress" id="detTicketStatus">In Progress</span></div>
                </div>
                <div style="flex: 1; min-width: 120px;">
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Priority</div>
                    <div style="margin-top: 4px;"><span class="badge badge-medium" id="detTicketPriority">Medium</span></div>
                </div>
                <div style="flex: 1; min-width: 140px;">
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Logged Date</div>
                    <div style="font-size: 13px; font-weight: 700; color: var(--navy-primary); margin-top: 4px;" id="detTicketDate">08 Oct 2026</div>
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Assigned Engineer</div>
                    <div style="font-size: 12.5px; font-weight: 700; color: var(--navy-primary); margin-top: 4px;" id="detTicketTech">Deepak Patel (IT)</div>
                </div>
            </div>

            <!-- Affected Device -->
            <div style="margin-bottom: 18px;">
                <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;">Affected Device / Service: </span>
                <span class="ticket-asset-pill" id="detTicketAsset">Dell UltraSharp 24" (AST2026048)</span>
            </div>

            <!-- Lifecycle Activity Stepper Timeline -->
            <div style="font-size: 13px; font-weight: 700; color: var(--navy-primary); margin-bottom: 10px;">
                Service Desk Progress Trail
            </div>

            <div class="ticket-timeline" id="ticketActivityList">
                <div class="timeline-item completed">
                    <div class="timeline-dot">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <div class="timeline-content">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="timeline-title">Ticket Registered by Employee</span>
                            <span class="timeline-time">System Auto-Ack</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                            Incident ticket successfully generated and assigned to Tier-1 Service Desk Queue.
                        </div>
                    </div>
                </div>

                <div class="timeline-item completed">
                    <div class="timeline-dot">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <div class="timeline-content">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="timeline-title">Assigned to IT Support Engineer</span>
                            <span class="timeline-time">within 18 mins</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                            Assigned to technician for initial diagnostics and hardware verification.
                        </div>
                    </div>
                </div>

                <div class="timeline-item active">
                    <div class="timeline-dot">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <div class="timeline-content">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="timeline-title">Diagnostics In Progress</span>
                            <span class="timeline-time">Recent</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                            Technician investigating driver conflict and replacement cable testing.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Post Reply or Update Note -->
            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                <div style="font-size: 13px; font-weight: 700; color: var(--navy-primary); margin-bottom: 8px;">
                    Add Note or Respond to Technician
                </div>
                <form id="ticketReplyForm">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="ticketReplyInput" required placeholder="Type additional info or update for the IT technician..." style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px; outline: none;">
                        <button type="submit" class="btn-primary" style="padding: 10px 18px; font-size: 12.5px; white-space: nowrap;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            <span>Send Note</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: #f8fafc;">
            <button type="button" class="btn-secondary" onclick="closeModal('ticketDetailsModal')">Close</button>
        </div>
    </div>
</div>

<?php
// Layout Footer
include 'includes/footer.php';
?>
