<?php
// Asset Management & IT Service Desk Portal - Scrap Accessories Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Scrap Accessories - VIROS Portal";
$active_page = "scrap_accessories";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/accessories.css', 'css/scrap_accessories.css'];
$extra_js  = ['js/scrap_accessories.js'];

require_once __DIR__ . '/config/db.php';

// Organization Branding for Official Slips
$orgSettings = function_exists('getOrganizationSettings') ? getOrganizationSettings() : [];
$orgName = !empty($orgSettings['organization_legal_name']) ? trim($orgSettings['organization_legal_name']) : 'VIROS PORTAL';
$orgLogo = !empty($orgSettings['primary_brand_logo']) ? $orgSettings['primary_brand_logo'] : 'assets/images/logo.png';
if (!file_exists(__DIR__ . '/' . $orgLogo) && !filter_var($orgLogo, FILTER_VALIDATE_URL)) {
    $orgLogo = 'assets/images/logo.png';
}

// Fetch live accessories from DB for modal selector if present
$liveAccessories = [];
if (isset($conn) && $conn !== false) {
    $accQ = "SELECT id, sku, name, category, brand, in_stock FROM accessories ORDER BY name ASC";
    $accStmt = sqlsrv_query($conn, $accQ);
    if ($accStmt !== false) {
        while ($r = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC)) {
            $liveAccessories[] = $r;
        }
        sqlsrv_free_stmt($accStmt);
    }
}

// Fallback accessories if DB is empty
if (empty($liveAccessories)) {
    $liveAccessories = [
        ['id' => 1, 'sku' => 'ACC-MOU-001', 'name' => 'Logitech MX Master 3S Wireless Mouse', 'category' => 'Mouse', 'brand' => 'Logitech', 'in_stock' => 12],
        ['id' => 2, 'sku' => 'ACC-CAB-004', 'name' => 'Dell 65W USB-C Power Adapter & Cable', 'category' => 'Cables & Power', 'brand' => 'Dell', 'in_stock' => 8],
        ['id' => 3, 'sku' => 'ACC-HDS-012', 'name' => 'Jabra Evolve 65 Stereo Headset', 'category' => 'Audio / Headsets', 'brand' => 'Jabra', 'in_stock' => 5],
        ['id' => 4, 'sku' => 'ACC-KEY-007', 'name' => 'HP Wired Desktop Keyboard K1500', 'category' => 'Keyboards', 'brand' => 'HP', 'in_stock' => 14],
        ['id' => 5, 'sku' => 'ACC-DCK-003', 'name' => 'Lenovo ThinkPad Universal USB-C Dock', 'category' => 'Docks & Hubs', 'brand' => 'Lenovo', 'in_stock' => 3]
    ];
}

// Fetch live scrap records from DB
$scrapRecords = [];
if (isset($conn) && $conn !== false) {
    $scrSql = "SELECT id, scrap_ref, accessory_id, accessory_name, sku, category, quantity, 
                      reason, bin_location, disposal_status, salvage_value, authorized_by, notes, 
                      CONVERT(VARCHAR(10), created_at, 105) AS created_date
               FROM scrap_accessories 
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
                'name'        => $row['accessory_name'],
                'sku'         => $row['sku'] ?? 'N/A',
                'category'    => $row['category'] ?? 'General',
                'qty'         => (int)($row['quantity'] ?? 1),
                'reason'      => $row['reason'],
                'location'    => $row['bin_location'] ?? 'IT Scrap Bin',
                'status'      => $statusKey,
                'status_text' => $st,
                'salvage_val' => (float)($row['salvage_value'] ?? 0),
                'date'        => $row['created_date'] ?? date('d M Y'),
                'approver'    => $row['authorized_by'] ?? 'IT Admin',
                'notes'       => $row['notes'] ?? ''
            ];
        }
        sqlsrv_free_stmt($scrStmt);
    }
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
    'Wire Frayed & Melted Pin',
    'Laser Sensor Failure',
    'Audio Jack Broken / Mic Dead',
    'Power Surge / Circuit Short',
    'Liquid Spill Damage',
    'Physical Body Fracture',
    'Obsolete & End of Life'
];

