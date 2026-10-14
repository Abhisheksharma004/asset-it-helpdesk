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

// Active Employee Profile Details - Dynamically resolved from GET, Session or DB
$selectedEmpId = !empty($_GET['emp']) ? intval($_GET['emp']) : (!empty($_GET['id']) ? intval($_GET['id']) : 0);
$sessionEmpId  = !empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$targetEmpMail = !empty($_GET['email']) ? trim($_GET['email']) : (!empty($_SESSION['user_email']) ? trim($_SESSION['user_email']) : '');
$targetEmpCode = !empty($_GET['code']) ? trim($_GET['code']) : (!empty($_SESSION['user_username']) ? trim($_SESSION['user_username']) : '');

$activeEmployee = null;

if (isset($conn) && $conn !== false) {
    $currStmt = null;
    if ($selectedEmpId > 0) {
        $currStmt = sqlsrv_query(
            $conn, 
            "SELECT e.*, d.department_name, l.location_name 
             FROM employees e 
             LEFT JOIN departments d ON e.department_id = d.id 
             LEFT JOIN locations l ON e.location_id = l.id 
             WHERE e.id = ?", 
            [$selectedEmpId]
        );
    } elseif ($sessionEmpId > 0 || !empty($targetEmpMail) || !empty($targetEmpCode)) {
        $currStmt = sqlsrv_query(
            $conn, 
            "SELECT e.*, d.department_name, l.location_name 
             FROM employees e 
             LEFT JOIN departments d ON e.department_id = d.id 
             LEFT JOIN locations l ON e.location_id = l.id 
             WHERE e.id = ? OR LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)", 
            [$sessionEmpId, $targetEmpMail, $targetEmpCode]
        );
    }

    if (!$currStmt || !($currRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC))) {
        $currStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 e.*, d.department_name, l.location_name 
             FROM employees e 
             LEFT JOIN departments d ON e.department_id = d.id 
             LEFT JOIN locations l ON e.location_id = l.id 
             WHERE e.status = 'Active' OR e.status IS NULL 
             ORDER BY e.id ASC"
        );
        if ($currStmt) {
            $currRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC);
        }
    }

    if (!empty($currRow)) {
        $fullName = trim(($currRow['first_name'] ?? '') . ' ' . ($currRow['last_name'] ?? ''));
        $activeEmployee = [
            'id'           => (int)$currRow['id'],
            'name'         => $fullName ?: 'Employee',
            'code'         => $currRow['emp_code'] ?? 'EMP-001',
            'designation'  => !empty($currRow['designation']) ? $currRow['designation'] : 'Staff Member',
            'department'   => !empty($currRow['department_name']) ? $currRow['department_name'] : 'General',
            'email'        => $currRow['email'] ?? '',
            'phone'        => !empty($currRow['phone']) ? $currRow['phone'] : 'Not Provided',
            'location'     => !empty($currRow['location_name']) ? $currRow['location_name'] : 'Corporate Office',
            'status'       => $currRow['status'] ?? 'Active',
            'custody'      => (strtolower(trim($currRow['status'] ?? '')) === 'active') ? 'Verified & Active' : 'Inactive'
        ];

        if (empty($_SESSION['user_id'])) {
            $_SESSION['user_id']        = $activeEmployee['id'];
            $_SESSION['user_name']      = $activeEmployee['name'];
            $_SESSION['user_username']  = $activeEmployee['code'];
            $_SESSION['user_email']     = $activeEmployee['email'];
            $_SESSION['user_role']      = 'Employee';
            $_SESSION['user_dept']      = $activeEmployee['department'];
            $_SESSION['logged_in']      = true;
            $_SESSION['is_first_login'] = 0;
        }

        if ($currStmt) {
            sqlsrv_free_stmt($currStmt);
        }
    }
}

if (!$activeEmployee) {
    $activeEmployee = [
        'id'           => 1,
        'name'         => $_SESSION['user_name'] ?? 'Employee Member',
        'code'         => $_SESSION['user_username'] ?? 'EMP-001',
        'designation'  => 'Staff Member',
        'department'   => $_SESSION['user_dept'] ?? 'General',
        'email'        => $_SESSION['user_email'] ?? 'employee@viros.in',
        'phone'        => 'Not Provided',
        'location'     => 'Corporate Office',
        'status'       => 'Active',
        'custody'      => 'Verified & Active'
    ];
}

