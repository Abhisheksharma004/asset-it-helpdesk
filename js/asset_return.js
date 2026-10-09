/**
 * VIROS IT Asset & Service Desk Portal
 * Asset Return & Depot Restock Controller (js/asset_return.js)
 */

(function () {
    'use strict';

    let returns = Array.isArray(window.RETURNED_DATA) ? window.RETURNED_DATA : [];
    let activeAllocations = Array.isArray(window.ACTIVE_DATA) ? window.ACTIVE_DATA : [];

    // Filter states
    let searchTerm = '';
    let selectedTab = 'all';
    let selectedCondition = 'all';
    let selectedDept = 'all';

    // Active Drawer State
    let activeDrawerReturnId = null;

    // DOM Elements
    const tbody = document.getElementById('returnsTbody');
    const searchInput = document.getElementById('returnSearchInput');
    const conditionFilter = document.getElementById('returnConditionFilter');
    const deptFilter = document.getElementById('returnDeptFilter');
    const resetFiltersBtn = document.getElementById('resetReturnFiltersBtn');
    const tableCountText = document.getElementById('returnTableCountText');
    const exportBtn = document.getElementById('exportReturnsBtn');

    // Modals & Drawer
    const processReturnModal = document.getElementById('processReturnModal');
    const openProcessReturnBtn = document.getElementById('openProcessReturnBtn');
    const closeProcessModalBtn = document.getElementById('closeProcessReturnModalBtn');
    const cancelProcessModalBtn = document.getElementById('cancelProcessReturnBtn');
    const processReturnForm = document.getElementById('processReturnForm');
    const selectActiveAlloc = document.getElementById('selectActiveAlloc');
    const allocPreviewBox = document.getElementById('allocPreviewBox');

    const returnDrawer = document.getElementById('returnDrawer');
    const drawerBackdrop = document.getElementById('returnDrawerBackdrop');
    const closeDrawerBtn = document.getElementById('closeReturnDrawerBtn');

    // Helpers
    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    const avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];
    function getAvatarColor(name) {
        let hash = 0;
        const key = name || '';
        for (let i = 0; i < key.length; i++) {
            hash = (hash << 5) - hash + key.charCodeAt(i);
            hash |= 0;
        }
        return avatarColors[Math.abs(hash) % avatarColors.length];
    }

    function getInitials(name) {
        if (!name) return 'ST';
        const parts = name.trim().split(' ');
        let inits = '';
        for (let p of parts) {
            if (p) inits += p[0].toUpperCase();
            if (inits.length >= 2) break;
        }
        return inits || 'ST';
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
    // FILTERING & RENDERING
    // =========================================================================

    function getFilteredReturns() {
        return returns.filter(item => {
            const cond = (item.return_condition || '').toLowerCase();
            const isRepair = cond.includes('repair') || cond.includes('damaged');

            // Tabs
            if (selectedTab === 'restocked' && isRepair) return false;
            if (selectedTab === 'repair' && !isRepair) return false;
            if (selectedTab === 'pending_custody') return false; // Handled separately if tab chosen

            // Condition filter
            if (selectedCondition !== 'all') {
                if ((item.return_condition || '').toLowerCase() !== selectedCondition.toLowerCase()) return false;
            }

            // Department filter
            if (selectedDept !== 'all' && item.department !== selectedDept) return false;

            // Search
            if (searchTerm) {
                const q = searchTerm;
                const matchSlip = item.slip_no && item.slip_no.toLowerCase().includes(q);
                const matchEmp = item.employee_name && item.employee_name.toLowerCase().includes(q);
                const matchCode = item.emp_code && item.emp_code.toLowerCase().includes(q);
                const matchDept = item.department && item.department.toLowerCase().includes(q);
                const matchNotes = item.return_notes && item.return_notes.toLowerCase().includes(q);

                let matchAsset = false;
                if (Array.isArray(item.assets)) {
                    matchAsset = item.assets.some(a =>
                        (a.tag && a.tag.toLowerCase().includes(q)) ||
                        (a.name && a.name.toLowerCase().includes(q)) ||
                        (a.serial && a.serial.toLowerCase().includes(q))
                    );
                }

                if (!matchSlip && !matchEmp && !matchCode && !matchDept && !matchNotes && !matchAsset) {
                    return false;
                }
            }

            return true;
        });
    }

    function renderReturnsTable() {
        if (!tbody) return;

        // Special case: if user clicked "Active Loaners Pending Return" tab
        if (selectedTab === 'pending_custody') {
            renderPendingCustodyTable();
            return;
        }

        const list = getFilteredReturns();
        tbody.innerHTML = '';

        if (tableCountText) {
            tableCountText.textContent = `Showing ${list.length} of ${returns.length} Returns`;
        }

        if (list.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="table-empty-state" style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8" style="margin-bottom: 6px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <div style="font-size: 14.5px; font-weight: 700; color: var(--text-primary);">No Return Records Found</div>
                            <div style="font-size: 12px; margin-top: 3px;">No returns match your current filter and search conditions.</div>
                            <button type="button" class="btn-secondary" style="margin-top: 12px; padding: 6px 14px; font-size: 12.5px;" onclick="document.getElementById('resetReturnFiltersBtn')?.click()">Reset Filters</button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(ret => {
            const c = (ret.return_condition || 'Good').toLowerCase();
            let condClass = 'condition-good';
            if (c.includes('brand') || c.includes('excel')) {
                condClass = 'condition-excellent';
            } else if (c.includes('repair')) {
                condClass = 'condition-repair';
            } else if (c.includes('damag')) {
                condClass = 'condition-damaged';
            }

            const isRestocked = !c.includes('repair') && !c.includes('damag');
            const inits = getInitials(ret.employee_name);
            const colorBg = getAvatarColor(ret.employee_name);
            const assetsList = Array.isArray(ret.assets) ? ret.assets : [];
            const accList = Array.isArray(ret.accessories) ? ret.accessories : [];
            const totalAcc = ret.total_accessories || accList.length;

            const tr = document.createElement('tr');
            tr.setAttribute('data-id', ret.id);
            tr.innerHTML = `
                <td>
                    <input type="checkbox" class="custom-checkbox row-select-checkbox" value="${ret.id}">
                </td>
                <td>
                    <div class="slip-cell">
                        <span class="slip-badge" onclick="openReturnDrawer(${ret.id})">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                            ${escapeHtml(ret.slip_no)}
                        </span>
                        <div class="slip-date">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            ${escapeHtml(ret.return_date)}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="custodian-cell">
                        <div class="custodian-avatar" style="background: ${colorBg};">
                            ${escapeHtml(inits)}
                        </div>
                        <div class="custodian-info">
                            <div class="custodian-name">
                                ${escapeHtml(ret.employee_name)}
                                <span class="emp-code-badge">${escapeHtml(ret.emp_code)}</span>
                            </div>
                            <div class="custodian-sub">
                                ${escapeHtml(ret.designation)} • <strong>${escapeHtml(ret.department)}</strong>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <div class="count-pill-wrap">
                            ${assetsList.length > 0 ? `
                                <span class="kitna-badge asset-kitna-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                    <strong>${assetsList.length} ${assetsList.length === 1 ? 'Asset' : 'Assets'}</strong>
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
                                <span class="chip-more-count" onclick="openReturnDrawer(${ret.id})">+${assetsList.length - 2} more device(s)</span>
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
                            ${accList.slice(0, 2).map(acc => {
                                const accName = typeof acc === 'string' ? acc : (acc.name || 'Item');
                                const accQty = (typeof acc === 'object' && acc.qty) ? acc.qty : 1;
                                return `
                                    <span class="chip-acc-tag" title="${escapeHtml(accName)}">
                                        <span>${escapeHtml(accName)}</span>
                                        <span class="chip-acc-qty">${escapeHtml(accQty)}</span>
                                    </span>
                                `;
                            }).join('')}
                            ${accList.length > 2 ? `
                                <span class="chip-more-count" onclick="openReturnDrawer(${ret.id})">+${accList.length - 2} more item(s)</span>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 3px; align-items: flex-start;">
                        <span style="font-weight: 600; font-size: 12.5px; color: var(--text-primary);">
                            ${escapeHtml(ret.return_date)}
                        </span>
                        <span style="font-size: 11px; color: var(--text-muted);">
                            From: ${escapeHtml(ret.assigned_date)}
                        </span>
                    </div>
                </td>
                <td>
                    <span class="condition-badge ${condClass}">
                        <span style="font-size: 8px;">●</span>
                        ${escapeHtml(ret.return_condition || 'Good')}
                    </span>
                </td>
                <td>
                    ${isRestocked ? `
                        <div class="custody-badge status-active">
                            <span class="dot" style="background: #10b981;"></span>
                            <span>Restocked</span>
                        </div>
                    ` : `
                        <div class="custody-badge status-overdue">
                            <span class="dot" style="background: #ef4444;"></span>
                            <span>Under Service</span>
                        </div>
                    `}
                </td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        <button type="button" class="action-icon-btn btn-view" title="View Inspection Details" onclick="openReturnDrawer(${ret.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <button type="button" class="action-icon-btn btn-qr" title="Print Equipment Return Receipt" onclick="printReturnReceipt(${ret.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        </button>
                        ${assetsList.length > 0 ? `
                            <a href="asset_assignment.php?preselect_asset=${encodeURIComponent(assetsList[0].id || '')}" class="action-icon-btn btn-return" title="Re-assign Asset to Employee">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
                            </a>
                        ` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Special view for Active allocations awaiting return
    function renderPendingCustodyTable() {
        if (!tbody) return;
        tbody.innerHTML = '';

        if (tableCountText) {
            tableCountText.textContent = `Showing ${activeAllocations.length} Active Allocations Awaiting Return`;
        }

        if (activeAllocations.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 36px 14px; color: var(--text-muted);">
                        No active allocations currently pending return.
                    </td>
                </tr>
            `;
            return;
        }

        activeAllocations.forEach(act => {
            const inits = getInitials(act.employee_name);
            const colorBg = getAvatarColor(act.employee_name);
            const assetsList = Array.isArray(act.assets) ? act.assets : [];
            const accList = Array.isArray(act.accessories) ? act.accessories : [];
            const totalAcc = act.total_accessories || accList.length;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="checkbox" class="custom-checkbox row-select-checkbox" value="${act.id}">
                </td>
                <td>
                    <div class="slip-cell">
                        <span class="slip-badge" style="background:#f1f5f9; color:#475569; border-color:#cbd5e1;">
                            ${escapeHtml(act.slip_no)}
                        </span>
                        <div class="slip-date">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            ${escapeHtml(act.assigned_date)}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="custodian-cell">
                        <div class="custodian-avatar" style="background: ${colorBg};">
                            ${escapeHtml(inits)}
                        </div>
                        <div class="custodian-info">
                            <div class="custodian-name">
                                ${escapeHtml(act.employee_name)}
                                <span class="emp-code-badge">${escapeHtml(act.emp_code)}</span>
                            </div>
                            <div class="custodian-sub">
                                ${escapeHtml(act.designation || 'Staff')} • <strong>${escapeHtml(act.department)}</strong>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <div class="count-pill-wrap">
                            ${assetsList.length > 0 ? `
                                <span class="kitna-badge asset-kitna-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                    <strong>${assetsList.length} ${assetsList.length === 1 ? 'Asset' : 'Assets'}</strong>
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
                    </div>
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 12.5px; color: var(--text-primary);">
                        ${escapeHtml(act.assigned_date)}
                    </div>
                </td>
                <td>
                    <div class="custody-badge status-active">
                        <span class="dot" style="background: #10b981;"></span>
                        <span>In Custody</span>
                    </div>
                </td>
                <td>
                    <div class="custody-badge status-due-soon">
                        <span class="dot" style="background: #f97316;"></span>
                        <span>Pending Return</span>
                    </div>
                </td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        <button type="button" class="btn-primary" style="padding: 5px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;" onclick="quickInitiateReturn(${act.id})">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 14 4 9 9 4"></polyline></svg>
                            Return Now
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updateKPIs() {
        let restocked = 0;
        let repair = 0;

        returns.forEach(r => {
            const c = (r.return_condition || '').toLowerCase();
            if (c.includes('repair') || c.includes('damag')) {
                repair++;
            } else {
                restocked++;
            }
        });

        const kpiTot = document.getElementById('kpiTotalReturns');
        const kpiRestock = document.getElementById('kpiRestockedGood');
        const kpiRep = document.getElementById('kpiNeedsRepair');
        const tabAll = document.getElementById('tabCountAll');
        const tabRest = document.getElementById('tabCountRestocked');
        const tabRep = document.getElementById('tabCountRepair');
        const tabPend = document.getElementById('tabCountPending');

        if (kpiTot) kpiTot.textContent = returns.length;
        if (kpiRestock) kpiRestock.textContent = restocked;
        if (kpiRep) kpiRep.textContent = repair;

        if (tabAll) tabAll.textContent = returns.length;
        if (tabRest) tabRest.textContent = restocked;
        if (tabRep) tabRep.textContent = repair;
        if (tabPend) tabPend.textContent = activeAllocations.length;
    }

    // =========================================================================
    // SLIDE-OVER DRAWER
    // =========================================================================
    window.openReturnDrawer = function (id) {
        const item = returns.find(r => r.id === id);
        if (!item) return;

        activeDrawerReturnId = item.id;

        const dAvatar = document.getElementById('drawerAvatar');
        if (dAvatar) {
            dAvatar.textContent = getInitials(item.employee_name);
            dAvatar.style.background = getAvatarColor(item.employee_name);
        }

        const dSlip = document.getElementById('drawerSlipNo');
        if (dSlip) dSlip.textContent = item.slip_no;

        const dEmpName = document.getElementById('drawerEmpName');
        if (dEmpName) dEmpName.textContent = item.employee_name;

        const dEmpMeta = document.getElementById('drawerEmpMeta');
        if (dEmpMeta) dEmpMeta.textContent = `${item.designation || 'Staff'} • ${item.department} (${item.emp_code})`;

        const dReturnDate = document.getElementById('drawerReturnDate');
        if (dReturnDate) dReturnDate.textContent = item.return_date;

        const dAssignedDate = document.getElementById('drawerAssignedDate');
        if (dAssignedDate) dAssignedDate.textContent = item.assigned_date;

        const dCondition = document.getElementById('drawerCondition');
        if (dCondition) dCondition.textContent = item.return_condition || item.condition || 'Good';

        const dAllocType = document.getElementById('drawerAllocType');
        if (dAllocType) dAllocType.textContent = item.allocation_type;

        const dNotes = document.getElementById('drawerNotes');
        if (dNotes) dNotes.textContent = item.return_notes || item.notes || 'Equipment returned in good condition.';

        // Hardware Devices
        const devWrap = document.getElementById('drawerDevicesWrap');
        const countBadge = document.getElementById('drawerDeviceCount');
        const assetsList = Array.isArray(item.assets) ? item.assets : [];
        if (countBadge) countBadge.textContent = assetsList.length;

        if (devWrap) {
            if (assetsList.length === 0) {
                devWrap.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:10px; background:#f8fafc; border-radius:6px; text-align:center;">No hardware devices attached.</div>';
            } else {
                devWrap.innerHTML = assetsList.map(a => `
                    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:8px; padding:12px 14px; display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                        <div style="display:flex; align-items:flex-start; gap:10px;">
                            <div style="background:#e0f2fe; color:#0284c7; padding:4px 8px; border-radius:6px; font-family:monospace; font-weight:700; font-size:11.5px;">
                                ${escapeHtml(a.tag)}
                            </div>
                            <div>
                                <div style="font-weight:700; font-size:13.5px; color:var(--navy-primary);">${escapeHtml(a.name)}</div>
                                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">
                                    ${escapeHtml(a.category || 'Hardware')} ${a.brand ? `• ${escapeHtml(a.brand)}` : ''} ${a.model ? `(${escapeHtml(a.model)})` : ''}
                                </div>
                                <div style="font-size:11.5px; color:var(--text-muted); font-family:monospace; margin-top:2px;">
                                    SN: <strong>${escapeHtml(a.serial || '—')}</strong>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="restock-pill available" style="font-size:10.5px;">✓ In Stock</span>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Accessories
        const accWrap = document.getElementById('drawerAccWrap');
        const accList = Array.isArray(item.accessories) ? item.accessories : [];
        if (accWrap) {
            if (accList.length === 0) {
                accWrap.innerHTML = '<div style="font-size:12px; color:var(--text-muted);">No accessories returned under this slip.</div>';
            } else {
                accWrap.innerHTML = accList.map(ac => {
                    const acName = typeof ac === 'string' ? ac : (ac.name || 'Item');
                    const acQty = (typeof ac === 'object' && ac.qty) ? ac.qty : 1;
                    return `
                        <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:600; color:#065f46; display:inline-flex; align-items:center; gap:6px;">
                            <span>${escapeHtml(acName)}</span>
                            <span style="background:#d1fae5; padding:1px 6px; border-radius:8px; font-size:10.5px;">x${escapeHtml(acQty)}</span>
                        </div>
                    `;
                }).join('');
            }
        }

        if (returnDrawer) returnDrawer.classList.add('open');
        if (drawerBackdrop) drawerBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeReturnDrawer = function () {
        if (returnDrawer) returnDrawer.classList.remove('open');
        if (drawerBackdrop) drawerBackdrop.classList.remove('open');
        document.body.style.overflow = '';
        activeDrawerReturnId = null;
    };

    // =========================================================================
    // MODAL TABS SWITCHING (Matching asset_assignment.php pattern)
    // =========================================================================
    window.switchReturnModalTab = function (tabName) {
        document.querySelectorAll('#processReturnModal .modal-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        document.querySelectorAll('#processReturnModal .modal-tab-pane').forEach(pane => {
            pane.classList.toggle('active', pane.id === 'modal_pane_' + tabName);
        });
    };

    // =========================================================================
    // QUICK INITIATE RETURN FROM ACTIVE ALLOCATIONS
    // =========================================================================
    window.quickInitiateReturn = function (allocId) {
        window.switchReturnModalTab('custodian');
        openModal(processReturnModal);
        if (selectActiveAlloc) {
            selectActiveAlloc.value = allocId;
            selectActiveAlloc.dispatchEvent(new Event('change'));
        }
    };

    // =========================================================================
    // PRINT RETURN CERTIFICATE / RECEIPT
    // =========================================================================
    window.printReturnReceipt = function (id) {
        const item = returns.find(r => r.id === id);
        if (!item) return;

        const printHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Equipment Return Receipt - ${item.slip_no}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 25px; color: #1e293b; }
        .receipt-header { border-bottom: 2px solid #059669; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .receipt-title { font-size: 20px; font-weight: 800; color: #001938; }
        .receipt-badge { font-family: monospace; font-size: 13px; font-weight: 700; background: #ecfdf5; color: #059669; padding: 4px 10px; border-radius: 6px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; font-size: 13px; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12.5px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background: #f1f5f9; font-weight: 700; font-size: 11px; text-transform: uppercase; }
        .sig-section { display: flex; justify-content: space-between; margin-top: 40px; font-size: 12px; }
        .sig-box { width: 220px; border-top: 1px solid #475569; padding-top: 6px; text-align: center; font-weight: 600; }
    </style>
</head>
<body>
    <div class="receipt-header">
        <div>
            <div class="receipt-title">IT Equipment Return & Restock Acknowledgment</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">VIROS Portal &bull; Enterprise IT Asset Management</div>
        </div>
        <div class="receipt-badge">${escapeHtml(item.slip_no)}</div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <strong>Returning Employee / Custodian:</strong><br>
            Name: ${escapeHtml(item.employee_name)}<br>
            Emp Code: ${escapeHtml(item.emp_code)}<br>
            Department: ${escapeHtml(item.department)}<br>
            Designation: ${escapeHtml(item.designation)}
        </div>
        <div class="info-box">
            <strong>Return & Restock Metadata:</strong><br>
            Return Date: ${escapeHtml(item.return_date)}<br>
            Initial Handover Date: ${escapeHtml(item.assigned_date)}<br>
            Inspection Condition: <strong>${escapeHtml(item.return_condition || 'Good')}</strong><br>
            Restock Status: <strong>Restocked into Available Stock</strong>
        </div>
    </div>

    <h4 style="margin: 15px 0 5px 0; font-size: 13px;">Returned Hardware Devices:</h4>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Asset Tag</th>
                <th>Device Name</th>
                <th>Category</th>
                <th>Serial Number</th>
                <th>Restock Status</th>
            </tr>
        </thead>
        <tbody>
            ${(item.assets || []).map((a, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td style="font-family: monospace; font-weight: bold;">${escapeHtml(a.tag)}</td>
                    <td>${escapeHtml(a.name)}</td>
                    <td>${escapeHtml(a.category || 'Hardware')}</td>
                    <td style="font-family: monospace;">${escapeHtml(a.serial || '—')}</td>
                    <td style="color: #059669; font-weight: bold;">Restocked in Depot</td>
                </tr>
            `).join('')}
        </tbody>
    </table>

    <div class="sig-section">
        <div class="sig-box">
            Employee Signature<br>
            <span style="font-size: 10.5px; color: #64748b;">(${escapeHtml(item.employee_name)})</span>
        </div>
        <div class="sig-box">
            IT Receiving Officer Seal<br>
            <span style="font-size: 10.5px; color: #64748b;">(Storage Depot Custodian)</span>
        </div>
    </div>
</body>
</html>`;

        let iframe = document.getElementById('returnPrintIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'returnPrintIframe';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.style.visibility = 'hidden';
            document.body.appendChild(iframe);
        }

        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(printHtml);
        doc.close();

        setTimeout(() => {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        }, 250);
    };

    // =========================================================================
    // EVENT BINDINGS
    // =========================================================================
    function bindEvents() {
        // Search
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                searchTerm = this.value.trim().toLowerCase();
                renderReturnsTable();
            });
        }

        // Condition Filter
        if (conditionFilter) {
            conditionFilter.addEventListener('change', function () {
                selectedCondition = this.value;
                renderReturnsTable();
            });
        }

        // Dept Filter
        if (deptFilter) {
            deptFilter.addEventListener('change', function () {
                selectedDept = this.value;
                renderReturnsTable();
            });
        }

        // Reset Filters
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                if (conditionFilter) conditionFilter.value = 'all';
                if (deptFilter) deptFilter.value = 'all';
                searchTerm = '';
                selectedCondition = 'all';
                selectedDept = 'all';
                renderReturnsTable();
                showNotification('Filters reset to default.', 'info');
            });
        }

        // Tabs
        document.querySelectorAll('.return-tab-btn, .status-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.return-tab-btn, .status-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedTab = this.dataset.tab;
                renderReturnsTable();
            });
        });

        // Top KPI Cards click -> filter tab
        document.querySelectorAll('.return-stat-card[data-tab], .alloc-stat-card[data-filter-tab]').forEach(card => {
            card.addEventListener('click', function () {
                const targetTab = this.dataset.tab || this.dataset.filterTab;
                const tabBtn = document.querySelector(`.return-tab-btn[data-tab="${targetTab}"], .status-tab-btn[data-tab="${targetTab}"]`);
                if (tabBtn) tabBtn.click();
            });
        });

        // Select All Checkbox
        const selectAllReturns = document.getElementById('selectAllReturns');
        if (selectAllReturns) {
            selectAllReturns.addEventListener('change', function () {
                const checked = this.checked;
                document.querySelectorAll('.row-select-checkbox').forEach(cb => {
                    cb.checked = checked;
                });
            });
        }

        // Modal triggers
        if (openProcessReturnBtn) {
            openProcessReturnBtn.addEventListener('click', () => {
                window.switchReturnModalTab('custodian');
                openModal(processReturnModal);
            });
        }

        if (closeProcessModalBtn) closeProcessModalBtn.addEventListener('click', () => closeModal(processReturnModal));
        if (cancelProcessModalBtn) cancelProcessModalBtn.addEventListener('click', () => closeModal(processReturnModal));

        // Return Modal Tabs click listeners
        document.querySelectorAll('#processReturnModal .modal-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (this.dataset.tab) {
                    window.switchReturnModalTab(this.dataset.tab);
                }
            });
        });

        // Diagnostic Check All / Uncheck All toggles
        const btnCheckAllDiag = document.getElementById('btnCheckAllDiag');
        const btnUncheckAllDiag = document.getElementById('btnUncheckAllDiag');
        if (btnCheckAllDiag) {
            btnCheckAllDiag.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.diag-check').forEach(chk => chk.checked = true);
            });
        }
        if (btnUncheckAllDiag) {
            btnUncheckAllDiag.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.diag-check').forEach(chk => chk.checked = false);
            });
        }

        // When active allocation is chosen in return modal
        if (selectActiveAlloc) {
            selectActiveAlloc.addEventListener('change', function () {
                const val = this.value;
                if (!val) {
                    if (allocPreviewBox) allocPreviewBox.style.display = 'none';
                    return;
                }

                // Look up in activeAllocations or option datasets
                const allocId = parseInt(val, 10);
                const alloc = activeAllocations.find(a => a.id === allocId);
                const opt = this.selectedOptions[0];

                const slip = (alloc && alloc.slip_no) || (opt && opt.dataset.slip) || 'SLIP';
                const emp = (alloc && alloc.employee_name) || (opt && opt.dataset.emp) || 'Staff';
                const code = (alloc && alloc.emp_code) || (opt && opt.dataset.code) || 'EMP';
                const dept = (alloc && alloc.department) || (opt && opt.dataset.dept) || 'General';
                const desig = (alloc && alloc.designation) || (opt && opt.dataset.desig) || 'Employee';
                const allocType = (alloc && alloc.allocation_type) || (opt && opt.dataset.type) || 'Permanent';
                const assignedDate = (alloc && alloc.assigned_date) || (opt && opt.dataset.date) || '-';
                const notes = (alloc && alloc.notes) || (opt && opt.dataset.notes) || '';

                let assets = [];
                if (alloc && Array.isArray(alloc.assets)) {
                    assets = alloc.assets;
                } else if (opt && opt.dataset.assets) {
                    try { assets = JSON.parse(opt.dataset.assets); } catch (e) { assets = []; }
                }

                let accessories = [];
                if (alloc && Array.isArray(alloc.accessories)) {
                    accessories = alloc.accessories;
                } else if (opt && opt.dataset.acc) {
                    try { accessories = JSON.parse(opt.dataset.acc); } catch (e) { accessories = []; }
                }

                // Preview DOM references
                const pSlip = document.getElementById('previewSlipBadge');
                const pCode = document.getElementById('previewEmpCode');
                const pType = document.getElementById('previewAllocType');
                const pDate = document.getElementById('previewHandoverDate');
                const pName = document.getElementById('previewEmpName');
                const pMeta = document.getElementById('previewEmpMeta');
                const pAvatar = document.getElementById('previewEmpAvatar');
                const tabItemBadge = document.getElementById('returnTabItemBadge');
                const pTbody = document.getElementById('previewItemsTbody');
                const pNotesWrap = document.getElementById('previewNotesWrap');
                const pNotesText = document.getElementById('previewNotesText');
                const selectAllChk = document.getElementById('selectAllReturnItems');
                const selectionSummary = document.getElementById('previewSelectionSummary');

                if (pSlip) pSlip.textContent = slip;
                if (pCode) pCode.textContent = code;
                if (pType) pType.textContent = allocType;
                if (pDate) pDate.textContent = `Assigned Date: ${assignedDate}`;
                if (pName) pName.textContent = emp;
                if (pMeta) pMeta.textContent = `${dept} • ${desig}`;

                if (pAvatar) {
                    pAvatar.textContent = getInitials(emp);
                    pAvatar.style.background = getAvatarColor(emp);
                }

                const totalItemsCount = assets.length + accessories.length;
                if (tabItemBadge) {
                    tabItemBadge.textContent = totalItemsCount;
                    tabItemBadge.style.display = totalItemsCount > 0 ? 'inline-block' : 'none';
                }

                // Function to update checklist counts and summary text
                function updateChecklistSummary() {
                    const assetChecks = Array.from(document.querySelectorAll('#previewItemsTbody .check-asset'));
                    const accChecks = Array.from(document.querySelectorAll('#previewItemsTbody .check-acc'));

                    const checkedAssets = assetChecks.filter(c => c.checked).length;
                    const checkedAccs = accChecks.filter(c => c.checked).length;
                    const totalSelected = checkedAssets + checkedAccs;
                    const totalAll = assetChecks.length + accChecks.length;

                    if (selectionSummary) {
                        if (totalAll === 0) {
                            selectionSummary.textContent = 'No equipment attached';
                            selectionSummary.style.color = '#64748b';
                        } else if (totalSelected === totalAll) {
                            selectionSummary.textContent = `All ${totalAll} items selected for return`;
                            selectionSummary.style.color = '#059669';
                        } else if (totalSelected === 0) {
                            selectionSummary.textContent = '0 items selected';
                            selectionSummary.style.color = '#dc2626';
                        } else {
                            selectionSummary.textContent = `${totalSelected} of ${totalAll} items selected`;
                            selectionSummary.style.color = '#0284c7';
                        }
                    }

                    if (selectAllChk) {
                        selectAllChk.checked = totalAll > 0 && totalSelected === totalAll;
                        selectAllChk.indeterminate = totalSelected > 0 && totalSelected < totalAll;
                    }
                }

                // Render Table Rows for All Assets and Accessories
                if (pTbody) {
                    pTbody.innerHTML = '';
                    const totalRows = assets.length + accessories.length;

                    if (totalRows === 0) {
                        pTbody.innerHTML = `
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 12.5px; font-style: italic;">
                                    No hardware assets or accessories attached to this slip.
                                </td>
                            </tr>
                        `;
                    } else {
                        // 1. Hardware Devices Rows
                        assets.forEach((a, idx) => {
                            const tr = document.createElement('tr');
                            tr.className = 'return-item-row is-checked';
                            tr.id = `row_asset_${a.id || idx}`;
                            tr.innerHTML = `
                                <td style="text-align: center;">
                                    <input type="checkbox" class="return-item-check check-asset" value="${a.id}" data-tag="${escapeHtml(a.tag)}" checked>
                                </td>
                                <td>
                                    <div class="item-main-title">${escapeHtml(a.name || 'Hardware Asset')}</div>
                                    <div class="item-sub-title">${escapeHtml(a.category || 'Device')}${a.brand ? ` • ${escapeHtml(a.brand)}` : ''}${a.model ? ` (${escapeHtml(a.model)})` : ''}</div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="item-type-pill asset">Asset</span>
                                </td>
                                <td>
                                    <span class="return-tag-pill">${escapeHtml(a.tag || 'AST')}</span>
                                    ${a.serial ? `<div class="item-serial-pill">SN: <strong>${escapeHtml(a.serial)}</strong></div>` : ''}
                                </td>
                                <td style="text-align: center;" class="qty-col">1</td>
                                <td style="text-align: center;">
                                    <span class="status-indicator-pill return">Restock</span>
                                </td>
                            `;
                            pTbody.appendChild(tr);
                        });

                        // 2. Accessories Rows
                        accessories.forEach((ac, idx) => {
                            const acName = typeof ac === 'string' ? ac : (ac.name || ac.accessory_name || 'Item');
                            const acQty = (typeof ac === 'object' && ac.qty) ? ac.qty : 1;
                            const acCat = (typeof ac === 'object' && ac.category) ? ac.category : '';

                            const tr = document.createElement('tr');
                            tr.className = 'return-item-row is-checked';
                            tr.id = `row_acc_${idx}`;
                            tr.innerHTML = `
                                <td style="text-align: center;">
                                    <input type="checkbox" class="return-item-check check-acc" value="${escapeHtml(acName)}" data-qty="${escapeHtml(acQty)}" checked>
                                </td>
                                <td>
                                    <div class="item-main-title">${escapeHtml(acName)}</div>
                                    <div class="item-sub-title">${acCat ? escapeHtml(acCat) : 'Peripheral Kit'}</div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="item-type-pill accessory">Accessory</span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="dash-muted">—</span>
                                </td>
                                <td style="text-align: center;" class="qty-col">${escapeHtml(acQty)}</td>
                                <td style="text-align: center;">
                                    <span class="status-indicator-pill return">Restock</span>
                                </td>
                            `;
                            pTbody.appendChild(tr);
                        });

                        // Row click toggle
                        pTbody.querySelectorAll('.return-item-row').forEach(row => {
                            row.addEventListener('click', function (e) {
                                if (e.target.tagName.toLowerCase() === 'input' || e.target.closest('input')) return;
                                const chk = this.querySelector('.return-item-check');
                                if (chk) {
                                    chk.checked = !chk.checked;
                                    chk.dispatchEvent(new Event('change'));
                                }
                            });
                        });

                        // Attach change events to all row checkboxes
                        pTbody.querySelectorAll('.return-item-check').forEach(chk => {
                            chk.addEventListener('change', function () {
                                const row = this.closest('tr');
                                const pill = row ? row.querySelector('.status-indicator-pill') : null;
                                if (this.checked) {
                                    if (row) {
                                        row.classList.add('is-checked');
                                        row.classList.remove('is-excluded');
                                    }
                                    if (pill) {
                                        pill.className = 'status-indicator-pill return';
                                        pill.textContent = 'Restock';
                                    }
                                } else {
                                    if (row) {
                                        row.classList.remove('is-checked');
                                        row.classList.add('is-excluded');
                                    }
                                    if (pill) {
                                        pill.className = 'status-indicator-pill skip';
                                        pill.textContent = 'Excluded';
                                    }
                                }
                                updateChecklistSummary();
                            });
                        });
                    }
                }

                // Master Select All Checkbox Handler
                if (selectAllChk) {
                    selectAllChk.onchange = function () {
                        const isChecked = this.checked;
                        document.querySelectorAll('#previewItemsTbody .return-item-check').forEach(c => {
                            c.checked = isChecked;
                            const row = c.closest('tr');
                            const pill = row ? row.querySelector('.status-indicator-pill') : null;
                            if (isChecked) {
                                if (row) {
                                    row.classList.add('is-checked');
                                    row.classList.remove('is-excluded');
                                }
                                if (pill) {
                                    pill.className = 'status-indicator-pill return';
                                    pill.textContent = 'Restock';
                                }
                            } else {
                                if (row) {
                                    row.classList.remove('is-checked');
                                    row.classList.add('is-excluded');
                                }
                                if (pill) {
                                    pill.className = 'status-indicator-pill skip';
                                    pill.textContent = 'Excluded';
                                }
                            }
                        });
                        updateChecklistSummary();
                    };
                }

                updateChecklistSummary();

                // Render Original Notes
                if (pNotesWrap && pNotesText) {
                    if (notes && notes.trim().length > 0) {
                        pNotesText.textContent = notes;
                        pNotesWrap.style.display = 'block';
                    } else {
                        pNotesWrap.style.display = 'none';
                    }
                }

                if (allocPreviewBox) allocPreviewBox.style.display = 'block';
            });
        }

        // Process Return Form Submit
        if (processReturnForm) {
            processReturnForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const allocId = parseInt(selectActiveAlloc ? selectActiveAlloc.value : 0, 10);
                if (!allocId) {
                    showNotification('Please select an active handover allocation to return.', 'warning');
                    return;
                }

                // Check that at least one item is selected for return
                const checkedAssetElements = Array.from(document.querySelectorAll('#previewItemsTbody .check-asset:checked'));
                const checkedAccElements = Array.from(document.querySelectorAll('#previewItemsTbody .check-acc:checked'));

                const totalItemsChecked = checkedAssetElements.length + checkedAccElements.length;
                if (totalItemsChecked === 0) {
                    showNotification('Please check at least one asset or accessory being returned.', 'warning');
                    return;
                }

                const retDate = document.getElementById('modalReturnDate')?.value || new Date().toISOString().split('T')[0];
                const retCond = document.getElementById('modalReturnCondition')?.value || 'Good';
                const retDepot = document.getElementById('modalStorageDepot')?.value || 'Storage Depot (Rack A-01)';
                
                // Diagnostic check result compilation
                const allDiagChecks = Array.from(document.querySelectorAll('.diag-check'));
                const totalDiag = allDiagChecks.length;
                const passedDiag = allDiagChecks.filter(c => c.checked).length;
                const failedChecks = allDiagChecks.filter(c => !c.checked).map(c => c.dataset.label || c.id);

                let diagReport = '';
                if (totalDiag > 0) {
                    if (passedDiag === totalDiag) {
                        diagReport = 'All 12 diagnostic checks passed.';
                    } else {
                        diagReport = `Diagnostic: ${passedDiag}/${totalDiag} passed (Unchecked: ${failedChecks.join(', ')}).`;
                    }
                }

                const checkedAssetTags = checkedAssetElements.map(c => c.dataset.tag).filter(Boolean);
                const itemsReport = `Checked-in: ${checkedAssetElements.length} device(s)${checkedAssetTags.length ? ' (' + checkedAssetTags.join(', ') + ')' : ''}, ${checkedAccElements.length} accessory item(s).`;
                const customNotes = document.getElementById('modalReturnNotes')?.value?.trim();
                const retNotes = customNotes ? `${customNotes} | ${diagReport} ${itemsReport}` : `${diagReport} Condition: ${retCond}. Placed in ${retDepot}. ${itemsReport}`;

                const submitBtn = document.getElementById('submitProcessReturnBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner" style="display:inline-block;width:12px;height:12px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:6px;"></span> Restocking...';
                }

                fetch('api/asset_assignment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'return',
                        alloc_id: allocId,
                        return_date: retDate,
                        return_condition: retCond,
                        storage_location: retDepot,
                        return_notes: retNotes,
                        returned_asset_ids: checkedAssetElements.map(c => c.value),
                        returned_accessories: checkedAccElements.map(c => c.value)
                    })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            // Find allocation in activeAllocations
                            const foundIdx = activeAllocations.findIndex(a => a.id === allocId);
                            let movedItem = null;
                            if (foundIdx >= 0) {
                                movedItem = activeAllocations.splice(foundIdx, 1)[0];
                            }

                            if (movedItem) {
                                movedItem.custody_status = 'Returned';
                                movedItem.return_date = retDate;
                                movedItem.return_condition = retCond;
                                movedItem.return_notes = retNotes;
                                returns.unshift(movedItem);

                                // Remove from active select dropdown
                                if (selectActiveAlloc) {
                                    const optToRemove = selectActiveAlloc.querySelector(`option[value="${allocId}"]`);
                                    if (optToRemove) optToRemove.remove();
                                    selectActiveAlloc.value = '';
                                    if (allocPreviewBox) allocPreviewBox.style.display = 'none';
                                }
                            }

                            // Update in-stock count
                            const kpiAvail = document.getElementById('kpiAvailableDepot');
                            if (kpiAvail && res.available_stock_count) {
                                kpiAvail.textContent = res.available_stock_count;
                            }

                            closeModal(processReturnModal);
                            renderReturnsTable();
                            updateKPIs();

                            showNotification(
                                res.message || 'Equipment returned successfully and checked into available inventory stock!',
                                'success'
                            );
                        } else {
                            showNotification(res.message || 'Failed to process equipment return.', 'danger');
                        }
                    })
                    .catch(err => {
                        console.error('Return process error:', err);
                        showNotification('Network/server error while processing equipment return.', 'danger');
                    })
                    .finally(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Confirm & Check-In to Stock
                        `;
                        }
                    });
            });
        }

        // Drawer Close
        if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', window.closeReturnDrawer);
        if (drawerBackdrop) drawerBackdrop.addEventListener('click', window.closeReturnDrawer);

        // Drawer Action Buttons
        const drawerPrintBtn = document.getElementById('drawerPrintBtn');
        if (drawerPrintBtn) {
            drawerPrintBtn.addEventListener('click', () => {
                if (activeDrawerReturnId) printReturnReceipt(activeDrawerReturnId);
            });
        }

        const drawerAssignBtn = document.getElementById('drawerAssignBtn');
        if (drawerAssignBtn) {
            drawerAssignBtn.addEventListener('click', () => {
                window.location.href = 'asset_assignment.php';
            });
        }

        // Export to CSV
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                const list = getFilteredReturns();
                if (list.length === 0) {
                    showNotification('No return records to export.', 'warning');
                    return;
                }

                const headers = ['Return Slip', 'Employee Name', 'Emp Code', 'Department', 'Return Date', 'Condition', 'Notes'];
                const rows = list.map(r => [
                    r.slip_no,
                    r.employee_name,
                    r.emp_code,
                    r.department,
                    r.return_date,
                    r.return_condition || r.condition || 'Good',
                    r.return_notes || ''
                ]);

                const csv = [headers.join(','), ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))].join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `equipment_returns_${new Date().toISOString().slice(0, 10)}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showNotification('Return records exported successfully to CSV.', 'success');
            });
        }

        // Close on Escape or click outside
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(processReturnModal);
                window.closeReturnDrawer();
            }
        });

        if (processReturnModal) {
            processReturnModal.addEventListener('click', function (e) {
                if (e.target === processReturnModal) {
                    closeModal(processReturnModal);
                }
            });
        }
    }

    function init() {
        // Relocate modal directly to body to avoid stacking context issues
        if (processReturnModal && processReturnModal.parentElement !== document.body) {
            document.body.appendChild(processReturnModal);
        }

        renderReturnsTable();
        updateKPIs();
        bindEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
