<?php
/**
 * Global Sidebar Navigation Component
 * Supports single links as well as interactive multi-level dropdown submenus.
 *
 * Variables you can define before including:
 *   $active_page (string) - Active page identifier
 */
if (!isset($active_page)) {
    $active_page = 'dashboard';
}

// Check which dropdown groups should be open by default
$is_master_open = in_array($active_page, ['master', 'master_category', 'master_accessory_category', 'master_component_category', 'master_department', 'master_location', 'master_vendor', 'master_employee', 'master_status']);
$is_assets_open = in_array($active_page, ['assets', 'asset_management', 'accessories', 'accessories_management', 'components', 'component_management', 'asset_assignment', 'asset_transfer', 'asset_return', 'asset_repair', 'software', 'software_licenses', 'asset_barcode', 'hardware', 'assets_all']);
$is_tickets_open = in_array($active_page, ['tickets', 'tickets_all', 'tickets_open', 'tickets_progress', 'tickets_resolved']);
$is_reports_open = in_array($active_page, ['reports', 'reports_audit', 'reports_sla', 'reports_warranty']);
?>
<!-- ==================== SIDEBAR ==================== -->
<aside class="sidebar" id="sidebar">
    
    <!-- Sidebar Brand Header -->
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-logo-wrap">
            <img src="assets/images/logo.png" alt="VIROS Logo">
        </div>
        <div class="brand-text">
            <span class="brand-title">VIROS <span>PORTAL</span></span>
            <span class="brand-subtitle">IT Asset & Service Desk</span>
        </div>
    </a>

    <!-- Navigation Menu Items -->
    <ul class="sidebar-menu">
        <!-- Dashboard (Single Item) -->
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>

        <!-- Master (Dropdown) -->
        <li class="nav-item nav-item-dropdown <?php echo $is_master_open ? 'open' : ''; ?>">
            <a href="javascript:void(0)" class="nav-link dropdown-toggle <?php echo $is_master_open ? 'active' : ''; ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                    </svg>
                </span>
                <span class="nav-text">Master</span>
                <span class="dropdown-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
            <ul class="submenu">
                <li>
                    <a href="categories.php" class="submenu-link <?php echo ($active_page === 'master_category') ? 'active' : ''; ?>">
                        <span>Asset Category</span>
                    </a>
                </li>
                <li>
                    <a href="accessory_categories.php" class="submenu-link <?php echo ($active_page === 'master_accessory_category') ? 'active' : ''; ?>">
                        <span>Accessory Category</span>
                    </a>
                </li>
                <li>
                    <a href="component_categories.php" class="submenu-link <?php echo ($active_page === 'master_component_category') ? 'active' : ''; ?>">
                        <span>Parts / Component Category</span>
                    </a>
                </li>
                <li>
                    <a href="departments.php" class="submenu-link <?php echo ($active_page === 'master_department') ? 'active' : ''; ?>">
                        <span>Department</span>
                    </a>
                </li>
                <li>
                    <a href="locations.php" class="submenu-link <?php echo ($active_page === 'master_location') ? 'active' : ''; ?>">
                        <span>Branch / Location</span>
                    </a>
                </li>
                <li>
                    <a href="vendors.php" class="submenu-link <?php echo ($active_page === 'master_vendor') ? 'active' : ''; ?>">
                        <span>Vendor / Supplier</span>
                    </a>
                </li>
                <li>
                    <a href="employees.php" class="submenu-link <?php echo ($active_page === 'master_employee') ? 'active' : ''; ?>">
                        <span>Employee Master</span>
                    </a>
                </li>
                <li>
                    <a href="#master-status" class="submenu-link <?php echo ($active_page === 'master_status') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Status & Priority Master')">
                        <span>Status & Priority</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- DROPDOWN 1: Asset Management -->
        <li class="nav-item nav-item-dropdown <?php echo $is_assets_open ? 'open' : ''; ?>">
            <a href="javascript:void(0)" class="nav-link dropdown-toggle <?php echo $is_assets_open ? 'active' : ''; ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </span>
                <span class="nav-text">Assets</span>
                <span class="dropdown-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
            <ul class="submenu">
                <li>
                    <a href="assets.php" class="submenu-link <?php echo in_array($active_page, ['assets', 'asset_management', 'assets_all']) ? 'active' : ''; ?>">
                        <span>Asset Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo file_exists(__DIR__ . '/../accessories.php') ? 'accessories.php' : '#accessories-management'; ?>" class="submenu-link <?php echo in_array($active_page, ['accessories', 'accessories_management']) ? 'active' : ''; ?>" <?php echo !file_exists(__DIR__ . '/../accessories.php') ? 'onclick="handleMenuClick(event, \'Accessories Management\')"' : ''; ?>>
                        <span>Accessories Management</span>
                    </a>
                </li>
                <li>
                    <a href="components.php" class="submenu-link <?php echo in_array($active_page, ['components', 'component_management']) ? 'active' : ''; ?>">
                        <span>Parts / Components</span>
                    </a>
                </li>
                <li>
                    <a href="asset_assignment.php" class="submenu-link <?php echo ($active_page === 'asset_assignment') ? 'active' : ''; ?>">
                        <span>Asset Assignment</span>
                    </a>
                </li>
                <li>
                    <a href="#asset-transfer" class="submenu-link <?php echo ($active_page === 'asset_transfer') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Asset Transfer')">
                        <span>Asset Transfer</span>
                    </a>
                </li>
                <li>
                    <a href="asset_return.php" class="submenu-link <?php echo ($active_page === 'asset_return') ? 'active' : ''; ?>">
                        <span>Asset Return</span>
                    </a>
                </li>
                <li>
                    <a href="#asset-repair" class="submenu-link <?php echo ($active_page === 'asset_repair') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Asset Repair')">
                        <span>Asset Repair</span>
                    </a>
                </li>
                <li>
                    <a href="#software-licenses" class="submenu-link <?php echo in_array($active_page, ['software', 'software_licenses']) ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Software & Licenses')">
                        <span>Software Licenses</span>
                    </a>
                </li>
                <li>
                    <a href="#asset-barcode" class="submenu-link <?php echo ($active_page === 'asset_barcode') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Asset Label & Barcode Management')">
                        <span>Asset Label & Barcode</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Inventory Stock (Single Item) -->
        <li class="nav-item">
            <a href="#inventory" class="nav-link <?php echo ($active_page === 'inventory') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Inventory Stock')">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </span>
                <span class="nav-text">Inventory Stock</span>
            </a>
        </li>

        <!-- DROPDOWN 2: Support Tickets -->
        <li class="nav-item nav-item-dropdown <?php echo $is_tickets_open ? 'open' : ''; ?>">
            <a href="javascript:void(0)" class="nav-link dropdown-toggle <?php echo $is_tickets_open ? 'active' : ''; ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </span>
                <span class="nav-text">Support Tickets</span>
                <span class="dropdown-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
            <ul class="submenu">
                <li>
                    <a href="#tickets-all" class="submenu-link <?php echo in_array($active_page, ['tickets', 'tickets_all']) ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'All Tickets')">
                        <span>All Tickets</span>
                    </a>
                </li>
                <li>
                    <a href="#tickets-open" class="submenu-link <?php echo ($active_page === 'tickets_open') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Open Tickets')">
                        <span>Open / Pending</span>
                    </a>
                </li>
                <li>
                    <a href="#tickets-progress" class="submenu-link <?php echo ($active_page === 'tickets_progress') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'In Progress Tickets')">
                        <span>In Progress</span>
                    </a>
                </li>
                <li>
                    <a href="#tickets-resolved" class="submenu-link <?php echo ($active_page === 'tickets_resolved') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Resolved Tickets')">
                        <span>Closed / Resolved</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Maintenance & AMC (Single Item) -->
        <li class="nav-item">
            <a href="#maintenance" class="nav-link <?php echo ($active_page === 'maintenance') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Maintenance & AMC')">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                </span>
                <span class="nav-text">Maintenance & AMC</span>
            </a>
        </li>

        <!-- DROPDOWN 3: Reports & Analytics -->
        <li class="nav-item nav-item-dropdown <?php echo $is_reports_open ? 'open' : ''; ?>">
            <a href="javascript:void(0)" class="nav-link dropdown-toggle <?php echo $is_reports_open ? 'active' : ''; ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"></line>
                        <line x1="12" y1="20" x2="12" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="14"></line>
                    </svg>
                </span>
                <span class="nav-text">Reports & Analytics</span>
                <span class="dropdown-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
            <ul class="submenu">
                <li>
                    <a href="#reports-audit" class="submenu-link <?php echo ($active_page === 'reports_audit') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Asset Audit Report')">
                        <span>Asset Audit</span>
                    </a>
                </li>
                <li>
                    <a href="#reports-sla" class="submenu-link <?php echo ($active_page === 'reports_sla') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Helpdesk SLA Performance')">
                        <span>SLA Performance</span>
                    </a>
                </li>
                <li>
                    <a href="#reports-warranty" class="submenu-link <?php echo ($active_page === 'reports_warranty') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Warranty & AMC Expiry')">
                        <span>Warranty Expiry</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Settings (Single Item) -->
        <li class="nav-item">
            <a href="#settings" class="nav-link <?php echo ($active_page === 'settings') ? 'active' : ''; ?>" onclick="handleMenuClick(event, 'Portal Settings')">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </span>
                <span class="nav-text">Settings</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer User Profile -->
    <?php
    $sidebar_name = $_SESSION['user_name'] ?? 'Abhishek Sharma';
    $sidebar_role = $_SESSION['user_role'] ?? 'IT Administrator';
    $sidebar_initials = '';
    foreach (explode(' ', trim($sidebar_name)) as $w) {
        if (!empty($w)) $sidebar_initials .= strtoupper($w[0]);
        if (strlen($sidebar_initials) >= 2) break;
    }
    if (empty($sidebar_initials)) $sidebar_initials = 'IT';
    ?>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo htmlspecialchars($sidebar_initials); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($sidebar_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($sidebar_role); ?></div>
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
