/**
 * Accessories Management Script (Database Synced with MSSQL)
 * VIROS IT Asset & Service Desk Portal
 */

(function () {
    'use strict';

    // Accessories dataset loaded from server or initialized
    let accessories = (typeof window !== 'undefined' && Array.isArray(window.INITIAL_ACCESSORIES))
        ? window.INITIAL_ACCESSORIES
        : [];

    // Filter states
    let searchTerm = '';
    let selectedBranch = 'all';
    let selectedCategory = 'all';
    let selectedStatus = 'all';

    // DOM Elements
    const tbody = document.getElementById('accessoriesTbody');
    const searchInput = document.getElementById('searchInput');
    const branchFilter = document.getElementById('branchFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resetFilterBtn = document.getElementById('resetFilterBtn');

    // Stats
    const statTotalItems = document.getElementById('statTotalItems');
    const statInStock = document.getElementById('statInStock');
    const statDeployed = document.getElementById('statDeployed');
    const statLowStock = document.getElementById('statLowStock');

    // Modals
    const addAccessoryModal = document.getElementById('addAccessoryModal');
    const editAccessoryModal = document.getElementById('editAccessoryModal');
    const deleteAccessoryModal = document.getElementById('deleteAccessoryModal');
    const issueModal = document.getElementById('issueModal');

    const openAddModalBtn = document.getElementById('openAddModalBtn');
    const closeAddModalBtn = document.getElementById('closeAddModalBtn');
    const cancelAddModalBtn = document.getElementById('cancelAddModalBtn');
    const addAccessoryForm = document.getElementById('addAccessoryForm');
    const saveAccBtn = document.getElementById('saveAccBtn');

    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const cancelEditModalBtn = document.getElementById('cancelEditModalBtn');
    const editAccessoryForm = document.getElementById('editAccessoryForm');
    const updateAccBtn = document.getElementById('updateAccBtn');

    const closeDeleteModalBtn = document.getElementById('closeDeleteModalBtn');
    const cancelDeleteModalBtn = document.getElementById('cancelDeleteModalBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteAccessoryName = document.getElementById('deleteAccessoryName');
    let accessoryToDeleteId = null;

    const closeIssueModalBtn = document.getElementById('closeIssueModalBtn');
    const cancelIssueBtn = document.getElementById('cancelIssueBtn');
    const issueForm = document.getElementById('issueForm');
    const exportAccBtn = document.getElementById('exportAccBtn');

    // Helper: Construct API URL with preview query if present
    function getApiUrl(params = {}) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('preview')) {
            params.preview = 1;
        }
        const qs = new URLSearchParams(params).toString();
        return 'api/accessories.php' + (qs ? '?' + qs : '');
    }

    // Generate Accessory SKU Tag in format ASO + MMYY + 3DIGITSERIAL (e.g. ASO1026001)
    function generateNextAccessorySku(offset = 0) {
        const now = new Date();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const yy = String(now.getFullYear()).slice(-2);
        const prefix = `ASO${mm}${yy}`;

        let maxNum = 0;
        accessories.forEach(a => {
            if (a.sku && a.sku.startsWith(prefix)) {
                const numPart = parseInt(a.sku.substring(prefix.length), 10);
                if (!isNaN(numPart) && numPart > maxNum) {
                    maxNum = numPart;
                }
            } else if (a.sku) {
                const match = a.sku.match(/\d{3}$/);
                if (match) {
                    const numPart = parseInt(match[0], 10);
                    if (!isNaN(numPart) && numPart > maxNum) {
                        maxNum = numPart;
                    }
                }
            }
        });

        if (maxNum === 0 && accessories.length > 0) {
            maxNum = accessories.length;
        }

        const nextNum = maxNum + 1 + offset;
        return `${prefix}${String(nextNum).padStart(3, '0')}`;
    }

    // Initialize
    function init() {
        if (accessories.length === 0) {
            loadAccessories();
        } else {
            renderTable();
            updateStats();
        }
        bindEvents();
        fetchDynamicCategories();
        fetchDynamicBranches();
    }

    // Load accessories and stats from database via API
    function loadAccessories(callback) {
        fetch(getApiUrl())
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.accessories)) {
                    accessories = data.accessories;
                    renderTable();
                    if (data.stats) updateStats(data.stats);
                    if (typeof callback === 'function') callback();
                }
            })
            .catch(err => {
                console.warn('Could not fetch accessories from database:', err);
                if (typeof callback === 'function') callback();
            });
    }

    // Fetch and populate dynamic categories from Master API
    function fetchDynamicCategories(selectedVal) {
        fetch('api/accessory_categories.php?status=Active' + (window.location.search.includes('preview=1') ? '&preview=1' : ''))
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.categories)) {
                    const accCategorySelect = document.getElementById('accCategory');
                    const editAccCategorySelect = document.getElementById('editAccCategory');
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

                    if (accCategorySelect) {
                        populate(accCategorySelect, 'Select Accessory Category', accCategorySelect.value);
                    }

                    if (editAccCategorySelect) {
                        const curEdit = (selectedVal !== undefined) ? selectedVal : editAccCategorySelect.value;
                        populate(editAccCategorySelect, 'Select Accessory Category', curEdit);
                    }

                    if (categoryFilterSelect) {
                        const curFilter = categoryFilterSelect.value;
                        let filterHtml = '<option value="all">All Accessory Categories</option>';
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
                console.warn('Could not refresh dynamic categories:', err);
            });
    }

    // Fetch and populate active branch locations from Locations Master API
    function fetchDynamicBranches(selectedVal) {
        fetch('api/locations.php?status=Active' + (window.location.search.includes('preview=1') ? '&preview=1' : ''))
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.locations)) {
                    const accBranch = document.getElementById('accBranch');
                    const editAccBranch = document.getElementById('editAccBranch');

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

                    if (accBranch) {
                        populate(accBranch, 'Select Branch Location', accBranch.value);
                    }
                    if (editAccBranch) {
                        const curEdit = (selectedVal !== undefined) ? selectedVal : editAccBranch.value;
                        populate(editAccBranch, 'Select Branch Location', curEdit);
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
                if (addAccessoryForm) addAccessoryForm.reset();
                const skuInput = document.getElementById('accSku');
                if (skuInput) skuInput.value = generateNextAccessorySku();

                // Fetch real guaranteed unique next SKU from server
                fetch(getApiUrl({ action: 'get_next_sku' }))
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.next_sku && skuInput) {
                            skuInput.value = res.next_sku;
                        }
                    })
                    .catch(() => {});

                fetchDynamicCategories('');
                fetchDynamicBranches('');
                if (window.SearchableSelect) {
                    window.SearchableSelect.sync(document.getElementById('accCategory'));
                    window.SearchableSelect.sync(document.getElementById('accBranch'));
                }
                openModal(addAccessoryModal);
            });
        }

        if (closeAddModalBtn) closeAddModalBtn.addEventListener('click', () => closeModal(addAccessoryModal));
        if (cancelAddModalBtn) cancelAddModalBtn.addEventListener('click', () => closeModal(addAccessoryModal));

        if (addAccessoryForm) {
            addAccessoryForm.addEventListener('submit', handleAddAccessory);
        }

        // Edit Modal
        if (closeEditModalBtn) closeEditModalBtn.addEventListener('click', () => closeModal(editAccessoryModal));
        if (cancelEditModalBtn) cancelEditModalBtn.addEventListener('click', () => closeModal(editAccessoryModal));

        if (editAccessoryForm) {
            editAccessoryForm.addEventListener('submit', handleUpdateAccessory);
        }

        // Delete Modal
        if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener('click', () => closeModal(deleteAccessoryModal));
        if (cancelDeleteModalBtn) cancelDeleteModalBtn.addEventListener('click', () => closeModal(deleteAccessoryModal));

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', handleConfirmDelete);
        }

        // Issue Modal
        if (closeIssueModalBtn) closeIssueModalBtn.addEventListener('click', () => closeModal(issueModal));
        if (cancelIssueBtn) cancelIssueBtn.addEventListener('click', () => closeModal(issueModal));

        if (issueForm) {
            issueForm.addEventListener('submit', handleConfirmIssue);
        }

        // Export CSV
        if (exportAccBtn) {
            exportAccBtn.addEventListener('click', handleExportCsv);
        }

        // Close on Escape or click outside
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(addAccessoryModal);
                closeModal(editAccessoryModal);
                closeModal(deleteAccessoryModal);
                closeModal(issueModal);
            }
        });

        [addAccessoryModal, editAccessoryModal, deleteAccessoryModal, issueModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal(modal);
                });
            }
        });
    }

    // Filter Logic
    function getFilteredList() {
        return accessories.filter(item => {
            // Branch Location
            if (selectedBranch !== 'all' && (item.branch_location || '') !== selectedBranch) return false;

            // Category
            if (selectedCategory !== 'all' && item.category !== selectedCategory) return false;

            // Status
            const itemStatus = getItemStatus(item);
            if (selectedStatus !== 'all' && itemStatus !== selectedStatus) return false;

            // Search
            if (searchTerm) {
                const combined = `${item.sku} ${item.name} ${item.brand} ${item.model || ''} ${item.category} ${item.branch_location || ''} ${item.location || ''}`.toLowerCase();
                if (!combined.includes(searchTerm)) return false;
            }

            return true;
        });
    }

    function getItemStatus(item) {
        const inStock = parseInt(item.inStock || 0, 10);
        const minStock = parseInt(item.minStock || 0, 10);
        if (inStock === 0) return 'Out of Stock';
        if (inStock <= minStock) return 'Low Stock';
        return 'In Stock';
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
                            <div style="font-weight: 600; font-size: 15px; color: var(--text-primary); margin-bottom: 4px;">No accessories found</div>
                            <div style="font-size: 12.5px; color: var(--text-muted);">Try adjusting your search query or filter selection.</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(item => {
            const status = getItemStatus(item);
            let statusBadge = '';
            if (status === 'In Stock') {
                statusBadge = `<span class="asset-status-badge status-available"><span class="dot"></span>In Stock</span>`;
            } else if (status === 'Low Stock') {
                statusBadge = `<span class="asset-status-badge status-maintenance"><span class="dot"></span>Low Stock (${item.inStock})</span>`;
            } else {
                statusBadge = `<span class="asset-status-badge status-retired" style="background:#fef2f2; color:#b91c1c;"><span class="dot" style="background:#ef4444;"></span>Out of Stock</span>`;
            }

            const locParts = [];
            if (item.branch_location) locParts.push(escapeHtml(item.branch_location));
            if (item.location) locParts.push(escapeHtml(item.location));
            const locText = locParts.length > 0 ? locParts.join(' • ') : 'Depot';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="white-space: nowrap;">
                    <span class="asset-tag-badge" title="Accessory SKU">${escapeHtml(item.sku)}</span>
                </td>
                <td>
                    <div class="asset-details-wrap">
                        <span class="asset-name-title" onclick="window.accMgr.openEdit(${item.id})">${escapeHtml(item.name)}</span>
                        <span class="asset-spec-sub">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            ${locText}
                        </span>
                    </div>
                </td>
                <td><span class="category-pill">${escapeHtml(item.category)}</span></td>
                <td>
                    <div style="font-weight: 500; font-size: 13px; color: var(--text-primary);">${escapeHtml(item.brand)}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">${escapeHtml(item.model || '—')}</div>
                </td>
                <td style="text-align: center;">
                    <span class="qty-badge ${item.inStock === 0 ? 'qty-zero' : 'qty-in-stock'}">${item.inStock}</span>
                </td>
                <td style="text-align: center;">
                    <span class="qty-badge qty-deployed">${item.deployed}</span>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                        <button class="action-icon-btn btn-view" title="Issue to Staff" onclick="window.accMgr.openIssue(${item.id})" ${item.inStock === 0 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''}>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                        </button>
                        <button class="action-icon-btn btn-edit" title="Edit Accessory" onclick="window.accMgr.openEdit(${item.id})">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button class="action-icon-btn btn-delete" title="Delete Accessory" onclick="window.accMgr.deleteItem(${item.id})">
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
            if (statTotalItems) statTotalItems.textContent = serverStats.total ?? accessories.length;
            if (statInStock) statInStock.textContent = (serverStats.in_stock ?? 0).toLocaleString('en-IN');
            if (statDeployed) statDeployed.textContent = (serverStats.deployed ?? 0).toLocaleString('en-IN');
            if (statLowStock) statLowStock.textContent = serverStats.low_stock ?? 0;
            return;
        }

        let totalItems = accessories.length;
        let inStockTotal = 0;
        let deployedTotal = 0;
        let lowStockCount = 0;

        accessories.forEach(item => {
            const inStock = parseInt(item.inStock || 0, 10);
            const deployed = parseInt(item.deployed || 0, 10);
            const minStock = parseInt(item.minStock || 0, 10);

            inStockTotal += inStock;
            deployedTotal += deployed;
            if (inStock > 0 && inStock <= minStock) {
                lowStockCount++;
            }
        });

        if (statTotalItems) statTotalItems.textContent = totalItems;
        if (statInStock) statInStock.textContent = inStockTotal.toLocaleString('en-IN');
        if (statDeployed) statDeployed.textContent = deployedTotal.toLocaleString('en-IN');
        if (statLowStock) statLowStock.textContent = lowStockCount;
    }

    // Add New Accessory - Saves directly to Database
    function handleAddAccessory(e) {
        e.preventDefault();
        const name = document.getElementById('accName').value.trim();
        const category = document.getElementById('accCategory').value;
        const branchLocation = document.getElementById('accBranch') ? document.getElementById('accBranch').value : '';
        const brand = document.getElementById('accBrand').value.trim();
        const model = document.getElementById('accModel').value.trim();
        const qty = parseInt(document.getElementById('accQty').value, 10) || 1;
        const minStock = parseInt(document.getElementById('accMinStock').value, 10) || 5;
        const location = document.getElementById('accLocation').value.trim();
        const skuInput = document.getElementById('accSku');
        const sku = (skuInput && skuInput.value.trim()) ? skuInput.value.trim() : generateNextAccessorySku();

        if (saveAccBtn) {
            saveAccBtn.disabled = true;
            saveAccBtn.textContent = 'Saving...';
        }

        const payload = {
            action: 'create',
            sku: sku,
            name: name,
            category: category,
            branch_location: branchLocation,
            brand: brand,
            model: model,
            totalQty: qty,
            minStock: minStock,
            location: location || 'HQ - New York Depot'
        };

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (saveAccBtn) {
                saveAccBtn.disabled = false;
                saveAccBtn.textContent = 'Save Accessory';
            }
            if (data.success) {
                showNotification(data.message || `Added new accessory "${name}".`, 'success');
                closeModal(addAccessoryModal);
                loadAccessories();
            } else {
                showNotification(data.message || 'Failed to save accessory.', 'error');
            }
        })
        .catch(err => {
            if (saveAccBtn) {
                saveAccBtn.disabled = false;
                saveAccBtn.textContent = 'Save Accessory';
            }
            showNotification('Server error while saving accessory.', 'error');
            console.error(err);
        });
    }

    // Update Accessory - Saves edits directly to Database
    function handleUpdateAccessory(e) {
        e.preventDefault();
        const editId = parseInt(document.getElementById('editAccId').value, 10);
        const name = document.getElementById('editAccName').value.trim();
        const category = document.getElementById('editAccCategory').value;
        const branchLocation = document.getElementById('editAccBranch') ? document.getElementById('editAccBranch').value : '';
        const brand = document.getElementById('editAccBrand').value.trim();
        const model = document.getElementById('editAccModel').value.trim();
        const qty = parseInt(document.getElementById('editAccQty').value, 10) || 1;
        const minStock = parseInt(document.getElementById('editAccMinStock').value, 10) || 5;
        const location = document.getElementById('editAccLocation').value.trim();

        if (updateAccBtn) {
            updateAccBtn.disabled = true;
            updateAccBtn.textContent = 'Updating...';
        }

        const payload = {
            action: 'edit',
            id: editId,
            name: name,
            category: category,
            branch_location: branchLocation,
            brand: brand,
            model: model,
            totalQty: qty,
            minStock: minStock,
            location: location
        };

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (updateAccBtn) {
                updateAccBtn.disabled = false;
                updateAccBtn.textContent = 'Update Accessory';
            }
            if (data.success) {
                showNotification(data.message || `Updated accessory "${name}".`, 'success');
                closeModal(editAccessoryModal);
                loadAccessories();
            } else {
                showNotification(data.message || 'Failed to update accessory.', 'error');
            }
        })
        .catch(err => {
            if (updateAccBtn) {
                updateAccBtn.disabled = false;
                updateAccBtn.textContent = 'Update Accessory';
            }
            showNotification('Server error while updating accessory.', 'error');
            console.error(err);
        });
    }

    // Issue (Check-Out)
    function openIssueModal(id) {
        const item = accessories.find(a => a.id === id);
        if (!item || item.inStock <= 0) {
            showNotification('No available units in stock to issue.', 'warning');
            return;
        }

        document.getElementById('issueAccId').value = item.id;
        document.getElementById('issueAccName').textContent = `${item.name} (${item.brand} ${item.model || ''})`;
        document.getElementById('issueAvailableStock').textContent = item.inStock;

        const qtyInput = document.getElementById('issueQty');
        qtyInput.value = 1;
        qtyInput.max = item.inStock;

        const dateInput = document.getElementById('issueDate');
        dateInput.value = new Date().toISOString().split('T')[0];

        openModal(issueModal);
    }

    function handleConfirmIssue(e) {
        e.preventDefault();
        const id = parseInt(document.getElementById('issueAccId').value, 10);
        const item = accessories.find(a => a.id === id);
        if (!item) return;

        const qty = parseInt(document.getElementById('issueQty').value, 10) || 1;
        const employee = document.getElementById('issueEmployee').value;

        if (qty > item.inStock) {
            showNotification('Requested quantity exceeds available stock.', 'error');
            return;
        }

        const payload = {
            action: 'issue',
            id: id,
            qty: qty,
            employee: employee
        };

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || `Issued ${qty} unit(s) of ${item.name} to ${employee}.`, 'success');
                closeModal(issueModal);
                loadAccessories();
            } else {
                showNotification(data.message || 'Failed to issue accessory.', 'error');
            }
        })
        .catch(err => {
            showNotification('Server error while issuing accessory.', 'error');
            console.error(err);
        });
    }

    // Edit Item Modal - Opens dedicated Edit modal
    function openEditModal(id) {
        const item = accessories.find(a => a.id === id);
        if (!item) return;

        document.getElementById('editAccId').value = item.id;
        const skuInput = document.getElementById('editAccSku');
        if (skuInput) skuInput.value = item.sku || '';
        document.getElementById('editAccName').value = item.name;
        document.getElementById('editAccBrand').value = item.brand;
        document.getElementById('editAccModel').value = item.model || '';
        document.getElementById('editAccQty').value = item.totalQty;
        document.getElementById('editAccMinStock').value = item.minStock;
        document.getElementById('editAccLocation').value = item.location || '';

        const catSelect = document.getElementById('editAccCategory');
        if (catSelect) {
            catSelect.value = item.category;
        }

        const branchSelect = document.getElementById('editAccBranch');
        if (branchSelect) {
            branchSelect.value = item.branch_location || '';
        }

        fetchDynamicCategories(item.category);
        fetchDynamicBranches(item.branch_location || '');

        if (window.SearchableSelect) {
            if (catSelect) window.SearchableSelect.sync(catSelect);
            if (branchSelect) window.SearchableSelect.sync(branchSelect);
        }

        openModal(editAccessoryModal);
    }

    // Delete Item Modal - Opens confirmation modal (same as master pages)
    function openDeleteModal(id) {
        const item = accessories.find(a => a.id === id);
        if (!item) return;

        accessoryToDeleteId = item.id;
        if (deleteAccessoryName) {
            deleteAccessoryName.textContent = `"${item.name}" (${item.sku})`;
        }
        openModal(deleteAccessoryModal);
    }

    // Confirm Delete Action - Persists directly to Database
    function handleConfirmDelete() {
        if (!accessoryToDeleteId) return;

        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';
        }

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: accessoryToDeleteId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (confirmDeleteBtn) {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Yes, Delete Accessory';
            }
            if (data.success) {
                showNotification(data.message || 'Accessory deleted successfully.', 'info');
                closeModal(deleteAccessoryModal);
                accessoryToDeleteId = null;
                loadAccessories();
            } else {
                showNotification(data.message || 'Failed to delete accessory.', 'error');
            }
        })
        .catch(err => {
            if (confirmDeleteBtn) {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Yes, Delete Accessory';
            }
            showNotification('Server error while deleting accessory.', 'error');
            console.error(err);
        });
    }

    // Export CSV
    function handleExportCsv() {
        const list = getFilteredList();
        if (list.length === 0) {
            showNotification('No accessories to export.', 'warning');
            return;
        }

        let csv = 'SKU,Name,Category,Branch Location,Brand,Model,TotalQty,InStock,Deployed,Status,Storage Location\n';
        list.forEach(a => {
            csv += `"${a.sku}","${a.name.replace(/"/g, '""')}","${a.category}","${(a.branch_location || '').replace(/"/g, '""')}","${a.brand}","${(a.model || '').replace(/"/g, '""')}",${a.totalQty},${a.inStock},${a.deployed},"${getItemStatus(a)}","${(a.location || '').replace(/"/g, '""')}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Accessories_List_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification(`Exported ${list.length} accessories to CSV.`, 'success');
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
    window.accMgr = {
        openIssue: openIssueModal,
        openEdit: openEditModal,
        deleteItem: openDeleteModal,
        reload: loadAccessories
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
