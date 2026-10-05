<?php
// Asset Management & IT Service Desk Portal - Hardware & IT Asset Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "IT Asset Management - VIROS Portal";
$active_page = "assets";
$extra_css = ['css/categories.css', 'css/assets.css'];
$extra_js  = ['js/assets.js'];

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Asset Management Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Assets</span>
                <span>/</span>
                <span>Asset Management</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                Hardware & IT Asset Management
                <span class="status-tab-badge" style="background: var(--cyan-light); color: var(--cyan-primary); font-size: 13px; font-weight: 700; padding: 3px 10px;">842 Assets</span>
            </h1>
            <p>Track hardware inventory, warranty lifecycles, user allocations, and asset depreciation across all branch offices.</p>
        </div>

        <div class="page-header-actions">

            <!-- Export CSV -->
            <button type="button" class="btn-secondary" id="exportAssetsBtn" title="Export assets to CSV" onclick="exportAssetsCsv()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Export CSV</span>
            </button>

            <!-- Import CSV -->
            <button type="button" class="btn-secondary" id="openImportBtn" title="Import assets from CSV" onclick="openImportModal()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Import CSV</span>
            </button>

            <!-- Add Asset Modal Trigger -->
            <button type="button" class="btn-primary" id="openAddModalBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add Asset</span>
            </button>
        </div>
    </div>

    <!-- Executive Metric KPI Strip -->
    <div class="asset-stats-grid">
        <!-- Total Assets -->
        <div class="asset-stat-card card-total" onclick="resetAllFilters()">
            <div class="asset-stat-info">
                <div class="stat-lbl">Total Assets</div>
                <div class="stat-val" id="statTotalAssets">842</div>
                <div class="stat-sub">
                    <span class="stat-trend-up">↑ 14</span> added this month
                </div>
            </div>
            <div class="asset-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <!-- In Use -->
        <div class="asset-stat-card card-in-use" onclick="document.querySelector('[data-status=in-use]').click()">
            <div class="asset-stat-info">
                <div class="stat-lbl">In Use / Assigned</div>
                <div class="stat-val" id="statInUseAssets">614</div>
                <div class="stat-sub">
                    <span style="color: var(--success); font-weight: 600;">72.9%</span> deployed rate
                </div>
            </div>
            <div class="asset-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
            </div>
        </div>

        <!-- Available / Ready Stock -->
        <div class="asset-stat-card card-available" onclick="document.querySelector('[data-status=available]').click()">
            <div class="asset-stat-info">
                <div class="stat-lbl">Available in Stock</div>
                <div class="stat-val" id="statAvailableAssets">156</div>
                <div class="stat-sub">Ready for issue</div>
            </div>
            <div class="asset-stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
        </div>

        <!-- In Maintenance -->
        <div class="asset-stat-card card-maintenance" onclick="document.querySelector('[data-status=maintenance]').click()">
            <div class="asset-stat-info">
                <div class="stat-lbl">Under Maintenance</div>
                <div class="stat-val" id="statMaintenanceAssets">42</div>
                <div class="stat-sub">IT Depot & RMA</div>
            </div>
            <div class="asset-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
        </div>

        <!-- Expiring Warranty -->
        <div class="asset-stat-card card-expiring">
            <div class="asset-stat-info">
                <div class="stat-lbl">Warranty Expiring</div>
                <div class="stat-val" id="statExpiringAssets" style="color: #ea580c;">18</div>
                <div class="stat-sub">Within next 30 days</div>
            </div>
            <div class="asset-stat-icon orange">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
        </div>

        <!-- Book Value -->
        <div class="asset-stat-card card-retired">
            <div class="asset-stat-info">
                <div class="stat-lbl">Est. Inventory Value</div>
                <div class="stat-val" id="statTotalValue">₹36,53,900</div>
                <div class="stat-sub">Historical PO total</div>
            </div>
            <div class="asset-stat-icon slate">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="4" x2="18" y2="4"></line>
                    <line x1="6" y1="9" x2="18" y2="9"></line>
                    <path d="M6 14h5a4 4 0 0 0 0-8H6"></path>
                    <path d="M11 14l6 7"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Status Tabs Navigation -->
    <div class="asset-status-tabs">
        <button type="button" class="status-tab-btn active" data-status="all">
            All Assets
            <span class="status-tab-badge" id="tabBadgeAll">842</span>
        </button>
        <button type="button" class="status-tab-btn" data-status="in-use">
            In Use
            <span class="status-tab-badge" id="tabBadgeInUse">614</span>
        </button>
        <button type="button" class="status-tab-btn" data-status="available">
            Available / In Stock
            <span class="status-tab-badge" id="tabBadgeAvail">156</span>
        </button>
        <button type="button" class="status-tab-btn" data-status="maintenance">
            Under Maintenance
            <span class="status-tab-badge" id="tabBadgeMaint">42</span>
        </button>
        <button type="button" class="status-tab-btn" data-status="reserved">
            Reserved
            <span class="status-tab-badge" id="tabBadgeRes">12</span>
        </button>
        <button type="button" class="status-tab-btn" data-status="retired">
            Retired / Disposed
            <span class="status-tab-badge" id="tabBadgeRet">18</span>
        </button>
    </div>

    <!-- Toolbar: Search, Filters & View Toggle -->
    <div class="asset-toolbar">
        <div class="toolbar-left">
            <!-- Search Input -->
            <div class="asset-search-wrapper">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="assetSearchInput" placeholder="Search by Asset Tag, Serial Number, Name, Model, Assignee...">
            </div>

            <!-- Category Filter -->
            <select class="asset-filter-select" id="categoryFilter">
                <option value="all">All Categories</option>
                <option value="Laptops">Laptops</option>
                <option value="Desktops">Desktops</option>
                <option value="Servers">Servers</option>
                <option value="Networking">Networking</option>
                <option value="Monitors">Monitors</option>
                <option value="Tablets & Mobile">Tablets & Mobile</option>
                <option value="Printers">Printers</option>
            </select>

            <!-- Department Filter -->
            <select class="asset-filter-select" id="deptFilter">
                <option value="all">All Departments</option>
                <option value="Software Engineering">Software Engineering</option>
                <option value="IT Infrastructure">IT Infrastructure</option>
                <option value="Design & Creative">Design & Creative</option>
                <option value="Finance">Finance</option>
                <option value="Operations">Operations</option>
                <option value="Human Resources">Human Resources</option>
                <option value="Executive Management">Executive Management</option>
            </select>

            <!-- Location Filter -->
            <select class="asset-filter-select" id="locationFilter">
                <option value="all">All Locations</option>
                <option value="HQ - New York">HQ - New York</option>
                <option value="Austin Hub">Austin Hub</option>
                <option value="London Office">London Office</option>
                <option value="Singapore DC">Singapore DC</option>
            </select>

            <!-- Condition Filter -->
            <select class="asset-filter-select" id="conditionFilter">
                <option value="all">All Conditions</option>
                <option value="Brand New">Brand New</option>
                <option value="Excellent">Excellent</option>
                <option value="Good">Good</option>
                <option value="Fair">Fair</option>
                <option value="Damaged">Damaged</option>
            </select>
        </div>

        <div class="toolbar-right">
            <!-- View Mode Switcher -->
            <div class="view-mode-toggle">
                <button type="button" class="view-btn active" id="viewTableBtn" title="Table View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                </button>
                <button type="button" class="view-btn" id="viewGridBtn" title="Grid / Card View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Bulk Actions Bar -->
    <div class="bulk-actions-bar" id="bulkActionsBar">
        <div class="bulk-info">
            <span class="bulk-selected-count" id="bulkSelectedCount">0</span>
            <span>assets selected</span>
        </div>
        <div class="bulk-btn-group">
            <button type="button" class="bulk-btn" onclick="bulkMarkStatus('Available')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Return to Stock
            </button>
            <button type="button" class="bulk-btn" onclick="bulkMarkStatus('Under Maintenance')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                Send to Repair
            </button>
            <button type="button" class="bulk-btn" onclick="bulkPrintLabels()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Print QR Labels
            </button>
            <button type="button" class="bulk-btn bulk-btn-danger" onclick="bulkMarkStatus('Retired')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Retire Selected
            </button>
            <button type="button" class="bulk-btn" onclick="clearBulkSelection()">
                &times; Deselect All
            </button>
        </div>
    </div>

    <!-- Table View Container -->
    <div class="asset-table-card" id="assetTableView">
        <div class="asset-table-responsive">
            <table class="asset-data-table">
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" class="custom-checkbox" id="selectAllAssets">
                        </th>
                        <th>Asset Name & Tag</th>
                        <th>Category</th>
                        <th>Serial Number (S/N)</th>
                        <th>Assigned To</th>
                        <th>Location & Dept</th>
                        <th>Status & Warranty</th>
                        <th style="text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="assetTableBody">
                    <!-- Populated dynamically via js/assets.js -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grid / Card View Container -->
    <div class="asset-grid-view" id="assetGridContainer" style="display: none;">
        <!-- Populated dynamically via js/assets.js -->
    </div>

    <!-- Pagination Footer -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 30px;">
        <div style="font-size: 13px; color: var(--text-secondary);" id="paginationInfo">
            Showing 1 to 10 of 842 assets
        </div>
        <div class="pagination-controls" id="paginationControls">
            <!-- Dynamically populated pagination buttons -->
        </div>
    </div>

