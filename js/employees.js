/**
 * Employee Master Management Script
 * Asset Management & IT Service Desk Portal
 * 
 * Features:
 * - Real-time debounced search & multi-facet filtering (Department, Location, Status)
 * - Quick-view profile cards with avatar generation
 * - CRUD operations via async API requests
 * - Instant status toggle
 * - CSV Export & Batch Drag-and-Drop CSV Import with sample template
 */

document.addEventListener("DOMContentLoaded", function () {
    let employeesList = [];
    let departmentsList = [];
    let locationsList = [];

    // DOM Elements
    const employeesTbody = document.getElementById("employeesTbody");
    const searchInput = document.getElementById("searchEmployee");
    const filterDepartment = document.getElementById("filterDepartment");
    const filterLocation = document.getElementById("filterLocation");
    const filterStatus = document.getElementById("filterStatus");
    const resetFiltersBtn = document.getElementById("resetFiltersBtn");
    const refreshTableBtn = document.getElementById("refreshTableBtn");
    const exportCsvBtn = document.getElementById("exportCsvBtn");

    // Stat Counters
    const statTotal = document.getElementById("statTotal");
    const statActive = document.getElementById("statActive");
    const statInactive = document.getElementById("statInactive");
    const statDepts = document.getElementById("statDepts");

    // Add Modal Elements
    const addModal = document.getElementById("addEmployeeModal");
    const openAddModalBtn = document.getElementById("openAddModalBtn");
    const closeAddModalBtn = document.getElementById("closeAddModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelAddModalBtn");
    const addEmployeeForm = document.getElementById("addEmployeeForm");
    const addEmpCodeInput = document.getElementById("addEmpCode");
    const addEmailInput = document.getElementById("addEmail");
    const addFirstNameInput = document.getElementById("addFirstName");
    const addLastNameInput = document.getElementById("addLastName");
    const addPhoneInput = document.getElementById("addPhone");
    const addDesignationInput = document.getElementById("addDesignation");
    const addDepartmentSelect = document.getElementById("addDepartment");
    const addLocationSelect = document.getElementById("addLocation");
    const addJoiningDateInput = document.getElementById("addJoiningDate");
    const addStatusSelect = document.getElementById("addStatus");

    // Edit Modal Elements
    const editModal = document.getElementById("editEmployeeModal");
    const closeEditModalBtn = document.getElementById("closeEditModalBtn");
    const cancelEditModalBtn = document.getElementById("cancelEditModalBtn");
    const editEmployeeForm = document.getElementById("editEmployeeForm");
    const editIdInput = document.getElementById("editId");
    const editEmpCodeInput = document.getElementById("editEmpCode");
    const editEmailInput = document.getElementById("editEmail");
    const editFirstNameInput = document.getElementById("editFirstName");
    const editLastNameInput = document.getElementById("editLastName");
    const editPhoneInput = document.getElementById("editPhone");
    const editDesignationInput = document.getElementById("editDesignation");
    const editDepartmentSelect = document.getElementById("editDepartment");
    const editLocationSelect = document.getElementById("editLocation");
    const editJoiningDateInput = document.getElementById("editJoiningDate");
    const editStatusSelect = document.getElementById("editStatus");

    // Profile Quick-View Modal Elements
    const profileModal = document.getElementById("viewProfileModal");
    const closeProfileModalBtn = document.getElementById("closeProfileModalBtn");
    const closeProfileBtn = document.getElementById("closeProfileBtn");
    const editFromProfileBtn = document.getElementById("editFromProfileBtn");
    const profileAvatar = document.getElementById("profileAvatar");
    const profileName = document.getElementById("profileName");
    const profileDesignation = document.getElementById("profileDesignation");
    const profileEmpCode = document.getElementById("profileEmpCode");
    const profileStatus = document.getElementById("profileStatus");
    const profileStatusPill = document.getElementById("profileStatusPill");
    const profileDepartment = document.getElementById("profileDepartment");
    const profileLocation = document.getElementById("profileLocation");
    const profileEmail = document.getElementById("profileEmail");
    const profilePhone = document.getElementById("profilePhone");
    const profileJoiningDate = document.getElementById("profileJoiningDate");
    const profileCreatedDate = document.getElementById("profileCreatedDate");
    let currentProfileEmpId = null;

    // Delete Modal Elements
    const deleteModal = document.getElementById("deleteEmployeeModal");
    const closeDeleteModalBtn = document.getElementById("closeDeleteModalBtn");
    const cancelDeleteModalBtn = document.getElementById("cancelDeleteModalBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const deleteEmployeeNameSpan = document.getElementById("deleteEmployeeName");
    const deleteEmployeeCodeSpan = document.getElementById("deleteEmployeeCode");
    let employeeToDeleteId = null;

    // Import Modal Elements
    const importModal = document.getElementById("importEmployeeModal");
    const openImportModalBtn = document.getElementById("openImportModalBtn");
    const closeImportModalBtn = document.getElementById("closeImportModalBtn");
    const cancelImportModalBtn = document.getElementById("cancelImportModalBtn");
    const importEmployeeForm = document.getElementById("importEmployeeForm");
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

    // Avatar Colors
    const avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];

    // Initial Load
    loadEmployees();

    // Search and Filter Listeners
    if (searchInput) {
        searchInput.addEventListener("input", debounce(filterAndRender, 200));
    }
    if (filterDepartment) {
        filterDepartment.addEventListener("change", filterAndRender);
    }
    if (filterLocation) {
        filterLocation.addEventListener("change", filterAndRender);
    }
    if (filterStatus) {
        filterStatus.addEventListener("change", filterAndRender);
    }
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener("click", function () {
            if (searchInput) searchInput.value = "";
            if (filterDepartment) filterDepartment.value = "All";
            if (filterLocation) filterLocation.value = "All";
            if (filterStatus) filterStatus.value = "All";
            filterAndRender();
        });
    }
    if (refreshTableBtn) {
        refreshTableBtn.addEventListener("click", function () {
            this.classList.add("spinning");
            loadEmployees(() => {
                this.classList.remove("spinning");
                if (typeof showToast === "function") showToast("Employee directory refreshed.", "info");
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

    // Modal Close Listeners
    if (closeAddModalBtn) closeAddModalBtn.addEventListener("click", () => closeModal(addModal));
    if (cancelAddModalBtn) cancelAddModalBtn.addEventListener("click", () => closeModal(addModal));
    if (closeEditModalBtn) closeEditModalBtn.addEventListener("click", () => closeModal(editModal));
    if (cancelEditModalBtn) cancelEditModalBtn.addEventListener("click", () => closeModal(editModal));
    if (closeProfileModalBtn) closeProfileModalBtn.addEventListener("click", () => closeModal(profileModal));
    if (closeProfileBtn) closeProfileBtn.addEventListener("click", () => closeModal(profileModal));
    if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener("click", () => closeModal(deleteModal));
    if (cancelDeleteModalBtn) cancelDeleteModalBtn.addEventListener("click", () => closeModal(deleteModal));
    if (closeImportModalBtn) closeImportModalBtn.addEventListener("click", () => closeModal(importModal));
    if (cancelImportModalBtn) cancelImportModalBtn.addEventListener("click", () => closeModal(importModal));

    // Outside Click & ESC Key
    [addModal, editModal, profileModal, deleteModal, importModal].forEach(m => {
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
            closeModal(profileModal);
            closeModal(deleteModal);
            closeModal(importModal);
        }
    });

    // Open Add Modal
    if (openAddModalBtn) {
        openAddModalBtn.addEventListener("click", function () {
            addEmployeeForm.reset();
            // Pre-fetch next code
            fetch("api/employees.php?action=get_next_code")
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.next_code && addEmpCodeInput) {
                        addEmpCodeInput.value = res.next_code;
                    }
                })
                .catch(() => {});
            openModal(addModal);
            setTimeout(() => addFirstNameInput && addFirstNameInput.focus(), 100);
        });
    }

    // Switch from Profile Quick-View to Edit Modal
    if (editFromProfileBtn) {
        editFromProfileBtn.addEventListener("click", function () {
            closeModal(profileModal);
            if (currentProfileEmpId) {
                window.openEditEmployeeModal(currentProfileEmpId);
            }
        });
    }

    // 1. Fetch Employees & Populate Meta from API
    function loadEmployees(callback) {
        fetch("api/employees.php")
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    employeesList = res.data || [];
                    departmentsList = res.departments || [];
                    locationsList = res.locations || [];

                    // Populate filter dropdowns if empty
                    syncDropdownOptions();

                    // Update KPI counters
                    if (res.stats) {
                        updateStats(res.stats);
                    }
                    filterAndRender();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to load employees", "error");
                }
                if (typeof callback === "function") callback();
            })
            .catch(err => {
                console.error("API error:", err);
                if (typeof showToast === "function") showToast("Network error connecting to API", "error");
                if (typeof callback === "function") callback();
            });
    }

    function syncDropdownOptions() {
        if (filterDepartment && departmentsList.length > 0 && filterDepartment.options.length <= 1) {
            departmentsList.forEach(d => {
                const opt = document.createElement("option");
                opt.value = d.id;
                opt.textContent = d.department_name;
                filterDepartment.appendChild(opt);
            });
        }
        if (filterLocation && locationsList.length > 0 && filterLocation.options.length <= 1) {
            locationsList.forEach(l => {
                const opt = document.createElement("option");
                opt.value = l.id;
                opt.textContent = l.location_name;
                filterLocation.appendChild(opt);
            });
        }
    }

    function updateStats(stats) {
        if (statTotal) statTotal.textContent = stats.total ?? 0;
        if (statActive) statActive.textContent = stats.active ?? 0;
        if (statInactive) statInactive.textContent = stats.inactive ?? 0;
        if (statDepts) statDepts.textContent = stats.departments_count ?? 0;
    }

    // 2. Filter & Render Table
    function filterAndRender() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : "";
        const deptVal = filterDepartment ? filterDepartment.value : "All";
        const locVal = filterLocation ? filterLocation.value : "All";
        const statusVal = filterStatus ? filterStatus.value : "All";

        const filtered = employeesList.filter(emp => {
            const fullName = `${emp.first_name || ""} ${emp.last_name || ""}`.toLowerCase();
            const code = (emp.emp_code || "").toLowerCase();
            const email = (emp.email || "").toLowerCase();
            const phone = (emp.phone || "").toLowerCase();
            const desig = (emp.designation || "").toLowerCase();
            const deptName = (emp.department_name || "").toLowerCase();
            const locName = (emp.location_name || "").toLowerCase();

            const matchesQuery = query === "" || 
                fullName.includes(query) || 
                code.includes(query) || 
                email.includes(query) || 
                phone.includes(query) || 
                desig.includes(query) || 
                deptName.includes(query) || 
                locName.includes(query);

            const matchesDept = deptVal === "All" || String(emp.department_id) === String(deptVal);
            const matchesLoc = locVal === "All" || String(emp.location_id) === String(locVal);
            const matchesStatus = statusVal === "All" || emp.status === statusVal;

            return matchesQuery && matchesDept && matchesLoc && matchesStatus;
        });

        renderTable(filtered);
    }

    function renderTable(rows) {
        if (!employeesTbody) return;

        if (rows.length === 0) {
            employeesTbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="table-empty-state">
                            <div class="empty-icon">
                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <div class="empty-title">No matching employee records found</div>
                            <div class="empty-desc">Try clearing or adjusting your search filters.</div>
                            <button class="btn-secondary" onclick="document.getElementById('resetFiltersBtn').click()">Clear Filters</button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = "";
        rows.forEach(emp => {
            const fullName = `${emp.first_name || ""} ${emp.last_name || ""}`.trim();
            const statusClass = (emp.status === "Active") ? "status-active" : "status-inactive";
            const initials = ((emp.first_name ? emp.first_name.charAt(0) : "") + (emp.last_name ? emp.last_name.charAt(0) : "")).toUpperCase() || "EM";
            const colorBg = avatarColors[(emp.id || 0) % avatarColors.length];

            const designationHtml = emp.designation 
                ? `<span style="font-weight: 500; color: var(--text-primary); font-size: 13px;">${escapeHtml(emp.designation)}</span>`
                : `<span style="color:#94a3b8; font-style:italic;">—</span>`;

            const deptHtml = emp.department_name
                ? escapeHtml(emp.department_name)
                : '<span style="color:#94a3b8; font-style:italic;">—</span>';

            const locHtml = emp.location_name
                ? `<div class="emp-location-badge">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span>${escapeHtml(emp.location_name)}</span>
                   </div>`
                : `<span style="color:#94a3b8; font-style:italic;">Unassigned</span>`;

            let contactHtml = "";
            if (emp.phone) {
                contactHtml += `<div style="color: var(--text-primary); font-weight: 600; margin-bottom: 2px;">${escapeHtml(emp.phone)}</div>`;
            }
            if (emp.email) {
                contactHtml += `<div style="color: var(--cyan-primary); font-size: 12px;">${escapeHtml(emp.email)}</div>`;
            }
            if (!emp.phone && !emp.email) {
                contactHtml = '<span style="color:#94a3b8; font-style:italic;">—</span>';
            }

            html += `
                <tr data-id="${emp.id}">
                    <td>
                        <div class="emp-cell-flex">
                            <div class="emp-avatar" style="background: ${colorBg};">
                                ${escapeHtml(initials)}
                            </div>
                            <div class="emp-details">
                                <span class="emp-name-text">${escapeHtml(fullName)}</span>
                                <span class="emp-code-badge">${escapeHtml(emp.emp_code)}</span>
                            </div>
                        </div>
                    </td>
                    <td>${designationHtml}</td>
                    <td style="color: var(--text-primary); font-size: 13px;">${deptHtml}</td>
                    <td>${locHtml}</td>
                    <td style="font-size: 13px;">${contactHtml}</td>
                    <td>
                        <div>
                            <span class="status-pill ${statusClass}">
                                <span class="status-dot"></span>
                                ${escapeHtml(emp.status)}
                            </span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <span>${emp.joining_date ? escapeHtml(emp.joining_date) : "—"}</span>
                        </div>
                    </td>
                    <td>
                        <div class="table-actions" style="justify-content: center;">
                            <!-- View Profile -->
                            <button type="button" class="action-btn view-btn" title="View Profile" onclick="viewEmployeeProfile(${emp.id})">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>

                            <!-- Toggle Status -->
                            <button type="button" class="action-btn toggle-btn" title="Toggle Status" onclick="toggleEmployeeStatus(${emp.id})">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"></polyline>
                                    <polyline points="23 20 23 14 17 14"></polyline>
                                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                                </svg>
                            </button>

                            <!-- Edit -->
                            <button type="button" class="action-btn" title="Edit Employee" onclick="openEditEmployeeModal(${emp.id})">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>

                            <!-- Delete -->
                            <button type="button" class="action-btn delete-btn" title="Delete Employee" onclick="openDeleteEmployeeModal(${emp.id}, '${escapeQuote(fullName)}', '${escapeQuote(emp.emp_code)}')">
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

        employeesTbody.innerHTML = html;
    }

    // 3. Handle Add Employee Form Submit
    if (addEmployeeForm) {
        addEmployeeForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const payload = {
                action: "create",
                emp_code: addEmpCodeInput.value.trim(),
                email: addEmailInput.value.trim(),
                first_name: addFirstNameInput.value.trim(),
                last_name: addLastNameInput.value.trim(),
                phone: addPhoneInput.value.trim(),
                designation: addDesignationInput.value.trim(),
                department_id: addDepartmentSelect.value,
                location_id: addLocationSelect.value,
                joining_date: addJoiningDateInput.value,
                status: addStatusSelect.value
            };

            const submitBtn = addEmployeeForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Saving...";
            }

            fetch("api/employees.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(addModal);
                    addEmployeeForm.reset();
                    if (typeof showToast === "function") showToast(res.message || "Employee registered successfully!", "success");
                    loadEmployees();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to create employee.", "error");
                }
            })
            .catch(err => {
                console.error("Create error:", err);
                if (typeof showToast === "function") showToast("Server communication error", "error");
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Save Employee";
                }
            });
        });
    }

    // 4. View Profile Modal Helper
    window.viewEmployeeProfile = function (id) {
        const emp = employeesList.find(e => String(e.id) === String(id));
        if (!emp) return;

        currentProfileEmpId = emp.id;
        const fullName = `${emp.first_name || ""} ${emp.last_name || ""}`.trim();
        const initials = ((emp.first_name ? emp.first_name.charAt(0) : "") + (emp.last_name ? emp.last_name.charAt(0) : "")).toUpperCase() || "EM";
        const colorBg = avatarColors[(emp.id || 0) % avatarColors.length];

        if (profileAvatar) {
            profileAvatar.textContent = initials;
            profileAvatar.style.background = colorBg;
        }
        if (profileName) profileName.textContent = fullName;
        if (profileDesignation) profileDesignation.textContent = emp.designation || "Staff Member";
        if (profileEmpCode) profileEmpCode.textContent = emp.emp_code || "—";
        if (profileStatus) profileStatus.textContent = emp.status || "Active";
        if (profileStatusPill) {
            profileStatusPill.className = `status-pill ${(emp.status === "Active") ? "status-active" : "status-inactive"}`;
        }
        if (profileDepartment) profileDepartment.textContent = emp.department_name || "Unassigned";
        if (profileLocation) profileLocation.textContent = emp.location_name || "Unassigned";
        if (profileEmail) profileEmail.textContent = emp.email || "—";
        if (profilePhone) profilePhone.textContent = emp.phone || "—";
        if (profileJoiningDate) profileJoiningDate.textContent = emp.joining_date || "—";
        if (profileCreatedDate) profileCreatedDate.textContent = emp.created_date || emp.created_at || "—";

        openModal(profileModal);
    };

    // 5. Open Edit Employee Modal
    window.openEditEmployeeModal = function (id) {
        const emp = employeesList.find(e => String(e.id) === String(id));
        if (!emp) return;

        if (editIdInput) editIdInput.value = emp.id;
        if (editEmpCodeInput) editEmpCodeInput.value = emp.emp_code || "";
        if (editEmailInput) editEmailInput.value = emp.email || "";
        if (editFirstNameInput) editFirstNameInput.value = emp.first_name || "";
        if (editLastNameInput) editLastNameInput.value = emp.last_name || "";
        if (editPhoneInput) editPhoneInput.value = emp.phone || "";
        if (editDesignationInput) editDesignationInput.value = emp.designation || "";
        if (editDepartmentSelect) editDepartmentSelect.value = emp.department_id || "";
        if (editLocationSelect) editLocationSelect.value = emp.location_id || "";
        if (editJoiningDateInput) editJoiningDateInput.value = emp.joining_date_raw || "";
        if (editStatusSelect) editStatusSelect.value = emp.status || "Active";

        openModal(editModal);
        setTimeout(() => editFirstNameInput && editFirstNameInput.focus(), 100);
    };

    // 6. Handle Edit Employee Form Submit
    if (editEmployeeForm) {
        editEmployeeForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const payload = {
                action: "edit",
                id: editIdInput.value,
                emp_code: editEmpCodeInput.value.trim(),
                email: editEmailInput.value.trim(),
                first_name: editFirstNameInput.value.trim(),
                last_name: editLastNameInput.value.trim(),
                phone: editPhoneInput.value.trim(),
                designation: editDesignationInput.value.trim(),
                department_id: editDepartmentSelect.value,
                location_id: editLocationSelect.value,
                joining_date: editJoiningDateInput.value,
                status: editStatusSelect.value
            };

            const submitBtn = editEmployeeForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Updating...";
            }

            fetch("api/employees.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(editModal);
                    if (typeof showToast === "function") showToast(res.message || "Employee updated successfully.", "success");
                    loadEmployees();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to update employee.", "error");
                }
            })
            .catch(err => {
                console.error("Update error:", err);
                if (typeof showToast === "function") showToast("Server communication error", "error");
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Update Employee";
                }
            });
        });
    }

    // 7. Toggle Employee Status (Active <-> Inactive)
    window.toggleEmployeeStatus = function (id) {
        fetch("api/employees.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "toggle_status", id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                if (typeof showToast === "function") showToast(res.message || "Status updated.", "info");
                loadEmployees();
            } else {
                if (typeof showToast === "function") showToast(res.message || "Failed to toggle status.", "error");
            }
        })
        .catch(err => {
            console.error("Toggle error:", err);
            if (typeof showToast === "function") showToast("Error updating status.", "error");
        });
    };

    // 8. Delete Employee
    window.openDeleteEmployeeModal = function (id, name, code) {
        employeeToDeleteId = id;
        if (deleteEmployeeNameSpan) deleteEmployeeNameSpan.textContent = name;
        if (deleteEmployeeCodeSpan) deleteEmployeeCodeSpan.textContent = code;
        openModal(deleteModal);
    };

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function () {
            if (!employeeToDeleteId) return;

            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = "Deleting...";

            fetch("api/employees.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "delete", id: employeeToDeleteId })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(deleteModal);
                    if (typeof showToast === "function") showToast(res.message || "Employee deleted.", "success");
                    loadEmployees();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Failed to delete employee.", "error");
                }
            })
            .catch(err => {
                console.error("Delete error:", err);
                if (typeof showToast === "function") showToast("Error deleting employee.", "error");
            })
            .finally(() => {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = "Yes, Delete Employee";
                employeeToDeleteId = null;
            });
        });
    }

    // 9. Export to CSV
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener("click", function () {
            if (employeesList.length === 0) {
                if (typeof showToast === "function") showToast("No employees to export.", "warning");
                return;
            }

            const headers = ["Employee ID", "First Name", "Last Name", "Email", "Phone", "Designation", "Department", "Location", "Joining Date", "Status"];
            const csvRows = [headers.join(",")];

            employeesList.forEach(emp => {
                const row = [
                    escapeCsvCell(emp.emp_code || ""),
                    escapeCsvCell(emp.first_name || ""),
                    escapeCsvCell(emp.last_name || ""),
                    escapeCsvCell(emp.email || ""),
                    escapeCsvCell(emp.phone || ""),
                    escapeCsvCell(emp.designation || ""),
                    escapeCsvCell(emp.department_name || ""),
                    escapeCsvCell(emp.location_name || ""),
                    escapeCsvCell(emp.joining_date_raw || emp.joining_date || ""),
                    escapeCsvCell(emp.status || "")
                ];
                csvRows.push(row.join(","));
            });

            const csvString = csvRows.join("\r\n");
            const blob = new Blob([csvString], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            const today = new Date().toISOString().slice(0, 10);
            a.href = url;
            a.download = `employees_viros_${today}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            if (typeof showToast === "function") showToast("Employee list exported to CSV.", "success");
        });
    }

    // 10. Sample CSV Download
    if (downloadSampleTemplateBtn) {
        downloadSampleTemplateBtn.addEventListener("click", function () {
            const sampleHeaders = ["Employee ID", "First Name", "Last Name", "Email", "Phone", "Designation", "Department", "Location", "Joining Date", "Status"];
            const sampleRows = [
                sampleHeaders.join(","),
                ['EMP-1010', 'Arun', 'Chawla', 'arun.chawla@viros.com', '+91 98123 45678', 'Cloud Security Specialist', 'Information Technology (IT)', 'Tech Hub - Bangalore', '2024-03-01', 'Active'].map(escapeCsvCell).join(","),
                ['EMP-1011', 'Divya', 'Menon', 'divya.menon@viros.com', '+91 98234 56789', 'Talent Operations Lead', 'Human Resources (HR)', 'Delivery Center - Hyderabad', '2023-11-15', 'Active'].map(escapeCsvCell).join(",")
            ];

            const csvContent = sampleRows.join("\r\n");
            const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "sample_employees_template.csv";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    // 11. Batch CSV Import & Dropzone
    if (openImportModalBtn) {
        openImportModalBtn.addEventListener("click", function () {
            resetImportForm();
            openModal(importModal);
        });
    }

    function resetImportForm() {
        parsedImportRows = [];
        if (csvFileInput) csvFileInput.value = "";
        if (filePreviewCard) filePreviewCard.style.display = "none";
        if (csvDropzone) csvDropzone.style.display = "block";
        if (startImportBtn) {
            startImportBtn.disabled = true;
            startImportBtn.textContent = "Import Employees";
        }
    }

    if (csvDropzone) {
        csvDropzone.addEventListener("click", () => csvFileInput && csvFileInput.click());

        csvDropzone.addEventListener("dragover", function (e) {
            e.preventDefault();
            csvDropzone.classList.add("dragover");
        });

        csvDropzone.addEventListener("dragleave", function () {
            csvDropzone.classList.remove("dragover");
        });

        csvDropzone.addEventListener("drop", function (e) {
            e.preventDefault();
            csvDropzone.classList.remove("dragover");
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                handleFileSelection(e.dataTransfer.files[0]);
            }
        });
    }

    if (csvFileInput) {
        csvFileInput.addEventListener("change", function () {
            if (this.files && this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }

    if (removeFileBtn) {
        removeFileBtn.addEventListener("click", resetImportForm);
    }

    function handleFileSelection(file) {
        if (!file.name.toLowerCase().endsWith(".csv")) {
            if (typeof showToast === "function") showToast("Please select a valid .csv file.", "warning");
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const text = e.target.result;
            parsedImportRows = parseCSV(text);

            if (parsedImportRows.length === 0) {
                if (typeof showToast === "function") showToast("The selected CSV file has no records.", "warning");
                resetImportForm();
                return;
            }

            if (previewFileName) previewFileName.textContent = file.name;
            if (previewFileMeta) {
                const sizeKb = (file.size / 1024).toFixed(1);
                previewFileMeta.textContent = `${sizeKb} KB • ${parsedImportRows.length} employee record(s) detected`;
            }

            if (csvDropzone) csvDropzone.style.display = "none";
            if (filePreviewCard) filePreviewCard.style.display = "flex";
            if (startImportBtn) startImportBtn.disabled = false;
        };
        reader.readAsText(file);
    }

    function parseCSV(text) {
        const lines = text.split(/\r?\n/).filter(line => line.trim() !== "");
        if (lines.length < 2) return [];

        const headers = parseCSVLine(lines[0]);
        const results = [];

        for (let i = 1; i < lines.length; i++) {
            const cols = parseCSVLine(lines[i]);
            if (cols.length === 0) continue;

            const row = {};
            headers.forEach((h, idx) => {
                const cleanKey = h.trim();
                row[cleanKey] = cols[idx] ? cols[idx].trim() : "";
            });
            results.push(row);
        }

        return results;
    }

    function parseCSVLine(line) {
        const result = [];
        let insideQuotes = false;
        let entry = "";

        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            const nextChar = line[i + 1];

            if (char === '"') {
                if (insideQuotes && nextChar === '"') {
                    entry += '"';
                    i++;
                } else {
                    insideQuotes = !insideQuotes;
                }
            } else if (char === ',' && !insideQuotes) {
                result.push(entry);
                entry = "";
            } else {
                entry += char;
            }
        }
        result.push(entry);
        return result;
    }

    if (importEmployeeForm) {
        importEmployeeForm.addEventListener("submit", function (e) {
            e.preventDefault();
            if (parsedImportRows.length === 0) return;

            startImportBtn.disabled = true;
            startImportBtn.textContent = "Importing...";

            const payload = {
                action: "import",
                duplicate_handling: duplicateHandlingSelect ? duplicateHandlingSelect.value : "skip",
                rows: parsedImportRows
            };

            fetch("api/employees.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    closeModal(importModal);
                    resetImportForm();
                    if (typeof showToast === "function") showToast(res.message || "Import completed.", "success");
                    loadEmployees();
                } else {
                    if (typeof showToast === "function") showToast(res.message || "Import encountered issues.", "error");
                }
            })
            .catch(err => {
                console.error("Import error:", err);
                if (typeof showToast === "function") showToast("Server error during CSV import.", "error");
            })
            .finally(() => {
                startImportBtn.disabled = false;
                startImportBtn.textContent = "Import Employees";
            });
        });
    }

    // Helper functions
    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function escapeQuote(str) {
        if (!str) return "";
        return String(str).replace(/'/g, "\\'").replace(/"/g, "&quot;");
    }

    function escapeCsvCell(cell) {
        const str = String(cell);
        if (str.includes(",") || str.includes('"') || str.includes("\n")) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
});
