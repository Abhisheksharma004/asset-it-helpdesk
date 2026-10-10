<?php
/**
 * Global Employee Top Sticky Header Component
 * Tailored specifically for Employee Self-Service Workspace.
 */
$emp_topbar_name = $activeEmployee['name'] ?? ($_SESSION['user_name'] ?? 'Abhishek Sharma');
$emp_topbar_code = $activeEmployee['code'] ?? 'EMP-1004';
$emp_topbar_role = $activeEmployee['designation'] ?? 'Staff Member';

$emp_initials = '';
foreach (explode(' ', trim($emp_topbar_name)) as $w) {
    if (!empty($w)) $emp_initials .= strtoupper($w[0]);
    if (strlen($emp_initials) >= 2) break;
}
if (empty($emp_initials)) $emp_initials = 'EM';
?>
<!-- ==================== MAIN CONTENT WRAPPER ==================== -->
<div class="main-wrapper" id="mainWrapper">
    
    <!-- Top Sticky Header -->
    <header class="top-header">
        
        <div class="header-left">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <div class="header-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="globalEmployeeSearchInput" placeholder="Search my allocated devices, serial numbers, tickets..." oninput="const tb = document.getElementById('empItemSearchInput'); if(tb){ tb.value = this.value; tb.dispatchEvent(new Event('input')); }">
            </div>
        </div>

        <div class="header-right">
            
            <!-- Quick Action: Raise Ticket -->
            <button class="quick-action-btn" onclick="openEmployeeTicketModal()" style="display: flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Raise Ticket
            </button>

            <!-- IT Helpdesk Hotline Pill -->
            <div style="display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid var(--border-color); padding: 5px 12px; border-radius: 20px; font-size: 12px; color: var(--navy-primary); font-weight: 600;">
                <span style="color: #059669; font-size: 10px;">●</span> IT Hotline: Ext 204
            </div>

            <!-- Notifications -->
            <button class="icon-btn" title="IT Notifications" onclick="if(typeof showToast === 'function') showToast('All assigned devices are healthy. No pending recalls.', 'info');">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="badge-dot" style="background: #10b981;"></span>
            </button>

            <!-- Employee User Pill -->
            <div class="user-pill" onclick="if(typeof showToast === 'function') showToast('Logged in as <?php echo htmlspecialchars($emp_topbar_name); ?> (<?php echo htmlspecialchars($emp_topbar_code); ?>) • Employee Portal', 'info');">
                <div class="avatar" style="background: var(--cyan-primary);"><?php echo htmlspecialchars($emp_initials); ?></div>
                <div style="display: flex; flex-direction: column; text-align: left; line-height: 1.2;">
                    <span class="user-info" style="font-size: 12.5px;"><?php echo htmlspecialchars($emp_topbar_name); ?></span>
                    <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600;"><?php echo htmlspecialchars($emp_topbar_code); ?> • Staff</span>
                </div>
            </div>

        </div>

    </header>
