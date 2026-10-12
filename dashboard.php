<?php
// Asset Management & IT Service Desk Portal - Dashboard Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Dashboard - Asset Management & IT Service Desk";
$active_page = "dashboard";
$extra_js = ['js/chart.min.js', 'js/dashboard_analytics.js'];

// Include Database
require_once __DIR__ . '/config/db.php';

// Live Metrics from MS SQL Server Database
$totalAssets = 0;
$inUseAssets = 0;
$availableAssets = 0;
$maintenanceAssets = 0;
$retiredAssets = 0;

if (isset($conn) && $conn !== false) {
    $aStmt = sqlsrv_query($conn, "SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'In Use' THEN 1 ELSE 0 END) AS in_use,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) AS maintenance,
        SUM(CASE WHEN status = 'Retired' THEN 1 ELSE 0 END) AS retired
    FROM assets");
    if ($aStmt !== false && ($aRow = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC))) {
        $totalAssets = intval($aRow['total'] ?? 0);
        $inUseAssets = intval($aRow['in_use'] ?? 0);
        $availableAssets = intval($aRow['available'] ?? 0);
        $maintenanceAssets = intval($aRow['maintenance'] ?? 0);
        $retiredAssets = intval($aRow['retired'] ?? 0);
        sqlsrv_free_stmt($aStmt);
    }
}

// Total Assignments
$totalAssignments = 0;
if (isset($conn) && $conn !== false) {
    $asStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM asset_assignments");
    if ($asStmt !== false && ($asRow = sqlsrv_fetch_array($asStmt, SQLSRV_FETCH_ASSOC))) {
        $totalAssignments = intval($asRow['total'] ?? 0);
        sqlsrv_free_stmt($asStmt);
    }
}

// Total Employees
$totalEmployees = 0;
if (isset($conn) && $conn !== false) {
    $eStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status = 'Active'");
    if ($eStmt !== false && ($eRow = sqlsrv_fetch_array($eStmt, SQLSRV_FETCH_ASSOC))) {
        $totalEmployees = intval($eRow['total'] ?? 0);
        sqlsrv_free_stmt($eStmt);
    }
}

// Live Categories Distribution from Database
$categoriesDist = [];
if (isset($conn) && $conn !== false) {
    $cStmt = sqlsrv_query($conn, "SELECT category, COUNT(*) AS cnt FROM assets WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY cnt DESC");
    if ($cStmt !== false) {
        while ($cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC)) {
            $categoriesDist[] = [
                'name' => $cRow['category'],
                'count' => intval($cRow['cnt'])
            ];
        }
        sqlsrv_free_stmt($cStmt);
    }
}

