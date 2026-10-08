<?php
// Asset Management & IT Service Desk Portal - Parts & Components Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Parts & Components Management - VIROS Portal";
$active_page = "components";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/accessories.css', 'css/searchable-select.css'];
$extra_js  = ['js/components.js'];

// Include database to fetch dynamic component categories & components data
require_once __DIR__ . '/config/db.php';

$dynamicCategories = [];
$branchLocations = [];
if (isset($conn) && $conn !== false) {
    $catQuery = "SELECT id, category_name FROM component_categories WHERE status = 'Active' ORDER BY category_name ASC";
    $catStmt = sqlsrv_query($conn, $catQuery);
    if ($catStmt !== false) {
        while ($row = sqlsrv_fetch_array($catStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row['category_name'])) {
                $dynamicCategories[] = $row['category_name'];
            }
        }
        sqlsrv_free_stmt($catStmt);
    }

    $locQuery = "SELECT id, location_name FROM locations WHERE status = 'Active' ORDER BY location_name ASC";
    $locStmt = sqlsrv_query($conn, $locQuery);
    if ($locStmt !== false) {
        while ($row = sqlsrv_fetch_array($locStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row['location_name'])) {
                $branchLocations[] = $row['location_name'];
            }
        }
        sqlsrv_free_stmt($locStmt);
    }
}

if (empty($dynamicCategories)) {
    $dynamicCategories = [
        'RAM & Memory Modules',
        'Solid State Drives (SSD)',
        'Hard Disk Drives (HDD)',
        'Graphics & GPU Cards',
        'Processors & CPUs',
        'Motherboards & Logic Boards',
        'Power Supply Units (PSU)',
        'Laptop Batteries',
        'Cooling Fans & Heatsinks',
        'Network Interface Cards (NIC)'
    ];
}

if (empty($branchLocations)) {
    $branchLocations = [
        'Corporate HQ - Mumbai',
        'Tech Hub - Bangalore',
        'Branch Office - Delhi NCR',
        'Delivery Center - Hyderabad',
        'Development Center - Pune',
        'Operations Center - Chennai',
        'Regional Hub - Kolkata',
        'Support Center - Ahmedabad',
        'Disaster Recovery Site - Jaipur'
    ];
}

// Fetch initial components from database for real-data rendering
$initialComponents = [];
$stats = ['total' => 0, 'available' => 0, 'installed' => 0, 'repair' => 0];