</main>

<!-- =========================================================================
     SLIDE-OVER DRAWER (Asset Details & Specifications)
     ========================================================================= -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>

<aside class="asset-drawer" id="assetDrawer">
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerAssetTag" style="font-size: 12px; font-weight: 700;">AST2024001</span>
                    <span id="drawerAssetStatus"></span>
                    <span class="serial-badge" id="drawerAssetSerial" style="font-size: 12px; font-weight: 600;">SN: C02G40PZMD6T</span>
                </div>
                <h3 id="drawerAssetName" style="margin-top: 4px;">MacBook Pro 16" M3 Max</h3>
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
        <button type="button" class="drawer-tab" data-tab="custody">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
            Custody & History
        </button>
        <button type="button" class="drawer-tab" data-tab="tickets">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Tickets & Maintenance
        </button>
    </div>

    <!-- Drawer Tab Panes -->
    <div class="drawer-content">
        <!-- Pane 1: Overview & Specs -->
        <div class="drawer-tab-pane active" id="pane_overview">
            <!-- Hardware Specifications -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    Hardware Specifications
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Asset Tag Number</div>
                        <div class="value" id="specAssetTag" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">AST2024001</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Category</div>
                        <div class="value" id="specCategory">Laptops</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Manufacturer / Brand</div>
                        <div class="value" id="specBrand">Apple</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Model Number</div>
                        <div class="value" id="specModel">MacBookPro18,1</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Serial Number</div>
                        <div class="value" id="specSerial">C02G40PZMD6T</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Processor / CPU</div>
                        <div class="value" id="specProcessor">M3 Max</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Installed Memory (RAM)</div>
                        <div class="value" id="specRam">36 GB</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Internal Storage</div>
                        <div class="value" id="specStorage">1 TB SSD</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Operating System</div>
                        <div class="value" id="specOs">macOS Sonoma</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">MAC Address</div>
                        <div class="value" id="specMac">F0:18:98:4C:AA:32</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">IP Address</div>
                        <div class="value" id="specIp">10.20.104.42</div>
                    </div>
                </div>
            </div>

            <!-- Financial & Procurement Details -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Procurement & Warranty Lifecycle
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Supplier / Vendor</div>
                        <div class="value" id="specVendor">Apple Direct</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Purchase Order (PO #)</div>
                        <div class="value" id="specPo">PO-2024-8901</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Purchase Date</div>
                        <div class="value" id="specPurchaseDate">2024-01-08</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Original Purchase Cost</div>
                        <div class="value" id="specCost">₹2,89,900</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Warranty Status</div>
                        <div class="value" id="specWarranty">Active</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Condition Grade</div>
                        <div class="value" id="specCondition">Excellent</div>
                    </div>
                </div>
            </div>

            <!-- Location & Deployment -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Deployment Location
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Assigned Branch / Site</div>
                        <div class="value" id="specLocation">HQ - New York</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Department</div>
                        <div class="value" id="specDepartment">Software Engineering</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pane 2: Custody & History -->
        <div class="drawer-tab-pane" id="pane_custody">
            <div class="drawer-section">
                <div class="drawer-section-title">Current Custodian</div>
                <div id="drawerAssigneeWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Movement & Assignment Audit Trail</div>
                <div class="history-timeline" id="drawerTimelineWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <!-- Pane 3: Tickets & Maintenance -->
        <div class="drawer-tab-pane" id="pane_tickets">
            <div class="drawer-section">
                <div class="drawer-section-title">Linked IT Service Desk Incidents</div>
                <div id="drawerTicketsWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div class="drawer-footer">
        <button type="button" class="btn-secondary" onclick="openLabelModal(activeDrawerAssetId)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Print Asset Label
        </button>
        <button type="button" class="btn-primary" onclick="openEditModal(activeDrawerAssetId)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
            Edit Asset
        </button>
    </div>
</aside>

<!-- =========================================================================
     MODAL 1: Add / Edit Asset Modal
     ========================================================================= -->
<div class="modal-overlay" id="assetModal" style="display: none;">
    <div class="modal-box" style="max-width: 780px;">
        <div class="modal-header">
            <h3 id="assetModalTitle">Register New IT Hardware Asset</h3>
            <button type="button" class="modal-close-btn" id="closeAssetModalBtn">&times;</button>
        </div>

        <!-- Modal Tab Headers -->
        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-btn active" data-tab="general">General Info</button>
            <button type="button" class="modal-tab-btn" data-tab="specs">Specs & Network</button>
            <button type="button" class="modal-tab-btn" data-tab="components">Components</button>
            <button type="button" class="modal-tab-btn" data-tab="procurement">Procurement & Cost</button>
            <button type="button" class="modal-tab-btn" data-tab="placement">Location & Status</button>
        </div>

        <form id="assetForm">
            <input type="hidden" id="editAssetId">
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                
                <!-- Tab Pane 1: General Info -->
                <div class="modal-tab-pane active" id="modal_pane_general">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalCategory">Asset Category *</label>
                            <select id="modalCategory" required>
                                <option value="Laptops">Laptops</option>
                                <option value="Desktops">Desktops</option>
                                <option value="Servers">Servers</option>
                                <option value="Networking">Networking</option>
                                <option value="Monitors">Monitors</option>
                                <option value="Tablets & Mobile">Tablets & Mobile</option>
                                <option value="Printers">Printers</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalCondition">Physical Condition</label>
                            <select id="modalCondition">
                                <option value="Brand New">Brand New</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalAssetTag">Asset Tag Number <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Auto Generated)</span></label>
                            <input type="text" id="modalAssetTag" readonly style="background-color: #f1f5f9; cursor: not-allowed; font-family: monospace; font-weight: 600; color: var(--cyan-primary);">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalAssetName">Asset Display Name *</label>
                            <input type="text" id="modalAssetName" placeholder="MacBook Pro 16&quot; M3 Max" required>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalBrand">Brand / Manufacturer *</label>
                            <input type="text" id="modalBrand" placeholder="Apple, Dell, Lenovo" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalModel">Model Number / Specification</label>
                            <input type="text" id="modalModel" placeholder="XPS 15 9530 / A2991">
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="modalSerial">Serial Number (S/N) *</label>
                        <input type="text" id="modalSerial" placeholder="Manufacturer Serial Number (. C02G40PZMD6T)" required>
                    </div>
                </div>

                <!-- Tab Pane 2: Specs & Network -->
                <div class="modal-tab-pane" id="modal_pane_specs">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalProcessor">Processor / CPU</label>
                            <input type="text" id="modalProcessor" placeholder="Intel Core i7-13700H / Apple M3">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalRam">Installed RAM</label>
                            <input type="text" id="modalRam" placeholder="32 GB DDR5">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalStorage">Storage Capacity & Type</label>
                            <input type="text" id="modalStorage" placeholder="1 TB NVMe SSD">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalOs">Installed OS / Firmware</label>
                            <input type="text" id="modalOs" placeholder="Windows 11 Enterprise / macOS Sonoma">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalMac">MAC Address</label>
                            <input type="text" id="modalMac" placeholder="00:1A:2B:3C:4D:5E">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalIp">Static IP / IP Reservation</label>
                            <input type="text" id="modalIp" placeholder="10.20.104.42 (or DHCP)">
                        </div>
                    </div>
                </div>

                <!-- Tab Pane: Components -->
                <div class="modal-tab-pane" id="modal_pane_components">
                    <div id="componentRowsContainer" class="component-row-wrap">
                        <!-- Initial Component Row -->
                        <div class="component-input-row">
                            <div class="modal-form-group" style="flex: 1;">
                                <label>Asset Name</label>
                                <input type="text" class="component-name" placeholder="e.g. 16GB DDR5 5600MHz / 2TB NVMe SSD">
                            </div>
                            <div class="modal-form-group" style="flex: 1;">
                                <label>Serial Number</label>
                                <input type="text" class="component-serial" placeholder="e.g. SN-882109">
                            </div>
                            <div class="modal-form-group" style="flex: 0 0 38px;">
                                <label>&nbsp;</label>
                                <button type="button" class="btn-add-comp-row" onclick="addComponentRow()" title="Add Row">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 3: Procurement & Cost -->
                <div class="modal-tab-pane" id="modal_pane_procurement">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalVendor">Supplier / Vendor</label>
                            <input type="text" id="modalVendor" placeholder="Dell Direct, CDW, Apple Business">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalPoNumber">Purchase Order (PO #)</label>
                            <input type="text" id="modalPoNumber" placeholder="PO-2024-9104">
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="modal-form-group">
                            <label for="modalPurchaseDate">Purchase Date</label>
                            <input type="date" id="modalPurchaseDate">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalCost">Purchase Price (₹ INR)</label>
                            <input type="number" step="0.01" id="modalCost" placeholder="0.00">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalWarrantyExpiry">Warranty Expiry Date</label>
                            <input type="date" id="modalWarrantyExpiry">
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 4: Location & Status -->
                <div class="modal-tab-pane" id="modal_pane_placement">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalStatus">Initial Asset Status *</label>
                            <select id="modalStatus" required>
                                <option value="Available">Available (In Stock)</option>
                                <option value="In Use">In Use (Assigned)</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Reserved">Reserved</option>
                                <option value="Retired">Retired / Scrapped</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalLocation">Branch / Location *</label>
                            <select id="modalLocation" required>
                                <option value="HQ - New York">HQ - New York</option>
                                <option value="Austin Hub">Austin Hub</option>
                                <option value="London Office">London Office</option>
                                <option value="Singapore DC">Singapore DC</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="modalDepartment">Assigned Department</label>
                        <select id="modalDepartment">
                            <option value="IT Infrastructure">IT Infrastructure</option>
                            <option value="Software Engineering">Software Engineering</option>
                            <option value="Design & Creative">Design & Creative</option>
                            <option value="Finance">Finance</option>
                            <option value="Operations">Operations</option>
                            <option value="Human Resources">Human Resources</option>
                            <option value="Executive Management">Executive Management</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAssetModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveAssetBtn">Save Asset Record</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: Print Thermal Barcode & QR Sticker Modal
     ========================================================================= -->
<div class="modal-overlay" id="labelModal" style="display: none;">
    <div class="modal-box" style="max-width: 460px;">
        <div class="modal-header">
            <h3>Print Physical Asset Label</h3>
            <button type="button" class="modal-close-btn" onclick="closeLabelModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                High-density thermal sticker layout optimized for standard Zebra / Dymo asset tag roll printers (50mm x 25mm).
            </p>

            <div class="label-preview-box">
                <div class="thermal-asset-sticker" id="printableAssetLabel">
                    <div class="sticker-header">
                        <div class="sticker-company">VIROS IT ASSETS</div>
                        <div class="sticker-property">PROPERTY OF VIROS CORP</div>
                    </div>
                    <div class="sticker-body">
                        <div class="sticker-qr">
                            <!-- Dynamic QR Code SVG -->
                            <svg viewBox="0 0 100 100" fill="#000">
                                <rect x="0" y="0" width="30" height="30"></rect>
                                <rect x="5" y="5" width="20" height="20" fill="#fff"></rect>
                                <rect x="10" y="10" width="10" height="10"></rect>
                                <rect x="70" y="0" width="30" height="30"></rect>
                                <rect x="75" y="5" width="20" height="20" fill="#fff"></rect>
                                <rect x="80" y="10" width="10" height="10"></rect>
                                <rect x="0" y="70" width="30" height="30"></rect>
                                <rect x="5" y="75" width="20" height="20" fill="#fff"></rect>
                                <rect x="10" y="80" width="10" height="10"></rect>
                                <rect x="40" y="10" width="8" height="8"></rect>
                                <rect x="52" y="10" width="8" height="8"></rect>
                                <rect x="40" y="30" width="15" height="15"></rect>
                                <rect x="70" y="45" width="12" height="12"></rect>
                                <rect x="45" y="70" width="14" height="14"></rect>
                                <rect x="80" y="75" width="12" height="12"></rect>
                            </svg>
                        </div>
                        <div class="sticker-info">
                            <div class="sticker-tag-number" id="lblStickerTag" style="font-family: monospace; font-size: 11.5px; font-weight: 800; color: #0284c7; margin-bottom: 2px;">TAG: AST2024001</div>
                            <div class="sticker-name" id="lblStickerName" style="font-size: 13px; font-weight: 800;">MacBook Pro 16" M3 Max</div>
                            <div class="sticker-serial" id="lblStickerSerial" style="font-size: 11px; font-weight: bold; margin-top: 3px;">SN: C02G40PZMD6T</div>
                            <div class="sticker-tag" id="lblStickerCategory" style="font-size: 11px; color: #555; margin-top: 2px;">Category: Laptops</div>
                        </div>
                    </div>
                    <div class="sticker-barcode-wrap">
                        <!-- Barcode lines SVG -->
                        <svg class="barcode-svg" viewBox="0 0 200 40">
                            <rect x="5" y="0" width="3" height="40" fill="#000"></rect>
                            <rect x="12" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="18" y="0" width="4" height="40" fill="#000"></rect>
                            <rect x="26" y="0" width="1" height="40" fill="#000"></rect>
                            <rect x="32" y="0" width="3" height="40" fill="#000"></rect>
                            <rect x="40" y="0" width="5" height="40" fill="#000"></rect>
                            <rect x="50" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="56" y="0" width="4" height="40" fill="#000"></rect>
                            <rect x="65" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="72" y="0" width="3" height="40" fill="#000"></rect>
                            <rect x="80" y="0" width="1" height="40" fill="#000"></rect>
                            <rect x="86" y="0" width="4" height="40" fill="#000"></rect>
                            <rect x="95" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="102" y="0" width="5" height="40" fill="#000"></rect>
                            <rect x="112" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="118" y="0" width="3" height="40" fill="#000"></rect>
                            <rect x="126" y="0" width="1" height="40" fill="#000"></rect>
                            <rect x="132" y="0" width="4" height="40" fill="#000"></rect>
                            <rect x="142" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="148" y="0" width="5" height="40" fill="#000"></rect>
                            <rect x="158" y="0" width="3" height="40" fill="#000"></rect>
                            <rect x="166" y="0" width="1" height="40" fill="#000"></rect>
                            <rect x="172" y="0" width="4" height="40" fill="#000"></rect>
                            <rect x="180" y="0" width="2" height="40" fill="#000"></rect>
                            <rect x="188" y="0" width="4" height="40" fill="#000"></rect>
                        </svg>
                        <div class="sticker-human-readable" id="lblBarcodeNumber">*C02G40PZMD6T*</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeLabelModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="printSticker()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Sticker
            </button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: QR & Barcode Scanner Simulation Modal
     ========================================================================= -->
<div class="modal-overlay" id="scannerModal" style="display: none;">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Asset Barcode & QR Scanner</h3>
            <button type="button" class="modal-close-btn" onclick="closeScannerModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="scanner-viewport">
                <div class="scan-reticle">
                    <div class="reticle-corner corner-tl"></div>
                    <div class="reticle-corner corner-tr"></div>
                    <div class="reticle-corner corner-bl"></div>
                    <div class="reticle-corner corner-br"></div>
                    <div class="scan-laser"></div>
                </div>
                <div class="scanner-hint">Align QR Code or Barcode within frame</div>
            </div>

            <div class="modal-form-group">
                <label for="manualScanInput">Scan with USB Gun or Type Tag / Serial Number</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="manualScanInput" placeholder=". AST2024001 or C02G40PZMD6T">
                    <button type="button" class="btn-primary" onclick="handleManualScan()">Lookup</button>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
                    Sample: <code>AST2024001</code> or <code>C02G40PZMD6T</code>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeScannerModal()">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: Reassign / Check-out Asset Modal
     ========================================================================= -->
<div class="modal-overlay" id="reassignModal" style="display: none;">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Transfer & Assign Asset</h3>
            <button type="button" class="modal-close-btn" onclick="closeReassignModal()">&times;</button>
        </div>
        <form id="reassignForm">
            <input type="hidden" id="reassignAssetId">
            <div class="modal-body">
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--text-muted);">Target Asset</div>
                    <div style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-top: 2px;" id="reassignAssetName">MacBook Pro 16" M3 Max</div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="reassignAssetSerial">SN: C02G40PZMD6T</div>
                </div>

                <div class="modal-form-group">
                    <label for="reassignActionType">Action Type *</label>
                    <select id="reassignActionType">
                        <option value="assign_employee">Issue / Assign to Employee</option>
                        <option value="return_to_stock">Return to Ready Stock (Unassign)</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="reassignEmpName">Recipient Employee Name *</label>
                    <input type="text" id="reassignEmpName" placeholder=". Alex Morgan">
                </div>

                <div class="modal-form-group">
                    <label for="reassignDept">Department *</label>
                    <select id="reassignDept">
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="IT Infrastructure">IT Infrastructure</option>
                        <option value="Design & Creative">Design & Creative</option>
                        <option value="Finance">Finance</option>
                        <option value="Operations">Operations</option>
                        <option value="Human Resources">Human Resources</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="reassignEmail">Work Email</label>
                    <input type="email" id="reassignEmail" placeholder=". alex.morgan@viros.com">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeReassignModal()">Cancel</button>
                <button type="submit" class="btn-primary">Confirm Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 5: Import CSV Modal
     ========================================================================= -->
<div class="modal-overlay" id="importModal" style="display: none;">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Import Assets from CSV</h3>
            <button type="button" class="modal-close-btn" onclick="closeImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="file-dropzone" style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 32px 20px; text-align: center; background: #f8fafc; cursor: pointer;" onclick="document.getElementById('csvAssetFile').click()">
                <input type="file" id="csvAssetFile" accept=".csv" style="display: none;" onchange="showToast('CSV uploaded and 8 records staged for review!', 'success'); closeImportModal();">
                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary); margin-bottom: 10px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <line x1="9" y1="15" x2="12" y2="12"></line>
                    <line x1="15" y1="15" x2="12" y2="12"></line>
                </svg>
                <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Click or drag & drop asset CSV here</div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Supports Asset Tag, Serial, Category, Brand, Specs & Cost</div>
            </div>
            <div style="margin-top: 14px; text-align: right;">
                <a href="#" style="font-size: 12px; color: var(--cyan-primary); text-decoration: none;" onclick="exportAssetsCsv(); return false;">Download Sample Template CSV</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeImportModal()">Cancel</button>
        </div>
    </div>
</div>

<?php
// Include Global Footer & Modals
include 'includes/footer.php';
?>