// Live Recent Assets from Database
$recentAssets = [];
if (isset($conn) && $conn !== false) {
    $rStmt = sqlsrv_query($conn, "SELECT TOP 6 id, tag, name, category, brand, model, serial, status, condition, location, department, assigned_to, 
        CONVERT(VARCHAR(10), created_at, 105) AS created_date
    FROM assets ORDER BY id DESC");
    if ($rStmt !== false) {
        while ($r = sqlsrv_fetch_array($rStmt, SQLSRV_FETCH_ASSOC)) {
            $recentAssets[] = $r;
        }
        sqlsrv_free_stmt($rStmt);
    }
}

// Live Software Licenses & Expiry Tracking from Database
$expiringLicenses = [];
if (isset($conn) && $conn !== false) {
    $licTableCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='software_licenses' AND xtype='U'");
    if ($licTableCheck !== false && sqlsrv_has_rows($licTableCheck)) {
        $licStmt = sqlsrv_query($conn, "SELECT id, software_name, publisher, category, version, license_type, expiry_date, status, total_cost 
                                        FROM software_licenses 
                                        ORDER BY id DESC");
        if ($licStmt !== false) {
            $todayTs = strtotime(date('Y-m-d'));
            while ($lRow = sqlsrv_fetch_array($licStmt, SQLSRV_FETCH_ASSOC)) {
                $swName = trim($lRow['software_name'] ?? '');
                if ($swName === '') continue;

                $expStr = trim($lRow['expiry_date'] ?? '');
                $isPerpetual = ($expStr === '' || strcasecmp($expStr, 'Perpetual') === 0 || strcasecmp($expStr, 'Lifetime') === 0);

                $diffDays = null;
                $statusLabel = 'Active';
                $badgeClass = 'badge-resolved';
                $expTs = false;

                if (!$isPerpetual) {
                    $expTs = strtotime($expStr);
                }

                if ($expTs !== false) {
                    $diffDays = (int) floor(($expTs - $todayTs) / 86400);
                    $formattedDate = date('d M Y', $expTs);

                    if ($diffDays < 0) {
                        $absDays = abs($diffDays);
                        $statusLabel = 'Expired ' . ($absDays === 1 ? '1d' : "{$absDays}d") . ' ago';
                        $badgeClass = 'badge-urgent';
                        $urgencyRank = 1;
                    } elseif ($diffDays <= 30) {
                        $statusLabel = ($diffDays === 0) ? 'Expires Today!' : "Expires in {$diffDays}d";
                        $badgeClass = 'badge-open';
                        $urgencyRank = 2;
                    } elseif ($diffDays <= 90) {
                        $statusLabel = "Expires in {$diffDays}d";
                        $badgeClass = 'badge-progress';
                        $urgencyRank = 3;
                    } else {
                        $statusLabel = "{$diffDays} days left";
                        $badgeClass = 'badge-resolved';
                        $urgencyRank = 4;
                    }
                } else {
                    $formattedDate = 'Lifetime / Perpetual';
                    $statusLabel = 'Perpetual';
                    $badgeClass = 'badge-medium';
                    $urgencyRank = 5;
                }

                $expiringLicenses[] = [
                    'id'             => intval($lRow['id']),
                    'name'           => $swName,
                    'publisher'      => trim($lRow['publisher'] ?? ''),
                    'category'       => trim($lRow['category'] ?? ''),
                    'expiry_date'    => $formattedDate,
                    'diff_days'      => $diffDays,
                    'status_label'   => $statusLabel,
                    'badge_class'    => $badgeClass,
                    'urgency_rank'   => $urgencyRank
                ];
            }
            sqlsrv_free_stmt($licStmt);

            // Sort: highest urgency first (expired, soonest expiry, then future, then perpetual)
            usort($expiringLicenses, function($a, $b) {
                if ($a['urgency_rank'] !== $b['urgency_rank']) {
                    return $a['urgency_rank'] - $b['urgency_rank'];
                }
                if ($a['diff_days'] !== null && $b['diff_days'] !== null) {
                    return $a['diff_days'] - $b['diff_days'];
                }
                return 0;
            });
        }
        sqlsrv_free_stmt($licTableCheck);
    }
}

// Live Support Tickets Metric & List from Database
$totalTickets = 0;
$openTicketsCount = 0;
$recentTickets = [];
if (isset($conn) && $conn !== false) {
    $tCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='support_tickets' AND xtype='U'");
    if ($tCheck !== false && sqlsrv_has_rows($tCheck)) {
        $tStmt = sqlsrv_query($conn, "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status != 'Closed' AND status != 'Resolved' THEN 1 ELSE 0 END) AS open_cnt
        FROM support_tickets");
        if ($tStmt !== false && ($tRow = sqlsrv_fetch_array($tStmt, SQLSRV_FETCH_ASSOC))) {
            $totalTickets = intval($tRow['total'] ?? 0);
            $openTicketsCount = intval($tRow['open_cnt'] ?? 0);
            sqlsrv_free_stmt($tStmt);
        }

        $rtStmt = sqlsrv_query($conn, "SELECT TOP 6 id, ticket_no, subject, category, priority, status, employee_name, emp_code, department, created_at 
                                       FROM support_tickets ORDER BY id DESC");
        if ($rtStmt !== false) {
            while ($t = sqlsrv_fetch_array($rtStmt, SQLSRV_FETCH_ASSOC)) {
                $cDate = '';
                if (!empty($t['created_at'])) {
                    if ($t['created_at'] instanceof DateTime) {
                        $cDate = $t['created_at']->format('d M Y');
                    } else {
                        $cDate = date('d M Y', strtotime($t['created_at']));
                    }
                }
                $t['formatted_date'] = $cDate;
                $recentTickets[] = $t;
            }
            sqlsrv_free_stmt($rtStmt);
        }
        sqlsrv_free_stmt($tCheck);
    }
}

// Recent Custody Allocations & Handover Slips from Database
$recentAssignments = [];
if (isset($conn) && $conn !== false) {
    $asCheck = sqlsrv_query($conn, "SELECT 1 FROM sysobjects WHERE name='asset_assignments' AND xtype='U'");
    if ($asCheck !== false && sqlsrv_has_rows($asCheck)) {
        $asQuery = sqlsrv_query($conn, "SELECT TOP 4 id, slip_no, asset_tag, asset_name, category, employee_name, emp_code, department, custody_status, 
            CONVERT(VARCHAR(10), created_at, 105) AS slip_date 
            FROM asset_assignments ORDER BY id DESC");
        if ($asQuery !== false) {
            while ($asRow = sqlsrv_fetch_array($asQuery, SQLSRV_FETCH_ASSOC)) {
                $recentAssignments[] = $asRow;
            }
            sqlsrv_free_stmt($asQuery);
        }
        sqlsrv_free_stmt($asCheck);
    }
}

// Department Allocation Distribution from Database
$deptDist = [];
if (isset($conn) && $conn !== false) {
    $dStmt = sqlsrv_query($conn, "SELECT department, COUNT(*) AS cnt FROM assets WHERE department IS NOT NULL AND department != '' GROUP BY department ORDER BY cnt DESC");
    if ($dStmt !== false) {
        while ($dRow = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC)) {
            $deptDist[] = [
                'name' => $dRow['department'],
                'count' => intval($dRow['cnt'])
            ];
        }
        sqlsrv_free_stmt($dStmt);
    }
}

// Additional Live Analytics Datasets from Database
$ticketPriorityStats = [
    'urgent' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'resolved' => 0,
    'total' => 0
];
if (isset($conn) && $conn !== false) {
    $tpStmt = sqlsrv_query($conn, "SELECT 
        SUM(CASE WHEN priority = 'Urgent' THEN 1 ELSE 0 END) as urgent_cnt,
        SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as high_cnt,
        SUM(CASE WHEN priority = 'Medium' THEN 1 ELSE 0 END) as medium_cnt,
        SUM(CASE WHEN priority = 'Low' THEN 1 ELSE 0 END) as low_cnt,
        SUM(CASE WHEN status = 'Resolved' OR status = 'Closed' THEN 1 ELSE 0 END) as resolved_cnt,
        COUNT(*) as total_cnt
    FROM support_tickets");
    if ($tpStmt !== false && ($tpRow = sqlsrv_fetch_array($tpStmt, SQLSRV_FETCH_ASSOC))) {
        $ticketPriorityStats = [
            'urgent' => intval($tpRow['urgent_cnt'] ?? 0),
            'high' => intval($tpRow['high_cnt'] ?? 0),
            'medium' => intval($tpRow['medium_cnt'] ?? 0),
            'low' => intval($tpRow['low_cnt'] ?? 0),
            'resolved' => intval($tpRow['resolved_cnt'] ?? 0),
            'total' => intval($tpRow['total_cnt'] ?? 0)
        ];
        sqlsrv_free_stmt($tpStmt);
    }
}

$resolutionRate = $ticketPriorityStats['total'] > 0 
    ? round(($ticketPriorityStats['resolved'] / $ticketPriorityStats['total']) * 100, 1) 
    : 100;

// Software Spend & Categories from Database
$softwareSpendCats = [];
$totalSoftwareSpend = 0;
if (isset($conn) && $conn !== false) {
    $swQuery = sqlsrv_query($conn, "SELECT category, COUNT(*) as cnt, SUM(total_cost) as total_spend 
                                   FROM software_licenses 
                                   WHERE category IS NOT NULL AND category != '' 
                                   GROUP BY category 
                                   ORDER BY total_spend DESC");
    if ($swQuery !== false) {
        while ($swRow = sqlsrv_fetch_array($swQuery, SQLSRV_FETCH_ASSOC)) {
            $catSpend = floatval($swRow['total_spend'] ?? 0);
            $totalSoftwareSpend += $catSpend;
            $softwareSpendCats[] = [
                'name'  => $swRow['category'],
                'count' => intval($swRow['cnt']),
                'spend' => $catSpend
            ];
        }
        sqlsrv_free_stmt($swQuery);
    }
}

// Hardware Physical Condition Breakdown
$hardwareConditions = [];
if (isset($conn) && $conn !== false) {
    $hStmt = sqlsrv_query($conn, "SELECT condition, COUNT(*) as cnt FROM assets WHERE condition IS NOT NULL AND condition != '' GROUP BY condition");
    if ($hStmt !== false) {
        while ($hRow = sqlsrv_fetch_array($hStmt, SQLSRV_FETCH_ASSOC)) {
            $hardwareConditions[] = [
                'name'  => $hRow['condition'],
                'count' => intval($hRow['cnt'])
            ];
        }
        sqlsrv_free_stmt($hStmt);
    }
}

// Calculate live utilization percentage
$utilizationPct = $totalAssets > 0 ? round(($inUseAssets / $totalAssets) * 100, 1) : 0;

// Include Modular Components
include 'includes/header.php';
include 'includes/sidebar.php';
$hide_topbar_actions = true;
include 'includes/topbar.php';
?>

<!-- Dashboard Body Content -->
<main class="dashboard-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h1>Asset & Service Desk Operations</h1>
            <p>Welcome back! Real-time operational command center across hardware assets, allocations, tickets, and software.</p>
        </div>
        <div class="welcome-stats">
            <div class="welcome-badge">
                <div class="num"><?php echo $utilizationPct; ?>%</div>
                <div class="lbl">Asset Utilization</div>
            </div>
            <div class="welcome-badge">
                <div class="num"><?php echo count($categoriesDist); ?></div>
                <div class="lbl">Active Categories</div>
            </div>
            <div class="welcome-badge">
                <div class="num"><?php echo number_format($openTicketsCount); ?></div>
                <div class="lbl">Open Incidents</div>
            </div>
        </div>
    </div>

    <!-- Quick Operations Command Toolbar -->
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 18px; box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <div>
                <div style="font-size: 13.5px; font-weight: 700; color: var(--navy-primary); line-height: 1.2;">Operations Command Center</div>
                <div style="font-size: 11.5px; color: var(--text-muted);">Quick shortcuts for daily asset, helpdesk, and software tasks</div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <a href="assets.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Add Asset</span>
            </a>
            <a href="asset_assignment.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                <span>Assign Asset</span>
            </a>
            <a href="asset_return.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                <span>Return Asset</span>
            </a>
            <a href="asset_transfer.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line></svg>
                <span>Transfer</span>
            </a>
            <a href="tickets.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                <span>Tickets</span>
            </a>
            <a href="software_licenses.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Licenses</span>
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards Grid (6 Live Operational Cards) -->
    <div class="metrics-grid">
        
        <!-- Metric 1: Total Assets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Total Managed Assets</span>
                <div class="metric-icon-wrap cyan">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($totalAssets); ?></div>
            <div class="metric-footer">
                <span class="trend-up"><?php echo $utilizationPct; ?>% in service</span>
                <span>• Live SQL Server data</span>
            </div>
        </div>

        <!-- Metric 2: Deployed Assets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Assigned & Deployed</span>
                <div class="metric-icon-wrap warning">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($inUseAssets); ?></div>
            <div class="metric-footer">
                <span class="trend-neutral"><?php echo number_format($totalAssignments); ?> Handover Slips</span>
                <span>• In Active Use</span>
            </div>
        </div>

        <!-- Metric 3: Ready to Assign -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Stock & Ready to Assign</span>
                <div class="metric-icon-wrap navy">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($availableAssets); ?></div>
            <div class="metric-footer">
                <span class="trend-up">Available</span>
                <span>In Storage / Inventory</span>
            </div>
        </div>

        <!-- Metric 4: Open Support Tickets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Open Support Tickets</span>
                <div class="metric-icon-wrap warning" style="background: #fef3c7; color: #b45309;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($openTicketsCount); ?></div>
            <div class="metric-footer">
                <span class="trend-up" style="color: #d97706;"><?php echo number_format($totalTickets); ?> Total Tickets</span>
                <span>• Incident Queue</span>
            </div>
        </div>

        <!-- Metric 5: Software & Subscriptions -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Software Licenses</span>
                <div class="metric-icon-wrap cyan" style="background: #ede9fe; color: #7c3aed;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo count($expiringLicenses); ?></div>
            <div class="metric-footer">
                <span class="trend-up" style="color: #7c3aed;">Active Licenses</span>
                <span>• Expiry tracking</span>
            </div>
        </div>

        <!-- Metric 6: Active Employees -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Active Employees</span>
                <div class="metric-icon-wrap success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
            </div>
            <div class="metric-value"><?php echo number_format($totalEmployees); ?></div>
            <div class="metric-footer">
                <span class="trend-up">Workforce</span>
                <span>Active Employee Master</span>
            </div>
        </div>

    </div>

    <!-- Analytics Section 1: Hardware Lifecycle & Infrastructure Analytics -->
    <div class="analytics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
        
        <!-- Chart 1: Asset Lifecycle & Status Breakdown (Doughnut Chart) -->
        <div class="content-card">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                        <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                    </svg>
                    <h2 style="margin: 0; font-size: 15px;">Asset Lifecycle & Status Analytics</h2>
                </div>
                <div class="card-header-actions">
                    <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 600; background: #f1f5f9; padding: 4px 10px; border-radius: 12px;">Live Inventory</span>
                </div>
            </div>
            <div style="padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <div style="position: relative; width: 100%; max-width: 280px; height: 230px; margin: 0 auto;">
                    <canvas id="assetStatusChart"></canvas>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 22px; font-weight: 800; color: var(--navy-primary); line-height: 1;"><?php echo number_format($totalAssets); ?></div>
                        <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 4px;">Total Assets</div>
                    </div>
                </div>
                <!-- Custom Modern Chart Legend -->
                <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 18px; font-size: 12px; border-top: 1px solid var(--border-color); padding-top: 14px; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #0891b2; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">In Use (<strong><?php echo $inUseAssets; ?></strong>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">Available (<strong><?php echo $availableAssets; ?></strong>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">Maintenance (<strong><?php echo $maintenanceAssets; ?></strong>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">Retired (<strong><?php echo $retiredAssets; ?></strong>)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Department-wise Hardware Allocation (Bar Chart) -->
        <div class="content-card">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                        <line x1="18" y1="20" x2="18" y2="10"></line>
                        <line x1="12" y1="20" x2="12" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="14"></line>
                    </svg>
                    <h2 style="margin: 0; font-size: 15px;">Department Deployment Analytics</h2>
                </div>
                <div class="card-header-actions">
                    <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 600; background: #f1f5f9; padding: 4px 10px; border-radius: 12px;">Active Units</span>
                </div>
            </div>
            <div style="padding: 20px; height: 310px; display: flex; flex-direction: column;">
                <div style="flex: 1; position: relative; width: 100%; height: 100%;">
                    <canvas id="deptAllocationChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Live Operational Telemetry & KPI Ribbon -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Fleet Health</div>
                <div style="font-size: 16px; font-weight: 800; color: #10b981; margin-top: 2px;">100% Operational</div>
            </div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
            </div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Software Investment</div>
                <div style="font-size: 16px; font-weight: 800; color: #7c3aed; margin-top: 2px;">₹<?php echo number_format($totalSoftwareSpend); ?></div>
            </div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #ede9fe; color: #7c3aed; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Ticket Resolution</div>
                <div style="font-size: 16px; font-weight: 800; color: #0284c7; margin-top: 2px;"><?php echo $resolutionRate; ?>% Rate</div>
            </div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="12" r="6"></circle>
                    <circle cx="12" cy="12" r="2"></circle>
                </svg>
            </div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Inventory Buffer</div>
                <div style="font-size: 16px; font-weight: 800; color: var(--navy-primary); margin-top: 2px;"><?php echo $availableAssets; ?> Units Ready</div>
            </div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #f1f5f9; color: var(--navy-primary); display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
        </div>
    </div>

    <!-- Analytics Section 2: Service Desk & Subscriptions Analytics Grid -->
    <div class="analytics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
        
        <!-- Chart 3: Helpdesk Incident Priority & Triage -->
        <div class="content-card">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <h2 style="margin: 0; font-size: 15px;">Support Incidents by Priority & Triage</h2>
                </div>
                <div class="card-header-actions">
                    <span style="font-size: 11.5px; color: #0284c7; font-weight: 700; background: #e0f2fe; padding: 4px 10px; border-radius: 12px;"><?php echo $resolutionRate; ?>% Resolved</span>
                </div>
            </div>
            <div style="padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <div style="position: relative; width: 100%; max-width: 280px; height: 230px; margin: 0 auto;">
                    <canvas id="ticketPriorityChart"></canvas>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                        <div style="font-size: 22px; font-weight: 800; color: var(--navy-primary); line-height: 1;"><?php echo number_format($totalTickets); ?></div>
                        <div style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 4px;">Total Tickets</div>
                    </div>
                </div>
                <!-- Custom Modern Chart Legend -->
                <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 18px; font-size: 12px; border-top: 1px solid var(--border-color); padding-top: 14px; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">Urgent (<strong><?php echo $ticketPriorityStats['urgent']; ?></strong>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #f97316; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">High (<strong><?php echo $ticketPriorityStats['high']; ?></strong>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #eab308; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">Medium (<strong><?php echo $ticketPriorityStats['medium']; ?></strong>)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                        <span style="color: var(--text-secondary); font-weight: 500;">Low (<strong><?php echo $ticketPriorityStats['low']; ?></strong>)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 4: Software License Investment & Category Spend -->
        <div class="content-card">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <h2 style="margin: 0; font-size: 15px;">Software Investment & Spend Analytics</h2>
                </div>
                <div class="card-header-actions">
                    <span style="font-size: 11.5px; color: #7c3aed; font-weight: 700; background: #ede9fe; padding: 4px 10px; border-radius: 12px;">₹<?php echo number_format($totalSoftwareSpend); ?> Total</span>
                </div>
            </div>
            <div style="padding: 20px; height: 310px; display: flex; flex-direction: column;">
                <div style="flex: 1; position: relative; width: 100%; height: 100%;">
                    <canvas id="softwareSpendChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Dashboard Main Grid: Table & Widgets -->
    <div class="dashboard-grid">

        <!-- Left Column: Recent Assets & Active Support Incidents -->
        <div style="display: flex; flex-direction: column; gap: 24px; min-width: 0;">

            <!-- Left Card 1: Recent Assets in Inventory Table -->
            <div class="content-card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                        <h2 style="margin: 0;">Recent Assets in Inventory</h2>
                    </div>
                    <div class="card-header-actions">
                        <a href="assets.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <span>View All Assets</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name & Category</th>
                                <th>Assigned To</th>
                                <th>Location / Dept</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentAssets)): ?>
                                <?php foreach ($recentAssets as $ra): ?>
                                    <?php
                                        $st = $ra['status'] ?? 'Available';
                                        $badgeClass = 'badge-open';
                                        if ($st === 'In Use') $badgeClass = 'badge-progress';
                                        elseif ($st === 'Under Maintenance') $badgeClass = 'badge-urgent';
                                        elseif ($st === 'Retired') $badgeClass = 'badge-medium';
                                        elseif ($st === 'Available') $badgeClass = 'badge-resolved';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-id" style="font-weight: 700; color: var(--navy-primary); font-family: monospace;">
                                                <?php echo htmlspecialchars($ra['tag'] ?? ('AST-' . $ra['id'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ticket-subject"><?php echo htmlspecialchars($ra['name'] ?? 'Asset'); ?></span>
                                            <span class="ticket-category">
                                                <?php echo htmlspecialchars($ra['category'] ?? 'General'); ?>
                                                <?php if (!empty($ra['brand'])): ?> • <?php echo htmlspecialchars($ra['brand']); ?><?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($ra['assigned_to'])): ?>
                                                <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($ra['assigned_to']); ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span><?php echo htmlspecialchars($ra['location'] ?? 'HQ'); ?></span>
                                            <?php if (!empty($ra['department'])): ?>
                                                <span style="display: block; font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($ra['department']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st); ?></span>
                                        </td>
                                        <td>
                                            <a href="assets.php?search=<?php echo urlencode($ra['tag'] ?? ''); ?>" class="action-btn-sm" title="View Asset" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 32px 16px; color: var(--text-secondary);">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 8px; opacity: 0.5;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                        <div>No assets registered in the database yet.</div>
                                        <a href="assets.php" style="display: inline-block; margin-top: 8px; color: var(--cyan-primary); font-weight: 600; text-decoration: none;">+ Add First Asset</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Left Card 2: Active IT Support Tickets & Incidents -->
            <div class="content-card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                        <h2 style="margin: 0;">Active IT Support Incidents</h2>
                    </div>
                    <div class="card-header-actions">
                        <a href="tickets.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <span>All Tickets (<?php echo $totalTickets; ?>)</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Ticket No</th>
                                <th>Subject & Category</th>
                                <th>Requester</th>
                                <th>Department</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentTickets)): ?>
                                <?php foreach ($recentTickets as $rt): ?>
                                    <?php
                                        $tStatus = $rt['status'] ?? 'Open';
                                        $tBadge = 'badge-open';
                                        if ($tStatus === 'In Progress') $tBadge = 'badge-progress';
                                        elseif ($tStatus === 'Resolved') $tBadge = 'badge-resolved';
                                        elseif ($tStatus === 'Closed') $tBadge = 'badge-medium';

                                        $prio = $rt['priority'] ?? 'Medium';
                                        $pBadge = 'badge-open';
                                        if ($prio === 'Urgent') $pBadge = 'badge-urgent';
                                        elseif ($prio === 'High') $pBadge = 'badge-urgent';
                                        elseif ($prio === 'Low') $pBadge = 'badge-resolved';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-id" style="font-weight: 700; color: var(--navy-primary); font-family: monospace;">
                                                <?php echo htmlspecialchars($rt['ticket_no'] ?? ('#TK-' . $rt['id'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ticket-subject"><?php echo htmlspecialchars($rt['subject'] ?? 'Support Ticket'); ?></span>
                                            <span class="ticket-category"><?php echo htmlspecialchars($rt['category'] ?? 'General Support'); ?></span>
                                        </td>
                                        <td>
                                            <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($rt['employee_name'] ?? 'Staff'); ?></span>
                                            <?php if (!empty($rt['emp_code'])): ?>
                                                <span style="display: block; font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($rt['emp_code']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span><?php echo htmlspecialchars($rt['department'] ?? 'General'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $pBadge; ?>"><?php echo htmlspecialchars($prio); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $tBadge; ?>"><?php echo htmlspecialchars($tStatus); ?></span>
                                        </td>
                                        <td>
                                            <a href="tickets.php?id=<?php echo urlencode($rt['id']); ?>" class="action-btn-sm" title="View & Respond Ticket" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 28px 16px; color: var(--text-secondary);">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 6px; opacity: 0.5;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                                        <div>No support tickets in queue right now.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column: Operations Widgets & Distribution -->
        <div style="display: flex; flex-direction: column; gap: 24px; min-width: 0;">

            <!-- Widget 1: Software License Expiry Alerts Widget -->
            <div class="content-card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <path d="M21 2l-2 2m-1-1l-3 3m-2-2l-3 3m-2-2l-2 2m-1-1l-2 2"></path>
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <h2 style="margin: 0; font-size: 15px;">Expiring Software Licenses</h2>
                    </div>
                    <div class="card-header-actions">
                        <a href="software_licenses.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <span>Manage</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>
                </div>
                <div class="license-expiry-list" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 10px;">
                    <?php if (!empty($expiringLicenses)): ?>
                        <?php foreach (array_slice($expiringLicenses, 0, 5) as $lic): ?>
                            <a href="software_licenses.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='var(--cyan-primary)';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='var(--border-color)';">
                                <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                            <line x1="8" y1="21" x2="16" y2="21"></line>
                                            <line x1="12" y1="17" x2="12" y2="21"></line>
                                        </svg>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 700; color: var(--navy-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($lic['name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted); display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                            <?php if (!empty($lic['publisher'])): ?>
                                                <span style="font-weight: 600; color: var(--text-secondary);"><?php echo htmlspecialchars($lic['publisher']); ?></span>
                                                <span>•</span>
                                            <?php endif; ?>
                                            <span>Expiry: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($lic['expiry_date']); ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                                <div style="flex-shrink: 0;">
                                    <span class="badge <?php echo $lic['badge_class']; ?>" style="font-size: 11px; padding: 4px 10px;">
                                        <?php echo htmlspecialchars($lic['status_label']); ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 24px 16px; color: var(--text-secondary); font-size: 13px;">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="margin-bottom: 8px; opacity: 0.5;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 3px;">No Software Licenses Found</div>
                            <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 12px;">Add software license subscriptions to monitor renewals & expiry dates.</div>
                            <a href="software_licenses.php" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: var(--cyan-primary); color: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">+ Add License</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Widget 2: Recent Custody Allocations & Slips -->
            <div class="content-card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <h2 style="margin: 0; font-size: 15px;">Recent Custody Slips</h2>
                    </div>
                    <div class="card-header-actions">
                        <a href="asset_assignment.php" class="card-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <span>All Slips</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>
                </div>
                <div style="padding: 14px 18px; display: flex; flex-direction: column; gap: 10px;">
                    <?php if (!empty($recentAssignments)): ?>
                        <?php foreach ($recentAssignments as $ra): ?>
                            <?php
                                $cSt = $ra['custody_status'] ?? 'Active';
                                $cBadge = 'badge-resolved';
                                if ($cSt === 'Returned') $cBadge = 'badge-medium';
                                elseif ($cSt === 'Transferred') $cBadge = 'badge-progress';
                            ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px;">
                                <div style="min-width: 0; flex: 1;">
                                    <div style="font-size: 12px; font-weight: 700; color: var(--navy-primary); display: flex; align-items: center; gap: 6px;">
                                        <span><?php echo htmlspecialchars($ra['slip_no']); ?></span>
                                        <?php if (!empty($ra['asset_tag'])): ?>
                                            <span style="font-size: 11px; color: var(--cyan-primary); font-family: monospace;">(<?php echo htmlspecialchars($ra['asset_tag']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        To: <strong><?php echo htmlspecialchars($ra['employee_name']); ?></strong> (<?php echo htmlspecialchars($ra['department']); ?>)
                                    </div>
                                </div>
                                <div>
                                    <span class="badge <?php echo $cBadge; ?>" style="font-size: 10.5px;"><?php echo htmlspecialchars($cSt); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px 14px; color: var(--text-secondary); font-size: 12.5px;">
                            No custody slips created yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Widget 3: Department Deployment Distribution -->
            <?php if (!empty($deptDist)): ?>
            <div class="content-card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <path d="M3 3h18v18H3zM3 9h18M9 21V9"></path>
                        </svg>
                        <h2 style="margin: 0; font-size: 15px;">Department Deployment</h2>
                    </div>
                </div>
                <div class="asset-category-list" style="padding: 16px 20px;">
                    <?php 
                        $dColors = ['progress-cyan', 'progress-navy', 'progress-amber', 'progress-green'];
                        $dIdx = 0;
                        foreach ($deptDist as $dd): 
                            $dPct = $totalAssets > 0 ? round(($dd['count'] / $totalAssets) * 100) : 0;
                            $dColorClass = $dColors[$dIdx % count($dColors)];
                            $dIdx++;
                    ?>
                        <div class="category-row">
                            <div class="category-meta">
                                <span><?php echo htmlspecialchars($dd['name']); ?></span>
                                <strong><?php echo number_format($dd['count']); ?> Assets (<?php echo $dPct; ?>%)</strong>
                            </div>
                            <div class="category-progress">
                                <div class="progress-fill <?php echo $dColorClass; ?>" style="width: <?php echo max(5, $dPct); ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Widget 4: Recent Activity Feed Widget -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Inventory Activity</h2>
                </div>
                <div class="activity-feed">
                    <?php if (!empty($recentAssets)): ?>
                        <?php foreach (array_slice($recentAssets, 0, 4) as $ra): ?>
                            <div class="activity-item">
                                <div class="activity-dot">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                </div>
                                <div class="activity-body">
                                    <div class="activity-text">
                                        <strong><?php echo htmlspecialchars($ra['name']); ?></strong> 
                                        (<code><?php echo htmlspecialchars($ra['tag']); ?></code>) 
                                        under <strong><?php echo htmlspecialchars($ra['category']); ?></strong>
                                    </div>
                                    <div class="activity-time">
                                        Status: <?php echo htmlspecialchars($ra['status'] ?? 'Available'); ?> 
                                        <?php if (!empty($ra['created_date'])): ?>• Added on <?php echo htmlspecialchars($ra['created_date']); ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 24px 16px; color: var(--text-secondary); font-size: 13px;">
                            No recent activity recorded yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</main>

<script>
window.DASHBOARD_ANALYTICS = {
    statusData: {
        inUse: <?php echo intval($inUseAssets); ?>,
        available: <?php echo intval($availableAssets); ?>,
        maintenance: <?php echo intval($maintenanceAssets); ?>,
        retired: <?php echo intval($retiredAssets); ?>
    },
    deptData: <?php echo json_encode($deptDist, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    categoriesData: <?php echo json_encode($categoriesDist, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    ticketPriorityData: <?php echo json_encode($ticketPriorityStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    softwareSpendData: <?php echo json_encode($softwareSpendCats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    hardwareConditions: <?php echo json_encode($hardwareConditions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
</script>

<?php
// Include Modular Footer & Modals
include 'includes/footer.php';
?>
