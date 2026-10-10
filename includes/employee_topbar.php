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

            <!-- Change Password Action -->
            <button class="icon-btn" title="Change Account Password" onclick="if(typeof openChangePasswordModal === 'function') openChangePasswordModal();" style="border-radius: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </button>

            <!-- Employee User Pill -->
            <div class="user-pill" onclick="window.location.href='employee_profile.php';" title="Click to view full employee profile & security" style="cursor: pointer;">
                <div class="avatar" style="background: var(--cyan-primary);"><?php echo htmlspecialchars($emp_initials); ?></div>
                <div style="display: flex; flex-direction: column; text-align: left; line-height: 1.2;">
                    <span class="user-info" style="font-size: 12.5px;"><?php echo htmlspecialchars($emp_topbar_name); ?></span>
                    <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600;"><?php echo htmlspecialchars($emp_topbar_code); ?> • Staff</span>
                </div>
            </div>

        </div>

    </header>

<?php
// Resolve live assigned assets & accessories strictly for the logged-in employee
$topbar_device_options = [];
$topbar_seen_tags = [];

$t_emp_id    = $activeEmployee['id'] ?? ($_SESSION['user_id'] ?? null);
$t_emp_email = $activeEmployee['email'] ?? ($_SESSION['user_email'] ?? '');
$t_emp_code  = $activeEmployee['code'] ?? ($_SESSION['user_username'] ?? '');
$t_emp_name  = $activeEmployee['name'] ?? ($_SESSION['user_name'] ?? '');

if (!isset($conn) || $conn === false) {
    @require_once __DIR__ . '/../config/db.php';
}

if (isset($conn) && $conn !== false && (!empty($t_emp_id) || !empty($t_emp_email) || !empty($t_emp_code))) {
    // 1. Fetch from asset_assignments (Primary asset, bundled assets_json, accessories_json)
    $t_sql = "SELECT a.asset_tag, a.asset_name, a.category, a.assets_json, a.accessories_json 
              FROM asset_assignments a 
              WHERE (a.employee_id = ? OR LOWER(a.employee_email) = LOWER(?) OR LOWER(a.emp_code) = LOWER(?))
                AND a.custody_status = 'Active'
              ORDER BY a.id DESC";
    $t_stmt = sqlsrv_query($conn, $t_sql, [$t_emp_id, $t_emp_email, $t_emp_code]);
    if ($t_stmt) {
        while ($r = sqlsrv_fetch_array($t_stmt, SQLSRV_FETCH_ASSOC)) {
            // Primary Hardware Asset
            if (!empty($r['asset_tag']) && !in_array($r['asset_tag'], $topbar_seen_tags)) {
                $topbar_seen_tags[] = $r['asset_tag'];
                $topbar_device_options[] = [
                    'val'   => $r['asset_tag'] . ' - ' . ($r['asset_name'] ?: 'Corporate Device'),
                    'label' => $r['asset_tag'] . ' — ' . ($r['asset_name'] ?: 'Corporate Device') . ' (Asset)'
                ];
            }
            // Bundled Assets from assets_json
            if (!empty($r['assets_json'])) {
                $baList = json_decode($r['assets_json'], true);
                if (is_array($baList)) {
                    foreach ($baList as $ba) {
                        $bTag = $ba['tag'] ?? '';
                        if (!empty($bTag) && !in_array($bTag, $topbar_seen_tags)) {
                            $topbar_seen_tags[] = $bTag;
                            $bName = $ba['name'] ?? 'Corporate Hardware';
                            $topbar_device_options[] = [
                                'val'   => $bTag . ' - ' . $bName,
                                'label' => $bTag . ' — ' . $bName . ' (Asset)'
                            ];
                        }
                    }
                }
            }
            // Bundled Accessories from accessories_json
            if (!empty($r['accessories_json'])) {
                $accList = json_decode($r['accessories_json'], true);
                if (is_array($accList)) {
                    foreach ($accList as $acc) {
                        $accTag = !empty($acc['sku']) ? $acc['sku'] : ('ACC-' . ($acc['id'] ?? uniqid()));
                        if (!in_array($accTag, $topbar_seen_tags)) {
                            $topbar_seen_tags[] = $accTag;
                            $accName = $acc['name'] ?? 'Workstation Accessory';
                            $topbar_device_options[] = [
                                'val'   => $accTag . ' - ' . $accName,
                                'label' => $accTag . ' — ' . $accName . ' (Accessory)'
                            ];
                        }
                    }
                }
            }
        }
        sqlsrv_free_stmt($t_stmt);
    }

    // 2. Direct assignments from assets table where assigned_to matches employee
    $t_astSql = "SELECT ast.tag, ast.name FROM assets ast WHERE (LOWER(ast.assigned_to) = LOWER(?) OR LOWER(ast.assigned_to) = LOWER(?)) AND ast.status = 'In Use'";
    $t_astStmt = sqlsrv_query($conn, $t_astSql, [$t_emp_name, $t_emp_code]);
    if ($t_astStmt) {
        while ($ast = sqlsrv_fetch_array($t_astStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($ast['tag']) && !in_array($ast['tag'], $topbar_seen_tags)) {
                $topbar_seen_tags[] = $ast['tag'];
                $topbar_device_options[] = [
                    'val'   => $ast['tag'] . ' - ' . ($ast['name'] ?: 'Corporate Device'),
                    'label' => $ast['tag'] . ' — ' . ($ast['name'] ?: 'Corporate Device') . ' (Asset)'
                ];
            }
        }
        sqlsrv_free_stmt($t_astStmt);
    }
}
?>

