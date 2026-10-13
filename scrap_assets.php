<?php
// Asset Management & IT Service Desk Portal - Scrap & Decommissioned Assets Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Scrap & Decommissioned Assets - VIROS Portal";
$active_page = "scrap_assets";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/scrap_assets.css'];
$extra_js  = ['js/scrap_assets.js'];

require_once __DIR__ . '/config/db.php';

// Organization Branding for Official Slips
$orgSettings = function_exists('getOrganizationSettings') ? getOrganizationSettings() : [];
$orgName = !empty($orgSettings['organization_legal_name']) ? trim($orgSettings['organization_legal_name']) : 'VIROS PORTAL';
$orgLogo = !empty($orgSettings['primary_brand_logo']) ? $orgSettings['primary_brand_logo'] : 'assets/images/logo.png';
if (!file_exists(__DIR__ . '/' . $orgLogo) && !filter_var($orgLogo, FILTER_VALIDATE_URL)) {
    $orgLogo = 'assets/images/logo.png';
}

// Fetch live assets from DB for modal selector (excluding assets issued/assigned to employees, or already scrapped/retired)
$liveAssets = [];
if (isset($conn) && $conn !== false) {
    $assetQ = "SELECT id, tag, name, category, brand, model, serial, location, department, status, components_json 
               FROM assets 
               WHERE (assigned_to IS NULL OR assigned_to = '') 
                 AND (status IS NULL OR status NOT IN ('In Use', 'Assigned', 'Allocated', 'Scrapped', 'Retired'))
                 AND NOT EXISTS (
                     SELECT 1 FROM asset_assignments aa 
                     WHERE (aa.asset_id = assets.id OR aa.asset_tag = assets.tag) 
                       AND aa.custody_status = 'Active'
                 )
               ORDER BY name ASC";
    $assetStmt = sqlsrv_query($conn, $assetQ);
    if ($assetStmt !== false) {
        while ($ar = sqlsrv_fetch_array($assetStmt, SQLSRV_FETCH_ASSOC)) {
            $liveAssets[] = [
                'id'         => intval($ar['id']),
                'tag'        => $ar['tag'] ?? 'AST-TAG',
                'name'       => $ar['name'] ?? 'Hardware Device',
                'category'   => $ar['category'] ?? 'General Asset',
                'brand'      => $ar['brand'] ?? '',
                'model'      => $ar['model'] ?? '',
                'serial'     => $ar['serial'] ?? 'SN-UNKNOWN',
                'location'   => $ar['location'] ?? 'HQ Facility',
                'department' => $ar['department'] ?? 'IT Department',
                'status'     => $ar['status'] ?? 'Available'
            ];
        }
        sqlsrv_free_stmt($assetStmt);
    }
}



