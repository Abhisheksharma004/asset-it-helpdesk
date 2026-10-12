<?php
// Asset Management & IT Service Desk Portal - Scrap Parts & Components Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Scrap Parts & Components - VIROS Portal";
$active_page = "scrap_components";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/scrap_components.css'];
$extra_js  = ['js/scrap_components.js'];

require_once __DIR__ . '/config/db.php';

// Organization Branding for Official Slips
$orgSettings = function_exists('getOrganizationSettings') ? getOrganizationSettings() : [];
$orgName = !empty($orgSettings['organization_legal_name']) ? trim($orgSettings['organization_legal_name']) : 'VIROS PORTAL';
$orgLogo = !empty($orgSettings['primary_brand_logo']) ? $orgSettings['primary_brand_logo'] : 'assets/images/logo.png';
if (!file_exists(__DIR__ . '/' . $orgLogo) && !filter_var($orgLogo, FILTER_VALIDATE_URL)) {
    $orgLogo = 'assets/images/logo.png';
}

// Fetch live components from DB for the modal selector (excluding already scrapped)
$liveComponents = [];
if (isset($conn) && $conn !== false) {
    $compQ = "SELECT c.id, c.sku, c.serial, c.name, c.category, c.brand, c.model, c.specs, c.status, c.installed_asset,
                     a.tag AS host_tag, a.name AS host_name
              FROM components c
              LEFT JOIN assets a ON (TRY_CAST(c.installed_asset AS INT) = a.id OR c.installed_asset = a.tag)
              WHERE c.status IS NULL OR c.status != 'Scrapped'
              ORDER BY c.name ASC";
    $compStmt = sqlsrv_query($conn, $compQ);
    if ($compStmt !== false) {
        while ($r = sqlsrv_fetch_array($compStmt, SQLSRV_FETCH_ASSOC)) {
            $hostDisplay = 'Standalone Spare';
            if (!empty($r['host_tag'])) {
                $hostDisplay = $r['host_tag'] . (!empty($r['host_name']) ? ' (' . $r['host_name'] . ')' : '');
            } elseif (!empty($r['installed_asset'])) {
                $hostDisplay = $r['installed_asset'];
            }
            $r['host_display'] = $hostDisplay;
            $liveComponents[] = $r;
        }
        sqlsrv_free_stmt($compStmt);
    }
}

// Fallback components if DB is empty
if (empty($liveComponents)) {
    $liveComponents = [
        ['id' => 1, 'sku' => 'CMP-SSD-001', 'serial' => 'S466NF0MA02934', 'name' => 'Samsung 970 EVO Plus 500GB NVMe M.2 SSD', 'category' => 'Storage (NVMe SSD)', 'brand' => 'Samsung', 'host_display' => 'AST-0038 (Lenovo ThinkCentre M70q)'],
        ['id' => 2, 'sku' => 'CMP-RAM-004', 'serial' => 'CR-DDR4-992014', 'name' => 'Crucial 8GB DDR4 2666MHz Desktop RAM', 'category' => 'RAM / Memory', 'brand' => 'Crucial', 'host_display' => 'AST-0019 (Dell OptiPlex 3080)'],
        ['id' => 3, 'sku' => 'CMP-PSU-002', 'serial' => 'CM-PSU-440192', 'name' => 'Cooler Master 550W Standard ATX Power Supply', 'category' => 'Power Supply (PSU)', 'brand' => 'Cooler Master', 'host_display' => 'AST-0051 (HP ProDesk 400 G6)'],
        ['id' => 4, 'sku' => 'CMP-GPU-001', 'serial' => 'ZT-GTX-883012', 'name' => 'ZOTAC Gaming GeForce GTX 1650 4GB GPU', 'category' => 'Graphics Card (GPU)', 'brand' => 'ZOTAC', 'host_display' => 'AST-0022 (CAD Workstation #4)'],
        ['id' => 5, 'sku' => 'CMP-CPU-003', 'serial' => 'IN-I5-339201', 'name' => 'Intel Core i5-8500 3.0GHz 6-Core LGA1151 CPU', 'category' => 'Processor (CPU)', 'brand' => 'Intel', 'host_display' => 'AST-0011 (Reception Desktop)'],
        ['id' => 6, 'sku' => 'CMP-MB-002',  'serial' => 'AS-H410M-7729', 'name' => 'ASUS Prime H410M-E Motherboard', 'category' => 'Motherboard', 'brand' => 'ASUS', 'host_display' => 'AST-0064 (Accounts Desktop)']
    ];
}

