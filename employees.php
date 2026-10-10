<?php
// Asset Management & IT Service Desk Portal - Employee Master Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Employee Master - VIROS IT Portal";
$active_page = "master_employee";
$extra_css = ['css/categories.css', 'css/employees.css'];
$extra_js  = ['js/employees.js'];

// Include database
require_once __DIR__ . '/config/db.php';

// Initial server-side query for immediate fast rendering
$initialEmployees = [];
$stats = [
    'total' => 0, 
    'active' => 0, 
    'inactive' => 0,
    'departments_count' => 0
];
$departmentsList = [];
$locationsList = [];

// Fetch initial employees list
$empQuery = "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.phone, 
                    e.department_id, e.location_id, e.designation,
                    CONVERT(VARCHAR(10), e.joining_date, 120) AS joining_date_raw,
                    CONVERT(VARCHAR(10), e.joining_date, 105) AS joining_date,
                    e.status,
                    e.password,
                    d.department_name,
                    l.location_name,
                    CONVERT(VARCHAR(10), e.created_at, 105) AS created_date
             FROM employees e
             LEFT JOIN departments d ON e.department_id = d.id
             LEFT JOIN locations l ON e.location_id = l.id
             ORDER BY e.id DESC";
$empStmt = sqlsrv_query($conn, $empQuery);
if ($empStmt !== false) {
    while ($row = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
        if (empty($row['password'])) {
            $row['password'] = trim($row['first_name'] ?? '') . '@' . trim($row['emp_code'] ?? '');
        }
        $initialEmployees[] = $row;
        if (($row['status'] ?? '') === 'Active') {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }
    $stats['total'] = count($initialEmployees);
    sqlsrv_free_stmt($empStmt);
}

// Fetch active departments for dropdowns
$deptStmt = sqlsrv_query($conn, "SELECT id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
if ($deptStmt !== false) {
    while ($dRow = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
        $departmentsList[] = $dRow;
    }
    sqlsrv_free_stmt($deptStmt);
}

// Fetch active locations for dropdowns
$locStmt = sqlsrv_query($conn, "SELECT id, location_name FROM locations WHERE status = 'Active' ORDER BY location_name ASC");
if ($locStmt !== false) {
    while ($lRow = sqlsrv_fetch_array($locStmt, SQLSRV_FETCH_ASSOC)) {
        $locationsList[] = $lRow;
    }
    sqlsrv_free_stmt($locStmt);
}

// Compute distinct departments assigned
$uniqueDepts = [];
foreach ($initialEmployees as $e) {
    if (!empty($e['department_id'])) {
        $uniqueDepts[$e['department_id']] = true;
    }
}
$stats['departments_count'] = count($uniqueDepts);

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Employee Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Master</span>
                <span>/</span>
                <span>Employees</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Employee Master
            </h1>
            <p>Manage workforce directory, department alignments, and asset recipient profiles.</p>
        </div>

        <div class="page-header-actions">
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
            <button type="button" class="btn-secondary" id="exportCsvBtn" title="Export employees list to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Export CSV</span>
            </button>

            <!-- Import CSV -->
            <button type="button" class="btn-secondary" id="openImportModalBtn" title="Import employees from CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Import CSV</span>
            </button>

            <!-- Add Employee Modal Trigger -->
            <button type="button" class="btn-primary" id="openAddModalBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add Employee</span>
            </button>
        </div>
    </div>

    <!-- Metric KPI Stat Cards -->
    <div class="cat-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Total Workforce</div>
                <div class="stat-val" id="statTotal"><?php echo $stats['total']; ?></div>
            </div>
            <div class="cat-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Active Employees</div>
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
                <div class="stat-lbl">Inactive / Exited</div>
                <div class="stat-val" id="statInactive"><?php echo $stats['inactive']; ?></div>
            </div>
            <div class="cat-stat-icon navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
            </div>
        </div>

        <div class="cat-stat-card">
            <div class="cat-stat-info">
                <div class="stat-lbl">Assigned Depts</div>
                <div class="stat-val" id="statDepts"><?php echo $stats['departments_count']; ?></div>
            </div>
            <div class="cat-stat-icon purple">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
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
                <input type="text" id="searchEmployee" placeholder="Search by name, employee code, email, designation...">
            </div>

            <!-- Department Filter -->
            <select class="filter-select" id="filterDepartment">
                <option value="All">All Departments</option>
                <?php foreach ($departmentsList as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Location Filter -->
            <select class="filter-select" id="filterLocation">
                <option value="All">All Locations</option>
                <?php foreach ($locationsList as $l): ?>
                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['location_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Status Filter -->
            <select class="filter-select" id="filterStatus">
                <option value="All">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>

            <button type="button" class="btn-filter-reset" id="resetFiltersBtn">Reset Filters</button>
        </div>
    </div>

    <!-- Employees Data Table Card -->
    <div class="content-card">
        <div class="card-header">
            <h2>All Employee Records</h2>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="employeesTable">
                <thead>
                    <tr>
                        <th style="min-width: 190px;">Employee</th>
                        <th style="min-width: 140px;">Role & Designation</th>
                        <th style="min-width: 130px;">Department</th>
                        <th style="min-width: 130px;">Branch / Location</th>
                        <th style="min-width: 160px;">Contact Information</th>
                        <th style="min-width: 110px;">Status & Joined</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="employeesTbody">
                    <?php if (empty($initialEmployees)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="table-empty-state">
                                    <div class="empty-icon">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </div>
                                    <div class="empty-title">No employees found</div>
                                    <div class="empty-desc">Get started by registering your first staff member to assign IT hardware and software.</div>
                                    <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add Employee</button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];
                        foreach ($initialEmployees as $index => $emp): 
                            $fullName = trim($emp['first_name'] . ' ' . ($emp['last_name'] ?? ''));
                            $statusClass = ($emp['status'] === 'Active') ? 'status-active' : 'status-inactive';
                            $initials = strtoupper(substr($emp['first_name'], 0, 1) . (!empty($emp['last_name']) ? substr($emp['last_name'], 0, 1) : ''));
                            $colorBg = $avatarColors[$emp['id'] % count($avatarColors)];
                        ?>
                            <tr data-id="<?php echo $emp['id']; ?>">
                                <td>
                                    <div class="emp-cell-flex">
                                        <div class="emp-avatar" style="background: <?php echo $colorBg; ?>;">
                                            <?php echo htmlspecialchars($initials); ?>
                                        </div>
                                        <div class="emp-details">
                                            <span class="emp-name-text"><?php echo htmlspecialchars($fullName); ?></span>
                                            <span class="emp-code-badge"><?php echo htmlspecialchars($emp['emp_code']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 500; color: var(--text-primary); font-size: 13px;">
                                        <?php echo !empty($emp['designation']) ? htmlspecialchars($emp['designation']) : '<span style="color:#94a3b8; font-style:italic;">—</span>'; ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-primary); font-size: 13px;">
                                    <?php echo !empty($emp['department_name']) ? htmlspecialchars($emp['department_name']) : '<span style="color:#94a3b8; font-style:italic;">—</span>'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($emp['location_name'])): ?>
                                        <div class="emp-location-badge">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            <span><?php echo htmlspecialchars($emp['location_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-style:italic;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 13px;">
                                    <?php if (!empty($emp['phone'])): ?>
                                        <div style="color: var(--text-primary); font-weight: 600; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($emp['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($emp['email'])): ?>
                                        <div style="color: var(--cyan-primary); font-size: 12px;">
                                            <?php echo htmlspecialchars($emp['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (empty($emp['phone']) && empty($emp['email'])): ?>
                                        <span style="color:#94a3b8; font-style:italic;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <span class="status-pill <?php echo $statusClass; ?>">
                                            <span class="status-dot"></span>
                                            <?php echo htmlspecialchars($emp['status']); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <span><?php echo !empty($emp['joining_date']) ? htmlspecialchars($emp['joining_date']) : '—'; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-actions" style="justify-content: center;">
                                        <!-- View Quick Profile -->
                                        <button type="button" class="action-btn view-btn" title="View Profile" onclick="viewEmployeeProfile(<?php echo $emp['id']; ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>

                                        <!-- Toggle Status Button -->
                                        <button type="button" class="action-btn toggle-btn" title="Toggle Status" onclick="toggleEmployeeStatus(<?php echo $emp['id']; ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="1 4 1 10 7 10"></polyline>
                                                <polyline points="23 20 23 14 17 14"></polyline>
                                                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                                            </svg>
                                        </button>

                                        <!-- Edit Button -->
                                        <button type="button" class="action-btn" title="Edit Employee" onclick="openEditEmployeeModal(<?php echo $emp['id']; ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" class="action-btn delete-btn" title="Delete Employee" onclick="openDeleteEmployeeModal(<?php echo $emp['id']; ?>, '<?php echo addslashes($fullName); ?>', '<?php echo addslashes($emp['emp_code']); ?>')">
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

<!-- ==================== ADD EMPLOYEE MODAL ==================== -->
<div class="modal-overlay" id="addEmployeeModal">
    <div class="modal-box" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Add New Employee</h3>
            <button class="modal-close-btn" id="closeAddModalBtn">&times;</button>
        </div>
        <form id="addEmployeeForm">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addEmpCode">Employee ID / Code *</label>
                        <input type="text" id="addEmpCode" placeholder="e.g. EMP-1009" required style="font-family: monospace; font-weight: 600;">
                    </div>

                    <div class="modal-form-group">
                        <label for="addEmail">Corporate Email *</label>
                        <input type="email" id="addEmail" placeholder="employee@viros.com" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addFirstName">First Name *</label>
                        <input type="text" id="addFirstName" placeholder="e.g. Rohit" required>
                    </div>

                    <div class="modal-form-group">
                        <label for="addLastName">Last Name</label>
                        <input type="text" id="addLastName" placeholder="e.g. Sharma">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addPhone">Phone / Mobile</label>
                        <input type="text" id="addPhone" placeholder="+91 98765 43210">
                    </div>

                    <div class="modal-form-group">
                        <label for="addDesignation">Designation / Role</label>
                        <input type="text" id="addDesignation" placeholder="e.g. Senior Software Engineer">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addDepartment">Department</label>
                        <select id="addDepartment">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departmentsList as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-form-group">
                        <label for="addLocation">Branch / Location</label>
                        <select id="addLocation">
                            <option value="">-- Select Location --</option>
                            <?php foreach ($locationsList as $l): ?>
                                <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['location_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="addJoiningDate">Date of Joining</label>
                        <input type="date" id="addJoiningDate">
                    </div>

                    <div class="modal-form-group">
                        <label for="addStatus">Status *</label>
                        <select id="addStatus" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAddModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT EMPLOYEE MODAL ==================== -->
<div class="modal-overlay" id="editEmployeeModal">
    <div class="modal-box" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Edit Employee Details</h3>
            <button class="modal-close-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editEmployeeForm">
            <input type="hidden" id="editId">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editEmpCode">Employee ID / Code *</label>
                        <input type="text" id="editEmpCode" required style="font-family: monospace; font-weight: 600;">
                    </div>

                    <div class="modal-form-group">
                        <label for="editEmail">Corporate Email *</label>
                        <input type="email" id="editEmail" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editFirstName">First Name *</label>
                        <input type="text" id="editFirstName" required>
                    </div>

                    <div class="modal-form-group">
                        <label for="editLastName">Last Name</label>
                        <input type="text" id="editLastName">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editPhone">Phone / Mobile</label>
                        <input type="text" id="editPhone">
                    </div>

                    <div class="modal-form-group">
                        <label for="editDesignation">Designation / Role</label>
                        <input type="text" id="editDesignation">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editDepartment">Department</label>
                        <select id="editDepartment">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departmentsList as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-form-group">
                        <label for="editLocation">Branch / Location</label>
                        <select id="editLocation">
                            <option value="">-- Select Location --</option>
                            <?php foreach ($locationsList as $l): ?>
                                <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['location_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="modal-form-group">
                        <label for="editJoiningDate">Date of Joining</label>
                        <input type="date" id="editJoiningDate">
                    </div>

                    <div class="modal-form-group">
                        <label for="editStatus">Status *</label>
                        <select id="editStatus" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelEditModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Update Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== VIEW EMPLOYEE PROFILE MODAL ==================== -->
<div class="modal-overlay" id="viewProfileModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Employee Details</h3>
            <button class="modal-close-btn" id="closeProfileModalBtn">&times;</button>
        </div>

        <div class="modal-body" style="padding: 20px 22px;">
            <!-- Top Summary Header -->
            <div class="emp-profile-summary">
                <div class="emp-avatar" id="profileAvatar" style="width: 44px; height: 44px; font-size: 15px;">RK</div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <h4 id="profileName" style="font-size: 16px; font-weight: 700; color: var(--navy-primary); margin: 0; line-height: 1.3;">Rajesh Kumar</h4>
                        <span class="status-pill status-active" id="profileStatusPill">
                            <span class="status-dot"></span>
                            <span id="profileStatus">Active</span>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; font-size: 12.5px;">
                        <span style="color: var(--text-secondary);" id="profileDesignation">Senior DevOps Engineer</span>
                        <span style="color: var(--border-color);">&bull;</span>
                        <span class="emp-code-badge" id="profileEmpCode" style="margin-top: 0;">EMP-1001</span>
                    </div>
                </div>
            </div>

            <!-- Details 2-Column Grid (Simple & Normal) -->
            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <span class="profile-info-label">Department</span>
                    <span class="profile-info-val" id="profileDepartment">Information Technology (IT)</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Branch / Location</span>
                    <span class="profile-info-val" id="profileLocation">Tech Hub - Bangalore</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Corporate Email</span>
                    <span class="profile-info-val" id="profileEmail" style="color: var(--cyan-primary); font-weight: 500;">rajesh.kumar@viros.com</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Phone / Mobile</span>
                    <span class="profile-info-val" id="profilePhone">+91 98765 43210</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Date of Joining</span>
                    <span class="profile-info-val" id="profileJoiningDate">15-04-2023</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Account Setup</span>
                    <span class="profile-info-val" id="profileAccountStatus" style="font-weight: 600; color: #059669;">First-Time Login Self Service</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">System Created</span>
                    <span class="profile-info-val" id="profileCreatedDate">03-10-2026</span>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="closeProfileBtn">Close</button>
            <button type="button" class="btn-primary" id="editFromProfileBtn">Edit Profile</button>
        </div>
    </div>
</div>

<!-- ==================== DELETE CONFIRMATION MODAL ==================== -->
<div class="modal-overlay" id="deleteEmployeeModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 style="font-size: 17px; color: var(--navy-primary); margin-bottom: 8px;">Delete Employee?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px;">
                Are you sure you want to remove <strong id="deleteEmployeeName" style="color: var(--text-primary);"></strong> (<span id="deleteEmployeeCode" style="font-family: monospace;"></span>)? This action cannot be undone.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelDeleteModalBtn">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">Yes, Delete Employee</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== IMPORT EMPLOYEES MODAL ==================== -->
<div class="modal-overlay" id="importEmployeeModal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <h3>Import Workforce Data</h3>
            <button class="modal-close-btn" id="closeImportModalBtn">&times;</button>
        </div>
        <form id="importEmployeeForm">
            <div class="modal-body">
                <!-- Template Notice -->
                <div class="import-template-box">
                    <div class="template-text">
                        <strong>CSV Format:</strong> Ensure columns for Employee ID, First Name, Last Name, Email, Phone, Department, Location, Designation, Joining Date, and Status.
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
                            <div class="file-meta" id="previewFileMeta">0 KB • 0 employees detected</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-file" id="removeFileBtn" title="Remove file">&times;</button>
                </div>

                <!-- Duplicate Option -->
                <div class="modal-form-group">
                    <label for="duplicateHandling">Duplicate Employee Handling</label>
                    <select id="duplicateHandling">
                        <option value="skip">Skip duplicates (keep existing records)</option>
                        <option value="update">Update existing records</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelImportModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="startImportBtn" disabled>Import Employees</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
