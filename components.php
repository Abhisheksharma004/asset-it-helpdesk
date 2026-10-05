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

// Include database to fetch dynamic component categories
require_once __DIR__ . '/config/db.php';

$dynamicCategories = [];
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
                <div class="stat-val" id="statTotalItems">12</div>
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
                <div class="stat-val" id="statAvailable" style="color: var(--success);">5</div>
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
                <div class="stat-val" id="statInstalled" style="color: var(--cyan-primary);">6</div>
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
                <div class="stat-val" id="statRepair" style="color: #ea580c;">1</div>
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
                    <!-- Populated dynamically via JS -->
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ==================== ADD / EDIT COMPONENT MODAL (SINGLE ITEM) ==================== -->
<div class="modal-overlay" id="componentModal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Component / Part</h3>
            <button class="modal-close-btn" id="closeModalBtn">&times;</button>
        </div>
        <form id="componentForm">
            <input type="hidden" id="editCompId" value="">
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
                        <label for="compSku">Part / SKU Tag <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Auto Generated)</span></label>
                        <input type="text" id="compSku" readonly style="background-color: #f8fafc; cursor: not-allowed; font-family: monospace; font-weight: 600; color: var(--cyan-primary);">
                    </div>
                </div>

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
                <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveCompBtn">Save Component</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== INSTALL / ALLOCATE COMPONENT TO ASSET MODAL ==================== -->
<div class="modal-overlay" id="installModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Install Component into Asset</h3>
            <button class="modal-close-btn" id="closeInstallModalBtn">&times;</button>
        </div>
        <form id="installForm">
            <input type="hidden" id="installCompId" value="">
            <div class="modal-body">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                    <div style="font-weight: 700; font-size: 14px; color: var(--navy-primary);" id="installCompName">Component Name</div>
                    <div style="display: flex; gap: 12px; margin-top: 5px; font-size: 12px; color: var(--text-muted);">
                        <div>SKU: <strong id="installCompSku" style="color: var(--text-primary);">-</strong></div>
                        <div>•</div>
                        <div>Serial: <strong id="installCompSerial" style="color: var(--text-primary); font-family: monospace;">-</strong></div>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="installTargetAsset">Target Asset / Host Machine *</label>
                    <select id="installTargetAsset" required>
                        <option value="">Select Target Asset</option>
                        <option value="AST-2026-001 (Dell Latitude 5420)">AST-2026-001 — Dell Latitude 5420 (Priya Sharma)</option>
                        <option value="AST-2026-002 (ThinkPad T14 Gen 2)">AST-2026-002 — Lenovo ThinkPad T14 Gen 2 (Rahul Mehta)</option>
                        <option value="AST-2026-003 (MacBook Pro 16 M1)">AST-2026-003 — Apple MacBook Pro 16 M1 Pro (Arjun Verma)</option>
                        <option value="AST-2026-004 (HP EliteDesk 800 G6)">AST-2026-004 — HP EliteDesk 800 G6 Mini (Finance Dept)</option>
                        <option value="AST-2026-005 (Dell PowerEdge R740)">AST-2026-005 — Dell PowerEdge R740 Server (DC Rack 1)</option>
                        <option value="AST-2026-006 (Custom AI Workstation)">AST-2026-006 — Custom Deep Learning Rig (AI Lab)</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="installDate">Installation Date *</label>
                    <input type="date" id="installDate" required>
                </div>

                <div class="modal-form-group">
                    <label for="installedBy">Technician / Installed By *</label>
                    <input type="text" id="installedBy" placeholder="e.g. Vikram Rathore (IT Support)" required>
                </div>

                <div class="modal-form-group">
                    <label for="installNotes">Installation Notes / Purpose</label>
                    <input type="text" id="installNotes" placeholder="e.g. RAM upgrade to 32GB for build tasks">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelInstallBtn">Cancel</button>
                <button type="submit" class="btn-primary">Confirm Installation</button>
            </div>
        </form>
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

<?php
// Include Modular Layout Footer
include 'includes/footer.php';
?>