$emp_initials = '';
foreach (explode(' ', trim($activeEmployee['name'])) as $w) {
    if (!empty($w)) $emp_initials .= strtoupper($w[0]);
    if (strlen($emp_initials) >= 2) break;
}
if (empty($emp_initials)) $emp_initials = 'EM';

// -----------------------------------------------------------------------------
// DYNAMIC FETCH: Real Custody Inventory for this employee from Database
// -----------------------------------------------------------------------------
$allocatedItems = [];
$seenTags       = [];
$activeSlipNo   = 'SLIP-' . date('Y') . '-' . str_pad($activeEmployee['id'], 4, '0', STR_PAD_LEFT);
$activeSlipDate = date('d M Y');

if (isset($conn) && $conn !== false && !empty($activeEmployee['id'])) {
    $empId    = $activeEmployee['id'];
    $empEmail = $activeEmployee['email'];
    $empCode  = $activeEmployee['code'];
    $empName  = $activeEmployee['name'];

    // 1. Fetch from asset_assignments table
    $assignSql = "SELECT a.* 
                  FROM asset_assignments a 
                  WHERE (a.employee_id = ? OR LOWER(a.employee_email) = LOWER(?) OR LOWER(a.emp_code) = LOWER(?))
                    AND a.custody_status = 'Active'
                  ORDER BY a.id DESC";
    $assignStmt = sqlsrv_query($conn, $assignSql, [$empId, $empEmail, $empCode]);

    if ($assignStmt) {
        while ($r = sqlsrv_fetch_array($assignStmt, SQLSRV_FETCH_ASSOC)) {
            $assignedDate = 'Recent';
            if (!empty($r['assigned_date'])) {
                $assignedDate = is_object($r['assigned_date']) 
                    ? $r['assigned_date']->format('d M Y') 
                    : date('d M Y', strtotime($r['assigned_date']));
            }
            if (!empty($r['slip_no'])) {
                $activeSlipNo   = $r['slip_no'];
                $activeSlipDate = $assignedDate;
            }

            // Primary Asset
            if (!empty($r['asset_tag']) && !in_array($r['asset_tag'], $seenTags)) {
                $seenTags[] = $r['asset_tag'];
                $catLower   = strtolower($r['category'] ?? '');
                $nameLower  = strtolower($r['asset_name'] ?? '');

                $iconType = 'hardware';
                $itemType = 'hardware';
                if (strpos($catLower, 'laptop') !== false || strpos($nameLower, 'laptop') !== false) {
                    $iconType = 'laptop';
                } elseif (strpos($catLower, 'monitor') !== false || strpos($nameLower, 'monitor') !== false || strpos($catLower, 'display') !== false) {
                    $iconType = 'monitor';
                } elseif (strpos($catLower, 'desktop') !== false || strpos($nameLower, 'desktop') !== false) {
                    $iconType = 'desktop';
                } elseif (strpos($catLower, 'cable') !== false || strpos($catLower, 'accessory') !== false || strpos($catLower, 'mouse') !== false || strpos($catLower, 'keyboard') !== false) {
                    $itemType = 'accessory';
                    $iconType = 'accessory';
                }

                $specs = !empty($r['specs']) ? $r['specs'] : trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? ''));

                $allocatedItems[] = [
                    'tag'       => $r['asset_tag'],
                    'name'      => !empty($r['asset_name']) ? $r['asset_name'] : 'Corporate Hardware Unit',
                    'specs'     => $specs ?: 'Configured computing system',
                    'category'  => !empty($r['category']) ? $r['category'] : 'Computing Unit',
                    'type'      => $itemType,
                    'serial'    => !empty($r['serial']) ? $r['serial'] : 'S/N: Not Specified',
                    'date'      => $assignedDate,
                    'condition' => !empty($r['condition']) ? $r['condition'] : 'Good',
                    'status'    => 'In Active Custody',
                    'badge'     => 'badge-resolved',
                    'icon_type' => $iconType,
                    'slip_no'   => $r['slip_no']
                ];
            }

            // Bundled Assets from assets_json
            if (!empty($r['assets_json'])) {
                $bundledAssets = json_decode($r['assets_json'], true);
                if (is_array($bundledAssets)) {
                    foreach ($bundledAssets as $ba) {
                        $baTag = $ba['tag'] ?? '';
                        if (!empty($baTag) && !in_array($baTag, $seenTags)) {
                            $seenTags[] = $baTag;
                            $baCatLower = strtolower($ba['category'] ?? '');
                            $baIcon = (strpos($baCatLower, 'laptop') !== false) ? 'laptop' : ((strpos($baCatLower, 'monitor') !== false) ? 'monitor' : 'hardware');

                            $allocatedItems[] = [
                                'tag'       => $baTag,
                                'name'      => $ba['name'] ?? 'Hardware Unit',
                                'specs'     => $ba['specs'] ?? trim(($ba['brand'] ?? '') . ' ' . ($ba['model'] ?? '')),
                                'category'  => $ba['category'] ?? 'Hardware',
                                'type'      => 'hardware',
                                'serial'    => !empty($ba['serial']) ? $ba['serial'] : 'S/N: N/A',
                                'date'      => $assignedDate,
                                'condition' => $ba['condition'] ?? 'Good',
                                'status'    => 'In Active Custody',
                                'badge'     => 'badge-resolved',
                                'icon_type' => $baIcon,
                                'slip_no'   => $r['slip_no']
                            ];
                        }
                    }
                }
            }

            // Bundled Accessories from accessories_json
            if (!empty($r['accessories_json'])) {
                $accList = json_decode($r['accessories_json'], true);
                if (is_array($accList)) {
                    foreach ($accList as $acc) {
                        $accTag = !empty($acc['sku']) ? $acc['sku'] : ('ACC-' . ($acc['id'] ?? uniqid()));
                        if (in_array($accTag, $seenTags)) continue;
                        $seenTags[] = $accTag;

                        $accName  = $acc['name'] ?? 'Workstation Accessory';
                        $accCat   = $acc['category'] ?? 'Peripheral';
                        $accBrand = $acc['brand'] ?? '';
                        $accQty   = $acc['qty'] ?? 1;

                        $catLower = strtolower($accCat . ' ' . $accName);
                        $iconType = 'accessory';
                        if (strpos($catLower, 'keyboard') !== false) $iconType = 'keyboard';
                        elseif (strpos($catLower, 'mouse') !== false) $iconType = 'mouse';
                        elseif (strpos($catLower, 'headset') !== false || strpos($catLower, 'audio') !== false) $iconType = 'headset';
                        elseif (strpos($catLower, 'dock') !== false) $iconType = 'dock';
                        elseif (strpos($catLower, 'cable') !== false) $iconType = 'cable';

                        $allocatedItems[] = [
                            'tag'       => $accTag,
                            'name'      => $accName,
                            'specs'     => ($accBrand ? $accBrand . ' • ' : '') . 'Qty: ' . $accQty . ' Unit(s)',
                            'category'  => $accCat ?: 'Workstation Accessory',
                            'type'      => 'accessory',
                            'serial'    => 'SKU: ' . $accTag,
                            'date'      => $assignedDate,
                            'condition' => 'Good',
                            'status'    => 'Issued & In Use',
                            'badge'     => 'badge-progress',
                            'icon_type' => $iconType,
                            'slip_no'   => $r['slip_no']
                        ];
                    }
                }
            }
        }
        sqlsrv_free_stmt($assignStmt);
    }

    // 2. Also check assets table directly (where assigned_to matches employee name or code)
    $assetSql = "SELECT ast.* FROM assets ast WHERE (LOWER(ast.assigned_to) = LOWER(?) OR LOWER(ast.assigned_to) = LOWER(?)) AND ast.status = 'In Use'";
    $assetStmt = sqlsrv_query($conn, $assetSql, [$empName, $empCode]);
    if ($assetStmt) {
        while ($ast = sqlsrv_fetch_array($assetStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($ast['tag']) && !in_array($ast['tag'], $seenTags)) {
                $seenTags[] = $ast['tag'];
                $specsParts = array_filter([$ast['processor'] ?? '', $ast['ram'] ?? '', $ast['storage'] ?? '', $ast['os'] ?? '']);
                $specs = !empty($specsParts) ? implode(' • ', $specsParts) : trim(($ast['brand'] ?? '') . ' ' . ($ast['model'] ?? ''));

                $catLower = strtolower($ast['category'] ?? '');
                $iconType = (strpos($catLower, 'laptop') !== false) ? 'laptop' : ((strpos($catLower, 'monitor') !== false) ? 'monitor' : 'hardware');

                $astDate = 'Recent';
                if (!empty($ast['updated_at'])) {
                    $astDate = is_object($ast['updated_at']) ? $ast['updated_at']->format('d M Y') : date('d M Y', strtotime($ast['updated_at']));
                }

                $allocatedItems[] = [
                    'tag'       => $ast['tag'],
                    'name'      => $ast['name'],
                    'specs'     => $specs ?: 'Hardware computing unit',
                    'category'  => $ast['category'] ?: 'Hardware',
                    'type'      => 'hardware',
                    'serial'    => !empty($ast['serial']) ? $ast['serial'] : 'S/N: Not Specified',
                    'date'      => $astDate,
                    'condition' => !empty($ast['condition']) ? $ast['condition'] : 'Good',
                    'status'    => 'In Active Custody',
                    'badge'     => 'badge-resolved',
                    'icon_type' => $iconType,
                    'slip_no'   => 'HS-' . $ast['tag']
                ];
            }
        }
        sqlsrv_free_stmt($assetStmt);
    }
}

