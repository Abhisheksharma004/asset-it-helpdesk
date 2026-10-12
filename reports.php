<?php
// Asset Management & IT Service Desk - Unified Reports Center
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/config/db.php';

$page_title = "Reports & Analytics - VIROS IT Portal";
$active_page = "reports";

// 1. Asset Statistics
$totalAssets = 0;
$assignedAssets = 0;
$availableAssets = 0;
$maintenanceAssets = 0;

$astRes = sqlsrv_query($conn, "SELECT status, COUNT(*) AS cnt FROM assets WHERE status != 'Archived' GROUP BY status");
if ($astRes !== false) {
    while ($r = sqlsrv_fetch_array($astRes, SQLSRV_FETCH_ASSOC)) {
        $st = strtolower(trim($r['status'] ?? ''));
        $cnt = (int)$r['cnt'];
        $totalAssets += $cnt;
        if (strpos($st, 'assign') !== false || strpos($st, 'deploy') !== false || strpos($st, 'in use') !== false) {
            $assignedAssets += $cnt;
        } elseif (strpos($st, 'avail') !== false || strpos($st, 'ready') !== false || strpos($st, 'stock') !== false) {
            $availableAssets += $cnt;
        } elseif (strpos($st, 'repair') !== false || strpos($st, 'maint') !== false) {
            $maintenanceAssets += $cnt;
        }
    }
    sqlsrv_free_stmt($astRes);
}

// 2. Software Licenses Expiry Count
$expiringLicCount = 0;
$totalLicCost = 0;
$licRes = sqlsrv_query($conn, "SELECT expiry_date, cost FROM software_licenses WHERE status = 'Active'");
if ($licRes !== false) {
    $now = new DateTime();
    while ($lr = sqlsrv_fetch_array($licRes, SQLSRV_FETCH_ASSOC)) {
        $totalLicCost += (float)($lr['cost'] ?? 0);
        if (!empty($lr['expiry_date'])) {
            $expDate = $lr['expiry_date'] instanceof DateTime ? $lr['expiry_date'] : new DateTime($lr['expiry_date']);
            $diff = (int)$now->diff($expDate)->format('%r%a');
            if ($diff <= 60) {
                $expiringLicCount++;
            }
        }
    }
    sqlsrv_free_stmt($licRes);
}

// 3. Ticket SLA Stats
$openTickets = 0;
$urgentTickets = 0;
$tktRes = sqlsrv_query($conn, "SELECT priority, status FROM tickets WHERE status NOT IN ('Closed', 'Resolved')");
if ($tktRes !== false) {
    while ($tr = sqlsrv_fetch_array($tktRes, SQLSRV_FETCH_ASSOC)) {
        $openTickets++;
        if (strtolower($tr['priority'] ?? '') === 'urgent' || strtolower($tr['priority'] ?? '') === 'high') {
            $urgentTickets++;
        }
    }
    sqlsrv_free_stmt($tktRes);
}

// 4. Asset List Query for Table
$reportAssets = [];
$astListSql = "SELECT TOP 100 
                a.id, a.asset_tag, a.name, a.serial_number, a.status, 
                c.category_name, d.department_name,
                CONVERT(VARCHAR(10), a.created_at, 105) AS added_date
               FROM assets a
               LEFT JOIN asset_categories c ON a.category_id = c.id
               LEFT JOIN departments d ON a.department_id = d.id
               WHERE a.status != 'Archived'
               ORDER BY a.id DESC";
