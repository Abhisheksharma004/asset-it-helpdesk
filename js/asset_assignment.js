/**
 * Asset Assignment & Allocation Controller
 * VIROS IT Asset & Service Desk Portal
 */

(function () {
    'use strict';

    // In-memory assignment dataset
    let assignments = (typeof window !== 'undefined' && Array.isArray(window.INITIAL_ASSIGNMENTS))
        ? window.INITIAL_ASSIGNMENTS
        : [];

    let availableAssets = (typeof window !== 'undefined' && Array.isArray(window.AVAILABLE_ASSETS))
        ? window.AVAILABLE_ASSETS
        : [];

    let employeesList = (typeof window !== 'undefined' && Array.isArray(window.EMPLOYEES_LIST))
        ? window.EMPLOYEES_LIST
        : [];

    // Filter states
    let searchTerm = '';
    let selectedTab = 'all';
    let selectedDept = 'all';
    let selectedType = 'all';
    let selectedStatus = 'all';

    // Active Drawer State
    let activeAllocId = null;
    window.activeAllocId = null;

    // DOM Elements
    const tbody = document.getElementById('allocationsTbody');
    const searchInput = document.getElementById('allocSearchInput');
    const deptFilter = document.getElementById('allocDeptFilter');
    const typeFilter = document.getElementById('allocTypeFilter');
    const statusFilter = document.getElementById('allocStatusFilter');
    const resetFiltersBtn = document.getElementById('resetAllocFiltersBtn');
    const tableCountText = document.getElementById('allocTableCountText');
    const selectAllCheckbox = document.getElementById('selectAllAlloc');
    const exportBtn = document.getElementById('exportAllocationsBtn');

    // Modals
    const assignModal = document.getElementById('assignAssetModal');
    const returnModal = document.getElementById('returnAssetModal');
    const transferModal = document.getElementById('transferAssetModal');
    const slipModal = document.getElementById('slipModal');

    // Drawer
    const assignmentDrawer = document.getElementById('assignmentDrawer');
    const drawerBackdrop = document.getElementById('assignmentDrawerBackdrop');
    const closeDrawerBtn = document.getElementById('closeAssignmentDrawerBtn');

    // =========================================================================
    // 1. Helper Functions
    // =========================================================================

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function getInitials(name) {
        if (!name) return 'ST';
        const parts = name.trim().split(' ');
        let initials = '';
        for (let p of parts) {
            if (p) initials += p[0].toUpperCase();
            if (initials.length >= 2) break;
        }
        return initials || 'ST';
    }

    const avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];

    function getAvatarColor(name, code, id) {
        if (typeof id === 'number' && id > 0) {
            return avatarColors[id % avatarColors.length];
        }
        const key = (name || '') + (code || '');
        let hash = 0;
        for (let i = 0; i < key.length; i++) {
            hash = (hash << 5) - hash + key.charCodeAt(i);
            hash |= 0;
        }
        return avatarColors[Math.abs(hash) % avatarColors.length];
    }

    function showNotification(msg, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else {
            alert(msg);
        }
    }

    function openModal(modal) {
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // =========================================================================
    // 2. Filtering & Rendering
    // =========================================================================

    function getFilteredAssignments() {
        return assignments.filter(item => {
            // Tab filter
            if (selectedTab === 'permanent' && item.allocation_type !== 'Permanent') return false;
            if (selectedTab === 'temporary' && item.allocation_type !== 'Temporary Loaner') return false;
            if (selectedTab === 'remote' && item.allocation_type !== 'Remote / WFH') return false;
            if (selectedTab === 'due_soon') {
                if (item.custody_status !== 'Due Soon' && item.custody_status !== 'Overdue') return false;
            }

            // Department filter
            if (selectedDept !== 'all' && item.department !== selectedDept) return false;

            // Allocation Type filter
            if (selectedType !== 'all' && item.allocation_type !== selectedType) return false;

            // Custody Status filter
            if (selectedStatus !== 'all' && item.custody_status !== selectedStatus) return false;

            // Search query
            if (searchTerm) {
                const q = searchTerm;
                const matchTag = item.asset_tag && item.asset_tag.toLowerCase().includes(q);
                const matchName = item.asset_name && item.asset_name.toLowerCase().includes(q);
                const matchSerial = item.serial && item.serial.toLowerCase().includes(q);
                const matchEmp = item.employee_name && item.employee_name.toLowerCase().includes(q);
                const matchCode = item.emp_code && item.emp_code.toLowerCase().includes(q);
                const matchDept = item.department && item.department.toLowerCase().includes(q);
                const matchDesig = item.designation && item.designation.toLowerCase().includes(q);
                if (!matchTag && !matchName && !matchSerial && !matchEmp && !matchCode && !matchDept && !matchDesig) {
                    return false;
                }
            }

            return true;
        });
    }

    function renderTable() {
        if (!tbody) return;
        const list = getFilteredAssignments();
        tbody.innerHTML = '';

        if (tableCountText) {
            tableCountText.textContent = `Showing ${list.length} of ${assignments.length} handover slips`;
        }

        if (list.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="table-empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.7;">📦</div>
                            <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">No Allocations Found</div>
                            <div style="font-size: 12.5px; margin-top: 4px;">No assignment records match your current search and filters.</div>
                            <button type="button" class="btn-secondary" style="margin-top: 14px; padding: 6px 14px;" onclick="document.getElementById('resetAllocFiltersBtn')?.click()">Reset Filters</button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(item => {
            let typePill = 'permanent';
            if (item.allocation_type === 'Temporary Loaner') {
                typePill = 'temporary';
            } else if (item.allocation_type === 'Remote / WFH') {
                typePill = 'remote';
            } else if (item.allocation_type === 'Project Deployment') {
                typePill = 'project';
            }

            let custodyBadge = 'status-active';
            let statusText = 'In Custody';
            if (item.custody_status === 'Due Soon') {
                custodyBadge = 'status-due-soon';
                statusText = 'Due Soon';
            } else if (item.custody_status === 'Overdue') {
                custodyBadge = 'status-overdue';
                statusText = 'Overdue';
            } else if (item.custody_status === 'Returned') {
                custodyBadge = 'status-returned';
                statusText = 'Returned';
            }

            const assetsList = Array.isArray(item.assets) ? item.assets : [];
            const accList = Array.isArray(item.accessories) ? item.accessories : [];
            const totalAssets = item.total_assets || assetsList.length;
            const totalAcc = item.total_accessories || accList.length;

            const tr = document.createElement('tr');
            tr.setAttribute('data-id', item.id);
            tr.innerHTML = `
                <td>
                    <input type="checkbox" class="custom-checkbox row-select-checkbox" value="${item.id}">
                </td>
                <td>
                    <div class="slip-cell">
                        <span class="slip-badge" onclick="openAssignmentDrawer(${item.id})">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            ${escapeHtml(item.slip_no || ('SLIP-2026-' + item.id))}
                        </span>
                        <div class="slip-date">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            ${escapeHtml(item.assigned_date)}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="custodian-cell">
                        <div class="custodian-avatar" style="background: ${getAvatarColor(item.employee_name, item.emp_code, item.employee_id || item.id)};">
                            ${escapeHtml(getInitials(item.employee_name))}
                        </div>
                        <div class="custodian-info">
                            <div class="custodian-name">
                                ${escapeHtml(item.employee_name)}
                                <span class="emp-code-badge">${escapeHtml(item.emp_code)}</span>
                            </div>
                            <div class="custodian-sub">
                                ${escapeHtml(item.designation)} • <strong>${escapeHtml(item.department)}</strong>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <div class="count-pill-wrap">
                            ${totalAssets > 0 ? `
                                <span class="kitna-badge asset-kitna-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                    <strong>${totalAssets} ${totalAssets === 1 ? 'Asset' : 'Assets'}</strong>
                                </span>
                            ` : `
                                <span class="kitna-badge zero-kitna-badge">0 Assets</span>
                            `}
                        </div>
                        <div class="chips-compact-list">
                            ${assetsList.slice(0, 2).map(a => `
                                <span class="chip-device" title="${escapeHtml(a.name)} (SN: ${escapeHtml(a.serial || '—')})">
                                    <span class="chip-tag">${escapeHtml(a.tag)}</span>
                                    <span class="chip-device-name">${escapeHtml(a.name)}</span>
                                </span>
                            `).join('')}
                            ${assetsList.length > 2 ? `
                                <span class="chip-more-count" onclick="openAssignmentDrawer(${item.id})">+${assetsList.length - 2} more device(s)</span>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <div class="count-pill-wrap">
                            ${totalAcc > 0 ? `
                                <span class="kitna-badge acc-kitna-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                    <strong>${totalAcc} ${totalAcc === 1 ? 'Accessory' : 'Accessories'}</strong>
                                </span>
                            ` : `
                                <span class="kitna-badge zero-kitna-badge">0 Accessories</span>
                            `}
                        </div>
                        <div class="chips-compact-list">
                            ${accList.slice(0, 2).map(ac => {
                                const acName = typeof ac === 'string' ? ac : (ac.name || 'Item');
                                const acQty = (typeof ac === 'object' && ac.qty) ? ac.qty : 1;
                                return `
                                    <span class="chip-acc-tag" title="${escapeHtml(acName)}">
                                        <span>${escapeHtml(acName)}</span>
                                        <span class="chip-acc-qty">${escapeHtml(acQty)}</span>
                                    </span>
                                `;
                            }).join('')}
                            ${accList.length > 2 ? `
                                <span class="chip-more-count" onclick="openAssignmentDrawer(${item.id})">+${accList.length - 2} more item(s)</span>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 3px; align-items: flex-start;">
                        <span class="alloc-pill ${typePill}">
                            ${escapeHtml(item.allocation_type)}
                        </span>
                        ${item.expected_return ? `
                            <span style="font-size: 11px; color: #ea580c; font-weight: 600;">
                                Exp: ${escapeHtml(item.expected_return)}
                            </span>
                        ` : `
                            <span style="font-size: 11px; color: var(--text-muted);">Permanent</span>
                        `}
                    </div>
                </td>
                <td>
                    <div class="custody-badge ${custodyBadge}">
                        <span class="dot"></span>
                        <span>${statusText}</span>
                    </div>
                    ${item.agreement_signed ? `
                        <div class="slip-signed-note">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Slip Verified</span>
                        </div>
                    ` : ''}
                </td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        <button type="button" class="action-icon-btn btn-view" title="View Custody Details & Handover Slip" onclick="openAssignmentDrawer(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <button type="button" class="action-icon-btn btn-return" title="Return Asset (Check-In to Inventory)" onclick="openReturnModal(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                        </button>
                        <button type="button" class="action-icon-btn btn-transfer" title="Transfer to Another Custodian" onclick="openTransferModal(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
                        </button>
                        <button type="button" class="action-icon-btn btn-qr" title="Print Handover Slip Receipt" onclick="openSlipModal(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updateKPIs() {
        let total = assignments.length;
        let permanent = 0;
        let temporary = 0;
        let remote = 0;
        let dueSoon = 0;
        let totalAssetsCount = 0;
        let totalAccCount = 0;

        assignments.forEach(a => {
            if (a.custody_status !== 'Returned') {
                totalAssetsCount += a.total_assets || (Array.isArray(a.assets) ? a.assets.length : 0);
                totalAccCount += a.total_accessories || (Array.isArray(a.accessories) ? a.accessories.length : 0);
            }

            if (a.allocation_type === 'Permanent') permanent++;
            else if (a.allocation_type === 'Temporary Loaner') temporary++;
            else if (a.allocation_type === 'Remote / WFH') remote++;

            if (a.custody_status === 'Due Soon' || a.custody_status === 'Overdue') {
                dueSoon++;
            }
        });

        // KPI elements
        const kpiTotal = document.getElementById('kpiTotalAllocated');
        const kpiDeployedAssets = document.getElementById('kpiDeployedAssets');
        const kpiDeployedAccessories = document.getElementById('kpiDeployedAccessories');
        const kpiTemporary = document.getElementById('kpiTemporaryAllocated');
        const kpiAvailable = document.getElementById('kpiAvailableAssets');

        if (kpiTotal) kpiTotal.textContent = total;
        if (kpiDeployedAssets) kpiDeployedAssets.textContent = totalAssetsCount;
        if (kpiDeployedAccessories) kpiDeployedAccessories.textContent = totalAccCount;
        if (kpiTemporary) kpiTemporary.textContent = temporary;
        if (kpiAvailable) kpiAvailable.textContent = availableAssets.length;

        // Tab count badges
        const tabAll = document.getElementById('tabCountAll');
        const tabPerm = document.getElementById('tabCountPermanent');
        const tabTemp = document.getElementById('tabCountTemporary');
        const tabRemote = document.getElementById('tabCountRemote');
        const tabDue = document.getElementById('tabCountDueSoon');

        if (tabAll) tabAll.textContent = total;
        if (tabPerm) tabPerm.textContent = permanent;
        if (tabTemp) tabTemp.textContent = temporary;
        if (tabRemote) tabRemote.textContent = remote;
        if (tabDue) tabDue.textContent = dueSoon;
    }

    // =========================================================================
    // 3. Slide-Over Drawer
    // =========================================================================

    window.openAssignmentDrawer = function (id) {
        const item = assignments.find(a => a.id === id);
        if (!item) return;

        activeAllocId = item.id;
        window.activeAllocId = item.id;

        const assetsList = Array.isArray(item.assets) ? item.assets : [];
        const accList = Array.isArray(item.accessories) ? item.accessories : [];

        // 1. Drawer Header: Employee Profile & Handover Slip Details
        const hAvatar = document.getElementById('drawerHeaderAvatar');
        if (hAvatar) {
            hAvatar.textContent = getInitials(item.employee_name);
            hAvatar.style.background = getAvatarColor(item.employee_name, item.emp_code, item.employee_id || item.id);
        }

        const hName = document.getElementById('drawerHeaderEmpName');
        if (hName) {
            hName.textContent = item.employee_name || 'Assigned Custodian';
        }

        const hEmpCode = document.getElementById('drawerHeaderEmpCode');
        if (hEmpCode) {
            hEmpCode.textContent = item.emp_code || 'EMP-STAFF';
        }

        const hMeta = document.getElementById('drawerHeaderEmpMeta');
        if (hMeta) {
            hMeta.textContent = `${item.designation || 'Staff'} • ${item.department || 'General'}`;
        }

        const hEmail = document.getElementById('drawerHeaderEmpEmail');
        if (hEmail) {
            const emailPart = item.employee_email ? item.employee_email : '';
            const locPart = item.location ? `• ${item.location}` : '';
            hEmail.textContent = [emailPart, locPart].filter(Boolean).join(' ') || '—';
        }

        const slipEl = document.getElementById('drawerSlipNo');
        if (slipEl) {
            slipEl.textContent = item.slip_no || ('SLIP-2026-' + item.id);
        }

        const statusEl = document.getElementById('drawerStatusBadge');
        if (statusEl) {
            let cls = 'status-active';
            let txt = 'In Custody';
            if (item.custody_status === 'Due Soon') {
                cls = 'status-due-soon';
                txt = 'Due Soon';
            } else if (item.custody_status === 'Overdue') {
                cls = 'status-overdue';
                txt = 'Overdue';
            } else if (item.custody_status === 'Returned') {
                cls = 'status-returned';
                txt = 'Returned';
            }
            statusEl.className = `custody-badge ${cls}`;
            statusEl.innerHTML = `<span class="dot"></span>${txt}`;
        }

        // Backward compatibility fallbacks
        const tagEl = document.getElementById('drawerTagBadge');
        if (tagEl) tagEl.textContent = assetsList.length > 0 ? assetsList[0].tag : 'SLIP';
        const nameEl = document.getElementById('drawerAssetName');
        if (nameEl) nameEl.textContent = assetsList.length > 0 ? assetsList.map(a => a.name).join(', ') : (item.asset_name || 'Equipment Allocation');
        const cAvatar = document.getElementById('drawerCustAvatar');
        if (cAvatar) {
            cAvatar.textContent = getInitials(item.employee_name);
            cAvatar.style.background = getAvatarColor(item.employee_name, item.emp_code, item.employee_id || item.id);
        }
        const cName = document.getElementById('drawerCustName');
        if (cName) cName.textContent = item.employee_name;
        const cMeta = document.getElementById('drawerCustMeta');
        if (cMeta) cMeta.textContent = `${item.designation} • ${item.department} (${item.emp_code})`;
        const cEmail = document.getElementById('drawerCustEmail');
        if (cEmail) cEmail.textContent = item.employee_email || '-';

        // Allocation Specs
        const setVal = (elId, val) => {
            const el = document.getElementById(elId);
            if (el) el.textContent = val || '—';
        };

        setVal('drawerAllocType', item.allocation_type);
        setVal('drawerAssignedDate', item.assigned_date);
        setVal('drawerExpectedReturn', item.expected_return || '— Permanent');
        setVal('drawerCondition', item.condition);
        setVal('drawerLocation', item.location);
        setVal('drawerHandoverBy', item.handover_by || 'IT Lead');
        setVal('drawerNotes', item.notes || 'Equipment verified and allocated in good physical condition.');

        // 2. Hardware Assets List (Asset Detail - NICHE)
        const assetsWrap = document.getElementById('drawerAssetsWrap');
        const countBadge = document.getElementById('drawerAssetCount');
        if (countBadge) countBadge.textContent = assetsList.length;
        if (assetsWrap) {
            if (assetsList.length === 0) {
                assetsWrap.innerHTML = '<div style="font-size: 12.5px; color: var(--text-muted); padding: 12px; background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 8px; text-align: center;">No hardware devices assigned under this slip.</div>';
            } else {
                assetsWrap.innerHTML = assetsList.map(a => `
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="display: flex; align-items: flex-start; gap: 12px; min-width: 0; flex: 1;">
                            <div style="min-width: 80px; text-align: center; padding: 6px 8px; border-radius: 6px; background: #e0f2fe; color: #0284c7; font-weight: 700; font-size: 11.5px; flex-shrink: 0; font-family: monospace; border: 1px solid #bae6fd;">
                                ${escapeHtml(a.tag || 'AST')}
                            </div>
                            <div style="min-width: 0; flex: 1;">
                                <div style="font-weight: 700; font-size: 13.5px; color: var(--navy-primary); line-height: 1.3;">${escapeHtml(a.name || 'Device')}</div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">
                                    ${a.brand ? `<strong>${escapeHtml(a.brand)}</strong> • ` : ''}
                                    ${escapeHtml(a.category || 'Hardware')} 
                                    ${a.model ? `(${escapeHtml(a.model)})` : ''}
                                </div>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 3px; font-family: monospace;">
                                    Serial No: <strong style="color: var(--text-primary);">${escapeHtml(a.serial || '—')}</strong>
                                </div>
                                ${a.specs ? `<div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 4px; background: #f8fafc; padding: 3px 8px; border-radius: 4px; display: inline-block;">${escapeHtml(a.specs)}</div>` : ''}
                            </div>
                        </div>
                        <div style="text-align: right; flex-shrink: 0;">
                            <span style="font-size: 11.5px; background: #f1f5f9; color: var(--navy-primary); padding: 3px 9px; border-radius: 12px; font-weight: 600; border: 1px solid #e2e8f0; display: inline-block;">
                                ${escapeHtml(a.condition || 'Good')}
                            </span>
                        </div>
                    </div>
                `).join('');
            }
        }

        // 3. Bundled Accessories List (Accessories Detail - NICHE)
        const accWrap = document.getElementById('drawerAccessoriesWrap');
        const accCountBadge = document.getElementById('drawerAccCount');
        if (accCountBadge) accCountBadge.textContent = accList.length;
        if (accWrap) {
            if (accList.length === 0) {
                accWrap.innerHTML = '<div style="font-size: 12.5px; color: var(--text-muted); padding: 12px; background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 8px; text-align: center; width: 100%;">No accessories assigned under this slip.</div>';
            } else {
                accWrap.innerHTML = accList.map(ac => {
                    const acName = typeof ac === 'string' ? ac : (ac.name || 'Item');
                    const acQty = (typeof ac === 'object' && ac.qty) ? ac.qty : 1;
                    const acCategory = (typeof ac === 'object' && ac.category) ? ac.category : '';
                    return `
                        <div class="accessory-chip checked" style="cursor: default; padding: 7px 14px; display: inline-flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span style="font-size: 12.5px; font-weight: 600; color: #166534;">
                                ${escapeHtml(acName)} 
                                <strong style="background: #dcfce7; padding: 2px 7px; border-radius: 10px; color: #15803d; font-size: 11px; margin-left: 3px;">x${escapeHtml(acQty)}</strong>
                            </span>
                            ${acCategory ? `<span style="font-size: 11px; color: #15803d; opacity: 0.85;">(${escapeHtml(acCategory)})</span>` : ''}
                        </div>
                    `;
                }).join('');
            }
        }

        // Device Specs Tab (Hardware specs for all assigned devices)
        const primary = assetsList[0] || {};
        setVal('specTag', primary.tag || item.asset_tag || '—');
        setVal('specCategory', primary.category || item.category || '—');
        setVal('specBrand', primary.brand || item.brand || '—');
        setVal('specModel', primary.model || item.model || '—');
        setVal('specSerial', primary.serial || item.serial || '—');
        setVal('specDetails', primary.specs || item.specs || 'N/A');

        const specsWrap = document.getElementById('drawerSpecsListWrap');
        if (specsWrap) {
            if (assetsList.length === 0) {
                specsWrap.innerHTML = '<div style="font-size: 12.5px; color: var(--text-muted); padding: 12px; background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 8px; text-align: center;">No hardware device specs available.</div>';
            } else {
                specsWrap.innerHTML = assetsList.map(a => `
                    <div class="drawer-section" style="margin-bottom: 16px;">
                        <div class="drawer-section-title" style="display: flex; align-items: center; justify-content: space-between;">
                            <span>${escapeHtml(a.name || 'Hardware Device')}</span>
                            <span class="asset-tag-badge" style="font-size: 11px; font-family: monospace;">${escapeHtml(a.tag || 'AST')}</span>
                        </div>
                        <div class="drawer-spec-grid">
                            <div class="drawer-spec-item">
                                <div class="label">Asset Tag</div>
                                <div class="value" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">${escapeHtml(a.tag || '—')}</div>
                            </div>
                            <div class="drawer-spec-item">
                                <div class="label">Category</div>
                                <div class="value">${escapeHtml(a.category || '—')}</div>
                            </div>
                            <div class="drawer-spec-item">
                                <div class="label">Brand</div>
                                <div class="value">${escapeHtml(a.brand || '—')}</div>
                            </div>
                            <div class="drawer-spec-item">
                                <div class="label">Model</div>
                                <div class="value">${escapeHtml(a.model || '—')}</div>
                            </div>
                            <div class="drawer-spec-item">
                                <div class="label">Serial Number</div>
                                <div class="value" style="font-family: monospace;">${escapeHtml(a.serial || '—')}</div>
                            </div>
                            <div class="drawer-spec-item">
                                <div class="label">Hardware Specs</div>
                                <div class="value">${escapeHtml(a.specs || 'N/A')}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Custody Timeline (Dynamic based on real assignment lifecycle)
        const timeWrap = document.getElementById('drawerTimelineWrap');
        if (timeWrap) {
            let timelineHtml = '';

            // Event 1: If returned, show return record
            if (item.custody_status === 'Returned' || item.return_date) {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: #10b981;"></div>
                        <div class="timeline-date">${escapeHtml(item.return_date || item.assigned_date)}</div>
                        <div class="timeline-content">
                            <div class="timeline-title" style="color: #059669; font-weight: 700;">Equipment Returned & Custody Released</div>
                            <div class="timeline-desc">Assets returned to inventory in ${escapeHtml(item.return_condition || item.condition || 'Good')} condition. ${escapeHtml(item.return_notes || 'Returned back to depot inventory.')}</div>
                        </div>
                    </div>
                `;
            }

            // Event 2: Handover / Deployment Event
            const totalAssignedText = `${item.total_assets || assetsList.length} Asset(s) and ${item.total_accessories || accList.length} Accessory(ies)`;
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot green"></div>
                    <div class="timeline-date">${escapeHtml(item.assigned_date)}</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Handover & Custody Assigned</div>
                        <div class="timeline-desc">
                            Issued to <strong>${escapeHtml(item.employee_name)}</strong> (${escapeHtml(item.emp_code || 'Employee')}) under <strong>${escapeHtml(item.allocation_type || 'Permanent')}</strong> terms. Handover slip <code>${escapeHtml(item.slip_no)}</code> verified by ${escapeHtml(item.handover_by || 'IT Department')}. (${totalAssignedText})
                        </div>
                    </div>
                </div>
            `;

            // Event 3: Expected return if temporary loaner
            if (item.allocation_type === 'Temporary Loaner' && item.expected_return && item.custody_status !== 'Returned') {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: #f59e0b;"></div>
                        <div class="timeline-date">${escapeHtml(item.expected_return)}</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Expected Custody Return Due</div>
                            <div class="timeline-desc">Scheduled return date for temporary equipment loaner allocation.</div>
                        </div>
                    </div>
                `;
            }

            // Event 4: Slip Registration / Creation timestamp
            const slipCreatedDate = item.created_date || item.assigned_date;
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot" style="background: #0284c7;"></div>
                    <div class="timeline-date">${escapeHtml(slipCreatedDate)}</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Handover Slip Generated</div>
                        <div class="timeline-desc">Official handover documentation registered in system for ${escapeHtml(item.department || 'General')} department.</div>
                    </div>
                </div>
            `;

            timeWrap.innerHTML = timelineHtml;
        }

        // Switch to default tab
        switchDrawerTab('custody_overview');

        // Open Drawer
        if (assignmentDrawer) assignmentDrawer.classList.add('open');
        if (drawerBackdrop) drawerBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeAssignmentDrawer = function () {
        if (assignmentDrawer) assignmentDrawer.classList.remove('open');
        if (drawerBackdrop) drawerBackdrop.classList.remove('open');
        document.body.style.overflow = '';
        activeAllocId = null;
        window.activeAllocId = null;
    };

    function switchDrawerTab(tabName) {
        if (!assignmentDrawer) return;
        assignmentDrawer.querySelectorAll('.drawer-tab').forEach(b => {
            b.classList.toggle('active', b.dataset.tab === tabName);
        });
        assignmentDrawer.querySelectorAll('.drawer-tab-pane').forEach(p => {
            p.classList.toggle('active', p.id === 'pane_' + tabName);
        });
    }

    // =========================================================================
    // 4. Modals (Assign, Return, Transfer, Slip)
    // =========================================================================

    // --- Assign Modal ---
    window.openAssignModal = function () {
        const form = document.getElementById('assignAssetForm');
        if (form) form.reset();

        const empPreview = document.getElementById('empPreviewBox');
        if (empPreview) empPreview.style.display = 'none';

        const assetPreview = document.getElementById('assetPreviewBox');
        if (assetPreview) assetPreview.style.display = 'none';

        const returnGrp = document.getElementById('expectedReturnGroup');
        if (returnGrp) returnGrp.style.display = 'none';

        const dateInput = document.getElementById('assignHandoverDate');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
        }

        batchSelectedAssets = [];
        renderBatchAssets();
        batchSelectedAccessories = [];
        renderBatchAccessories();

        switchAssignModalTab('employee');
        openModal(assignModal);
    };

    // =========================================================================
    // MODAL TABS SWITCHING (Exact design pattern from assets.js)
    // =========================================================================
    window.switchAssignModalTab = function (tabName) {
        document.querySelectorAll('#assignAssetModal .modal-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        document.querySelectorAll('#assignAssetModal .modal-tab-pane').forEach(pane => {
            pane.classList.toggle('active', pane.id === 'modal_pane_' + tabName);
        });
    };

    document.querySelectorAll('#assignAssetModal .modal-tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.dataset.tab) {
                switchAssignModalTab(this.dataset.tab);
            }
        });
    });

    // =========================================================================
    // DYNAMIC MULTI-ACCESSORIES BATCH SYSTEM (SAME AS ASSET DESIGN)
    // =========================================================================
    let batchSelectedAccessories = [];

    function renderBatchAccessories() {
        const listEl = document.getElementById('batchAccessoriesList');
        const badgeEl = document.getElementById('selectedAccCountBadge');
        const tabAccBadge = document.getElementById('assignTabAccBadge') || document.getElementById('tabAccCountBadge');

        if (!listEl) return;

        if (batchSelectedAccessories.length === 0) {
            listEl.innerHTML = `
                <div class="batch-asset-empty" id="accEmptyState">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    <span>No accessories added yet. Pick an accessory above and click <strong>+ Add</strong>.</span>
                </div>
            `;
            if (badgeEl) badgeEl.textContent = '0 Items';
            if (tabAccBadge) tabAccBadge.style.display = 'none';
            return;
        }

        if (badgeEl) {
            badgeEl.textContent = `${batchSelectedAccessories.length} Item${batchSelectedAccessories.length > 1 ? 's' : ''}`;
        }
        if (tabAccBadge) {
            tabAccBadge.style.display = 'inline-block';
            tabAccBadge.textContent = batchSelectedAccessories.length;
        }

        listEl.innerHTML = `
            <div class="batch-table-container">
                <table class="batch-modal-table">
                    <thead>
                        <tr>
                            <th style="width: 68%;">Accessory / Item</th>
                            <th style="width: 22%;">Quantity</th>
                            <th style="width: 10%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${batchSelectedAccessories.map(item => `
                            <tr data-acc-id="${item.id}">
                                <td>
                                    <div class="tbl-asset-cell">
                                        <div class="batch-asset-icon" style="background: #e0f2fe; color: #0284c7;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                        </div>
                                        <div class="tbl-asset-info">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span class="asset-tag-badge" style="background: #e0f2fe; color: #0369a1; border-color: #bae6fd;">${escapeHtml(item.sku || 'ACC')}</span>
                                                <span class="tbl-asset-name">${escapeHtml(item.name)}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <select class="batch-asset-condition-select" data-acc-id="${item.id}" title="Quantity">
                                        <option value="1" ${item.qty === 1 ? 'selected' : ''}>Qty: 1</option>
                                        <option value="2" ${item.qty === 2 ? 'selected' : ''}>Qty: 2</option>
                                        <option value="3" ${item.qty === 3 ? 'selected' : ''}>Qty: 3</option>
                                        <option value="4" ${item.qty === 4 ? 'selected' : ''}>Qty: 4</option>
                                        <option value="5" ${item.qty === 5 ? 'selected' : ''}>Qty: 5</option>
                                    </select>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-remove-batch-asset" data-acc-id="${item.id}" title="Remove accessory">&times;</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        // Attach quantity change handlers
        listEl.querySelectorAll('.batch-asset-condition-select').forEach(sel => {
            sel.addEventListener('change', function () {
                const aId = parseInt(this.dataset.accId, 10);
                const target = batchSelectedAccessories.find(a => a.id === aId);
                if (target) target.qty = parseInt(this.value, 10) || 1;
            });
        });

        // Attach remove buttons
        listEl.querySelectorAll('.btn-remove-batch-asset').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const aId = parseInt(this.dataset.accId, 10);
                removeAccessoryFromBatch(aId);
            });
        });
    }

    function addAccessoryToBatch(accVal) {
        if (!accVal) return;
        const select = document.getElementById('assignAccessorySelect');
        const opt = select ? (select.querySelector(`option[value="${accVal}"]`) || select.selectedOptions[0]) : null;

        const idNum = parseInt(accVal, 10);
        const name = (opt && opt.dataset.name) ? opt.dataset.name : (opt ? opt.textContent.trim() : String(accVal).trim());
        const sku = (opt && opt.dataset.sku) ? opt.dataset.sku : 'ACC';
        const category = (opt && opt.dataset.category) ? opt.dataset.category : 'Standard Peripheral';
        const inStock = (opt && opt.dataset.instock) ? parseInt(opt.dataset.instock, 10) : 1;

        if (batchSelectedAccessories.some(a => (idNum && a.id === idNum) || a.name.toLowerCase() === name.toLowerCase())) {
            showNotification(`"${name}" is already added to the accessories list.`, 'warning');
            return;
        }

        batchSelectedAccessories.push({
            id: idNum || (Date.now() + Math.floor(Math.random() * 100)),
            sku: sku,
            name: name,
            category: category,
            inStock: inStock,
            qty: 1
        });

        if (select) select.value = '';

        renderBatchAccessories();
    }

    function removeAccessoryFromBatch(id) {
        batchSelectedAccessories = batchSelectedAccessories.filter(a => a.id !== id);
        renderBatchAccessories();
    }

    // Attach Add Button and Change Handler
    const addAccBtn = document.getElementById('addAccessoryToBatchBtn');
    if (addAccBtn) {
        addAccBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const select = document.getElementById('assignAccessorySelect');
            if (select && select.value) {
                addAccessoryToBatch(select.value);
            } else {
                showNotification('Please choose an accessory from the dropdown first.', 'warning');
            }
        });
    }

    const assignAccessorySelect = document.getElementById('assignAccessorySelect');
    if (assignAccessorySelect) {
        assignAccessorySelect.addEventListener('change', function () {
            if (this.value) {
                addAccessoryToBatch(this.value);
            }
        });
    }

    // =========================================================================
    // MULTI-ASSET BATCH ALLOCATION SYSTEM
    // =========================================================================
    let batchSelectedAssets = [];

    function renderBatchAssets() {
        const listEl = document.getElementById('batchAssetsList');
        const badgeEl = document.getElementById('selectedAssetCountBadge');
        const tabBadge = document.getElementById('assignTabAssetBadge') || document.getElementById('tabAssetCountBadge');
        const submitBtn = document.getElementById('submitAssignBtn');

        if (!listEl) return;

        if (batchSelectedAssets.length === 0) {
            listEl.innerHTML = `
                <div class="batch-asset-empty" id="batchEmptyState">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    <span>No assets selected yet. Pick an asset above and click <strong>+ Add</strong>.</span>
                </div>
            `;
            if (badgeEl) badgeEl.textContent = '0 Devices';
            if (tabBadge) tabBadge.style.display = 'none';
            if (submitBtn) {
                submitBtn.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm & Complete Handover
                `;
            }
            return;
        }

        if (badgeEl) {
            badgeEl.textContent = `${batchSelectedAssets.length} Device${batchSelectedAssets.length > 1 ? 's' : ''}`;
        }
        if (tabBadge) {
            tabBadge.style.display = 'inline-block';
            tabBadge.textContent = batchSelectedAssets.length;
        }
        if (submitBtn) {
            submitBtn.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Confirm Handover (${batchSelectedAssets.length} Asset${batchSelectedAssets.length > 1 ? 's' : ''})
            `;
        }

        listEl.innerHTML = `
            <div class="batch-table-container">
                <table class="batch-modal-table">
                    <thead>
                        <tr>
                            <th style="width: 44%;">Asset Details</th>
                            <th style="width: 26%;">Serial Number</th>
                            <th style="width: 22%;">Condition</th>
                            <th style="width: 8%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${batchSelectedAssets.map(item => `
                            <tr data-asset-id="${item.id}">
                                <td>
                                    <div class="tbl-asset-cell">
                                        <div class="batch-asset-icon">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                        </div>
                                        <div class="tbl-asset-info">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span class="asset-tag-badge">${escapeHtml(item.tag)}</span>
                                                <span class="tbl-asset-name">${escapeHtml(item.name)}</span>
                                            </div>
                                            <span class="tbl-asset-category">${escapeHtml(item.category)}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="tbl-serial-text">${escapeHtml(item.serial || 'N/A')}</span>
                                </td>
                                <td>
                                    <select class="batch-asset-condition-select" data-id="${item.id}" title="Condition">
                                        <option value="Brand New" ${item.condition === 'Brand New' ? 'selected' : ''}>Brand New</option>
                                        <option value="Excellent" ${item.condition === 'Excellent' ? 'selected' : ''}>Excellent</option>
                                        <option value="Good" ${item.condition === 'Good' ? 'selected' : ''}>Good</option>
                                    </select>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-remove-batch-asset" data-id="${item.id}" title="Remove device">&times;</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        // Attach condition change handlers
        listEl.querySelectorAll('.batch-asset-condition-select').forEach(sel => {
            sel.addEventListener('change', function () {
                const aId = parseInt(this.dataset.id, 10);
                const target = batchSelectedAssets.find(a => a.id === aId);
                if (target) target.condition = this.value;
            });
        });

        // Attach remove buttons
        listEl.querySelectorAll('.btn-remove-batch-asset').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const aId = parseInt(this.dataset.id, 10);
                removeAssetFromBatch(aId);
            });
        });
    }

    function addAssetToBatch(assetId) {
        if (!assetId) return;
        const idNum = parseInt(assetId, 10);
        if (batchSelectedAssets.some(a => a.id === idNum)) {
            showNotification('This hardware asset is already added to the batch list.', 'warning');
            return;
        }

        const assetSelect = document.getElementById('assignAssetSelect');
        const opt = assetSelect ? assetSelect.querySelector(`option[value="${idNum}"]`) : null;
        if (!opt) return;

        batchSelectedAssets.push({
            id: idNum,
            tag: opt.dataset.tag || 'AST-' + idNum,
            name: opt.dataset.name || 'Hardware Device',
            category: opt.dataset.category || 'Hardware',
            brand: opt.dataset.brand || '',
            serial: opt.dataset.serial || 'N/A',
            condition: opt.dataset.condition || 'Brand New',
            specs: opt.dataset.specs || ''
        });

        // Reset dropdown
        if (assetSelect) assetSelect.value = '';

        renderBatchAssets();
    }

    function removeAssetFromBatch(assetId) {
        batchSelectedAssets = batchSelectedAssets.filter(a => a.id !== assetId);
        renderBatchAssets();
    }

    // Attach Add Button and Change Event for Asset Select
    const addAssetBtn = document.getElementById('addAssetToBatchBtn');
    if (addAssetBtn) {
        addAssetBtn.addEventListener('click', function () {
            const assetSelect = document.getElementById('assignAssetSelect');
            if (assetSelect && assetSelect.value) {
                addAssetToBatch(assetSelect.value);
            } else {
                showNotification('Please choose an available asset from the dropdown first.', 'warning');
            }
        });
    }

    const assignAssetSelect = document.getElementById('assignAssetSelect');
    if (assignAssetSelect) {
        assignAssetSelect.addEventListener('change', function () {
            if (this.value) {
                addAssetToBatch(this.value);
            }
        });
    }

    // Employee selection preview change
    const assignEmpSelect = document.getElementById('assignEmployeeSelect');
    if (assignEmpSelect) {
        assignEmpSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const previewBox = document.getElementById('empPreviewBox');
            if (this.value && opt && previewBox) {
                previewBox.style.display = 'block';
                const name = opt.dataset.name || '-';
                document.getElementById('empPreviewName').textContent = name;
                const prevAv = document.getElementById('empPreviewAvatar');
                if (prevAv) {
                    prevAv.textContent = getInitials(name);
                    prevAv.style.background = getAvatarColor(name, opt.dataset.code, parseInt(opt.value, 10));
                }
                document.getElementById('empPreviewMeta').textContent = `${opt.dataset.desig} • ${opt.dataset.dept} (${opt.dataset.code})`;
                document.getElementById('empPreviewEmail').textContent = opt.dataset.email || '';
            } else if (previewBox) {
                previewBox.style.display = 'none';
            }
        });
    }

    // Allocation type change toggle
    const assignTypeSelect = document.getElementById('assignAllocationType');
    if (assignTypeSelect) {
        assignTypeSelect.addEventListener('change', function () {
            const returnGrp = document.getElementById('expectedReturnGroup');
            if (returnGrp) {
                if (this.value === 'Temporary Loaner' || this.value === 'Project Deployment') {
                    returnGrp.style.display = 'block';
                } else {
                    returnGrp.style.display = 'none';
                }
            }
        });
    }

    // Accessory chips checkbox toggle
    document.querySelectorAll('.accessory-chip input[type="checkbox"]').forEach(chk => {
        chk.addEventListener('change', function () {
            const chip = this.closest('.accessory-chip');
            if (chip) {
                chip.classList.toggle('checked', this.checked);
            }
        });
    });

    // Handle Assign Form Submit (Batch Multi-Asset)
    const assignForm = document.getElementById('assignAssetForm');
    if (assignForm) {
        assignForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const empSelect = document.getElementById('assignEmployeeSelect');
            const empOpt = empSelect ? empSelect.options[empSelect.selectedIndex] : null;

            if (!empSelect || !empSelect.value) {
                switchAssignStep('employee');
                showNotification('Please select an employee / staff member.', 'danger');
                if (empSelect) empSelect.focus();
                return;
            }

            if (batchSelectedAssets.length === 0) {
                switchAssignStep('assets');
                showNotification('Please add at least one hardware asset to the allocation list.', 'danger');
                const assetSelect = document.getElementById('assignAssetSelect');
                if (assetSelect) assetSelect.focus();
                return;
            }

            const policyCheck = document.getElementById('assignPolicyCheck');
            if (policyCheck && !policyCheck.checked) {
                switchAssignStep('accessories');
                showNotification('Please accept the Custodian Policy Sign-Off checkbox before completing handover.', 'warning');
                policyCheck.focus();
                return;
            }

            const allocType = document.getElementById('assignAllocationType').value;
            const handoverDate = document.getElementById('assignHandoverDate').value;
            const expectedReturn = (allocType === 'Temporary Loaner' || allocType === 'Project Deployment')
                ? document.getElementById('assignExpectedReturn').value
                : null;
            const location = document.getElementById('assignLocation').value;
            const notes = document.getElementById('assignNotes').value.trim();

            const accessories = batchSelectedAccessories.length > 0 
                ? batchSelectedAccessories.map(a => a.qty > 1 ? `${a.name} (Qty: ${a.qty})` : a.name) 
                : ['Power Adapter & Cable'];

            const assetSelect = document.getElementById('assignAssetSelect');
            const submitBtn = document.getElementById('submitAssignBtn');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `Saving Allocation...`;
            }

            const payload = {
                action: 'assign',
                employee_id: parseInt(empSelect.value, 10) || null,
                employee_name: empOpt.dataset.name,
                emp_code: empOpt.dataset.code,
                employee_email: empOpt.dataset.email,
                department: empOpt.dataset.dept,
                designation: empOpt.dataset.desig,
                location: location,
                allocation_type: allocType,
                handover_date: handoverDate,
                expected_return: expectedReturn,
                notes: notes,
                agreement_signed: 1,
                assets: batchSelectedAssets.map(a => ({
                    id: a.id,
                    tag: a.tag,
                    name: a.name,
                    condition: a.condition || 'Brand New'
                })),
                accessories: batchSelectedAccessories.map(acc => ({
                    id: acc.id,
                    sku: acc.sku,
                    name: acc.name,
                    category: acc.category,
                    qty: acc.qty || 1
                }))
            };

            fetch('api/asset_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const rec = Array.isArray(res.data) ? res.data[0] : res.data;
                    assignments.unshift(rec);

                    if (Array.isArray(rec.assets)) {
                        rec.assets.forEach(ast => {
                            availableAssets = availableAssets.filter(a => a.id !== ast.id);
                            if (assetSelect) {
                                const selOpt = assetSelect.querySelector(`option[value="${ast.id}"]`);
                                if (selOpt) selOpt.remove();
                            }
                        });
                    }

                    batchSelectedAssets = [];
                    batchSelectedAccessories = [];
                    renderBatchAssets();
                    renderBatchAccessories();

                    closeModal(assignModal);
                    renderTable();
                    updateKPIs();

                    showNotification(res.message || `Successfully allocated equipment under slip ${rec.slip_no}!`, 'success');

                    const slipIdToOpen = res.new_id || rec.id;
                    if (slipIdToOpen) {
                        setTimeout(() => {
                            openSlipModal(slipIdToOpen);
                        }, 300);
                    }
                } else {
                    showNotification(res.message || 'Failed to complete assignment.', 'danger');
                }
            })
            .catch(err => {
                console.error('Assign error:', err);
                showNotification('Network/server error while saving assignment.', 'danger');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            });
        });
    }

    // --- Return Modal ---
    window.openReturnModal = function (id) {
        const item = assignments.find(a => a.id === id);
        if (!item) return;

        document.getElementById('returnAllocId').value = item.id;
        document.getElementById('returnTagText').textContent = item.asset_tag;
        document.getElementById('returnAssetNameText').textContent = item.asset_name;
        document.getElementById('returnEmpNameText').textContent = item.employee_name;
        document.getElementById('returnEmpCodeText').textContent = item.emp_code;

        const dateInput = document.getElementById('returnDate');
        if (dateInput) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }

        openModal(returnModal);
    };

    const returnForm = document.getElementById('returnAssetForm');
    if (returnForm) {
        returnForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = parseInt(document.getElementById('returnAllocId').value, 10);
            const item = assignments.find(a => a.id === id);
            if (!item) return;

            const retCondition = document.getElementById('returnCondition').value;
            const retShelf = document.getElementById('returnStorageLocation').value;
            const retDate = document.getElementById('returnDate') ? document.getElementById('returnDate').value : new Date().toISOString().split('T')[0];
            const retNotes = `Returned condition: ${retCondition}, placed in ${retShelf}.`;

            const retBtn = returnForm.querySelector('button[type="submit"]');
            if (retBtn) retBtn.disabled = true;

            fetch('api/asset_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'return',
                    alloc_id: id,
                    return_date: retDate,
                    return_condition: retCondition,
                    return_notes: retNotes
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    item.custody_status = 'Returned';
                    item.return_date = retDate;
                    item.condition = retCondition;
                    item.notes += ` [${retNotes}]`;

                    if (retCondition !== 'Damaged') {
                        availableAssets.push({
                            id: item.asset_id,
                            tag: item.asset_tag,
                            name: item.asset_name,
                            category: item.category,
                            brand: item.brand,
                            model: item.model,
                            serial: item.serial,
                            condition: retCondition,
                            location: retShelf,
                            specs: item.specs
                        });
                    }

                    closeModal(returnModal);
                    if (window.activeAllocId === id) {
                        closeAssignmentDrawer();
                    }

                    renderTable();
                    updateKPIs();
                    showNotification(res.message || `Asset ${item.asset_tag} checked-in back to inventory successfully!`, 'success');
                } else {
                    showNotification(res.message || 'Failed to process return.', 'danger');
                }
            })
            .catch(err => {
                console.error('Return error:', err);
                showNotification('Network error while returning asset.', 'danger');
            })
            .finally(() => {
                if (retBtn) retBtn.disabled = false;
            });
        });
    }

    // --- Transfer Modal ---
    window.openTransferModal = function (id) {
        const item = assignments.find(a => a.id === id);
        if (!item) return;

        document.getElementById('transferAllocId').value = item.id;
        document.getElementById('transferTagText').textContent = item.asset_tag;
        document.getElementById('transferAssetNameText').textContent = item.asset_name;
        document.getElementById('transferOldEmpText').textContent = `${item.employee_name} (${item.emp_code} - ${item.department})`;

        openModal(transferModal);
    };

    const transferForm = document.getElementById('transferAssetForm');
    if (transferForm) {
        transferForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = parseInt(document.getElementById('transferAllocId').value, 10);
            const item = assignments.find(a => a.id === id);
            if (!item) return;

            const newEmpSelect = document.getElementById('transferNewEmpSelect');
            const newEmpOpt = newEmpSelect.options[newEmpSelect.selectedIndex];
            if (!newEmpSelect.value || !newEmpOpt) {
                showNotification('Please select a new custodian employee.', 'danger');
                return;
            }

            const transferReason = document.getElementById('transferReason').value;
            const effDate = document.getElementById('transferEffectiveDate').value;
            const newDept = newEmpOpt.dataset.dept || item.department;

            const trBtn = transferForm.querySelector('button[type="submit"]');
            if (trBtn) trBtn.disabled = true;

            fetch('api/asset_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'transfer',
                    alloc_id: id,
                    new_employee_id: parseInt(newEmpSelect.value, 10) || null,
                    new_employee_name: newEmpOpt.dataset.name,
                    new_emp_code: newEmpOpt.dataset.code,
                    new_dept: newDept,
                    transfer_date: effDate,
                    transfer_notes: transferReason
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const oldName = item.employee_name;
                    item.custody_status = 'Transferred';
                    item.notes += ` [Custody transferred from ${oldName} to ${newEmpOpt.dataset.name} on ${effDate} for ${transferReason}].`;

                    assignments.unshift({
                        id: res.new_id || Date.now(),
                        slip_no: res.new_slip || ('SLIP-2026-' + String(assignments.length + 1).padStart(4, '0')),
                        asset_id: item.asset_id,
                        asset_tag: item.asset_tag,
                        asset_name: item.asset_name,
                        category: item.category,
                        brand: item.brand,
                        model: item.model,
                        serial: item.serial,
                        specs: item.specs,
                        employee_name: newEmpOpt.dataset.name,
                        emp_code: newEmpOpt.dataset.code,
                        employee_email: newEmpOpt.dataset.email || '',
                        department: newDept,
                        designation: newEmpOpt.dataset.desig || 'Team Member',
                        location: item.location,
                        assigned_date: effDate,
                        allocation_type: item.allocation_type,
                        expected_return: item.expected_return,
                        custody_status: 'Active',
                        condition: item.condition,
                        accessories: item.accessories,
                        handover_by: 'IT Administrator (Transfer)',
                        agreement_signed: true,
                        notes: `Transferred custody from ${oldName}. Reason: ${transferReason}`
                    });

                    closeModal(transferModal);
                    if (window.activeAllocId === id) {
                        openAssignmentDrawer(res.new_id || id);
                    }

                    renderTable();
                    updateKPIs();
                    showNotification(res.message || `Custody of ${item.asset_tag} successfully transferred to ${newEmpOpt.dataset.name}!`, 'success');
                } else {
                    showNotification(res.message || 'Transfer failed.', 'danger');
                }
            })
            .catch(err => {
                console.error('Transfer error:', err);
                showNotification('Network error while transferring asset.', 'danger');
            })
            .finally(() => {
                if (trBtn) trBtn.disabled = false;
            });
        });
    }

    // --- Direct Browser Print Handover Slip (Simple Table Format - No On-Screen Modal) ---
    function directPrintSlip(id) {
        const item = assignments.find(a => a.id === id);
        if (!item) {
            showNotification('Record not found for printing.', 'warning');
            return;
        }

        const slipNo = item.slip_no || ('SLIP-2026-' + String(item.id).padStart(4, '0'));
        const todayStr = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        const empName = escapeHtml(item.employee_name || '—');
        const empCode = escapeHtml(item.emp_code || '—');
        const empDept = escapeHtml(item.department || '—');
        const empDesig = escapeHtml(item.designation || 'Staff');
        const empEmail = escapeHtml(item.employee_email || '—');
        const empLoc = escapeHtml(item.location || 'Headquarters');
        const allocType = escapeHtml(item.allocation_type || 'Permanent');
        const assignedDate = escapeHtml(item.assigned_date || todayStr);
        const expectedReturn = escapeHtml(item.expected_return || 'Permanent');
        const handoverBy = escapeHtml(item.handover_by || 'IT Administrator');
        const notes = escapeHtml(item.notes || 'Equipment verified and allocated in good physical condition. Custodian agreed to corporate IT acceptable use policy.');
        const custodyStatus = escapeHtml(item.custody_status || 'Active');

        // Hardware Devices
        let assetsList = Array.isArray(item.assets) ? [...item.assets] : [];
        if (assetsList.length === 0 && (item.asset_tag || item.asset_name)) {
            assetsList.push({
                tag: item.asset_tag,
                name: item.asset_name,
                category: item.category || 'Hardware',
                brand: item.brand || '',
                model: item.model || '',
                serial: item.serial || '',
                specs: item.specs || '',
                condition: item.condition || 'Good'
            });
        }

        let assetsRows = '';
        if (assetsList.length === 0) {
            assetsRows = `<tr><td colspan="7" style="text-align: center; padding: 8px; color: #555; font-style: italic;">No hardware devices assigned under this slip.</td></tr>`;
        } else {
            assetsRows = assetsList.map((ast, idx) => {
                const bm = [ast.brand, ast.model].filter(Boolean).join(' ') || '—';
                return `
                    <tr>
                        <td style="text-align: center; width: 32px;">${idx + 1}</td>
                        <td style="font-family: monospace; font-weight: bold; width: 100px;">${escapeHtml(ast.tag || '—')}</td>
                        <td>
                            <strong>${escapeHtml(ast.name || 'Device')}</strong>
                            ${ast.specs ? `<div style="font-size: 9.5px; color: #555; margin-top: 1px;">${escapeHtml(ast.specs)}</div>` : ''}
                        </td>
                        <td style="width: 95px;">${escapeHtml(ast.category || 'Hardware')}</td>
                        <td style="width: 110px;">${escapeHtml(bm)}</td>
                        <td style="font-family: monospace; width: 110px;">${escapeHtml(ast.serial || '—')}</td>
                        <td style="text-align: center; width: 80px;">${escapeHtml(ast.condition || 'Good')}</td>
                    </tr>
                `;
            }).join('');
        }

        // Accessories
        let accList = Array.isArray(item.accessories) ? [...item.accessories] : [];
        let accRows = '';
        if (accList.length === 0) {
            accRows = `<tr><td colspan="5" style="text-align: center; padding: 8px; color: #555; font-style: italic;">No accessories assigned under this slip.</td></tr>`;
        } else {
            accRows = accList.map((ac, idx) => {
                const acName = typeof ac === 'string' ? ac : (ac.name || 'Accessory Item');
                const acQty = (typeof ac === 'object' && ac.qty) ? ac.qty : 1;
                const acCategory = (typeof ac === 'object' && ac.category) ? ac.category : 'Standard Accessory';
                const acCondition = (typeof ac === 'object' && ac.condition) ? ac.condition : 'Good';
                return `
                    <tr>
                        <td style="text-align: center; width: 32px;">${idx + 1}</td>
                        <td><strong>${escapeHtml(acName)}</strong></td>
                        <td style="width: 180px;">${escapeHtml(acCategory)}</td>
                        <td style="text-align: center; width: 60px; font-weight: bold;">${escapeHtml(String(acQty))}</td>
                        <td style="text-align: center; width: 85px;">${escapeHtml(acCondition)}</td>
                    </tr>
                `;
            }).join('');
        }

        const printHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Equipment Handover Slip - ${slipNo}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #0f172a;
            background: #ffffff;
        }
        .slip-container {
            width: 100%;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 9px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #334155;
            padding: 5px 8px;
            vertical-align: middle;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 700;
            text-align: left;
            font-size: 10.5px;
            color: #0f172a;
        }
        .header-tbl {
            border: none !important;
            margin-bottom: 14px;
        }
        .header-tbl td {
            border: none !important;
            padding: 4px 8px;
        }
        .header-logo {
            width: 22%;
            text-align: left;
            vertical-align: middle;
            border: none !important;
        }
        .header-logo img {
            max-height: 72px;
            max-width: 170px;
            object-fit: contain;
            display: block;
        }
        .header-title {
            text-align: center;
            vertical-align: middle;
        }
        .header-title h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #001938;
            text-transform: uppercase;
        }
        .header-title .sub {
            font-size: 11.5px;
            font-weight: 600;
            color: #475569;
            margin-top: 2px;
        }
        .header-title .doc-name {
            font-size: 13px;
            font-weight: 800;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            border-top: 1.5px solid #001938;
            padding-top: 2px;
            color: #0093A7;
        }
        .header-meta {
            width: 28%;
            border: none !important;
            font-size: 10.5px;
            line-height: 1.55;
            vertical-align: middle;
            text-align: right;
            background: transparent !important;
        }
        .sec-title {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            padding: 4px 8px;
            border: 1px solid #334155;
            border-bottom: none;
            margin-top: 9px;
            color: #0f172a;
            letter-spacing: 0.3px;
        }
        .lbl {
            background: #f8fafc;
            font-weight: 600;
            color: #334155;
            width: 18%;
        }
        .val {
            color: #0f172a;
            width: 32%;
        }
        .declaration-box {
            border: 1px solid #334155;
            padding: 7px 10px;
            font-size: 10px;
            color: #334155;
            background: #f8fafc;
            margin-top: 9px;
            line-height: 1.45;
        }
        .signatures-tbl {
            border: 1px solid #334155;
            border-top: none;
            margin-top: 0;
            margin-bottom: 0;
        }
        .signatures-tbl td {
            border: none;
            padding: 38px 14px 10px;
            vertical-align: bottom;
            width: 50%;
        }
        .sig-line {
            border-top: 1.5px solid #0f172a;
            padding-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .sig-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="slip-container">
        <!-- Document Header Table -->
        <table class="header-tbl">
            <tr>
                <td class="header-logo">
                    <img src="assets/images/logo.png" alt="Company Logo" style="max-height: 72px; max-width: 170px; object-fit: contain;">
                </td>
                <td class="header-title">
                    <h1>VIROS PORTAL</h1>
                    <div class="sub">IT Asset Management & Helpdesk</div>
                    <div class="doc-name">EQUIPMENT HANDOVER & CUSTODY SLIP</div>
                </td>
                <td class="header-meta">
                    <div><strong>Slip No:</strong> ${slipNo}</div>
                    <div><strong>Date:</strong> ${assignedDate}</div>
                    <div><strong>Type:</strong> ${allocType}</div>
                    <div><strong>Status:</strong> ${custodyStatus}</div>
                </td>
            </tr>
        </table>

        <!-- 1. Custodian / Employee Information -->
        <div class="sec-title">1. Custodian / Employee Information</div>
        <table>
            <tr>
                <td class="lbl">Employee Name</td>
                <td class="val"><strong>${empName}</strong></td>
                <td class="lbl">Employee ID</td>
                <td class="val" style="font-family: monospace; font-weight: 700;">${empCode}</td>
            </tr>
            <tr>
                <td class="lbl">Designation</td>
                <td class="val">${empDesig}</td>
                <td class="lbl">Department</td>
                <td class="val">${empDept}</td>
            </tr>
            <tr>
                <td class="lbl">Email Address</td>
                <td class="val">${empEmail}</td>
                <td class="lbl">Branch / Location</td>
                <td class="val">${empLoc}</td>
            </tr>
        </table>

        <!-- 2. Assigned Hardware Assets -->
        <div class="sec-title">2. Assigned Hardware Assets (${assetsList.length})</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;">#</th>
                    <th style="width: 100px;">Asset Tag</th>
                    <th>Device Name & Specs</th>
                    <th style="width: 95px;">Category</th>
                    <th style="width: 110px;">Brand & Model</th>
                    <th style="width: 110px;">Serial Number</th>
                    <th style="width: 80px; text-align: center;">Condition</th>
                </tr>
            </thead>
            <tbody>
                ${assetsRows}
            </tbody>
        </table>

        <!-- 3. Assigned Accessories -->
        <div class="sec-title">3. Assigned Accessories & Peripherals (${accList.length})</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;">#</th>
                    <th>Accessory Item</th>
                    <th style="width: 180px;">Category / Classification</th>
                    <th style="width: 60px; text-align: center;">Qty</th>
                    <th style="width: 85px; text-align: center;">Condition</th>
                </tr>
            </thead>
            <tbody>
                ${accRows}
            </tbody>
        </table>

        <!-- 4. Handover Terms & Scope -->
        <div class="sec-title">4. Handover Terms & Authorization</div>
        <table>
            <tr>
                <td class="lbl">Allocation Type</td>
                <td class="val">${allocType}</td>
                <td class="lbl">Handover Date</td>
                <td class="val">${assignedDate}</td>
            </tr>
            <tr>
                <td class="lbl">Expected Return</td>
                <td class="val">${expectedReturn}</td>
                <td class="lbl">Issued By</td>
                <td class="val">${handoverBy}</td>
            </tr>
            <tr>
                <td class="lbl">Remarks / Notes</td>
                <td class="val" colspan="3">${notes}</td>
            </tr>
        </table>

        <!-- 5. Declaration & Signatures -->
        <div class="declaration-box">
            <strong>Declaration & Acceptance:</strong> I hereby acknowledge receipt of the hardware and accessories listed above in sound physical and working condition. I agree to abide by the company IT Acceptable Use Policy and accept full responsibility for their care and custody.
        </div>
        <table class="signatures-tbl">
            <tr>
                <td>
                    <div class="sig-line">Employee / Custodian Signature</div>
                    <div class="sig-sub">${empName} (${empCode}) &bull; Date: _____________</div>
                </td>
                <td style="text-align: right;">
                    <div class="sig-line">Authorized IT Official / Seal</div>
                    <div class="sig-sub">IT Asset Management Dept &bull; Date: _____________</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>`;

        // Direct Browser Print via hidden iframe (No on-screen modal)
        let printIframe = document.getElementById('slipPrintIframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'slipPrintIframe';
            printIframe.style.position = 'fixed';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            printIframe.style.width = '0';
            printIframe.style.height = '0';
            printIframe.style.border = '0';
            printIframe.style.visibility = 'hidden';
            document.body.appendChild(printIframe);
        }

        const frameDoc = printIframe.contentWindow.document;
        frameDoc.open();
        frameDoc.write(printHtml);
        frameDoc.close();

        // Directly open the browser's native print modal
        setTimeout(() => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        }, 200);
    }

    // Expose functions globally
    window.directPrintSlip = directPrintSlip;
    window.openSlipModal = directPrintSlip;
    window.printSlipReceipt = directPrintSlip;

    // =========================================================================
    // 5. Event Listeners & Initialization
    // =========================================================================

    function bindEvents() {
        // Search Input
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                searchTerm = e.target.value.toLowerCase().trim();
                renderTable();
            });
        }

        // Filters
        if (deptFilter) {
            deptFilter.addEventListener('change', function (e) {
                selectedDept = e.target.value;
                renderTable();
            });
        }

        if (typeFilter) {
            typeFilter.addEventListener('change', function (e) {
                selectedType = e.target.value;
                renderTable();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function (e) {
                selectedStatus = e.target.value;
                renderTable();
            });
        }

        // Reset Filters
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function () {
                searchTerm = '';
                selectedTab = 'all';
                selectedDept = 'all';
                selectedType = 'all';
                selectedStatus = 'all';

                if (searchInput) searchInput.value = '';
                if (deptFilter) deptFilter.value = 'all';
                if (typeFilter) typeFilter.value = 'all';
                if (statusFilter) statusFilter.value = 'all';

                document.querySelectorAll('.status-tab-btn').forEach(b => {
                    b.classList.toggle('active', b.dataset.tab === 'all');
                });

                renderTable();
                showNotification('Filters reset to default.', 'info');
            });
        }

        // Status Tabs Navigation
        document.querySelectorAll('.status-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.status-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedTab = this.dataset.tab;
                renderTable();
            });
        });

        // Top KPI Cards Click -> Toggle tab filter
        document.querySelectorAll('.alloc-stat-card[data-filter-tab]').forEach(card => {
            card.addEventListener('click', function () {
                const targetTab = this.dataset.filterTab;
                const tabBtn = document.querySelector(`.status-tab-btn[data-tab="${targetTab}"]`);
                if (tabBtn) tabBtn.click();
            });
        });

        // "Ready to Assign" card click -> opens assignment modal
        const cardAvailable = document.getElementById('cardAvailableInStock');
        if (cardAvailable) {
            cardAvailable.addEventListener('click', function () {
                openAssignModal();
            });
        }

        // Modal Open Buttons
        const openAssignBtn = document.getElementById('openAssignModalBtn');
        if (openAssignBtn) {
            openAssignBtn.addEventListener('click', openAssignModal);
        }

        // Modal Close Buttons
        document.getElementById('closeAssignModalBtn')?.addEventListener('click', () => closeModal(assignModal));
        document.getElementById('cancelAssignBtn')?.addEventListener('click', () => closeModal(assignModal));

        document.getElementById('closeReturnModalBtn')?.addEventListener('click', () => closeModal(returnModal));
        document.getElementById('cancelReturnBtn')?.addEventListener('click', () => closeModal(returnModal));

        document.getElementById('closeTransferModalBtn')?.addEventListener('click', () => closeModal(transferModal));
        document.getElementById('cancelTransferBtn')?.addEventListener('click', () => closeModal(transferModal));

        document.getElementById('closeSlipModalBtn')?.addEventListener('click', () => closeModal(slipModal));
        document.getElementById('closeSlipBtn')?.addEventListener('click', () => closeModal(slipModal));

        // Drawer Close & Backdrop
        if (closeDrawerBtn) {
            closeDrawerBtn.addEventListener('click', window.closeAssignmentDrawer);
        }
        if (drawerBackdrop) {
            drawerBackdrop.addEventListener('click', window.closeAssignmentDrawer);
        }

        // Drawer Tabs Navigation
        if (assignmentDrawer) {
            assignmentDrawer.querySelectorAll('.drawer-tab').forEach(btn => {
                btn.addEventListener('click', function () {
                    switchDrawerTab(this.dataset.tab);
                });
            });
        }

        // Select All Checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                const checked = this.checked;
                document.querySelectorAll('.row-select-checkbox').forEach(cb => {
                    cb.checked = checked;
                });
            });
        }

        // Export to CSV
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                const list = getFilteredAssignments();
                if (list.length === 0) {
                    showNotification('No allocation records to export.', 'warning');
                    return;
                }

                const headers = ['Slip No', 'Asset Tag', 'Device Name', 'Category', 'Serial', 'Employee Name', 'Emp Code', 'Department', 'Designation', 'Allocation Type', 'Assigned Date', 'Expected Return', 'Custody Status'];
                const rows = list.map(item => [
                    item.slip_no,
                    item.asset_tag,
                    item.asset_name,
                    item.category,
                    item.serial,
                    item.employee_name,
                    item.emp_code,
                    item.department,
                    item.designation,
                    item.allocation_type,
                    item.assigned_date,
                    item.expected_return || 'Permanent',
                    item.custody_status
                ]);

                const csvContent = [headers.join(','), ...rows.map(r => r.map(f => `"${String(f).replace(/"/g, '""')}"`).join(','))].join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `asset_assignments_${new Date().toISOString().slice(0, 10)}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showNotification('Allocations exported to CSV successfully.', 'success');
            });
        }

        // Close on Escape or click outside
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(assignModal);
                closeModal(returnModal);
                closeModal(transferModal);
                closeModal(slipModal);
                window.closeAssignmentDrawer();
            }
        });

        [assignModal, returnModal, transferModal, slipModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeModal(modal);
                    }
                });
            }
        });
    }

    function init() {
        // Relocate all modals directly into <body> to prevent sidebar / main-wrapper positioning constraints
        [assignModal, returnModal, transferModal, slipModal].forEach(modal => {
            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });

        renderTable();
        updateKPIs();
        bindEvents();
    }

    // Global Hooks
    window.allocMgr = {
        openAssign: openAssignModal,
        openReturn: openReturnModal,
        openTransfer: openTransferModal,
        openSlip: openSlipModal,
        printSlip: printSlipReceipt,
        openView: openAssignmentDrawer,
        reload: renderTable
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
