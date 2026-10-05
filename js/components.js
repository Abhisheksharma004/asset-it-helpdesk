/**
 * Parts & Components Management Script (Database Synced with MSSQL)
 * VIROS IT Asset & Service Desk Portal
 */

(function () {
    'use strict';

    // Components dataset loaded from server or initialized
    let components = (typeof window !== 'undefined' && Array.isArray(window.INITIAL_COMPONENTS))
        ? window.INITIAL_COMPONENTS
        : [];

    // Filter states
    let searchTerm = '';
    let selectedBranch = 'all';
    let selectedCategory = 'all';
    let selectedStatus = 'all';

    // DOM Elements
    const tbody = document.getElementById('componentsTbody');
    const searchInput = document.getElementById('searchInput');
    const branchFilter = document.getElementById('branchFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resetFilterBtn = document.getElementById('resetFilterBtn');

    // Stats
    const statTotalItems = document.getElementById('statTotalItems');
    const statAvailable = document.getElementById('statAvailable');
    const statInstalled = document.getElementById('statInstalled');
    const statRepair = document.getElementById('statRepair');

    // Modals
    const addModal = document.getElementById('addComponentModal');
    const editModal = document.getElementById('editComponentModal');
    const deleteModal = document.getElementById('deleteComponentModal');
    const installModal = document.getElementById('installModal');
    const importModal = document.getElementById('importModal');

    const openAddModalBtn = document.getElementById('openAddModalBtn');
    const closeAddModalBtn = document.getElementById('closeAddModalBtn');
    const cancelAddModalBtn = document.getElementById('cancelAddModalBtn');
    const addComponentForm = document.getElementById('addComponentForm');
    const saveCompBtn = document.getElementById('saveCompBtn');

    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const cancelEditModalBtn = document.getElementById('cancelEditModalBtn');
    const editComponentForm = document.getElementById('editComponentForm');
    const updateCompBtn = document.getElementById('updateCompBtn');

    const closeDeleteModalBtn = document.getElementById('closeDeleteModalBtn');
    const cancelDeleteModalBtn = document.getElementById('cancelDeleteModalBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteComponentName = document.getElementById('deleteComponentName');
    let componentToDeleteId = null;

    const detachModal = document.getElementById('detachComponentModal');
    const closeDetachModalBtn = document.getElementById('closeDetachModalBtn');
    const cancelDetachModalBtn = document.getElementById('cancelDetachModalBtn');
    const confirmDetachBtn = document.getElementById('confirmDetachBtn');
    const detachComponentName = document.getElementById('detachComponentName');
    const detachTargetAsset = document.getElementById('detachTargetAsset');
    let componentToDetachId = null;

    const closeInstallModalBtn = document.getElementById('closeInstallModalBtn');
    const cancelInstallBtn = document.getElementById('cancelInstallBtn');
    const installForm = document.getElementById('installForm');

    const openImportModalBtn = document.getElementById('openImportModalBtn');
    const closeImportModalBtn = document.getElementById('closeImportModalBtn');
    const cancelImportModalBtn = document.getElementById('cancelImportModalBtn');
    const importForm = document.getElementById('importForm');
    const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
    const csvDropzone = document.getElementById('csvDropzone');
    const csvFileInput = document.getElementById('csvFileInput');
    const filePreviewCard = document.getElementById('filePreviewCard');
    const previewFileName = document.getElementById('previewFileName');
    const previewFileMeta = document.getElementById('previewFileMeta');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const startImportBtn = document.getElementById('startImportBtn');

    const exportCompBtn = document.getElementById('exportCompBtn');

    let stagedImportData = [];

    // Helper: Construct API URL with preview query if present
    function getApiUrl(params = {}) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('preview')) {
            params.preview = 1;
        }
        const qs = new URLSearchParams(params).toString();
        return 'api/components.php' + (qs ? '?' + qs : '');
    }

    // Generate Part / SKU Tag in format PRT + MMYY + 3DIGITSERIAL (e.g. PRT1026001)
    function generateNextPartSku(offset = 0) {
        const now = new Date();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const yy = String(now.getFullYear()).slice(-2);
        const prefix = `PRT${mm}${yy}`;

        let maxNum = 0;
        components.forEach(c => {
            if (c.sku && c.sku.startsWith(prefix)) {
                const numPart = parseInt(c.sku.substring(prefix.length), 10);
                if (!isNaN(numPart) && numPart > maxNum) {
                    maxNum = numPart;
                }
            } else if (c.sku) {
                const match = c.sku.match(/\d{3}$/);
                if (match) {
                    const numPart = parseInt(match[0], 10);
                    if (!isNaN(numPart) && numPart > maxNum) {
                        maxNum = numPart;
                    }
                }
            }
        });

        if (maxNum === 0 && components.length > 0) {
            maxNum = components.length;
        }

        const nextNum = maxNum + 1 + offset;
        return `${prefix}${String(nextNum).padStart(3, '0')}`;
    }

    // Initialize
    function init() {
        if (components.length === 0) {
            loadComponents();
        } else {
            renderTable();
            updateStats();
        }
        bindEvents();
        fetchDynamicCategories();
        fetchDynamicBranches();
    }

    // Load components and stats from database via API
    function loadComponents(callback) {
        fetch(getApiUrl())
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.components)) {
                    components = data.components;
                    renderTable();
                    if (data.stats) updateStats(data.stats);
                    if (typeof callback === 'function') callback();
                }
            })
            .catch(err => {
                console.warn('Could not fetch components from database:', err);
                if (typeof callback === 'function') callback();
            });
    }

    // Fetch dynamic categories from component_categories table API
    function fetchDynamicCategories(selectedVal) {
        fetch('api/component_categories.php?status=Active' + (window.location.search.includes('preview=1') ? '&preview=1' : ''))
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.categories)) {
                    const compCategorySelect = document.getElementById('compCategory');
                    const editCompCategorySelect = document.getElementById('editCompCategory');
                    const categoryFilterSelect = document.getElementById('categoryFilter');

                    const populate = (sel, defText, cur) => {
                        if (!sel) return;
                        let optsHtml = `<option value="">${defText}</option>`;
                        data.categories.forEach(cat => {
                            const name = cat.category_name;
                            const isSel = (name === cur) ? 'selected' : '';
                            optsHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        sel.innerHTML = optsHtml;
                        if (cur) sel.value = cur;
                        if (window.SearchableSelect) window.SearchableSelect.sync(sel);
                    };

                    if (compCategorySelect) {
                        populate(compCategorySelect, 'Select Part / Component Category', compCategorySelect.value);
                    }

                    if (editCompCategorySelect) {
                        const curEdit = (selectedVal !== undefined) ? selectedVal : editCompCategorySelect.value;
                        populate(editCompCategorySelect, 'Select Part / Component Category', curEdit);
                    }

                    if (categoryFilterSelect) {
                        const curFilter = categoryFilterSelect.value;
                        let filterHtml = '<option value="all">All Part / Component Categories</option>';
                        data.categories.forEach(cat => {
                            const name = cat.category_name;
                            const isSel = (name === curFilter) ? 'selected' : '';
                            filterHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        categoryFilterSelect.innerHTML = filterHtml;
                        if (curFilter) categoryFilterSelect.value = curFilter;
                        if (window.SearchableSelect) window.SearchableSelect.sync(categoryFilterSelect);
                    }
                }
            })
            .catch(err => {
                console.warn('Could not refresh component categories:', err);
            });
    }

    // Fetch and populate active branch locations from Locations Master API
    function fetchDynamicBranches(selectedVal) {
        fetch('api/locations.php?status=Active' + (window.location.search.includes('preview=1') ? '&preview=1' : ''))
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.locations)) {
                    const compBranch = document.getElementById('compBranch');
                    const editCompBranch = document.getElementById('editCompBranch');

                    const populate = (sel, defText, cur) => {
                        if (!sel) return;
                        let optsHtml = `<option value="">${defText}</option>`;
                        data.locations.forEach(loc => {
                            const name = loc.location_name;
                            const isSel = (name === cur) ? 'selected' : '';
                            optsHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        sel.innerHTML = optsHtml;
                        if (cur) sel.value = cur;
                        if (window.SearchableSelect) window.SearchableSelect.sync(sel);
                    };

                    if (compBranch) {
                        populate(compBranch, 'Select Branch Location', compBranch.value);
                    }
                    if (editCompBranch) {
                        const curEdit = (selectedVal !== undefined) ? selectedVal : editCompBranch.value;
                        populate(editCompBranch, 'Select Branch Location', curEdit);
                    }
                    if (branchFilter) {
                        const curFilter = branchFilter.value;
                        let filterHtml = '<option value="all">All Branch Locations</option>';
                        data.locations.forEach(loc => {
                            const name = loc.location_name;
                            const isSel = (name === curFilter) ? 'selected' : '';
                            filterHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        branchFilter.innerHTML = filterHtml;
                        if (curFilter) branchFilter.value = curFilter;
                        if (window.SearchableSelect) window.SearchableSelect.sync(branchFilter);
                    }
                }
            })
            .catch(err => {
                console.warn('Could not refresh branch locations:', err);
            });
    }

    function bindEvents() {
        // Search & Filters
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                searchTerm = e.target.value.toLowerCase().trim();
                renderTable();
            });
        }

        if (branchFilter) {
            branchFilter.addEventListener('change', function (e) {
                selectedBranch = e.target.value;
                renderTable();
            });
        }

        if (categoryFilter) {
            categoryFilter.addEventListener('change', function (e) {
                selectedCategory = e.target.value;
                renderTable();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function (e) {
                selectedStatus = e.target.value;
                renderTable();
            });
        }

        if (resetFilterBtn) {
            resetFilterBtn.addEventListener('click', function () {
                searchTerm = '';
                selectedBranch = 'all';
                selectedCategory = 'all';
                selectedStatus = 'all';
                if (searchInput) searchInput.value = '';
                if (branchFilter) branchFilter.value = 'all';
                if (categoryFilter) categoryFilter.value = 'all';
                if (statusFilter) statusFilter.value = 'all';
                if (window.SearchableSelect) {
                    if (branchFilter) window.SearchableSelect.sync(branchFilter);
                    if (categoryFilter) window.SearchableSelect.sync(categoryFilter);
                    if (statusFilter) window.SearchableSelect.sync(statusFilter);
                }
                renderTable();
                showNotification('Filters reset to default.', 'info');
            });
        }

        // Add Modal
        if (openAddModalBtn) {
            openAddModalBtn.addEventListener('click', function () {
                if (addComponentForm) addComponentForm.reset();
                const statusEl = document.getElementById('compStatus');
                if (statusEl) statusEl.value = 'Available';

                const branchEl = document.getElementById('compBranch');
                if (branchEl) branchEl.value = '';

                const skuInput = document.getElementById('compSku');
                if (skuInput) skuInput.value = generateNextPartSku();

                // Fetch real guaranteed unique next SKU from server
                fetch(getApiUrl({ action: 'get_next_sku' }))
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.next_sku && skuInput) {
                            skuInput.value = res.next_sku;
                        }
                    })
                    .catch(() => {});

                fetchDynamicCategories();
                fetchDynamicBranches();
                if (window.SearchableSelect) {
                    window.SearchableSelect.sync(document.getElementById('compCategory'));
                    if (branchEl) window.SearchableSelect.sync(branchEl);
                    window.SearchableSelect.sync(document.getElementById('compStatus'));
                }
                openModal(addModal);
            });
        }

        if (closeAddModalBtn) closeAddModalBtn.addEventListener('click', () => closeModal(addModal));
        if (cancelAddModalBtn) cancelAddModalBtn.addEventListener('click', () => closeModal(addModal));

        if (addComponentForm) {
            addComponentForm.addEventListener('submit', handleCreateComponent);
        }

        // Edit Modal
        if (closeEditModalBtn) closeEditModalBtn.addEventListener('click', () => closeModal(editModal));
        if (cancelEditModalBtn) cancelEditModalBtn.addEventListener('click', () => closeModal(editModal));

        if (editComponentForm) {
            editComponentForm.addEventListener('submit', handleUpdateComponent);
        }

        // Delete Modal
        if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener('click', () => closeModal(deleteModal));
        if (cancelDeleteModalBtn) cancelDeleteModalBtn.addEventListener('click', () => closeModal(deleteModal));

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', handleConfirmDelete);
        }

        // Detach Modal
        if (closeDetachModalBtn) closeDetachModalBtn.addEventListener('click', () => closeModal(detachModal));
        if (cancelDetachModalBtn) cancelDetachModalBtn.addEventListener('click', () => closeModal(detachModal));

        if (confirmDetachBtn) {
            confirmDetachBtn.addEventListener('click', handleConfirmDetach);
        }

        // Install Modal
        if (closeInstallModalBtn) closeInstallModalBtn.addEventListener('click', () => closeModal(installModal));
        if (cancelInstallBtn) cancelInstallBtn.addEventListener('click', () => closeModal(installModal));

        if (installForm) {
            installForm.addEventListener('submit', handleConfirmInstall);
        }

        // Import Modal
        if (openImportModalBtn) {
            openImportModalBtn.addEventListener('click', function () {
                resetImportModal();
                openModal(importModal);
            });
        }
        if (closeImportModalBtn) closeImportModalBtn.addEventListener('click', () => closeModal(importModal));
        if (cancelImportModalBtn) cancelImportModalBtn.addEventListener('click', () => closeModal(importModal));

        if (downloadTemplateBtn) {
            downloadTemplateBtn.addEventListener('click', handleDownloadTemplate);
        }

        // CSV File Dropzone
        if (csvDropzone && csvFileInput) {
            csvDropzone.addEventListener('click', () => csvFileInput.click());

            csvDropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                csvDropzone.classList.add('drag-over');
            });

            csvDropzone.addEventListener('dragleave', () => {
                csvDropzone.classList.remove('drag-over');
            });

            csvDropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                csvDropzone.classList.remove('drag-over');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    processUploadedCsv(e.dataTransfer.files[0]);
                }
            });

            csvFileInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files.length > 0) {
                    processUploadedCsv(e.target.files[0]);
                }
            });
        }

        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                resetImportModal();
            });
        }

        if (importForm) {
            importForm.addEventListener('submit', handleExecuteImport);
        }

        // Export CSV
        if (exportCompBtn) {
            exportCompBtn.addEventListener('click', handleExportCsv);
        }

        // Close on Escape or click outside
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(addModal);
                closeModal(editModal);
                closeModal(deleteModal);
                closeModal(detachModal);
                closeModal(installModal);
                closeModal(importModal);
            }
        });

        [addModal, editModal, deleteModal, detachModal, installModal, importModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal(modal);
                });
            }
        });
    }

    // Filter Logic
    function getFilteredList() {
        return components.filter(item => {
            const matchesSearch = !searchTerm || (
                item.name.toLowerCase().includes(searchTerm) ||
                item.sku.toLowerCase().includes(searchTerm) ||
                (item.serial && item.serial.toLowerCase().includes(searchTerm)) ||
                item.brand.toLowerCase().includes(searchTerm) ||
                (item.model && item.model.toLowerCase().includes(searchTerm)) ||
                (item.specs && item.specs.toLowerCase().includes(searchTerm)) ||
                (item.branch_location && item.branch_location.toLowerCase().includes(searchTerm)) ||
                (item.installedAsset && item.installedAsset.toLowerCase().includes(searchTerm)) ||
                item.category.toLowerCase().includes(searchTerm)
            );

            const matchesBranch = (selectedBranch === 'all') || ((item.branch_location || '') === selectedBranch);
            const matchesCategory = (selectedCategory === 'all') || (item.category === selectedCategory);
            const matchesStatus = (selectedStatus === 'all') || (item.status === selectedStatus);

            return matchesSearch && matchesBranch && matchesCategory && matchesStatus;
        });
    }

    // Render Table
    function renderTable() {
        if (!tbody) return;
        tbody.innerHTML = '';

        const list = getFilteredList();

        if (list.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="table-empty-state">
                            <div class="empty-icon">⚙️</div>
                            <div class="empty-title">No Components Found</div>
                            <div class="empty-desc">No parts match your current search and filter criteria.</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(item => {
            let statusBadge = '';
            if (item.status === 'Available') {
                statusBadge = `<span class="asset-status-badge status-available"><span class="dot"></span>Available</span>`;
            } else if (item.status === 'Installed') {
                statusBadge = `<span class="asset-status-badge status-in-use"><span class="dot"></span>Installed</span>`;
            } else if (item.status === 'Under Repair') {
                statusBadge = `<span class="asset-status-badge status-maintenance"><span class="dot"></span>Under Repair</span>`;
            } else {
                statusBadge = `<span class="asset-status-badge status-retired" style="background:#fef2f2; color:#b91c1c;"><span class="dot" style="background:#ef4444;"></span>Defective</span>`;
            }

            // Asset Column
            let assetCol = `<span style="color: var(--text-muted); font-size: 12px;">— (Unassigned / In Stock)</span>`;
            if (item.status === 'Installed' && item.installedAsset) {
                assetCol = `
                    <span class="asset-badge" title="Host Device">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                        ${escapeHtml(item.installedAsset)}
                    </span>
                `;
            } else if (item.status === 'Under Repair') {
                assetCol = `<span style="color: #ea580c; font-size: 12px; font-weight: 500;">In Service Depot</span>`;
            }

            // Action Buttons
            let installOrDetachBtn = '';
            if (item.status === 'Available') {
                installOrDetachBtn = `
                    <button class="action-icon-btn btn-view" title="Install into Host Asset" onclick="window.compMgr.openInstall(${item.id})">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    </button>
                `;
            } else if (item.status === 'Installed') {
                installOrDetachBtn = `
                    <button class="action-icon-btn btn-detach" title="Detach from Asset & Return to Stock" onclick="window.compMgr.detachItem(${item.id})">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                `;
            }

            const locParts = [];
            if (item.branch_location) locParts.push(escapeHtml(item.branch_location));
            if (item.location) locParts.push(escapeHtml(item.location));
            const locText = locParts.length > 0 ? locParts.join(' • ') : 'Depot Shelf';

            const tr = document.createElement('tr');
            tr.setAttribute('data-id', item.id);
            tr.innerHTML = `
                <td style="white-space: nowrap;">
                    <span class="asset-tag-badge" title="Part SKU Tag">${escapeHtml(item.sku)}</span>
                </td>
                <td style="white-space: nowrap;">
                    <span class="serial-badge">${escapeHtml(item.serial || '—')}</span>
                </td>
                <td>
                    <div class="asset-details-wrap">
                        <span class="asset-name-title" onclick="window.compMgr.openEdit(${item.id})">${escapeHtml(item.name)}</span>
                        <span class="asset-spec-sub">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            ${locText}
                        </span>
                    </div>
                </td>
                <td><span class="category-pill">${escapeHtml(item.category)}</span></td>
                <td>
                    <div style="font-weight: 500; font-size: 13px; color: var(--text-primary);">${escapeHtml(item.brand)}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">${escapeHtml(item.specs || item.model || '—')}</div>
                </td>
                <td>${assetCol}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        ${installOrDetachBtn}
                        <button class="action-icon-btn btn-edit" title="Edit Component" onclick="window.compMgr.openEdit(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button class="action-icon-btn btn-delete" title="Delete Component" onclick="window.compMgr.deleteItem(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Update Stats
    function updateStats(serverStats) {
        if (serverStats) {
            if (statTotalItems) statTotalItems.textContent = serverStats.total ?? components.length;
            if (statAvailable) statAvailable.textContent = serverStats.available ?? 0;
            if (statInstalled) statInstalled.textContent = serverStats.installed ?? 0;
            if (statRepair) statRepair.textContent = serverStats.repair ?? 0;
            return;
        }

        let totalItems = components.length;
        let availableCount = 0;
        let installedCount = 0;
        let repairCount = 0;

        components.forEach(item => {
            if (item.status === 'Available') availableCount++;
            else if (item.status === 'Installed') installedCount++;
            else if (item.status === 'Under Repair' || item.status === 'Defective') repairCount++;
        });

        if (statTotalItems) statTotalItems.textContent = totalItems;
        if (statAvailable) statAvailable.textContent = availableCount;
        if (statInstalled) statInstalled.textContent = installedCount;
        if (statRepair) statRepair.textContent = repairCount;
    }

    // Create Component - Saves directly to Database
    function handleCreateComponent(e) {
        e.preventDefault();
        const name = document.getElementById('compName').value.trim();
        const category = document.getElementById('compCategory').value;
        const branchLocation = document.getElementById('compBranch') ? document.getElementById('compBranch').value : '';
        const brand = document.getElementById('compBrand').value.trim();
        const model = document.getElementById('compModel').value.trim();
        const serial = document.getElementById('compSerial').value.trim();
        const status = document.getElementById('compStatus').value;
        const specs = document.getElementById('compSpecs').value.trim();
        const location = document.getElementById('compLocation').value.trim();
        const skuInput = document.getElementById('compSku');
        const sku = (skuInput && skuInput.value.trim()) ? skuInput.value.trim() : generateNextPartSku();

        if (saveCompBtn) {
            saveCompBtn.disabled = true;
            saveCompBtn.textContent = 'Saving...';
        }

        const payload = {
            action: 'create',
            sku: sku,
            name: name,
            category: category,
            branch_location: branchLocation,
            brand: brand,
            model: model,
            serial: serial,
            status: status,
            specs: specs,
            location: location
        };

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (saveCompBtn) {
                saveCompBtn.disabled = false;
                saveCompBtn.textContent = 'Save Component';
            }
            if (data.success) {
                showNotification(data.message || `Added component "${name}".`, 'success');
                closeModal(addModal);
                loadComponents();
            } else {
                showNotification(data.message || 'Failed to save component.', 'error');
            }
        })
        .catch(err => {
            if (saveCompBtn) {
                saveCompBtn.disabled = false;
                saveCompBtn.textContent = 'Save Component';
            }
            showNotification('Server error while saving component.', 'error');
            console.error(err);
        });
    }

    // Update Component - Saves edits directly to Database
    function handleUpdateComponent(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('editCompId').value, 10);
        const name = document.getElementById('editCompName').value.trim();
        const category = document.getElementById('editCompCategory').value;
        const branchLocation = document.getElementById('editCompBranch') ? document.getElementById('editCompBranch').value : '';
        const brand = document.getElementById('editCompBrand').value.trim();
        const model = document.getElementById('editCompModel').value.trim();
        const serial = document.getElementById('editCompSerial').value.trim();
        const status = document.getElementById('editCompStatus').value;
        const specs = document.getElementById('editCompSpecs').value.trim();
        const location = document.getElementById('editCompLocation').value.trim();

        if (updateCompBtn) {
            updateCompBtn.disabled = true;
            updateCompBtn.textContent = 'Updating...';
        }

        const payload = {
            action: 'edit',
            id: id,
            name: name,
            category: category,
            branch_location: branchLocation,
            brand: brand,
            model: model,
            serial: serial,
            status: status,
            specs: specs,
            location: location
        };

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (updateCompBtn) {
                updateCompBtn.disabled = false;
                updateCompBtn.textContent = 'Update Component';
            }
            if (data.success) {
                showNotification(data.message || `Updated component "${name}".`, 'success');
                closeModal(editModal);
                loadComponents();
            } else {
                showNotification(data.message || 'Failed to update component.', 'error');
            }
        })
        .catch(err => {
            if (updateCompBtn) {
                updateCompBtn.disabled = false;
                updateCompBtn.textContent = 'Update Component';
            }
            showNotification('Server error while updating component.', 'error');
            console.error(err);
        });
    }

    // Install / Allocate to Asset
    function openInstallModal(id) {
        const item = components.find(c => c.id === id);
        if (!item || item.status !== 'Available') {
            showNotification('Only available parts in stock can be installed into assets.', 'warning');
            return;
        }

        document.getElementById('installCompId').value = item.id;
        document.getElementById('installCompName').textContent = item.name;
        document.getElementById('installCompSku').textContent = item.sku;
        document.getElementById('installCompSerial').textContent = item.serial || 'No Serial';

        const dateInput = document.getElementById('installDate');
        dateInput.value = new Date().toISOString().split('T')[0];

        openModal(installModal);
    }

    function handleConfirmInstall(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('installCompId').value, 10);
        const targetAsset = document.getElementById('installTargetAsset').value;
        const technician = document.getElementById('installedBy').value;
        const installDate = document.getElementById('installDate').value;

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'install',
                id: id,
                target_asset: targetAsset,
                technician: technician,
                install_date: installDate
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal(installModal);
                showNotification(data.message || `Component installed into ${targetAsset}.`, 'success');
                loadComponents();
            } else {
                showNotification(data.message || 'Failed to install component.', 'error');
            }
        })
        .catch(err => {
            showNotification('Server error while installing component.', 'error');
            console.error(err);
        });
    }

    // Detach Item Modal - Opens confirmation modal (same as delete)
    function openDetachModal(id) {
        const item = components.find(c => c.id === id);
        if (!item) return;

        componentToDetachId = item.id;
        if (detachComponentName) {
            detachComponentName.textContent = `"${item.name}" (${item.sku})`;
        }
        if (detachTargetAsset) {
            detachTargetAsset.textContent = item.installedAsset || 'Host Asset';
        }
        openModal(detachModal);
    }

    // Handle Confirm Detach - Detaches and returns to stock
    function handleConfirmDetach() {
        if (!componentToDetachId) return;

        const item = components.find(c => c.id === componentToDetachId);
        const itemName = item ? item.name : 'Component';

        if (confirmDetachBtn) {
            confirmDetachBtn.disabled = true;
            confirmDetachBtn.textContent = 'Detaching...';
        }

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'detach',
                id: componentToDetachId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (confirmDetachBtn) {
                confirmDetachBtn.disabled = false;
                confirmDetachBtn.textContent = 'Yes, Detach Component';
            }
            if (data.success) {
                showNotification(data.message || `Component "${itemName}" returned to available stock.`, 'info');
                closeModal(detachModal);
                componentToDetachId = null;
                loadComponents();
            } else {
                showNotification(data.message || 'Failed to detach component.', 'error');
            }
        })
        .catch(err => {
            if (confirmDetachBtn) {
                confirmDetachBtn.disabled = false;
                confirmDetachBtn.textContent = 'Yes, Detach Component';
            }
            showNotification('Server error while detaching component.', 'error');
            console.error(err);
        });
    }

    // Edit Item Modal - Populates and opens dedicated Edit modal
    function openEditModal(id) {
        const item = components.find(c => c.id === id);
        if (!item) return;

        document.getElementById('editCompId').value = item.id;
        const skuInput = document.getElementById('editCompSku');
        if (skuInput) skuInput.value = item.sku || '';
        document.getElementById('editCompName').value = item.name || '';
        document.getElementById('editCompBrand').value = item.brand || '';
        document.getElementById('editCompModel').value = item.model || '';
        document.getElementById('editCompSerial').value = item.serial || '';
        document.getElementById('editCompStatus').value = item.status || 'Available';
        document.getElementById('editCompSpecs').value = item.specs || '';
        document.getElementById('editCompLocation').value = item.location || '';

        const catSelect = document.getElementById('editCompCategory');
        if (catSelect) {
            catSelect.value = item.category || '';
        }

        const branchSelect = document.getElementById('editCompBranch');
        if (branchSelect) {
            branchSelect.value = item.branch_location || '';
        }

        fetchDynamicCategories(item.category);
        fetchDynamicBranches(item.branch_location || '');

        if (window.SearchableSelect) {
            if (catSelect) window.SearchableSelect.sync(catSelect);
            if (branchSelect) window.SearchableSelect.sync(branchSelect);
            window.SearchableSelect.sync(document.getElementById('editCompStatus'));
        }

        openModal(editModal);
    }

    // Delete Item Modal - Opens confirmation modal (same as master pages)
    function openDeleteModal(id) {
        const item = components.find(c => c.id === id);
        if (!item) return;

        componentToDeleteId = item.id;
        if (deleteComponentName) {
            deleteComponentName.textContent = `"${item.name}" (${item.sku})`;
        }
        openModal(deleteModal);
    }

    // Handle Confirm Delete - Deletes permanently from Database
    function handleConfirmDelete() {
        if (!componentToDeleteId) return;

        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';
        }

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: componentToDeleteId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (confirmDeleteBtn) {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Yes, Delete Component';
            }
            if (data.success) {
                showNotification(data.message || 'Component removed from database successfully.', 'success');
                closeModal(deleteModal);
                componentToDeleteId = null;
                loadComponents();
            } else {
                showNotification(data.message || 'Failed to delete component.', 'error');
            }
        })
        .catch(err => {
            if (confirmDeleteBtn) {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Yes, Delete Component';
            }
            showNotification('Server error while deleting component.', 'error');
            console.error(err);
        });
    }

    // Export CSV
    function handleExportCsv() {
        const list = getFilteredList();
        if (list.length === 0) {
            showNotification('No components to export.', 'warning');
            return;
        }

        let csv = 'Part SKU,Serial Number,Component Name,Category,Branch Location,Brand,Part No,Specs,Installed Asset,Status,Location\n';
        list.forEach(c => {
            csv += `"${c.sku}","${c.serial || ''}","${c.name.replace(/"/g, '""')}","${c.category}","${(c.branch_location || '').replace(/"/g, '""')}","${c.brand}","${c.model || ''}","${c.specs ? c.specs.replace(/"/g, '""') : ''}","${c.installedAsset || ''}","${c.status}","${c.location || ''}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Components_List_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification(`Exported ${list.length} components to CSV.`, 'success');
    }

    // Download Sample Template
    function handleDownloadTemplate() {
        const template = 'Component Name,Category,Brand,Part No,Serial Number,Specs,Status,Location\n' +
            '"Kingston 16GB DDR4 3200MHz RAM","RAM & Memory Modules","Kingston","KVR32S22S8/16","SN-KNG-99120","16GB DDR4 3200MHz SO-DIMM","Available","Depot Rack 1"\n' +
            '"Samsung 970 EVO Plus 1TB NVMe","Solid State Drives (SSD)","Samsung","MZ-V7S1T0B/AM","SN-SAM-38192","1TB M.2 PCIe Gen3 NVMe","Available","Depot Rack 2"\n';

        const blob = new Blob([template], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sample_components_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Process Uploaded CSV
    function processUploadedCsv(file) {
        if (!file.name.endsWith('.csv')) {
            showNotification('Please upload a valid .csv file.', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const text = e.target.result;
            const lines = text.split(/\r?\n/).filter(line => line.trim() !== '');
            if (lines.length <= 1) {
                showNotification('CSV file is empty or missing data rows.', 'error');
                return;
            }

            stagedImportData = [];
            for (let i = 1; i < lines.length; i++) {
                const cols = parseCsvLine(lines[i]);
                if (cols.length >= 1 && cols[0].trim()) {
                    stagedImportData.push({
                        name: cols[0].trim(),
                        category: cols[1] ? cols[1].trim() : 'RAM & Memory Modules',
                        brand: cols[2] ? cols[2].trim() : 'Generic OEM',
                        model: cols[3] ? cols[3].trim() : '',
                        serial: cols[4] ? cols[4].trim() : `SN-IMP-${Date.now().toString().slice(-5)}`,
                        specs: cols[5] ? cols[5].trim() : '',
                        status: cols[6] ? cols[6].trim() : 'Available',
                        location: cols[7] ? cols[7].trim() : 'Storage Depot'
                    });
                }
            }

            if (stagedImportData.length === 0) {
                showNotification('Could not detect any valid component rows.', 'error');
                return;
            }

            // Show preview card
            if (filePreviewCard) {
                filePreviewCard.style.display = 'flex';
                previewFileName.textContent = file.name;
                previewFileMeta.textContent = `${(file.size / 1024).toFixed(1)} KB • ${stagedImportData.length} components detected`;
            }
            if (csvDropzone) csvDropzone.style.display = 'none';
            if (startImportBtn) startImportBtn.disabled = false;
        };
        reader.readAsText(file);
    }

    function parseCsvLine(line) {
        const result = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    cur += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                result.push(cur);
                cur = '';
            } else {
                cur += char;
            }
        }
        result.push(cur);
        return result;
    }

    function resetImportModal() {
        stagedImportData = [];
        if (csvFileInput) csvFileInput.value = '';
        if (filePreviewCard) filePreviewCard.style.display = 'none';
        if (csvDropzone) csvDropzone.style.display = 'block';
        if (startImportBtn) {
            startImportBtn.disabled = true;
            startImportBtn.textContent = 'Import Components';
        }
    }

    // Execute Import - Saves bulk rows into Database via API
    function handleExecuteImport(e) {
        e.preventDefault();
        if (stagedImportData.length === 0) {
            showNotification('No data to import.', 'warning');
            return;
        }

        if (startImportBtn) {
            startImportBtn.disabled = true;
            startImportBtn.textContent = 'Importing to Database...';
        }

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'import',
                rows: stagedImportData
            })
        })
        .then(res => res.json())
        .then(data => {
            if (startImportBtn) {
                startImportBtn.disabled = false;
                startImportBtn.textContent = 'Import Components';
            }
            if (data.success) {
                closeModal(importModal);
                showNotification(data.message || `Successfully imported ${data.imported || stagedImportData.length} components into database!`, 'success');
                loadComponents();
            } else {
                showNotification(data.message || 'Import failed.', 'error');
            }
        })
        .catch(err => {
            if (startImportBtn) {
                startImportBtn.disabled = false;
                startImportBtn.textContent = 'Import Components';
            }
            showNotification('Server error during import.', 'error');
            console.error(err);
        });
    }

    // Helpers
    function openModal(modal) {
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function showNotification(msg, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else {
            alert(msg);
        }
    }

    // Global hooks
    window.compMgr = {
        openInstall: openInstallModal,
        detachItem: openDetachModal,
        openEdit: openEditModal,
        deleteItem: openDeleteModal,
        reload: loadComponents
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
