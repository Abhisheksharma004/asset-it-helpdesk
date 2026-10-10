<?php
// Asset Management & IT Service Desk Portal - Dedicated Employee Support Tickets Page (UI)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');

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

// Fetch Dynamic Support Tickets from MS SQL Server Database for THIS active employee ONLY
$supportTickets = [];
if (isset($conn) && $conn !== false && !empty($activeEmployee)) {
    $empId   = intval($activeEmployee['id'] ?? 0);
    $empCode = trim($activeEmployee['code'] ?? '');
    $empName = trim($activeEmployee['name'] ?? '');

    $whereEmp = [];
    $paramsEmp = [];

    if ($empId > 0) {
        $whereEmp[] = "employee_id = ?";
        $paramsEmp[] = $empId;
    }
    if (!empty($empCode)) {
        $whereEmp[] = "LOWER(emp_code) = LOWER(?)";
        $paramsEmp[] = $empCode;
    }
    if (!empty($empName)) {
        $whereEmp[] = "LOWER(employee_name) = LOWER(?)";
        $paramsEmp[] = $empName;
    }

    if (!empty($whereEmp)) {
        $whereSql = '(' . implode(' OR ', $whereEmp) . ')';
        $tSql = "SELECT * FROM support_tickets WHERE {$whereSql} ORDER BY incident_date DESC, id DESC";
        $tStmt = sqlsrv_query($conn, $tSql, $paramsEmp);
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

                $chatList = json_decode($row['chat_history'] ?? '[]', true) ?: [];

                $supportTickets[] = [
                    'id'          => $row['ticket_no'],
                    'ticket_no'   => $row['ticket_no'],
                    'subject'     => $row['subject'],
                    'category'    => $row['category'] ?: 'General IT Support',
                    'asset'       => $row['asset_name'] ?: 'General Workstation / Laptop',
                    'priority'    => $prio,
                    'p_class'     => $pClass,
                    'status'      => $st,
                    's_class'     => $sClass,
                    's_filter'    => $sFilter,
                    'date'        => $formattedDate,
                    'description' => $row['description'] ?? '',
                    'chat_thread' => $chatList
                ];
            }
            sqlsrv_free_stmt($tStmt);
        }

        // Populate conversation threads from dedicated ticket_messages table
        if (!empty($supportTickets)) {
            $ticketNos = array_column($supportTickets, 'ticket_no');
            if (!empty($ticketNos)) {
                $uniqueNos = array_values(array_unique(array_filter($ticketNos)));
                $placeholders = implode(',', array_fill(0, count($uniqueNos), '?'));
                $msgSql = "SELECT id, ticket_no, sender_type, sender_name, sender_avatar, message, created_at 
                           FROM ticket_messages 
                           WHERE ticket_no IN ($placeholders) 
                           ORDER BY created_at ASC, id ASC";
                $msgStmt = sqlsrv_query($conn, $msgSql, $uniqueNos);
                $msgMap = [];
                if ($msgStmt) {
                    while ($mr = sqlsrv_fetch_array($msgStmt, SQLSRV_FETCH_ASSOC)) {
                        $tNo = $mr['ticket_no'];
                        if (!isset($msgMap[$tNo])) $msgMap[$tNo] = [];
                        $dt = $mr['created_at'];
                        $timeStr = ($dt instanceof DateTime) ? $dt->format('d M Y, h:i A') : (empty($dt) ? '' : date('d M Y, h:i A', strtotime($dt)));
                        $msgMap[$tNo][] = [
                            'id'     => $mr['id'],
                            'author' => $mr['sender_name'],
                            'avatar' => $mr['sender_avatar'] ?: strtoupper(substr($mr['sender_name'] ?? 'US', 0, 2)),
                            'type'   => $mr['sender_type'],
                            'time'   => $timeStr,
                            'text'   => $mr['message']
                        ];
                    }
                    sqlsrv_free_stmt($msgStmt);
                }
                foreach ($supportTickets as &$st) {
                    $tNo = $st['ticket_no'];
                    if (isset($msgMap[$tNo]) && !empty($msgMap[$tNo])) {
                        $st['chat_thread'] = $msgMap[$tNo];
                    }
                }
                unset($st);
            }
        }
    }
}

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
                        <th style="width: 15%;">Ticket ID & Date</th>
                        <th style="width: 33%;">Issue Subject & Description</th>
                        <th style="width: 11%;">Urgency Level</th>
                        <th style="width: 19%;">Affected Asset / Device</th>
                        <th style="width: 11%;">Status</th>
                        <th style="width: 11%; text-align: right; padding-right: 24px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supportTickets as $tkt): ?>
                        <tr class="emp-ticket-row" data-status="<?php echo $tkt['s_filter']; ?>">
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="ticket-id-pill" style="align-self: flex-start;"><?php echo htmlspecialchars($tkt['id']); ?></span>
                                    <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; white-space: nowrap;">
                                        <?php echo htmlspecialchars($tkt['date']); ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="ticket-subject-title">
                                    <?php echo htmlspecialchars($tkt['subject']); ?>
                                </div>
                                <?php if (!empty($tkt['description'])): ?>
                                    <div class="ticket-meta-desc" title="<?php echo htmlspecialchars($tkt['description']); ?>">
                                        <?php echo htmlspecialchars($tkt['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge <?php echo $tkt['p_class']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($tkt['priority']); ?>
                                </span>
                            </td>

                            <td>
                                <span class="ticket-asset-pill">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line></svg>
                                    <?php echo htmlspecialchars($tkt['asset'] ?: 'General Workstation / Laptop'); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo $tkt['s_class']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                    <?php echo htmlspecialchars($tkt['status']); ?>
                                </span>
                            </td>

                            <td style="text-align: right; padding-right: 24px;">
                                <button type="button" class="btn-ticket-view" 
                                    onclick='viewTicketDetails(<?php echo json_encode($tkt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    title="View Ticket Details & Chat with IT Support">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    <span>View & Chat</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Empty State -->
            <div id="ticketsEmptyState" class="assets-empty-state" style="<?php echo empty($supportTickets) ? 'display: block;' : 'display: none;'; ?> padding: 48px 20px; text-align: center; color: var(--text-muted);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #cbd5e1; margin-bottom: 12px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <div style="font-size: 14px; font-weight: 700; color: var(--navy-primary);">No Support Tickets Found</div>
                <div style="font-size: 12px; margin-top: 4px;">
                    <?php echo empty($supportTickets) ? 'You have not logged any support tickets yet. Click "Raise New Ticket" above to report an issue.' : 'Try searching with another keyword or adjust your status filter above.'; ?>
                </div>
            </div>
        </div>
    </div>

</main>


<!-- =========================================================================
     SLIDE-OVER DRAWER: TICKET DETAILS & PROGRESS TIMELINE (LIKE ASSETS.PHP)
     ========================================================================= -->
<div class="drawer-backdrop" id="ticketDetailsBackdrop" onclick="closeTicketDetailsDrawer()"></div>

<aside class="ticket-details-drawer" id="ticketDetailsModal">
    <div class="drawer-header" style="padding: 20px 24px; border-bottom: 1px solid var(--border-color); border-top: 4px solid var(--cyan-primary); display: flex; align-items: flex-start; justify-content: space-between; background: #ffffff; flex-shrink: 0;">
        <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1; padding-right: 12px;">
            <span class="ticket-id-pill" id="detTicketId" style="font-size: 13px; padding: 4px 10px; font-weight: 700; white-space: nowrap;">--</span>
            <div>
                <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--navy-primary); line-height: 1.35;" id="detTicketSubject">
                    Ticket Subject
                </h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" onclick="closeTicketDetailsDrawer()" title="Close Drawer">&times;</button>
    </div>

    <div class="drawer-body" style="flex: 1; overflow-y: auto; padding: 24px;">
        
        <!-- Quick Meta Attributes Bar (Single Unified Compact Section) -->
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 9px 14px; margin-bottom: 16px; font-size: 12px;">
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Status:</span>
                <span class="badge badge-status-progress" id="detTicketStatus" style="font-size: 11px; padding: 2px 8px;">--</span>
            </div>
            <span style="color: #cbd5e1; font-size: 12px;">•</span>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Urgency:</span>
                <span class="badge badge-medium" id="detTicketPriority" style="font-size: 11px; padding: 2px 8px;">--</span>
            </div>
            <span style="color: #cbd5e1; font-size: 12px;">•</span>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Incident:</span>
                <span style="font-size: 12px; font-weight: 700; color: var(--navy-primary);" id="detTicketDate">--</span>
            </div>
            <span style="color: #cbd5e1; font-size: 12px;">•</span>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Device:</span>
                <span class="ticket-asset-pill" id="detTicketAsset" style="font-weight: 600; font-size: 11.5px; padding: 2px 8px;">--</span>
            </div>
        </div>

        <!-- Detailed Description Box (Prominent & Full View) -->
        <div style="margin-bottom: 22px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 16px;">
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Detailed Description
            </div>
            <div style="font-size: 13.5px; color: var(--text-primary); line-height: 1.6; white-space: pre-wrap; word-break: break-word;" id="detTicketDescription">
                Description details...
            </div>
        </div>

        <!-- Support Conversation & Activity Trail (Chat Design with Date & Time) -->
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

            <!-- Scrollable Chat Stream (Dynamically populated from ticket chat thread) -->
            <div class="ticket-chat-stream" id="ticketActivityList">
                <!-- System Registered Event Bubble -->
                <div class="chat-system-event">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span id="chatSystemAckText">Ticket Logged & Auto-Acknowledged by IT Service Desk Queue</span>
                </div>
            </div>

            <!-- Chat Bottom Input Bar -->
            <div class="chat-input-container">
                <form id="ticketReplyForm" style="display: flex; gap: 10px; align-items: center; background: #f8fafc; border: 1.5px solid var(--border-color); border-radius: 8px; padding: 5px 8px 5px 14px; transition: border-color 0.2s;">
                    <input type="text" id="ticketReplyInput" required placeholder="Type a message or update for IT technician..." style="flex: 1; border: none; outline: none; font-size: 13px; color: var(--navy-primary); background: transparent;">
                    <button type="submit" class="btn-primary" style="padding: 8px 18px; border-radius: 6px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        <span>Send</span>
                    </button>
                </form>
            </div>
        </div>

    </div>

    <div class="drawer-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: #f8fafc; flex-shrink: 0;">
        <button type="button" class="btn-secondary" onclick="closeTicketDetailsDrawer()" style="padding: 9px 22px;">Close</button>
    </div>
</aside>

<script>
window.currentActiveEmployee = <?php echo json_encode($activeEmployee, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php
// Layout Footer
include 'includes/footer.php';
?>
