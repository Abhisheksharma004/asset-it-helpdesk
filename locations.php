<?php
// Asset Management & IT Service Desk Portal - Branch & Location Master Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Branch & Location Master - VIROS IT Portal";
$active_page = "master_location";
$extra_css = ['css/categories.css'];
$extra_js  = ['js/locations.js'];

// Include database
require_once __DIR__ . '/config/db.php';

// Initial server-side query for immediate fast rendering
$initialLocations = [];
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];

$locQuery = "SELECT id, location_name, description, status, 
                    CONVERT(VARCHAR(10), created_at, 105) AS created_date
             FROM locations 
             ORDER BY id DESC";
$locStmt = sqlsrv_query($conn, $locQuery);
if ($locStmt !== false) {
    while ($row = sqlsrv_fetch_array($locStmt, SQLSRV_FETCH_ASSOC)) {
        $initialLocations[] = $row;
        if (($row['status'] ?? '') === 'Active') {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }
    $stats['total'] = count($initialLocations);
    sqlsrv_free_stmt($locStmt);
}

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Branch & Location Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Master</span>
                <span>/</span>
                <span>Branch & Location</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                Branch & Location Master
            </h1>
            <p>Configure corporate offices, regional delivery centers, campuses, and warehouse facilities.</p>
        </div>

        <div class="page-header-actions">
            <button class="btn-secondary" id="refreshBtn" title="Reload Locations">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                Refresh
            </button>
            <button class="btn-secondary" id="exportCsvBtn" title="Export locations to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
            <button class="btn-secondary" id="openImportModalBtn" title="Import locations from CSV file">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Import CSV
            </button>
            <button class="btn-primary" id="openAddModalBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Location
            </button>
        </div>
    </div>

    <!-- Location KPI Stat Cards Grid -->
    <div class="cat-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Total Locations</div>
                <div class="stat-val" id="statTotal"><?php echo $stats['total']; ?></div>
            </div>
            <div class="cat-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Active Locations</div>
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
                <div class="stat-lbl">Inactive Locations</div>
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
                <input type="text" id="searchLocation" placeholder="Search branch or address...">
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

    <!-- Locations Data Table Card -->
    <div class="content-card">
        <div class="card-header">
            <h2>All Branches & Locations</h2>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="locationsTable">
                <thead>
                    <tr>
                        <th style="min-width: 260px;">Branch / Location Name</th>
                        <th>Address / Facility Notes</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 130px;">Created Date</th>
                        <th style="width: 120px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="locationsTbody">
                    <?php if (empty($initialLocations)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="table-empty-state">
                                    <div class="empty-icon">
                                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                    </div>
                                    <div class="empty-title">No locations found</div>
                                    <div class="empty-desc">Get started by creating your first branch or office location.</div>
                                    <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add New Location</button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($initialLocations as $loc): 
                            $statusClass = ($loc['status'] === 'Active') ? 'status-active' : 'status-inactive';
                        ?>
                            <tr data-id="<?php echo $loc['id']; ?>">
                                <td>
                                    <span class="category-main-text"><?php echo htmlspecialchars($loc['location_name']); ?></span>
                                </td>
                                <td style="color: var(--text-secondary); font-size: 13px;">
                                    <?php echo !empty($loc['description']) ? htmlspecialchars($loc['description']) : '<span style="color:#94a3b8; font-style:italic;">No address/details provided</span>'; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo $statusClass; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo htmlspecialchars($loc['status']); ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-muted); font-size: 12px; white-space: nowrap;">
                                    <?php echo htmlspecialchars($loc['created_date'] ?? '—'); ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="action-btn edit-loc-btn" title="Edit Location" data-id="<?php echo $loc['id']; ?>">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <button class="action-btn toggle-btn toggle-loc-btn" title="<?php echo ($loc['status'] === 'Active') ? 'Deactivate' : 'Activate'; ?>" data-id="<?php echo $loc['id']; ?>">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                                <line x1="12" y1="2" x2="12" y2="12"></line>
                                            </svg>
                                        </button>
                                        <button class="action-btn delete-btn delete-loc-btn" title="Delete Location" data-id="<?php echo $loc['id']; ?>" data-name="<?php echo htmlspecialchars($loc['location_name']); ?>">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

<!-- ==================== ADD LOCATION MODAL ==================== -->
<div class="modal-overlay" id="addLocationModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Branch / Location</h3>
            <button class="modal-close-btn" id="closeAddModalBtn">&times;</button>
        </div>
        <form id="addLocationForm">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="addName">Branch / Location Name *</label>
                    <input type="text" id="addName" placeholder="e.g. Tech Hub - Bangalore" required>
                </div>

                <div class="modal-form-group">
                    <label for="addDesc">Address / Facility Notes</label>
                    <textarea id="addDesc" rows="3" placeholder="Enter physical premises address, floor, building details..."></textarea>
                </div>

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
                <button type="submit" class="btn-primary">Save Location</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT LOCATION MODAL ==================== -->
<div class="modal-overlay" id="editLocationModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Branch / Location</h3>
            <button class="modal-close-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editLocationForm">
            <input type="hidden" id="editId">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="editName">Branch / Location Name *</label>
                    <input type="text" id="editName" required>
                </div>

                <div class="modal-form-group">
                    <label for="editDesc">Address / Facility Notes</label>
                    <textarea id="editDesc" rows="3"></textarea>
                </div>

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
                <button type="submit" class="btn-primary">Update Location</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== DELETE CONFIRMATION MODAL ==================== -->
<div class="modal-overlay" id="deleteLocationModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 style="font-size: 17px; color: var(--navy-primary); margin-bottom: 8px;">Delete Location?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px;">
                Are you sure you want to delete <strong id="deleteLocationName" style="color: var(--text-primary);"></strong>? This action cannot be undone.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelDeleteModalBtn">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">Yes, Delete Location</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== IMPORT LOCATIONS MODAL ==================== -->
<div class="modal-overlay" id="importLocationModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Import Locations & Branches</h3>
            <button class="modal-close-btn" id="closeImportModalBtn">&times;</button>
        </div>
        <form id="importLocationForm">
            <div class="modal-body">
                <!-- Template Notice -->
                <div class="import-template-box">
                    <div class="template-text">
                        <strong>CSV Format:</strong> Ensure your file has columns for Location Name, Description, and Status.
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
                            <div class="file-meta" id="previewFileMeta">0 KB • 0 locations detected</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-file" id="removeFileBtn" title="Remove file">&times;</button>
                </div>

                <!-- Duplicate Option -->
                <div class="modal-form-group">
                    <label for="duplicateHandling">Duplicate Locations Handling</label>
                    <select id="duplicateHandling">
                        <option value="skip">Skip duplicates (keep existing)</option>
                        <option value="update">Update existing locations</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelImportModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="startImportBtn" disabled>Import Locations</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include Modular Footer & Modals
include 'includes/footer.php';
?>