// Auto-create scrap_components table if not exists and fetch records
$scrapRecords = [];
if (isset($conn) && $conn !== false) {
    $tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='scrap_components' AND xtype='U')
    BEGIN
        CREATE TABLE scrap_components (
            id INT IDENTITY(1,1) PRIMARY KEY,
            scrap_ref NVARCHAR(50) NOT NULL UNIQUE,
            component_id INT NULL,
            component_name NVARCHAR(200) NOT NULL,
            sku NVARCHAR(50) NULL,
            serial_no NVARCHAR(100) NULL,
            category NVARCHAR(100) NULL,
            host_asset NVARCHAR(255) NULL,
            quantity INT NOT NULL DEFAULT 1,
            reason NVARCHAR(255) NOT NULL,
            bin_location NVARCHAR(150) NULL,
            disposal_status NVARCHAR(100) NOT NULL DEFAULT 'In Scrap Yard',
            salvage_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            authorized_by NVARCHAR(150) NULL,
            notes NVARCHAR(MAX) NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            updated_at DATETIME NOT NULL DEFAULT GETDATE()
        );
    END";
    sqlsrv_query($conn, $tableSetupSql);

    $scrSql = "SELECT id, scrap_ref, component_name, sku, serial_no, category, host_asset, quantity,
                      reason, bin_location, disposal_status, salvage_value, authorized_by, notes, 
                      CONVERT(VARCHAR(10), created_at, 105) AS created_date
               FROM scrap_components 
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

            $scrapRecords[] = [
                'id'          => (int)$row['id'],
                'ref'         => $row['scrap_ref'],
                'name'        => $row['component_name'],
                'sku'         => $row['sku'] ?? 'N/A',
                'serial'      => $row['serial_no'] ?? 'N/A',
                'category'    => $row['category'] ?? 'General Component',
                'host_asset'  => $row['host_asset'] ?? 'Standalone Spare',
                'qty'         => (int)($row['quantity'] ?? 1),
                'reason'      => $row['reason'],
                'location'    => $row['bin_location'] ?? 'Bin C-01 (Scrap Yard)',
                'status'      => $statusKey,
                'status_text' => $st,
                'salvage_val' => (float)($row['salvage_value'] ?? 0),
                'date'        => $row['created_date'] ?? date('d M Y'),
                'approver'    => $row['authorized_by'] ?? 'Abhishek Sharma',
                'notes'       => $row['notes'] ?? ''
            ];
        }
        sqlsrv_free_stmt($scrStmt);
    }
}

