/**
 * Unified Reports Center Interactive Controller
 * VIROS IT Asset & Helpdesk Portal
 */

document.addEventListener("DOMContentLoaded", function () {
    const moduleTabs = document.querySelectorAll(".report-module-tab");
    const reportPanes = document.querySelectorAll(".report-module-pane");
    const searchInput = document.getElementById("reportSearchInput");
    const categoryFilter = document.getElementById("reportCategoryFilter");

    let currentModule = "assets";
    let activeSubFilter = "all";

    const MODULE_SUBFILTERS = {
        assets: [
            { label: "All Assets", filter: "all" },
            { label: "In Use", filter: "in use" },
            { label: "In Stock", filter: "available" },
            { label: "Maintenance", filter: "maintenance" },
            { label: "Scrapped", filter: "scrapped" }
        ],
        licenses: [
            { label: "All Licenses", filter: "all" },
            { label: "Active", filter: "active" },
            { label: "Expiring Soon", filter: "expiring" },
            { label: "Expired", filter: "expired" }
        ],
        tickets: [
            { label: "All Tickets", filter: "all" },
            { label: "Open", filter: "open" },
            { label: "In Progress", filter: "progress" },
            { label: "Resolved", filter: "resolved" }
        ],
        scrap: [
            { label: "All Items", filter: "all" },
            { label: "In Scrap Yard", filter: "yard" },
            { label: "Recycled / E-Waste", filter: "recycle" },
            { label: "Sold / Salvaged", filter: "sold" }
        ]
    };

    function renderSubFilters() {
        const container = document.getElementById("reportsSubFilterContainer");
        if (!container) return;

        const filters = MODULE_SUBFILTERS[currentModule] || MODULE_SUBFILTERS.assets;
        container.innerHTML = "";

        filters.forEach(item => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "reports-sub-filter-btn" + (item.filter === activeSubFilter ? " active" : "");
            btn.dataset.filter = item.filter;
            btn.textContent = item.label;

            btn.addEventListener("click", function () {
                container.querySelectorAll(".reports-sub-filter-btn").forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                activeSubFilter = (this.dataset.filter || "all").toLowerCase().trim();
                filterCurrentTable();
            });

            container.appendChild(btn);
        });
    }

    const MODULE_NAMES = {
        assets: "Hardware Assets Registry",
        licenses: "Software Licenses & Renewals",
        tickets: "IT Helpdesk & Incident SLA",
        scrap: "Scrap & Salvage Disposal Register"
    };

    function updatePrintHeaderInfo() {
        const printTitle = document.getElementById("printHeaderTitle");
        if (printTitle) {
            printTitle.textContent = "OFFICIAL EXECUTIVE AUDIT REPORT: " + (MODULE_NAMES[currentModule] || "IT AUDIT REPORT").toUpperCase();
        }
        const printMod = document.getElementById("printHeaderModuleName");
        if (printMod) {
            printMod.textContent = MODULE_NAMES[currentModule] || "Active Audit Register";
        }
    }

    // 1. Module Tab Switching (Assets / Licenses / Tickets / Scrap)
    moduleTabs.forEach(tab => {
        tab.addEventListener("click", function () {
            moduleTabs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");
            currentModule = this.dataset.module;

            reportPanes.forEach(pane => {
                const isCurrent = (pane.dataset.module === currentModule);
                pane.style.display = isCurrent ? "block" : "none";
                if (isCurrent) {
                    pane.classList.add("active");
                } else {
                    pane.classList.remove("active");
                }
            });

            updatePrintHeaderInfo();

            // Reset search & subfilter on module switch
            if (searchInput) searchInput.value = "";
            activeSubFilter = "all";

            renderSubFilters();
            populateCategoryFilter();
            filterCurrentTable();
        });
    });

    // 3. Category / Department Filter Dropdown
    if (categoryFilter) {
        categoryFilter.addEventListener("change", function () {
            filterCurrentTable();
        });
    }

    // 4. Search Filter
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            filterCurrentTable();
        });
    }

    function populateCategoryFilter() {
        if (!categoryFilter) return;
        const activePane = document.querySelector(`.report-module-pane[data-module="${currentModule}"]`);
        if (!activePane) return;

        const table = activePane.querySelector("table");
        if (!table) return;

        const categories = new Set();
        table.querySelectorAll("tbody tr").forEach(row => {
            const cat = row.getAttribute("data-category");
            if (cat && cat.trim()) categories.add(cat.trim());
        });

        // Retain first "All" option
        categoryFilter.innerHTML = '<option value="">All Categories / Types</option>';
        Array.from(categories).sort().forEach(c => {
            const opt = document.createElement("option");
            opt.value = c;
            opt.textContent = c;
            categoryFilter.appendChild(opt);
        });
    }

    // 5. Main Filter Function
    function filterCurrentTable() {
        const activePane = document.querySelector(`.report-module-pane[data-module="${currentModule}"]`);
        if (!activePane) return;

        const table = activePane.querySelector("table");
        if (!table) return;

        const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const selectedCat = categoryFilter ? categoryFilter.value.toLowerCase().trim() : "";
        const rows = table.querySelectorAll("tbody tr");
        let visibleCount = 0;

        rows.forEach(row => {
            if (row.classList.contains("no-records-row")) return;

            const text = row.textContent.toLowerCase();
            const rowStatus = (row.getAttribute("data-status") || "").toLowerCase().trim();
            const rowCat = (row.getAttribute("data-category") || "").toLowerCase().trim();

            const matchesQuery = !query || text.includes(query);
            const matchesCategory = !selectedCat || rowCat === selectedCat;
            const matchesSubFilter = (activeSubFilter === "all") || (rowStatus === activeSubFilter) || (rowStatus.includes(activeSubFilter));

            if (matchesQuery && matchesCategory && matchesSubFilter) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        // Update counter badge if available
        const counterEl = activePane.querySelector(".reports-count-display");
        if (counterEl) {
            counterEl.textContent = visibleCount;
        }

        // Show/hide no-records message
        let noRecordsEl = table.querySelector(".no-records-row");
        if (visibleCount === 0) {
            if (!noRecordsEl) {
                const tr = document.createElement("tr");
                tr.className = "no-records-row";
                const cols = table.querySelectorAll("thead th").length || 6;
                tr.innerHTML = `<td colspan="${cols}" style="text-align:center; padding: 36px 16px; color: var(--text-secondary);">
                    <div style="font-weight: 600; font-size: 14px; color: var(--navy-deep); margin-bottom: 4px;">No matching records found</div>
                    <div style="font-size: 12px;">Try adjusting your search query or changing active filter criteria.</div>
                </td>`;
                table.querySelector("tbody").appendChild(tr);
            } else {
                noRecordsEl.style.display = "";
            }
        } else if (noRecordsEl) {
            noRecordsEl.style.display = "none";
        }
    }

    // Initial setup
    renderSubFilters();
    populateCategoryFilter();
    updatePrintHeaderInfo();
});

