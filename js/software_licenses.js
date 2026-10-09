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

    // Modal Elements
    const addModalOverlay = document.getElementById('addLicenseModalOverlay');
    const licenseForm = document.getElementById('licenseForm');
    const openAddBtn = document.getElementById('openAddLicenseBtn');

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
            // Tab filter
            if (currentTab === 'saas' && !lic.license_type.toLowerCase().includes('saas')) return false;
            if (currentTab === 'perpetual' && !lic.license_type.toLowerCase().includes('perpetual') && !lic.license_type.toLowerCase().includes('oem')) return false;
            if (currentTab === 'expiring' && lic.status !== 'Expiring Soon') return false;

            // Dropdown filters
            if (cat !== 'all' && lic.category !== cat) return false;
            if (type !== 'all' && lic.license_type !== type) return false;
            if (status !== 'all' && lic.status !== status) return false;

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
                    lic.po_number
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
                    <td colspan="9" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
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
            let brandClass = 'brand-default';
            if (lic.brand_code === 'msft') brandClass = 'brand-msft';
            else if (lic.brand_code === 'adobe') brandClass = 'brand-adobe';
            else if (lic.brand_code === 'jb') brandClass = 'brand-jb';
            else if (lic.brand_code === 'slack') brandClass = 'brand-slack';
            else if (lic.brand_code === 'github') brandClass = 'brand-github';
            else if (lic.brand_code === 'atlassian') brandClass = 'brand-atlassian';
            else if (lic.brand_code === 'crowdstrike') brandClass = 'brand-crowdstrike';

            const logoLetters = escapeHtml((lic.name || 'SW').substring(0, 2).toUpperCase());

            let statusBadgeClass = 'lic-badge-active';
            if (lic.status === 'Expiring Soon') statusBadgeClass = 'lic-badge-expiring';
            else if (lic.status === 'Expired') statusBadgeClass = 'lic-badge-expired';

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
                        ${lic.status === 'Expiring Soon'
                            ? `<span class="days-pill warn">&#9888; Renewal Due Soon</span>`
                            : (lic.expiry_date === 'Perpetual'
                                ? `<span class="days-pill safe">Lifetime Perpetual</span>`
                                : `<span class="days-pill safe">Active License</span>`
                            )
                        }
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
                    <span class="lic-badge ${statusBadgeClass}">
                        <span class="dot"></span>
                        ${escapeHtml(lic.status)}
                    </span>
                </td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        <button type="button" class="action-icon-btn btn-view" title="View License Details" onclick="openLicenseDrawer(${lic.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
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
        let totalCost = 0;
        let saasCount = 0;
        let perpCount = 0;

        licenses.forEach(lic => {
            totalCost += parseFloat(lic.total_cost || 0);

            if (lic.status === 'Active') activeCount++;
            if (lic.status === 'Expiring Soon') expiringCount++;
            if ((lic.license_type || '').toLowerCase().includes('saas')) saasCount++;
            if ((lic.license_type || '').toLowerCase().includes('perpetual') || (lic.license_type || '').toLowerCase().includes('oem')) perpCount++;
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

        // Stats
        if (drawerStatModel) drawerStatModel.textContent = item.license_type || 'Commercial';
        if (drawerStatStatus) {
            drawerStatStatus.textContent = item.status || 'Active';
            drawerStatStatus.style.color = (item.status === 'Expiring Soon') ? '#d97706' : (item.status === 'Expired' ? '#dc2626' : '#059669');
        }
        if (drawerStatCost) drawerStatCost.textContent = `₹${formatNumber(item.total_cost || 0)}`;

        // Details Grid
        if (drawerLicType) drawerLicType.textContent = item.license_type;
        if (drawerLicCategory) drawerLicCategory.textContent = item.category;
        if (drawerLicenseKey) drawerLicenseKey.textContent = item.license_key || '—';
        if (drawerPoNumber) drawerPoNumber.textContent = item.po_number || '—';
        if (drawerVendor) drawerVendor.textContent = item.vendor || '—';
        if (drawerPurchaseDate) drawerPurchaseDate.textContent = item.purchase_date || '—';
        if (drawerExpiryDate) drawerExpiryDate.textContent = item.expiry_date || '—';
        if (drawerCostPerSeat) drawerCostPerSeat.textContent = `₹${formatNumber(item.cost_per_seat || 0)}`;
        if (drawerTotalCost) drawerTotalCost.textContent = `₹${formatNumber(item.total_cost || 0)}`;

        if (drawerStatusBadge) {
            const isExp = item.status === 'Expiring Soon';
            drawerStatusBadge.className = `lic-badge ${isExp ? 'lic-badge-expiring' : 'lic-badge-active'}`;
            drawerStatusBadge.innerHTML = `<span class="dot"></span> ${escapeHtml(item.status)}`;
        }

        if (drawerNotes) drawerNotes.textContent = item.notes || 'No specific usage constraints recorded.';

        // Open Overlay
        if (drawerOverlay) drawerOverlay.classList.add('open');
    };

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
            showNotification(`New software license "${name}" registered successfully.`, 'success');
        }

        closeAddLicenseModal();
        renderTable();
        updateKPIs();
    };


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
