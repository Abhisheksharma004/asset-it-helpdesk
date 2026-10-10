<?php
// Asset Management & IT Service Desk Portal - Employee Profile Page (Dedicated UI)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title  = "Employee Profile - VIROS IT Portal";
$active_page = "employee_profile";
$extra_css   = ['css/employee_dashboard.css', 'css/employee_profile.css'];
$extra_js    = ['js/employee_dashboard.js'];

// Include Database Configuration
require_once __DIR__ . '/config/db.php';

// Default Active Employee Details
$activeEmployee = [
    'name'        => $_SESSION['user_name'] ?? 'Abhishek Ranjan',
    'code'        => $_SESSION['user_username'] ?? 'VE015',
    'designation' => 'Senior Software Engineer',
    'department'  => $_SESSION['user_dept'] ?? 'Information Technology',
    'email'       => $_SESSION['user_email'] ?? 'abhishekkumarranjan965@gmail.com',
    'phone'       => '+91 98765 43210',
    'location'    => 'Bangalore HQ (Floor 3)',
    'joining_date'=> '15 Jan 2024',
    'status'      => 'Active',
    'custody'     => 'Verified & Active'
];

// Fetch live employee data if connected
if (isset($conn) && $conn !== false && !empty($_SESSION['user_id'])) {
    $currStmt = sqlsrv_query(
        $conn, 
        "SELECT e.*, d.department_name, l.location_name 
         FROM employees e 
         LEFT JOIN departments d ON e.department_id = d.id 
         LEFT JOIN locations l ON e.location_id = l.id 
         WHERE e.id = ? OR LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)", 
        [$_SESSION['user_id'], $_SESSION['user_email'] ?? '', $_SESSION['user_username'] ?? '']
    );
    if ($currStmt && ($currRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC))) {
        $fullName = trim(($currRow['first_name'] ?? '') . ' ' . ($currRow['last_name'] ?? ''));
        $joinDateFormatted = '15 Jan 2024';
        if (!empty($currRow['joining_date'])) {
            $joinDateFormatted = is_object($currRow['joining_date']) 
                ? $currRow['joining_date']->format('d M Y') 
                : date('d M Y', strtotime($currRow['joining_date']));
        }

        $activeEmployee = [
            'id'           => $currRow['id'],
            'name'         => $fullName ?: ($_SESSION['user_name'] ?? 'Employee'),
            'code'         => $currRow['emp_code'] ?? 'EMP-000',
            'designation'  => $currRow['designation'] ?: 'Staff Member',
            'department'   => $currRow['department_name'] ?: ($_SESSION['user_dept'] ?? 'General'),
            'email'        => $currRow['email'] ?? $_SESSION['user_email'],
            'phone'        => $currRow['phone'] ?: '+91 98765 43210',
            'location'     => $currRow['location_name'] ?: 'Bangalore HQ (Floor 3)',
            'joining_date' => $joinDateFormatted,
            'status'       => $currRow['status'] ?? 'Active',
            'custody'      => ($currRow['status'] === 'Active') ? 'Verified & Active' : 'Inactive'
        ];
        sqlsrv_free_stmt($currStmt);
    }
}

// Compute initials for Avatar
$avatarInitials = '';
foreach (explode(' ', trim($activeEmployee['name'])) as $w) {
    if (!empty($w)) $avatarInitials .= strtoupper($w[0]);
    if (strlen($avatarInitials) >= 2) break;
}
if (empty($avatarInitials)) $avatarInitials = 'EM';

// Layout Components
include 'includes/header.php';
include 'includes/employee_sidebar.php';
include 'includes/employee_topbar.php';
?>

