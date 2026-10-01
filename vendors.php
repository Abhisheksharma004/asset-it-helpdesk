<?php
// Asset Management & IT Service Desk Portal - Vendor & Supplier Master Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Vendor & Supplier Master - VIROS IT Portal";
$active_page = "master_vendor";
$extra_css = ['css/categories.css'];
$extra_js  = ['js/vendors.js'];

// Include database
require_once __DIR__ . '/config/db.php';

// Initial server-side query for immediate fast rendering
$initialVendors = [];
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];

$vendorQuery = "SELECT id, vendor_name, contact_person, phone, email, address, gstin, status, 
                       CONVERT(VARCHAR(10), created_at, 105) AS created_date
                FROM vendors 
                ORDER BY id DESC";
$vendorStmt = sqlsrv_query($conn, $vendorQuery);
if ($vendorStmt !== false) {
    while ($row = sqlsrv_fetch_array($vendorStmt, SQLSRV_FETCH_ASSOC)) {
        $initialVendors[] = $row;
        if (($row['status'] ?? '') === 'Active') {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }
    $stats['total'] = count($initialVendors);
    sqlsrv_free_stmt($vendorStmt);
}

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Vendor & Supplier Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Master</span>
                <span>/</span>
                <span>Vendor & Supplier</span>
            </div>
            <h1>Vendor & Supplier Master</h1>
        </div>

        <div class="header-actions">
            <!-- Refresh Table -->
            <button type="button" class="btn-secondary" id="refreshTableBtn" title="Reload list">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                <span>Refresh</span>
            </button>

            <!-- Export CSV -->
            <button type="button" class="btn-secondary" id="exportCsvBtn" title="Export vendors list to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Export CSV</span>
            </button>

            <!-- Import CSV -->
            <button type="button" class="btn-secondary" id="openImportModalBtn" title="Import vendors from CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Import CSV</span>
            </button>

            <!-- Add Vendor Modal Trigger -->
            <button type="button" class="btn-primary" id="openAddModalBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add Vendor</span>
            </button>
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="cat-stats-grid">
        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Total Vendors</div>
                <div class="stat-val" id="statTotal"><?php echo $stats['total']; ?></div>
            </div>
            <div class="cat-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Active Vendors</div>
                <div class="stat-val" id="statActive"><?php echo $stats['active']; ?></div>
            </div>
            <div class="cat-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Inactive Vendors</div>
                <div class="stat-val" id="statInactive"><?php echo $stats['inactive']; ?></div>
            </div>
            <div class="cat-stat-icon navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="toolbar-card">
        <div class="toolbar-filters">
            <!-- Search Input -->
            <div class="search-box-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="searchVendor" placeholder="Search vendor name, GSTIN, contact person, email...">
            </div>

            <!-- Status Filter -->
            <select class="filter-select" id="filterStatus">
                <option value="All">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>

            <button type="button" class="btn-filter-reset" id="resetFiltersBtn">Reset Filters</button>
        </div>
    </div>

    <!-- Vendors Data Table Card -->
    <div class="content-card">
        <div class="card-header">
            <h2>All Vendors & Suppliers</h2>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="vendorsTable">
                <thead>
                    <tr>
                        <th style="min-width: 240px;">Vendor / Supplier</th>
                        <th style="min-width: 140px;">Contact Person</th>
                        <th style="min-width: 170px;">Phone & Email</th>
                        <th style="min-width: 170px;">Address / Notes</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 120px;">Created Date</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="vendorsTbody">
                    <?php if (empty($initialVendors)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="table-empty-state">
                                    <div class="empty-icon">
                                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                        </svg>
                                    </div>
                                    <div class="empty-title">No vendors found</div>
                                    <div class="empty-desc">Get started by onboarding your first vendor or supplier partner.</div>
                                    <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add New Vendor</button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($initialVendors as $v): 
                            $statusClass = ($v['status'] === 'Active') ? 'status-active' : 'status-inactive';
                        ?>
                            <tr data-id="<?php echo $v['id']; ?>">
                                <td>
                                    <div class="category-main-text"><?php echo htmlspecialchars($v['vendor_name']); ?></div>
                                    <?php if (!empty($v['gstin'])): ?>
                                        <div style="color: var(--cyan-primary); font-size: 12px; margin-top: 2px;">
                                            GSTIN: <?php echo htmlspecialchars($v['gstin']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($v['contact_person']) ? htmlspecialchars($v['contact_person']) : '<span style="color:#94a3b8; font-style:italic;">—</span>'; ?>
                                </td>
                                <td style="font-size: 13px;">
                                    <?php if (!empty($v['phone'])): ?>
                                        <div style="color: var(--text-primary); font-weight: 500; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($v['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($v['email'])): ?>
                                        <div style="color: var(--cyan-primary); font-size: 12px;">
                                            <?php echo htmlspecialchars($v['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (empty($v['phone']) && empty($v['email'])): ?>
                                        <span style="color:#94a3b8; font-style:italic;">No contact info</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 13px;">
                                    <?php echo !empty($v['address']) ? htmlspecialchars($v['address']) : '<span style="color:#94a3b8; font-style:italic;">—</span>'; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo $statusClass; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo htmlspecialchars($v['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 13px; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($v['created_date'] ?? '—'); ?>
                                </td>
                                <td>
                                    <div class="table-actions" style="justify-content: center;">
                                        <!-- Toggle Status Button -->
                                        <button type="button" class="action-btn toggle-btn" title="Toggle Status" onclick="toggleVendorStatus(<?php echo $v['id']; ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="1 4 1 10 7 10"></polyline>
                                                <polyline points="23 20 23 14 17 14"></polyline>
                                                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                                            </svg>
                                        </button>

                                        <!-- Edit Button -->
                                        <button type="button" class="action-btn" title="Edit Vendor" onclick="openEditVendorModal(<?php echo $v['id']; ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" class="action-btn delete-btn" title="Delete Vendor" onclick="openDeleteVendorModal(<?php echo $v['id']; ?>, '<?php echo addslashes($v['vendor_name']); ?>')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
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

</main>

<!-- ==================== ADD VENDOR MODAL ==================== -->
<div class="modal-overlay" id="addVendorModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Vendor / Supplier</h3>
            <button class="modal-close-btn" id="closeAddModalBtn">&times;</button>
        </div>
        <form id="addVendorForm">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="addName">Vendor / Supplier Name *</label>
                    <input type="text" id="addName" placeholder="e.g. Dell Technologies India" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addGstin">GSTIN Number</label>
                        <input type="text" id="addGstin" placeholder="e.g. 29AABCD1234F1Z5" maxlength="15" style="text-transform: uppercase;">
                    </div>

                    <div class="modal-form-group">
                        <label for="addPerson">Contact Person</label>
                        <input type="text" id="addPerson" placeholder="e.g. Rajesh Gupta">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addPhone">Phone / Mobile</label>
                        <input type="text" id="addPhone" placeholder="e.g. +91 98201 12345">
                    </div>

                    <div class="modal-form-group">
                        <label for="addEmail">Email Address</label>
                        <input type="email" id="addEmail" placeholder="e.g. sales@vendor.com">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="addAddress">Office Address / Notes</label>
                    <textarea id="addAddress" rows="2" placeholder="Building, City, State, Pin Code..."></textarea>
                </div>

                <!-- Status Field placed at the bottom -->
                <div class="modal-form-group">
                    <label for="addStatus">Initial Status *</label>
                    <select id="addStatus" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAddModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save Vendor</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT VENDOR MODAL ==================== -->
<div class="modal-overlay" id="editVendorModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Vendor / Supplier</h3>
            <button class="modal-close-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editVendorForm">
            <input type="hidden" id="editId">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="editName">Vendor / Supplier Name *</label>
                    <input type="text" id="editName" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editGstin">GSTIN Number</label>
                        <input type="text" id="editGstin" placeholder="e.g. 29AABCD1234F1Z5" maxlength="15" style="text-transform: uppercase;">
                    </div>

                    <div class="modal-form-group">
                        <label for="editPerson">Contact Person</label>
                        <input type="text" id="editPerson">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editPhone">Phone / Mobile</label>
                        <input type="text" id="editPhone">
                    </div>

                    <div class="modal-form-group">
                        <label for="editEmail">Email Address</label>
                        <input type="email" id="editEmail">
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="editAddress">Office Address / Notes</label>
                    <textarea id="editAddress" rows="2"></textarea>
                </div>

                <!-- Status Field placed at the bottom -->
                <div class="modal-form-group">
                    <label for="editStatus">Status *</label>
                    <select id="editStatus" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelEditModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Update Vendor</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== DELETE CONFIRMATION MODAL ==================== -->
<div class="modal-overlay" id="deleteVendorModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 style="font-size: 17px; color: var(--navy-primary); margin-bottom: 8px;">Delete Vendor?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px;">
                Are you sure you want to delete <strong id="deleteVendorName" style="color: var(--text-primary);"></strong>? This action cannot be undone.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelDeleteModalBtn">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">Yes, Delete Vendor</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== IMPORT VENDORS MODAL ==================== -->
<div class="modal-overlay" id="importVendorModal">
    <div class="modal-box" style="max-width: 540px;">
        <div class="modal-header">
            <h3>Import Vendors & Suppliers</h3>
            <button class="modal-close-btn" id="closeImportModalBtn">&times;</button>
        </div>
        <form id="importVendorForm">
            <div class="modal-body">
                <!-- Template Notice -->
                <div class="import-template-box">
                    <div class="template-text">
                        <strong>CSV Format:</strong> Ensure your file has columns for Vendor Name, GSTIN, Contact Person, Phone, Email, Address, and Status.
                    </div>
                    <button type="button" class="btn-template-download" id="downloadSampleTemplateBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Download Sample Template
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
                            <div class="file-meta" id="previewFileMeta">0 KB • 0 vendors detected</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-file" id="removeFileBtn" title="Remove file">&times;</button>
                </div>

                <!-- Duplicate Option -->
                <div class="modal-form-group">
                    <label for="duplicateHandling">Duplicate Vendors Handling</label>
                    <select id="duplicateHandling">
                        <option value="skip">Skip duplicates (keep existing)</option>
                        <option value="update">Update existing vendors</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelImportModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="startImportBtn" disabled>Import Vendors</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
