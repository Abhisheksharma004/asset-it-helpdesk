<?php
/**
 * VIROS IT Asset & Service Desk - Support Tickets Backend API
 * Handles database CRUD operations, dynamic ticket number generation (prefix + MMYY + serial),
 * incident timestamps, chat conversations, and status triage.
 */

header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// -----------------------------------------------------------------------------
// 1. Ensure Table Schema Exists (support_tickets & ticket_messages Tables)
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
END
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='ticket_messages' AND xtype='U')
BEGIN
    CREATE TABLE ticket_messages (
        id INT IDENTITY(1,1) PRIMARY KEY,
        ticket_no NVARCHAR(50) NOT NULL,
        ticket_id INT NULL,
        sender_type NVARCHAR(50) NOT NULL DEFAULT 'employee',
        sender_name NVARCHAR(150) NOT NULL,
        sender_avatar NVARCHAR(20) NULL,
        message NVARCHAR(MAX) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX idx_ticket_messages_ticket_no ON ticket_messages(ticket_no);
END";
sqlsrv_query($conn, $tableSetupSql);

// Helper function to fetch messages from dedicated ticket_messages table
if (!function_exists('fetchTicketMessagesMap')) {
    function fetchTicketMessagesMap($conn, array $ticketNos) {
        $map = [];
        if (empty($ticketNos) || !$conn) return $map;
        $uniqueNos = array_values(array_unique(array_filter($ticketNos)));
        if (empty($uniqueNos)) return $map;

        $placeholders = implode(',', array_fill(0, count($uniqueNos), '?'));
        $sql = "SELECT id, ticket_no, ticket_id, sender_type, sender_name, sender_avatar, message, created_at 
                FROM ticket_messages 
                WHERE ticket_no IN ($placeholders) 
                ORDER BY created_at ASC, id ASC";
        $stmt = sqlsrv_query($conn, $sql, $uniqueNos);
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $tNo = $r['ticket_no'];
                if (!isset($map[$tNo])) {
                    $map[$tNo] = [];
                }
                $dt = $r['created_at'];
                $timeStr = ($dt instanceof DateTime) ? $dt->format('d M Y, h:i A') : (empty($dt) ? '' : date('d M Y, h:i A', strtotime($dt)));
                $map[$tNo][] = [
                    'id'     => $r['id'],
                    'author' => $r['sender_name'],
                    'avatar' => $r['sender_avatar'] ?: strtoupper(substr($r['sender_name'] ?? 'US', 0, 2)),
                    'type'   => $r['sender_type'],
                    'time'   => $timeStr,
                    'text'   => $r['message']
                ];
            }
            sqlsrv_free_stmt($stmt);
        }
        return $map;
    }
}