<!-- Profile Page Body Content -->
<main class="dashboard-content">

    <!-- Breadcrumb -->
    <nav class="profile-breadcrumb">
        <a href="employee_dashboard.php">Home</a>
        <span>/</span>
        <a href="employee_dashboard.php">My Dashboard</a>
        <span>/</span>
        <span class="current">Employee Profile</span>
    </nav>

    <!-- Hero Profile Banner Card -->
    <div class="profile-hero-card">
        <div class="profile-hero-left">
            <div class="profile-avatar-large">
                <?php echo htmlspecialchars($avatarInitials); ?>
            </div>
            <div class="profile-hero-info">
                <h1><?php echo htmlspecialchars($activeEmployee['name']); ?></h1>
                <div class="profile-tags-wrap">
                    <span class="hero-tag">
                        <strong>ID:</strong> <?php echo htmlspecialchars($activeEmployee['code']); ?>
                    </span>
                    <span class="hero-tag">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        <?php echo htmlspecialchars($activeEmployee['designation']); ?>
                    </span>
                    <span class="hero-tag">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="22.01"></line><line x1="15" y1="22" x2="15" y2="22.01"></line></svg>
                        <?php echo htmlspecialchars($activeEmployee['department']); ?>
                    </span>
                    <span class="hero-tag">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?php echo htmlspecialchars($activeEmployee['location']); ?>
                    </span>
                    <span class="hero-tag status-active">
                        ● <?php echo htmlspecialchars($activeEmployee['custody']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="profile-hero-actions">
            <button type="button" class="btn-hero-action" onclick="openChangePasswordModal()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Change Password
            </button>
            <a href="employee_dashboard.php" class="btn-hero-action secondary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                View IT Dashboard
            </a>
        </div>
    </div>

    <!-- Main Content Layout Grid -->
    <div class="profile-layout-grid">

        <!-- Left Column: Official Employment Details -->
        <div>
            <!-- Card 1: Official Employment Details -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Official Employment Details
                    </h2>
                    <span class="badge-pill-verified">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -1px; margin-right: 2px;"><polyline points="20 6 9 17 4 12"></polyline></svg> Verified Profile
                    </span>
                </div>

                <div class="profile-card-body">
                    <div class="profile-fields-grid">
                        <div class="profile-field-item">
                            <span class="field-label">Full Legal Name</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['name']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Employee Code</span>
                            <span class="field-value highlight"><?php echo htmlspecialchars($activeEmployee['code']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Corporate Email</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['email']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Primary Mobile</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['phone']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Designation / Role</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['designation']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Department</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['department']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Primary Workstation / Location</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['location']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Date of Joining</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['joining_date']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Employment Type</span>
                            <span class="field-value">Full-Time / Permanent</span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Portal Access Status</span>
                            <span class="field-value" style="color: #10b981;">● Active & Authenticated</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Portal Security & IT Desk Support -->
        <div>
            <!-- Card 2: Security & Portal Login Details -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Security & Portal Credentials
                    </h2>
                </div>

                <div class="profile-card-body">
                    <div class="security-notice-box">
                        <div class="icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <div class="desc">
                            <h4>Self-Service Security Management</h4>
                            <p>You can change your portal login password anytime. Your changes sync immediately across all employee IT desk operations.</p>
                        </div>
                    </div>

                    <div class="profile-fields-grid" style="margin-bottom: 20px;">
                        <div class="profile-field-item">
                            <span class="field-label">Sign-In Identifier</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['email']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Employee Username</span>
                            <span class="field-value"><?php echo htmlspecialchars($activeEmployee['code']); ?></span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Account Password</span>
                            <span class="field-value">••••••••••••••</span>
                        </div>

                        <div class="profile-field-item">
                            <span class="field-label">Support Priority SLA</span>
                            <span class="field-value highlight">Standard IT SLA (4h)</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="button" class="btn-primary" onclick="openChangePasswordModal()" style="padding: 10px 20px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Change Portal Password
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card 3: IT Desk Support & Direct Contact -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                        IT Support & Custody Desk
                    </h2>
                </div>

                <div class="profile-card-body">
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #f1f5f9; color: var(--navy-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect>
                                    <line x1="9" y1="22" x2="9" y2="22.01"></line>
                                    <line x1="15" y1="22" x2="15" y2="22.01"></line>
                                </svg>
                            </div>
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: var(--navy-primary);">IT Procurement & Asset Team</div>
                                <div style="font-size: 11.5px; color: var(--text-muted);">Floor 3, Tech Ops Bay 4</div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </div>
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: #059669;">Internal Hotline: Ext 204</div>
                                <div style="font-size: 11.5px; color: var(--text-muted);">Available Mon - Sat (9:00 AM - 7:00 PM)</div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </div>
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: #2563eb;">it.support@viros.in</div>
                                <div style="font-size: 11.5px; color: var(--text-muted);">Official IT Helpdesk Ticketing Queue</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</main>

<!-- Change Account Password Modal (Built-in for Profile Page) -->
<div class="modal-overlay" id="changePasswordModal" style="display: none; z-index: 9999;">
    <div class="modal-box" style="max-width: 460px; border-top: 4px solid var(--cyan-primary);">
        <div class="modal-header" style="padding: 18px 20px 14px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(0, 147, 167, 0.12); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div>
                    <h3 id="pwdModalTitle" style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--navy-primary);">
                        Change Account Password
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                        Update your portal login credentials.
                    </p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('changePasswordModal')">&times;</button>
        </div>

        <form id="changePasswordForm">
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="currentPasswordInput" style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Current Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="currentPasswordInput" required placeholder="Enter current password" style="width: 100%; padding: 10px 60px 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box;">
                        <button type="button" class="pwd-toggle-btn" onclick="toggleInputType('currentPasswordInput', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 11.5px; font-weight: 600; padding: 4px 6px;">
                            Show
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="newPasswordInput" style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        New Password * <span style="font-size: 11px; font-weight: 400; color: var(--text-muted);">(Min. 6 characters)</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="newPasswordInput" required minlength="6" placeholder="Enter new password" style="width: 100%; padding: 10px 60px 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box;">
                        <button type="button" class="pwd-toggle-btn" onclick="toggleInputType('newPasswordInput', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 11.5px; font-weight: 600; padding: 4px 6px;">
                            Show
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="confirmPasswordInput" style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                        Confirm New Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="confirmPasswordInput" required minlength="6" placeholder="Re-enter new password" style="width: 100%; padding: 10px 60px 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box;">
                        <button type="button" class="pwd-toggle-btn" onclick="toggleInputType('confirmPasswordInput', this)" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 11.5px; font-weight: 600; padding: 4px 6px;">
                            Show
                        </button>
                    </div>
                    <small id="pwdMatchMsg" style="display: block; margin-top: 4px; font-size: 11px;"></small>
                </div>
            </div>

            <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                <button type="submit" class="btn-primary" id="savePasswordBtn" style="padding: 9px 20px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Save Password</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Layout Footer
include 'includes/footer.php';
?>
