<?php
/**
 * Global Top Sticky Header Component
 * Can be reused across any portal page.
 */
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
                <input type="text" id="globalSearchInput" placeholder="Search assets, serial numbers, tickets...">
            </div>
        </div>

        <div class="header-right">
            
            <button class="quick-action-btn" id="openTicketModalBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                New Ticket
            </button>

            <button class="icon-btn" title="Notifications" onclick="showToast('You have 3 unread IT alerts', 'info')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="badge-dot"></span>
            </button>

            <?php
            $topbar_name = $_SESSION['user_name'] ?? 'Abhishek Sharma';
            $topbar_role = $_SESSION['user_role'] ?? 'IT Administrator';
            $topbar_initials = '';
            foreach (explode(' ', trim($topbar_name)) as $w) {
                if (!empty($w)) $topbar_initials .= strtoupper($w[0]);
                if (strlen($topbar_initials) >= 2) break;
            }
            if (empty($topbar_initials)) $topbar_initials = 'IT';
            ?>
            <div class="user-pill" onclick="showToast('Logged in as <?php echo htmlspecialchars($topbar_name); ?> (<?php echo htmlspecialchars($topbar_role); ?>)', 'info')">
                <div class="avatar"><?php echo htmlspecialchars($topbar_initials); ?></div>
                <span class="user-info"><?php echo htmlspecialchars($topbar_name); ?></span>
            </div>

        </div>

    </header>
