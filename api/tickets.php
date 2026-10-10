<?php
/**
 * VIROS IT Asset & Service Desk - Support Tickets Backend API
 * Handles database CRUD operations, dynamic ticket number generation (prefix + MMYY + serial),
 * incident timestamps, chat conversations, and status triage.
 */

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// -----------------------------------------------------------------------------
// 1. Ensure Table Schema Exists (support_tickets Table)
// -----------------------------------------------------------------------------
$tableSetupSql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='support_tickets' AND xtype='U')
BEGIN
    CREATE TABLE support_tickets (
        id INT IDENTITY(1,1) PRIMARY KEY,
        ticket_no NVARCHAR(50) NOT NULL UNIQUE,
        subject NVARCHAR(255) NOT NULL,
        description NVARCHAR(MAX) NULL,
        category NVARCHAR(150) NULL,
        priority NVARCHAR(50) NOT NULL DEFAULT 'Medium',
        status NVARCHAR(50) NOT NULL DEFAULT 'Open',
        asset_name NVARCHAR(255) NULL,
        employee_id INT NULL,
        employee_name NVARCHAR(150) NULL,
        emp_code NVARCHAR(50) NULL,
        department NVARCHAR(150) NULL,
        incident_date DATETIME NOT NULL DEFAULT GETDATE(),
        chat_history NVARCHAR(MAX) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";
sqlsrv_query($conn, $tableSetupSql);

// -----------------------------------------------------------------------------
// 2. Auto-seed with dynamic initial records if table is completely empty
// -----------------------------------------------------------------------------
$countStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM support_tickets");
$curCount = 0;
if ($countStmt && ($cntRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
    $curCount = intval($cntRow['total']);
}

if ($curCount === 0) {
    $now = new DateTime();
    $mmyy = $now->format('my'); // e.g. 1026 for Oct 2026

    // Sample dynamic seeded incidents
    $seedData = [
        [
            'ticket_no'     => 'TKT' . $mmyy . '1104',
            'subject'       => 'Laptop battery draining rapidly and heating during Teams meetings',
            'category'      => 'Hardware / Thermal & Battery',
            'priority'      => 'High',
            'status'        => 'In Progress',
            'asset_name'    => 'Dell Latitude 5420 (AST2026001)',
            'employee_name' => 'Rajesh Sharma',
            'emp_code'      => 'EMP-104',
            'department'    => 'Operations',
            'incident_date' => (clone $now)->modify('-4 hours')->format('Y-m-d H:i:s'),
            'description'   => 'Battery drops from 100% to 20% in less than 45 minutes of video calls. Fan stays on continuously. Diagnostic requested for thermal paste or battery replacement.',
            'chat_history'  => json_encode([
                [
                    'author' => 'Rajesh Sharma',
                    'avatar' => 'RS',
                    'type'   => 'employee',
                    'time'   => (clone $now)->modify('-4 hours')->format('d M Y, h:i A'),
                    'text'   => 'Fan noise gets extremely loud whenever video camera is active.'
                ],
                [
                    'author' => 'IT Support Service Desk',
                    'avatar' => 'IT',
                    'type'   => 'tech',
                    'time'   => (clone $now)->modify('-2 hours')->format('d M Y, h:i A'),
                    'text'   => 'Hardware diagnostics ran. Replacement battery pack dispatched from OEM stock. Scheduled swap tomorrow morning.'
                ]
            ])
        ],
        [
            'ticket_no'     => 'TKT' . $mmyy . '1098',
            'subject'       => 'Multiple ERP login auth timeouts and SSL certificate handshake error',
            'category'      => 'Software / Enterprise ERP',
            'priority'      => 'Urgent',
            'status'        => 'Open',
            'asset_name'    => 'HP EliteBook 840 (AST2026019)',
            'employee_name' => 'Priya Patel',
            'emp_code'      => 'EMP-108',
            'department'    => 'Finance & Accounts',
            'incident_date' => (clone $now)->modify('-1 day -2 hours')->format('Y-m-d H:i:s'),
            'description'   => 'User cannot process month-end accounts closure. Browser throws SEC_ERROR_UNKNOWN_ISSUER on ERP portal root gateway. Need immediate SSL trust store check.',
            'chat_history'  => json_encode([
                [
                    'author' => 'Priya Patel',
                    'avatar' => 'PP',
                    'type'   => 'employee',
                    'time'   => (clone $now)->modify('-1 day -2 hours')->format('d M Y, h:i A'),
                    'text'   => 'Critical blocker for today\'s vendor payout approvals. Please assist urgently.'
                ]
            ])
        ],
        [
            'ticket_no'     => 'TKT' . $mmyy . '1082',
            'subject'       => 'External monitor HDMI signal flickering after workstation standby',
            'category'      => 'Hardware / External Display',
            'priority'      => 'Medium',
            'status'        => 'In Progress',
            'asset_name'    => 'Dell UltraSharp 27" (AST2026048)',
            'employee_name' => 'Vikram Malhotra',
            'emp_code'      => 'EMP-115',
            'department'    => 'Engineering & R&D',
            'incident_date' => (clone $now)->modify('-2 days -3 hours')->format('Y-m-d H:i:s'),
            'description'   => 'Whenever laptop wakes from sleep/standby, the secondary HDMI monitor flickers black for 5 seconds before returning to normal. Cable has been reseated once.',
            'chat_history'  => json_encode([
                [
                    'author' => 'IT Support Engineer',
                    'avatar' => 'IT',
                    'type'   => 'tech',
                    'time'   => (clone $now)->modify('-2 days -1 hour')->format('d M Y, h:i A'),
                    'text'   => 'Driver update rolled out via Intune. Testing with high-speed 4K HDMI 2.1 cable today.'
                ]
            ])
        ],
        [
            'ticket_no'     => 'TKT' . $mmyy . '1075',
            'subject'       => 'Cisco AnyConnect VPN gateway disconnects repeatedly during remote access',
            'category'      => 'Network / Remote VPN',
            'priority'      => 'High',
            'status'        => 'In Progress',
            'asset_name'    => 'Lenovo ThinkPad T14 (AST2026032)',
            'employee_name' => 'Ananya Sen',
            'emp_code'      => 'EMP-122',
            'department'    => 'Human Resources',
            'incident_date' => (clone $now)->modify('-3 days')->format('Y-m-d H:i:s'),
            'description'   => 'VPN tunnel drops precisely every 15 minutes with "Tunnel connection reset by peer" alert. HR recruitment database records fail to save during candidate onboarding.',
            'chat_history'  => json_encode([
                [
                    'author' => 'IT Network Desk',
                    'avatar' => 'IT',
                    'type'   => 'tech',
                    'time'   => (clone $now)->modify('-3 days +2 hours')->format('d M Y, h:i A'),
                    'text'   => 'Firewall keepalive timeout extended to 120 minutes on VPN profile #4. Verified session stability.'
                ]
            ])
        ],
        [
            'ticket_no'     => 'TKT' . $mmyy . '1045',
            'subject'       => 'Replacement 65W Type-C adapter for travel docking station',
            'category'      => 'Peripherals / Cables & Power',
            'priority'      => 'Low',
            'status'        => 'Resolved & Closed',
            'asset_name'    => 'Lenovo 65W AC Adapter (ACC2026009)',
            'employee_name' => 'Sneha Kulkarni',
            'emp_code'      => 'EMP-102',
            'department'    => 'Executive Office',
            'incident_date' => (clone $now)->modify('-7 days')->format('Y-m-d H:i:s'),
            'description'   => 'Original adapter wire frayed near USB-C connector tip. Issued new Lenovo genuine 65W Type-C adapter from accessory storeroom.',
            'chat_history'  => json_encode([
                [
                    'author' => 'IT Support',
                    'avatar' => 'IT',
                    'type'   => 'tech',
                    'time'   => (clone $now)->modify('-6 days')->format('d M Y, h:i A'),
                    'text'   => 'New adapter handed over and sign-off custody receipt updated.'
                ]
            ])
        ]
    ];

    $seedSql = "INSERT INTO support_tickets 
                (ticket_no, subject, description, category, priority, status, asset_name, employee_name, emp_code, department, incident_date, chat_history, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

    foreach ($seedData as $sd) {
        sqlsrv_query($conn, $seedSql, [
            $sd['ticket_no'],
            $sd['subject'],
            $sd['description'],
            $sd['category'],
            $sd['priority'],
            $sd['status'],
            $sd['asset_name'],
            $sd['employee_name'],
            $sd['emp_code'],
            $sd['department'],
            $sd['incident_date'],
            $sd['chat_history']
        ]);
    }
}

// -----------------------------------------------------------------------------
// 3. Request Handler Routing
// -----------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

// ACTION: Create new support ticket
if ($method === 'POST' && $action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $subject     = trim($input['subject'] ?? '');
    $description = trim($input['description'] ?? '');
    $category    = trim($input['category'] ?? 'General IT Support');
    $priority    = trim($input['priority'] ?? 'Medium');
    $assetName   = trim($input['asset_name'] ?? ($input['asset'] ?? 'General Workstation / Laptop'));
    $empId       = !empty($input['employee_id']) ? intval($input['employee_id']) : (!empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);
    $empName     = trim($input['employee_name'] ?? ($_SESSION['user_name'] ?? 'Employee Member'));
    $empCode     = trim($input['emp_code'] ?? ($_SESSION['user_username'] ?? 'EMP-001'));
    $department  = trim($input['department'] ?? ($_SESSION['user_dept'] ?? 'General Staff'));
    
    // Parse incident date & time dynamically from user input or use current datetime
    $rawDate = trim($input['incident_date'] ?? ($input['incident_datetime'] ?? ''));
    $incidentDateObj = new DateTime();
    if (!empty($rawDate)) {
        try {
            $incidentDateObj = new DateTime($rawDate);
        } catch (Exception $e) {
            $incidentDateObj = new DateTime();
        }
    }
    $incidentSqlDate = $incidentDateObj->format('Y-m-d H:i:s');
    $formattedDateStr = $incidentDateObj->format('d M Y, h:i A');

    if (empty($subject)) {
        echo json_encode(['success' => false, 'message' => 'Issue subject is required.']);
        exit;
    }

    // Generate dynamic Ticket ID: TKT + MMYY + 4-digit serial
    $mmyy = $incidentDateObj->format('my');
    
    // Find highest serial for current month/year prefix
    $likePrefix = 'TKT' . $mmyy . '%';
    $maxSerialQuery = "SELECT MAX(ticket_no) AS max_tkt FROM support_tickets WHERE ticket_no LIKE ?";
    $maxStmt = sqlsrv_query($conn, $maxSerialQuery, [$likePrefix]);
    $nextSerialNum = 1105;
    if ($maxStmt && ($maxRow = sqlsrv_fetch_array($maxStmt, SQLSRV_FETCH_ASSOC))) {
        if (!empty($maxRow['max_tkt'])) {
            $lastDigits = substr($maxRow['max_tkt'], 7);
            if (is_numeric($lastDigits)) {
                $nextSerialNum = intval($lastDigits) + 1;
            }
        }
    }
    $newTicketNo = 'TKT' . $mmyy . str_pad($nextSerialNum, 4, '0', STR_PAD_LEFT);

    // Initial chat history with system ACK and employee note
    $initialChat = [
        [
            'author' => $empName,
            'avatar' => strtoupper(substr($empName, 0, 2)),
            'type'   => 'employee',
            'time'   => $formattedDateStr,
            'text'   => $description ?: $subject
        ]
    ];

    $insertSql = "INSERT INTO support_tickets 
                  (ticket_no, subject, description, category, priority, status, asset_name, employee_id, employee_name, emp_code, department, incident_date, chat_history, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, 'Open', ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

    $insertStmt = sqlsrv_query($conn, $insertSql, [
        $newTicketNo,
        $subject,
        $description,
        $category,
        $priority,
        $assetName,
        $empId,
        $empName,
        $empCode,
        $department,
        $incidentSqlDate,
        json_encode($initialChat)
    ]);

    if ($insertStmt === false) {
        $errors = sqlsrv_errors();
        echo json_encode(['success' => false, 'message' => 'Failed to insert ticket: ' . ($errors[0]['message'] ?? 'SQL Error')]);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'message'   => "Ticket {$newTicketNo} raised successfully!",
        'ticket_no' => $newTicketNo,
        'ticket'    => [
            'id'          => $newTicketNo,
            'subject'     => $subject,
            'description' => $description,
            'category'    => $category,
            'priority'    => $priority,
            'status'      => 'Open',
            'asset'       => $assetName,
            'date'        => $formattedDateStr,
            'chat_thread' => $initialChat
        ]
    ]);
    exit;
}

// ACTION: Post reply to ticket conversation
if ($method === 'POST' && $action === 'reply') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $ticketNo = trim($input['ticket_no'] ?? ($input['id'] ?? ''));
    $messageText = trim($input['message'] ?? ($input['text'] ?? ''));
    $senderType = trim($input['type'] ?? 'employee'); // 'employee' or 'admin' / 'tech'
    $authorName = trim($input['author'] ?? ($_SESSION['user_name'] ?? 'User'));

    if (empty($ticketNo) || empty($messageText)) {
        echo json_encode(['success' => false, 'message' => 'Ticket number and message are required.']);
        exit;
    }

    // Fetch existing chat history
    $getStmt = sqlsrv_query($conn, "SELECT chat_history FROM support_tickets WHERE ticket_no = ?", [$ticketNo]);
    if (!$getStmt || !($tRow = sqlsrv_fetch_array($getStmt, SQLSRV_FETCH_ASSOC))) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    $chatHistory = json_decode($tRow['chat_history'] ?? '[]', true) ?: [];
    $nowStr = (new DateTime())->format('d M Y, h:i A');

    $avatar = ($senderType === 'admin' || $senderType === 'tech') ? 'IT' : strtoupper(substr($authorName, 0, 2));

    $newMsg = [
        'author' => $authorName,
        'avatar' => $avatar,
        'type'   => $senderType,
        'time'   => $nowStr,
        'text'   => $messageText
    ];
    $chatHistory[] = $newMsg;

    $updateStmt = sqlsrv_query(
        $conn, 
        "UPDATE support_tickets SET chat_history = ?, updated_at = GETDATE() WHERE ticket_no = ?", 
        [json_encode($chatHistory), $ticketNo]
    );

    if ($updateStmt === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to save reply.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reply posted successfully.',
        'msg'     => $newMsg
    ]);
    exit;
}

// ACTION: Administrative triage update (status / priority)
if ($method === 'POST' && $action === 'update_triage') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $ticketNo = trim($input['ticket_no'] ?? ($input['id'] ?? ''));
    $newStatus = trim($input['status'] ?? '');
    $newPriority = trim($input['priority'] ?? '');

    if (empty($ticketNo)) {
        echo json_encode(['success' => false, 'message' => 'Ticket number is required.']);
        exit;
    }

    $updates = [];
    $params = [];
    if (!empty($newStatus)) {
        $updates[] = "status = ?";
        $params[] = $newStatus;
    }
    if (!empty($newPriority)) {
        $updates[] = "priority = ?";
        $params[] = $newPriority;
    }
    $updates[] = "updated_at = GETDATE()";
    $params[] = $ticketNo;

    $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE ticket_no = ?";
    $upStmt = sqlsrv_query($conn, $sql, $params);

    if ($upStmt === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to update triage status.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => "Ticket {$ticketNo} triage updated."]);
    exit;
}

