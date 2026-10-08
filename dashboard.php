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

// Calculate live utilization percentage
$utilizationPct = $totalAssets > 0 ? round(($inUseAssets / $totalAssets) * 100, 1) : 0;

// Include Modular Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Dashboard Body Content -->
<main class="dashboard-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h1>Asset & Service Desk Operations</h1>
            <p>Welcome back! Real-time operational overview across hardware assets, allocations, and categories.</p>
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
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
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

        <!-- Metric 4: Active Employees -->
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

    <!-- Dashboard Main Grid: Table & Widgets -->
    <div class="dashboard-grid">

        <!-- Left Column: Recent Assets Inventory Table -->
        <div class="content-card">
            <div class="card-header">
                <h2>Recent Assets in Inventory</h2>
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

        <!-- Right Column: Asset Inventory Distribution & Activity -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Asset Distribution Widget -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Asset Categories</h2>
                </div>
                <div class="asset-category-list">
                    <?php if (!empty($categoriesDist)): ?>
                        <?php 
                            $colors = ['progress-cyan', 'progress-navy', 'progress-amber', 'progress-green'];
                            $cIdx = 0;
                            foreach ($categoriesDist as $cd): 
                                $pct = $totalAssets > 0 ? round(($cd['count'] / $totalAssets) * 100) : 0;
                                $colorClass = $colors[$cIdx % count($colors)];
                                $cIdx++;
                        ?>
                            <div class="category-row">
                                <div class="category-meta">
                                    <span><?php echo htmlspecialchars($cd['name']); ?></span>
                                    <strong><?php echo number_format($cd['count']); ?> Units (<?php echo $pct; ?>%)</strong>
                                </div>
                                <div class="category-progress">
                                    <div class="progress-fill <?php echo $colorClass; ?>" style="width: <?php echo max(5, $pct); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 24px 16px; color: var(--text-secondary); font-size: 13px;">
                            No categorized assets found in database.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity Feed Widget -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Inventory Activity</h2>
                </div>
                <div class="activity-feed">
                    <?php if (!empty($recentAssets)): ?>
                        <?php foreach (array_slice($recentAssets, 0, 4) as $ra): ?>
                            <div class="activity-item">
                                <div class="activity-dot">💻</div>
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

<?php
// Include Modular Footer & Modals
include 'includes/footer.php';
?>
