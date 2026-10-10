<?php
/**
 * Global Employee Sidebar Navigation Component
 * Contains strictly the requested 4 navigation items:
 *   1. My Dashboard
 *   2. Employee Profile
 *   3. My Assets
 *   4. My Support Tickets
 */
if (!isset($active_page)) {
    $active_page = 'employee_dashboard';
}

$emp_sidebar_name = $activeEmployee['name'] ?? ($_SESSION['user_name'] ?? 'Abhishek Sharma');
$emp_sidebar_code = $activeEmployee['code'] ?? 'EMP-1004';
$emp_sidebar_role = $activeEmployee['designation'] ?? 'Staff Member';

$emp_initials = '';
foreach (explode(' ', trim($emp_sidebar_name)) as $w) {
    if (!empty($w)) $emp_initials .= strtoupper($w[0]);
    if (strlen($emp_initials) >= 2) break;
}
if (empty($emp_initials)) $emp_initials = 'EM';
?>
<!-- ==================== EMPLOYEE SIDEBAR ==================== -->
<aside class="sidebar" id="sidebar">
    
    <!-- Sidebar Brand Header -->
    <a href="employee_dashboard.php" class="sidebar-brand">
        <div class="brand-logo-wrap">
            <img src="assets/images/logo.png" alt="VIROS Logo">
        </div>
        <div class="brand-text">
            <span class="brand-title">VIROS <span>PORTAL</span></span>
            <span class="brand-subtitle">Employee IT Self-Service</span>
        </div>
    </a>

    <!-- Navigation Menu Items (Only 4 Items) -->
    <ul class="sidebar-menu">
        <!-- 1. My Dashboard -->
        <li class="nav-item">
            <a href="employee_dashboard.php" class="nav-link <?php echo ($active_page === 'employee_dashboard') ? 'active' : ''; ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </span>
                <span class="nav-text">My Dashboard</span>
            </a>
        </li>

        <!-- 2. Employee Profile -->
        <li class="nav-item">
            <a href="javascript:void(0)" class="nav-link" onclick="if(typeof openEmployeeProfileModal === 'function') openEmployeeProfileModal();">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                <span class="nav-text">Employee Profile</span>
            </a>
        </li>

        <!-- 3. My Assets -->
        <li class="nav-item">
            <a href="javascript:void(0)" class="nav-link" onclick="if(typeof scrollToMyAssets === 'function') { scrollToMyAssets(); } else { document.getElementById('secMyAssets')?.scrollIntoView({behavior: 'smooth'}); }">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </span>
                <span class="nav-text">My Assets</span>
            </a>
        </li>

        <!-- 4. My Support Tickets -->
        <li class="nav-item">
            <a href="javascript:void(0)" class="nav-link" onclick="if(typeof scrollToMyTickets === 'function') { scrollToMyTickets(); } else { document.getElementById('secMyTickets')?.scrollIntoView({behavior: 'smooth'}); }">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </span>
                <span class="nav-text">My Support Tickets</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer Employee Profile -->
    <div class="sidebar-footer">
        <div class="sidebar-user" style="cursor: pointer;" onclick="if(typeof openEmployeeProfileModal === 'function') openEmployeeProfileModal();" title="Click to view full employee profile">
            <div class="user-avatar" style="background: var(--cyan-primary);"><?php echo htmlspecialchars($emp_initials); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($emp_sidebar_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($emp_sidebar_code); ?> • Staff</div>
            </div>
        </div>
        <a href="api/logout.php" class="logout-btn" title="Sign Out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>

</aside>
