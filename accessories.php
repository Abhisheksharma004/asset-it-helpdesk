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
$extra_css = ['css/categories.css', 'css/accessories.css'];
$extra_js  = ['js/accessories.js'];

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
                <div class="stat-val" id="statTotalItems">18</div>
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
                <div class="stat-val" id="statInStock" style="color: var(--success);">338</div>
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
                <div class="stat-val" id="statDeployed" style="color: var(--cyan-primary);">1,142</div>
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
                <div class="stat-val" id="statLowStock" style="color: #ea580c;">3</div>
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

            <select class="filter-select" id="categoryFilter">
                <option value="all">All Categories</option>
                <option value="Keyboards & Mice">Keyboards & Mice</option>
                <option value="Docks & Hubs">Docks & Hubs</option>
                <option value="Headsets & Audio">Headsets & Audio</option>
                <option value="Webcams & Video">Webcams & Video</option>
                <option value="Chargers & Power Adapters">Chargers & Power Adapters</option>
                <option value="Cables & Display Adapters">Cables & Display Adapters</option>
                <option value="Security Tokens & Smart Keys">Security Tokens & Smart Keys</option>
                <option value="Laptop Stands & Mounts">Laptop Stands & Mounts</option>
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

    <!-- Simple Data Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="custom-table" id="accessoriesTable">
                <thead>
                    <tr>
                        <th class="sku-cell" style="width: 140px; white-space: nowrap;">SKU</th>
                        <th style="min-width: 230px;">Accessory Name</th>
                        <th style="width: 160px;">Category</th>
                        <th style="width: 160px;">Brand & Model</th>
                        <th style="width: 110px; text-align: center;">In Stock</th>
                        <th style="width: 110px; text-align: center;">Deployed</th>
                        <th style="width: 130px;">Status</th>
                        <th style="width: 130px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="accessoriesTbody">
                    <!-- Populated dynamically via JS -->
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ==================== ADD / EDIT ACCESSORY MODAL ==================== -->
<div class="modal-overlay" id="accessoryModal">
    <div class="modal-box" style="max-width: 540px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Accessory</h3>
            <button class="modal-close-btn" id="closeModalBtn">&times;</button>
        </div>
        <form id="accessoryForm">
            <input type="hidden" id="editAccId" value="">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="accName">Accessory Name *</label>
                    <input type="text" id="accName" placeholder="e.g. Logitech MX Master 3S Wireless Mouse" required>
                </div>

                <div class="modal-form-group">
                    <label for="accCategory">Category *</label>
                    <select id="accCategory" required>
                        <option value="">Select Category</option>
                        <option value="Keyboards & Mice">Keyboards & Mice</option>
                        <option value="Docks & Hubs">Docks & Hubs</option>
                        <option value="Headsets & Audio">Headsets & Audio</option>
                        <option value="Webcams & Video">Webcams & Video</option>
                        <option value="Chargers & Power Adapters">Chargers & Power Adapters</option>
                        <option value="Cables & Display Adapters">Cables & Display Adapters</option>
                        <option value="Security Tokens & Smart Keys">Security Tokens & Smart Keys</option>
                        <option value="Laptop Stands & Mounts">Laptop Stands & Mounts</option>
                    </select>
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
                <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveModalBtn">Save Accessory</button>
            </div>
        </form>
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

<?php
// Include Modular Layout Footer
include 'includes/footer.php';
?>
