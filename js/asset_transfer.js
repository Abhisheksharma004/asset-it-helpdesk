/**
 * VIROS IT Asset & Service Desk Portal
 * Asset Transfer & Relocation Controller (js/asset_transfer.js)
 */

(function () {
    'use strict';

    let transfers = Array.isArray(window.TRANSFER_DATA) ? window.TRANSFER_DATA : [];
    let activeCustodians = Array.isArray(window.ACTIVE_CUSTODIANS) ? window.ACTIVE_CUSTODIANS : [];
    let employeesList = Array.isArray(window.EMPLOYEES_DATA) ? window.EMPLOYEES_DATA : [];

    // Filter states
    let searchTerm = '';

    // Active Drawer State
    let activeDrawerTransferId = null;

    // DOM Elements
    const tbody = document.getElementById('transfersTbody');
    const searchInput = document.getElementById('transferSearchInput');
    const tableCountText = document.getElementById('transferTableCountText');
    const exportBtn = document.getElementById('exportTransfersBtn');

    // Modals & Drawer
    const transferModal = document.getElementById('transferModal');
    const openTransferBtn = document.getElementById('openInitiateTransferBtn');
    const closeTransferBtn = document.getElementById('closeTransferModalBtn');
    const cancelTransferBtn = document.getElementById('cancelTransferModalBtn');
    const transferForm = document.getElementById('transferForm');
    const selectSourceCust = document.getElementById('selectSourceCustodian');
    const sourcePreviewBox = document.getElementById('sourcePreviewBox');

    const transferDrawer = document.getElementById('transferDrawer');
    const drawerBackdrop = document.getElementById('transferDrawerBackdrop');
    const closeDrawerBtn = document.getElementById('closeTransferDrawerBtn');

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
        if (!name) return 'TR';
        const parts = name.trim().split(/\s+/);
        let inits = '';
        for (let i = 0; i < parts.length && i < 2; i++) {
            if (parts[i].length > 0) inits += parts[i][0].toUpperCase();
        }
        return inits || 'TR';
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
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Modal Wizard Step Switching
    window.switchTransferModalTab = function (tabKey) {
        document.querySelectorAll('#transferModal .modal-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabKey);
        });
        document.querySelectorAll('#transferModal .modal-tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        const target = document.getElementById(`modal_pane_${tabKey}`);
        if (target) target.classList.add('active');
    };

    // Filter Logic
    function getFilteredTransfers() {
        return transfers.filter(item => {
            // Search query
            if (searchTerm) {
                const q = searchTerm.toLowerCase();
                const slip = (item.transfer_slip_no || '').toLowerCase();
                const gate = (item.gate_pass_no || '').toLowerCase();
                const sName = (item.source_custodian?.name || '').toLowerCase();
                const tName = (item.target_custodian?.name || '').toLowerCase();
                const sDept = (item.source_custodian?.department || '').toLowerCase();
                const tDept = (item.target_custodian?.department || '').toLowerCase();
                const sLoc = (item.source_custodian?.location || '').toLowerCase();
                const tLoc = (item.target_custodian?.location || '').toLowerCase();
                const astMatch = (item.assets || []).some(a => 
                    (a.name || '').toLowerCase().includes(q) || (a.tag || '').toLowerCase().includes(q)
                );

                if (!slip.includes(q) && !gate.includes(q) && !sName.includes(q) && !tName.includes(q) && !sDept.includes(q) && !tDept.includes(q) && !sLoc.includes(q) && !tLoc.includes(q) && !astMatch) {
                    return false;
                }
            }

            return true;
        });
    }

    function renderTransfersTable() {
        if (!tbody) return;
        const list = getFilteredTransfers();
        tbody.innerHTML = '';

        if (tableCountText) {
            tableCountText.textContent = `Showing ${list.length} of ${transfers.length} transfer records`;
        }

        if (list.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div style="padding: 42px 20px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.7;">🔄</div>
                            <div style="font-size: 15px; font-weight: 700; color: var(--navy-primary);">${searchTerm ? 'No Transfer Records Found' : 'No Transfer Records Available'}</div>
                            <div style="font-size: 12.5px; margin-top: 4px;">${searchTerm ? 'No records match your search criteria.' : 'No asset transfer records currently available. Click "Initiate Asset Transfer" to create one.'}</div>
                            ${searchTerm ? '<button type="button" class="btn-secondary" style="margin-top: 14px; padding: 6px 14px;" onclick="const s = document.getElementById(\'transferSearchInput\'); if(s) { s.value=\'\'; s.dispatchEvent(new Event(\'input\')); }">Clear Search</button>' : ''}
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(t => {
            const totalAst = (t.assets || []).length;
            let totalAcc = 0;
            (t.accessories || []).forEach(ac => {
                totalAcc += is_array_or_obj(ac) ? intval_helper(ac.qty) : 1;
            });

            const srcName = t.source_custodian?.name || 'Staff';
            const srcCode = t.source_custodian?.emp_code || '';
            const srcDept = t.source_custodian?.department || 'General';
            const srcLoc = t.source_custodian?.location || '';

            const tgtName = t.target_custodian?.name || 'Staff';
            const tgtCode = t.target_custodian?.emp_code || '';
            const tgtDept = t.target_custodian?.department || 'General';
            const tgtLoc = t.target_custodian?.location || '';

            let statusClass = 'completed';
            if (t.status === 'In Transit') statusClass = 'in-transit';
            else if (t.status === 'Pending Sign-off') statusClass = 'pending';

            let typeClass = 'reassignment';
            if (t.transfer_type === 'Inter-Branch Relocation') typeClass = 'inter-branch';
            else if (t.transfer_type === 'Inter-Department') typeClass = 'inter-dept';
            else if (t.transfer_type === 'Temporary Project Assignment') typeClass = 'project';

            const tr = document.createElement('tr');
            tr.setAttribute('data-id', t.id);
            tr.innerHTML = `
                <td style="text-align: center;">
                    <input type="checkbox" class="custom-checkbox row-select-checkbox" value="${t.id}">
                </td>
                <td>
                    <div class="slip-cell">
                        <span class="transfer-slip-badge" onclick="openTransferDrawer(${t.id})">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line></svg>
                            ${escapeHtml(t.transfer_slip_no)}
                        </span>
                        <div class="slip-date">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            ${escapeHtml(t.transfer_date)}
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <div class="count-pill-wrap" style="display: flex; gap: 6px; margin-bottom: 4px;">
                            ${totalAst > 0 ? `
                                <span class="kitna-badge asset-kitna-badge">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                    <strong>${totalAst} Asset</strong>
                                </span>
                            ` : ''}
                            ${totalAcc > 0 ? `
                                <span class="kitna-badge acc-kitna-badge">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"></rect></svg>
                                    <strong>${totalAcc} Acc</strong>
                                </span>
                            ` : ''}
                        </div>
                        <div class="chips-compact-list">
                            ${(t.assets || []).map(a => `
                                <span class="chip-device" title="${escapeHtml(a.name)} (SN: ${escapeHtml(a.serial || '—')})">
                                    <span class="chip-tag">${escapeHtml(a.tag)}</span>
                                    <span class="chip-device-name">${escapeHtml(a.name)}</span>
                                </span>
                            `).join('')}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="route-custodian-cell">
                        <div class="route-avatar" style="background: ${getAvatarColor(srcName)};">
                            ${escapeHtml(getInitials(srcName))}
                        </div>
                        <div class="route-info">
                            <div class="route-name">
                                ${escapeHtml(srcName)}
                                ${srcCode ? `<span class="emp-code-badge">${escapeHtml(srcCode)}</span>` : ''}
                            </div>
                            <div class="route-meta">
                                ${escapeHtml(srcDept)} • ${escapeHtml(srcLoc)}
                            </div>
                        </div>
                    </div>
                </td>
                <td style="text-align: center;">
                    <span class="route-arrow-pill" title="Transferred to">➔</span>
                </td>
                <td>
                    <div class="route-custodian-cell">
                        <div class="route-avatar" style="background: ${getAvatarColor(tgtName)};">
                            ${escapeHtml(getInitials(tgtName))}
                        </div>
                        <div class="route-info">
                            <div class="route-name">
                                ${escapeHtml(tgtName)}
                                ${tgtCode ? `<span class="emp-code-badge">${escapeHtml(tgtCode)}</span>` : ''}
                            </div>
                            <div class="route-meta">
                                ${escapeHtml(tgtDept)} • ${escapeHtml(tgtLoc)}
                            </div>
                        </div>
                    </div>
                </td>
                <td style="text-align: center; white-space: nowrap;">
                    <div class="action-btn-group" style="display: inline-flex; flex-direction: row; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;">
                        <button type="button" class="action-icon-btn" title="View Transfer Details" onclick="openTransferDrawer(${t.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <button type="button" class="action-icon-btn" title="Print Transfer Slip" onclick="printTransferReceipt(${t.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function is_array_or_obj(val) {
        return typeof val === 'object' && val !== null;
    }
    function intval_helper(val) {
        return parseInt(val || 1, 10);
    }

    function updateKPIs() {
        const total = transfers.length;
        const comp = transfers.filter(t => t.status === 'Completed').length;
        const transit = transfers.filter(t => t.status === 'In Transit').length;

        const elTot = document.getElementById('kpiTotalTransfers');
        const elComp = document.getElementById('kpiCompletedTransfers');
        const elTrans = document.getElementById('kpiInTransitTransfers');

        if (elTot) elTot.textContent = total;
        if (elComp) elComp.textContent = comp;
        if (elTrans) elTrans.textContent = transit;
    }

    // Detail Slide-over Drawer
    window.openTransferDrawer = function (id) {
        const t = transfers.find(item => item.id === id);
        if (!t) return;
        activeDrawerTransferId = id;

        const title = document.getElementById('drawerTransferSlipTitle');
        const srcPerson = document.getElementById('drawerSrcPerson');
        const srcDept = document.getElementById('drawerSrcDept');
        const tgtPerson = document.getElementById('drawerTgtPerson');
        const tgtDept = document.getElementById('drawerTgtDept');
        const dateEl = document.getElementById('drawerDate');
        const listEl = document.getElementById('drawerEquipmentList');
        const notesEl = document.getElementById('drawerNotes');
        const countBadge = document.getElementById('drawerItemCountBadge');

        if (title) title.textContent = t.transfer_slip_no;
        if (srcPerson) srcPerson.textContent = `${t.source_custodian?.name} (${t.source_custodian?.emp_code})`;
        if (srcDept) srcDept.textContent = t.source_custodian?.department ? `${t.source_custodian.department}${t.source_custodian.location ? ' • ' + t.source_custodian.location : ''}` : 'General';

        if (tgtPerson) tgtPerson.textContent = `${t.target_custodian?.name} (${t.target_custodian?.emp_code})`;
        if (tgtDept) tgtDept.textContent = t.target_custodian?.department || 'General';

        if (dateEl) dateEl.textContent = t.transfer_date;
        if (notesEl) notesEl.textContent = t.reason || 'Routine reallocation.';

        if (listEl) {
            listEl.innerHTML = '';
            const allItems = [...(t.assets || []), ...(t.accessories || [])];
            if (countBadge) countBadge.textContent = `${allItems.length} Item(s)`;

            (t.assets || []).forEach(a => {
                const row = document.createElement('div');
                row.style.cssText = 'background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between;';
                const snText = (a.serial && a.serial !== '—' && a.serial !== '-') ? a.serial : '—';
                row.innerHTML = `
                    <div>
                        <div style="font-weight: 700; font-size: 13px; color: var(--navy-primary);">${escapeHtml(a.name)}</div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Tag: <strong>${escapeHtml(a.tag)}</strong> • SN: <strong style="font-family: monospace; color: var(--text-primary); font-size: 11.5px;">${escapeHtml(snText)}</strong></div>
                    </div>
                    <span class="item-type-pill asset" style="font-size: 10.5px; padding: 2px 7px; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-weight: 700;">Asset</span>
                `;
                listEl.appendChild(row);
            });

            (t.accessories || []).forEach(ac => {
                const acName = typeof ac === 'string' ? ac : (ac.name || 'Accessory');
                const acQty = typeof ac === 'object' && ac.qty ? ac.qty : 1;
                const row = document.createElement('div');
                row.style.cssText = 'background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between;';
                row.innerHTML = `
                    <div>
                        <div style="font-weight: 700; font-size: 13px; color: var(--navy-primary);">${escapeHtml(acName)}</div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Quantity: <strong>${escapeHtml(acQty)}</strong> unit(s)</div>
                    </div>
                    <span class="item-type-pill accessory" style="font-size: 10.5px; padding: 2px 7px; background: #f3e8ff; color: #7e22ce; border-radius: 4px; font-weight: 700;">Accessory</span>
                `;
                listEl.appendChild(row);
            });
        }

        if (transferDrawer) transferDrawer.classList.add('open');
        if (drawerBackdrop) drawerBackdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeTransferDrawer = function () {
        if (transferDrawer) transferDrawer.classList.remove('open');
        if (drawerBackdrop) drawerBackdrop.classList.remove('active');
        document.body.style.overflow = '';
        activeDrawerTransferId = null;
    };

    window.printTransferReceipt = function (id) {
        const t = transfers.find(item => item.id === id);
        if (!t) return;
        showNotification(`Generating Transfer Slip for ${t.transfer_slip_no}... Ready to print.`, 'info');
        setTimeout(() => {
            window.print();
        }, 500);
    };

    function bindEvents() {
        // Search
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                searchTerm = this.value.trim();
                renderTransfersTable();
            });
        }

        // Filter out Source Custodian from Target Recipient dropdown
        function filterTargetEmployees(sourceCustodianEmpCode, sourceCustodianEmpName, sourceCustodianEmpId) {
            const selectTargetEmp = document.getElementById('selectTargetEmployee');
            if (!selectTargetEmp) return;

            const targetPreviewBox = document.getElementById('targetPreviewBox');
            const normSrcCode = (sourceCustodianEmpCode || '').trim().toLowerCase();
            const normSrcName = (sourceCustodianEmpName || '').trim().toLowerCase();
            const normSrcId = (sourceCustodianEmpId !== undefined && sourceCustodianEmpId !== null && String(sourceCustodianEmpId).trim() !== '' && String(sourceCustodianEmpId) !== '0')
                ? String(sourceCustodianEmpId).trim() 
                : '';

            let hasClearedCurrentTarget = false;

            Array.from(selectTargetEmp.options).forEach(opt => {
                if (!opt.value) return; // Keep placeholder

                const optCode = (opt.dataset.code || '').trim().toLowerCase();
                const optName = (opt.dataset.name || '').trim().toLowerCase();
                const optId = (opt.dataset.id || opt.value || '').trim();

                const isMatch = (normSrcCode && optCode && normSrcCode === optCode) ||
                                (normSrcId && optId && normSrcId === optId) ||
                                (normSrcName && optName && normSrcName === optName);

                if (isMatch) {
                    opt.hidden = true;
                    opt.dataset.exclude = 'true';
                    opt.disabled = true;

                    if (selectTargetEmp.value === opt.value) {
                        selectTargetEmp.value = '';
                        hasClearedCurrentTarget = true;
                    }
                } else {
                    opt.hidden = false;
                    opt.dataset.exclude = 'false';
                    opt.disabled = false;
                }
            });

            if (hasClearedCurrentTarget && targetPreviewBox) {
                targetPreviewBox.style.display = 'none';
            }

            // Sync searchable-select custom dropdown
            if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
                window.SearchableSelect.sync(selectTargetEmp);
            }
        }

        // Modal triggers
        if (openTransferBtn) {
            openTransferBtn.addEventListener('click', () => {
                window.switchTransferModalTab('source');
                const curSrc = selectSourceCust ? selectSourceCust.value : '';
                if (!curSrc) {
                    filterTargetEmployees('', '', '');
                }
                openModal(transferModal);
            });
        }

        if (closeTransferBtn) closeTransferBtn.addEventListener('click', () => closeModal(transferModal));
        if (cancelTransferBtn) cancelTransferBtn.addEventListener('click', () => closeModal(transferModal));

        // Modal Nav Tabs
        document.querySelectorAll('#transferModal .modal-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (this.dataset.tab) window.switchTransferModalTab(this.dataset.tab);
            });
        });

        // Source Custodian Selection in Modal
        if (selectSourceCust) {
            selectSourceCust.addEventListener('change', function () {
                const val = this.value;
                if (!val) {
                    if (sourcePreviewBox) sourcePreviewBox.style.display = 'none';
                    filterTargetEmployees('', '', '');
                    return;
                }

                const cust = activeCustodians.find(c => c.emp_key === val || c.emp_code === val);
                const opt = this.selectedOptions[0];

                const empName = (cust && cust.employee_name) || opt?.dataset.empName || 'Staff';
                const empCode = (cust && cust.emp_code) || opt?.dataset.empCode || 'EMP';
                const dept = (cust && cust.department) || opt?.dataset.dept || 'General';
                const desig = (cust && cust.designation) || opt?.dataset.desig || 'Staff';
                const loc = (cust && cust.location) || opt?.dataset.loc || 'HO DELHI';
                const empId = cust?.employee_id || opt?.dataset.empId;

                // Dynamically remove source custodian from Target Recipient dropdown
                filterTargetEmployees(empCode, empName, empId);

                let slips = (cust && cust.slips) || [];
                let assets = (cust && cust.all_assets) || [];
                let accessories = (cust && cust.all_accessories) || [];

                // Preview DOM
                const sName = document.getElementById('sourceName');
                const sCode = document.getElementById('sourceCode');
                const sMeta = document.getElementById('sourceMeta');
                const sBadge = document.getElementById('sourceBranchBadge');
                const sAvatar = document.getElementById('sourceAvatar');
                const sSlipsBadges = document.getElementById('sourceSlipsBadges');
                const tabBadge = document.getElementById('transferTabBadge');
                const tbodyItems = document.getElementById('transferItemsTbody');
                const selectionSummary = document.getElementById('transferSelectionSummary');
                const selectAllChk = document.getElementById('selectAllTransferItems');

                if (sName) sName.textContent = empName;
                if (sCode) sCode.textContent = empCode;
                if (sMeta) sMeta.textContent = `${dept} • ${desig}`;
                if (sBadge) sBadge.textContent = `Branch: ${loc}`;
                if (sAvatar) {
                    sAvatar.textContent = getInitials(empName);
                    sAvatar.style.background = getAvatarColor(empName);
                }
                if (sSlipsBadges) {
                    sSlipsBadges.innerHTML = slips.length > 0 
                        ? slips.map(s => `<span class="slip-badge" style="background:#e0f2fe;color:#0284c7;font-weight:700;font-size:11px;padding:2px 7px;border-radius:4px;border:1px solid #bae6fd;">${escapeHtml(s)}</span>`).join('')
                        : '<span style="color:#94a3b8;font-style:italic;">No active slips</span>';
                }

                const totalItemsCount = assets.length + accessories.length;
                if (tabBadge) {
                    tabBadge.textContent = totalItemsCount;
                    tabBadge.style.display = totalItemsCount > 0 ? 'inline-block' : 'none';
                }

                // Render Equipment Checklist
                if (tbodyItems) {
                    tbodyItems.innerHTML = '';
                    if (totalItemsCount === 0) {
                        tbodyItems.innerHTML = `
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; font-style: italic;">
                                    No hardware assets or accessories assigned to this employee.
                                </td>
                            </tr>
                        `;
                    } else {
                        // Assets
                        assets.forEach((a, idx) => {
                            const tr = document.createElement('tr');
                            tr.className = 'return-item-row is-checked';
                            tr.innerHTML = `
                                <td style="text-align: center;">
                                    <input type="checkbox" class="transfer-item-check check-asset" value="${a.id}" data-tag="${escapeHtml(a.tag || '')}" data-name="${escapeHtml(a.name || '')}" data-slip="${escapeHtml(a.slip_no || 'SLIP')}" data-serial="${escapeHtml(a.serial || '')}" data-brand="${escapeHtml(a.brand || '')}" data-model="${escapeHtml(a.model || '')}" data-category="${escapeHtml(a.category || '')}" checked>
                                </td>
                                <td>
                                    <span class="slip-badge" style="background:#e0f2fe;color:#0369a1;padding:2px 7px;border-radius:4px;font-weight:700;font-size:11px;border:1px solid #bae6fd;display:inline-block;">
                                        ${escapeHtml(a.slip_no || 'SLIP')}
                                    </span>
                                </td>
                                <td>
                                    <div class="item-main-title" style="font-weight:700;font-size:13px;color:var(--navy-primary);">${escapeHtml(a.name || 'Device')}</div>
                                    <div class="item-sub-title" style="font-size:11px;color:var(--text-muted);">${escapeHtml(a.category || 'Hardware')}</div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="item-type-pill asset" style="font-size:10.5px;padding:2px 6px;background:#e0f2fe;color:#0369a1;border-radius:4px;font-weight:700;">Asset</span>
                                </td>
                                <td>
                                    <span style="font-weight:700;font-size:11.5px;color:#0093a7;">${escapeHtml(a.tag || 'AST')}</span>
                                    <div style="font-size:11px;color:var(--text-secondary);font-weight:600;margin-top:2px;">
                                        SN: <span style="font-family:monospace;color:var(--navy-primary);font-weight:700;">${escapeHtml((a.serial && a.serial !== '—' && a.serial !== '-') ? a.serial : '—')}</span>
                                    </div>
                                </td>
                                <td style="text-align: center; font-weight:700;">1</td>
                                <td style="text-align: center;">
                                    <span class="status-indicator-pill return" style="font-size:11px;font-weight:700;color:#059669;background:#ecfdf5;padding:2px 8px;border-radius:10px;">Transfer</span>
                                </td>
                            `;
                            tbodyItems.appendChild(tr);
                        });

                        // Accessories
                        accessories.forEach((ac, idx) => {
                            const acName = typeof ac === 'string' ? ac : (ac.name || 'Item');
                            const acQty = typeof ac === 'object' && ac.qty ? ac.qty : 1;
                            const acSlip = typeof ac === 'object' && ac.slip_no ? ac.slip_no : 'SLIP';

                            const tr = document.createElement('tr');
                            tr.className = 'return-item-row is-checked';
                            tr.innerHTML = `
                                <td style="text-align: center;">
                                    <input type="checkbox" class="transfer-item-check check-acc" value="${escapeHtml(acName)}" data-qty="${escapeHtml(acQty)}" data-slip="${escapeHtml(acSlip)}" checked>
                                </td>
                                <td>
                                    <span class="slip-badge" style="background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:4px;font-weight:700;font-size:11px;border:1px solid #cbd5e1;display:inline-block;">
                                        ${escapeHtml(acSlip)}
                                    </span>
                                </td>
                                <td>
                                    <div class="item-main-title" style="font-weight:700;font-size:13px;color:var(--navy-primary);">${escapeHtml(acName)}</div>
                                    <div class="item-sub-title" style="font-size:11px;color:var(--text-muted);">Peripheral Kit</div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="item-type-pill accessory" style="font-size:10.5px;padding:2px 6px;background:#f3e8ff;color:#7e22ce;border-radius:4px;font-weight:700;">Accessory</span>
                                </td>
                                <td style="text-align: center; color:#94a3b8;">—</td>
                                <td style="text-align: center; font-weight:700;">${escapeHtml(acQty)}</td>
                                <td style="text-align: center;">
                                    <span class="status-indicator-pill return" style="font-size:11px;font-weight:700;color:#059669;background:#ecfdf5;padding:2px 8px;border-radius:10px;">Transfer</span>
                                </td>
                            `;
                            tbodyItems.appendChild(tr);
                        });
                    }
                }

                function updateSummary() {
                    const checks = Array.from(document.querySelectorAll('#transferItemsTbody .transfer-item-check'));
                    const checked = checks.filter(c => c.checked).length;
                    if (selectionSummary) {
                        selectionSummary.textContent = `${checked} of ${checks.length} items selected for transfer`;
                    }
                    if (selectAllChk) {
                        selectAllChk.checked = checks.length > 0 && checked === checks.length;
                    }
                }

                // Attach change listeners to items
                tbodyItems.querySelectorAll('.transfer-item-check').forEach(chk => {
                    chk.addEventListener('change', updateSummary);
                });

                if (selectAllChk) {
                    selectAllChk.onchange = function () {
                        tbodyItems.querySelectorAll('.transfer-item-check').forEach(chk => {
                            chk.checked = selectAllChk.checked;
                        });
                        updateSummary();
                    };
                }

                updateSummary();
                if (sourcePreviewBox) sourcePreviewBox.style.display = 'block';
            });
        }

        // Target Recipient Employee Selection in Modal Step 3
        const selectTargetEmp = document.getElementById('selectTargetEmployee');
        const targetPreviewBox = document.getElementById('targetPreviewBox');
        if (selectTargetEmp) {
            selectTargetEmp.addEventListener('change', function () {
                const val = this.value;
                if (!val) {
                    if (targetPreviewBox) targetPreviewBox.style.display = 'none';
                    return;
                }

                const opt = this.selectedOptions[0];
                const empName = opt?.dataset.name || 'Recipient Employee';
                const empCode = opt?.dataset.code || 'EMP';
                const dept = opt?.dataset.dept || 'General';
                const desig = opt?.dataset.desig || 'Staff';
                const loc = opt?.dataset.loc || 'HO DELHI';

                const tName = document.getElementById('targetName');
                const tCode = document.getElementById('targetCode');
                const tMeta = document.getElementById('targetMeta');
                const tBadge = document.getElementById('targetBranchBadge');
                const tAvatar = document.getElementById('targetAvatar');

                if (tName) tName.textContent = empName;
                if (tCode) tCode.textContent = empCode;
                if (tMeta) tMeta.textContent = `${dept} • ${desig}`;
                if (tBadge) tBadge.textContent = `Branch: ${loc}`;
                if (tAvatar) {
                    tAvatar.textContent = getInitials(empName);
                    tAvatar.style.background = getAvatarColor(empName);
                }

                // Extra safety: Check if matches selected source custodian
                const srcVal = selectSourceCust ? selectSourceCust.value : '';
                const curSrcCust = activeCustodians.find(c => c.emp_key === srcVal || c.emp_code === srcVal);
                const curSrcCode = (curSrcCust?.emp_code || '').trim().toLowerCase();
                const curSrcName = (curSrcCust?.employee_name || '').trim().toLowerCase();
                const curSrcId = curSrcCust?.employee_id ? String(curSrcCust.employee_id) : '';

                if (
                    (curSrcCode && empCode && curSrcCode === empCode.trim().toLowerCase()) ||
                    (curSrcId && val && curSrcId === val.trim()) ||
                    (curSrcName && empName && curSrcName === empName.trim().toLowerCase())
                ) {
                    showNotification('Source and Target employee cannot be the same.', 'warning');
                    this.value = '';
                    if (targetPreviewBox) targetPreviewBox.style.display = 'none';
                    if (window.SearchableSelect) window.SearchableSelect.sync(this);
                    return;
                }

                if (targetPreviewBox) targetPreviewBox.style.display = 'block';
            });
        }

        // Form Submit
        if (transferForm) {
            transferForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const srcVal = selectSourceCust ? selectSourceCust.value : '';
                const tgtEmpId = document.getElementById('selectTargetEmployee')?.value;

                if (!srcVal) {
                    showNotification('Please select a source employee custodian in Step 1.', 'warning');
                    window.switchTransferModalTab('source');
                    return;
                }
                if (!tgtEmpId) {
                    showNotification('Please select a target recipient employee in Step 3.', 'warning');
                    window.switchTransferModalTab('destination');
                    return;
                }

                const checkedAssets = Array.from(document.querySelectorAll('#transferItemsTbody .check-asset:checked')).map(c => ({
                    id: parseInt(c.value, 10),
                    tag: c.dataset.tag,
                    name: c.dataset.name,
                    slip_no: c.dataset.slip,
                    serial: c.dataset.serial || '',
                    brand: c.dataset.brand || '',
                    model: c.dataset.model || '',
                    category: c.dataset.category || ''
                }));

                const checkedAcc = Array.from(document.querySelectorAll('#transferItemsTbody .check-acc:checked')).map(c => ({
                    name: c.value,
                    qty: parseInt(c.dataset.qty || 1, 10),
                    slip_no: c.dataset.slip
                }));

                if (checkedAssets.length === 0 && checkedAcc.length === 0) {
                    showNotification('Please select at least one asset or accessory to transfer in Step 2.', 'warning');
                    window.switchTransferModalTab('equipment');
                    return;
                }

                const srcCust = activeCustodians.find(c => c.emp_key === srcVal || c.emp_code === srcVal);
                const srcOpt = selectSourceCust.selectedOptions[0];
                const tgtOpt = document.getElementById('selectTargetEmployee')?.selectedOptions[0];

                const submitBtn = document.getElementById('submitTransferBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner" style="display:inline-block;width:12px;height:12px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:6px;"></span> Transferring...';
                }

                const payload = {
                    action: 'transfer',
                    source_employee_id: srcCust?.employee_id || (srcOpt?.dataset.empId ? parseInt(srcOpt.dataset.empId, 10) : null),
                    source_employee_name: srcCust?.employee_name || srcOpt?.dataset.empName || 'Staff',
                    source_emp_code: srcCust?.emp_code || srcOpt?.dataset.empCode || 'EMP',
                    source_department: srcCust?.department || srcOpt?.dataset.dept || 'General',
                    source_designation: srcCust?.designation || srcOpt?.dataset.desig || 'Staff',
                    source_location: srcCust?.location || srcOpt?.dataset.loc || 'Corporate HQ',

                    target_employee_id: parseInt(tgtOpt.value, 10),
                    target_employee_name: tgtOpt.dataset.name || 'Recipient Employee',
                    target_emp_code: tgtOpt.dataset.code || 'EMP',
                    target_department: tgtOpt.dataset.dept || 'General',
                    target_designation: tgtOpt.dataset.desig || 'Staff',
                    target_location: tgtOpt.dataset.loc || 'Corporate HQ',

                    transfer_date: document.getElementById('modalTransferDate')?.value || new Date().toISOString().split('T')[0],
                    reason: document.getElementById('modalTransferNotes')?.value || '',
                    assets: checkedAssets,
                    accessories: checkedAcc
                };

                fetch('api/asset_transfer.php?action=transfer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        transfers.unshift(data.data);
                        renderTransfersTable();
                        updateKPIs();
                        closeModal(transferModal);
                        showNotification(data.message || `Asset transfer completed successfully under receipt ${data.data.transfer_slip_no}!`, 'success');

                        // Reload page after short delay so updated equipment assignments and custodians are re-indexed
                        setTimeout(() => {
                            window.location.reload();
                        }, 1200);
                    } else {
                        showNotification(data.message || 'Failed to complete asset transfer.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Transfer API Error:', err);
                    showNotification('Network or server error while completing transfer.', 'error');
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Confirm &amp; Complete Transfer
                        `;
                    }
                });
            });
        }

        // Drawer Close
        if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', window.closeTransferDrawer);
        if (drawerBackdrop) drawerBackdrop.addEventListener('click', window.closeTransferDrawer);

        // Export to CSV
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                const list = getFilteredTransfers();
                if (list.length === 0) {
                    showNotification('No transfer records to export.', 'warning');
                    return;
                }
                const headers = ['Transfer Slip', 'Date', 'Source Custodian', 'Target Custodian', 'Assets Count', 'Accessories Count', 'Reason'];
                const rows = list.map(t => [
                    t.transfer_slip_no,
                    t.transfer_date,
                    `${t.source_custodian?.name} (${t.source_custodian?.emp_code})`,
                    `${t.target_custodian?.name} (${t.target_custodian?.emp_code})`,
                    (t.assets || []).length,
                    (t.accessories || []).length,
                    t.reason || ''
                ]);
                const csv = [headers.join(','), ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))].join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `asset_transfers_${new Date().toISOString().slice(0, 10)}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showNotification('Transfer records exported successfully to CSV.', 'success');
            });
        }

        // Escape key close
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(transferModal);
                window.closeTransferDrawer();
            }
        });
    }

    function init() {
        if (transferModal && transferModal.parentElement !== document.body) {
            document.body.appendChild(transferModal);
        }
        renderTransfersTable();
        updateKPIs();
        bindEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