foreach ($scrapRecords as $r) {
    $totalScrappedQty += $r['qty'];
    $totalSalvageVal += $r['salvage_val'];
    if ($r['status'] === 'yard') $inYardCount += $r['qty'];
    if ($r['status'] === 'recycled') $recycledCount += $r['qty'];

    // Dynamic record count per tab status
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
                <span>Accessories</span>
            </div>
            <h1 style="font-size: 22px; font-weight: 700; color: var(--navy-deep); margin: 4px 0 0;">Scrap Accessories Register</h1>
            <div class="scrap-header-meta">
                <span style="font-size: 13px; color: var(--text-secondary);">Track disposed, damaged, and salvaged peripheral accessories</span>
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
                Record Scrap Item
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="scrap-metrics-grid">
        <div class="scrap-metric-card card-total">
            <div class="scrap-metric-icon icon-cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
            </div>
            <div class="scrap-metric-body">
                <div class="metric-label">Total Scrapped Units</div>
                <div class="metric-value"><?php echo number_format($totalScrappedQty); ?> <span style="font-size: 13px; font-weight: 500; color: var(--text-secondary);">Items</span></div>
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
                    <input type="text" id="scrapSearchInput" placeholder="Search by Scrap Ref, SKU, name, or reason...">
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
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Accessory Details</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Qty</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Defect / Reason</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Bin Location</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Status</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Salvage (₹)</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary);">Logged Date</th>
                        <th style="padding: 12px 14px; font-weight: 600; color: var(--text-secondary); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scrapRecords as $sr): ?>
                        <?php 
                        $badgeClass = 'status-yard';
                        if ($sr['status'] === 'recycled') $badgeClass = 'status-recycled';
                        elseif ($sr['status'] === 'sold') $badgeClass = 'status-sold';
                        elseif ($sr['status'] === 'written-off') $badgeClass = 'status-written-off';
                        ?>
                        <tr data-status="<?php echo $sr['status']; ?>" data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px 14px;"><span class="ref-code"><?php echo $sr['ref']; ?></span></td>
                            <td style="padding: 12px 14px;">
                                <strong style="color: var(--navy-deep);"><?php echo htmlspecialchars($sr['name']); ?></strong>
                                <div style="margin-top: 2px;">
                                    <span class="sku-badge"><?php echo htmlspecialchars($sr['sku']); ?></span>
                                    <span style="font-size: 11px; color: var(--text-secondary); margin-left: 6px;"><?php echo htmlspecialchars($sr['category']); ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px 14px;"><strong style="font-size: 14px; color: var(--navy-deep);"><?php echo $sr['qty']; ?></strong> pcs</td>
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
                                <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                    <button type="button" class="action-icon-btn btn-view view-slip-btn" 
                                            data-ref="<?php echo htmlspecialchars($sr['ref']); ?>" 
                                            data-name="<?php echo htmlspecialchars($sr['name']); ?>" 
                                            data-sku="<?php echo htmlspecialchars($sr['sku']); ?>" 
                                            data-category="<?php echo htmlspecialchars($sr['category']); ?>" 
                                            data-qty="<?php echo (int)$sr['qty']; ?>" 
                                            data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                            data-location="<?php echo htmlspecialchars($sr['location']); ?>" 
                                            data-status="<?php echo htmlspecialchars($sr['status_text']); ?>" 
                                            data-value="<?php echo (float)$sr['salvage_val']; ?>" 
                                            data-approver="<?php echo htmlspecialchars($sr['approver']); ?>" 
                                            data-notes="<?php echo htmlspecialchars(!empty($sr['notes']) ? $sr['notes'] : 'Hardware verified defective, non-repairable, and approved for scrap write-off.'); ?>" 
                                            data-date="<?php echo htmlspecialchars($sr['date']); ?>"
                                            title="View Accessory Scrap Slip">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-qr direct-print-slip-btn" 
                                            data-ref="<?php echo htmlspecialchars($sr['ref']); ?>" 
                                            data-name="<?php echo htmlspecialchars($sr['name']); ?>" 
                                            data-sku="<?php echo htmlspecialchars($sr['sku']); ?>" 
                                            data-category="<?php echo htmlspecialchars($sr['category']); ?>" 
                                            data-qty="<?php echo (int)$sr['qty']; ?>" 
                                            data-reason="<?php echo htmlspecialchars($sr['reason']); ?>" 
                                            data-location="<?php echo htmlspecialchars($sr['location']); ?>" 
                                            data-status="<?php echo htmlspecialchars($sr['status_text']); ?>" 
                                            data-value="<?php echo (float)$sr['salvage_val']; ?>" 
                                            data-approver="<?php echo htmlspecialchars($sr['approver']); ?>" 
                                            data-notes="<?php echo htmlspecialchars(!empty($sr['notes']) ? $sr['notes'] : 'Hardware verified defective, non-repairable, and approved for scrap write-off.'); ?>" 
                                            data-date="<?php echo htmlspecialchars($sr['date']); ?>"
                                            title="Print Official Accessory Scrap Slip">
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
                </tbody>
            </table>
        </div>

    </div>

