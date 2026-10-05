/**
 * Accessory Categories Management Script
 * VIROS IT Asset & Service Desk Portal
 */

document.addEventListener("DOMContentLoaded", function () {
    let categoriesList = [];

    // DOM Elements
    const categoriesTbody = document.getElementById("categoriesTbody");
    const searchInput = document.getElementById("searchCategory");
    const filterStatus = document.getElementById("filterStatus");
    const resetFiltersBtn = document.getElementById("resetFiltersBtn");
    const refreshBtn = document.getElementById("refreshBtn");
    const exportCsvBtn = document.getElementById("exportCsvBtn");

    // Stat counters
    const statTotal = document.getElementById("statTotal");
    const statActive = document.getElementById("statActive");
    const statInactive = document.getElementById("statInactive");

    // Add Modal Elements
    const addModal = document.getElementById("addCategoryModal");
    const openAddModalBtn = document.getElementById("openAddModalBtn");
    const closeAddModalBtn = document.getElementById("closeAddModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelAddModalBtn");
    const addCategoryForm = document.getElementById("addCategoryForm");

    // Edit Modal Elements
    const editModal = document.getElementById("editCategoryModal");
    const closeEditModalBtn = document.getElementById("closeEditModalBtn");
    const cancelEditModalBtn = document.getElementById("cancelEditModalBtn");
    const editCategoryForm = document.getElementById("editCategoryForm");
    const editIdInput = document.getElementById("editId");
    const editNameInput = document.getElementById("editName");
    const editDescInput = document.getElementById("editDesc");
    const editStatusSelect = document.getElementById("editStatus");

    // Delete Modal Elements
    const deleteModal = document.getElementById("deleteCategoryModal");
    const closeDeleteModalBtn = document.getElementById("closeDeleteModalBtn");
    const cancelDeleteModalBtn = document.getElementById("cancelDeleteModalBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const deleteCategoryNameSpan = document.getElementById("deleteCategoryName");
    let categoryToDeleteId = null;

    // Import Modal Elements
    const importModal = document.getElementById("importCategoryModal");
    const openImportModalBtn = document.getElementById("openImportModalBtn");
    const closeImportModalBtn = document.getElementById("closeImportModalBtn");
    const cancelImportModalBtn = document.getElementById("cancelImportModalBtn");
    const importCategoryForm = document.getElementById("importCategoryForm");
    const downloadSampleTemplateBtn = document.getElementById("downloadSampleTemplateBtn");
    const csvDropzone = document.getElementById("csvDropzone");
    const csvFileInput = document.getElementById("csvFileInput");
    const filePreviewCard = document.getElementById("filePreviewCard");
    const previewFileName = document.getElementById("previewFileName");
    const previewFileMeta = document.getElementById("previewFileMeta");
    const removeFileBtn = document.getElementById("removeFileBtn");
    const duplicateHandlingSelect = document.getElementById("duplicateHandling");
    const startImportBtn = document.getElementById("startImportBtn");
    let parsedImportRows = [];

    // Load Categories on init
    loadCategories();

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
            loadCategories(() => {
                this.classList.remove("spinning");
                toast("Accessory categories refreshed", "info");
            });
        });
    }

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener("click", exportCategoriesCsv);
    }

    // Modal Control Helpers
    function openModal(modal) {
        if (modal) modal.classList.add("active");
    }
    function closeModal(modal) {
        if (modal) modal.classList.remove("active");
    }

    // Modal Trigger Listeners
    if (openAddModalBtn) openAddModalBtn.addEventListener("click", () => openModal(addModal));
    if (closeAddModalBtn) closeAddModalBtn.addEventListener("click", () => closeModal(addModal));
    if (cancelAddModalBtn) cancelAddModalBtn.addEventListener("click", () => closeModal(addModal));

    if (closeEditModalBtn) closeEditModalBtn.addEventListener("click", () => closeModal(editModal));
    if (cancelEditModalBtn) cancelEditModalBtn.addEventListener("click", () => closeModal(editModal));

    if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener("click", () => closeModal(deleteModal));
    if (cancelDeleteModalBtn) cancelDeleteModalBtn.addEventListener("click", () => closeModal(deleteModal));

    if (openImportModalBtn) {
        openImportModalBtn.addEventListener("click", () => {
            resetImportForm();
            openModal(importModal);
        });
    }
    if (closeImportModalBtn) closeImportModalBtn.addEventListener("click", () => closeModal(importModal));
    if (cancelImportModalBtn) cancelImportModalBtn.addEventListener("click", () => closeModal(importModal));

    // Close on backdrop click
    [addModal, editModal, deleteModal, importModal].forEach(modal => {
        if (modal) {
            modal.addEventListener("click", function (e) {
                if (e.target === modal) closeModal(modal);
            });
        }
    });

    // Close on Escape key
    window.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeModal(addModal);
            closeModal(editModal);
            closeModal(deleteModal);
            closeModal(importModal);
        }
    });

    // Load categories from API
    function loadCategories(callback) {
        fetch("api/accessory_categories.php")
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    categoriesList = data.categories || [];
                    updateStatsDisplay(data.stats);
                    filterAndRender();
                } else {
                    toast(data.message || "Failed to load categories", "error");
                }
                if (typeof callback === "function") callback();
            })
            .catch(err => {
                console.error("API error:", err);
                toast("Could not connect to database server.", "error");
                if (typeof callback === "function") callback();
            });
    }

    // Filter and Render rows
    function filterAndRender() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const statusVal = filterStatus ? filterStatus.value : "All";

        const filtered = categoriesList.filter(cat => {
            const matchesQuery = query === "" ||
                (cat.category_name && cat.category_name.toLowerCase().includes(query)) ||
                (cat.description && cat.description.toLowerCase().includes(query));

            const matchesStatus = statusVal === "All" || cat.status === statusVal;

            return matchesQuery && matchesStatus;
        });

        renderTable(filtered);
    }

    // Render table
    function renderTable(list) {
        if (!categoriesTbody) return;
        categoriesTbody.innerHTML = "";

        if (list.length === 0) {
            categoriesTbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="table-empty-state">
                            <div class="empty-icon">
                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <div class="empty-title">No categories found</div>
                            <div class="empty-desc">Try modifying your search or filter criteria, or add a new category.</div>
                            <button class="btn-primary" onclick="document.getElementById('openAddModalBtn').click()">+ Add New Category</button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        list.forEach(cat => {
            const statusClass = cat.status === "Active" ? "status-active" : "status-inactive";

            const tr = document.createElement("tr");
            tr.setAttribute("data-id", cat.id);
            tr.innerHTML = `
                <td>
                    <span class="category-main-text">${escapeHtml(cat.category_name)}</span>
                </td>
                <td style="color: var(--text-secondary); font-size: 13px;">
                    ${cat.description ? escapeHtml(cat.description) : '<span style="color:#94a3b8; font-style:italic;">No description provided</span>'}
                </td>
                <td>
                    <span class="status-pill ${statusClass}">
                        <span class="status-dot"></span>
                        ${escapeHtml(cat.status)}
                    </span>
                </td>
                <td style="color: var(--text-muted); font-size: 12px; white-space: nowrap;">
                    ${escapeHtml(cat.created_at || "—")}
                </td>
                <td>
                    <div class="table-actions">
                        <button class="action-btn edit-cat-btn" title="Edit Category" data-id="${cat.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button class="action-btn toggle-btn toggle-cat-btn" title="${cat.status === 'Active' ? 'Deactivate' : 'Activate'}" data-id="${cat.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                        </button>
                        <button class="action-btn delete-btn delete-cat-btn" title="Delete Category" data-id="${cat.id}" data-name="${escapeHtml(cat.category_name)}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </td>
            `;

            // Bind Row Actions
            tr.querySelector(".edit-cat-btn").addEventListener("click", () => openEditCategory(cat));
            tr.querySelector(".toggle-cat-btn").addEventListener("click", () => toggleCategoryStatus(cat.id));
            tr.querySelector(".delete-cat-btn").addEventListener("click", () => openDeleteCategory(cat));

            categoriesTbody.appendChild(tr);
        });
    }

    // Add Category Form Submit
    if (addCategoryForm) {
        addCategoryForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            const formData = new FormData(this);
            formData.append("action", "create");

            fetch("api/accessory_categories.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    if (data.success) {
                        toast(data.message, "success");
                        closeModal(addModal);
                        addCategoryForm.reset();
                        loadCategories();
                    } else {
                        toast(data.message || "Failed to create category", "error");
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    console.error("Create error:", err);
                    toast("An unexpected error occurred", "error");
                });
        });
    }

    // Open Edit Category Modal
    function openEditCategory(cat) {
        editIdInput.value = cat.id;
        editNameInput.value = cat.category_name;
        editDescInput.value = cat.description || "";
        editStatusSelect.value = cat.status || "Active";
        openModal(editModal);
    }

    // Edit Category Form Submit
    if (editCategoryForm) {
        editCategoryForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            const formData = new FormData(this);
            formData.append("action", "edit");

            fetch("api/accessory_categories.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    if (data.success) {
                        toast(data.message, "success");
                        closeModal(editModal);
                        loadCategories();
                    } else {
                        toast(data.message || "Failed to update category", "error");
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    console.error("Edit error:", err);
                    toast("An unexpected error occurred", "error");
                });
        });
    }

    // Toggle Category Status
    function toggleCategoryStatus(id) {
        const formData = new FormData();
        formData.append("action", "toggle_status");
        formData.append("id", id);

        fetch("api/accessory_categories.php", {
            method: "POST",
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    toast(data.message, "info");
                    loadCategories();
                } else {
                    toast(data.message || "Failed to change status", "error");
                }
            })
            .catch(err => {
                console.error("Toggle error:", err);
                toast("Could not update status", "error");
            });
    }

    // Open Delete Modal
    function openDeleteCategory(cat) {
        categoryToDeleteId = cat.id;
        if (deleteCategoryNameSpan) deleteCategoryNameSpan.textContent = `"${cat.category_name}"`;
        openModal(deleteModal);
    }

    // Confirm Delete
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function () {
            if (!categoryToDeleteId) return;
            this.disabled = true;

            const formData = new FormData();
            formData.append("action", "delete");
            formData.append("id", categoryToDeleteId);

            fetch("api/accessory_categories.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    confirmDeleteBtn.disabled = false;
                    if (data.success) {
                        toast(data.message, "success");
                        closeModal(deleteModal);
                        categoryToDeleteId = null;
                        loadCategories();
                    } else {
                        toast(data.message || "Failed to delete category", "error");
                    }
                })
                .catch(err => {
                    confirmDeleteBtn.disabled = false;
                    console.error("Delete error:", err);
                    toast("Could not delete category", "error");
                });
        });
    }

    // Export CSV
    function exportCategoriesCsv() {
        if (categoriesList.length === 0) {
            toast("No categories to export", "warning");
            return;
        }

        let csv = "ID,Category Name,Description,Status,Created Date\n";
        categoriesList.forEach(c => {
            csv += `"${c.id}","${c.category_name.replace(/"/g, '""')}","${(c.description || '').replace(/"/g, '""')}","${c.status}","${c.created_at}"\n`;
        });

        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `Accessory_Categories_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast(`Exported ${categoriesList.length} categories to CSV`, "success");
    }

    // ==================== IMPORT FUNCTIONALITY ====================
    // Download Sample Template
    if (downloadSampleTemplateBtn) {
        downloadSampleTemplateBtn.addEventListener("click", function () {
            const sampleCsv = "Category Name,Description,Status\n" +
                "\"Keyboards & Mice\",\"Wireless and wired desktop peripheral sets\",\"Active\"\n" +
                "\"Docking Stations & Hubs\",\"USB-C and Thunderbolt multi-port docks\",\"Active\"\n" +
                "\"Webcams & Microphones\",\"Conference cameras and noise-canceling headsets\",\"Active\"\n" +
                "\"Cables & Adapters\",\"HDMI, DisplayPort, Ethernet, and charging cables\",\"Active\"\n" +
                "\"Laptop Bags & Sleeves\",\"Protective travel sleeves and backpacks\",\"Active\"\n";

            const blob = new Blob([sampleCsv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "sample_accessory_categories_template.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            toast("Sample CSV template downloaded!", "info");
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
            startImportBtn.textContent = "Import Categories";
        }
    }

    function handleFileSelection(file) {
        if (!file.name.toLowerCase().endsWith(".csv")) {
            toast("Please select a valid CSV file.", "error");
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const content = e.target.result;
            const parsed = parseCsvText(content);

            if (parsed.length === 0) {
                toast("No valid category rows found in this file.", "warning");
                resetImportForm();
                return;
            }

            parsedImportRows = parsed;

            if (previewFileName) previewFileName.textContent = file.name;
            const sizeKb = (file.size / 1024).toFixed(1);
            if (previewFileMeta) previewFileMeta.textContent = `${sizeKb} KB • ${parsed.length} categories ready to import`;

            if (csvDropzone) csvDropzone.style.display = "none";
            if (filePreviewCard) filePreviewCard.style.display = "flex";
            if (startImportBtn) {
                startImportBtn.disabled = false;
                startImportBtn.textContent = `Import ${parsed.length} Categories`;
            }
        };
        reader.onerror = function () {
            toast("Failed to read file.", "error");
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
            if (c.includes("name") || c.includes("category")) nameIdx = idx;
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
                category_name: name,
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
    if (importCategoryForm) {
        importCategoryForm.addEventListener("submit", function (e) {
            e.preventDefault();

            if (parsedImportRows.length === 0) {
                toast("Please select a valid CSV file first.", "warning");
                return;
            }

            if (startImportBtn) {
                startImportBtn.disabled = true;
                startImportBtn.textContent = "Importing...";
            }

            const duplicateHandling = duplicateHandlingSelect ? duplicateHandlingSelect.value : "skip";

            fetch("api/accessory_categories.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "import",
                    duplicate_handling: duplicateHandling,
                    categories: parsedImportRows
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(importModal);
                    resetImportForm();
                    toast(res.message || "Categories imported successfully!", "success");
                    loadCategories();
                } else {
                    if (startImportBtn) {
                        startImportBtn.disabled = false;
                        startImportBtn.textContent = `Import ${parsedImportRows.length} Categories`;
                    }
                    toast(res.message || "Import failed.", "error");
                }
            })
            .catch(err => {
                console.error("Import error:", err);
                if (startImportBtn) {
                    startImportBtn.disabled = false;
                    startImportBtn.textContent = `Import ${parsedImportRows.length} Categories`;
                }
                toast("An unexpected error occurred during import.", "error");
            });
        });
    }

    // Update Stats
    function updateStatsDisplay(stats) {
        if (!stats) return;
        if (statTotal) statTotal.textContent = stats.total || 0;
        if (statActive) statActive.textContent = stats.active || 0;
        if (statInactive) statInactive.textContent = stats.inactive || 0;
    }

    function toast(msg, type = "info") {
        if (typeof window.showToast === "function") {
            window.showToast(msg, type);
        } else {
            console.log(`[Toast ${type}]: ${msg}`);
        }
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
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }
});