// Dynamic item counts
$hardwareCount   = count(array_filter($allocatedItems, function($i) { return isset($i['type']) && $i['type'] === 'hardware'; }));
$accessoryCount  = count(array_filter($allocatedItems, function($i) { return isset($i['type']) && $i['type'] === 'accessory'; }));
$softwareCount   = count(array_filter($allocatedItems, function($i) { return isset($i['type']) && $i['type'] === 'software'; }));
$totalItemsCount = count($allocatedItems);

// -----------------------------------------------------------------------------
// DYNAMIC FETCH: Real Support Tickets for this employee
// -----------------------------------------------------------------------------
$recentTickets        = [];
$allTickets           = [];
$openTicketsCount     = 0;
$resolvedTicketsCount = 0;

if (isset($conn) && $conn !== false && !empty($activeEmployee['id'])) {
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
        $rtStmt = sqlsrv_query(
            $conn,
            "SELECT * FROM support_tickets WHERE {$whereSql} ORDER BY incident_date DESC, id DESC",
            $paramsEmp
        );
        if ($rtStmt) {
            while ($r = sqlsrv_fetch_array($rtStmt, SQLSRV_FETCH_ASSOC)) {
                $dt = $r['incident_date'];
                $formattedDate = '';
                if ($dt instanceof DateTime) $formattedDate = $dt->format('d M Y');
                elseif (!empty($dt)) $formattedDate = date('d M Y', strtotime($dt));

                $st = $r['status'] ?? 'Open';
                $stLower = strtolower($st);
                if (in_array($stLower, ['resolved', 'closed'])) {
                    $resolvedTicketsCount++;
                    $sClass = 'badge-resolved';
                } else {
                    $openTicketsCount++;
                    $sClass = (stripos($stLower, 'open') !== false) ? 'badge-open' : 'badge-progress';
                }

                $prio = $r['priority'] ?? 'Medium';
                $pClass = 'badge-medium';
                if ($prio === 'Urgent') $pClass = 'badge-urgent';
                elseif ($prio === 'High') $pClass = 'badge-high';
                elseif ($prio === 'Low') $pClass = 'badge-open';

                $item = [
                    'id'       => $r['ticket_no'],
                    'subject'  => $r['subject'],
                    'asset'    => $r['asset_name'] ?: 'Corporate Device',
                    'priority' => $prio,
                    'p_class'  => $pClass,
                    'status'   => $st,
                    's_class'  => $sClass,
                    'date'     => $formattedDate,
                    'tech'     => !empty($r['assigned_to']) ? $r['assigned_to'] : 'IT Service Desk Queue'
                ];
                $allTickets[] = $item;
            }
            sqlsrv_free_stmt($rtStmt);
        }
    }
}
$recentTickets = array_slice($allTickets, 0, 5);
$totalTicketsCount = count($allTickets);