// Build map of installed components for each asset (keyed by asset ID & asset Tag)
$installedComponentsMap = [];
if (isset($conn) && $conn !== false) {
    // 1. From components table where installed_asset is set
    $compQ = "SELECT id, sku, serial, name, category, brand, model, specs, status, installed_asset 
              FROM components 
              WHERE installed_asset IS NOT NULL AND installed_asset != '' 
                AND (status IS NULL OR status != 'Scrapped')";
    $compStmt = sqlsrv_query($conn, $compQ);
    if ($compStmt !== false) {
        while ($cr = sqlsrv_fetch_array($compStmt, SQLSRV_FETCH_ASSOC)) {
            $instKey = trim(strval($cr['installed_asset']));
            $cItem = [
                'id'       => intval($cr['id']),
                'name'     => $cr['name'] ?? 'Component',
                'sku'      => $cr['sku'] ?? '',
                'serial'   => $cr['serial'] ?? '',
                'category' => $cr['category'] ?? 'Component',
                'brand'    => $cr['brand'] ?? '',
                'model'    => $cr['model'] ?? '',
                'status'   => $cr['status'] ?? 'Installed'
            ];
            if (!isset($installedComponentsMap[$instKey])) $installedComponentsMap[$instKey] = [];
            $installedComponentsMap[$instKey][] = $cItem;
        }
        sqlsrv_free_stmt($compStmt);
    }

    // 2. From assets.components_json
    $ajQ = "SELECT id, tag, components_json FROM assets WHERE components_json IS NOT NULL AND components_json != ''";
    $ajStmt = sqlsrv_query($conn, $ajQ);
    if ($ajStmt !== false) {
        while ($ar = sqlsrv_fetch_array($ajStmt, SQLSRV_FETCH_ASSOC)) {
            $aIdStr = strval($ar['id']);
            $aTagStr = trim(strval($ar['tag']));
            $dec = json_decode($ar['components_json'], true);
            if (is_array($dec)) {
                foreach ($dec as $item) {
                    $cId = intval($item['component_id'] ?? ($item['id'] ?? 0));
                    $cSerial = trim(strval($item['serial'] ?? ''));
                    $cItem = [
                        'id'       => $cId,
                        'name'     => $item['name'] ?? 'Installed Component',
                        'sku'      => $item['sku'] ?? ($item['tag'] ?? ''),
                        'serial'   => $cSerial,
                        'category' => $item['category'] ?? 'Component',
                        'brand'    => $item['brand'] ?? '',
                        'model'    => $item['model'] ?? '',
                        'status'   => $item['status'] ?? 'Installed'
                    ];

                    if (!isset($installedComponentsMap[$aIdStr])) $installedComponentsMap[$aIdStr] = [];
                    $dup = false;
                    foreach ($installedComponentsMap[$aIdStr] as $ex) {
                        if (($cId > 0 && $ex['id'] === $cId) || (!empty($cSerial) && $ex['serial'] === $cSerial)) {
                            $dup = true;
                            break;
                        }
                    }
                    if (!$dup) $installedComponentsMap[$aIdStr][] = $cItem;

                    if ($aTagStr !== '') {
                        if (!isset($installedComponentsMap[$aTagStr])) $installedComponentsMap[$aTagStr] = [];
                        $dupTag = false;
                        foreach ($installedComponentsMap[$aTagStr] as $ex) {
                            if (($cId > 0 && $ex['id'] === $cId) || (!empty($cSerial) && $ex['serial'] === $cSerial)) {
                                $dupTag = true;
                                break;
                            }
                        }
                        if (!$dupTag) $installedComponentsMap[$aTagStr][] = $cItem;
                    }
                }
            }
        }
        sqlsrv_free_stmt($ajStmt);
    }
}



// Auto-create scrap_assets table if not exists and fetch live records
$dbScrapRecords = [];
if (isset($conn) && $conn !== false) {
    $tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='scrap_assets' AND xtype='U')
    BEGIN
        CREATE TABLE scrap_assets (
            id INT IDENTITY(1,1) PRIMARY KEY,
            scrap_ref NVARCHAR(50) NOT NULL UNIQUE,
            asset_id INT NULL,
            asset_tag NVARCHAR(100) NOT NULL,
            asset_name NVARCHAR(200) NOT NULL,
            category NVARCHAR(100) NULL,
            brand NVARCHAR(100) NULL,
            model NVARCHAR(100) NULL,
            serial_no NVARCHAR(100) NULL,
            origin_location NVARCHAR(150) NULL,
            reason NVARCHAR(255) NOT NULL,
            disposal_status NVARCHAR(100) NOT NULL DEFAULT 'In Scrap Yard',
            yard_location NVARCHAR(150) NULL,
            salvage_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            authorized_by NVARCHAR(150) NULL,
            notes NVARCHAR(MAX) NULL,
            components_disposition NVARCHAR(MAX) NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            updated_at DATETIME NOT NULL DEFAULT GETDATE()
        );
    END";
    sqlsrv_query($conn, $tableSetupSql);

    $scrSql = "SELECT id, scrap_ref, asset_id, asset_tag, asset_name, category, brand, model, serial_no,
                      origin_location, reason, disposal_status, yard_location, salvage_value, authorized_by, notes,
                      components_disposition, CONVERT(VARCHAR(10), created_at, 105) AS created_date
               FROM scrap_assets 
               ORDER BY id DESC";
    $scrStmt = sqlsrv_query($conn, $scrSql);
    if ($scrStmt !== false) {
        while ($row = sqlsrv_fetch_array($scrStmt, SQLSRV_FETCH_ASSOC)) {
            $st = trim($row['disposal_status'] ?? 'In Scrap Yard');
            $statusKey = 'yard';
            if (stripos($st, 'recycled') !== false || stripos($st, 'e-waste') !== false) {
                $statusKey = 'recycled';
            } elseif (stripos($st, 'sold') !== false) {
                $statusKey = 'sold';
            } elseif (stripos($st, 'written') !== false) {
                $statusKey = 'written-off';
            }

            $dbScrapRecords[] = [
                'id'          => intval($row['id']),
                'ref'         => $row['scrap_ref'],
                'asset_tag'   => $row['asset_tag'] ?? 'AST-0000',
                'name'        => $row['asset_name'] ?? 'Hardware Device',
                'category'    => $row['category'] ?? 'General Asset',
                'brand'       => $row['brand'] ?? '',
                'model'       => $row['model'] ?? '',
                'serial'      => $row['serial_no'] ?? 'N/A',
                'location'    => $row['origin_location'] ?? 'HQ Facility',
                'reason'      => $row['reason'] ?? 'Hardware Condemned',
                'yard'        => $row['yard_location'] ?? 'Scrap Yard Bay',
                'status'      => $statusKey,
                'status_text' => $st,
                'salvage_val' => floatval($row['salvage_value'] ?? 0),
                'date'        => !empty($row['created_date']) ? date('d M Y', strtotime(str_replace('-', '/', $row['created_date']))) : date('d M Y'),
                'approver'    => $row['authorized_by'] ?? 'Abhishek Sharma',
                'notes'       => $row['notes'] ?? '',
                'components_disposition' => $row['components_disposition'] ?? ''
            ];
        }
        sqlsrv_free_stmt($scrStmt);
    }
}

