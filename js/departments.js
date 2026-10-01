/**
 * Department Management Script
 * Handles real-time search, filters, CRUD actions via API, and CSV Import/Export
 */

document.addEventListener("DOMContentLoaded", function () {
    let departmentsList = [];

    // DOM Elements
    const departmentsTbody = document.getElementById("departmentsTbody");
    const searchInput = document.getElementById("searchDepartment");
    const filterStatus = document.getElementById("filterStatus");
    const resetFiltersBtn = document.getElementById("resetFiltersBtn");
    const refreshBtn = document.getElementById("refreshBtn");

    // Stat counters
    const statTotal = document.getElementById("statTotal");
    const statActive = document.getElementById("statActive");
    const statInactive = document.getElementById("statInactive");

    // Add Modal Elements
    const addModal = document.getElementById("addDepartmentModal");
    const openAddModalBtn = document.getElementById("openAddModalBtn");
    const closeAddModalBtn = document.getElementById("closeAddModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelAddModalBtn");
    const addDepartmentForm = document.getElementById("addDepartmentForm");
    const addNameInput = document.getElementById("addName");
    const addStatusSelect = document.getElementById("addStatus");
    const addDescInput = document.getElementById("addDesc");

    // Edit Modal Elements
    const editModal = document.getElementById("editDepartmentModal");
    const closeEditModalBtn = document.getElementById("closeEditModalBtn");
    const cancelEditModalBtn = document.getElementById("cancelEditModalBtn");
    const editDepartmentForm = document.getElementById("editDepartmentForm");
    const editIdInput = document.getElementById("editId");
    const editNameInput = document.getElementById("editName");
    const editStatusSelect = document.getElementById("editStatus");
    const editDescInput = document.getElementById("editDesc");

    // Delete Modal Elements
    const deleteModal = document.getElementById("deleteDepartmentModal");
    const closeDeleteModalBtn = document.getElementById("closeDeleteModalBtn");
    const cancelDeleteModalBtn = document.getElementById("cancelDeleteModalBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const deleteDepartmentNameSpan = document.getElementById("deleteDepartmentName");
    let departmentToDeleteId = null;

    // Import Modal Elements
    const importModal = document.getElementById("importDepartmentModal");
    const openImportModalBtn = document.getElementById("openImportModalBtn");
    const closeImportModalBtn = document.getElementById("closeImportModalBtn");
    const cancelImportModalBtn = document.getElementById("cancelImportModalBtn");
    const importDepartmentForm = document.getElementById("importDepartmentForm");
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

    // Load Departments on init
    loadDepartments();

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
            loadDepartments(() => {
                this.classList.remove("spinning");
                if (typeof showToast === "function") showToast("Departments refreshed", "info");
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
            addDepartmentForm.reset();
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

    // 1. Fetch Departments
    function loadDepartments(callback) {
        fetch("api/departments.php")
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    departmentsList = res.data || [];
                    updateStats(res.stats);
                    filterAndRender();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to load departments.", "error");
                }
                if (callback) callback();
            })
            .catch(err => {
                console.error("Departments fetch error:", err);
                if (typeof showToast === "function") showToast("Unable to connect to database.", "error");
                if (callback) callback();
            });
    }

    // Update Top Metric Stat Badges
    function updateStats(stats) {
        if (!stats) {
            stats = {
                total: departmentsList.length,
                active: departmentsList.filter(c => c.status === 'Active').length,
                inactive: departmentsList.filter(c => c.status === 'Inactive').length
            };
        }
        if (statTotal) statTotal.textContent = stats.total ?? departmentsList.length;
        if (statActive) statActive.textContent = stats.active ?? 0;
        if (statInactive) statInactive.textContent = stats.inactive ?? 0;
    }

    // Filter & Render Table Rows
    function filterAndRender() {
        const query = (searchInput ? searchInput.value : "").toLowerCase().trim();
        const statusVal = filterStatus ? filterStatus.value : "All";

        const filtered = departmentsList.filter(item => {
            const matchesQuery = !query || 
                (item.department_name && item.department_name.toLowerCase().includes(query)) ||
                (item.description && item.description.toLowerCase().includes(query));

            const matchesStatus = (statusVal === "All") || (item.status === statusVal);

            return matchesQuery && matchesStatus;
        });

        renderTable(filtered);
    }

    // Render HTML Table
    function renderTable(data) {
        if (!departmentsTbody) return;

        if (data.length === 0) {
            departmentsTbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="table-empty-state">
                            <div class="empty-icon">
                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <div class="empty-title">No departments found</div>
                            <div class="empty-desc">Try modifying your search or filter criteria, or add a new department.</div>
                            <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add New Department</button>
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
                        <span class="category-main-text">${escapeHtml(item.department_name)}</span>
                    </td>
                    <td style="color: var(--text-secondary); font-size: 13px;">
                        ${item.description ? escapeHtml(item.description) : '<span style="color:#94a3b8; font-style:italic;">No description provided</span>'}
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
                            <button class="action-btn edit-dept-btn" title="Edit Department" data-id="${item.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button class="action-btn toggle-btn toggle-dept-btn" title="${item.status === 'Active' ? 'Deactivate' : 'Activate'}" data-id="${item.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
                                    <line x1="12" y1="2" x2="12" y2="12"></line>
                                </svg>
                            </button>
                            <button class="action-btn delete-btn delete-dept-btn" title="Delete Department" data-id="${item.id}" data-name="${escapeHtml(item.department_name)}">
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

        departmentsTbody.innerHTML = html;

        // Attach action handlers
        attachActionListeners();
    }

    function attachActionListeners() {
        // Edit button click
        document.querySelectorAll(".edit-dept-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                const item = departmentsList.find(c => parseInt(c.id) === id);
                if (!item) return;

                editIdInput.value = item.id;
                editNameInput.value = item.department_name;
                editDescInput.value = item.description || "";
                editStatusSelect.value = item.status || "Active";

                openModal(editModal);
                setTimeout(() => editNameInput.focus(), 100);
            });
        });

        // Toggle Status click
        document.querySelectorAll(".toggle-dept-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = parseInt(this.dataset.id);
                toggleDepartmentStatus(id);
            });
        });

        // Delete button click
        document.querySelectorAll(".delete-dept-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                departmentToDeleteId = parseInt(this.dataset.id);
                const name = this.dataset.name;
                if (deleteDepartmentNameSpan) deleteDepartmentNameSpan.textContent = name;
                openModal(deleteModal);
            });
        });
    }

    // 2. Submit New Department
    if (addDepartmentForm) {
        addDepartmentForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector("button[type='submit']");
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";

            const payload = {
                action: "create",
                department_name: addNameInput.value.trim(),
                description: addDescInput.value.trim(),
                status: addStatusSelect.value
            };

            fetch("api/departments.php", {
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
                    addDepartmentForm.reset();
                    if (typeof showToast === "function") showToast("Department created successfully!", "success");
                    loadDepartments();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to create department.", "error");
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

    // 3. Submit Edit Department
    if (editDepartmentForm) {
        editDepartmentForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector("button[type='submit']");
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Updating...";

            const payload = {
                action: "edit",
                id: editIdInput.value,
                department_name: editNameInput.value.trim(),
                description: editDescInput.value.trim(),
                status: editStatusSelect.value
            };

            fetch("api/departments.php", {
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
                    if (typeof showToast === "function") showToast("Department updated successfully!", "success");
                    loadDepartments();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to update department.", "error");
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
    function toggleDepartmentStatus(id) {
        fetch("api/departments.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "toggle_status", id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === "function") showToast(res.message || "Status updated", "success");
                loadDepartments();
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
            if (!departmentToDeleteId) return;

            this.disabled = true;
            this.textContent = "Deleting...";

            fetch("api/departments.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "delete", id: departmentToDeleteId })
            })
            .then(res => res.json())
            .then(res => {
                this.disabled = false;
                this.textContent = "Yes, Delete Department";
                closeModal(deleteModal);

                if (res.success) {
                    if (typeof showToast === "function") showToast("Department deleted successfully!", "success");
                    departmentToDeleteId = null;
                    loadDepartments();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to delete department.", "error");
                }
            })
            .catch(err => {
                this.disabled = false;
                this.textContent = "Yes, Delete Department";
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
            if (departmentsList.length === 0) {
                if (typeof showToast === "function") showToast("No departments to export", "info");
                return;
            }

            let csv = "ID,Department Name,Description,Status,Created At\n";
            departmentsList.forEach(c => {
                const name = (c.department_name || "").replace(/"/g, '""');
                const desc = (c.description || "").replace(/"/g, '""');
                csv += `"${c.id}","${name}","${desc}","${c.status}","${formatDate(c.created_at)}"\n`;
            });

            const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", `departments_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            if (typeof showToast === "function") showToast("Departments exported to CSV!", "success");
        });
    }

    // ==================== IMPORT FUNCTIONALITY ====================
    // Download Sample Template
    if (downloadSampleTemplateBtn) {
        downloadSampleTemplateBtn.addEventListener("click", function () {
            const sampleCsv = "Department Name,Description,Status\n" +
                "\"Information Technology (IT)\",\"Enterprise IT infrastructure and systems\",\"Active\"\n" +
                "\"Human Resources (HR)\",\"Employee lifecycle and culture\",\"Active\"\n" +
                "\"Finance & Accounts\",\"Financial planning, payroll and auditing\",\"Active\"\n" +
                "\"Marketing & Communications\",\"Brand strategy and public relations\",\"Active\"\n" +
                "\"Customer Support\",\"Helpdesk and service delivery\",\"Active\"\n";

            const blob = new Blob([sampleCsv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "sample_departments_template.csv");
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
            startImportBtn.textContent = "Import Departments";
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
                if (typeof showToast === "function") showToast("No valid department rows found in this file.", "warning");
                resetImportForm();
                return;
            }

            parsedImportRows = parsed;

            if (previewFileName) previewFileName.textContent = file.name;
            const sizeKb = (file.size / 1024).toFixed(1);
            if (previewFileMeta) previewFileMeta.textContent = `${sizeKb} KB • ${parsed.length} departments ready to import`;

            if (csvDropzone) csvDropzone.style.display = "none";
            if (filePreviewCard) filePreviewCard.style.display = "flex";
            if (startImportBtn) {
                startImportBtn.disabled = false;
                startImportBtn.textContent = `Import ${parsed.length} Departments`;
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
            if (c.includes("name") || c.includes("dep")) nameIdx = idx;
            else if (c.includes("desc") || c.includes("detail") || c.includes("note")) descIdx = idx;
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
                department_name: name,
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
    if (importDepartmentForm) {
        importDepartmentForm.addEventListener("submit", function (e) {
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

            fetch("api/departments.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "import",
                    duplicate_handling: duplicateHandling,
                    departments: parsedImportRows
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(importModal);
                    resetImportForm();
                    if (typeof showToast === "function") {
                        showToast(res.message || "Departments imported successfully!", "success");
                    }
                    loadDepartments();
                } else {
                    if (startImportBtn) {
                        startImportBtn.disabled = false;
                        startImportBtn.textContent = `Import ${parsedImportRows.length} Departments`;
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
                    startImportBtn.textContent = `Import ${parsedImportRows.length} Departments`;
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
