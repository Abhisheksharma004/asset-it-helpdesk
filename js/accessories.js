/**
 * Simple Accessories Management Script
 * VIROS IT Asset & Service Desk Portal
 */

(function () {
    'use strict';

    // Sample Accessories Dataset
    let accessories = [
        { id: 1, sku: "ASO1026001", name: "Logitech MX Master 3S Wireless Mouse", category: "Keyboards & Mice", brand: "Logitech", model: "MX Master 3S", totalQty: 120, inStock: 34, deployed: 86, minStock: 15, location: "HQ - New York Depot (Shelf A-02)" },
        { id: 2, sku: "ASO1026002", name: "Dell Pro Wireless Keyboard & Mouse KM5221W", category: "Keyboards & Mice", brand: "Dell", model: "KM5221W", totalQty: 250, inStock: 58, deployed: 192, minStock: 25, location: "HQ - New York Depot (Shelf A-05)" },
        { id: 3, sku: "ASO1026003", name: "Apple Magic Keyboard with Touch ID", category: "Keyboards & Mice", brand: "Apple", model: "Numeric Keypad", totalQty: 60, inStock: 8, deployed: 52, minStock: 10, location: "Austin Hub Depot (Shelf B-01)" },
        { id: 4, sku: "ASO1026004", name: "Dell Thunderbolt 4 Dock WD22TB4", category: "Docks & Hubs", brand: "Dell", model: "WD22TB4 180W", totalQty: 85, inStock: 19, deployed: 66, minStock: 10, location: "HQ - New York Depot (Shelf C-01)" },
        { id: 5, sku: "ASO1026005", name: "Anker 575 USB-C Docking Station (13-in-1)", category: "Docks & Hubs", brand: "Anker", model: "Triple Display 85W", totalQty: 45, inStock: 3, deployed: 42, minStock: 8, location: "Austin Hub Depot (Shelf C-03)" },
        { id: 6, sku: "ASO1026006", name: "CalDigit TS4 Thunderbolt 4 Dock (18 Ports)", category: "Docks & Hubs", brand: "CalDigit", model: "TS4-US 98W", totalQty: 25, inStock: 0, deployed: 25, minStock: 5, location: "London Office Store (Shelf D-01)" },
        { id: 7, sku: "ASO1026007", name: "Jabra Evolve2 65 UC Wireless Headset", category: "Headsets & Audio", brand: "Jabra", model: "HSC110W Dual ANC", totalQty: 110, inStock: 22, deployed: 88, minStock: 15, location: "Bangalore DC Depot (Shelf H-01)" },
        { id: 8, sku: "ASO1026008", name: "Poly Voyager Focus 2 UC Headset", category: "Headsets & Audio", brand: "Poly", model: "Focus 2 Bluetooth", totalQty: 50, inStock: 14, deployed: 36, minStock: 10, location: "Austin Hub Depot (Shelf H-02)" },
        { id: 9, sku: "ASO1026009", name: "Sony WH-1000XM5 ANC Headphones", category: "Headsets & Audio", brand: "Sony", model: "WH-1000XM5 Black", totalQty: 30, inStock: 2, deployed: 28, minStock: 6, location: "HQ - New York Depot (Shelf H-04)" },
        { id: 10, sku: "ASO1026010", name: "Logitech Brio 4K Ultra HD Webcam", category: "Webcams & Video", brand: "Logitech", model: "Brio 4K HDR", totalQty: 95, inStock: 28, deployed: 67, minStock: 12, location: "HQ - New York Depot (Shelf V-01)" },
        { id: 11, sku: "ASO1026011", name: "Anker PowerConf C300 HD Webcam", category: "Webcams & Video", brand: "Anker", model: "C300 1080p 60fps", totalQty: 75, inStock: 21, deployed: 54, minStock: 10, location: "Bangalore DC Depot (Shelf V-02)" },
        { id: 12, sku: "ASO1026012", name: "Apple 96W USB-C Power Adapter", category: "Chargers & Power Adapters", brand: "Apple", model: "96W GaN Fast Charger", totalQty: 80, inStock: 16, deployed: 64, minStock: 12, location: "Austin Hub Depot (Shelf P-01)" },
        { id: 13, sku: "ASO1026013", name: "Lenovo 65W USB-C GaN Travel Charger", category: "Chargers & Power Adapters", brand: "Lenovo", model: "ThinkPad 65W AC", totalQty: 140, inStock: 35, deployed: 105, minStock: 20, location: "HQ - New York Depot (Shelf P-03)" },
        { id: 14, sku: "ASO1026014", name: "Dell 130W USB-C Slim AC Adapter", category: "Chargers & Power Adapters", brand: "Dell", model: "HA130PM170", totalQty: 90, inStock: 0, deployed: 90, minStock: 10, location: "HQ - New York Depot (Shelf P-04)" },
        { id: 15, sku: "ASO1026015", name: "Belkin USB-C to 4K HDMI Adapter", category: "Cables & Display Adapters", brand: "Belkin", model: "AVC002btBK 4K@60Hz", totalQty: 150, inStock: 44, deployed: 106, minStock: 20, location: "London Office Store (Shelf C-02)" },
        { id: 16, sku: "ASO1026016", name: "Anker USB-C to Lightning Braided Cable (6ft)", category: "Cables & Display Adapters", brand: "Anker", model: "PowerLine III MFi", totalQty: 80, inStock: 0, deployed: 80, minStock: 15, location: "Bangalore DC Depot (Shelf C-05)" },
        { id: 17, sku: "ASO1026017", name: "YubiKey 5 NFC Hardware Security Key", category: "Security Tokens & Smart Keys", brand: "Yubico", model: "Y-501 FIDO2", totalQty: 120, inStock: 27, deployed: 93, minStock: 15, location: "HQ - New York Depot (Safe Vault 1)" },
        { id: 18, sku: "ASO1026018", name: "Rain Design mStand Aluminum Laptop Stand", category: "Laptop Stands & Mounts", brand: "Rain Design", model: "mStand 10032", totalQty: 75, inStock: 19, deployed: 56, minStock: 10, location: "Austin Hub Depot (Shelf S-01)" }
    ];

    // Filter states
    let searchTerm = '';
    let selectedCategory = 'all';
    let selectedStatus = 'all';

    // DOM Elements
    const tbody = document.getElementById('accessoriesTbody');
    const searchInput = document.getElementById('searchInput');
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
        renderTable();
        updateStats();
        bindEvents();
        fetchDynamicCategories();
    }

    // Fetch and populate dynamic categories from Master API
    function fetchDynamicCategories(selectedVal) {
        fetch('api/accessory_categories.php?status=Active')
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
                if (addAccessoryForm) addAccessoryForm.reset();
                const skuInput = document.getElementById('accSku');
                if (skuInput) skuInput.value = generateNextAccessorySku();
                fetchDynamicCategories('');
                if (window.SearchableSelect) {
                    window.SearchableSelect.sync(document.getElementById('accCategory'));
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
            // Category
            if (selectedCategory !== 'all' && item.category !== selectedCategory) return false;

            // Status
            const itemStatus = getItemStatus(item);
            if (selectedStatus !== 'all' && itemStatus !== selectedStatus) return false;

            // Search
            if (searchTerm) {
                const combined = `${item.sku} ${item.name} ${item.brand} ${item.model} ${item.category}`.toLowerCase();
                if (!combined.includes(searchTerm)) return false;
            }

            return true;
        });
    }

    function getItemStatus(item) {
        if (item.inStock === 0) return 'Out of Stock';
        if (item.inStock <= item.minStock) return 'Low Stock';
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
                            ${escapeHtml(item.location || 'Depot')}
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
    function updateStats() {
        let totalItems = accessories.length;
        let inStockTotal = 0;
        let deployedTotal = 0;
        let lowStockCount = 0;

        accessories.forEach(item => {
            inStockTotal += item.inStock;
            deployedTotal += item.deployed;
            if (item.inStock > 0 && item.inStock <= item.minStock) {
                lowStockCount++;
            }
        });

        if (statTotalItems) statTotalItems.textContent = totalItems;
        if (statInStock) statInStock.textContent = inStockTotal.toLocaleString('en-IN');
        if (statDeployed) statDeployed.textContent = deployedTotal.toLocaleString('en-IN');
        if (statLowStock) statLowStock.textContent = lowStockCount;
    }

    // Add New Accessory
    function handleAddAccessory(e) {
        e.preventDefault();
        const name = document.getElementById('accName').value.trim();
        const category = document.getElementById('accCategory').value;
        const brand = document.getElementById('accBrand').value.trim();
        const model = document.getElementById('accModel').value.trim();
        const qty = parseInt(document.getElementById('accQty').value, 10) || 1;
        const minStock = parseInt(document.getElementById('accMinStock').value, 10) || 5;
        const location = document.getElementById('accLocation').value.trim();

        if (saveAccBtn) {
            saveAccBtn.disabled = true;
            saveAccBtn.textContent = 'Saving...';
        }

        const newId = accessories.length > 0 ? Math.max(...accessories.map(a => a.id)) + 1 : 1;
        const skuInput = document.getElementById('accSku');
        const newSku = (skuInput && skuInput.value.trim()) ? skuInput.value.trim() : generateNextAccessorySku();

        accessories.unshift({
            id: newId,
            sku: newSku,
            name: name,
            category: category,
            brand: brand,
            model: model,
            totalQty: qty,
            inStock: qty,
            deployed: 0,
            minStock: minStock,
            location: location || 'HQ - New York Depot'
        });

        if (saveAccBtn) {
            saveAccBtn.disabled = false;
            saveAccBtn.textContent = 'Save Accessory';
        }

        closeModal(addAccessoryModal);
        updateStats();
        renderTable();
        showNotification(`Added new accessory "${name}" (${newSku}).`, 'success');
    }

    // Update Accessory
    function handleUpdateAccessory(e) {
        e.preventDefault();
        const editId = document.getElementById('editAccId').value;
        const name = document.getElementById('editAccName').value.trim();
        const category = document.getElementById('editAccCategory').value;
        const brand = document.getElementById('editAccBrand').value.trim();
        const model = document.getElementById('editAccModel').value.trim();
        const qty = parseInt(document.getElementById('editAccQty').value, 10) || 1;
        const minStock = parseInt(document.getElementById('editAccMinStock').value, 10) || 5;
        const location = document.getElementById('editAccLocation').value.trim();

        if (updateAccBtn) {
            updateAccBtn.disabled = true;
            updateAccBtn.textContent = 'Updating...';
        }

        const item = accessories.find(a => a.id === parseInt(editId, 10));
        if (item) {
            item.name = name;
            item.category = category;
            item.brand = brand;
            item.model = model;
            const diff = qty - item.totalQty;
            item.totalQty = qty;
            item.inStock = Math.max(0, item.inStock + diff);
            item.minStock = minStock;
            item.location = location;

            if (updateAccBtn) {
                updateAccBtn.disabled = false;
                updateAccBtn.textContent = 'Update Accessory';
            }

            closeModal(editAccessoryModal);
            updateStats();
            renderTable();
            showNotification(`Updated accessory "${name}".`, 'success');
        } else {
            if (updateAccBtn) {
                updateAccBtn.disabled = false;
                updateAccBtn.textContent = 'Update Accessory';
            }
            showNotification('Failed to find accessory to update.', 'error');
        }
    }

    // Issue (Check-Out)
    function openIssueModal(id) {
        const item = accessories.find(a => a.id === id);
        if (!item || item.inStock <= 0) {
            showNotification('No available units in stock to issue.', 'warning');
            return;
        }

        document.getElementById('issueAccId').value = item.id;
        document.getElementById('issueAccName').textContent = `${item.name} (${item.brand} ${item.model})`;
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

        item.inStock -= qty;
        item.deployed += qty;

        closeModal(issueModal);
        updateStats();
        renderTable();
        showNotification(`Issued ${qty} unit(s) of ${item.name} to ${employee}.`, 'success');
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
        document.getElementById('editAccModel').value = item.model;
        document.getElementById('editAccQty').value = item.totalQty;
        document.getElementById('editAccMinStock').value = item.minStock;
        document.getElementById('editAccLocation').value = item.location;

        const catSelect = document.getElementById('editAccCategory');
        if (catSelect) {
            catSelect.value = item.category;
        }

        fetchDynamicCategories(item.category);
        if (window.SearchableSelect && catSelect) {
            window.SearchableSelect.sync(catSelect);
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

    // Confirm Delete Action
    function handleConfirmDelete() {
        if (!accessoryToDeleteId) return;

        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';
        }

        const item = accessories.find(a => a.id === accessoryToDeleteId);
        const itemName = item ? item.name : 'Accessory';

        accessories = accessories.filter(a => a.id !== accessoryToDeleteId);
        accessoryToDeleteId = null;

        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.textContent = 'Yes, Delete Accessory';
        }

        closeModal(deleteAccessoryModal);
        updateStats();
        renderTable();
        showNotification(`Accessory "${itemName}" deleted successfully.`, 'info');
    }

    // Export CSV
    function handleExportCsv() {
        const list = getFilteredList();
        if (list.length === 0) {
            showNotification('No accessories to export.', 'warning');
            return;
        }

        let csv = 'SKU,Name,Category,Brand,Model,TotalQty,InStock,Deployed,Status,Location\n';
        list.forEach(a => {
            csv += `"${a.sku}","${a.name.replace(/"/g, '""')}","${a.category}","${a.brand}","${a.model}",${a.totalQty},${a.inStock},${a.deployed},"${getItemStatus(a)}","${a.location}"\n`;
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
        deleteItem: openDeleteModal
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