</main>

<!-- ==================== RECORD SCRAP ACCESSORY MODAL ==================== -->
<div class="modal-overlay" id="recordScrapModal">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Record Scrap Accessory</h3>
            <button class="modal-close-btn" id="closeRecordScrapModalBtn">&times;</button>
        </div>
        <form id="recordScrapForm">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="scrapAccSelect">Select Accessory *</label>
                    <select id="scrapAccSelect" required>
                        <option value="" data-stock="0">-- Choose Decommissioned Accessory --</option>
                        <?php foreach ($liveAccessories as $la): ?>
                            <?php $inStock = (int)($la['in_stock'] ?? 0); ?>
                            <option value="<?php echo $la['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($la['name']); ?>"
                                    data-sku="<?php echo htmlspecialchars($la['sku']); ?>"
                                    data-stock="<?php echo $inStock; ?>">
                                <?php echo htmlspecialchars($la['name']); ?> (<?php echo htmlspecialchars($la['sku']); ?>) — Available: <?php echo $inStock; ?> pcs
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label for="scrapQty" style="margin-bottom: 0;">Quantity to Scrap *</label>
                            
                        </div>
                        <input type="number" id="scrapQty" min="1" value="1" required placeholder="Enter quantity">
                    </div>
                    <div class="modal-form-group">
                        <label for="scrapReason">Scrap Reason / Defect *</label>
                        <select id="scrapReason" required>
                            <option value="Wire Frayed & Melted Pin">Wire Frayed & Melted Pin</option>
                            <option value="Laser Sensor Failure">Laser Sensor Failure</option>
                            <option value="Audio Jack Broken & Mic Dead">Audio Jack Broken / Mic Dead</option>
                            <option value="Power Surge / Circuit Short">Power Surge / Circuit Short</option>
                            <option value="Liquid Spill Damage">Liquid Spill Damage</option>
                            <option value="Physical Body Fracture">Physical Body Fracture</option>
                            <option value="Obsolete & End of Life">Obsolete & End of Life</option>
                        </select>
                    </div>
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
                        <label for="scrapValue">Estimated Salvage (₹)</label>
                        <input type="number" id="scrapValue" min="0" value="0" placeholder="e.g. 250">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="scrapLocation">Storage Bin / Yard Location</label>
                        <input type="text" id="scrapLocation" value="IT Scrap Yard Bin 1" placeholder="e.g. Bin A-03">
                    </div>
                    <div class="modal-form-group">
                        <label for="scrapApprover">Authorized By</label>
                        <input type="text" id="scrapApprover" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Abhishek Sharma'); ?>">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="scrapNotes">Technical Inspection Notes</label>
                    <textarea id="scrapNotes" rows="2" style="width: 100%; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px; font-size: 13px;" placeholder="Optional details regarding diagnostic findings, inspection, or write-off reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelRecordScrapModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveScrapBtn">Confirm Scrap Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== SLIDE-OVER DRAWER (Accessory Scrap Slip) ==================== -->
<div class="drawer-backdrop" id="scrapSlipBackdrop"></div>