// -----------------------------------------------------------------------------
// DYNAMIC BREAKDOWN: Real Custody Distribution
// -----------------------------------------------------------------------------
$custodyDistribution = [];
if ($totalItemsCount > 0) {
    $catCounts = [];
    foreach ($allocatedItems as $it) {
        $cat = !empty($it['category']) ? $it['category'] : 'General Equipment';
        $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
    }
    arsort($catCounts);
    $colorOptions = ['progress-cyan', 'progress-navy', 'progress-green', 'progress-amber'];
    $ci = 0;
    foreach ($catCounts as $cName => $cnt) {
        $pct = round(($cnt / $totalItemsCount) * 100);
        $custodyDistribution[] = [
            'name'       => $cName,
            'count'      => $cnt,
            'percentage' => $pct,
            'color'      => $colorOptions[$ci % count($colorOptions)]
        ];
        $ci++;
    }
}

// -----------------------------------------------------------------------------
// DYNAMIC TIMELINE: Real Custody & Ticket Activity
// -----------------------------------------------------------------------------
$recentActivities = [];
if (isset($conn) && $conn !== false && !empty($activeEmployee['id'])) {
    $actStmt = sqlsrv_query(
        $conn,
        "SELECT TOP 3 slip_no, asset_name, category, custody_status, assigned_date 
         FROM asset_assignments 
         WHERE (employee_id = ? OR LOWER(employee_email) = LOWER(?) OR LOWER(emp_code) = LOWER(?))
         ORDER BY id DESC",
        [$activeEmployee['id'], $activeEmployee['email'], $activeEmployee['code']]
    );
    if ($actStmt) {
        while ($ar = sqlsrv_fetch_array($actStmt, SQLSRV_FETCH_ASSOC)) {
            $aDate = 'Recent';
            if (!empty($ar['assigned_date'])) {
                $aDate = is_object($ar['assigned_date']) ? $ar['assigned_date']->format('d M Y') : date('d M Y', strtotime($ar['assigned_date']));
            }
            $icon = (stripos($ar['category'] ?? '', 'laptop') !== false) ? '💻' : ((stripos($ar['category'] ?? '', 'cable') !== false) ? '🔌' : '📦');
            $recentActivities[] = [
                'icon'  => $icon,
                'title' => (!empty($ar['asset_name']) ? $ar['asset_name'] : 'IT Equipment') . ' recorded on ' . ($ar['slip_no'] ?? 'Custody Slip'),
                'time'  => $aDate . ' • ' . ($ar['custody_status'] ?? 'Active') . ' Custody'
            ];
        }
        sqlsrv_free_stmt($actStmt);
    }
}
foreach (array_slice($recentTickets, 0, 2) as $t) {
    $recentActivities[] = [
        'icon'  => '🎫',
        'title' => 'Ticket #' . $t['id'] . ': ' . $t['subject'],
        'time'  => $t['date'] . ' • ' . $t['status']
    ];
}