// Disable mock fallback so only real database records are shown
if (false) {
    $scrapRecords = [
        [
            'id'          => 1,
            'ref'         => 'SCR1026001',
            'name'        => 'Samsung 970 EVO Plus 500GB NVMe M.2 SSD',
            'sku'         => 'CMP-SSD-001',
            'serial'      => 'S466NF0MA02934',
            'category'    => 'Storage (NVMe SSD)',
            'host_asset'  => 'AST-0038 (Lenovo ThinkCentre M70q)',
            'qty'         => 1,
            'reason'      => 'Excessive Bad Sectors / I/O Read Error',
            'location'    => 'Bin C-01 (Component Yard)',
            'status'      => 'yard',
            'status_text' => 'In Scrap Yard',
            'salvage_val' => 250,
            'date'        => '12 Oct 2026',
            'approver'    => 'Abhishek Sharma',
            'notes'       => 'SMART status critical failure. Persistent write errors and memory controller failure. Non-recoverable.'
        ],
        [
            'id'          => 2,
            'ref'         => 'SCR1026002',
            'name'        => 'Crucial 8GB DDR4 2666MHz Desktop RAM',
            'sku'         => 'CMP-RAM-004',
            'serial'      => 'CR-DDR4-992014',
            'category'    => 'RAM / Memory',
            'host_asset'  => 'AST-0019 (Dell OptiPlex 3080)',
            'qty'         => 2,
            'reason'      => 'Memory BSOD / Multiple Bit Faults',
            'location'    => 'Bin C-03 (Scrap Yard)',
            'status'      => 'yard',
            'status_text' => 'In Scrap Yard',
            'salvage_val' => 360,
            'date'        => '11 Oct 2026',
            'approver'    => 'Abhishek Sharma',
            'notes'       => 'MemTest86 diagnostic reported persistent fatal memory bank errors across address 0x4F00.'
        ],
        [
            'id'          => 3,
            'ref'         => 'SCR1026003',
            'name'        => 'Cooler Master 550W Standard ATX Power Supply',
            'sku'         => 'CMP-PSU-002',
            'serial'      => 'CM-PSU-440192',
            'category'    => 'Power Supply (PSU)',
            'host_asset'  => 'AST-0051 (HP ProDesk 400 G6)',
            'qty'         => 1,
            'reason'      => 'Burnout / Shorted Capacitor Failure',
            'location'    => 'Hazardous Bin H-02',
            'status'      => 'recycled',
            'status_text' => 'E-Waste Recycled',
            'salvage_val' => 150,
            'date'        => '09 Oct 2026',
            'approver'    => 'Vikram Malhotra',
            'notes'       => '12V power rail dead after building power surge. Internal electrolytic filter capacitors blown.'
        ],
        [
            'id'          => 4,
            'ref'         => 'SCR1026004',
            'name'        => 'ZOTAC Gaming GeForce GTX 1650 4GB GPU',
            'sku'         => 'CMP-GPU-001',
            'serial'      => 'ZT-GTX-883012',
            'category'    => 'Graphics Card (GPU)',
            'host_asset'  => 'AST-0022 (CAD Workstation #4)',
            'qty'         => 1,
            'reason'      => 'VRAM Artifacting & Fan Bearing Failure',
            'location'    => 'E-Waste Depot Area',
            'status'      => 'sold',
            'status_text' => 'Sold to Vendor',
            'salvage_val' => 950,
            'date'        => '06 Oct 2026',
            'approver'    => 'Abhishek Sharma',
            'notes'       => 'Display renders heavy artifacting and freezes under GPU load. Sold to authorized corporate electronic recycler.'
        ],
        [
            'id'          => 5,
            'ref'         => 'SCR1026005',
            'name'        => 'Intel Core i5-8500 3.0GHz 6-Core LGA1151 CPU',
            'sku'         => 'CMP-CPU-003',
            'serial'      => 'IN-I5-339201',
            'category'    => 'Processor (CPU)',
            'host_asset'  => 'AST-0011 (Reception Desktop)',
            'qty'         => 1,
            'reason'      => 'Physical Contact Pad Fracture & Overheating',
            'location'    => 'Bin C-05 (Scrap Yard)',
            'status'      => 'written-off',
            'status_text' => 'Written Off',
            'salvage_val' => 0,
            'date'        => '02 Oct 2026',
            'approver'    => 'Abhishek Sharma',
            'notes'       => 'Substrate cracked and LGA contact pads severely burnt during prior field technician maintenance.'
        ]
    ];
}

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