<aside class="asset-drawer" id="scrapSlipModal" style="width: 820px; max-width: 95vw;">
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerAccRef" style="font-size: 12px; font-weight: 700;">-</span>
                    <span class="serial-badge" id="drawerAccDate" style="font-size: 12px; font-weight: 600;">-</span>
                </div>
                <h3 style="margin-top: 4px; font-size: 16.5px; font-weight: 700; color: var(--navy-deep);">Accessory Scrap &amp; Disposal Slip</h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeSlipModalBtn" title="Close Drawer">&times;</button>
    </div>

    <div class="drawer-content" style="flex: 1; overflow-y: auto; padding: 20px 24px;">
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
                        <div class="slip-doc-pill">PERIPHERAL DECOMMISSION &amp; SCRAP DISPOSAL SLIP</div>
                    </td>
                    <td class="slip-meta-cell">
                        <div><strong>Slip No:</strong> <span id="slipRefId" style="font-family: monospace; font-weight: 700;">SCR1026001</span></div>
                        <div><strong>Date:</strong> <span id="slipDate">12 Oct 2026</span></div>
                        <div><strong>Type:</strong> Scrap / Decommission</div>
                        <div><strong>Status:</strong> <span id="slipStatusBadge" class="slip-status-tag">In Scrap Yard</span></div>
                    </td>
                </tr>
            </table>

            <!-- 2. Section 1: Item & Peripheral Details -->
            <div class="slip-sec-heading">1. Decommissioned Peripheral / Accessory Details</div>
            <table class="slip-data-tbl">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th>Accessory Name &amp; Specifications</th>
                        <th style="width: 130px;">SKU Code</th>
                        <th style="width: 130px;">Category</th>
                        <th style="width: 80px; text-align: center;">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">1</td>
                        <td><strong id="slipAccName">Dell 65W USB-C Power Adapter</strong></td>
                        <td><span id="slipSku" class="slip-code-mono">ACC-CAB-004</span></td>
                        <td id="slipCategory">Cables &amp; Power</td>
                        <td style="text-align: center;"><strong id="slipQty" style="font-size: 13px;">6 Units</strong></td>
                    </tr>
                </tbody>
            </table>

            <!-- 3. Section 2: Technical Diagnostics & Disposal Target -->
            <div class="slip-sec-heading">2. Technical Diagnostics &amp; Disposal Target</div>
            <table class="slip-data-tbl">
                <tr>
                    <td class="slip-lbl">Defect / Scrap Reason</td>
                    <td class="slip-val" id="slipReason">Wire Frayed &amp; Melted Pin</td>
                    <td class="slip-lbl">Hardware Condition</td>
                    <td class="slip-val" id="slipCondition">Beyond Economic Repair (BER)</td>
                </tr>
                <tr>
                    <td class="slip-lbl">Storage / Bin Location</td>
                    <td class="slip-val" id="slipLocation">IT Scrap Yard Bin 1</td>
                    <td class="slip-lbl">Disposal Method / Target</td>
                    <td class="slip-val" id="slipStatus">In Scrap Yard</td>
                </tr>
                <tr>
                    <td class="slip-lbl">Estimated Salvage Realization</td>
                    <td class="slip-val" id="slipValue" style="font-weight: 700; color: #0284c7;">₹300.00</td>
                    <td class="slip-lbl">Decommission Date</td>
                    <td class="slip-val" id="slipDateAlt">12 Oct 2026</td>
                </tr>
            </table>

            <!-- 4. Section 3: Diagnostic Findings & Remarks -->
            <div class="slip-sec-heading">3. Diagnostic Findings &amp; Remarks</div>
            <table class="slip-data-tbl">
                <tr>
                    <td class="slip-lbl" style="width: 22%;">Authorized By</td>
                    <td class="slip-val" id="slipApprover" style="width: 28%; font-weight: 700;">Abhishek Sharma</td>
                    <td class="slip-lbl" style="width: 20%;">Inspection Notes</td>
                    <td class="slip-val" id="slipNotes" style="width: 30%;">Hardware verified defective and cleared for scrap disposal.</td>
                </tr>
            </table>

            <!-- 5. Section 4: Declaration & Environmental Compliance -->
            <div class="slip-declaration-box">
                <strong>Official Decommission Certification:</strong> I hereby certify that the physical hardware / accessory items listed above have undergone technical diagnostic evaluation and are confirmed beyond economic repair (BER) or unserviceable. These items have been struck off from active inventory stock and scheduled for authorized scrap disposal / e-waste recycling in compliance with corporate asset disposal policies and e-waste management guidelines.
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

    <div class="drawer-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #ffffff;">
        <button type="button" class="btn-secondary" id="closeSlipModalFooterBtn">Close</button>
        <button type="button" class="btn-primary" id="printScrapSlipBtn" style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; font-weight: 600;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Official Slip
        </button>
    </div>
</aside>

<?php include 'includes/footer.php'; ?>
