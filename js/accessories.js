/**
 * Simple Accessories Management Script
 * VIROS IT Asset & Service Desk Portal
 */

(function () {
    'use strict';

    // Sample Accessories Dataset
    let accessories = [
        { id: 1, sku: "ACC-MOU-001", name: "Logitech MX Master 3S Wireless Mouse", category: "Keyboards & Mice", brand: "Logitech", model: "MX Master 3S", totalQty: 120, inStock: 34, deployed: 86, minStock: 15, location: "HQ - New York Depot (Shelf A-02)" },
        { id: 2, sku: "ACC-KBD-002", name: "Dell Pro Wireless Keyboard & Mouse KM5221W", category: "Keyboards & Mice", brand: "Dell", model: "KM5221W", totalQty: 250, inStock: 58, deployed: 192, minStock: 25, location: "HQ - New York Depot (Shelf A-05)" },
        { id: 3, sku: "ACC-KBD-003", name: "Apple Magic Keyboard with Touch ID", category: "Keyboards & Mice", brand: "Apple", model: "Numeric Keypad", totalQty: 60, inStock: 8, deployed: 52, minStock: 10, location: "Austin Hub Depot (Shelf B-01)" },
        { id: 4, sku: "ACC-DCK-004", name: "Dell Thunderbolt 4 Dock WD22TB4", category: "Docks & Hubs", brand: "Dell", model: "WD22TB4 180W", totalQty: 85, inStock: 19, deployed: 66, minStock: 10, location: "HQ - New York Depot (Shelf C-01)" },
        { id: 5, sku: "ACC-DCK-005", name: "Anker 575 USB-C Docking Station (13-in-1)", category: "Docks & Hubs", brand: "Anker", model: "Triple Display 85W", totalQty: 45, inStock: 3, deployed: 42, minStock: 8, location: "Austin Hub Depot (Shelf C-03)" },
        { id: 6, sku: "ACC-DCK-006", name: "CalDigit TS4 Thunderbolt 4 Dock (18 Ports)", category: "Docks & Hubs", brand: "CalDigit", model: "TS4-US 98W", totalQty: 25, inStock: 0, deployed: 25, minStock: 5, location: "London Office Store (Shelf D-01)" },
        { id: 7, sku: "ACC-AUD-007", name: "Jabra Evolve2 65 UC Wireless Headset", category: "Headsets & Audio", brand: "Jabra", model: "HSC110W Dual ANC", totalQty: 110, inStock: 22, deployed: 88, minStock: 15, location: "Bangalore DC Depot (Shelf H-01)" },
        { id: 8, sku: "ACC-AUD-008", name: "Poly Voyager Focus 2 UC Headset", category: "Headsets & Audio", brand: "Poly", model: "Focus 2 Bluetooth", totalQty: 50, inStock: 14, deployed: 36, minStock: 10, location: "Austin Hub Depot (Shelf H-02)" },
        { id: 9, sku: "ACC-AUD-009", name: "Sony WH-1000XM5 ANC Headphones", category: "Headsets & Audio", brand: "Sony", model: "WH-1000XM5 Black", totalQty: 30, inStock: 2, deployed: 28, minStock: 6, location: "HQ - New York Depot (Shelf H-04)" },
        { id: 10, sku: "ACC-CAM-010", name: "Logitech Brio 4K Ultra HD Webcam", category: "Webcams & Video", brand: "Logitech", model: "Brio 4K HDR", totalQty: 95, inStock: 28, deployed: 67, minStock: 12, location: "HQ - New York Depot (Shelf V-01)" },
        { id: 11, sku: "ACC-CAM-011", name: "Anker PowerConf C300 HD Webcam", category: "Webcams & Video", brand: "Anker", model: "C300 1080p 60fps", totalQty: 75, inStock: 21, deployed: 54, minStock: 10, location: "Bangalore DC Depot (Shelf V-02)" },
        { id: 12, sku: "ACC-PWR-012", name: "Apple 96W USB-C Power Adapter", category: "Chargers & Power Adapters", brand: "Apple", model: "96W GaN Fast Charger", totalQty: 80, inStock: 16, deployed: 64, minStock: 12, location: "Austin Hub Depot (Shelf P-01)" },
        { id: 13, sku: "ACC-PWR-013", name: "Lenovo 65W USB-C GaN Travel Charger", category: "Chargers & Power Adapters", brand: "Lenovo", model: "ThinkPad 65W AC", totalQty: 140, inStock: 35, deployed: 105, minStock: 20, location: "HQ - New York Depot (Shelf P-03)" },
        { id: 14, sku: "ACC-PWR-014", name: "Dell 130W USB-C Slim AC Adapter", category: "Chargers & Power Adapters", brand: "Dell", model: "HA130PM170", totalQty: 90, inStock: 0, deployed: 90, minStock: 10, location: "HQ - New York Depot (Shelf P-04)" },
        { id: 15, sku: "ACC-CBL-015", name: "Belkin USB-C to 4K HDMI Adapter", category: "Cables & Display Adapters", brand: "Belkin", model: "AVC002btBK 4K@60Hz", totalQty: 150, inStock: 44, deployed: 106, minStock: 20, location: "London Office Store (Shelf C-02)" },
        { id: 16, sku: "ACC-CBL-016", name: "Anker USB-C to Lightning Braided Cable (6ft)", category: "Cables & Display Adapters", brand: "Anker", model: "PowerLine III MFi", totalQty: 80, inStock: 0, deployed: 80, minStock: 15, location: "Bangalore DC Depot (Shelf C-05)" },
        { id: 17, sku: "ACC-SEC-017", name: "YubiKey 5 NFC Hardware Security Key", category: "Security Tokens & Smart Keys", brand: "Yubico", model: "Y-501 FIDO2", totalQty: 120, inStock: 27, deployed: 93, minStock: 15, location: "HQ - New York Depot (Safe Vault 1)" },
        { id: 18, sku: "ACC-STD-018", name: "Rain Design mStand Aluminum Laptop Stand", category: "Laptop Stands & Mounts", brand: "Rain Design", model: "mStand 10032", totalQty: 75, inStock: 19, deployed: 56, minStock: 10, location: "Austin Hub Depot (Shelf S-01)" }
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
    const accessoryModal = document.getElementById('accessoryModal');
    const issueModal = document.getElementById('issueModal');
    const openAddModalBtn = document.getElementById('openAddModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const accessoryForm = document.getElementById('accessoryForm');
    const closeIssueModalBtn = document.getElementById('closeIssueModalBtn');
    const cancelIssueBtn = document.getElementById('cancelIssueBtn');
    const issueForm = document.getElementById('issueForm');
    const exportAccBtn = document.getElementById('exportAccBtn');

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
                    const categoryFilterSelect = document.getElementById('categoryFilter');

                    if (accCategorySelect) {
                        const currentVal = (selectedVal !== undefined) ? selectedVal : accCategorySelect.value;
                        let optsHtml = '<option value="">Select Category</option>';
                        data.categories.forEach(cat => {
                            const name = cat.category_name;
                            const isSel = (name === currentVal) ? 'selected' : '';
                            optsHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        accCategorySelect.innerHTML = optsHtml;
                        if (currentVal) accCategorySelect.value = currentVal;
                    }

                    if (categoryFilterSelect) {
                        const curFilter = categoryFilterSelect.value;
                        let filterHtml = '<option value="all">All Categories</option>';
                        data.categories.forEach(cat => {
                            const name = cat.category_name;
                            const isSel = (name === curFilter) ? 'selected' : '';
                            filterHtml += `<option value="${escapeHtml(name)}" ${isSel}>${escapeHtml(name)}</option>`;
                        });
                        categoryFilterSelect.innerHTML = filterHtml;
                        if (curFilter) categoryFilterSelect.value = curFilter;
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
                renderTable();
                showNotification('Filters reset to default.', 'info');
            });
        }

        // Add Modal
        if (openAddModalBtn) {
            openAddModalBtn.addEventListener('click', function () {
                document.getElementById('modalTitle').textContent = 'Add New Accessory';
                document.getElementById('editAccId').value = '';
                accessoryForm.reset();
                fetchDynamicCategories('');
                openModal(accessoryModal);
            });
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', () => closeModal(accessoryModal));
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', () => closeModal(accessoryModal));

        if (accessoryForm) {
            accessoryForm.addEventListener('submit', handleSaveAccessory);
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
                closeModal(accessoryModal);
                closeModal(issueModal);
            }
        });

        [accessoryModal, issueModal].forEach(modal => {
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
                statusBadge = `<span class="status-pill status-instock"><span class="status-dot"></span>In Stock</span>`;
            } else if (status === 'Low Stock') {
                statusBadge = `<span class="status-pill status-lowstock"><span class="status-dot"></span>Low Stock (${item.inStock})</span>`;
            } else {
                statusBadge = `<span class="status-pill status-outstock"><span class="status-dot"></span>Out of Stock</span>`;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="sku-cell" style="white-space: nowrap;"><span class="sku-badge">${escapeHtml(item.sku)}</span></td>
                <td>
                    <div style="font-weight: 600; color: var(--navy-primary); font-size: 13.5px;">${escapeHtml(item.name)}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(item.location || 'Depot')}</div>
                </td>
                <td><span style="font-size: 12.5px; color: var(--text-secondary);">${escapeHtml(item.category)}</span></td>
                <td>
                    <div style="font-weight: 500; font-size: 13px; color: var(--text-primary);">${escapeHtml(item.brand)}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted);">${escapeHtml(item.model || '—')}</div>
                </td>
                <td style="text-align: center;">
                    <span class="qty-badge ${item.inStock === 0 ? 'qty-zero' : 'qty-in-stock'}">${item.inStock}</span>
                </td>
                <td style="text-align: center;">
                    <span class="qty-badge qty-deployed">${item.deployed}</span>
                </td>
                <td>${statusBadge}</td>
                <td style="text-align: center;">
                    <div class="table-actions" style="justify-content: center; gap: 6px;">
                        <button class="action-btn issue-btn" title="Issue to Staff" onclick="window.accMgr.openIssue(${item.id})" ${item.inStock === 0 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                        </button>
                        <button class="action-btn edit-cat-btn" title="Edit Accessory" onclick="window.accMgr.openEdit(${item.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button class="action-btn delete-cat-btn" title="Delete Accessory" onclick="window.accMgr.deleteItem(${item.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
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

    // Save Accessory (Add / Edit)
    function handleSaveAccessory(e) {
        e.preventDefault();
        const editId = document.getElementById('editAccId').value;
        const name = document.getElementById('accName').value.trim();
        const category = document.getElementById('accCategory').value;
        const brand = document.getElementById('accBrand').value.trim();
        const model = document.getElementById('accModel').value.trim();
        const qty = parseInt(document.getElementById('accQty').value, 10) || 1;
        const minStock = parseInt(document.getElementById('accMinStock').value, 10) || 5;
        const location = document.getElementById('accLocation').value.trim();

        if (editId) {
            // Update existing
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
                showNotification(`Updated accessory "${name}".`, 'success');
            }
        } else {
            // Create new
            const newId = accessories.length > 0 ? Math.max(...accessories.map(a => a.id)) + 1 : 1;
            const newSku = `ACC-${category.slice(0, 3).toUpperCase()}-${String(newId).padStart(3, '0')}`;
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
            showNotification(`Added new accessory "${name}".`, 'success');
        }

        closeModal(accessoryModal);
        updateStats();
        renderTable();
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

    // Edit Item
    function openEditModal(id) {
        const item = accessories.find(a => a.id === id);
        if (!item) return;

        document.getElementById('modalTitle').textContent = 'Edit Accessory';
        document.getElementById('editAccId').value = item.id;
        document.getElementById('accName').value = item.name;
        document.getElementById('accCategory').value = item.category;
        document.getElementById('accBrand').value = item.brand;
        document.getElementById('accModel').value = item.model;
        document.getElementById('accQty').value = item.totalQty;
        document.getElementById('accMinStock').value = item.minStock;
        document.getElementById('accLocation').value = item.location;

        fetchDynamicCategories(item.category);
        openModal(accessoryModal);
    }

    // Delete Item
    function deleteItem(id) {
        const item = accessories.find(a => a.id === id);
        if (!item) return;

        if (confirm(`Are you sure you want to remove "${item.name}" from accessories?`)) {
            accessories = accessories.filter(a => a.id !== id);
            updateStats();
            renderTable();
            showNotification(`Accessory "${item.name}" deleted.`, 'info');
        }
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
        deleteItem: deleteItem
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