$scrapRecords = $dbScrapRecords;

// Calculate KPI Totals & Dynamic Status Counts
$totalScrappedQty = 0;
$inYardCount = 0;
$recycledCount = 0;
$totalSalvageVal = 0;

$tabCounts = [
    'all'         => count($scrapRecords),
    'yard'        => 0,
    'recycled'    => 0,
    'sold'        => 0,
    'written-off' => 0
];

$uniqueReasons = [];
$uniqueCategories = [];

foreach ($scrapRecords as $r) {
    $totalScrappedQty++;
    $totalSalvageVal += $r['salvage_val'];
    if ($r['status'] === 'yard') $inYardCount++;
    if ($r['status'] === 'recycled') $recycledCount++;

    if (isset($tabCounts[$r['status']])) {
        $tabCounts[$r['status']]++;
    }

    if (!empty($r['reason']) && !in_array($r['reason'], $uniqueReasons)) {
        $uniqueReasons[] = $r['reason'];
    }
    if (!empty($r['category']) && !in_array($r['category'], $uniqueCategories)) {
        $uniqueCategories[] = $r['category'];
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<main class="dashboard-content">

    <!-- Top Breadcrumb & Page Header -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 22px; flex-wrap: wrap; gap: 16px;">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="index.php">Dashboard</a>
                <span>/</span>
                <span>Scrap / Dismantle</span>
                <span>/</span>
                <span>Assets</span>
            </div>
            <h1 style="font-size: 22px; font-weight: 700; color: var(--navy-deep); margin: 4px 0 0;">Scrap &amp; Decommissioned Assets</h1>
            <div class="scrap-header-meta">
                <span style="font-size: 13px; color: var(--text-secondary);">Decommission, write-off, or dispose obsolete, damaged, or beyond-economic-repair IT assets</span>
            </div>
        </div>

        <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn btn-outline" id="exportScrapCsvBtn" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button type="button" class="btn btn-primary" id="openRecordScrapModalBtn" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 6px; background: var(--cyan-primary); color: #fff; border: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Record Scrap Asset
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="scrap-metrics-grid">
        <div class="scrap-metric-card card-total">
            <div class="scrap-metric-icon icon-cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <div class="scrap-metric-body">
                <div class="metric-label">Total Scrapped Assets</div>
                <div class="metric-value" id="kpiTotalAssets"><?php echo number_format($totalScrappedQty); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Devices</span></div>
            </div>
        </div>

        <div class="scrap-metric-card card-yard">
            <div class="scrap-metric-icon icon-amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <div class="scrap-metric-body">
                <div class="metric-label">In Scrap Yard / Storage</div>
                <div class="metric-value" id="kpiYardAssets"><?php echo number_format($inYardCount); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Devices</span></div>
            </div>
        </div>

        <div class="scrap-metric-card card-recycled">
            <div class="scrap-metric-icon icon-green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
            </div>
            <div class="scrap-metric-body">
                <div class="metric-label">E-Waste Recycled</div>
                <div class="metric-value" id="kpiRecycledAssets"><?php echo number_format($recycledCount); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Devices</span></div>
            </div>
        </div>

        <div class="scrap-metric-card card-salvage">
            <div class="scrap-metric-icon icon-indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="scrap-metric-body">
                <div class="metric-label">Realized Salvage Value</div>
                <div class="metric-value" id="kpiSalvageVal">₹<?php echo number_format($totalSalvageVal); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content Table Card -->
    <div class="content-card" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px;">

        <!-- Unified Single Row Toolbar: Status Tabs on Left, Search & Defect Filter on Right -->
        <div class="scrap-unified-row">
            <div class="scrap-filter-tabs">
                <button type="button" class="scrap-tab-btn active" data-tab="all">
                    All Records <span class="scrap-tab-count" id="tabCountAll"><?php echo $tabCounts['all']; ?></span>
                </button>
                <button type="button" class="scrap-tab-btn" data-tab="yard">
                    In Scrap Yard <span class="scrap-tab-count" id="tabCountYard"><?php echo $tabCounts['yard']; ?></span>
                </button>
                <button type="button" class="scrap-tab-btn" data-tab="recycled">
                    E-Waste Recycled <span class="scrap-tab-count" id="tabCountRecycled"><?php echo $tabCounts['recycled']; ?></span>
                </button>
                <button type="button" class="scrap-tab-btn" data-tab="sold">
                    Sold to Vendor <span class="scrap-tab-count" id="tabCountSold"><?php echo $tabCounts['sold']; ?></span>
                </button>
                <button type="button" class="scrap-tab-btn" data-tab="written-off">
                    Written Off <span class="scrap-tab-count" id="tabCountWrittenOff"><?php echo $tabCounts['written-off']; ?></span>
                </button>
            </div>

            <div class="scrap-filter-actions">
                <div class="scrap-search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-secondary); flex-shrink: 0;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="scrapSearchInput" placeholder="Search Ref, Tag, serial, name...">
                </div>

                <select id="scrapReasonFilter" class="scrap-select-filter">
                    <option value="">All Defect Reasons</option>
                    <?php foreach ($uniqueReasons as $ur): ?>
                        <option value="<?php echo htmlspecialchars($ur); ?>"><?php echo htmlspecialchars($ur); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Scrap Assets Table (Clean & Simple) -->
        <div class="asset-table-card" id="scrapTableView">
            <div class="asset-table-responsive">
                <table class="asset-data-table" id="scrapTable">
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Asset Name &amp; Tag</th>
                            <th>Category</th>
                            <th>Serial Number (S/N)</th>
                            <th>Disposal Status</th>
                            <th>Salvage (₹)</th>
                            <th>Scrap Date</th>
                            <th style="text-align: right; padding-right: 18px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scrapRecords)): ?>
                            <tr id="noRecordsRow">
                                <td colspan="8" style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                        </svg>
                                        <div style="font-weight: 600; color: #475569; font-size: 13.5px;">No Scrap Asset Records Found</div>
                                        <div style="font-size: 12px; color: #94a3b8;">When assets are decommissioned and sent to scrap, they will appear here.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                        <?php foreach ($scrapRecords as $sr): ?>
                            <?php 
                                $badgeClass = 'status-' . $sr['status'];
                            ?>
                            <tr data-status="<?php echo htmlspecialchars($sr['status']); ?>" 
                                data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                data-category="<?php echo htmlspecialchars($sr['category']); ?>">
                                <td>
                                    <span class="ref-code"><?php echo htmlspecialchars($sr['ref']); ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong class="asset-name-title"><?php echo htmlspecialchars($sr['name']); ?></strong>
                                        <div style="margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                                            <span class="asset-tag-badge"><?php echo htmlspecialchars($sr['asset_tag']); ?></span>
                                            <?php if (!empty($sr['model'])): ?>
                                                <span style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($sr['model']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-pill"><?php echo htmlspecialchars($sr['category']); ?></span>
                                </td>
                                <td>
                                    <span class="serial-badge"><?php echo htmlspecialchars($sr['serial']); ?></span>
                                </td>
                                <td>
                                    <span class="scrap-status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($sr['status_text']); ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--navy-deep);">₹<?php echo number_format($sr['salvage_val']); ?></strong>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 12.5px; white-space: nowrap;">
                                    <?php echo $sr['date']; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                        <button type="button" class="action-icon-btn btn-view view-slip-btn" 
                                                data-ref="<?php echo htmlspecialchars($sr['ref']); ?>" 
                                                data-name="<?php echo htmlspecialchars($sr['name']); ?>" 
                                                data-tag="<?php echo htmlspecialchars($sr['asset_tag']); ?>" 
                                                data-serial="<?php echo htmlspecialchars($sr['serial']); ?>" 
                                                data-category="<?php echo htmlspecialchars($sr['category']); ?>" 
                                                data-brand="<?php echo htmlspecialchars($sr['brand']); ?>" 
                                                data-model="<?php echo htmlspecialchars($sr['model']); ?>" 
                                                data-location="<?php echo htmlspecialchars($sr['location']); ?>" 
                                                data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                                data-yard="<?php echo htmlspecialchars($sr['yard']); ?>" 
                                                data-status="<?php echo htmlspecialchars($sr['status_text']); ?>" 
                                                data-value="<?php echo (float)$sr['salvage_val']; ?>" 
                                                data-approver="<?php echo htmlspecialchars($sr['approver']); ?>" 
                                                data-notes="<?php echo htmlspecialchars(!empty($sr['notes']) ? $sr['notes'] : 'Asset evaluated, found beyond economic repair, decommissioned from asset register.'); ?>" 
                                                data-date="<?php echo htmlspecialchars($sr['date']); ?>"
                                                data-components="<?php echo htmlspecialchars(is_array($sr['components_disposition'] ?? null) ? json_encode($sr['components_disposition']) : ($sr['components_disposition'] ?? '')); ?>"
                                                title="View Decommission Details & Slip">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button type="button" class="action-icon-btn btn-qr direct-print-slip-btn" 
                                                data-ref="<?php echo htmlspecialchars($sr['ref']); ?>" 
                                                data-name="<?php echo htmlspecialchars($sr['name']); ?>" 
                                                data-tag="<?php echo htmlspecialchars($sr['asset_tag']); ?>" 
                                                data-serial="<?php echo htmlspecialchars($sr['serial']); ?>" 
                                                data-category="<?php echo htmlspecialchars($sr['category']); ?>" 
                                                data-brand="<?php echo htmlspecialchars($sr['brand']); ?>" 
                                                data-model="<?php echo htmlspecialchars($sr['model']); ?>" 
                                                data-location="<?php echo htmlspecialchars($sr['location']); ?>" 
                                                data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                                data-yard="<?php echo htmlspecialchars($sr['yard']); ?>" 
                                                data-status="<?php echo htmlspecialchars($sr['status_text']); ?>" 
                                                data-value="<?php echo (float)$sr['salvage_val']; ?>" 
                                                data-approver="<?php echo htmlspecialchars($sr['approver']); ?>" 
                                                data-notes="<?php echo htmlspecialchars(!empty($sr['notes']) ? $sr['notes'] : 'Asset evaluated, found beyond economic repair, decommissioned from asset register.'); ?>" 
                                                data-date="<?php echo htmlspecialchars($sr['date']); ?>"
                                                data-components="<?php echo htmlspecialchars(is_array($sr['components_disposition'] ?? null) ? json_encode($sr['components_disposition']) : ($sr['components_disposition'] ?? '')); ?>"
                                                title="Print Official Scrap Slip">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                <rect x="6" y="14" width="12" height="8"></rect>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<!-- ==================== RECORD SCRAP ASSET MODAL ==================== -->
<div class="modal-overlay" id="recordScrapModal">
    <div class="modal-box" style="max-width: 780px;">
        <div class="modal-header">
            <h3 id="recordScrapModalTitle">Record Scrap &amp; Decommissioned Asset</h3>
            <button type="button" class="modal-close-btn" id="closeRecordScrapModalBtn">&times;</button>
        </div>

        <!-- Modal Tab Headers (Matches assets.php) -->
        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-btn active" data-tab="general">
                General Info
            </button>
            <button type="button" class="modal-tab-btn" data-tab="components">
                Components <span id="modalCompTabBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span>
            </button>
            <button type="button" class="modal-tab-btn" data-tab="disposal">
                Disposal &amp; Valuation
            </button>
        </div>

        <form id="recordScrapForm">
            <div class="modal-body">
                
                <!-- Tab Pane 1: General Info -->
                <div class="modal-tab-pane active" id="modal_pane_general">
                    <div class="modal-form-group">
                        <label for="scrapAssetSelect">Select Hardware Asset *</label>
                        <select id="scrapAssetSelect" required>
                            <option value="">-- Choose Asset from Inventory / In-Stock Devices --</option>
                            <?php foreach ($liveAssets as $la): ?>
                                <option value="<?php echo $la['id']; ?>" 
                                        data-tag="<?php echo htmlspecialchars($la['tag']); ?>"
                                        data-name="<?php echo htmlspecialchars($la['name']); ?>"
                                        data-serial="<?php echo htmlspecialchars($la['serial']); ?>"
                                        data-category="<?php echo htmlspecialchars($la['category']); ?>"
                                        data-brand="<?php echo htmlspecialchars($la['brand']); ?>"
                                        data-model="<?php echo htmlspecialchars($la['model']); ?>"
                                        data-location="<?php echo htmlspecialchars($la['location']); ?>">
                                    <?php echo htmlspecialchars($la['name']); ?> (<?php echo htmlspecialchars($la['tag']); ?>) — SN: <?php echo htmlspecialchars($la['serial']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="assetHasComponentsNotice" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 14px; font-size: 12.5px; color: #166534; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span id="assetNoticeText">This asset has installed component parts attached.</span>
                        </div>
                        <button type="button" id="btnGoToCompTab" style="background: #ffffff; border: 1px solid #86efac; color: #15803d; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 4px; cursor: pointer;">
                            Manage Parts &rarr;
                        </button>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="scrapAssetTag">Asset Tag / Identifier</label>
                            <input type="text" id="scrapAssetTag" readonly placeholder="Auto-populated tag" style="background: #f8fafc; font-family: monospace; font-weight: 600; color: var(--cyan-primary);">
                        </div>
                        <div class="modal-form-group">
                            <label for="scrapAssetSerial">Serial Number (S/N)</label>
                            <input type="text" id="scrapAssetSerial" readonly placeholder="Auto-populated serial" style="background: #f8fafc; font-family: monospace;">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="scrapAssetCategory">Category &amp; Model</label>
                            <input type="text" id="scrapAssetCategory" readonly placeholder="Device category &amp; model" style="background: #f8fafc;">
                        </div>
                        <div class="modal-form-group">
                            <label for="scrapAssetLocation">Origin Location</label>
                            <input type="text" id="scrapAssetLocation" readonly placeholder="Asset location" style="background: #f8fafc;">
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="scrapReason">Condemnation / Scrap Reason *</label>
                        <select id="scrapReason" required>
                            <option value="Motherboard Burnout & Unrepairable Short Circuit">Motherboard Burnout &amp; Unrepairable Short Circuit</option>
                            <option value="Screen Shattered & Chassis Structural Fracture">Screen Shattered &amp; Chassis Structural Fracture</option>
                            <option value="Severe Liquid Ingress & Corroded Logic Board">Severe Liquid Ingress &amp; Corroded Logic Board</option>
                            <option value="Obsolete & Beyond Economic Repair (BER)">Obsolete &amp; Beyond Economic Repair (BER)</option>
                            <option value="Power Controller Failure & Battery Swelling Hazard">Power Controller Failure &amp; Battery Swelling Hazard</option>
                            <option value="Thermal Throttling / Processor Core Breakdown">Thermal Throttling / Processor Core Breakdown</option>
                            <option value="Multi-Component System Failure & EOL Obsolete">Multi-Component System Failure &amp; EOL Obsolete</option>
                        </select>
                    </div>
                </div>

                <!-- Tab Pane 2: Components (Attached Hardware Components like assets.php) -->
                <div class="modal-tab-pane" id="modal_pane_components">
                    <div class="added-parts-section" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                            <div style="font-size: 13.5px; font-weight: 700; color: var(--navy-deep); display: flex; align-items: center; gap: 8px;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);">
                                    <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                    <rect x="9" y="9" width="6" height="6"></rect>
                                    <line x1="9" y1="1" x2="9" y2="4"></line>
                                    <line x1="15" y1="1" x2="15" y2="4"></line>
                                    <line x1="9" y1="20" x2="9" y2="23"></line>
                                    <line x1="15" y1="20" x2="15" y2="23"></line>
                                    <line x1="20" y1="9" x2="23" y2="9"></line>
                                    <line x1="20" y1="14" x2="23" y2="14"></line>
                                    <line x1="1" y1="9" x2="4" y2="9"></line>
                                    <line x1="1" y1="14" x2="4" y2="14"></line>
                                </svg>
                                <span>Attached Hardware Components</span>
                                <span id="componentsCountBadge" style="background: #e0f2fe; color: #0369a1; font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 700;">0 Parts</span>
                            </div>
                            <div id="compBulkActions" style="display: flex; gap: 6px;">
                                <button type="button" id="btnScrapAllParts" class="comp-bulk-btn comp-bulk-scrap">Scrap All Parts</button>
                                <button type="button" id="btnSalvageAllParts" class="comp-bulk-btn comp-bulk-salvage">Salvage All to Stock</button>
                            </div>
                        </div>
                        <p style="font-size: 12px; color: #64748b; margin: 0 0 14px; line-height: 1.5;">Select whether each installed component part should be decommissioned together with this asset or salvaged back to available spare inventory:</p>
                        
                        <div id="installedComponentsList" class="installed-components-list">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 3: Disposal & Valuation -->
                <div class="modal-tab-pane" id="modal_pane_disposal">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="scrapStatus">Disposal Target / Status *</label>
                            <select id="scrapStatus" required>
                                <option value="In Scrap Yard">In Scrap Yard (Storage)</option>
                                <option value="E-Waste Recycled">E-Waste Recycled</option>
                                <option value="Sold to Vendor">Sold to Scrap Vendor</option>
                                <option value="Written Off">Written Off (Total Loss)</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="scrapValue">Estimated Salvage Realization (₹)</label>
                            <input type="number" id="scrapValue" min="0" value="0" placeholder="e.g. 2500">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="scrapYardLocation">Storage Bin / Yard Location</label>
                            <input type="text" id="scrapYardLocation" value="Yard Bay A-01 (Asset Storage)" placeholder="e.g. Bay A-04">
                        </div>
                        <div class="modal-form-group">
                            <label for="scrapApprover">Authorized By</label>
                            <input type="text" id="scrapApprover" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Abhishek Sharma'); ?>">
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="scrapNotes">Technical Inspection Notes</label>
                        <textarea id="scrapNotes" rows="3" placeholder="Diagnostic findings, reason for condemnation, or salvage assessment..."></textarea>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelRecordScrapModalBtn" style="padding: 9px 18px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); background: #fff; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveScrapBtn" style="padding: 9px 20px; font-size: 13px; font-weight: 600; border-radius: 6px; background: var(--cyan-primary); color: #fff; border: none; cursor: pointer;">Confirm Scrap Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== SLIDE-OVER DRAWER (Official Asset Scrap & Decommissioning Slip) ==================== -->
<div class="drawer-backdrop" id="scrapSlipBackdrop"></div>

<aside class="asset-drawer" id="scrapSlipModal" style="width: 780px; max-width: 95vw;">
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerSlipRef" style="font-size: 12px; font-weight: 700;">-</span>
                    <span class="serial-badge" id="drawerSlipDate" style="font-size: 12px; font-weight: 600;">-</span>
                </div>
                <h3 style="margin-top: 4px; font-size: 16.5px; font-weight: 700; color: var(--navy-deep);">Asset Scrap &amp; Decommissioning Slip</h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeSlipModalBtn" title="Close Drawer">&times;</button>
    </div>

    <div class="drawer-content" style="flex: 1; overflow-y: auto; padding: 20px 24px;">
        <div class="scrap-slip-container" id="printableSlipArea">
            <!-- Slip Header -->
            <div class="scrap-slip-header">
                <div class="scrap-slip-brand">
                    <img src="<?php echo htmlspecialchars($orgLogo); ?>" alt="Organization Logo">
                    <div class="scrap-slip-brand-info">
                        <h2><?php echo htmlspecialchars($orgName); ?></h2>
                        <p>IT Infrastructure &amp; Asset Management Directorate</p>
                    </div>
                </div>
                <div class="scrap-slip-meta">
                    <div class="scrap-slip-title">Official Asset Scrap Slip</div>
                    <div class="scrap-slip-ref" id="slipRefCode">-</div>
                    <div class="scrap-slip-date">Date: <span id="slipDate">-</span></div>
                </div>
            </div>

            <!-- Slip Grid Information -->
            <div class="scrap-slip-grid">
                <div class="scrap-slip-section">
                    <div class="scrap-slip-section-title">Decommissioned Asset Details</div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Asset Tag:</span>
                        <span class="scrap-slip-value" id="slipAssetTag" style="font-family: monospace; color: #1d4ed8;">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Device Name:</span>
                        <span class="scrap-slip-value" id="slipAssetName">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Category:</span>
                        <span class="scrap-slip-value" id="slipCategory">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Brand / Model:</span>
                        <span class="scrap-slip-value" id="slipBrandModel">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Serial Number:</span>
                        <span class="scrap-slip-value" id="slipSerial" style="font-family: monospace;">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Origin Branch:</span>
                        <span class="scrap-slip-value" id="slipOriginLocation">-</span>
                    </div>
                </div>

                <div class="scrap-slip-section">
                    <div class="scrap-slip-section-title">Disposal & Valuation Details</div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Disposal Target:</span>
                        <span class="scrap-slip-value" id="slipStatusText" style="color: #b45309; font-weight: 700;">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Storage Bin / Bay:</span>
                        <span class="scrap-slip-value" id="slipYardLocation">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Salvage Valuation:</span>
                        <span class="scrap-slip-value" id="slipSalvageVal" style="color: #0f172a; font-weight: 700;">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Condemnation Reason:</span>
                        <span class="scrap-slip-value" id="slipReason" style="color: #b91c1c;">-</span>
                    </div>
                    <div class="scrap-slip-field">
                        <span class="scrap-slip-label">Authorized Officer:</span>
                        <span class="scrap-slip-value" id="slipApprover">-</span>
                    </div>
                </div>
            </div>

            <!-- Attached Components Disposition in Slip -->
            <div class="scrap-slip-notes-box" id="slipComponentsBox" style="display: none; margin-top: 14px; background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="scrap-slip-notes-label" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>Attached Hardware Components Disposition</span>
                    <span id="slipComponentsCount" style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0 Parts</span>
                </div>
                <div id="slipComponentsList" style="margin-top: 8px; display: flex; flex-direction: column; gap: 6px;"></div>
            </div>

            <!-- Notes & Diagnostic Findings -->
            <div class="scrap-slip-notes-box">
                <div class="scrap-slip-notes-label">Diagnostic Findings & Condemnation Assessment</div>
                <div class="scrap-slip-notes-text" id="slipNotesText">-</div>
            </div>

            <!-- Signatures -->
            <div class="scrap-slip-signatures">
                <div class="scrap-slip-sign-box">
                    <div class="scrap-slip-sign-line"></div>
                    <div class="scrap-slip-sign-title">IT Asset Custodian</div>
                    <div class="scrap-slip-sign-sub">Device Handed Over</div>
                </div>
                <div class="scrap-slip-sign-box">
                    <div class="scrap-slip-sign-line"></div>
                    <div class="scrap-slip-sign-title">Technical Inspector</div>
                    <div class="scrap-slip-sign-sub">Defect Verified & Certified</div>
                </div>
                <div class="scrap-slip-sign-box">
                    <div class="scrap-slip-sign-line"></div>
                    <div class="scrap-slip-sign-title">Authorized Disposal Officer</div>
                    <div class="scrap-slip-sign-sub">Decommissioning Approval</div>
                </div>
            </div>
        </div>
    </div>

    <div class="drawer-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #ffffff;">
        <button type="button" class="btn-secondary" id="closeSlipModalFooterBtn">Close</button>
        <button type="button" class="btn-primary" id="printSlipBtn" style="display: flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Official Slip
        </button>
    </div>
</aside>

<script>
    window.ORG_NAME = <?php echo json_encode($orgName); ?>;
    window.ORG_LOGO = <?php echo json_encode($orgLogo); ?>;
    window.ASSET_COMPONENTS_MAP = <?php echo json_encode($installedComponentsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php include 'includes/footer.php'; ?>