<!-- =========================================================================
     GLOBAL MODAL: RAISE IT SUPPORT TICKET (AVAILABLE ON ALL EMPLOYEE PAGES)
     ========================================================================= -->
<div class="modal-overlay" id="employeeTicketModal" style="display: none; z-index: 9999;">
    <div class="modal-box" style="max-width: 540px; border-top: 4px solid var(--navy-primary);">
        <div class="modal-header" style="padding: 18px 24px 14px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(0, 147, 167, 0.12); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16.5px; font-weight: 700; color: var(--navy-primary);">
                        Raise IT Support Ticket
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                        Submit an issue or technical assistance request to the IT Service Desk.
                    </p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('employeeTicketModal')">&times;</button>
        </div>
        <form id="employeeTicketForm">
            <div class="modal-body" style="padding: 20px 24px; max-height: 75vh; overflow-y: auto;">
                <!-- 1. Issue Subject -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Issue Subject *</label>
                    <input type="text" class="modal-input" id="ticketSubjectInput" placeholder="Brief summary of the problem..." required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                </div>

                <!-- 2. Detailed Description -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Detailed Description *</label>
                    <textarea class="modal-textarea" id="ticketDescTextarea" placeholder="Please describe what happened, any error codes, and steps already tried..." rows="4" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px; box-sizing: border-box; outline: none; resize: vertical;"></textarea>
                </div>

                <!-- 3 & 4. Urgency Level and Affected Asset in a single row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Urgency Level *</label>
                        <select class="modal-select" id="ticketUrgencySelect" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Affected Asset / Device</label>
                        <select class="modal-select" id="ticketAssetSelect" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                            <option value="">Select Assigned Asset or Accessory</option>
                            <?php if (!empty($topbar_device_options)): ?>
                                <?php foreach ($topbar_device_options as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt['val']); ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No assets or accessories assigned to your profile</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- 5. Incident Date & Time -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Incident Date & Time *</label>
                    <input type="datetime-local" class="modal-input" id="ticketDateTimeInput" value="<?php echo date('Y-m-d\TH:i'); ?>" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13.5px; box-sizing: border-box; outline: none; background: #fff; color: var(--text-primary); font-family: inherit;">
                </div>

            </div>
            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="btn-secondary" onclick="closeModal('employeeTicketModal')">Cancel</button>
                <button type="submit" class="btn-primary" style="padding: 9px 22px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Submit Ticket</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Global Employee Support Ticket Modal Controller
window.openEmployeeTicketModal = function (assetTag = '') {
    const modal = document.getElementById('employeeTicketModal');
    if (!modal) return;
    const assetSelect = document.getElementById('ticketAssetSelect');
    if (assetSelect && assetTag) {
        // Try exact match or match containing tag
        let matched = false;
        for (let i = 0; i < assetSelect.options.length; i++) {
            if (assetSelect.options[i].value.includes(assetTag) || assetSelect.options[i].text.includes(assetTag)) {
                assetSelect.selectedIndex = i;
                matched = true;
                break;
            }
        }
        if (!matched && assetTag) {
            assetSelect.value = assetTag;
        }
    }

    // Ensure datetime input has default current timestamp if blank
    const dtInput = document.getElementById('ticketDateTimeInput');
    if (dtInput && !dtInput.value) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        dtInput.value = now.toISOString().slice(0, 16);
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
};

// Global Alias for consistency
window.openNewTicketModal = function (assetTag = '') {
    window.openEmployeeTicketModal(assetTag);
};

if (typeof window.closeModal !== 'function') {
    window.closeModal = function(modalId) {
        const m = document.getElementById(modalId);
        if (m) {
            m.style.display = 'none';
            document.body.style.overflow = '';
        }
    };
}

// Background click and ESC key listeners
document.addEventListener('DOMContentLoaded', function () {
    const tktModal = document.getElementById('employeeTicketModal');
    if (tktModal) {
        tktModal.addEventListener('click', function (e) {
            if (e.target === tktModal) {
                closeModal('employeeTicketModal');
            }
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const m = document.getElementById('employeeTicketModal');
            if (m && m.style.display === 'flex') {
                closeModal('employeeTicketModal');
            }
        }
    });
});
</script>
