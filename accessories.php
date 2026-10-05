<?php
// Asset Management & IT Service Desk Portal - Accessories Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Accessories Management - VIROS Portal";
$active_page = "accessories";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/accessories.css', 'css/searchable-select.css'];
$extra_js  = ['js/accessories.js'];

// Include database to fetch dynamic accessory categories, stats, and initial records
require_once __DIR__ . '/config/db.php';

$dynamicCategories = [];
$branchLocations = [];
$stats = ['total' => 0, 'in_stock' => 0, 'deployed' => 0, 'low_stock' => 0];
$initialAccessories = [];

if (isset($conn) && $conn !== false) {
    // 1. Fetch Active Accessory Categories
    $catQuery = "SELECT id, category_name FROM accessory_categories WHERE status = 'Active' ORDER BY category_name ASC";
    $catStmt = sqlsrv_query($conn, $catQuery);
    if ($catStmt !== false) {
        while ($row = sqlsrv_fetch_array($catStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row['category_name'])) {
                $dynamicCategories[] = $row['category_name'];
            }
        }
        sqlsrv_free_stmt($catStmt);
    }

    // 2. Fetch Active Branch Locations
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

    // Fallback default branches if table is empty
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

    // 3. Fetch Live Stats
    $sQuery = "SELECT 
                COUNT(*) AS total,
                ISNULL(SUM(in_stock), 0) AS in_stock,
                ISNULL(SUM(deployed), 0) AS deployed,
                ISNULL(SUM(CASE WHEN in_stock > 0 AND in_stock <= min_stock THEN 1 ELSE 0 END), 0) AS low_stock
               FROM accessories";
    $sStmt = sqlsrv_query($conn, $sQuery);
    if ($sStmt !== false && ($sRow = sqlsrv_fetch_array($sStmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total'] = intval($sRow['total'] ?? 0);
        $stats['in_stock'] = intval($sRow['in_stock'] ?? 0);
        $stats['deployed'] = intval($sRow['deployed'] ?? 0);
        $stats['low_stock'] = intval($sRow['low_stock'] ?? 0);
        sqlsrv_free_stmt($sStmt);
    }

    // 4. Fetch Initial Accessories
    $aQuery = "SELECT id, sku, name, category, branch_location, brand, model, total_qty, in_stock, deployed, min_stock, location, 
                      CONVERT(VARCHAR(19), created_at, 120) as created_date
               FROM accessories 
               ORDER BY id DESC";
    $aStmt = sqlsrv_query($conn, $aQuery);
    if ($aStmt !== false) {
        while ($row = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC)) {
            $initialAccessories[] = [
                'id'              => intval($row['id']),
                'sku'             => $row['sku'],
                'name'            => $row['name'],
                'category'        => $row['category'],
                'branch_location' => $row['branch_location'] ?? '',
                'brand'           => $row['brand'],
                'model'           => $row['model'] ?? '',
                'totalQty'        => intval($row['total_qty']),
                'inStock'         => intval($row['in_stock']),
                'deployed'        => intval($row['deployed']),
                'minStock'        => intval($row['min_stock']),
                'location'        => $row['location'] ?? '',
                'created_date'    => $row['created_date'] ?? ''
            ];
        }
        sqlsrv_free_stmt($aStmt);
    }
}

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Simple Accessories Management Content -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Assets</span>
                <span>/</span>
                <span>Accessories Management</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="6" y1="8" x2="6.01" y2="8"></line>
                    <line x1="10" y1="8" x2="10.01" y2="8"></line>
                    <line x1="14" y1="8" x2="14.01" y2="8"></line>
                    <line x1="18" y1="8" x2="18.01" y2="8"></line>
                    <line x1="6" y1="12" x2="6.01" y2="12"></line>
                    <line x1="10" y1="12" x2="10.01" y2="12"></line>
                    <line x1="14" y1="12" x2="14.01" y2="12"></line>
                    <line x1="18" y1="12" x2="18.01" y2="12"></line>
                    <line x1="7" y1="16" x2="17" y2="16"></line>
                </svg>
                Accessories Management
            </h1>
            <p>Manage keyboards, mice, docking stations, headsets, chargers, and IT peripheral stock.</p>
        </div>

        <div class="page-header-actions">
            <button type="button" class="btn-secondary" id="exportAccBtn" title="Export accessories list to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button type="button" class="btn-primary" id="openAddModalBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Accessory
            </button>
        </div>
    </div>

    <!-- 4 Clean Metric Cards -->
    <div class="cat-stats-grid">
        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Total Accessory Items</div>
                <div class="stat-val" id="statTotalItems"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="cat-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Available in Stock</div>
                <div class="stat-val" id="statInStock" style="color: var(--success);"><?php echo number_format($stats['in_stock']); ?></div>
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
                <div class="stat-lbl">Deployed / Issued</div>
                <div class="stat-val" id="statDeployed" style="color: var(--cyan-primary);"><?php echo number_format($stats['deployed']); ?></div>
            </div>
            <div class="cat-stat-icon navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Low Stock Alerts</div>
                <div class="stat-val" id="statLowStock" style="color: #ea580c;"><?php echo number_format($stats['low_stock']); ?></div>
            </div>
            <div class="cat-stat-icon" style="background: #fff7ed; color: #ea580c;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
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
                <input type="text" id="searchInput" placeholder="Search accessories by name, brand, SKU...">
            </div>

            <select class="filter-select" id="branchFilter">
                <option value="all">All Branch Locations</option>
                <?php foreach ($branchLocations as $branch): ?>
                    <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                <?php endforeach; ?>
            </select>

            <select class="filter-select" id="categoryFilter">
                <option value="all">All Accessory Categories</option>
                <?php foreach ($dynamicCategories as $catName): ?>
                    <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                <?php endforeach; ?>
            </select>

            <select class="filter-select" id="statusFilter">
                <option value="all">All Statuses</option>
                <option value="In Stock">In Stock</option>
                <option value="Low Stock">Low Stock</option>
                <option value="Out of Stock">Out of Stock</option>
            </select>

            <button type="button" class="btn-filter-reset" id="resetFilterBtn">Reset Filters</button>
        </div>
    </div>

    <!-- Table View Container (matching assets.php design) -->
    <div class="asset-table-card">
        <div class="asset-table-responsive">
            <table class="asset-data-table" id="accessoriesTable">
                <thead>
                    <tr>
                        <th style="width: 140px; white-space: nowrap;">SKU Tag</th>
                        <th style="min-width: 240px;">Accessory Name & Depot</th>
                        <th style="width: 170px;">Accessory Category</th>
                        <th style="width: 170px;">Brand & Model</th>
                        <th style="width: 110px; text-align: center;">In Stock</th>
                        <th style="width: 110px; text-align: center;">Deployed</th>
                        <th style="width: 140px;">Status</th>
                        <th style="width: 120px; text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="accessoriesTbody">
                    <?php if (empty($initialAccessories)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="table-empty-state">
                                    <div style="font-weight: 600; font-size: 15px; color: var(--text-primary); margin-bottom: 4px;">No accessories found</div>
                                    <div style="font-size: 12.5px; color: var(--text-muted);">Get started by adding your first IT accessory to the depot inventory.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($initialAccessories as $item): 
                            $inStock = intval($item['inStock']);
                            $minStock = intval($item['minStock']);
                            if ($inStock === 0) {
                                $statusBadge = '<span class="asset-status-badge status-retired" style="background:#fef2f2; color:#b91c1c;"><span class="dot" style="background:#ef4444;"></span>Out of Stock</span>';
                            } elseif ($inStock <= $minStock) {
                                $statusBadge = '<span class="asset-status-badge status-maintenance"><span class="dot"></span>Low Stock (' . $inStock . ')</span>';
                            } else {
                                $statusBadge = '<span class="asset-status-badge status-available"><span class="dot"></span>In Stock</span>';
                            }
                        ?>
                            <tr data-id="<?php echo $item['id']; ?>">
                                <td style="white-space: nowrap;">
                                    <span class="asset-tag-badge" title="Accessory SKU"><?php echo htmlspecialchars($item['sku']); ?></span>
                                </td>
                                <td>
                                    <div class="asset-details-wrap">
                                        <span class="asset-name-title" onclick="window.accMgr.openEdit(<?php echo $item['id']; ?>)"><?php echo htmlspecialchars($item['name']); ?></span>
                                        <span class="asset-spec-sub">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                            <?php 
                                            $locParts = [];
                                            if (!empty($item['branch_location'])) $locParts[] = $item['branch_location'];
                                            if (!empty($item['location'])) $locParts[] = $item['location'];
                                            echo htmlspecialchars(!empty($locParts) ? implode(' • ', $locParts) : 'Depot'); 
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="category-pill"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <td>
                                    <div style="font-weight: 500; font-size: 13px; color: var(--text-primary);"><?php echo htmlspecialchars($item['brand']); ?></div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;"><?php echo htmlspecialchars($item['model'] ?: '—'); ?></div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="qty-badge <?php echo $inStock === 0 ? 'qty-zero' : 'qty-in-stock'; ?>"><?php echo $inStock; ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="qty-badge qty-deployed"><?php echo intval($item['deployed']); ?></span>
                                </td>
                                <td><?php echo $statusBadge; ?></td>
                                <td>
                                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                        <button class="action-icon-btn btn-view" title="Issue to Staff" onclick="window.accMgr.openIssue(<?php echo $item['id']; ?>)" <?php echo $inStock === 0 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''; ?>>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                        </button>
                                        <button class="action-icon-btn btn-edit" title="Edit Accessory" onclick="window.accMgr.openEdit(<?php echo $item['id']; ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </button>
                                        <button class="action-icon-btn btn-delete" title="Delete Accessory" onclick="window.accMgr.deleteItem(<?php echo $item['id']; ?>)">
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

<!-- ==================== ADD ACCESSORY MODAL ==================== -->
<div class="modal-overlay" id="addAccessoryModal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <h3>Add New Accessory</h3>
            <button class="modal-close-btn" id="closeAddModalBtn">&times;</button>
        </div>
        <form id="addAccessoryForm">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="accCategory">Accessory Category *</label>
                        <select id="accCategory" required>
                            <option value="">Select Accessory Category</option>
                            <?php foreach ($dynamicCategories as $catName): ?>
                                <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="accBranch">Select Branch Location *</label>
                        <select id="accBranch" required>
                            <option value="">Select Branch Location</option>
                            <?php foreach ($branchLocations as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="accSku" value="">

                <div class="modal-form-group">
                    <label for="accName">Accessory Name *</label>
                    <input type="text" id="accName" placeholder="e.g. Logitech MX Master 3S Wireless Mouse" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="accBrand">Brand / Manufacturer *</label>
                        <input type="text" id="accBrand" placeholder="e.g. Logitech" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="accModel">Model Variant</label>
                        <input type="text" id="accModel" placeholder="e.g. MX Master 3S">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="accQty">Total Quantity *</label>
                        <input type="number" id="accQty" min="1" value="10" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="accMinStock">Min Stock Alert Level</label>
                        <input type="number" id="accMinStock" min="1" value="5">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="accLocation">Depot / Storage Location</label>
                    <input type="text" id="accLocation" placeholder="e.g. HQ - New York Depot (Shelf A-02)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAddModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveAccBtn">Save Accessory</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT ACCESSORY MODAL ==================== -->
<div class="modal-overlay" id="editAccessoryModal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <h3>Edit Accessory</h3>
            <button class="modal-close-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editAccessoryForm">
            <input type="hidden" id="editAccId" value="">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="editAccCategory">Accessory Category *</label>
                        <select id="editAccCategory" required>
                            <option value="">Select Accessory Category</option>
                            <?php foreach ($dynamicCategories as $catName): ?>
                                <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="editAccBranch">Select Branch Location *</label>
                        <select id="editAccBranch" required>
                            <option value="">Select Branch Location</option>
                            <?php foreach ($branchLocations as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="editAccSku" value="">

                <div class="modal-form-group">
                    <label for="editAccName">Accessory Name *</label>
                    <input type="text" id="editAccName" placeholder="e.g. Logitech MX Master 3S Wireless Mouse" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="editAccBrand">Brand / Manufacturer *</label>
                        <input type="text" id="editAccBrand" placeholder="e.g. Logitech" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="editAccModel">Model Variant</label>
                        <input type="text" id="editAccModel" placeholder="e.g. MX Master 3S">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="editAccQty">Total Quantity *</label>
                        <input type="number" id="editAccQty" min="1" value="10" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="editAccMinStock">Min Stock Alert Level</label>
                        <input type="number" id="editAccMinStock" min="1" value="5">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="editAccLocation">Depot / Storage Location</label>
                    <input type="text" id="editAccLocation" placeholder="e.g. HQ - New York Depot (Shelf A-02)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelEditModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="updateAccBtn">Update Accessory</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== DELETE CONFIRMATION MODAL ==================== -->
<div class="modal-overlay" id="deleteAccessoryModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 style="font-size: 17px; color: var(--navy-primary); margin-bottom: 8px;">Delete Accessory?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px;">
                Are you sure you want to delete <strong id="deleteAccessoryName" style="color: var(--text-primary);"></strong>? This action cannot be undone.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelDeleteModalBtn">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">Yes, Delete Accessory</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== QUICK ISSUE (CHECK-OUT) MODAL ==================== -->
<div class="modal-overlay" id="issueModal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Issue Accessory to Staff</h3>
            <button class="modal-close-btn" id="closeIssueModalBtn">&times;</button>
        </div>
        <form id="issueForm">
            <input type="hidden" id="issueAccId" value="">
            <div class="modal-body">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                    <div style="font-weight: 700; font-size: 14px; color: var(--navy-primary);" id="issueAccName">Accessory Name</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                        Available Stock: <strong style="color: var(--success);" id="issueAvailableStock">0</strong> units
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="issueEmployee">Assign to Employee *</label>
                    <select id="issueEmployee" required>
                        <option value="">Select Employee</option>
                        <option value="Priya Sharma">Priya Sharma — Software Engineering</option>
                        <option value="Rahul Mehta">Rahul Mehta — Design & Creative</option>
                        <option value="Arjun Verma">Arjun Verma — IT Infrastructure</option>
                        <option value="Sneha Patel">Sneha Patel — Finance & Accounts</option>
                        <option value="Vikram Rathore">Vikram Rathore — Operations</option>
                        <option value="Aditi Rao">Aditi Rao — Human Resources</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="modal-form-group">
                        <label for="issueQty">Quantity to Issue *</label>
                        <input type="number" id="issueQty" min="1" value="1" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="issueDate">Issue Date *</label>
                        <input type="date" id="issueDate" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelIssueBtn">Cancel</button>
                <button type="submit" class="btn-primary">Confirm Issue</button>
            </div>
        </form>
    </div>
</div>
<!-- Pass initial SQL Server dataset to client script -->
<script>
    window.INITIAL_ACCESSORIES = <?php echo json_encode($initialAccessories, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    window.BRANCH_LOCATIONS = <?php echo json_encode($branchLocations, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Modular Layout Footer
include 'includes/footer.php';
?>