$astListStmt = sqlsrv_query($conn, $astListSql);
if ($astListStmt !== false) {
    while ($row = sqlsrv_fetch_array($astListStmt, SQLSRV_FETCH_ASSOC)) {
        $reportAssets[] = $row;
    }
    sqlsrv_free_stmt($astListStmt);
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<main class="dashboard-content">

    <!-- Page Header Bar -->
    <div class="page-header-bar" style="margin-bottom: 24px;">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Reports</span>
            </div>
            <h1 style="font-size: 22px; font-weight: 700; color: var(--navy-deep); margin: 4px 0 0;">Unified System Reports</h1>
        </div>
        <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
            <button onclick="window.print()" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Report
            </button>
            <button onclick="exportReportToCSV()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 6px; background: var(--cyan-primary); color: #fff; border: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="metric-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(0, 147, 167, 0.1); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Total Hardware Assets</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--navy-deep); margin-top: 2px;"><?php echo number_format($totalAssets); ?></div>
            </div>
        </div>

        <div class="metric-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(5, 150, 105, 0.1); color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Assigned / Deployed</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--navy-deep); margin-top: 2px;"><?php echo number_format($assignedAssets); ?></div>
            </div>
        </div>

        <div class="metric-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(217, 119, 6, 0.1); color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Expiring Licenses</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--navy-deep); margin-top: 2px;"><?php echo number_format($expiringLicCount); ?></div>
            </div>
        </div>

        <div class="metric-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(220, 38, 38, 0.1); color: #dc2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Open IT Incidents</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--navy-deep); margin-top: 2px;"><?php echo number_format($openTickets); ?></div>
            </div>
        </div>
    </div>

    <!-- Reports Data Table Card -->
    <div class="content-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
            <div>
                <h2 style="font-size: 17px; font-weight: 700; color: var(--navy-deep); margin: 0;">Asset Inventory Audit Log</h2>
                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0;">Comprehensive snapshot of registered physical hardware assets</p>
            </div>
            <div>
                <input type="text" id="reportSearchInput" placeholder="Filter by tag, name or dept..." style="padding: 8px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px; width: 260px;">
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="reportAuditTable" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--bg-surface); text-align: left; border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Asset Tag</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Asset Name</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Category</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Department</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Status</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Date Logged</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reportAssets)): ?>
                        <?php foreach ($reportAssets as $row): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 14px;"><code style="background: rgba(0, 147, 167, 0.08); color: var(--cyan-primary); padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?php echo htmlspecialchars($row['asset_tag']); ?></code></td>
                                <td style="padding: 12px 14px; font-weight: 600; color: var(--navy-deep);"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="padding: 12px 14px;"><?php echo htmlspecialchars($row['category_name'] ?? 'General'); ?></td>
                                <td style="padding: 12px 14px;"><?php echo htmlspecialchars($row['department_name'] ?? 'Unallocated'); ?></td>
                                <td style="padding: 12px 14px;">
                                    <?php 
                                    $st = htmlspecialchars($row['status'] ?? 'Available'); 
                                    $badgeStyle = "padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;";
                                    if ($st === 'Assigned') {
                                        $badgeStyle .= "background: #e0f2fe; color: #0284c7;";
                                    } elseif ($st === 'Available') {
                                        $badgeStyle .= "background: #ecfdf5; color: #059669;";
                                    } else {
                                        $badgeStyle .= "background: #fef3c7; color: #d97706;";
                                    }
                                    ?>
                                    <span style="<?php echo $badgeStyle; ?>"><?php echo $st; ?></span>
                                </td>
                                <td style="padding: 12px 14px; color: var(--text-secondary);"><?php echo htmlspecialchars($row['added_date'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 32px 16px; color: var(--text-secondary);">No asset audit records found in the database.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("reportSearchInput");
    const table = document.getElementById("reportAuditTable");
    if (searchInput && table) {
        searchInput.addEventListener("input", function () {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll("tbody tr");
            rows.forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
            });
        });
    }
});

function exportReportToCSV() {
    const table = document.getElementById("reportAuditTable");
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll("tr");
    rows.forEach(function (row) {
        if (row.style.display === "none") return;
        const cols = row.querySelectorAll("th, td");
        let rowData = [];
        cols.forEach(function (col) {
            rowData.push('"' + col.innerText.replace(/"/g, '""').trim() + '"');
        });
        csv.push(rowData.join(","));
    });
    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "viros_asset_report_" + new Date().toISOString().slice(0, 10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include 'includes/footer.php'; ?>