$empIdFilter = !empty($_GET['emp_id']) ? intval($_GET['emp_id']) : 0;
$empFilter   = trim($_GET['emp_code'] ?? ($_GET['emp'] ?? ''));
$statusFilter = trim($_GET['status'] ?? '');

$whereClauses = [];
$queryParams = [];

if ($empIdFilter > 0 && !empty($empFilter)) {
    $whereClauses[] = "(employee_id = ? OR LOWER(emp_code) = LOWER(?))";
    $queryParams[] = $empIdFilter;
    $queryParams[] = $empFilter;
} elseif ($empIdFilter > 0) {
    $whereClauses[] = "employee_id = ?";
    $queryParams[] = $empIdFilter;
} elseif (!empty($empFilter)) {
    $whereClauses[] = "(LOWER(emp_code) = LOWER(?) OR LOWER(employee_name) LIKE ?)";
    $queryParams[] = $empFilter;
    $queryParams[] = '%' . strtolower($empFilter) . '%';
}

if (!empty($statusFilter) && $statusFilter !== 'all') {
    $whereClauses[] = "LOWER(status) LIKE ?";
    $queryParams[] = '%' . strtolower($statusFilter) . '%';
}

$whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';
$listSql = "SELECT * FROM support_tickets {$whereSql} ORDER BY incident_date DESC, id DESC";

