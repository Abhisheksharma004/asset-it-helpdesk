<?php
// Asset Management & IT Service Desk Portal - Admin Settings Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter for staging)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title  = "Settings - Organization & User Configuration";
$active_page = "settings";
$extra_css   = ['css/settings.css'];
$extra_js    = ['js/settings.js'];

// Include Database Configuration (MSSQL Server)
require_once __DIR__ . '/config/db.php';

// Fetch Organization Settings from database
$orgStmt = sqlsrv_query($conn, "SELECT TOP 1 organization_legal_name, support_desk_email, it_helpdesk_helpline, primary_brand_logo, browser_favicon FROM organization_settings ORDER BY id ASC");
$orgSettings = ($orgStmt && ($row = sqlsrv_fetch_array($orgStmt, SQLSRV_FETCH_ASSOC))) ? $row : [
    'organization_legal_name' => 'Viros Technologies Pvt Ltd',
    'support_desk_email' => 'it-desk@viros.in',
    'it_helpdesk_helpline' => '+91 (020) 4123-8899',
    'primary_brand_logo' => 'assets/images/logo.png',
    'browser_favicon' => 'assets/images/logo.png'
];
if ($orgStmt) sqlsrv_free_stmt($orgStmt);

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- ==================== MAIN SETTINGS PAGE CONTENT ==================== -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="settings-header-bar">
        <div class="settings-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Administration</span>
                <span>/</span>
                <span>Settings</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                System Settings
            </h1>
            <p>Manage your corporate organization profile, enterprise branding, and user account security policies.</p>
        </div>

        <div class="settings-header-actions">
            <div class="system-status-pill">
                <span class="status-dot-pulse"></span>
                <span>Portal Live • MSSQL Connected</span>
            </div>
        </div>
    </div>


    <!-- Settings Main Layout Grid -->
    <div class="settings-layout-grid">

        <!-- ==================== LEFT NAVIGATION TABS (ONLY 2 TABS) ==================== -->
        <aside class="settings-nav-sidebar">
            <div class="settings-nav-title">Settings Modules</div>
            <ul class="settings-nav-list">
                <li>
                    <button type="button" class="settings-nav-btn active" data-tab="panel-organization">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Organization Setting</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="settings-nav-btn" data-tab="panel-users">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Technician Setting</span>
                    </button>
                </li>
            </ul>
        </aside>

        <!-- ==================== RIGHT TAB PANELS ==================== -->
        <div class="settings-content-wrapper">

            <!-- ==================================================== -->
            <!-- 1. ORGANIZATION SETTING TAB                          -->
            <!-- ==================================================== -->
            <div class="settings-tab-panel active" id="panel-organization">
                
                <!-- Organization Information & Branding Card (View & Edit Modes) -->
                <div class="settings-card">
                    <div class="settings-card-header with-bg">
                        <div class="settings-card-title">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                Organization Information
                            </h3>
                            <p>Global company legal details, communication channels, and portal brand logos.</p>
                        </div>
                        <div class="settings-card-actions">
                            <!-- View Mode Button -->
                            <button type="button" class="btn-primary" id="btnEditOrg">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Edit Details
                            </button>

                            <!-- Edit Mode Buttons (Initially Hidden) -->
                            <div id="orgEditActions" style="display: none; align-items: center; gap: 8px;">
                                <button type="button" class="btn-secondary" id="btnCancelOrgEdit">Cancel</button>
                                <button type="button" class="btn-primary" id="btnSaveOrgEdit">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card-body">
                        
                        <!-- ================= 1. VIEW MODE (DEFAULT) ================= -->
                        <div id="orgViewMode">
                            <div class="org-info-tiles-grid">
                                <div class="org-info-tile">
                                    <div class="org-info-tile-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                        </svg>
                                    </div>
                                    <div class="org-info-tile-body">
                                        <div class="org-info-tile-label">Organization Name</div>
                                        <div class="org-info-tile-value" id="viewOrgName"><?php echo htmlspecialchars($orgSettings['organization_legal_name'] ?? 'Viros Technologies Pvt Ltd'); ?></div>
                                    </div>
                                </div>

                                <div class="org-info-tile">
                                    <div class="org-info-tile-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                    </div>
                                    <div class="org-info-tile-body">
                                        <div class="org-info-tile-label">Support Desk Email</div>
                                        <div class="org-info-tile-value" id="viewOrgEmail"><?php echo htmlspecialchars($orgSettings['support_desk_email'] ?? 'it-desk@viros.in'); ?></div>
                                    </div>
                                </div>

                                <div class="org-info-tile">
                                    <div class="org-info-tile-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                    </div>
                                    <div class="org-info-tile-body">
                                        <div class="org-info-tile-label">IT Helpdesk Helpline</div>
                                        <div class="org-info-tile-value" id="viewOrgPhone"><?php echo htmlspecialchars($orgSettings['it_helpdesk_helpline'] ?? '+91 (020) 4123-8899'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtle Divider -->
                            <div style="border-top: 1px solid var(--border-color); margin: 24px 0 20px;"></div>

                            <div style="font-size: 13px; font-weight: 600; color: var(--navy-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cyan-primary);">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                Brand Assets & Logos
                            </div>

                            <div class="org-brand-display-grid">
                                <div class="org-brand-display-item">
                                    <div class="brand-preview-wrap">
                                        <img src="<?php echo htmlspecialchars($orgSettings['primary_brand_logo'] ?? 'assets/images/logo.png'); ?>" alt="Current Logo" id="viewLogoImg">
                                    </div>
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 700; color: var(--navy-primary);">Primary Brand Logo</div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Active portal navigation & report header logo</div>
                                    </div>
                                </div>

                                <div class="org-brand-display-item">
                                    <div class="favicon-preview-wrap">
                                        <img src="<?php echo htmlspecialchars($orgSettings['browser_favicon'] ?? 'assets/images/logo.png'); ?>" alt="Favicon" id="viewFaviconImg">
                                    </div>
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 700; color: var(--navy-primary);">Browser Favicon</div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Active browser tab shortcut icon</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= 2. EDIT MODE (HIDDEN INITIALLY) ================= -->
                        <div id="orgEditMode" style="display: none;">
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label for="settingCompanyName">Organization Legal Name *</label>
                                    <input type="text" id="settingCompanyName" class="form-input settings-form-control" value="<?php echo htmlspecialchars($orgSettings['organization_legal_name'] ?? 'Viros Technologies Pvt Ltd'); ?>">
                                    <span class="form-help">Appears on invoices, asset labels, and system reports.</span>
                                </div>

                                <div class="form-group">
                                    <label for="settingSupportEmail">Support Desk Email *</label>
                                    <input type="email" id="settingSupportEmail" class="form-input settings-form-control" value="<?php echo htmlspecialchars($orgSettings['support_desk_email'] ?? 'it-desk@viros.in'); ?>">
                                    <span class="form-help">Primary support contact address.</span>
                                </div>

                                <div class="form-group">
                                    <label for="settingSupportPhone">IT Helpdesk Helpline</label>
                                    <input type="text" id="settingSupportPhone" class="form-input settings-form-control" value="<?php echo htmlspecialchars($orgSettings['it_helpdesk_helpline'] ?? '+91 (020) 4123-8899'); ?>">
                                    <span class="form-help">Toll-free or office desk phone.</span>
                                </div>
                            </div>

                            <!-- Subtle Divider -->
                            <div style="border-top: 1px solid var(--border-color); margin: 24px 0 20px;"></div>

                            <div style="font-size: 13px; font-weight: 600; color: var(--navy-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cyan-primary);">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                Upload & Update Brand Assets
                            </div>

                            <div class="branding-upload-grid">
                                <div class="brand-upload-item">
                                    <div class="brand-preview-wrap">
                                        <img src="<?php echo htmlspecialchars($orgSettings['primary_brand_logo'] ?? 'assets/images/logo.png'); ?>" alt="Current Logo" id="logoPreviewImg">
                                    </div>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 600; color: var(--navy-primary);">Primary Brand Logo</div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">PNG, SVG, JPG or WebP • Recommended: 280 x 70 px</div>
                                    </div>
                                    <div class="upload-action-btns">
                                        <input type="file" id="inputLogoFile" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="display: none;">
                                        <button type="button" class="btn-secondary" id="btnTriggerLogoUpload">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 12 3 7 8"></polyline>
                                                <line x1="12" y1="3" x2="12" y2="15"></line>
                                            </svg>
                                            Upload Logo
                                        </button>
                                        <button type="button" class="btn-secondary" id="btnResetLogo" title="Reset default logo">Reset</button>
                                    </div>
                                </div>

                                <div class="brand-upload-item">
                                    <div class="favicon-preview-wrap">
                                        <img src="<?php echo htmlspecialchars($orgSettings['browser_favicon'] ?? 'assets/images/logo.png'); ?>" alt="Favicon" id="faviconPreviewImg">
                                    </div>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 600; color: var(--navy-primary);">Browser Favicon</div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">ICO, PNG or SVG • Recommended: 32 x 32 px</div>
                                    </div>
                                    <div class="upload-action-btns">
                                        <input type="file" id="inputFaviconFile" accept="image/x-icon,image/png,image/svg+xml" style="display: none;">
                                        <button type="button" class="btn-secondary" id="btnTriggerFaviconUpload">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 12 3 7 8"></polyline>
                                                <line x1="12" y1="3" x2="12" y2="15"></line>
                                            </svg>
                                            Upload Icon
                                        </button>
                                        <button type="button" class="btn-secondary" id="btnResetFavicon" title="Reset default favicon">Reset</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- ==================================================== -->
            <!-- 2. USER SETTING TAB                                  -->
            <!-- ==================================================== -->
            <div class="settings-tab-panel" id="panel-users">
                
                <!-- User Directory & Privilege Overview -->
                <div class="settings-card">
                    <div class="settings-card-header with-bg">
                        <div class="settings-card-title">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                Administrative Privileges & Key Personnel
                            </h3>
                            <p>Overview of system administrators and IT technicians who have elevated access to settings.</p>
                        </div>
                        <div class="settings-card-actions">
                            <button type="button" class="btn-primary" id="btnAddTechnician">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Add Technician
                            </button>
                        </div>
                    </div>
                    <div class="settings-card-body" style="padding: 0;">
                        <div class="sla-table-responsive" style="margin-top: 0;">
                            <table class="sla-table">
                                <thead>
                                    <tr>
                                        <th>User Name</th>
                                        <th>Role / Privilege</th>
                                        <th>Email Address</th>
                                        <th>2FA Status</th>
                                        <th>Account Status</th>
                                    </tr>
                                </thead>
                                <tbody id="techniciansTableBody">
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">AS</div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--navy-primary);">Abhishek Sharma</div>
                                                    <div style="font-size: 11px; color: var(--text-muted);">EMP-1001</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="priority-pill urgent">Super Admin</span></td>
                                        <td>abhishek@viros.in</td>
                                        <td><span style="color: var(--success); font-weight: 600; font-size: 12px;">✓ Enforced</span></td>
                                        <td><span style="color: var(--success); font-weight: 600; font-size: 12px;">Active</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">RK</div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--navy-primary);">Rahul Kulkarni</div>
                                                    <div style="font-size: 11px; color: var(--text-muted);">EMP-1002</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="priority-pill high">IT Lead</span></td>
                                        <td>rahul.k@viros.in</td>
                                        <td><span style="color: var(--success); font-weight: 600; font-size: 12px;">✓ Enforced</span></td>
                                        <td><span style="color: var(--success); font-weight: 600; font-size: 12px;">Active</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">PS</div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--navy-primary);">Priya Sharma</div>
                                                    <div style="font-size: 11px; color: var(--text-muted);">EMP-1003</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="priority-pill medium">IT Support Desk</span></td>
                                        <td>priya.s@viros.in</td>
                                        <td><span style="color: var(--warning); font-weight: 600; font-size: 12px;">Pending</span></td>
                                        <td><span style="color: var(--success); font-weight: 600; font-size: 12px;">Active</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal: Add New Technician -->
            <div class="modal-overlay" id="addTechnicianModal" style="display: none;">
                <div class="modal-box" style="max-width: 520px;">
                    <div class="modal-header">
                        <h3>Add IT Technician / Administrator</h3>
                        <button type="button" class="modal-close-btn" id="closeTechModalBtn">&times;</button>
                    </div>
                    <form id="addTechnicianForm">
                        <div class="modal-body">
                            <div class="modal-form-group">
                                <label for="techName">Full Name *</label>
                                <input type="text" id="techName" placeholder="e.g. Amit Patil" required>
                            </div>

                            <div class="modal-form-group">
                                <label for="techEmail">Corporate Email *</label>
                                <input type="email" id="techEmail" placeholder="e.g. amit.p@viros.in" required>
                            </div>

                            <div class="form-grid-2">
                                <div class="modal-form-group">
                                    <label for="techRole">Role / Access Level *</label>
                                    <select id="techRole" required>
                                        <option value="IT Support Desk" selected>IT Support Desk</option>
                                        <option value="IT Lead">IT Lead</option>
                                        <option value="Super Admin">Super Admin</option>
                                        <option value="Hardware Technician">Hardware Technician</option>
                                    </select>
                                </div>

                                <div class="modal-form-group">
                                    <label for="techPhone">Helpline Phone Number</label>
                                    <input type="text" id="techPhone" placeholder="+91 98765 43210">
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="modal-form-group">
                                    <label for="techUserId">User ID / Username *</label>
                                    <input type="text" id="techUserId" placeholder="e.g. EMP-1004" required>
                                </div>
                                <div class="modal-form-group">
                                    <label for="techPassword">Login Password *</label>
                                    <div class="password-input-wrap">
                                        <input type="password" id="techPassword" placeholder="Enter password" required>
                                        <button type="button" class="password-toggle-btn" data-target="techPassword" title="Toggle password visibility">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-secondary" id="cancelTechModalBtn">Cancel</button>
                            <button type="submit" class="btn-primary" style="background: var(--cyan-primary); border-color: var(--cyan-primary);">Save Technician</button>
                        </div>
                    </form>
                </div>
            </div>

        </div> <!-- End .settings-content-wrapper -->

    </div> <!-- End .settings-layout-grid -->

    <!-- ==================== FLOATING UNSAVED CHANGES DOCK ==================== -->
    <div class="unsaved-changes-dock" id="unsavedChangesDock">
        <div class="dock-text">
            <span class="dock-dot"></span>
            <span>You have modified settings that are pending save</span>
        </div>
        <div class="dock-actions">
            <button type="button" class="btn-dock-discard" id="btnDiscardDock">
                Discard
            </button>
            <button type="button" class="btn-primary" id="btnSaveSettingsDock">
                Save Changes
            </button>
        </div>
    </div>

</main> <!-- End .dashboard-content -->

<?php
// Include Footer & Scripts Component
include 'includes/footer.php';
?>
