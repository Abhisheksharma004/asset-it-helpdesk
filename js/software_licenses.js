/**
 * VIROS IT Asset & Service Desk Portal
 * Software & Licenses Workspace JavaScript (js/software_licenses.js)
 */

(function () {
    'use strict';

    // State Variables
    let licenses = Array.isArray(window.INITIAL_LICENSES_DATA) ? [...window.INITIAL_LICENSES_DATA] : [];
    let currentTab = 'all';
    let activeDrawerLicId = null;

    // DOM Elements
    const tbody = document.getElementById('licensesTbody');
    const searchInput = document.getElementById('licSearchInput');
    const categoryFilter = document.getElementById('licCategoryFilter');
    const typeFilter = document.getElementById('licTypeFilter');
    const statusFilter = document.getElementById('licStatusFilter');
    const resetBtn = document.getElementById('resetLicFiltersBtn');
    const countText = document.getElementById('licTableCountText');
    const selectAllCheckbox = document.getElementById('selectAllLic');
    const exportBtn = document.getElementById('exportLicensesBtn');

    // Drawer Elements
    const drawerOverlay = document.getElementById('licenseDrawerOverlay');
    const drawerLogoBadge = document.getElementById('drawerLogoBadge');
    const drawerSoftwareTitle = document.getElementById('drawerSoftwareTitle');
    const drawerSoftwarePublisher = document.getElementById('drawerSoftwarePublisher');
    const drawerStatModel = document.getElementById('drawerStatModel');
    const drawerStatStatus = document.getElementById('drawerStatStatus');
    const drawerStatCost = document.getElementById('drawerStatCost');
    const drawerLicType = document.getElementById('drawerLicType');
    const drawerLicCategory = document.getElementById('drawerLicCategory');
    const drawerLicenseKey = document.getElementById('drawerLicenseKey');
    const drawerPoNumber = document.getElementById('drawerPoNumber');
    const drawerVendor = document.getElementById('drawerVendor');
    const drawerPurchaseDate = document.getElementById('drawerPurchaseDate');
    const drawerExpiryDate = document.getElementById('drawerExpiryDate');
    const drawerCostPerSeat = document.getElementById('drawerCostPerSeat');
    const drawerTotalCost = document.getElementById('drawerTotalCost');
    const drawerStatusBadge = document.getElementById('drawerStatusBadge');
    const drawerNotes = document.getElementById('drawerNotes');
    const drawerRenewalLogsContainer = document.getElementById('drawerRenewalLogsContainer');
    const drawerRenewalLogCount = document.getElementById('drawerRenewalLogCount');

    // Modal Elements (Add/Edit)
    const addModalOverlay = document.getElementById('addLicenseModalOverlay');
    const licenseForm = document.getElementById('licenseForm');
    const openAddBtn = document.getElementById('openAddLicenseBtn');

    // Renewal Modal Elements
    const renewModalOverlay = document.getElementById('renewLicenseModalOverlay');
    const renewForm = document.getElementById('renewLicenseForm');
    const openRenewBtn = document.getElementById('openRenewLicenseBtn');
    const renewSelectLicense = document.getElementById('renewSelectLicense');
    const renewSummaryName = document.getElementById('renewSummaryName');
    const renewSummaryPublisher = document.getElementById('renewSummaryPublisher');
    const renewSummaryCurrentExpiry = document.getElementById('renewSummaryCurrentExpiry');
    const renewSummaryCurrentCost = document.getElementById('renewSummaryCurrentCost');
    const renewLogoBadge = document.getElementById('renewLogoBadge');
    const renewNewExpiryDate = document.getElementById('renewNewExpiryDate');
    const renewCost = document.getElementById('renewCost');
    const renewPoNumber = document.getElementById('renewPoNumber');
    const renewVendor = document.getElementById('renewVendor');
    const renewLicenseKey = document.getElementById('renewLicenseKey');
    const renewStatus = document.getElementById('renewStatus');
    const renewNotes = document.getElementById('renewNotes');
    const renewLicenseId = document.getElementById('renewLicenseId');

    // Renewal Logs Modal Elements
    const openRenewalLogsBtn = document.getElementById('openRenewalLogsBtn');
    const allRenewalLogsModalOverlay = document.getElementById('allRenewalLogsModalOverlay');
    const allRenewalLogsTableBody = document.getElementById('allRenewalLogsTableBody');
    const renewalLogSearchInput = document.getElementById('renewalLogSearchInput');
    let cachedAllRenewalLogs = [];

    // =========================================================================
    // DYNAMIC EXPIRY & STATUS CALCULATION (TODAY'S DATE COMPARISON)
    // =========================================================================
    function computeLicenseExpiry(dateStr, fallbackStatus = 'Active') {
        if (!dateStr || dateStr.toLowerCase() === 'perpetual' || dateStr.toLowerCase() === 'lifetime') {
            return {
                status: 'Active',
                days: null,
                label: 'Lifetime Perpetual',
                badgeClass: 'safe',
                statusBadgeClass: 'lic-badge-active'
            };
        }

        const parts = String(dateStr).trim().split('-');
        let expDate;
        if (parts.length === 3) {
            expDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        } else {
            expDate = new Date(dateStr);
        }

        if (isNaN(expDate.getTime())) {
            return {
                status: fallbackStatus || 'Active',
                days: null,
                label: dateStr,
                badgeClass: 'safe',
                statusBadgeClass: 'lic-badge-active'
            };
        }

        const today = new Date();
        const todayMidnight = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const expMidnight = new Date(expDate.getFullYear(), expDate.getMonth(), expDate.getDate());

        const diffTime = expMidnight.getTime() - todayMidnight.getTime();
        const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays < 0) {
            const absDays = Math.abs(diffDays);
            return {
                status: 'Expired',
                days: diffDays,
                label: `Expired ${absDays === 1 ? '1 day' : absDays + ' days'} ago`,
                badgeClass: 'expired',
                statusBadgeClass: 'lic-badge-expired'
            };
        } else if (diffDays === 0) {
            return {
                status: 'Expiring Soon',
                days: 0,
                label: 'Expires Today!',
                badgeClass: 'warn',
                statusBadgeClass: 'lic-badge-expiring'
            };
        } else if (diffDays <= 30) {
            return {
                status: 'Expiring Soon',
                days: diffDays,
                label: `Expires in ${diffDays} day${diffDays === 1 ? '' : 's'}`,
                badgeClass: 'warn',
                statusBadgeClass: 'lic-badge-expiring'
            };
        } else {
            return {
                status: 'Active',
                days: diffDays,
                label: `Active (${diffDays} days left)`,
                badgeClass: 'safe',
                statusBadgeClass: 'lic-badge-active'
            };
        }
    }

    // =========================================================================
    // INITIALIZATION
    // =========================================================================
    function init() {
        bindEvents();
        renderTable();
        updateKPIs();
    }

    // =========================================================================
    // RENDER LICENSES TABLE
    // =========================================================================
    function getFilteredList() {
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const cat = categoryFilter ? categoryFilter.value : 'all';
        const type = typeFilter ? typeFilter.value : 'all';
        const status = statusFilter ? statusFilter.value : 'all';

        return licenses.filter(lic => {
            const expInfo = computeLicenseExpiry(lic.expiry_date, lic.status);

            // Tab filter
            if (currentTab === 'saas' && !lic.license_type.toLowerCase().includes('saas')) return false;
            if (currentTab === 'perpetual' && !lic.license_type.toLowerCase().includes('perpetual') && !lic.license_type.toLowerCase().includes('oem')) return false;
            if (currentTab === 'expiring' && expInfo.status !== 'Expiring Soon') return false;
            if (currentTab === 'expired' && expInfo.status !== 'Expired') return false;

            // Dropdown filters
            if (cat !== 'all' && lic.category !== cat) return false;
            if (type !== 'all' && lic.license_type !== type) return false;
            if (status !== 'all' && expInfo.status !== status) return false;

            // Search query
            if (query) {
                const searchCorpus = [
                    lic.name,
                    lic.publisher,
                    lic.category,
                    lic.license_type,
                    lic.license_key,
                    lic.version,
                    lic.vendor,
                    lic.po_number,
                    expInfo.status,
                    expInfo.label
                ].join(' ').toLowerCase();

                if (!searchCorpus.includes(query)) return false;
            }

            return true;
        });
    }

    function renderTable() {
        if (!tbody) return;
        const list = getFilteredList();

        tbody.innerHTML = '';

        if (countText) {
            countText.textContent = `Showing ${list.length} of ${licenses.length} software licenses`;
        }

        if (list.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 8px; opacity: 0.5;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <div style="font-weight: 700; font-size: 14px; color: #334155;">No Software Licenses Found</div>
                        <div style="font-size: 12.5px; margin-top: 4px;">Try modifying your search or filter criteria.</div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(lic => {
            const expInfo = computeLicenseExpiry(lic.expiry_date, lic.status);
            lic.status = expInfo.status; // keep dynamic status synced

            let brandClass = 'brand-default';
            if (lic.brand_code === 'msft') brandClass = 'brand-msft';
            else if (lic.brand_code === 'adobe') brandClass = 'brand-adobe';
            else if (lic.brand_code === 'jb') brandClass = 'brand-jb';
            else if (lic.brand_code === 'slack') brandClass = 'brand-slack';
            else if (lic.brand_code === 'github') brandClass = 'brand-github';
            else if (lic.brand_code === 'atlassian') brandClass = 'brand-atlassian';
            else if (lic.brand_code === 'crowdstrike') brandClass = 'brand-crowdstrike';

            const logoLetters = escapeHtml((lic.name || 'SW').substring(0, 2).toUpperCase());

            const isDueAction = (expInfo.status === 'Expiring Soon' || expInfo.status === 'Expired');

            const tr = document.createElement('tr');
            tr.setAttribute('data-lic-id', lic.id);
            tr.innerHTML = `
                <td>
                    <input type="checkbox" class="custom-checkbox row-select-checkbox" value="${lic.id}">
                </td>
                <td>
                    <div class="software-cell">
                        <div class="software-logo-badge ${brandClass}">
                            ${logoLetters}
                        </div>
                        <div class="software-info">
                            <div class="software-info-title">
                                <a href="javascript:void(0)" onclick="openLicenseDrawer(${lic.id})" style="color: inherit; text-decoration: none;">
                                    ${escapeHtml(lic.name)}
                                </a>
                            </div>
                            <div class="software-info-sub">
                                <span>${escapeHtml(lic.publisher)}</span>
                                <span>&bull;</span>
                                <span class="lic-category-tag">${escapeHtml(lic.category)}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="type-pill type-pill-saas">
                        ${escapeHtml(lic.license_type)}
                    </span>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                        ${escapeHtml(lic.version || 'Commercial')}
                    </div>
                </td>
                <td>
                    <div class="license-key-wrapper" title="Click to copy key">
                        <span>${escapeHtml(lic.license_key || '—')}</span>
                        <button type="button" class="btn-copy-inline" onclick="copyLicenseKey('${escapeJs(lic.license_key)}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </td>
                <td>
                    <div class="expiry-cell">
                        <div class="date-str">${escapeHtml(lic.expiry_date || '—')}</div>
                        <span class="days-pill ${expInfo.badgeClass}">
                            ${isDueAction ? '&#9888; ' : ''}${escapeHtml(expInfo.label)}
                        </span>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 12.5px; color: #0f172a;">
                        ₹${formatNumber(lic.total_cost || 0)}
                    </div>
                    <div style="font-size: 11px; color: #64748b;">
                        ${escapeHtml(lic.vendor || 'Direct')}
                    </div>
                </td>
                <td>
                    <span class="lic-badge ${expInfo.statusBadgeClass}">
                        <span class="dot"></span>
                        ${escapeHtml(expInfo.status)}
                    </span>
                </td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        <button type="button" class="action-icon-btn btn-view" title="View License Details" onclick="openLicenseDrawer(${lic.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <button type="button" class="action-icon-btn btn-renew ${isDueAction ? 'due-pulse' : ''}" title="Renew License" onclick="openRenewModal(${lic.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                        </button>
                        <button type="button" class="action-icon-btn btn-view" title="Edit License" onclick="editLicense(${lic.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    // =========================================================================
    // UPDATE KPI METRICS
    // =========================================================================
    function updateKPIs() {
        let activeCount = 0;
        let expiringCount = 0;
        let expiredCount = 0;
        let totalCost = 0;

        licenses.forEach(lic => {
            totalCost += parseFloat(lic.total_cost || 0);
            const expInfo = computeLicenseExpiry(lic.expiry_date, lic.status);
            lic.status = expInfo.status;

            if (expInfo.status === 'Active') activeCount++;
            else if (expInfo.status === 'Expiring Soon') expiringCount++;
            else if (expInfo.status === 'Expired') expiredCount++;
        });

        const elTot = document.getElementById('kpiTotalLicenses');
        if (elTot) elTot.textContent = licenses.length;

        const elActive = document.getElementById('kpiActiveLicenses');
        if (elActive) elActive.textContent = activeCount;

        const elExp = document.getElementById('kpiExpiringCount');
        if (elExp) elExp.textContent = expiringCount;

        const elCost = document.getElementById('kpiTotalCost');
        if (elCost) elCost.textContent = '₹' + formatNumber(totalCost);
    }

    // =========================================================================
    // SLIDE-OVER DRAWER (DETAILS & SEAT DIRECTORY)
    // =========================================================================
    window.openLicenseDrawer = function (id) {
        const item = licenses.find(l => l.id === id);
        if (!item) return;

        activeDrawerLicId = id;

        // Header
        if (drawerSoftwareTitle) drawerSoftwareTitle.textContent = item.name;
        if (drawerSoftwarePublisher) drawerSoftwarePublisher.textContent = `${item.publisher} • ${item.version || 'Commercial'}`;
        if (drawerLogoBadge) {
            drawerLogoBadge.textContent = (item.name || 'SW').substring(0, 2).toUpperCase();
            drawerLogoBadge.className = `software-logo-badge brand-${item.brand_code || 'default'}`;
        }

        const expInfo = computeLicenseExpiry(item.expiry_date, item.status);
        item.status = expInfo.status;

        // Stats
        if (drawerStatModel) drawerStatModel.textContent = item.license_type || 'Commercial';
        if (drawerStatStatus) {
            drawerStatStatus.textContent = expInfo.status;
            drawerStatStatus.style.color = (expInfo.status === 'Expiring Soon') ? '#d97706' : (expInfo.status === 'Expired' ? '#dc2626' : '#059669');
        }
        if (drawerStatCost) drawerStatCost.textContent = `₹${formatNumber(item.total_cost || 0)}`;

        // Details Grid
        if (drawerLicType) drawerLicType.textContent = item.license_type;
        if (drawerLicCategory) drawerLicCategory.textContent = item.category;
        if (drawerLicenseKey) drawerLicenseKey.textContent = item.license_key || '—';
        if (drawerPoNumber) drawerPoNumber.textContent = item.po_number || '—';
        if (drawerVendor) drawerVendor.textContent = item.vendor || '—';
        if (drawerPurchaseDate) drawerPurchaseDate.textContent = item.purchase_date || '—';
        if (drawerExpiryDate) {
            drawerExpiryDate.innerHTML = `${escapeHtml(item.expiry_date || '—')} <span class="days-pill ${expInfo.badgeClass}" style="margin-left: 6px; font-size: 11px;">${escapeHtml(expInfo.label)}</span>`;
        }
        if (drawerCostPerSeat) drawerCostPerSeat.textContent = `₹${formatNumber(item.cost_per_seat || 0)}`;
        if (drawerTotalCost) drawerTotalCost.textContent = `₹${formatNumber(item.total_cost || 0)}`;

        if (drawerStatusBadge) {
            drawerStatusBadge.className = `lic-badge ${expInfo.statusBadgeClass}`;
            drawerStatusBadge.innerHTML = `<span class="dot"></span> ${escapeHtml(expInfo.status)}`;
        }

        if (drawerNotes) drawerNotes.textContent = item.notes || 'No specific usage constraints recorded.';

        // Load Renewal History & Audit Records for this specific license
        loadDrawerRenewalLogs(item.id);

        // Open Overlay
        if (drawerOverlay) drawerOverlay.classList.add('open');
    };

    function loadDrawerRenewalLogs(licId) {
        if (!drawerRenewalLogsContainer) return;
        drawerRenewalLogsContainer.innerHTML = '<div class="renewal-empty-state"><span style="color:#0284c7;">⌛</span> Loading renewal history...</div>';
        if (drawerRenewalLogCount) drawerRenewalLogCount.textContent = '...';

        fetch(`api/software_licenses.php?fetch_renewals=1&license_id=${encodeURIComponent(licId)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.renewals) || data.renewals.length === 0) {
                    drawerRenewalLogsContainer.innerHTML = '<div class="renewal-empty-state">No renewal audit records recorded yet.</div>';
                    if (drawerRenewalLogCount) drawerRenewalLogCount.textContent = '0 Logs';
                    return;
                }

                if (drawerRenewalLogCount) drawerRenewalLogCount.textContent = `${data.renewals.length} Logs`;
                const html = data.renewals.map(r => `
                    <div class="renewal-log-card">
                        <div class="renewal-log-header">
                            <span class="renewal-log-date">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                ${escapeHtml(r.renewal_date)}
                            </span>
                            <span class="renewal-log-cost">₹${formatNumber(r.renewal_cost || 0)}</span>
                        </div>
                        <div class="renewal-log-dates-row">
                            <span>${escapeHtml(r.previous_expiry || 'Initial')}</span>
                            <span class="renewal-log-arrow">&rarr;</span>
                            <strong style="color: #0369a1;">${escapeHtml(r.new_expiry)}</strong>
                        </div>
                        ${(r.previous_license_key || r.new_license_key) ? `
                        <div style="font-size: 11px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; margin: 6px 0;">
                            <div style="color: #64748b; display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                <span style="font-weight: 600;">Old Key:</span>
                                <code style="font-family: monospace; color: #b91c1c; background: #fee2e2; padding: 1px 5px; border-radius: 3px;">${escapeHtml(r.previous_license_key || '—')}</code>
                            </div>
                            ${(r.new_license_key && r.new_license_key !== r.previous_license_key) ? `
                            <div style="color: #15803d; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 3px;">
                                <span style="font-weight: 600;">New Key:</span>
                                <code style="font-family: monospace; color: #15803d; background: #dcfce7; padding: 1px 5px; border-radius: 3px;">${escapeHtml(r.new_license_key)}</code>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}
                        <div class="renewal-log-user">Renewed by: <strong>${escapeHtml(r.renewed_by || 'IT Administrator')}</strong></div>
                        ${r.notes ? `<div class="renewal-log-notes">${escapeHtml(r.notes)}</div>` : ''}
                    </div>
                `).join('');
                drawerRenewalLogsContainer.innerHTML = html;
            })
            .catch(err => {
                console.error('Error fetching drawer renewal logs:', err);
                drawerRenewalLogsContainer.innerHTML = '<div class="renewal-empty-state" style="color: #ef4444;">Failed to load renewal history.</div>';
                if (drawerRenewalLogCount) drawerRenewalLogCount.textContent = '0 Logs';
            });
    }

    window.closeLicenseDrawer = function (e) {
        if (e && e.target !== drawerOverlay) return;
        if (drawerOverlay) drawerOverlay.classList.remove('open');
        activeDrawerLicId = null;
    };

    window.copyDrawerKey = function () {
        if (!drawerLicenseKey) return;
        copyLicenseKey(drawerLicenseKey.textContent);
    };

    window.editCurrentDrawerLicense = function () {
        if (activeDrawerLicId) {
            closeLicenseDrawer();
            editLicense(activeDrawerLicId);
        }
    };

    // =========================================================================
    // MODAL: ADD / EDIT LICENSE (WIZARD STEPS & ACTIONS)
    // =========================================================================
    let currentModalStep = 0;
    const modalStepKeys = ['basic', 'licensing', 'procurement'];

    window.switchModalTab = function (tabKey) {
        const idx = modalStepKeys.indexOf(tabKey);
        if (idx !== -1) {
            currentModalStep = idx;
        }

        modalStepKeys.forEach(k => {
            const btn = document.getElementById(`tabBtn_${k}`);
            const pane = document.getElementById(`modal_pane_${k}`);
            if (btn) btn.classList.toggle('active', k === tabKey);
            if (pane) pane.classList.toggle('active', k === tabKey);
        });

        // Update Next / Back buttons
        const prevBtn = document.getElementById('modalPrevStepBtn');
        const nextBtn = document.getElementById('modalNextStepBtn');
        const saveBtn = document.getElementById('saveLicenseBtn');

        if (prevBtn) prevBtn.style.display = (currentModalStep > 0) ? 'inline-flex' : 'none';
        if (nextBtn) nextBtn.style.display = (currentModalStep < modalStepKeys.length - 1) ? 'inline-flex' : 'none';
        if (saveBtn) saveBtn.style.display = (currentModalStep === modalStepKeys.length - 1) ? 'inline-flex' : 'inline-flex';
    };

    window.navigateModalStep = function (direction) {
        const newStep = currentModalStep + direction;
        if (newStep >= 0 && newStep < modalStepKeys.length) {
            switchModalTab(modalStepKeys[newStep]);
        }
    };

    window.togglePerpetual = function (checkbox) {
        const expInput = document.getElementById('formExpiryDate');
        if (!expInput) return;
        if (checkbox.checked) {
            expInput.value = '';
            expInput.disabled = true;
            expInput.style.backgroundColor = '#f1f5f9';
            expInput.style.cursor = 'not-allowed';
        } else {
            expInput.disabled = false;
            expInput.style.backgroundColor = '#ffffff';
            expInput.style.cursor = 'pointer';
        }
    };

    window.generateSampleKey = function () {
        const prefixes = ['LIC', 'MSFT', 'ADBE', 'JB', 'CORP'];
        const randPre = prefixes[Math.floor(Math.random() * prefixes.length)];
        const seg1 = Math.random().toString(36).substring(2, 6).toUpperCase();
        const seg2 = Math.random().toString(36).substring(2, 6).toUpperCase();
        const seg3 = Math.random().toString(36).substring(2, 6).toUpperCase();
        const sample = `${randPre}-2026-${seg1}-${seg2}-${seg3}`;
        const input = document.getElementById('formLicenseKey');
        if (input) {
            input.value = sample;
            showNotification(`Generated key: ${sample}`, 'info');
        }
    };

    function openAddLicenseModal() {
        if (!addModalOverlay) return;
        const titleEl = document.getElementById('modalLicenseTitle');
        if (titleEl) {
            titleEl.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                <span>Add Software License</span>
            `;
        }
        document.getElementById('editLicenseId').value = '';
        if (licenseForm) licenseForm.reset();
        document.getElementById('formPurchaseDate').value = new Date().toISOString().split('T')[0];

        addModalOverlay.style.display = 'flex';
        addModalOverlay.classList.add('active');
        addModalOverlay.classList.add('open');
    }

    window.closeAddLicenseModal = function () {
        if (addModalOverlay) {
            addModalOverlay.style.display = 'none';
            addModalOverlay.classList.remove('active');
            addModalOverlay.classList.remove('open');
        }
    };

    window.editLicense = function (id) {
        const lic = licenses.find(l => l.id === id);
        if (!lic) return;

        const titleEl = document.getElementById('modalLicenseTitle');
        if (titleEl) {
            titleEl.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>Edit Software License: ${escapeHtml(lic.name)}</span>
            `;
        }

        document.getElementById('editLicenseId').value = lic.id;
        document.getElementById('formSoftwareName').value = lic.name || '';
        document.getElementById('formPublisher').value = lic.publisher || '';
        document.getElementById('formCategory').value = lic.category || 'Office & Productivity';
        document.getElementById('formVersion').value = lic.version || '';
        document.getElementById('formLicenseType').value = lic.license_type || 'SaaS Subscription';
        document.getElementById('formLicenseKey').value = lic.license_key || '';
        document.getElementById('formVendor').value = lic.vendor || '';
        document.getElementById('formPurchaseDate').value = lic.purchase_date || '';
        document.getElementById('formExpiryDate').value = (lic.expiry_date === 'Perpetual') ? '' : (lic.expiry_date || '');
        document.getElementById('formCost').value = lic.total_cost || '';
        document.getElementById('formStatus').value = lic.status || 'Active';
        document.getElementById('formNotes').value = lic.notes || '';

        if (addModalOverlay) {
            addModalOverlay.style.display = 'flex';
            addModalOverlay.classList.add('active');
            addModalOverlay.classList.add('open');
        }
    };

    window.saveLicenseForm = function (e) {
        e.preventDefault();
        const editId = document.getElementById('editLicenseId').value;
        const name = document.getElementById('formSoftwareName').value.trim();
        const publisher = document.getElementById('formPublisher').value.trim();
        const category = document.getElementById('formCategory').value;
        const version = document.getElementById('formVersion').value.trim();
        const licType = document.getElementById('formLicenseType').value;
        const licKey = document.getElementById('formLicenseKey').value.trim();
        const vendor = document.getElementById('formVendor').value;
        const pDate = document.getElementById('formPurchaseDate').value;
        const expDate = document.getElementById('formExpiryDate').value || 'Perpetual';
        const cost = parseFloat(document.getElementById('formCost').value) || 0;
        const status = document.getElementById('formStatus').value;
        const notes = document.getElementById('formNotes').value.trim();

        let brand = 'default';
        const pLower = publisher.toLowerCase();
        if (pLower.includes('micro')) brand = 'msft';
        else if (pLower.includes('adobe')) brand = 'adobe';
        else if (pLower.includes('jet')) brand = 'jb';
        else if (pLower.includes('slack')) brand = 'slack';
        else if (pLower.includes('git')) brand = 'github';
        else if (pLower.includes('atlass')) brand = 'atlassian';
        else if (pLower.includes('crowd')) brand = 'crowdstrike';

        if (editId) {
            // Update existing
            const target = licenses.find(l => String(l.id) === String(editId));
            if (target) {
                target.name = name;
                target.publisher = publisher;
                target.category = category;
                target.version = version;
                target.license_type = licType;
                target.license_key = licKey;
                target.vendor = vendor;
                target.purchase_date = pDate;
                target.expiry_date = expDate;
                target.total_cost = cost;
                target.status = status;
                target.notes = notes;
                target.brand_code = brand;

                // Sync update with DB API
                fetch('api/software_licenses.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'edit',
                        id: target.id,
                        name: target.name,
                        publisher: target.publisher,
                        category: target.category,
                        version: target.version,
                        license_type: target.license_type,
                        license_key: target.license_key,
                        vendor: target.vendor,
                        purchase_date: target.purchase_date,
                        expiry_date: target.expiry_date,
                        total_cost: target.total_cost,
                        status: target.status,
                        notes: target.notes
                    })
                }).catch(err => console.warn('API sync warning:', err));

                showNotification(`Software license "${name}" updated successfully.`, 'success');
            }
        } else {
            // Add new
            const newId = licenses.length > 0 ? Math.max(...licenses.map(l => l.id)) + 1 : 1;
            const newLicense = {
                id: newId,
                name,
                publisher,
                brand_code: brand,
                category,
                version,
                license_type: licType,
                license_key: licKey || `LIC-2026-${String(newId).padStart(4, '0')}`,
                total_seats: 10,
                assigned_seats: 0,
                vendor,
                po_number: `PO-2026-LIC-${newId}`,
                purchase_date: pDate,
                expiry_date: expDate,
                cost_per_seat: cost,
                total_cost: cost,
                currency: '₹',
                status,
                notes,
                allocations: []
            };
            licenses.unshift(newLicense);

            // Sync create with DB API
            fetch('api/software_licenses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    name,
                    publisher,
                    category,
                    version,
                    license_type: licType,
                    license_key: newLicense.license_key,
                    vendor,
                    purchase_date: pDate,
                    expiry_date: expDate,
                    total_cost: cost,
                    status,
                    notes
                })
            }).then(r => r.json()).then(res => {
                if (res && res.id) newLicense.id = res.id;
            }).catch(err => console.warn('API sync warning:', err));

            showNotification(`New software license "${name}" registered successfully.`, 'success');
        }

        closeAddLicenseModal();
        renderTable();
        updateKPIs();
    };

    // =========================================================================
    // MODAL 2: RENEW SOFTWARE LICENSE (ACTIONS & FORM HANDLING)
    // =========================================================================
    window.openRenewModal = function (targetId) {
        if (!renewModalOverlay) return;

        // Populate dropdown options dynamically from active array
        if (renewSelectLicense) {
            renewSelectLicense.innerHTML = '';
            licenses.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = `${l.name} (${l.publisher}) — Exp: ${l.expiry_date || 'Perpetual'}`;
                renewSelectLicense.appendChild(opt);
            });
        }

        // If targetId is not specified, prefer an Expiring Soon license, or the first license
        let selectedId = targetId;
        if (!selectedId && licenses.length > 0) {
            const expiringOne = licenses.find(l => l.status === 'Expiring Soon');
            selectedId = expiringOne ? expiringOne.id : licenses[0].id;
        }

        if (renewSelectLicense && selectedId) {
            renewSelectLicense.value = selectedId;
        }

        populateRenewModalData(selectedId);

        renewModalOverlay.style.display = 'flex';
        renewModalOverlay.classList.add('active', 'open');
    };

    window.closeRenewLicenseModal = function () {
        if (renewModalOverlay) {
            renewModalOverlay.style.display = 'none';
            renewModalOverlay.classList.remove('active', 'open');
        }
    };

    window.onRenewLicenseSelectChange = function (id) {
        populateRenewModalData(parseInt(id, 10));
    };

    function populateRenewModalData(licId) {
        const item = licenses.find(l => l.id == licId);
        if (!item) return;

        if (renewLicenseId) renewLicenseId.value = item.id;
        if (renewSummaryName) renewSummaryName.textContent = item.name;
        if (renewSummaryPublisher) renewSummaryPublisher.textContent = `${item.publisher} • ${item.license_type || 'SaaS'}`;
        const expInfo = computeLicenseExpiry(item.expiry_date, item.status);
        if (renewSummaryCurrentExpiry) {
            renewSummaryCurrentExpiry.textContent = `${item.expiry_date || 'Perpetual'} (${expInfo.label})`;
            renewSummaryCurrentExpiry.style.color = (expInfo.status === 'Expired') ? '#dc2626' : ((expInfo.status === 'Expiring Soon') ? '#ea580c' : '#059669');
        }
        if (renewSummaryCurrentCost) renewSummaryCurrentCost.textContent = `₹${formatNumber(item.total_cost || 0)}`;

        if (renewLogoBadge) {
            renewLogoBadge.textContent = (item.name || 'SW').substring(0, 2).toUpperCase();
            renewLogoBadge.className = `software-logo-badge brand-${item.brand_code || 'default'}`;
        }

        // Calculate Default +1 Year expiry date
        const baseDate = parseExpiryDate(item.expiry_date);
        const newDate = new Date(baseDate);
        newDate.setFullYear(newDate.getFullYear() + 1);
        const formattedDate = newDate.toISOString().split('T')[0];

        if (renewNewExpiryDate) renewNewExpiryDate.value = formattedDate;
        if (renewCost) renewCost.value = item.total_cost || 0;
        if (renewPoNumber) {
            const year = new Date().getFullYear();
            const rndCode = Math.floor(100 + Math.random() * 900);
            renewPoNumber.value = `PO-${year}-RNW-${rndCode}`;
        }
        if (renewVendor) renewVendor.value = item.vendor || '';
        if (renewLicenseKey) renewLicenseKey.value = '';
        const curKeyEl = document.getElementById('renewCurrentKeyDisplay');
        if (curKeyEl) curKeyEl.textContent = item.license_key || '—';
        if (renewStatus) renewStatus.value = 'Active';
        if (renewNotes) renewNotes.value = `Annual renewal processed for ${item.name}. Software maintenance and subscription extended by 1 year.`;

        // Reset duration buttons state to 12 months
        document.querySelectorAll('.duration-pill-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-months') === '12');
        });
    }

    function parseExpiryDate(dateStr) {
        if (!dateStr || dateStr === 'Perpetual' || isNaN(Date.parse(dateStr))) {
            return new Date();
        }
        const parsed = new Date(dateStr);
        // If expiry is already in the past, renew from today
        return (parsed < new Date()) ? new Date() : parsed;
    }

    window.setRenewalDuration = function (months, btn) {
        document.querySelectorAll('.duration-pill-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        if (months === 'custom') {
            if (renewNewExpiryDate) renewNewExpiryDate.focus();
            return;
        }

        const id = renewLicenseId ? renewLicenseId.value : null;
        const item = licenses.find(l => l.id == id);
        const baseDate = parseExpiryDate(item ? item.expiry_date : null);

        const newDate = new Date(baseDate);
        newDate.setMonth(newDate.getMonth() + parseInt(months, 10));
        const formatted = newDate.toISOString().split('T')[0];

        if (renewNewExpiryDate) renewNewExpiryDate.value = formatted;
    };

    window.generateRenewPo = function () {
        const year = new Date().getFullYear();
        const randNum = Math.floor(100 + Math.random() * 900);
        if (renewPoNumber) {
            renewPoNumber.value = `PO-${year}-RNW-${randNum}`;
            showNotification(`Generated Renewal PO: ${renewPoNumber.value}`, 'info');
        }
    };

    window.generateSampleRenewKey = function () {
        const seg1 = Math.random().toString(36).substring(2, 6).toUpperCase();
        const seg2 = Math.random().toString(36).substring(2, 6).toUpperCase();
        const seg3 = Math.random().toString(36).substring(2, 6).toUpperCase();
        const sample = `RNW-2026-${seg1}-${seg2}-${seg3}`;
        if (renewLicenseKey) {
            renewLicenseKey.value = sample;
            showNotification(`Generated new key: ${sample}`, 'info');
        }
    };

    window.saveRenewLicenseForm = function (e) {
        e.preventDefault();
        const id = renewLicenseId ? renewLicenseId.value : null;
        const target = licenses.find(l => String(l.id) === String(id));
        if (!target) {
            showNotification('Error: Target software license not found.', 'error');
            return;
        }

        const newExpiry = renewNewExpiryDate ? renewNewExpiryDate.value : '';
        const newCost = parseFloat(renewCost ? renewCost.value : 0) || 0;
        const newPo = renewPoNumber ? renewPoNumber.value.trim() : '';
        const newVendor = renewVendor ? renewVendor.value : target.vendor;
        const newKey = renewLicenseKey ? renewLicenseKey.value.trim() : '';
        const newStatus = renewStatus ? renewStatus.value : 'Active';
        const renewalNotes = renewNotes ? renewNotes.value.trim() : '';

        const submitBtn = document.getElementById('saveRenewBtn');
        const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Renewing License...';
        }

        // Sync renew with DB API
        fetch('api/software_licenses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'renew',
                id: target.id,
                expiry_date: newExpiry,
                total_cost: newCost,
                po_number: newPo,
                vendor: newVendor,
                license_key: newKey,
                status: newStatus,
                notes: renewalNotes
            })
        })
        .then(r => r.json())
        .then(res => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }

            if (!res.success) {
                showNotification(res.error || 'Failed to renew software license.', 'error');
                return;
            }

            // Apply updates to license state
            target.expiry_date = newExpiry || target.expiry_date;
            target.total_cost = newCost || target.total_cost;
            target.status = newStatus;
            if (newPo) target.po_number = newPo;
            if (newVendor) target.vendor = newVendor;
            if (newKey) target.license_key = newKey;

            const timestamp = new Date().toISOString().split('T')[0];
            const noteStamp = `[Renewed on ${timestamp} until ${target.expiry_date}] ${renewalNotes}`;
            target.notes = target.notes ? `${target.notes} | ${noteStamp}` : noteStamp;

            closeRenewLicenseModal();
            renderTable();
            updateKPIs();

            // Refresh Drawer if open for this license
            if (activeDrawerLicId && String(activeDrawerLicId) === String(target.id)) {
                openLicenseDrawer(target.id);
            }

            showNotification(`🎉 Software license "${target.name}" renewed successfully until ${target.expiry_date}! Audit log saved.`, 'success');
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }
            console.error('API sync error:', err);
            showNotification('Network error while processing renewal.', 'error');
        });
    };

    window.renewCurrentDrawerLicense = function () {
        if (activeDrawerLicId) {
            closeLicenseDrawer();
            openRenewModal(activeDrawerLicId);
        }
    };

    // =========================================================================
    // MODAL 3: ALL SOFTWARE LICENSE RENEWAL AUDIT LOGS
    // =========================================================================
    window.openAllRenewalLogsModal = function () {
        if (!allRenewalLogsModalOverlay) return;
        if (renewalLogSearchInput) renewalLogSearchInput.value = '';
        allRenewalLogsModalOverlay.style.display = 'flex';
        allRenewalLogsModalOverlay.classList.add('active', 'open');
        loadAllRenewalLogs(true);
    };

    window.closeAllRenewalLogsModal = function () {
        if (allRenewalLogsModalOverlay) {
            allRenewalLogsModalOverlay.style.display = 'none';
            allRenewalLogsModalOverlay.classList.remove('active', 'open');
        }
    };

    window.loadAllRenewalLogs = function (force = false) {
        if (!allRenewalLogsTableBody) return;
        allRenewalLogsTableBody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">
                    <div style="display: inline-flex; align-items: center; gap: 8px;">
                        <span style="color: #0284c7;">⌛</span> Loading renewal audit records from database...
                    </div>
                </td>
            </tr>
        `;

        fetch('api/software_licenses.php?fetch_renewals=1')
            .then(res => res.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.renewals)) {
                    allRenewalLogsTableBody.innerHTML = `
                        <tr><td colspan="8" style="text-align: center; padding: 24px; color: #dc2626;">Error loading renewal audit trail.</td></tr>
                    `;
                    return;
                }
                cachedAllRenewalLogs = data.renewals;
                filterRenewalLogsTable();
            })
            .catch(err => {
                console.error('Error fetching renewal logs:', err);
                allRenewalLogsTableBody.innerHTML = `
                    <tr><td colspan="8" style="text-align: center; padding: 24px; color: #dc2626;">Failed to connect to renewal log service.</td></tr>
                `;
            });
    };

    window.filterRenewalLogsTable = function () {
        const query = (renewalLogSearchInput ? renewalLogSearchInput.value : '').trim().toLowerCase();
        let list = cachedAllRenewalLogs;
        if (query) {
            list = list.filter(r => 
                (r.software_name && r.software_name.toLowerCase().includes(query)) ||
                (r.previous_license_key && r.previous_license_key.toLowerCase().includes(query)) ||
                (r.new_license_key && r.new_license_key.toLowerCase().includes(query)) ||
                (r.renewed_by && r.renewed_by.toLowerCase().includes(query)) ||
                (r.notes && r.notes.toLowerCase().includes(query)) ||
                (r.new_expiry && r.new_expiry.toLowerCase().includes(query)) ||
                (r.renewal_date && r.renewal_date.toLowerCase().includes(query))
            );
        }
        renderRenewalLogsTable(list);
    };

    function renderRenewalLogsTable(list) {
        if (!allRenewalLogsTableBody) return;
        if (!list || list.length === 0) {
            allRenewalLogsTableBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 32px; color: #64748b;">
                        <div style="font-weight: 600; font-size: 13.5px; margin-bottom: 4px;">No Renewal Logs Found</div>
                        <div style="font-size: 12px; color: #94a3b8;">No license renewals matching your search criteria have been recorded yet.</div>
                    </td>
                </tr>
            `;
            return;
        }

        const rows = list.map(r => `
            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                <td style="padding: 10px 10px; font-weight: 700; color: #64748b;">#${r.id}</td>
                <td style="padding: 10px 10px; font-weight: 700; color: #0f172a;">${escapeHtml(r.software_name)}</td>
                <td style="padding: 10px 10px; color: #334155;">${escapeHtml(r.renewal_date)}</td>
                <td style="padding: 10px 10px; font-size: 11.5px; white-space: nowrap;">
                    <span style="color: #64748b;">${escapeHtml(r.previous_expiry || '—')}</span>
                    <span style="color: #0284c7; font-weight: bold; margin: 0 4px;">&rarr;</span>
                    <strong style="color: #0369a1;">${escapeHtml(r.new_expiry)}</strong>
                </td>
                <td style="padding: 10px 10px;">
                    <code style="font-family: monospace; color: #b91c1c; background: #fee2e2; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(r.previous_license_key || '—')}</code>
                </td>
                <td style="padding: 10px 10px;">
                    <code style="font-family: monospace; color: #15803d; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">${escapeHtml(r.new_license_key || r.previous_license_key || '—')}</code>
                </td>
                <td style="padding: 10px 10px; text-align: right; font-weight: 800; color: #0f172a;">₹${formatNumber(r.renewal_cost || 0)}</td>
                <td style="padding: 10px 10px; font-size: 11.5px; color: #475569;">
                    <span style="display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 2px 8px; border-radius: 12px; font-weight: 600;">
                        👤 ${escapeHtml(r.renewed_by || 'IT Admin')}
                    </span>
                </td>
                <td style="padding: 10px 10px; font-size: 11.5px; color: #475569; max-width: 180px;">
                    <span title="${escapeHtml(r.notes || '')}">${escapeHtml(r.notes || '—')}</span>
                </td>
            </tr>
        `).join('');

        allRenewalLogsTableBody.innerHTML = rows;
    }


    // =========================================================================
    // UTILITY: CLIPBOARD & EXPORT
    // =========================================================================
    window.copyLicenseKey = function (key) {
        if (!key || key === '—') return;
        navigator.clipboard.writeText(key).then(() => {
            showNotification(`License key copied to clipboard!`, 'info');
        }).catch(() => {
            showNotification(`Key: ${key}`, 'info');
        });
    };

    function exportCSV() {
        const list = getFilteredList();
        if (list.length === 0) {
            showNotification('No software records to export.', 'warning');
            return;
        }

        const headers = ['ID', 'Software Name', 'Publisher', 'Category', 'Version', 'License Type', 'Product Key', 'Vendor', 'Expiry Date', 'Total Spend (₹)', 'Status'];
        const rows = list.map(l => [
            l.id,
            `"${(l.name || '').replace(/"/g, '""')}"`,
            `"${(l.publisher || '').replace(/"/g, '""')}"`,
            `"${(l.category || '').replace(/"/g, '""')}"`,
            `"${(l.version || '').replace(/"/g, '""')}"`,
            `"${(l.license_type || '').replace(/"/g, '""')}"`,
            `"${(l.license_key || '').replace(/"/g, '""')}"`,
            `"${(l.vendor || '').replace(/"/g, '""')}"`,
            `"${(l.expiry_date || '').replace(/"/g, '""')}"`,
            l.total_cost || 0,
            `"${(l.status || '').replace(/"/g, '""')}"`
        ]);

        const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `software_licenses_export_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification('Software licenses list exported to CSV.', 'success');
    }

    // =========================================================================
    // EVENT BINDINGS
    // =========================================================================
    function bindEvents() {
        // Search & Filters
        if (searchInput) searchInput.addEventListener('input', renderTable);
        if (categoryFilter) categoryFilter.addEventListener('change', renderTable);
        if (typeFilter) typeFilter.addEventListener('change', renderTable);
        if (statusFilter) statusFilter.addEventListener('change', renderTable);

        // Reset Filters
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                if (categoryFilter) categoryFilter.value = 'all';
                if (typeFilter) typeFilter.value = 'all';
                if (statusFilter) statusFilter.value = 'all';
                currentTab = 'all';
                document.querySelectorAll('.lic-stat-card').forEach(c => c.classList.remove('active-filter'));
                renderTable();
            });
        }

        // KPI Stat Cards Click to Filter
        document.querySelectorAll('.lic-stat-card').forEach(card => {
            card.addEventListener('click', function () {
                const tab = this.getAttribute('data-filter-tab');
                if (!tab) return;
                document.querySelectorAll('.lic-stat-card').forEach(c => c.classList.remove('active-filter'));
                this.classList.add('active-filter');

                if (tab === 'expiring') {
                    currentTab = 'expiring';
                } else {
                    currentTab = 'all';
                }

                renderTable();
            });
        });

        // Modals Buttons
        if (openAddBtn) openAddBtn.addEventListener('click', openAddLicenseModal);
        if (openRenewBtn) openRenewBtn.addEventListener('click', function () { window.openRenewModal(); });
        if (openRenewalLogsBtn) openRenewalLogsBtn.addEventListener('click', openAllRenewalLogsModal);
        if (exportBtn) exportBtn.addEventListener('click', exportCSV);

        // Select All Checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                const checked = this.checked;
                document.querySelectorAll('.row-select-checkbox').forEach(cb => cb.checked = checked);
            });
        }

    }

    // Helpers
    function intval(v) {
        const n = parseInt(v, 10);
        return isNaN(n) ? 0 : n;
    }

    function formatNumber(num) {
        return Number(num).toLocaleString('en-IN');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    function showNotification(msg, type = 'info') {
        if (window.showToast) {
            window.showToast(msg, type);
            return;
        }
        console.log(`[VIROS Alert - ${type}]: ${msg}`);
    }

    // Auto Run on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