$uniqueReasons = [
    'Excessive Bad Sectors / I/O Read Error',
    'Memory BSOD / Multiple Bit Faults',
    'Burnout / Shorted Capacitor Failure',
    'VRAM Artifacting & Fan Bearing Failure',
    'Physical Contact Pad Fracture & Overheating',
    'Liquid Damage & Corroded PCB',
    'Obsolete & Beyond Economic Repair (BER)'
];

foreach ($scrapRecords as $r) {
    $totalScrappedQty += $r['qty'];
    $totalSalvageVal += $r['salvage_val'];
    if ($r['status'] === 'yard') $inYardCount += $r['qty'];
    if ($r['status'] === 'recycled') $recycledCount += $r['qty'];

    // Dynamic record count per tab
    if (isset($tabCounts[$r['status']])) {
        $tabCounts[$r['status']]++;
    }

    if (!empty($r['reason']) && !in_array($r['reason'], $uniqueReasons)) {
        $uniqueReasons[] = $r['reason'];
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<main class="dashboard-content">

    <!-- Top Breadcrumb & Page Header -->
    <div class="page-header-bar" style="margin-bottom: 22px;">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Scrap / Dismantle</span>
                <span>/</span>
                <span>Parts & Components</span>
            </div>
            <h1 style="font-size: 22px; font-weight: 700; color: var(--navy-deep); margin: 4px 0 0;">Scrap Parts & Components Register</h1>
            <div class="scrap-header-meta">
                <span style="font-size: 13px; color: var(--text-secondary);">Track decommissioned, salvaged, and dismantled internal hardware components</span>
            </div>
        </div>
        <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
            <button onclick="exportScrapToCSV()" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 6px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button id="openRecordScrapModalBtn" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 6px; background: var(--cyan-primary); color: #fff; border: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Record Scrap Component
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="scrap-metrics-grid">
        <div class="scrap-metric-card card-total">
            <div class="scrap-metric-icon icon-cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect>
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
            </div>
            <div class="scrap-metric-body">
                <div class="metric-label">Total Scrapped Components</div>
                <div class="metric-value"><?php echo number_format($totalScrappedQty); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Units</span></div>
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
                <div class="metric-value"><?php echo number_format($inYardCount); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Units</span></div>
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
                <div class="metric-value"><?php echo number_format($recycledCount); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Units</span></div>
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
                <div class="metric-value">₹<?php echo number_format($totalSalvageVal); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content Table Card -->
    <div class="content-card" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px;">
        
        <!-- Unified Single Row Toolbar: Status Tabs on Left, Search & Defect Filter on Right -->
        <div class="scrap-unified-row">
            <div class="scrap-filter-tabs">
                <button class="scrap-tab-btn active" data-tab="all">
                    All Records <span class="scrap-tab-count" id="tabCountAll"><?php echo $tabCounts['all']; ?></span>
                </button>
                <button class="scrap-tab-btn" data-tab="yard">
                    In Scrap Yard <span class="scrap-tab-count" id="tabCountYard"><?php echo $tabCounts['yard']; ?></span>
                </button>
                <button class="scrap-tab-btn" data-tab="recycled">
                    E-Waste Recycled <span class="scrap-tab-count" id="tabCountRecycled"><?php echo $tabCounts['recycled']; ?></span>
                </button>
                <button class="scrap-tab-btn" data-tab="sold">
                    Sold to Vendor <span class="scrap-tab-count" id="tabCountSold"><?php echo $tabCounts['sold']; ?></span>
                </button>
                <button class="scrap-tab-btn" data-tab="written-off">
                    Written Off <span class="scrap-tab-count" id="tabCountWrittenOff"><?php echo $tabCounts['written-off']; ?></span>
                </button>
            </div>

            <div class="scrap-filter-actions">
                <div class="scrap-search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-secondary); flex-shrink: 0;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="scrapSearchInput" placeholder="Search Ref, SKU, serial, name...">
                </div>

                <select id="scrapReasonFilter" class="scrap-select-filter">
                    <option value="">All Defect Reasons</option>
                    <?php foreach ($uniqueReasons as $ur): ?>
                        <option value="<?php echo htmlspecialchars($ur); ?>"><?php echo htmlspecialchars($ur); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Scrap Table -->
        <div class="table-responsive">
            <table class="data-table" id="scrapTable" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--bg-surface); text-align: left; border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Ref ID</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Component Details</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Serial / Part No</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Dismantled From</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Defect Reason</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Location</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Status</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Salvage (₹)</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Date</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scrapRecords)): ?>
                        <tr id="noRecordsRow">
                            <td colspan="9" style="text-align: center; padding: 40px 14px; color: var(--text-secondary);">
                                <div style="font-size: 14px; font-weight: 500; color: var(--navy-deep); margin-bottom: 4px;">No Scrapped Components Found</div>
                                <div style="font-size: 12.5px;">Click "Record Scrap Component" above to decommission and scrap defective hardware components.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($scrapRecords as $sr): ?>
                        <?php 
                            $badgeClass = 'status-' . $sr['status'];
                            $isStandalone = stripos($sr['host_asset'], 'standalone') !== false;
                        ?>
                        <tr data-status="<?php echo htmlspecialchars($sr['status']); ?>" data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 14px;"><span class="ref-code"><?php echo htmlspecialchars($sr['ref']); ?></span></td>
                            <td style="padding: 12px 14px;">
                                <strong style="color: var(--navy-deep);"><?php echo htmlspecialchars($sr['name']); ?></strong>
                                <div style="margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                                    <span class="sku-badge"><?php echo htmlspecialchars($sr['sku']); ?></span>
                                    <span style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($sr['category']); ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px 14px;"><span class="serial-badge"><?php echo htmlspecialchars($sr['serial']); ?></span></td>
                            <td style="padding: 12px 14px;">
                                <span class="host-asset-tag <?php echo $isStandalone ? 'standalone' : ''; ?>">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                    <?php echo htmlspecialchars($sr['host_asset']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 14px;"><span class="reason-tag"><?php echo htmlspecialchars($sr['reason']); ?></span></td>
                            <td style="padding: 12px 14px; color: var(--text-secondary); font-size: 12.5px;"><?php echo htmlspecialchars($sr['location']); ?></td>
                            <td style="padding: 12px 14px;">
                                <span class="scrap-status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($sr['status_text']); ?></span>
                            </td>
                            <td style="padding: 12px 14px; font-weight: 600; color: var(--navy-deep);">₹<?php echo number_format($sr['salvage_val']); ?></td>
                            <td style="padding: 12px 14px; font-size: 12px; color: var(--text-secondary);">
                                <div><?php echo $sr['date']; ?></div>
                                <div style="font-size: 11px; opacity: 0.8;">By <?php echo htmlspecialchars($sr['approver']); ?></div>
                            </td>
                            <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-outline btn-sm view-slip-btn" 
                                        data-ref="<?php echo htmlspecialchars($sr['ref']); ?>" 
                                        data-name="<?php echo htmlspecialchars($sr['name']); ?>" 
                                        data-sku="<?php echo htmlspecialchars($sr['sku']); ?>" 
                                        data-serial="<?php echo htmlspecialchars($sr['serial']); ?>" 
                                        data-category="<?php echo htmlspecialchars($sr['category']); ?>" 
                                        data-host="<?php echo htmlspecialchars($sr['host_asset']); ?>" 
                                        data-qty="<?php echo (int)$sr['qty']; ?>" 
                                        data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                        data-location="<?php echo htmlspecialchars($sr['location']); ?>" 
                                        data-status="<?php echo htmlspecialchars($sr['status_text']); ?>" 
                                        data-value="<?php echo (float)$sr['salvage_val']; ?>" 
                                        data-approver="<?php echo htmlspecialchars($sr['approver']); ?>" 
                                        data-notes="<?php echo htmlspecialchars(!empty($sr['notes']) ? $sr['notes'] : 'Component inspected, evaluated beyond economic repair, and decommissioned.'); ?>" 
                                        data-date="<?php echo htmlspecialchars($sr['date']); ?>"
                                        style="padding: 4px 8px; font-size: 11.5px; border-radius: 4px; border: 1px solid var(--border-color); cursor: pointer; background: #fff;">
                                    View Slip
                                </button>
                                <button type="button" class="btn btn-primary btn-sm direct-print-slip-btn" 
                                        data-ref="<?php echo htmlspecialchars($sr['ref']); ?>" 
                                        data-name="<?php echo htmlspecialchars($sr['name']); ?>" 
                                        data-sku="<?php echo htmlspecialchars($sr['sku']); ?>" 
                                        data-serial="<?php echo htmlspecialchars($sr['serial']); ?>" 
                                        data-category="<?php echo htmlspecialchars($sr['category']); ?>" 
                                        data-host="<?php echo htmlspecialchars($sr['host_asset']); ?>" 
                                        data-qty="<?php echo (int)$sr['qty']; ?>" 
                                        data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                        data-location="<?php echo htmlspecialchars($sr['location']); ?>" 
                                        data-status="<?php echo htmlspecialchars($sr['status_text']); ?>" 
                                        data-value="<?php echo (float)$sr['salvage_val']; ?>" 
                                        data-approver="<?php echo htmlspecialchars($sr['approver']); ?>" 
                                        data-notes="<?php echo htmlspecialchars(!empty($sr['notes']) ? $sr['notes'] : 'Component inspected, evaluated beyond economic repair, and decommissioned.'); ?>" 
                                        data-date="<?php echo htmlspecialchars($sr['date']); ?>"
                                        style="padding: 4px 8px; font-size: 11.5px; border-radius: 4px; border: none; cursor: pointer; background: var(--cyan-primary); color: #fff; margin-left: 4px;" title="Print Slip Directly">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 2px;">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                    Print
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</main>

<!-- ==================== RECORD SCRAP COMPONENT MODAL ==================== -->
<div class="modal-overlay" id="recordScrapModal">
    <div class="modal-box" style="max-width: 620px;">
        <div class="modal-header">
            <h3>Record Scrap Part / Component</h3>
            <button class="modal-close-btn" id="closeRecordScrapModalBtn">&times;</button>
        </div>
        <form id="recordScrapForm">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="scrapCompSelect">Select Hardware Component *</label>
                    <select id="scrapCompSelect" required>
                        <option value="">-- Choose Component from Inventory / Spares --</option>
                        <?php foreach ($liveComponents as $lc): ?>
                            <option value="<?php echo $lc['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($lc['name']); ?>"
                                    data-sku="<?php echo htmlspecialchars($lc['sku']); ?>"
                                    data-serial="<?php echo htmlspecialchars($lc['serial'] ?? 'SN-' . rand(100000, 999999)); ?>"
                                    data-category="<?php echo htmlspecialchars($lc['category']); ?>"
                                    data-host="<?php echo htmlspecialchars($lc['host_display']); ?>">
                                <?php echo htmlspecialchars($lc['name']); ?> (<?php echo htmlspecialchars($lc['sku']); ?>) — <?php echo htmlspecialchars($lc['host_display']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="scrapSerial">Component Serial No</label>
                        <input type="text" id="scrapSerial" readonly placeholder="Auto-populated serial" style="background: #f8fafc; font-family: monospace;">
                    </div>
                    <div class="modal-form-group">
                        <label for="scrapHostAsset">Dismantled From Host Asset</label>
                        <input type="text" id="scrapHostAsset" readonly placeholder="Origin asset" style="background: #f8fafc;">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="scrapReason">Defect / Scrap Reason *</label>
                    <select id="scrapReason" required>
                        <option value="Excessive Bad Sectors / I/O Read Error">Excessive Bad Sectors / I/O Read Error</option>
                        <option value="Memory BSOD / Multiple Bit Faults">Memory BSOD / Multiple Bit Faults</option>
                        <option value="Burnout / Shorted Capacitor Failure">Burnout / Shorted Capacitor Failure</option>
                        <option value="VRAM Artifacting & Fan Bearing Failure">VRAM Artifacting & Fan Bearing Failure</option>
                        <option value="Physical Contact Pad Fracture & Overheating">Physical Contact Pad Fracture & Overheating</option>
                        <option value="Liquid Damage & Corroded PCB">Liquid Damage & Corroded PCB</option>
                        <option value="Obsolete & Beyond Economic Repair (BER)">Obsolete & Beyond Economic Repair (BER)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
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
                        <input type="number" id="scrapValue" min="0" value="0" placeholder="e.g. 350">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="scrapLocation">Storage Bin / Yard Location</label>
                        <input type="text" id="scrapLocation" value="Bin C-01 (Component Yard)" placeholder="e.g. Bin C-04">
                    </div>
                    <div class="modal-form-group">
                        <label for="scrapApprover">Authorized By</label>
                        <input type="text" id="scrapApprover" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Abhishek Sharma'); ?>">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="scrapNotes">Technical Inspection Notes</label>
                    <textarea id="scrapNotes" rows="2" style="width: 100%; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px; font-size: 13px;" placeholder="Diagnostic findings, SMART test results, or reason for scrap disposal..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelRecordScrapModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveScrapBtn">Confirm Scrap Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== SCRAP COMPONENT DISPOSAL CERTIFICATE SLIP MODAL ==================== -->
<div class="modal-overlay" id="scrapSlipModal">
    <div class="modal-box" style="max-width: 840px; padding: 22px;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <h3 style="margin: 0; font-size: 16.5px; color: var(--navy-deep);">Component Scrap Slip Preview</h3>
            </div>
            <button class="modal-close-btn" id="closeSlipModalBtn">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0; max-height: 75vh; overflow-y: auto;">
            <div class="official-slip-sheet" id="officialScrapSlipContent">
                <!-- 1. Document Header Table -->
                <table class="slip-header-tbl">
                    <tr>
                        <td class="slip-logo-cell">
                            <img src="<?php echo htmlspecialchars($orgLogo); ?>" alt="<?php echo htmlspecialchars($orgName); ?> Logo">
                        </td>
                        <td class="slip-title-cell">
                            <h2><?php echo htmlspecialchars($orgName); ?></h2>
                            <div class="slip-sub-title">IT Asset Management &amp; Helpdesk</div>
                            <div class="slip-doc-pill">COMPONENT DISMANTLE &amp; SCRAP DISPOSAL SLIP</div>
                        </td>
                        <td class="slip-meta-cell">
                            <div><strong>Slip No:</strong> <span id="slipRefId" style="font-family: monospace; font-weight: 700;">SCR1026001</span></div>
                            <div><strong>Date:</strong> <span id="slipDate">12 Oct 2026</span></div>
                            <div><strong>Disposition:</strong> Component Decommission</div>
                            <div><strong>Status:</strong> <span id="slipStatusBadge" class="slip-status-tag">In Scrap Yard</span></div>
                        </td>
                    </tr>
                </table>

                <!-- 2. Section 1: Item & Component Details -->
                <div class="slip-sec-heading">1. Decommissioned Hardware Component Details</div>
                <table class="slip-data-tbl">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">#</th>
                            <th>Component Name &amp; Specifications</th>
                            <th style="width: 120px;">SKU / Code</th>
                            <th style="width: 130px;">Serial Number</th>
                            <th style="width: 130px;">Dismantled From</th>
                            <th style="width: 70px; text-align: center;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">1</td>
                            <td><strong id="slipCompName">Samsung 970 EVO Plus 500GB NVMe SSD</strong></td>
                            <td><span id="slipSku" class="slip-code-mono">CMP-SSD-001</span></td>
                            <td><span id="slipSerial" class="slip-code-mono">S466NF0MA02934</span></td>
                            <td id="slipHostAsset">AST-0038</td>
                            <td style="text-align: center;"><strong id="slipQty" style="font-size: 13px;">1 Unit</strong></td>
                        </tr>
                    </tbody>
                </table>

                <!-- 3. Section 2: Technical Diagnostics & Disposal Target -->
                <div class="slip-sec-heading">2. Technical Diagnostics &amp; Disposal Target</div>
                <table class="slip-data-tbl">
                    <tr>
                        <td class="slip-lbl">Defect / Failure Reason</td>
                        <td class="slip-val" id="slipReason">Excessive Bad Sectors / I/O Read Error</td>
                        <td class="slip-lbl">Hardware Condition</td>
                        <td class="slip-val" id="slipCondition">Beyond Economic Repair (BER)</td>
                    </tr>
                    <tr>
                        <td class="slip-lbl">Storage / Bin Location</td>
                        <td class="slip-val" id="slipLocation">Bin C-01 (Component Yard)</td>
                        <td class="slip-lbl">Disposal Method / Target</td>
                        <td class="slip-val" id="slipStatus">In Scrap Yard</td>
                    </tr>
                    <tr>
                        <td class="slip-lbl">Estimated Salvage Realization</td>
                        <td class="slip-val" id="slipValue" style="font-weight: 700; color: #0284c7;">₹250.00</td>
                        <td class="slip-lbl">Decommission Date</td>
                        <td class="slip-val" id="slipDateAlt">12 Oct 2026</td>
                    </tr>
                </table>

                <!-- 4. Section 3: Diagnostic Findings & Remarks -->
                <div class="slip-sec-heading">3. Diagnostic Findings &amp; Technical Authorization</div>
                <table class="slip-data-tbl">
                    <tr>
                        <td class="slip-lbl" style="width: 22%;">Authorized By</td>
                        <td class="slip-val" id="slipApprover" style="width: 28%; font-weight: 700;">Abhishek Sharma</td>
                        <td class="slip-lbl" style="width: 20%;">Inspection Notes</td>
                        <td class="slip-val" id="slipNotes" style="width: 30%;">SMART diagnostic test failure. Memory controller dead. Approved for write-off.</td>
                    </tr>
                </table>

                <!-- 5. Section 4: Declaration & Environmental Compliance -->
                <div class="slip-declaration-box">
                    <strong>Official Decommission Certification:</strong> I hereby certify that the internal hardware component listed above has undergone technical diagnostic evaluation and is confirmed beyond economic repair (BER) or unserviceable. The item has been struck off from active inventory stock and scheduled for authorized scrap disposal / e-waste recycling in compliance with corporate electronic waste management regulations.
                </div>

                <!-- 6. Section 5: Tripartite Signatures -->
                <table class="slip-signatures-tbl">
                    <tr>
                        <td style="width: 34%;">
                            <div class="slip-sig-line">Inspecting IT Technician</div>
                            <div class="slip-sig-sub">Signature: ________________<br>Date: ________________</div>
                        </td>
                        <td style="width: 33%; text-align: center;">
                            <div class="slip-sig-line">Scrap Yard / Store Officer</div>
                            <div class="slip-sig-sub">Signature: ________________<br>Date: ________________</div>
                        </td>
                        <td style="width: 33%; text-align: right;">
                            <div class="slip-sig-line">Authorized IT Head / Seal</div>
                            <div class="slip-sig-sub" id="slipApproverSub">Abhishek Sharma<br>IT Asset Dept &bull; Date: ________________</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color);">
            <button type="button" class="btn-secondary" onclick="document.getElementById('scrapSlipModal').classList.remove('active')">Close</button>
            <button type="button" class="btn-primary" id="printScrapSlipBtn" style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; font-weight: 600;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Official Slip
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