// First login password setup is handled on index.php via Smart Login Check
$isFirstLogin = false;
$body_attributes = '';

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
            <div class="welcome-badge">
                <div class="num" style="display: flex; align-items: center; justify-content: center; gap: 4px; color: <?php echo ($totalItemsCount > 0) ? '#34d399' : '#94a3b8'; ?>;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php echo ($totalItemsCount > 0) ? 'Verified' : 'No Custody'; ?>
                </div>
                <div class="lbl">Custody Status</div>
            </div>
            <div class="welcome-badge">
                <div class="num"><?php echo $totalItemsCount; ?> Items</div>
                <div class="lbl">In Active Custody</div>
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
            <div class="metric-value"><?php echo $hardwareCount; ?> Device<?php echo $hardwareCount === 1 ? '' : 's'; ?></div>
            <div class="metric-footer">
                <span class="trend-up">● In Active Custody</span>
                <span>• Laptop & Computing</span>
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
            <div class="metric-value"><?php echo $accessoryCount; ?> Peripheral<?php echo $accessoryCount === 1 ? '' : 's'; ?></div>
            <div class="metric-footer">
                <span class="trend-neutral">● Allocated</span>
                <span>• Peripherals & Cables</span>
            </div>
        </div>

        <!-- Metric 3: Active Support Requests -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Open Support Requests</span>
                <div class="metric-icon-wrap <?php echo ($openTicketsCount > 0) ? 'urgent' : 'success'; ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $openTicketsCount; ?> Open</div>
            <div class="metric-footer">
                <?php if ($openTicketsCount > 0): ?>
                    <span class="trend-urgent" style="color: #ef4444;">● In IT Queue</span>
                    <span>• SLA Monitored</span>
                <?php else: ?>
                    <span class="trend-up">✓ All Resolved</span>
                    <span>• No Pending Issues</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Metric 4: Resolved / Total Tickets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Support Ticket History</span>
                <div class="metric-icon-wrap navy">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo $totalTicketsCount; ?> Total</div>
            <div class="metric-footer">
                <span class="trend-neutral">● <?php echo $resolvedTicketsCount; ?> Closed / Resolved</span>
                <span>• Tracked</span>
            </div>
        </div>

    </div>

    <!-- Dashboard Main Grid: Table & Widgets -->
    <div class="dashboard-grid">

        <!-- Left Column: Assigned Inventory & Open Tickets -->
        <div class="dashboard-col-main" style="display: flex; flex-direction: column; gap: 24px; min-width: 0; width: 100%; max-width: 100%;">

            <!-- Main Content Card: My Assigned IT Equipment -->
            <div class="content-card" id="secMyAssets">
                <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h2>My Allocated IT Equipment</h2>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                            Hardware and peripherals registered to your official custody
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
                            <?php if ($softwareCount > 0): ?>
                                <button type="button" class="emp-tab-btn" data-filter="software">
                                    🔑 Software <span class="emp-tab-count"><?php echo $softwareCount; ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allocatedItems)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 48px 20px; color: var(--text-secondary);">
                                        <div style="font-size: 36px; margin-bottom: 8px;">💻</div>
                                        <div style="font-weight: 700; color: var(--navy-primary); font-size: 14.5px;">No IT Equipment Currently Allocated</div>
                                        <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 4px; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.4;">
                                            You currently do not have any company hardware or peripherals registered in your custody.
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr id="empNoItemsRow" style="display: none;">
                                <td colspan="5" style="text-align: center; padding: 36px 16px; color: var(--text-secondary);">
                                    <div style="font-size: 28px; margin-bottom: 6px;">📦</div>
                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;">No matching items found</div>
                                    <div style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">Try adjusting your category tab filter.</div>
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
                    <div class="card-header-actions" style="display: flex; gap: 8px;">
                        <a href="employee_tickets.php" class="quick-action-btn" style="height: 32px; padding: 0 12px; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: transparent; border: 1px solid var(--border-color); color: var(--navy-primary);">
                            View All Tickets
                        </a>
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
                            <?php if (empty($recentTickets)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
                                        <div style="font-size: 28px; margin-bottom: 6px;">🎫</div>
                                        <div style="font-weight: 700; color: var(--navy-primary); font-size: 13.5px;">No Support Tickets Logged</div>
                                        <div style="font-size: 12px; margin-top: 3px;">You have no active technical tickets. Click "Raise New Ticket" if you require IT assistance.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column: Custody Distribution -->
        <div class="dashboard-col-side" style="display: flex; flex-direction: column; gap: 24px; min-width: 0; width: 100%; max-width: 100%;">

            <!-- Custody Distribution (Dynamic Breakdown) -->
            <div class="content-card">
                <div class="card-header">
                    <h2>My Custody Distribution</h2>
                </div>
                <div class="asset-category-list">
                    <?php if (empty($custodyDistribution)): ?>
                        <div style="padding: 24px 12px; text-align: center; color: var(--text-muted); font-size: 12.5px;">
                            <div style="font-size: 24px; margin-bottom: 6px;">📦</div>
                            <div>No IT assets currently assigned.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($custodyDistribution as $cd): ?>
                            <div class="category-row">
                                <div class="category-meta">
                                    <span><?php echo htmlspecialchars($cd['name']); ?></span>
                                    <strong><?php echo $cd['count']; ?> Unit<?php echo $cd['count'] > 1 ? 's' : ''; ?> (<?php echo $cd['percentage']; ?>%)</strong>
                                </div>
                                <div class="category-progress">
                                    <div class="progress-fill <?php echo $cd['color']; ?>" style="width: <?php echo $cd['percentage']; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</main>



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
                        <h3><?php echo htmlspecialchars(!empty($orgSettings['organization_legal_name']) ? $orgSettings['organization_legal_name'] : 'VIROS IT SOLUTIONS & SERVICES'); ?></h3>
                        <p>Enterprise IT Asset Management & Service Desk Division</p>
                        <p style="font-size: 11px; color: #94a3b8;">Document Form: IT-AST-HND-2025/V1</p>
                    </div>
                    <div class="slip-title-box">
                        <div class="slip-doc-no">SLIP #<?php echo htmlspecialchars($activeSlipNo); ?></div>
                        <div class="slip-date">Date Issued: <?php echo htmlspecialchars($activeSlipDate); ?></div>
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
                        <?php if (empty($allocatedItems)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                    No active equipment items registered on this custody slip.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allocatedItems as $ai): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ai['tag']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ai['name'] . ' (' . $ai['specs'] . ')'); ?></td>
                                    <td><code><?php echo htmlspecialchars($ai['serial']); ?></code></td>
                                    <td><?php echo htmlspecialchars($ai['condition']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-secondary" onclick="closeModal('employeeProfileModal'); openChangePasswordModal();" style="display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Change Password
                </button>
                <button type="button" class="btn-primary modal-cancel-btn">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 5: FIRST-TIME LOGIN & CHANGE PASSWORD MODAL
     ========================================================================= -->
<div class="modal-overlay" id="changePasswordModal" style="display: none; z-index: 9999;">
    <div class="modal-box" style="max-width: 460px; border-top: 4px solid var(--cyan-primary);">
        <div class="modal-header" style="padding: 18px 20px 14px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(0, 147, 167, 0.12); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div>
                    <h3 id="pwdModalTitle" style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--navy-primary);">
                        Change Account Password
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                        Update your portal login credentials.
                    </p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('changePasswordModal')">&times;</button>
        </div>

        <form id="changePasswordForm">
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="currentPasswordInput" style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Current / Default Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="currentPasswordInput" required placeholder="Enter current default password" style="width: 100%; padding: 10px 60px 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box;">
                        <button type="button" class="pwd-toggle-btn" onclick="toggleInputType('currentPasswordInput', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 11.5px; font-weight: 600; padding: 4px 6px;">
                            Show
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="newPasswordInput" style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        New Password * <span style="font-size: 11px; font-weight: 400; color: var(--text-muted);">(Min. 6 characters)</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="newPasswordInput" required minlength="6" placeholder="Enter new password" style="width: 100%; padding: 10px 60px 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box;">
                        <button type="button" class="pwd-toggle-btn" onclick="toggleInputType('newPasswordInput', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 11.5px; font-weight: 600; padding: 4px 6px;">
                            Show
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="confirmPasswordInput" style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Confirm New Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="confirmPasswordInput" required minlength="6" placeholder="Re-enter new password" style="width: 100%; padding: 10px 60px 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box;">
                        <button type="button" class="pwd-toggle-btn" onclick="toggleInputType('confirmPasswordInput', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 11.5px; font-weight: 600; padding: 4px 6px;">
                            Show
                        </button>
                    </div>
                    <small id="pwdMatchMsg" style="display: block; margin-top: 4px; font-size: 11px;"></small>
                </div>

            </div>

            <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                <button type="submit" class="btn-primary" id="savePasswordBtn" style="padding: 9px 20px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Save Password & Continue</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include Layout Footer
include 'includes/footer.php';
?>