// -----------------------------------------------------------------------------
// 2. Request Handler Routing (Real Data Only - No Mock Seeding)
// -----------------------------------------------------------------------------

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
    $assetName   = trim($input['asset_name'] ?? ($input['asset'] ?? 'NA'));
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

    // Prevent immediate duplicate submission (if identical subject submitted within last 6 seconds by same requester)
    $dupCheckSql = "SELECT TOP 1 ticket_no, id, subject, description, category, priority, status, asset_name 
                    FROM support_tickets 
                    WHERE (employee_id = ? OR LOWER(emp_code) = LOWER(?)) 
                      AND LOWER(subject) = LOWER(?) 
                      AND DATEDIFF(SECOND, created_at, GETDATE()) <= 6
                    ORDER BY id DESC";
    $dupStmt = sqlsrv_query($conn, $dupCheckSql, [$empId, $empCode, $subject]);
    if ($dupStmt && ($dupRow = sqlsrv_fetch_array($dupStmt, SQLSRV_FETCH_ASSOC))) {
        $existingTicketNo = $dupRow['ticket_no'];
        echo json_encode([
            'success'   => true,
            'message'   => "Ticket {$existingTicketNo} raised successfully!",
            'ticket_no' => $existingTicketNo,
            'ticket'    => [
                'id'          => $existingTicketNo,
                'ticket_no'   => $existingTicketNo,
                'subject'     => $dupRow['subject'],
                'description' => $dupRow['description'],
                'category'    => $dupRow['category'],
                'priority'    => $dupRow['priority'],
                'status'      => $dupRow['status'] ?: 'Open',
                'asset'       => $dupRow['asset_name'],
                'date'        => $formattedDateStr,
                'chat_thread' => []
            ]
        ]);
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

    // Initial message payload (for immediate UI response)
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
                  (ticket_no, subject, description, category, priority, status, asset_name, employee_id, employee_name, emp_code, department, incident_date, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, 'Open', ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

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
        $incidentSqlDate
    ]);

    if ($insertStmt === false) {
        $errors = sqlsrv_errors();
        echo json_encode(['success' => false, 'message' => 'Failed to insert ticket: ' . ($errors[0]['message'] ?? 'SQL Error')]);
        exit;
    }

    // Retrieve inserted ticket ID
    $newTktId = null;
    $idStmt = sqlsrv_query($conn, "SELECT id FROM support_tickets WHERE ticket_no = ?", [$newTicketNo]);
    if ($idStmt && ($idRow = sqlsrv_fetch_array($idStmt, SQLSRV_FETCH_ASSOC))) {
        $newTktId = intval($idRow['id']);
    }

    // Insert initial employee issue message into dedicated ticket_messages table
    $initMsgText = $description ?: $subject;
    $initAvatar  = strtoupper(substr($empName, 0, 2));
    sqlsrv_query($conn, 
        "INSERT INTO ticket_messages (ticket_no, ticket_id, sender_type, sender_name, sender_avatar, message, created_at) VALUES (?, ?, 'employee', ?, ?, ?, ?)",
        [$newTicketNo, $newTktId, $empName, $initAvatar, $initMsgText, $incidentSqlDate]
    );

    echo json_encode([
        'success'   => true,
        'message'   => "Ticket {$newTicketNo} raised successfully!",
        'ticket_no' => $newTicketNo,
        'ticket'    => [
            'id'          => $newTicketNo,
            'ticket_no'   => $newTicketNo,
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

// ACTION: Fetch ticket conversation thread directly from ticket_messages table
if ($action === 'get_messages') {
    $ticketNo = trim($_GET['ticket_no'] ?? ($_GET['id'] ?? ''));
    if (empty($ticketNo)) {
        echo json_encode(['success' => false, 'message' => 'Ticket number is required.']);
        exit;
    }
    $map = fetchTicketMessagesMap($conn, [$ticketNo]);
    $messages = $map[$ticketNo] ?? [];

    echo json_encode([
        'success'   => true,
        'ticket_no' => $ticketNo,
        'total'     => count($messages),
        'messages'  => $messages
    ]);
    exit;
}

// ACTION: Post reply to ticket conversation (stored in dedicated ticket_messages table)
if ($method === 'POST' && $action === 'reply') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $ticketNo = trim($input['ticket_no'] ?? ($input['id'] ?? ''));
    $messageText = trim($input['message'] ?? ($input['text'] ?? ''));
    $senderType = trim($input['type'] ?? 'employee'); // 'employee', 'admin', or 'tech'
    $authorName = trim($input['author'] ?? ($_SESSION['user_name'] ?? 'User'));

    if (empty($ticketNo) || empty($messageText)) {
        echo json_encode(['success' => false, 'message' => 'Ticket number and message are required.']);
        exit;
    }

    // Verify ticket exists
    $getStmt = sqlsrv_query($conn, "SELECT id FROM support_tickets WHERE ticket_no = ?", [$ticketNo]);
    if (!$getStmt || !($tRow = sqlsrv_fetch_array($getStmt, SQLSRV_FETCH_ASSOC))) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }
    $ticketId = intval($tRow['id']);

    $nowObj = new DateTime();
    $nowStr = $nowObj->format('d M Y, h:i A');
    $sqlDate = $nowObj->format('Y-m-d H:i:s');
    $avatar = ($senderType === 'admin' || $senderType === 'tech') ? 'IT' : strtoupper(substr($authorName, 0, 2));

    // 1. Insert new message directly into dedicated ticket_messages table
    $insertMsgSql = "INSERT INTO ticket_messages (ticket_no, ticket_id, sender_type, sender_name, sender_avatar, message, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    $msgStmt = sqlsrv_query($conn, $insertMsgSql, [$ticketNo, $ticketId, $senderType, $authorName, $avatar, $messageText, $sqlDate]);

    if ($msgStmt === false) {
        $errors = sqlsrv_errors();
        echo json_encode(['success' => false, 'message' => 'Failed to save reply: ' . ($errors[0]['message'] ?? 'SQL Error')]);
        exit;
    }

    // 2. Keep support_tickets updated_at timestamp updated
    $newMsg = [
        'author' => $authorName,
        'avatar' => $avatar,
        'type'   => $senderType,
        'time'   => $nowStr,
        'text'   => $messageText
    ];

    sqlsrv_query(
        $conn, 
        "UPDATE support_tickets SET updated_at = GETDATE() WHERE id = ?", 
        [$ticketId]
    );

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
    $customMessage = trim($input['message'] ?? ($input['remark'] ?? ''));
    $adminAuthor = trim($input['author'] ?? ($_SESSION['user_name'] ?? 'IT Service Desk Admin'));

    if (empty($ticketNo)) {
        echo json_encode(['success' => false, 'message' => 'Ticket number is required.']);
        exit;
    }

    // Fetch ticket ID for message foreign reference
    $ticketId = null;
    $idStmt = sqlsrv_query($conn, "SELECT id, status, priority FROM support_tickets WHERE ticket_no = ?", [$ticketNo]);
    if ($idStmt && ($tRow = sqlsrv_fetch_array($idStmt, SQLSRV_FETCH_ASSOC))) {
        $ticketId = intval($tRow['id']);
    }

    // Parse and prepare selected Date & Time
    $newDateTime = trim($input['date_time'] ?? '');
    $dtObj = null;
    if (!empty($newDateTime)) {
        try {
            $dtObj = new DateTime($newDateTime);
        } catch (Exception $e) {
            $dtObj = null;
        }
    }
    if (!$dtObj) {
        $dtObj = new DateTime();
    }
    $sqlDate = $dtObj->format('Y-m-d H:i:s');
    $formattedDateTime = $dtObj->format('d M Y, h:i A');

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
    $updates[] = "updated_at = ?";
    $params[] = $sqlDate;
    $params[] = $ticketNo;

    $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE ticket_no = ?";
    $upStmt = sqlsrv_query($conn, $sql, $params);

    if ($upStmt === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to update triage status.']);
        exit;
    }

    // Compose status update message text with selected date & time
    $isClosedOrResolved = (stripos($newStatus, 'resolved') !== false || stripos($newStatus, 'closed') !== false);

    if ($isClosedOrResolved) {
        if (!empty($customMessage)) {
            $msgText = "Dear Sir/Ma'am, your IT support ticket has been successfully resolved and closed.\n"
                     . "🕒 Closed / Resolution Time: " . $formattedDateTime . "\n"
                     . "Resolution Details: " . $customMessage . "\n"
                     . "Thank you for your patience and cooperation! If you face any further issue or require assistance, please feel free to reach out to the IT Service Desk.";
        } else {
            $msgText = "Dear Sir/Ma'am, your IT support ticket has been successfully resolved and closed by IT Service Desk.\n"
                     . "🕒 Closed / Resolution Time: " . $formattedDateTime . "\n"
                     . "All reported issues have been addressed. Thank you for your patience and cooperation! If you continue to experience any issue or require further assistance, feel free to reply here or raise a new request.";
        }
    } elseif (!empty($customMessage)) {
        $msgText = $customMessage . "\n🕒 Updated Time: " . $formattedDateTime;
    } else {
        if (stripos($newStatus, 'progress') !== false) {
            $msgText = "Ticket status has been updated to 'In Progress' by " . $adminAuthor . ".\n"
                     . "🕒 Status Update Time: " . $formattedDateTime . "\n"
                     . "Our IT technical team has taken up your request and is actively working on resolving it.";
        } else {
            $parts = [];
            if (!empty($newStatus)) {
                $parts[] = "Status set to \"{$newStatus}\"";
            }
            if (!empty($newPriority)) {
                $parts[] = "Priority set to \"{$newPriority}\"";
            }
            $msgText = implode(', ', $parts) . " by " . $adminAuthor . ".\n🕒 Date & Time: " . $formattedDateTime;
        }
    }

    // Save status change message into dedicated ticket_messages table
    $msgInsertSql = "INSERT INTO ticket_messages (ticket_no, ticket_id, sender_type, sender_name, sender_avatar, message, created_at) 
                     VALUES (?, ?, 'admin', ?, 'IT', ?, ?)";
    sqlsrv_query($conn, $msgInsertSql, [$ticketNo, $ticketId, $adminAuthor, $msgText, $sqlDate]);

    $newMsgItem = [
        'author' => $adminAuthor,
        'avatar' => 'IT',
        'type'   => 'admin',
        'time'   => $formattedDateTime,
        'text'   => $msgText
    ];

    echo json_encode([
        'success' => true, 
        'message' => "Ticket {$ticketNo} triage updated.",
        'msg'     => $newMsgItem
    ]);
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
            'asset'       => $row['asset_name'] ?: 'NA',
            'date'        => $formattedDate,
            'requester'   => [
                'name'       => $row['employee_name'],
                'code'       => $row['emp_code'],
                'department' => $row['department'],
                'initials'   => strtoupper(substr($row['employee_name'] ?? 'EM', 0, 2))
            ],
            'chat_thread' => []
        ];
    }
    sqlsrv_free_stmt($stmt);
}

// Populate conversation thread from separate ticket_messages table
if (!empty($results)) {
    $ticketNos = array_column($results, 'ticket_no');
    $messagesMap = fetchTicketMessagesMap($conn, $ticketNos);

    foreach ($results as &$tktItem) {
        $tNo = $tktItem['ticket_no'];
        if (isset($messagesMap[$tNo]) && !empty($messagesMap[$tNo])) {
            $tktItem['chat_thread'] = $messagesMap[$tNo];
        }
    }
    unset($tktItem);
}

echo json_encode([
    'success' => true,
    'total'   => count($results),
    'tickets' => $results
]);
