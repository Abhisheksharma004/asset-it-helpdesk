/**
 * Locations / Branch Management Script
 * Handles real-time search, filters, CRUD actions via API, and CSV Import/Export
 */

document.addEventListener("DOMContentLoaded", function () {
    let locationsList = [];

    // DOM Elements
    const locationsTbody = document.getElementById("locationsTbody");
    const searchInput = document.getElementById("searchLocation");
    const filterStatus = document.getElementById("filterStatus");
    const resetFiltersBtn = document.getElementById("resetFiltersBtn");
    const refreshBtn = document.getElementById("refreshBtn");

    // Stat counters
    const statTotal = document.getElementById("statTotal");
    const statActive = document.getElementById("statActive");
    const statInactive = document.getElementById("statInactive");

    // Add Modal Elements
    const addModal = document.getElementById("addLocationModal");
    const openAddModalBtn = document.getElementById("openAddModalBtn");
    const closeAddModalBtn = document.getElementById("closeAddModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelAddModalBtn");
    const addLocationForm = document.getElementById("addLocationForm");
    const addNameInput = document.getElementById("addName");
    const addStatusSelect = document.getElementById("addStatus");
    const addDescInput = document.getElementById("addDesc");

    // Edit Modal Elements
    const editModal = document.getElementById("editLocationModal");
    const closeEditModalBtn = document.getElementById("closeEditModalBtn");
    const cancelEditModalBtn = document.getElementById("cancelEditModalBtn");
    const editLocationForm = document.getElementById("editLocationForm");
    const editIdInput = document.getElementById("editId");
    const editNameInput = document.getElementById("editName");
    const editStatusSelect = document.getElementById("editStatus");
    const editDescInput = document.getElementById("editDesc");

    // Delete Modal Elements
    const deleteModal = document.getElementById("deleteLocationModal");
    const closeDeleteModalBtn = document.getElementById("closeDeleteModalBtn");
    const cancelDeleteModalBtn = document.getElementById("cancelDeleteModalBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const deleteLocationNameSpan = document.getElementById("deleteLocationName");
    let locationToDeleteId = null;

    // Import Modal Elements
    const importModal = document.getElementById("importLocationModal");
    const openImportModalBtn = document.getElementById("openImportModalBtn");
    const closeImportModalBtn = document.getElementById("closeImportModalBtn");
    const cancelImportModalBtn = document.getElementById("cancelImportModalBtn");
    const importLocationForm = document.getElementById("importLocationForm");
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

    // Load Locations on init
    loadLocations();

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
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
            this.classList.add("spinning");
            loadLocations(() => {
                this.classList.remove("spinning");
                if (typeof showToast === "function") showToast("Locations refreshed", "info");
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
            addLocationForm.reset();
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

    // 1. Fetch Locations
    function loadLocations(callback) {
        fetch("api/locations.php")
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    locationsList = res.data || [];
                    updateStats(res.stats);
                    filterAndRender();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to load locations.", "error");
                }
                if (callback) callback();
            })
            .catch(err => {
                console.error("Locations fetch error:", err);
                if (typeof showToast === "function") showToast("Unable to connect to database.", "error");
                if (callback) callback();
            });
    }

    // Update Top Metric Stat Badges
    function updateStats(stats) {
        if (!stats) {
            stats = {
                total: locationsList.length,
                active: locationsList.filter(c => c.status === 'Active').length,
                inactive: locationsList.filter(c => c.status === 'Inactive').length
            };
        }
        if (statTotal) statTotal.textContent = stats.total ?? locationsList.length;
        if (statActive) statActive.textContent = stats.active ?? 0;
        if (statInactive) statInactive.textContent = stats.inactive ?? 0;
    }

    // Filter & Render Table Rows
    function filterAndRender() {
        const query = (searchInput ? searchInput.value : "").toLowerCase().trim();
        const statusVal = filterStatus ? filterStatus.value : "All";

        const filtered = locationsList.filter(item => {
            const matchesQuery = !query || 
                (item.location_name && item.location_name.toLowerCase().includes(query)) ||
                (item.description && item.description.toLowerCase().includes(query));

            const matchesStatus = (statusVal === "All") || (item.status === statusVal);

            return matchesQuery && matchesStatus;
        });

        renderTable(filtered);
    }

    // Render HTML Table
    function renderTable(data) {
        if (!locationsTbody) return;

        if (data.length === 0) {
            locationsTbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="table-empty-state">
                            <div class="empty-icon">
                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <div class="empty-title">No locations found</div>
                            <div class="empty-desc">Try modifying your search or filter criteria, or add a new branch location.</div>
                            <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add New Location</button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = "";
        data.forEach(item => {
            const statusClass = item.status === "Active" ? "status-active" : "status-inactive";
            const createdDate = formatDate(item.created_at);

            html += `
                <tr data-id="${item.id}">
                    <td>
                        <span class="category-main-text">${escapeHtml(item.location_name)}</span>
                    </td>
                    <td style="color: var(--text-secondary); font-size: 13px;">
                        ${item.description ? escapeHtml(item.description) : '<span style="color:#94a3b8; font-style:italic;">No address/details provided</span>'}
                    </td>
                    <td>
                        <span class="status-pill ${statusClass}">
                            <span class="status-dot"></span>
                            ${escapeHtml(item.status)}
                        </span>
                    </td>
                    <td style="color: var(--text-muted); font-size: 12px; white-space: nowrap;">
                        ${createdDate}
                    </td>
                    <td>
                        <div class="table-actions">
                            <button class="action-btn edit-loc-btn" title="Edit Location" data-id="${item.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button class="action-btn toggle-btn toggle-loc-btn" title="${item.status === 'Active' ? 'Deactivate' : 'Activate'}" data-id="${item.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                    <line x1="12" y1="2" x2="12" y2="12"></line>
                                </svg>
                            </button>
                            <button class="action-btn delete-btn delete-loc-btn" title="Delete Location" data-id="${item.id}" data-name="${escapeHtml(item.location_name)}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        locationsTbody.innerHTML = html;

        // Attach action handlers
        attachActionListeners();
    }

    function attachActionListeners() {
        // Edit button click
        document.querySelectorAll(".edit-loc-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                const item = locationsList.find(c => parseInt(c.id) === id);
                if (!item) return;

                editIdInput.value = item.id;
                editNameInput.value = item.location_name;
                editDescInput.value = item.description || "";
                editStatusSelect.value = item.status || "Active";

                openModal(editModal);
                setTimeout(() => editNameInput.focus(), 100);
            });
        });

        // Toggle Status click
        document.querySelectorAll(".toggle-loc-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                toggleLocationStatus(id);
            });
        });

        // Delete button click
        document.querySelectorAll(".delete-loc-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                locationToDeleteId = parseInt(this.dataset.id);
                const name = this.dataset.name;
                if (deleteLocationNameSpan) deleteLocationNameSpan.textContent = name;
                openModal(deleteModal);
            });
        });
    }

    // 2. Submit New Location
    if (addLocationForm) {
        addLocationForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector("button[type='submit']");
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";

            const payload = {
                action: "create",
                location_name: addNameInput.value.trim(),
                description: addDescInput.value.trim(),
                status: addStatusSelect.value
            };

            fetch("api/locations.php", {
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
                    addLocationForm.reset();
                    if (typeof showToast === "function") showToast("Location created successfully!", "success");
                    loadLocations();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to create location.", "error");
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

    // 3. Submit Edit Location
    if (editLocationForm) {
        editLocationForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector("button[type='submit']");
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Updating...";

            const payload = {
                action: "edit",
                id: editIdInput.value,
                location_name: editNameInput.value.trim(),
                description: editDescInput.value.trim(),
                status: editStatusSelect.value
            };

            fetch("api/locations.php", {
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
                    if (typeof showToast === "function") showToast("Location updated successfully!", "success");
                    loadLocations();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to update location.", "error");
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

    // 4. Toggle Status Action
    function toggleLocationStatus(id) {
        fetch("api/locations.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "toggle_status", id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === "function") showToast(res.message || "Status updated", "success");
                loadLocations();
            } else {
                if (typeof showToast === "function") showToast(res.message || "Failed to update status", "error");
            }
        })
        .catch(err => {
            console.error(err);
            if (typeof showToast === "function") showToast("Server error", "error");
        });
    }

    // 5. Confirm Delete Action
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function () {
            if (!locationToDeleteId) return;

            this.disabled = true;
            this.textContent = "Deleting...";

            fetch("api/locations.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "delete", id: locationToDeleteId })
            })
            .then(res => res.json())
            .then(res => {
                this.disabled = false;
                this.textContent = "Yes, Delete Location";
                closeModal(deleteModal);

                if (res.success) {
                    if (typeof showToast === "function") showToast("Location deleted successfully!", "success");
                    locationToDeleteId = null;
                    loadLocations();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to delete location.", "error");
                }
            })
            .catch(err => {
                this.disabled = false;
                this.textContent = "Yes, Delete Location";
                closeModal(deleteModal);
                console.error(err);
                if (typeof showToast === "function") showToast("Server connection error.", "error");
            });
        });
    }

    // Export to CSV Feature
    const exportCsvBtn = document.getElementById("exportCsvBtn");
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener("click", function () {
            if (locationsList.length === 0) {
                if (typeof showToast === "function") showToast("No locations to export", "info");
                return;
            }

            let csv = "ID,Location Name,Description / Address,Status,Created At\n";
            locationsList.forEach(c => {
                const name = (c.location_name || "").replace(/"/g, '""');
                const desc = (c.description || "").replace(/"/g, '""');
                csv += `"${c.id}","${name}","${desc}","${c.status}","${formatDate(c.created_at)}"\n`;
            });

            const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", `locations_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            if (typeof showToast === "function") showToast("Locations exported to CSV!", "success");
        });
    }

    // ==================== IMPORT FUNCTIONALITY ====================
    // Download Sample Template
    if (downloadSampleTemplateBtn) {
        downloadSampleTemplateBtn.addEventListener("click", function () {
            const sampleCsv = "Location Name,Description,Status\n" +
                "\"Corporate HQ - Mumbai\",\"BKC Corporate Park, Tower 2, 8th & 9th Floor\",\"Active\"\n" +
                "\"Tech Hub - Bangalore\",\"Electronic City Phase 1, Silicon Valley Campus\",\"Active\"\n" +
                "\"Branch Office - Delhi NCR\",\"Cyber City DLF Phase 2, Building 10, Gurgaon\",\"Active\"\n" +
                "\"Delivery Center - Hyderabad\",\"HITEC City, Mindspace IT Park, 4th Floor\",\"Active\"\n" +
                "\"Development Center - Pune\",\"Hinjewadi Phase 2, Rajiv Gandhi Infotech Park\",\"Active\"\n";

            const blob = new Blob([sampleCsv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "sample_locations_template.csv");
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
            startImportBtn.textContent = "Import Locations";
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
                if (typeof showToast === "function") showToast("No valid location rows found in this file.", "warning");
                resetImportForm();
                return;
            }

            parsedImportRows = parsed;

            if (previewFileName) previewFileName.textContent = file.name;
            const sizeKb = (file.size / 1024).toFixed(1);
            if (previewFileMeta) previewFileMeta.textContent = `${sizeKb} KB • ${parsed.length} locations ready to import`;

            if (csvDropzone) csvDropzone.style.display = "none";
            if (filePreviewCard) filePreviewCard.style.display = "flex";
            if (startImportBtn) {
                startImportBtn.disabled = false;
                startImportBtn.textContent = `Import ${parsed.length} Locations`;
            }
        };
        reader.onerror = function () {
            if (typeof showToast === "function") showToast("Failed to read file.", "error");
        };
        reader.readAsText(file);
    }

    // Client-side CSV Parser
    function parseCsvText(text) {
        const lines = text.split(/\r?\n/).filter(line => line.trim().length > 0);
        if (lines.length < 2) return [];

        const headerCols = parseCsvLine(lines[0]);
        let nameIdx = -1;
        let descIdx = -1;
        let statusIdx = -1;

        headerCols.forEach((col, idx) => {
            const c = col.toLowerCase().replace(/[^a-z]/g, "");
            if (c.includes("name") || c.includes("location") || c.includes("branch")) nameIdx = idx;
            else if (c.includes("desc") || c.includes("address") || c.includes("detail")) descIdx = idx;
            else if (c.includes("stat")) statusIdx = idx;
        });

        if (nameIdx === -1) nameIdx = 0;
        if (descIdx === -1 && headerCols.length > 1) descIdx = 1;
        if (statusIdx === -1 && headerCols.length > 2) statusIdx = 2;

        const results = [];
        for (let i = 1; i < lines.length; i++) {
            const cols = parseCsvLine(lines[i]);
            if (cols.length === 0) continue;

            const name = (cols[nameIdx] || "").trim();
            if (!name) continue;

            const desc = (descIdx !== -1 && cols[descIdx]) ? cols[descIdx].trim() : "";
            let status = (statusIdx !== -1 && cols[statusIdx]) ? cols[statusIdx].trim() : "Active";
            if (!["active", "inactive"].includes(status.toLowerCase())) status = "Active";

            results.push({
                location_name: name,
                description: desc,
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
    if (importLocationForm) {
        importLocationForm.addEventListener("submit", function (e) {
            e.preventDefault();

            if (parsedImportRows.length === 0) {
                if (typeof showToast === "function") showToast("Please select a valid CSV file first.", "warning");
                return;
            }

            if (startImportBtn) {
                startImportBtn.disabled = true;
                startImportBtn.textContent = "Importing...";
            }

            const duplicateHandling = duplicateHandlingSelect ? duplicateHandlingSelect.value : "skip";

            fetch("api/locations.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "import",
                    duplicate_handling: duplicateHandling,
                    locations: parsedImportRows
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(importModal);
                    resetImportForm();
                    if (typeof showToast === "function") {
                        showToast(res.message || "Locations imported successfully!", "success");
                    }
                    loadLocations();
                } else {
                    if (startImportBtn) {
                        startImportBtn.disabled = false;
                        startImportBtn.textContent = `Import ${parsedImportRows.length} Locations`;
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
                    startImportBtn.textContent = `Import ${parsedImportRows.length} Locations`;
                }
                if (typeof showToast === "function") showToast("Server error during import.", "error");
            });
        });
    }

    // Helpers
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