if (isset($conn) && $conn !== false) {
    $compQuery = "SELECT c.id, c.sku, c.serial, c.name, c.category, c.brand, c.model, c.specs, c.status, 
                         c.installed_asset, c.location, c.branch_location,
                         a.id AS host_asset_id, a.tag AS host_asset_tag, a.name AS host_asset_name,
                         CONVERT(VARCHAR(10), c.created_at, 105) AS created_date
                  FROM components c
                  LEFT JOIN assets a ON (TRY_CAST(c.installed_asset AS INT) = a.id OR c.installed_asset = a.tag)
                  ORDER BY c.id DESC";
    $compStmt = sqlsrv_query($conn, $compQuery);
    if ($compStmt !== false) {
        while ($row = sqlsrv_fetch_array($compStmt, SQLSRV_FETCH_ASSOC)) {
            $hostDisplay = '';
            if (!empty($row['host_asset_tag'])) {
                $hostDisplay = $row['host_asset_tag'] . ' (' . $row['host_asset_name'] . ')';
            } elseif (!empty($row['installed_asset'])) {
                $hostDisplay = $row['installed_asset'];
            }

            $initialComponents[] = [
                'id'              => intval($row['id']),
                'sku'             => $row['sku'],
                'serial'          => $row['serial'],
                'name'            => $row['name'],
                'category'        => $row['category'],
                'branch_location' => $row['branch_location'] ?? '',
                'brand'           => $row['brand'],
                'model'           => $row['model'] ?? '',
                'specs'           => $row['specs'] ?? '',
                'status'          => $row['status'],
                'installedAsset'  => $hostDisplay,
                'installedAssetId'=> $row['installed_asset'] ?? '',
                'host_asset_tag'  => $row['host_asset_tag'] ?? '',
                'host_asset_name' => $row['host_asset_name'] ?? '',
                'location'        => $row['location'] ?? '',
                'created_date'    => $row['created_date'] ?? ''
            ];
            $st = $row['status'] ?? '';
            if ($st === 'Available') {
                $stats['available']++;
            } elseif ($st === 'Installed') {
                $stats['installed']++;
            } elseif ($st === 'Under Repair' || $st === 'Defective') {
                $stats['repair']++;
            }
        }
        $stats['total'] = count($initialComponents);
        sqlsrv_free_stmt($compStmt);
    }
}

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Parts & Components Management Content (Single-Item Asset Tracking) -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Assets</span>
                <span>/</span>
                <span>Parts & Components</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
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
                Parts & Components Management
            </h1>
            <p>Individual tracking for modular hardware parts, RAM sticks, SSDs, GPUs, and replacement spares.</p>
        </div>

        <div class="page-header-actions">
            <button type="button" class="btn-secondary" id="exportCompBtn" title="Export components list to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button type="button" class="btn-secondary" id="openImportModalBtn" title="Import components from CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Import CSV
            </button>
            <button type="button" class="btn-primary" id="openAddModalBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Component
            </button>
        </div>
    </div>

    <!-- 4 Clean Metric Cards -->
    <div class="cat-stats-grid">
        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Total Components</div>
                <div class="stat-val" id="statTotalItems"><?php echo $stats['total']; ?></div>
            </div>
            <div class="cat-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Available in Stock</div>
                <div class="stat-val" id="statAvailable" style="color: var(--success);"><?php echo $stats['available']; ?></div>
            </div>
            <div class="cat-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Installed in Assets</div>
                <div class="stat-val" id="statInstalled" style="color: var(--cyan-primary);"><?php echo $stats['installed']; ?></div>
            </div>
            <div class="cat-stat-icon navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">In Repair / Defective</div>
                <div class="stat-val" id="statRepair" style="color: #ea580c;"><?php echo $stats['repair']; ?></div>
            </div>
            <div class="cat-stat-icon" style="background: #fff7ed; color: #ea580c;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="toolbar-card">
        <div class="toolbar-filters">
            <div class="search-box-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="searchInput" placeholder="Search parts by name, serial no, brand, part SKU, specs...">
            </div>

            <select class="filter-select" id="branchFilter">
                <option value="all">All Branch Locations</option>
                <?php foreach ($branchLocations as $branch): ?>
                    <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                <?php endforeach; ?>
            </select>

            <select class="filter-select" id="categoryFilter">
                <option value="all">All Part / Component Categories</option>
                <?php foreach ($dynamicCategories as $catName): ?>
                    <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                <?php endforeach; ?>
            </select>

            <select class="filter-select" id="statusFilter">
                <option value="all">All Statuses</option>
                <option value="Available">Available (In Stock)</option>
                <option value="Installed">Installed in Asset</option>
                <option value="Under Repair">Under Repair</option>
                <option value="Defective">Defective</option>
            </select>

            <button type="button" class="btn-filter-reset" id="resetFilterBtn">Reset Filters</button>
        </div>
    </div>

    <!-- Table View Container (matching assets.php design) -->
    <div class="asset-table-card">
        <div class="asset-table-responsive">
            <table class="asset-data-table" id="componentsTable">
                <thead>
                    <tr>
                        <th style="width: 140px; white-space: nowrap;">Part / SKU Tag</th>
                        <th style="width: 140px; white-space: nowrap;">Serial No.</th>
                        <th style="min-width: 220px;">Component Name & Depot</th>
                        <th style="width: 170px;">Part / Component Category</th>
                        <th style="width: 160px;">Brand & Specs</th>
                        <th style="width: 190px;">Installed In (Asset)</th>
                        <th style="width: 130px;">Status</th>
                        <th style="width: 120px; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="componentsTbody">
                    <?php if (empty($initialComponents)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="table-empty-state">
                                    <div class="empty-icon">⚙️</div>
                                    <div class="empty-title">No Components Found</div>
                                    <div class="empty-desc">No parts found in the database. Click 'Add Component' to register one.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($initialComponents as $item): 
                            $status = $item['status'];
                            if ($status === 'Available') {
                                $statusBadge = '<span class="asset-status-badge status-available"><span class="dot"></span>Available</span>';
                            } elseif ($status === 'Installed') {
                                $statusBadge = '<span class="asset-status-badge status-in-use"><span class="dot"></span>Installed</span>';
                            } elseif ($status === 'Under Repair') {
                                $statusBadge = '<span class="asset-status-badge status-maintenance"><span class="dot"></span>Under Repair</span>';
                            } else {
                                $statusBadge = '<span class="asset-status-badge status-retired" style="background:#fef2f2; color:#b91c1c;"><span class="dot" style="background:#ef4444;"></span>Defective</span>';
                            }

                            if ($status === 'Installed' && !empty($item['installedAsset'])) {
                                $assetCol = '<span class="asset-badge" title="Host Device">'
                                          . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg> '
                                          . htmlspecialchars($item['installedAsset']) . '</span>';
                            } elseif ($status === 'Under Repair') {
                                $assetCol = '<span style="color: #ea580c; font-size: 12px; font-weight: 500;">In Service Depot</span>';
                            } else {
                                $assetCol = '<span style="color: var(--text-muted); font-size: 12px;">— (Unassigned / In Stock)</span>';
                            }
                        ?>
                            <tr data-id="<?php echo $item['id']; ?>">
                                <td style="white-space: nowrap;">
                                    <span class="asset-tag-badge" onclick="openComponentDrawer(<?php echo $item['id']; ?>)" style="cursor: pointer;" title="View component details"><?php echo htmlspecialchars($item['sku']); ?></span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <span class="serial-badge" onclick="openComponentDrawer(<?php echo $item['id']; ?>)" style="cursor: pointer;" title="View component details"><?php echo htmlspecialchars($item['serial'] ?: '—'); ?></span>
                                </td>
                                <td>
                                    <div class="asset-details-wrap">
                                        <span class="asset-name-title" onclick="openComponentDrawer(<?php echo $item['id']; ?>)" style="cursor: pointer;"><?php echo htmlspecialchars($item['name']); ?></span>
                                        <span class="asset-spec-sub">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                            <?php 
                                            $locParts = [];
                                            if (!empty($item['branch_location'])) $locParts[] = $item['branch_location'];
                                            if (!empty($item['location'])) $locParts[] = $item['location'];
                                            echo htmlspecialchars(!empty($locParts) ? implode(' • ', $locParts) : 'Depot Shelf'); 
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="category-pill"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <td>
                                    <div style="font-weight: 500; font-size: 13px; color: var(--text-primary);"><?php echo htmlspecialchars($item['brand']); ?></div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;"><?php echo htmlspecialchars($item['specs'] ?: ($item['model'] ?: '—')); ?></div>
                                </td>
                                <td><?php echo $assetCol; ?></td>
                                <td><?php echo $statusBadge; ?></td>
                                <td>
                                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                        <button class="action-icon-btn btn-view" title="View Details" onclick="openComponentDrawer(<?php echo $item['id']; ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        </button>
                                        <button class="action-icon-btn btn-qr" title="Print Barcode / QR Label" onclick="openLabelModal(<?php echo $item['id']; ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                        </button>
                                        <button class="action-icon-btn btn-edit" title="Edit Component" onclick="window.compMgr.openEdit(<?php echo $item['id']; ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </button>
                                        <button class="action-icon-btn btn-delete" title="Delete Component" onclick="window.compMgr.deleteItem(<?php echo $item['id']; ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
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

</main>

<!-- =========================================================================
     SLIDE-OVER DRAWER (Component Details & Specifications)
     ========================================================================= -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>

<aside class="asset-drawer" id="componentDrawer">
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerCompSku" style="font-size: 12px; font-weight: 700;"></span>
                    <span id="drawerCompStatus"></span>
                    <span class="serial-badge" id="drawerCompSerial" style="font-size: 12px; font-weight: 600;"></span>
                </div>
                <h3 id="drawerCompName" style="margin-top: 4px;"></h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeDrawerBtn" title="Close Drawer">&times;</button>
    </div>

    <!-- Drawer Navigation Tabs -->
    <div class="drawer-tabs">
        <button type="button" class="drawer-tab active" data-tab="overview">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Overview & Specs
        </button>
        <button type="button" class="drawer-tab" data-tab="host_asset">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            Host Machine / Deployment
        </button>
    </div>

    <!-- Drawer Tab Panes -->
    <div class="drawer-content">
        <!-- Pane 1: Overview & Specs -->
        <div class="drawer-tab-pane active" id="pane_overview">
            <!-- Hardware Specifications -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
                    Component Specifications
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Part / SKU Tag</div>
                        <div class="value" id="specCompSku" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Part Category</div>
                        <div class="value" id="specCompCategory">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Manufacturer / Brand</div>
                        <div class="value" id="specCompBrand">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Model / Part No.</div>
                        <div class="value" id="specCompModel">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Serial Number</div>
                        <div class="value" id="specCompSerial" style="font-family: monospace;">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Current Status</div>
                        <div class="value" id="specCompStatusText">-</div>
                    </div>
                    <div class="drawer-spec-item" style="grid-column: 1 / -1;">
                        <div class="label">Technical Specifications & Speed</div>
                        <div class="value" id="specCompSpecs">-</div>
                    </div>
                </div>
            </div>

            <!-- Location & Storage -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Deployment Location & Storage
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Assigned Branch / Site</div>
                        <div class="value" id="specCompBranch">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Depot Shelf / Rack Location</div>
                        <div class="value" id="specCompLocation">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Registered Date</div>
                        <div class="value" id="specCompCreatedDate">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pane 2: Host Machine Deployment -->
        <div class="drawer-tab-pane" id="pane_host_asset">
            <div class="drawer-section">
                <div class="drawer-section-title">Host Machine Deployment</div>
                <div id="drawerHostAssetWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div class="drawer-footer" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        <button type="button" class="btn-danger" style="background: #ef4444; color: #fff; border: 1px solid #dc2626; padding: 8px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="if(window.activeDrawerCompId){window.compMgr.deleteItem(window.activeDrawerCompId);}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Delete Component
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn-secondary" onclick="if(window.activeDrawerCompId){openLabelModal(window.activeDrawerCompId);}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Print Label
            </button>
            <button type="button" class="btn-primary" onclick="if(window.activeDrawerCompId){window.compMgr.openEdit(window.activeDrawerCompId);}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                Edit Component
            </button>
        </div>
    </div>
</aside>

<!-- ==================== ADD COMPONENT MODAL (SINGLE ITEM) ==================== -->
<div class="modal-overlay" id="addComponentModal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <h3>Add New Component / Part</h3>
            <button class="modal-close-btn" id="closeAddModalBtn">&times;</button>
        </div>
        <form id="addComponentForm">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="compCategory">Part / Component Category *</label>
                        <select id="compCategory" required>
                            <option value="">Select Part / Component Category</option>
                            <?php foreach ($dynamicCategories as $catName): ?>
                                <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="compBranch">Select Branch Location *</label>
                        <select id="compBranch" required>
                            <option value="">Select Branch Location</option>
                            <?php foreach ($branchLocations as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="compSku" value="">

                <div class="modal-form-group">
                    <label for="compName">Component / Part Name *</label>
                    <input type="text" id="compName" placeholder="e.g. Crucial 16GB DDR4 3200MHz SO-DIMM" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="compBrand">Brand / Manufacturer *</label>
                        <input type="text" id="compBrand" placeholder="e.g. Crucial / Samsung / Dell" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="compModel">Part No. / Model</label>
                        <input type="text" id="compModel" placeholder="e.g. CT16G4SFD832A">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="compSerial">Serial Number *</label>
                        <input type="text" id="compSerial" placeholder="e.g. SN-CRU-892102" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="compStatus">Initial Status *</label>
                        <select id="compStatus" required>
                            <option value="Available">Available (In Stock)</option>
                            <option value="Installed">Installed in Asset</option>
                            <option value="Under Repair">Under Repair</option>
                            <option value="Defective">Defective</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="compSpecs">Technical Specifications / Speed</label>
                    <input type="text" id="compSpecs" placeholder="e.g. 16GB DDR4 3200MHz CL22 1.2V 260-Pin">
                </div>

                <div class="modal-form-group">
                    <label for="compLocation">Storage Depot / Shelf Location</label>
                    <input type="text" id="compLocation" placeholder="e.g. Server Room Depot (Rack 3, Drawer B)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAddModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveCompBtn">Save Component</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT COMPONENT MODAL ==================== -->
<div class="modal-overlay" id="editComponentModal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <h3>Edit Component / Part</h3>
            <button class="modal-close-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editComponentForm">
            <input type="hidden" id="editCompId" value="">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="editCompCategory">Part / Component Category *</label>
                        <select id="editCompCategory" required>
                            <option value="">Select Part / Component Category</option>
                            <?php foreach ($dynamicCategories as $catName): ?>
                                <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="editCompBranch">Select Branch Location *</label>
                        <select id="editCompBranch" required>
                            <option value="">Select Branch Location</option>
                            <?php foreach ($branchLocations as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="editCompSku" value="">

                <div class="modal-form-group">
                    <label for="editCompName">Component / Part Name *</label>
                    <input type="text" id="editCompName" placeholder="e.g. Crucial 16GB DDR4 3200MHz SO-DIMM" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="editCompBrand">Brand / Manufacturer *</label>
                        <input type="text" id="editCompBrand" placeholder="e.g. Crucial / Samsung / Dell" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="editCompModel">Part No. / Model</label>
                        <input type="text" id="editCompModel" placeholder="e.g. CT16G4SFD832A">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="editCompSerial">Serial Number *</label>
                        <input type="text" id="editCompSerial" placeholder="e.g. SN-CRU-892102" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="editCompStatus">Status *</label>
                        <select id="editCompStatus" required>
                            <option value="Available">Available (In Stock)</option>
                            <option value="Installed">Installed in Asset</option>
                            <option value="Under Repair">Under Repair</option>
                            <option value="Defective">Defective</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="editCompSpecs">Technical Specifications / Speed</label>
                    <input type="text" id="editCompSpecs" placeholder="e.g. 16GB DDR4 3200MHz CL22 1.2V 260-Pin">
                </div>

                <div class="modal-form-group">
                    <label for="editCompLocation">Storage Depot / Shelf Location</label>
                    <input type="text" id="editCompLocation" placeholder="e.g. Server Room Depot (Rack 3, Drawer B)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelEditModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="updateCompBtn">Update Component</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== DELETE CONFIRMATION MODAL ==================== -->
<div class="modal-overlay" id="deleteComponentModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 style="font-size: 17px; color: var(--navy-primary); margin-bottom: 8px;">Delete Component / Part?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px;">
                Are you sure you want to delete <strong id="deleteComponentName" style="color: var(--text-primary);"></strong> from database? This action cannot be undone.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelDeleteModalBtn">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">Yes, Delete Component</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== IMPORT CSV MODAL ==================== -->
<div class="modal-overlay" id="importModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Import Components from CSV</h3>
            <button class="modal-close-btn" id="closeImportModalBtn">&times;</button>
        </div>
        <form id="importForm">
            <div class="modal-body">
                <!-- Template Download Box -->
                <div class="import-template-box">
                    <div class="template-text">
                        Download sample CSV template with single-item serial headers.
                    </div>
                    <button type="button" class="btn-template-download" id="downloadTemplateBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Sample CSV
                    </button>
                </div>

                <!-- Drag & Drop Upload Zone -->
                <div class="dropzone-box" id="csvDropzone">
                    <input type="file" id="csvFileInput" accept=".csv, text/csv" style="display: none;">
                    <div class="dropzone-icon">
                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="12" y1="18" x2="12" y2="12"></line>
                            <line x1="9" y1="15" x2="12" y2="12"></line>
                            <line x1="15" y1="15" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="dropzone-text">
                        <strong>Click to browse</strong> or drag & drop CSV file here
                    </div>
                    <div class="dropzone-hint">Supports .csv files up to 5MB</div>
                </div>

                <!-- Selected File Preview -->
                <div class="file-preview-card" id="filePreviewCard" style="display: none;">
                    <div class="file-preview-left">
                        <div class="file-icon-badge">CSV</div>
                        <div>
                            <div class="file-name" id="previewFileName">filename.csv</div>
                            <div class="file-meta" id="previewFileMeta">0 KB • 0 components detected</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-file" id="removeFileBtn" title="Remove file">&times;</button>
                </div>

                <!-- Duplicate Option -->
                <div class="modal-form-group">
                    <label for="duplicateHandling">Duplicate Serial Numbers Handling</label>
                    <select id="duplicateHandling">
                        <option value="skip">Skip duplicates (keep existing)</option>
                        <option value="update">Update existing components</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelImportModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="startImportBtn" disabled>Import Components</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL: Print Thermal Barcode & QR Sticker Modal
     ========================================================================= -->
<div class="modal-overlay" id="labelModal" style="display: none;">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Print Physical Component Label</h3>
            <button type="button" class="modal-close-btn" onclick="closeLabelModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Select Printer & Hardware Configuration Panel -->
            <div class="printer-selection-panel" style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 13px 15px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <label for="labelPrinterSelect" style="font-size: 12.5px; font-weight: 700; color: var(--navy-primary); display: flex; align-items: center; gap: 7px; margin: 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Select System Printer
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span id="printerStatusBadge" style="font-size: 11px; font-weight: 600; color: #16a34a; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
                            Ready / Online
                        </span>
                        <button type="button" id="refreshPrintersBtn" onclick="refreshSystemPrinters(true)" title="Scan / Refresh System Printers" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <div style="position: relative;">
                    <select id="labelPrinterSelect" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 13px; font-weight: 500; background: #ffffff; color: var(--text-primary); cursor: pointer;" onchange="handleLabelPrinterChange(this.value)">
                        <option value="" disabled selected>Detecting installed system printers...</option>
                    </select>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 9px; padding-top: 8px; border-top: 1px dashed #e2e8f0; font-size: 11.5px; color: var(--text-secondary);">
                    <div id="printerMediaInfo" style="display: flex; align-items: center; gap: 5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        <span id="selectedPrinterNameText">Printer: <strong>Detecting...</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="labelCopiesCount" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin: 0;">Copies:</label>
                        <select id="labelCopiesCount" style="padding: 2px 6px; font-size: 11.5px; border: 1px solid var(--border-color); border-radius: 4px; background: #ffffff; cursor: pointer;">
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Component Details Summary Card -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; margin-bottom: 16px; box-shadow: var(--shadow-sm);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Component To Print</span>
                    <span id="lblStickerTag" style="font-family: monospace; font-size: 12px; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 2px 8px; border-radius: 4px;"></span>
                </div>
                <div style="font-size: 14.5px; font-weight: 700; color: var(--navy-primary); margin-bottom: 4px;" id="lblStickerName"></div>
                <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: var(--text-secondary);">
                    <div id="lblStickerSerial"></div>
                    <div id="lblStickerCategory"></div>
                </div>
                <div style="font-size: 11px; color: #0369a1; background: #f0f9ff; border: 1px solid #bae6fd; padding: 7px 10px; border-radius: 6px; margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span>Template: <strong>Fortune Marketing (50x25mm ZPL)</strong> will be sent directly to your thermal printer.</span>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-secondary" onclick="closeLabelModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="printSticker()" id="btnPrintStickerBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Sticker
            </button>
        </div>
    </div>
</div>

<script>
    window.INITIAL_COMPONENTS = <?php echo json_encode($initialComponents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.BRANCH_LOCATIONS = <?php echo json_encode($branchLocations, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Modular Layout Footer
include 'includes/footer.php';
?>