// Direct Print Slip Trigger
function printReportSlip() {
    window.print();
}
window.printReportSlip = printReportSlip;

// CSV Export for active module
function exportActiveReportCSV() {
    const activeTab = document.querySelector(".report-module-tab.active");
    const moduleName = activeTab ? activeTab.dataset.module : "assets";
    const activePane = document.querySelector(`.report-module-pane[data-module="${moduleName}"]`);
    if (!activePane) return;

    const table = activePane.querySelector("table");
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll("tr");

    rows.forEach(row => {
        if (row.style.display === "none" || row.classList.contains("no-records-row")) return;
        const cols = row.querySelectorAll("th, td");
        let rowData = [];
        cols.forEach(col => {
            // Strip multiple spaces and quotes
            let text = col.innerText.replace(/"/g, '""').replace(/\r?\n|\r/g, ' ').trim();
            rowData.push('"' + text + '"');
        });
        if (rowData.length > 0) {
            csv.push(rowData.join(","));
        }
    });

    if (csv.length === 0) {
        if (typeof showToast === "function") showToast("No records available to export.", "warning");
        else alert("No records available to export.");
        return;
    }

    const csvContent = "\uFEFF" + csv.join("\n"); // Include UTF-8 BOM
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `IT_Report_${moduleName}_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    if (typeof showToast === "function") {
        showToast("Report exported successfully as CSV!", "success");
    }
}
