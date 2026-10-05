/**
 * Parts & Components Management Script (Single-Item Asset Tracking)
 * VIROS IT Asset & Service Desk Portal
 */

(function () {
    'use strict';

    // Sample IT Parts & Components Dataset (Individual Serialized Units)
    let components = [
        { id: 1, sku: "PRT1026001", serial: "CRU-DDR4-88491", name: "Crucial 16GB DDR4 3200MHz SO-DIMM", category: "RAM & Memory Modules", brand: "Crucial", model: "CT16G4SFD832A", specs: "16GB DDR4 3200MHz CL22 1.2V 260-Pin", status: "Installed", installedAsset: "AST-2026-001 (Dell Latitude 5420)", location: "Installed in Slot 2" },
        { id: 2, sku: "PRT1026002", serial: "CRU-DDR4-88492", name: "Crucial 16GB DDR4 3200MHz SO-DIMM", category: "RAM & Memory Modules", brand: "Crucial", model: "CT16G4SFD832A", specs: "16GB DDR4 3200MHz CL22 1.2V 260-Pin", status: "Available", installedAsset: "", location: "Depot Rack 1 (Drawer A-01)" },
        { id: 3, sku: "PRT1026003", serial: "KNG-DDR5-10293", name: "Kingston Fury Beast 32GB DDR5 5600MHz", category: "RAM & Memory Modules", brand: "Kingston", model: "KF556C40BBK2-32", specs: "32GB (2x16GB) DDR5 5600MHz Desktop", status: "Available", installedAsset: "", location: "Depot Rack 1 (Drawer A-03)" },
        { id: 4, sku: "PRT1026004", serial: "SAM-NVME-99102", name: "Samsung 980 PRO 1TB PCIe 4.0 NVMe M.2", category: "Solid State Drives (SSD)", brand: "Samsung", model: "MZ-V8P1T0B/AM", specs: "1TB M.2 NVMe PCIe Gen4 (7000MB/s Read)", status: "Installed", installedAsset: "AST-2026-003 (MacBook Pro 16 M1)", location: "Installed as Primary Drive" },
        { id: 5, sku: "PRT1026005", serial: "SAM-NVME-99103", name: "Samsung 980 PRO 1TB PCIe 4.0 NVMe M.2", category: "Solid State Drives (SSD)", brand: "Samsung", model: "MZ-V8P1T0B/AM", specs: "1TB M.2 NVMe PCIe Gen4 (7000MB/s Read)", status: "Available", installedAsset: "", location: "Depot Rack 2 (Drawer B-01)" },
        { id: 6, sku: "PRT1026006", serial: "CRU-SATA-44129", name: "Crucial MX500 500GB 2.5-Inch SATA SSD", category: "Solid State Drives (SSD)", brand: "Crucial", model: "CT500MX500SSD1", specs: "500GB SATA 6Gb/s 2.5-Inch 7mm Internal", status: "Installed", installedAsset: "AST-2026-004 (HP EliteDesk 800 G6)", location: "Installed in SATA Bay 1" },
        { id: 7, sku: "PRT1026007", serial: "SEA-NAS-77218", name: "Seagate IronWolf 4TB NAS Hard Drive", category: "Hard Disk Drives (HDD)", brand: "Seagate", model: "ST4000VN006", specs: "4TB 5400RPM SATA 6Gb/s 256MB Cache", status: "Installed", installedAsset: "AST-2026-005 (Dell PowerEdge R740)", location: "Server Bay 03" },
        { id: 8, sku: "PRT1026008", serial: "NV-RTX-55102", name: "NVIDIA RTX A2000 12GB Workstation GPU", category: "Graphics & GPU Cards", brand: "NVIDIA / PNY", model: "VCNRTXA2000-12GB", specs: "12GB GDDR6 Low Profile PCIe 4.0 x16", status: "Installed", installedAsset: "AST-2026-006 (Custom AI Workstation)", location: "PCIe Slot 1" },
        { id: 9, sku: "PRT1026009", serial: "INT-I7-33910", name: "Intel Core i7-13700 Desktop Processor", category: "Processors & CPUs", brand: "Intel", model: "BX8071513700", specs: "16 Cores (8P+8E) up to 5.2GHz LGA1700", status: "Installed", installedAsset: "AST-2026-006 (Custom AI Workstation)", location: "Socket LGA1700" },
        { id: 10, sku: "PRT1026010", serial: "DEL-BAT-22019", name: "Dell 58Wh 4-Cell Laptop Replacement Battery", category: "Laptop Batteries", brand: "Dell OEM", model: "68Wh H5CKD", specs: "15.2V 58Wh Li-ion for Latitude 5420/5430", status: "Available", installedAsset: "", location: "Battery Safe Cabinet (Shelf 2)" },
        { id: 11, sku: "PRT1026011", serial: "COR-750-66014", name: "Corsair RM750x 750W Fully Modular PSU", category: "Power Supply Units (PSU)", brand: "Corsair", model: "CP-9020199-NA", specs: "750 Watt 80 Plus Gold Fully Modular", status: "Under Repair", installedAsset: "", location: "Repair Bench (Ticket #IT-884)" },
        { id: 12, sku: "PRT1026012", serial: "INT-NIC-12004", name: "Intel X550-T2 Dual Port 10GbE Network Card", category: "Network Interface Cards (NIC)", brand: "Intel", model: "X550T2BLK", specs: "Dual-Port RJ45 10GbE PCIe 3.0 x4", status: "Available", installedAsset: "", location: "Depot Rack 4 (Drawer N-01)" }
    ];

    // Filter states
    let searchTerm = '';
    let selectedCategory = 'all';
    let selectedStatus = 'all';

    // DOM Elements
    const tbody = document.getElementById('componentsTbody');
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resetFilterBtn = document.getElementById('resetFilterBtn');

    // Stats
    const statTotalItems = document.getElementById('statTotalItems');
    const statAvailable = document.getElementById('statAvailable');
    const statInstalled = document.getElementById('statInstalled');
    const statRepair = document.getElementById('statRepair');

    // Modals
    const componentModal = document.getElementById('componentModal');
    const installModal = document.getElementById('installModal');
    const importModal = document.getElementById('importModal');

    const openAddModalBtn = document.getElementById('openAddModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const componentForm = document.getElementById('componentForm');

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
        renderTable();
        updateStats();
        bindEvents();
        fetchDynamicCategories();
    }

    // Fetch dynamic categories from component_categories table API
    function fetchDynamicCategories(selectedVal) {
        fetch('api/component_categories.php?status=Active')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.categories)) {
                    const compCategorySelect = document.getElementById('compCategory');
                    const categoryFilterSelect = document.getElementById('categoryFilter');

                    if (compCategorySelect) {
                        const currentVal = (selectedVal !== undefined) ? selectedVal : compCategorySelect.value;
                        let optsHtml = '<option value="">Select Part / Component Category</option>';
                        data.categories.forEach(cat => {
                            const name = cat.category_name;
                            const isSel = (name === currentVal) ? 'selected' : '';
                            optsHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        compCategorySelect.innerHTML = optsHtml;
                        if (currentVal) compCategorySelect.value = currentVal;
                        if (window.SearchableSelect) window.SearchableSelect.sync(compCategorySelect);
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

    function bindEvents() {
        // Search & Filters
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                searchTerm = e.target.value.toLowerCase().trim();
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
                selectedCategory = 'all';
                selectedStatus = 'all';
                if (searchInput) searchInput.value = '';
                if (categoryFilter) categoryFilter.value = 'all';
                if (statusFilter) statusFilter.value = 'all';
                if (window.SearchableSelect) {
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
                document.getElementById('modalTitle').textContent = 'Add New Component / Part';
                document.getElementById('editCompId').value = '';
                componentForm.reset();
                document.getElementById('compStatus').value = 'Available';
                const skuInput = document.getElementById('compSku');
                if (skuInput) skuInput.value = generateNextPartSku();
                if (window.SearchableSelect) {
                    window.SearchableSelect.sync(document.getElementById('compCategory'));
                    window.SearchableSelect.sync(document.getElementById('compStatus'));
                }
                fetchDynamicCategories('');
                openModal(componentModal);
            });
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', () => closeModal(componentModal));
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', () => closeModal(componentModal));

        if (componentForm) {
            componentForm.addEventListener('submit', handleSaveComponent);
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
                closeModal(componentModal);
                closeModal(installModal);
                closeModal(importModal);
            }
        });

        [componentModal, installModal, importModal].forEach(modal => {
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
                (item.installedAsset && item.installedAsset.toLowerCase().includes(searchTerm)) ||
                item.category.toLowerCase().includes(searchTerm)
            );

            const matchesCategory = (selectedCategory === 'all') || (item.category === selectedCategory);
            const matchesStatus = (selectedStatus === 'all') || (item.status === selectedStatus);

            return matchesSearch && matchesCategory && matchesStatus;
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

            const tr = document.createElement('tr');
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
                            ${escapeHtml(item.location || 'Depot Shelf')}
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
    function updateStats() {
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

    // Save Component (Add / Edit) - Single Item
    function handleSaveComponent(e) {
        e.preventDefault();
        const editId = document.getElementById('editCompId').value;
        const name = document.getElementById('compName').value.trim();
        const category = document.getElementById('compCategory').value;
        const brand = document.getElementById('compBrand').value.trim();
        const model = document.getElementById('compModel').value.trim();
        const serial = document.getElementById('compSerial').value.trim();
        const status = document.getElementById('compStatus').value;
        const specs = document.getElementById('compSpecs').value.trim();
        const location = document.getElementById('compLocation').value.trim();

        if (editId) {
            // Update existing
            const item = components.find(c => c.id === parseInt(editId, 10));
            if (item) {
                item.name = name;
                item.category = category;
                item.brand = brand;
                item.model = model;
                item.serial = serial;
                item.status = status;
                if (status === 'Available') {
                    item.installedAsset = '';
                }
                item.specs = specs;
                item.location = location;
                showNotification(`Updated component "${name}".`, 'success');
            }
        } else {
            // Create new single item
            const newId = components.length > 0 ? Math.max(...components.map(c => c.id)) + 1 : 1;
            const skuInput = document.getElementById('compSku');
            const newSku = (skuInput && skuInput.value.trim()) ? skuInput.value.trim() : generateNextPartSku();
            components.unshift({
                id: newId,
                sku: newSku,
                serial: serial,
                name: name,
                category: category,
                brand: brand,
                model: model,
                specs: specs,
                status: status,
                installedAsset: '',
                location: location || 'Depot Shelf'
            });
            showNotification(`Added new component "${name}" (${newSku}).`, 'success');
        }

        closeModal(componentModal);
        updateStats();
        renderTable();
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
        const item = components.find(c => c.id === id);
        if (!item) return;

        const targetAsset = document.getElementById('installTargetAsset').value;
        const technician = document.getElementById('installedBy').value;

        item.status = 'Installed';
        item.installedAsset = targetAsset;

        closeModal(installModal);
        updateStats();
        renderTable();
        showNotification(`Component ${item.name} (${item.serial}) installed into ${targetAsset}.`, 'success');
    }

    // Detach from Asset
    function detachItem(id) {
        const item = components.find(c => c.id === id);
        if (!item) return;

        if (confirm(`Are you sure you want to detach "${item.name}" from ${item.installedAsset || 'asset'} and return it to available stock?`)) {
            item.status = 'Available';
            item.installedAsset = '';
            item.location = 'Returned to Depot Shelf';
            updateStats();
            renderTable();
            showNotification(`Component "${item.name}" returned to available stock.`, 'info');
        }
    }

    // Edit Item
    function openEditModal(id) {
        const item = components.find(c => c.id === id);
        if (!item) return;

        document.getElementById('modalTitle').textContent = 'Edit Component / Part';
        document.getElementById('editCompId').value = item.id;
        const skuInput = document.getElementById('compSku');
        if (skuInput) skuInput.value = item.sku || '';
        document.getElementById('compName').value = item.name;
        document.getElementById('compCategory').value = item.category;
        document.getElementById('compBrand').value = item.brand;
        document.getElementById('compModel').value = item.model || '';
        document.getElementById('compSerial').value = item.serial || '';
        document.getElementById('compStatus').value = item.status;
        document.getElementById('compSpecs').value = item.specs || '';
        document.getElementById('compLocation').value = item.location || '';

        fetchDynamicCategories(item.category);
        if (window.SearchableSelect) {
            window.SearchableSelect.sync(document.getElementById('compCategory'));
            window.SearchableSelect.sync(document.getElementById('compStatus'));
        }
        openModal(componentModal);
    }

    // Delete Item
    function deleteItem(id) {
        const item = components.find(c => c.id === id);
        if (!item) return;

        if (confirm(`Are you sure you want to remove "${item.name}" (${item.serial}) from parts inventory?`)) {
            components = components.filter(c => c.id !== id);
            updateStats();
            renderTable();
            showNotification(`Component "${item.name}" deleted.`, 'info');
        }
    }

    // Export CSV
    function handleExportCsv() {
        const list = getFilteredList();
        if (list.length === 0) {
            showNotification('No components to export.', 'warning');
            return;
        }

        let csv = 'Part SKU,Serial Number,Component Name,Category,Brand,Part No,Specs,Installed Asset,Status,Location\n';
        list.forEach(c => {
            csv += `"${c.sku}","${c.serial || ''}","${c.name.replace(/"/g, '""')}","${c.category}","${c.brand}","${c.model || ''}","${c.specs ? c.specs.replace(/"/g, '""') : ''}","${c.installedAsset || ''}","${c.status}","${c.location || ''}"\n`;
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
        if (startImportBtn) startImportBtn.disabled = true;
    }

    function handleExecuteImport(e) {
        e.preventDefault();
        if (stagedImportData.length === 0) {
            showNotification('No data to import.', 'warning');
            return;
        }

        let addedCount = 0;
        stagedImportData.forEach(item => {
            const newId = components.length > 0 ? Math.max(...components.map(c => c.id)) + 1 : 1;
            const newSku = generateNextPartSku(addedCount);
            components.unshift({
                id: newId,
                sku: newSku,
                serial: item.serial,
                name: item.name,
                category: item.category,
                brand: item.brand,
                model: item.model,
                specs: item.specs,
                status: item.status || 'Available',
                installedAsset: '',
                location: item.location
            });
            addedCount++;
        });

        closeModal(importModal);
        updateStats();
        renderTable();
        showNotification(`Successfully imported ${addedCount} individual components from CSV!`, 'success');
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
        detachItem: detachItem,
        openEdit: openEditModal,
        deleteItem: deleteItem
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