$stmt = sqlsrv_query($conn, $listSql, $queryParams);
$results = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dt = $row['incident_date'];
        $formattedDate = '';
        if ($dt instanceof DateTime) {
            $formattedDate = $dt->format('d M Y, h:i A');
        } elseif (!empty($dt)) {
            $formattedDate = date('d M Y, h:i A', strtotime($dt));
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

        $results[] = [
            'id'          => $row['ticket_no'],
            'ticket_no'   => $row['ticket_no'],
            'subject'     => $row['subject'],
            'description' => $row['description'],
            'category'    => $row['category'],
            'priority'    => $prio,
            'p_class'     => $pClass,
            'status'      => $st,
            's_class'     => $sClass,
            's_filter'    => $sFilter,
            'asset'       => $row['asset_name'] ?: 'General Workstation / Laptop',
            'date'        => $formattedDate,
            'requester'   => [
                'name'       => $row['employee_name'],
                'code'       => $row['emp_code'],
                'department' => $row['department'],
                'initials'   => strtoupper(substr($row['employee_name'] ?? 'EM', 0, 2))
            ],
            'chat_thread' => json_decode($row['chat_history'] ?? '[]', true) ?: []
        ];
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode([
    'success' => true,
    'total'   => count($results),
    'tickets' => $results
]);
