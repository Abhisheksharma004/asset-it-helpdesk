/**
 * Vendor / Supplier Management Script
 * Handles real-time search, filters, CRUD actions via API, and CSV Import/Export
 * Supports GSTIN Number tracking
 */

document.addEventListener("DOMContentLoaded", function () {
    let vendorsList = [];

    // DOM Elements
    const vendorsTbody = document.getElementById("vendorsTbody");
    const searchInput = document.getElementById("searchVendor");
    const filterStatus = document.getElementById("filterStatus");
    const resetFiltersBtn = document.getElementById("resetFiltersBtn");
    const refreshTableBtn = document.getElementById("refreshTableBtn");

    // Stat counters
    const statTotal = document.getElementById("statTotal");
    const statActive = document.getElementById("statActive");
    const statInactive = document.getElementById("statInactive");

    // Add Modal Elements
    const addModal = document.getElementById("addVendorModal");
    const openAddModalBtn = document.getElementById("openAddModalBtn");
    const closeAddModalBtn = document.getElementById("closeAddModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelAddModalBtn");
    const addVendorForm = document.getElementById("addVendorForm");
    const addNameInput = document.getElementById("addName");
    const addGstinInput = document.getElementById("addGstin");
    const addPersonInput = document.getElementById("addPerson");
    const addPhoneInput = document.getElementById("addPhone");
    const addEmailInput = document.getElementById("addEmail");
    const addAddressInput = document.getElementById("addAddress");
    const addStatusSelect = document.getElementById("addStatus");

    // Edit Modal Elements
    const editModal = document.getElementById("editVendorModal");
    const closeEditModalBtn = document.getElementById("closeEditModalBtn");
    const cancelEditModalBtn = document.getElementById("cancelEditModalBtn");
    const editVendorForm = document.getElementById("editVendorForm");
    const editIdInput = document.getElementById("editId");
    const editNameInput = document.getElementById("editName");
    const editGstinInput = document.getElementById("editGstin");
    const editPersonInput = document.getElementById("editPerson");
    const editPhoneInput = document.getElementById("editPhone");
    const editEmailInput = document.getElementById("editEmail");
    const editAddressInput = document.getElementById("editAddress");
    const editStatusSelect = document.getElementById("editStatus");

    // Delete Modal Elements
    const deleteModal = document.getElementById("deleteVendorModal");
    const closeDeleteModalBtn = document.getElementById("closeDeleteModalBtn");
    const cancelDeleteModalBtn = document.getElementById("cancelDeleteModalBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const deleteVendorNameSpan = document.getElementById("deleteVendorName");
    let vendorToDeleteId = null;

    // Import Modal Elements
    const importModal = document.getElementById("importVendorModal");
    const openImportModalBtn = document.getElementById("openImportModalBtn");
    const closeImportModalBtn = document.getElementById("closeImportModalBtn");
    const cancelImportModalBtn = document.getElementById("cancelImportModalBtn");
    const importVendorForm = document.getElementById("importVendorForm");
    const csvDropzone = document.getElementById("csvDropzone");
    const csvFileInput = document.getElementById("csvFileInput");
    const filePreviewCard = document.getElementById("filePreviewCard");
    const previewFileName = document.getElementById("previewFileName");
    const previewFileMeta = document.getElementById("previewFileMeta");
    const removeFileBtn = document.getElementById("removeFileBtn");
    const downloadSampleTemplateBtn = document.getElementById("downloadSampleTemplateBtn");
    const duplicateHandlingSelect = document.getElementById("duplicateHandling");
    const startImportBtn = document.getElementById("startImportBtn");
    let parsedImportRows = [];

    // Load Vendors on init
    loadVendors();

    // Search and Filter Events
    if (searchInput) {
        searchInput.addEventListener("input", debounce(filterAndRender, 200));
    }
    if (filterStatus) {
        filterStatus.addEventListener("change", filterAndRender);
    }
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener("click", function () {
            if (searchInput) searchInput.value = "";
            if (filterStatus) filterStatus.value = "All";
            filterAndRender();
        });
    }
    if (refreshTableBtn) {
        refreshTableBtn.addEventListener("click", function () {
            this.classList.add("spinning");
            loadVendors(() => {
                this.classList.remove("spinning");
                if (typeof showToast === "function") showToast("Vendors list refreshed", "info");
            });
        });
    }

    // Modal Control Helpers
    function openModal(modal) {
        if (modal) modal.classList.add("active");
    }
    function closeModal(modal) {
        if (modal) modal.classList.remove("active");
    }

    // Add Modal Listeners
    if (openAddModalBtn) {
        openAddModalBtn.addEventListener("click", function () {
            addVendorForm.reset();
            openModal(addModal);
            setTimeout(() => addNameInput && addNameInput.focus(), 100);
        });
    }
    if (closeAddModalBtn) closeAddModalBtn.addEventListener("click", () => closeModal(addModal));
    if (cancelAddModalBtn) cancelAddModalBtn.addEventListener("click", () => closeModal(addModal));

    // Edit Modal Listeners
    if (closeEditModalBtn) closeEditModalBtn.addEventListener("click", () => closeModal(editModal));
    if (cancelEditModalBtn) cancelEditModalBtn.addEventListener("click", () => closeModal(editModal));

    // Delete Modal Listeners
    if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener("click", () => closeModal(deleteModal));
    if (cancelDeleteModalBtn) cancelDeleteModalBtn.addEventListener("click", () => closeModal(deleteModal));

    // Import Modal Listeners
    if (openImportModalBtn) {
        openImportModalBtn.addEventListener("click", function () {
            resetImportForm();
            openModal(importModal);
        });
    }
    if (closeImportModalBtn) closeImportModalBtn.addEventListener("click", () => closeModal(importModal));
    if (cancelImportModalBtn) cancelImportModalBtn.addEventListener("click", () => closeModal(importModal));

    // Click outside modal or ESC key
    [addModal, editModal, deleteModal, importModal].forEach(m => {
        if (m) {
            m.addEventListener("click", function (e) {
                if (e.target === m) closeModal(m);
            });
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeModal(addModal);
            closeModal(editModal);
            closeModal(deleteModal);
            closeModal(importModal);
        }
    });

    // 1. Fetch Vendors from API
    function loadVendors(callback) {
        fetch("api/vendors.php")
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    vendorsList = res.data || [];
                    updateStats(res.stats);
                    filterAndRender();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to load vendors.", "error");
                }
                if (callback) callback();
            })
            .catch(err => {
                console.error("Vendors fetch error:", err);
                if (typeof showToast === "function") showToast("Unable to connect to database.", "error");
                if (callback) callback();
            });
    }

    // Update Top Metric Stat Badges
    function updateStats(stats) {
        if (!stats) {
            stats = {
                total: vendorsList.length,
                active: vendorsList.filter(c => c.status === 'Active').length,
                inactive: vendorsList.filter(c => c.status === 'Inactive').length
            };
        }
        if (statTotal) statTotal.textContent = stats.total ?? vendorsList.length;
        if (statActive) statActive.textContent = stats.active ?? 0;
        if (statInactive) statInactive.textContent = stats.inactive ?? 0;
    }

    // Filter & Render Table Rows
    function filterAndRender() {
        const query = (searchInput ? searchInput.value : "").toLowerCase().trim();
        const statusVal = filterStatus ? filterStatus.value : "All";

        const filtered = vendorsList.filter(item => {
            const matchesQuery = !query || 
                (item.vendor_name && item.vendor_name.toLowerCase().includes(query)) ||
                (item.gstin && item.gstin.toLowerCase().includes(query)) ||
                (item.contact_person && item.contact_person.toLowerCase().includes(query)) ||
                (item.phone && item.phone.toLowerCase().includes(query)) ||
                (item.email && item.email.toLowerCase().includes(query)) ||
                (item.address && item.address.toLowerCase().includes(query));

            const matchesStatus = (statusVal === "All") || (item.status === statusVal);

            return matchesQuery && matchesStatus;
        });

        renderTable(filtered);
    }

    // Render HTML Table
    function renderTable(data) {
        if (!vendorsTbody) return;

        if (data.length === 0) {
            vendorsTbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="table-empty-state">
                            <div class="empty-icon">
                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                            </div>
                            <div class="empty-title">No vendors found</div>
                            <div class="empty-desc">Try modifying your search or filter criteria, or add a new vendor.</div>
                            <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add New Vendor</button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = "";
        data.forEach(item => {
            const statusClass = item.status === "Active" ? "status-active" : "status-inactive";
            const createdDate = formatDate(item.created_at || item.created_date);

            const phoneHtml = item.phone ? `<div style="color: var(--text-primary); font-weight: 500; margin-bottom: 2px;">${escapeHtml(item.phone)}</div>` : '';
            const emailHtml = item.email ? `<div style="color: var(--cyan-primary); font-size: 12px;">${escapeHtml(item.email)}</div>` : '';
            const contactDetailHtml = (phoneHtml || emailHtml) ? `${phoneHtml}${emailHtml}` : '<span style="color:#94a3b8; font-style:italic;">No contact info</span>';

            const gstinTextHtml = item.gstin ? `<div style="color: var(--cyan-primary); font-size: 12px; margin-top: 2px;">GSTIN: ${escapeHtml(item.gstin)}</div>` : '';

            html += `
                <tr data-id="${item.id}">
                    <td>
                        <div class="category-main-text">${escapeHtml(item.vendor_name)}</div>
                        ${gstinTextHtml}
                    </td>
                    <td>
                        ${item.contact_person ? escapeHtml(item.contact_person) : '<span style="color:#94a3b8; font-style:italic;">—</span>'}
                    </td>
                    <td style="font-size: 13px;">
                        ${contactDetailHtml}
                    </td>
                    <td style="color: var(--text-secondary); font-size: 13px;">
                        ${item.address ? escapeHtml(item.address) : '<span style="color:#94a3b8; font-style:italic;">—</span>'}
                    </td>
                    <td>
                        <span class="status-pill ${statusClass}">
                            <span class="status-dot"></span>
                            ${escapeHtml(item.status)}
                        </span>
                    </td>
                    <td style="color: var(--text-muted); font-size: 13px; white-space: nowrap;">
                        ${createdDate}
                    </td>
                    <td>
                        <div class="table-actions" style="justify-content: center;">
                            <button class="action-btn toggle-btn toggle-ven-btn" title="Toggle Status" data-id="${item.id}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"></polyline>
                                    <polyline points="23 20 23 14 17 14"></polyline>
                                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                                </svg>
                            </button>
                            <button class="action-btn edit-ven-btn" title="Edit Vendor" data-id="${item.id}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button class="action-btn delete-btn delete-ven-btn" title="Delete Vendor" data-id="${item.id}" data-name="${escapeHtml(item.vendor_name)}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        vendorsTbody.innerHTML = html;

        // Attach action handlers
        attachActionListeners();
    }

    function attachActionListeners() {
        // Edit button click
        document.querySelectorAll(".edit-ven-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                openEditVendorModal(id);
            });
        });

        // Toggle Status click
        document.querySelectorAll(".toggle-ven-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                toggleVendorStatus(id);
            });
        });

        // Delete button click
        document.querySelectorAll(".delete-ven-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                const name = this.dataset.name;
                openDeleteVendorModal(id, name);
            });
        });
    }

    // Global Modal Functions for inline & dynamic clicks
    window.openEditVendorModal = function (id) {
        const item = vendorsList.find(c => parseInt(c.id) === parseInt(id));
        if (!item) return;

        editIdInput.value = item.id;
        editNameInput.value = item.vendor_name || "";
        if (editGstinInput) editGstinInput.value = item.gstin || "";
        editPersonInput.value = item.contact_person || "";
        editPhoneInput.value = item.phone || "";
        editEmailInput.value = item.email || "";
        editAddressInput.value = item.address || "";
        editStatusSelect.value = item.status || "Active";

        openModal(editModal);
        setTimeout(() => editNameInput && editNameInput.focus(), 100);
    };

    window.openDeleteVendorModal = function (id, name) {
        vendorToDeleteId = parseInt(id);
        if (deleteVendorNameSpan) deleteVendorNameSpan.textContent = name || "this vendor";
        openModal(deleteModal);
    };

    window.toggleVendorStatus = function (id) {
        fetch("api/vendors.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "toggle_status", id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === "function") showToast(res.message || "Status updated", "success");
                loadVendors();
            } else {
                if (typeof showToast === "function") showToast(res.message || "Failed to update status", "error");
            }
        })
        .catch(err => {
            console.error(err);
            if (typeof showToast === "function") showToast("Server error updating status.", "error");
        });
    };

    // 2. Submit New Vendor
    if (addVendorForm) {
        addVendorForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector("button[type='submit']");
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";

            const payload = {
                action: "create",
                vendor_name: addNameInput.value.trim(),
                gstin: addGstinInput ? addGstinInput.value.trim().toUpperCase() : "",
                contact_person: addPersonInput.value.trim(),
                phone: addPhoneInput.value.trim(),
                email: addEmailInput.value.trim(),
                address: addAddressInput.value.trim(),
                status: addStatusSelect.value
            };

            fetch("api/vendors.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;

                if (res.success) {
                    closeModal(addModal);
                    addVendorForm.reset();
                    if (typeof showToast === "function") showToast("Vendor created successfully!", "success");
                    loadVendors();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to create vendor.", "error");
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                console.error(err);
                if (typeof showToast === "function") showToast("Error connecting to server.", "error");
            });
        });
    }

    // 3. Submit Edit Vendor
    if (editVendorForm) {
        editVendorForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector("button[type='submit']");
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Updating...";

            const payload = {
                action: "edit",
                id: editIdInput.value,
                vendor_name: editNameInput.value.trim(),
                gstin: editGstinInput ? editGstinInput.value.trim().toUpperCase() : "",
                contact_person: editPersonInput.value.trim(),
                phone: editPhoneInput.value.trim(),
                email: editEmailInput.value.trim(),
                address: editAddressInput.value.trim(),
                status: editStatusSelect.value
            };

            fetch("api/vendors.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;

                if (res.success) {
                    closeModal(editModal);
                    if (typeof showToast === "function") showToast("Vendor updated successfully!", "success");
                    loadVendors();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to update vendor.", "error");
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                console.error(err);
                if (typeof showToast === "function") showToast("Error connecting to server.", "error");
            });
        });
    }

    // 4. Confirm Delete Action
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function () {
            if (!vendorToDeleteId) return;

            this.disabled = true;
            this.textContent = "Deleting...";

            fetch("api/vendors.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "delete", id: vendorToDeleteId })
            })
            .then(res => res.json())
            .then(res => {
                this.disabled = false;
                this.textContent = "Yes, Delete Vendor";
                closeModal(deleteModal);

                if (res.success) {
                    if (typeof showToast === "function") showToast("Vendor deleted successfully!", "success");
                    vendorToDeleteId = null;
                    loadVendors();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to delete vendor.", "error");
                }
            })
            .catch(err => {
                this.disabled = false;
                this.textContent = "Yes, Delete Vendor";
                closeModal(deleteModal);
                console.error(err);
                if (typeof showToast === "function") showToast("Server connection error.", "error");
            });
        });
    }

    // 5. Export to CSV Feature
    const exportCsvBtn = document.getElementById("exportCsvBtn");
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener("click", function () {
            if (vendorsList.length === 0) {
                if (typeof showToast === "function") showToast("No vendors to export", "info");
                return;
            }

            let csv = "ID,Vendor Name,GSTIN,Contact Person,Phone,Email,Address,Status,Created At\n";
            vendorsList.forEach(c => {
                const name = (c.vendor_name || "").replace(/"/g, '""');
                const gstin = (c.gstin || "").replace(/"/g, '""');
                const person = (c.contact_person || "").replace(/"/g, '""');
                const phone = (c.phone || "").replace(/"/g, '""');
                const email = (c.email || "").replace(/"/g, '""');
                const addr = (c.address || "").replace(/"/g, '""');
                csv += `"${c.id}","${name}","${gstin}","${person}","${phone}","${email}","${addr}","${c.status}","${formatDate(c.created_at || c.created_date)}"\n`;
            });

            const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", `vendors_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            if (typeof showToast === "function") showToast("Vendors exported to CSV!", "success");
        });
    }

    // ==================== IMPORT FUNCTIONALITY ====================
    // Download Sample Template
    if (downloadSampleTemplateBtn) {
        downloadSampleTemplateBtn.addEventListener("click", function () {
            const sampleCsv = "Vendor Name,GSTIN,Contact Person,Phone,Email,Address,Status\n" +
                "\"Dell Technologies India\",\"29AABCD1234F1Z5\",\"Rajesh Gupta\",\"+91 98201 12345\",\"enterprise.sales@dell.com\",\"Ambience Island, DLF Phase 3, Gurugram\",\"Active\"\n" +
                "\"Lenovo Global Technology\",\"29AABCL5678G1Z2\",\"Amit Sharma\",\"+91 98450 67890\",\"commercial@lenovo.com\",\"Marathahalli Outer Ring Rd, Bangalore\",\"Active\"\n" +
                "\"HP India Sales Pvt Ltd\",\"06AAACH1234C1ZB\",\"Sunil Verma\",\"+91 98110 54321\",\"support.india@hp.com\",\"Cyber City, Tower D, Gurugram\",\"Active\"\n" +
                "\"Cisco Systems India\",\"29AABCC1122D1Z8\",\"Priya Menon\",\"+91 97400 98765\",\"partners@cisco.com\",\"Cessna Business Park, Bangalore\",\"Active\"\n";

            const blob = new Blob([sampleCsv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "sample_vendors_template.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            if (typeof showToast === "function") showToast("Sample CSV template downloaded!", "info");
        });
    }

    // Dropzone Click & Drag/Drop
    if (csvDropzone && csvFileInput) {
        csvDropzone.addEventListener("click", () => csvFileInput.click());

        csvDropzone.addEventListener("dragover", function (e) {
            e.preventDefault();
            this.classList.add("dragover");
        });

        csvDropzone.addEventListener("dragleave", function () {
            this.classList.remove("dragover");
        });

        csvDropzone.addEventListener("drop", function (e) {
            e.preventDefault();
            this.classList.remove("dragover");
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                handleFileSelection(files[0]);
            }
        });

        csvFileInput.addEventListener("change", function () {
            if (this.files && this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }

    // Remove selected file
    if (removeFileBtn) {
        removeFileBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            resetImportForm();
        });
    }

    function resetImportForm() {
        if (csvFileInput) csvFileInput.value = "";
        parsedImportRows = [];
        if (filePreviewCard) filePreviewCard.style.display = "none";
        if (csvDropzone) csvDropzone.style.display = "block";
        if (startImportBtn) {
            startImportBtn.disabled = true;
            startImportBtn.textContent = "Import Vendors";
        }
    }

    function handleFileSelection(file) {
        if (!file.name.toLowerCase().endsWith(".csv")) {
            if (typeof showToast === "function") showToast("Please select a valid CSV file.", "error");
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const content = e.target.result;
            const parsed = parseCsvText(content);

            if (parsed.length === 0) {
                if (typeof showToast === "function") showToast("No valid vendor rows found in this file.", "warning");
                resetImportForm();
                return;
            }

            parsedImportRows = parsed;

            if (previewFileName) previewFileName.textContent = file.name;
            const sizeKb = (file.size / 1024).toFixed(1);
            if (previewFileMeta) previewFileMeta.textContent = `${sizeKb} KB • ${parsed.length} vendors ready to import`;

            if (csvDropzone) csvDropzone.style.display = "none";
            if (filePreviewCard) filePreviewCard.style.display = "flex";
            if (startImportBtn) {
                startImportBtn.disabled = false;
                startImportBtn.textContent = `Import ${parsed.length} Vendors`;
            }
        };
        reader.onerror = function () {
            if (typeof showToast === "function") showToast("Failed to read file.", "error");
        };
        reader.readAsText(file);
    }

    // Client-side CSV Parser for Vendors
    function parseCsvText(text) {
        const lines = text.split(/\r?\n/).filter(line => line.trim().length > 0);
        if (lines.length < 2) return [];

        const headerCols = parseCsvLine(lines[0]);
        let nameIdx = -1;
        let gstinIdx = -1;
        let personIdx = -1;
        let phoneIdx = -1;
        let emailIdx = -1;
        let addressIdx = -1;
        let statusIdx = -1;

        headerCols.forEach((col, idx) => {
            const c = col.toLowerCase().replace(/[^a-z0-9]/g, "");
            if (c.includes("gstin") || c.includes("gst") || c.includes("taxid")) {
                gstinIdx = idx;
            } else if (c.includes("vendor") || c.includes("supplier") || c.includes("company") || c.includes("name")) {
                if (nameIdx === -1) nameIdx = idx;
            } else if (c.includes("person") || c.includes("contact") || c.includes("rep")) {
                personIdx = idx;
            } else if (c.includes("phone") || c.includes("mobile") || c.includes("tel")) {
                phoneIdx = idx;
            } else if (c.includes("email") || c.includes("mail")) {
                emailIdx = idx;
            } else if (c.includes("address") || c.includes("location") || c.includes("city") || c.includes("note")) {
                addressIdx = idx;
            } else if (c.includes("status")) {
                statusIdx = idx;
            }
        });

        if (nameIdx === -1) nameIdx = 0;

        const results = [];
        for (let i = 1; i < lines.length; i++) {
            const cols = parseCsvLine(lines[i]);
            if (cols.length === 0) continue;

            const name = (cols[nameIdx] || "").trim();
            if (!name) continue;

            const gstin = (gstinIdx !== -1 && cols[gstinIdx]) ? cols[gstinIdx].trim().toUpperCase() : "";
            const person = (personIdx !== -1 && cols[personIdx]) ? cols[personIdx].trim() : "";
            const phone = (phoneIdx !== -1 && cols[phoneIdx]) ? cols[phoneIdx].trim() : "";
            const email = (emailIdx !== -1 && cols[emailIdx]) ? cols[emailIdx].trim() : "";
            const addr = (addressIdx !== -1 && cols[addressIdx]) ? cols[addressIdx].trim() : "";
            let status = (statusIdx !== -1 && cols[statusIdx]) ? cols[statusIdx].trim() : "Active";
            if (!["active", "inactive"].includes(status.toLowerCase())) status = "Active";

            results.push({
                vendor_name: name,
                gstin: gstin,
                contact_person: person,
                phone: phone,
                email: email,
                address: addr,
                status: status.charAt(0).toUpperCase() + status.slice(1).toLowerCase()
            });
        }

        return results;
    }

    function parseCsvLine(text) {
        const result = [];
        let cur = "";
        let inQuotes = false;

        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            const nextChar = text[i + 1];

            if (char === '"') {
                if (inQuotes && nextChar === '"') {
                    cur += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                result.push(cur.trim());
                cur = "";
            } else {
                cur += char;
            }
        }
        result.push(cur.trim());
        return result;
    }

    // Submit Import Form
    if (importVendorForm) {
        importVendorForm.addEventListener("submit", function (e) {
            e.preventDefault();

            if (parsedImportRows.length === 0) {
                if (typeof showToast === "function") showToast("Please select a valid CSV file first.", "warning");
                return;
            }

            if (startImportBtn) {
                startImportBtn.disabled = true;
                startImportBtn.textContent = "Importing...";
            }

            fetch("api/vendors.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "import",
                    rows: parsedImportRows
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(importModal);
                    resetImportForm();
                    if (typeof showToast === "function") {
                        showToast(res.message || "Vendors imported successfully!", "success");
                    }
                    loadVendors();
                } else {
                    if (startImportBtn) {
                        startImportBtn.disabled = false;
                        startImportBtn.textContent = `Import ${parsedImportRows.length} Vendors`;
                    }
                    if (typeof showToast === "function") {
                        showToast(res.message || "Import failed.", "error");
                    }
                }
            })
            .catch(err => {
                console.error("Import error:", err);
                if (startImportBtn) {
                    startImportBtn.disabled = false;
                    startImportBtn.textContent = `Import ${parsedImportRows.length} Vendors`;
                }
                if (typeof showToast === "function") showToast("Server error during import.", "error");
            });
        });
    }

    // Date formatting helper: strictly DD-MM-YYYY
    function formatDate(dateStr) {
        if (!dateStr) return "—";
        // If already in DD-MM-YYYY (e.g. 01-10-2026)
        if (/^\d{2}-\d{2}-\d{4}/.test(dateStr)) {
            return dateStr.substring(0, 10);
        }
        // If in YYYY-MM-DD (e.g. 2026-10-01)
        if (/^\d{4}-\d{2}-\d{2}/.test(dateStr)) {
            const parts = dateStr.substring(0, 10).split("-");
            return `${parts[2]}-${parts[1]}-${parts[0]}`;
        }
        const d = new Date(dateStr);
        if (!isNaN(d.getTime())) {
            const day = String(d.getDate()).padStart(2, "0");
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const year = d.getFullYear();
            return `${day}-${month}-${year}`;
        }
        return dateStr;
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function escapeHtml(text) {
        if (!text) return "";
        return text
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
