/**
 * Scrap & Decommissioned Assets Management JavaScript
 * VIROS IT Asset & Helpdesk Portal
 */

document.addEventListener("DOMContentLoaded", function () {
    // 1. Elements
    const searchInput = document.getElementById("scrapSearchInput");
    const reasonFilter = document.getElementById("scrapReasonFilter");
    const categoryFilter = document.getElementById("scrapCategoryFilter");
    const tabButtons = document.querySelectorAll(".scrap-tab-btn");
    const table = document.getElementById("scrapTable");
    const exportBtn = document.getElementById("exportScrapCsvBtn");

    // Modal Elements
    const addModal = document.getElementById("recordScrapModal");
    const openAddModalBtn = document.getElementById("openRecordScrapModalBtn");
    const closeAddModalBtn = document.getElementById("closeRecordScrapModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelRecordScrapModalBtn");
    const scrapForm = document.getElementById("recordScrapForm");

    const assetSelect = document.getElementById("scrapAssetSelect");
    const assetTagInput = document.getElementById("scrapAssetTag");
    const assetSerialInput = document.getElementById("scrapAssetSerial");
    const assetCategoryInput = document.getElementById("scrapAssetCategory");
    const assetLocationInput = document.getElementById("scrapAssetLocation");

    // Slip Drawer Elements
    const slipModal = document.getElementById("scrapSlipModal");
    const slipBackdrop = document.getElementById("scrapSlipBackdrop");
    const closeSlipModalBtn = document.getElementById("closeSlipModalBtn");
    const closeSlipModalFooterBtn = document.getElementById("closeSlipModalFooterBtn");
    const printSlipBtn = document.getElementById("printSlipBtn");

    let currentSlipData = null;

    // Helper: Escape HTML
    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Helper: Copy to Clipboard
    window.copyToClipboard = function (text, msg) {
        if (!text) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof showToast === "function") {
                    showToast(msg || "Copied to clipboard", "info");
                }
            }).catch(() => {
                fallbackCopy(text, msg);
            });
        } else {
            fallbackCopy(text, msg);
        }
    };

    function fallbackCopy(text, msg) {
        const ta = document.createElement("textarea");
        ta.value = text;
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand("copy");
            if (typeof showToast === "function") {
                showToast(msg || "Copied to clipboard", "info");
            }
        } catch (err) {}
        document.body.removeChild(ta);
    }

    // Modal / Drawer Control Helpers
    function openModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.add("open");
        if (modalEl === slipModal && slipBackdrop) {
            slipBackdrop.classList.add("open");
        }
        document.body.style.overflow = "hidden";
    }

    function closeModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.remove("open");
        if (modalEl === slipModal && slipBackdrop) {
            slipBackdrop.classList.remove("open");
        }
        document.body.style.overflow = "";
    }

    // 2. Tab & Search Filtering
    let currentTab = "all";

    function filterTableRows() {
        if (!table) return;
        const rows = table.querySelectorAll("tbody tr");
        const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const selectedReason = reasonFilter ? reasonFilter.value.trim() : "";
        const selectedCat = categoryFilter ? categoryFilter.value.trim() : "";

        rows.forEach(row => {
            if (row.id === "noRecordsRow") return;

            const rowStatus = row.getAttribute("data-status") || "";
            const rowReason = (row.getAttribute("data-reason") || "").trim();
            const rowCat = (row.getAttribute("data-category") || "").trim();
            const rowText = row.textContent.toLowerCase();

            // Status Tab Filter
            const matchesTab = (currentTab === "all" || rowStatus === currentTab);

            // Search Query Filter
            const matchesQuery = !query || rowText.includes(query);

            // Defect Reason Filter
            const matchesReason = !selectedReason || rowReason === selectedReason;

            // Category Filter
            const matchesCategory = !selectedCat || rowCat === selectedCat;

            if (matchesTab && matchesQuery && matchesReason && matchesCategory) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    // Tab click handlers
    tabButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            tabButtons.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            currentTab = this.getAttribute("data-tab") || "all";
            filterTableRows();
        });
    });

    if (searchInput) {
        searchInput.addEventListener("input", filterTableRows);
    }

    if (reasonFilter) {
        reasonFilter.addEventListener("change", filterTableRows);
    }

    if (categoryFilter) {
        categoryFilter.addEventListener("change", filterTableRows);
    }

    // Installed Components Elements
    const installedComponentsSection = document.getElementById("installedComponentsSection");
    const installedComponentsList = document.getElementById("installedComponentsList");
    const componentsCountBadge = document.getElementById("componentsCountBadge");
    const btnScrapAllParts = document.getElementById("btnScrapAllParts");
    const btnSalvageAllParts = document.getElementById("btnSalvageAllParts");
    let currentAssetComponents = [];

    // Modal Tab Switching (Matches assets.js)
    function switchScrapModalTab(tabName) {
        const modal = document.getElementById("recordScrapModal");
        if (!modal) return;
        modal.querySelectorAll(".modal-tab-btn").forEach(btn => {
            btn.classList.toggle("active", btn.getAttribute("data-tab") === tabName);
        });
        modal.querySelectorAll(".modal-tab-pane").forEach(pane => {
            pane.classList.toggle("active", pane.id === "modal_pane_" + tabName);
        });
    }

    document.querySelectorAll("#recordScrapModal .modal-tab-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            switchScrapModalTab(this.getAttribute("data-tab"));
        });
    });

    const btnGoToComp = document.getElementById("btnGoToCompTab");
    if (btnGoToComp) {
        btnGoToComp.addEventListener("click", function () {
            switchScrapModalTab("components");
        });
    }

    // Helper: Render Installed Components in the Scrap Modal
    function renderInstalledComponents(components) {
        currentAssetComponents = components || [];
        const modalTabBadge = document.getElementById("modalCompTabBadge");
        const noticeBox = document.getElementById("assetHasComponentsNotice");
        const noticeText = document.getElementById("assetNoticeText");

        if (!installedComponentsList) return;

        if (currentAssetComponents.length === 0) {
            if (modalTabBadge) {
                modalTabBadge.textContent = "0";
                modalTabBadge.style.display = "none";
            }
            if (noticeBox) noticeBox.style.display = "none";
            if (componentsCountBadge) {
                componentsCountBadge.textContent = "0 Parts";
                componentsCountBadge.style.background = "#f1f5f9";
                componentsCountBadge.style.color = "#64748b";
                componentsCountBadge.style.borderColor = "#e2e8f0";
            }
            installedComponentsList.innerHTML = `
                <div class="no-components-empty-msg">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    No component parts currently installed in this host asset.
                </div>
            `;
            const bulkDiv = document.getElementById("compBulkActions");
            if (bulkDiv) bulkDiv.style.display = "none";
            return;
        }

        // When components are present
        if (modalTabBadge) {
            modalTabBadge.textContent = currentAssetComponents.length;
            modalTabBadge.style.display = "inline-block";
        }
        if (noticeBox) {
            noticeBox.style.display = "flex";
            if (noticeText) {
                noticeText.textContent = `This asset has ${currentAssetComponents.length} installed component ${currentAssetComponents.length === 1 ? 'part' : 'parts'} attached.`;
            }
        }
        if (componentsCountBadge) {
            componentsCountBadge.textContent = `${currentAssetComponents.length} ${currentAssetComponents.length === 1 ? 'Part' : 'Parts'}`;
            componentsCountBadge.style.background = "#e0f2fe";
            componentsCountBadge.style.color = "#0369a1";
            componentsCountBadge.style.borderColor = "#bae6fd";
        }
        const bulkDiv = document.getElementById("compBulkActions");
        if (bulkDiv) bulkDiv.style.display = "flex";

        installedComponentsList.innerHTML = "";
        currentAssetComponents.forEach((comp, idx) => {
            const cId = comp.id || (idx + 1);
            const radioName = `comp_action_${cId}`;
            const itemDiv = document.createElement("div");
            itemDiv.className = "comp-scrap-item action-scrap";
            itemDiv.setAttribute("data-comp-id", cId);
            itemDiv.setAttribute("data-serial", comp.serial || "");
            itemDiv.setAttribute("data-sku", comp.sku || "");
            itemDiv.setAttribute("data-name", comp.name || "Component");
            itemDiv.setAttribute("data-category", comp.category || "Component");

            itemDiv.innerHTML = `
                <div class="comp-scrap-info">
                    <div class="comp-scrap-name">${escapeHtml(comp.name || 'Installed Part')}</div>
                    <div class="comp-scrap-meta">
                        ${comp.sku ? `<code>${escapeHtml(comp.sku)}</code>` : ''}
                        ${comp.serial && comp.serial !== 'N/A' ? `<span>SN: <strong>${escapeHtml(comp.serial)}</strong></span>` : ''}
                        <span>•</span>
                        <span>${escapeHtml(comp.category || 'Component')}</span>
                    </div>
                </div>
                <div class="comp-action-toggle">
                    <label class="comp-action-pill active-scrap" title="Decommission & scrap this part with the host asset">
                        <input type="radio" name="${radioName}" value="scrap" checked>
                        Scrap with Asset
                    </label>
                    <label class="comp-action-pill" title="Detach and return this part to available spare inventory">
                        <input type="radio" name="${radioName}" value="salvage">
                        Salvage to Stock
                    </label>
                </div>
            `;

            // Radio button interaction
            const radios = itemDiv.querySelectorAll(`input[name="${radioName}"]`);
            radios.forEach(r => {
                r.addEventListener("change", function () {
                    const pills = itemDiv.querySelectorAll(".comp-action-pill");
                    pills.forEach(p => p.classList.remove("active-scrap", "active-salvage"));
                    if (this.value === "scrap") {
                        this.parentElement.classList.add("active-scrap");
                        itemDiv.className = "comp-scrap-item action-scrap";
                    } else {
                        this.parentElement.classList.add("active-salvage");
                        itemDiv.className = "comp-scrap-item action-salvage";
                    }
                });
            });

            installedComponentsList.appendChild(itemDiv);
        });
    }

    // Bulk actions: Scrap All vs Salvage All
    if (btnScrapAllParts) {
        btnScrapAllParts.addEventListener("click", function () {
            if (!installedComponentsList) return;
            const items = installedComponentsList.querySelectorAll(".comp-scrap-item");
            items.forEach(item => {
                const radio = item.querySelector('input[value="scrap"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event("change"));
                }
            });
        });
    }

    if (btnSalvageAllParts) {
        btnSalvageAllParts.addEventListener("click", function () {
            if (!installedComponentsList) return;
            const items = installedComponentsList.querySelectorAll(".comp-scrap-item");
            items.forEach(item => {
                const radio = item.querySelector('input[value="salvage"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event("change"));
                }
            });
        });
    }

    // 3. Asset Selector Auto-population & Installed Components Loading
    if (assetSelect) {
        assetSelect.addEventListener("change", function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) {
                if (assetTagInput) assetTagInput.value = "";
                if (assetSerialInput) assetSerialInput.value = "";
                if (assetCategoryInput) assetCategoryInput.value = "";
                if (assetLocationInput) assetLocationInput.value = "";
                if (installedComponentsSection) installedComponentsSection.style.display = "none";
                renderInstalledComponents([]);
                return;
            }

            const assetIdVal = selectedOpt.value;
            const tag = selectedOpt.getAttribute("data-tag") || "";
            const serial = selectedOpt.getAttribute("data-serial") || "";
            const category = selectedOpt.getAttribute("data-category") || "";
            const brand = selectedOpt.getAttribute("data-brand") || "";
            const model = selectedOpt.getAttribute("data-model") || "";
            const location = selectedOpt.getAttribute("data-location") || "";

            const catModelDisplay = [category, brand, model].filter(Boolean).join(" • ");

            if (assetTagInput) assetTagInput.value = tag;
            if (assetSerialInput) assetSerialInput.value = serial;
            if (assetCategoryInput) assetCategoryInput.value = catModelDisplay;
            if (assetLocationInput) assetLocationInput.value = location;

            // Resolve installed components from window.ASSET_COMPONENTS_MAP or API
            let comps = [];
            if (window.ASSET_COMPONENTS_MAP) {
                if (window.ASSET_COMPONENTS_MAP[assetIdVal]) {
                    comps = window.ASSET_COMPONENTS_MAP[assetIdVal];
                } else if (window.ASSET_COMPONENTS_MAP[tag]) {
                    comps = window.ASSET_COMPONENTS_MAP[tag];
                }
            }

            if (!comps || comps.length === 0) {
                fetch(`api/scrap_assets.php?action=get_asset_components&asset_id=${encodeURIComponent(assetIdVal)}&asset_tag=${encodeURIComponent(tag)}`)
                    .then(res => res.json())
                    .then(resData => {
                        if (resData.success && resData.components && resData.components.length > 0) {
                            renderInstalledComponents(resData.components);
                        } else {
                            renderInstalledComponents([]);
                        }
                    })
                    .catch(() => {
                        renderInstalledComponents([]);
                    });
            } else {
                renderInstalledComponents(comps);
            }
        });
    }

    // 4. Modal Open & Close Event Listeners
    if (openAddModalBtn && addModal) {
        openAddModalBtn.addEventListener("click", function () {
            switchScrapModalTab("general");
            openModal(addModal);
            if (assetSelect && assetSelect.value) {
                assetSelect.dispatchEvent(new Event("change"));
            }
        });
    }

    if (closeAddModalBtn && addModal) {
        closeAddModalBtn.addEventListener("click", function () {
            closeModal(addModal);
        });
    }

    if (cancelAddModalBtn && addModal) {
        cancelAddModalBtn.addEventListener("click", function () {
            closeModal(addModal);
        });
    }

    if (closeSlipModalBtn && slipModal) {
        closeSlipModalBtn.addEventListener("click", function () {
            closeModal(slipModal);
        });
    }

    if (closeSlipModalFooterBtn && slipModal) {
        closeSlipModalFooterBtn.addEventListener("click", function () {
            closeModal(slipModal);
        });
    }

    // Close on overlay / backdrop click
    window.addEventListener("click", function (e) {
        if (e.target === addModal) closeModal(addModal);
        if (e.target === slipModal || e.target === slipBackdrop) closeModal(slipModal);
    });

    // Escape key handler
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeModal(addModal);
            closeModal(slipModal);
        }
    });

    // 5. Dynamic Tab Counts Recalculation
    function updateDynamicTabCounts() {
        if (!table) return;
        const rows = table.querySelectorAll("tbody tr");
        const counts = {
            all: 0,
            yard: 0,
            recycled: 0,
            sold: 0,
            "written-off": 0
        };

        let totalQty = 0;
        let yardQty = 0;
        let recycledQty = 0;
        let totalVal = 0;

        rows.forEach(r => {
            if (r.id === "noRecordsRow") return;
            const st = r.getAttribute("data-status");
            counts.all++;
            totalQty++;
            if (counts[st] !== undefined) {
                counts[st]++;
            }

            if (st === "yard") yardQty++;
            if (st === "recycled") recycledQty++;

            const valBtn = r.querySelector(".view-slip-btn");
            if (valBtn) {
                const val = parseFloat(valBtn.getAttribute("data-value") || "0");
                if (!isNaN(val)) totalVal += val;
            }
        });

        const tabAll = document.getElementById("tabCountAll");
        const tabYard = document.getElementById("tabCountYard");
        const tabRecycled = document.getElementById("tabCountRecycled");
        const tabSold = document.getElementById("tabCountSold");
        const tabWritten = document.getElementById("tabCountWrittenOff");

        if (tabAll) tabAll.textContent = counts.all;
        if (tabYard) tabYard.textContent = counts.yard;
        if (tabRecycled) tabRecycled.textContent = counts.recycled;
        if (tabSold) tabSold.textContent = counts.sold;
        if (tabWritten) tabWritten.textContent = counts["written-off"];

        const kpiTotal = document.getElementById("kpiTotalAssets");
        const kpiYard = document.getElementById("kpiYardAssets");
        const kpiRecycled = document.getElementById("kpiRecycledAssets");
        const kpiSalvage = document.getElementById("kpiSalvageVal");

        if (kpiTotal) kpiTotal.textContent = totalQty;
        if (kpiYard) kpiYard.textContent = yardQty;
        if (kpiRecycled) kpiRecycled.textContent = recycledQty;
        if (kpiSalvage) kpiSalvage.textContent = "₹" + Number(totalVal).toLocaleString();
    }

    // 6. Form Submission (Record Scrap Asset UI)
    if (scrapForm) {
        scrapForm.addEventListener("submit", function (e) {
            e.preventDefault();

            if (!assetSelect || !assetSelect.value) {
                if (typeof showToast === "function") showToast("Please select a hardware asset to scrap.", "warning");
                if (assetSelect) assetSelect.focus();
                return;
            }

            const selectedOpt = assetSelect.options[assetSelect.selectedIndex];
            const assetName = selectedOpt.getAttribute("data-name") || "Hardware Asset";
            const assetTag = selectedOpt.getAttribute("data-tag") || "AST-0000";
            const serialNo = selectedOpt.getAttribute("data-serial") || "N/A";
            const category = selectedOpt.getAttribute("data-category") || "General Asset";
            const brand = selectedOpt.getAttribute("data-brand") || "";
            const model = selectedOpt.getAttribute("data-model") || "";
            const location = selectedOpt.getAttribute("data-location") || "HQ Office";

            const reason = document.getElementById("scrapReason").value || "Hardware Defective";
            const statusLabel = document.getElementById("scrapStatus").value || "In Scrap Yard";
            const salvageVal = parseFloat(document.getElementById("scrapValue").value || "0");
            const yardLocation = document.getElementById("scrapYardLocation").value || "Yard Bay A-01";
            const approver = document.getElementById("scrapApprover").value || "Abhishek Sharma";
            const notes = document.getElementById("scrapNotes") ? document.getElementById("scrapNotes").value : "";

            // Normalize status key
            let statusKey = "yard";
            if (statusLabel.toLowerCase().includes("recycled")) statusKey = "recycled";
            else if (statusLabel.toLowerCase().includes("sold")) statusKey = "sold";
            else if (statusLabel.toLowerCase().includes("written")) statusKey = "written-off";

            // Gather Installed Components Disposition
            const compDisposition = [];
            if (installedComponentsList) {
                const compRows = installedComponentsList.querySelectorAll(".comp-scrap-item");
                compRows.forEach(row => {
                    const cId = parseInt(row.getAttribute("data-comp-id") || "0", 10);
                    const serial = row.getAttribute("data-serial") || "";
                    const sku = row.getAttribute("data-sku") || "";
                    const name = row.getAttribute("data-name") || "Component";
                    const cat = row.getAttribute("data-category") || "Component";
                    const selectedRadio = row.querySelector('input[type="radio"]:checked');
                    const action = selectedRadio ? selectedRadio.value : "scrap";

                    compDisposition.push({
                        component_id: cId,
                        id: cId,
                        serial: serial,
                        sku: sku,
                        name: name,
                        category: cat,
                        action: action
                    });
                });
            }

            // Generate next Ref ID fallback
            const now = new Date();
            const mm = String(now.getMonth() + 1).padStart(2, "0");
            const yy = String(now.getFullYear()).slice(-2);
            const randomSerial = String(Math.floor(100 + Math.random() * 900));
            const fallbackRefId = `SCR${mm}${yy}${randomSerial}`;
            const dateStr = now.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });

            const saveBtn = document.getElementById("saveScrapBtn");
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = "Processing...";
            }

            function insertRowAndFinish(refId) {
                // Insert into table
                const tbody = table ? table.querySelector("tbody") : null;
                if (tbody) {
                    const noRecordsRow = tbody.querySelector("#noRecordsRow");
                    if (noRecordsRow) noRecordsRow.remove();

                    const tr = document.createElement("tr");
                    tr.setAttribute("data-status", statusKey);
                    tr.setAttribute("data-reason", reason);
                    tr.setAttribute("data-category", category);
                    const brandModelStr = [brand, model].filter(Boolean).join(" ");

                    tr.innerHTML = `
                        <td>
                            <span class="ref-code">${escapeHtml(refId)}</span>
                        </td>
                        <td>
                            <div>
                                <strong class="asset-name-title">${escapeHtml(assetName)}</strong>
                                <div style="margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                                    <span class="asset-tag-badge">${escapeHtml(assetTag)}</span>
                                    ${model ? `<span style="font-size: 11px; color: var(--text-secondary);">${escapeHtml(model)}</span>` : ""}
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="category-pill">${escapeHtml(category)}</span>
                        </td>
                        <td>
                            <span class="serial-badge">${escapeHtml(serialNo)}</span>
                        </td>
                        <td>
                            <span class="scrap-status-badge status-${statusKey}">${escapeHtml(statusLabel)}</span>
                        </td>
                        <td>
                            <strong style="color: var(--navy-deep);">₹${Number(salvageVal).toLocaleString()}</strong>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 12.5px; white-space: nowrap;">
                            ${dateStr}
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                <button type="button" class="action-icon-btn btn-view view-slip-btn" 
                                        data-ref="${escapeHtml(refId)}" data-name="${escapeHtml(assetName)}" data-tag="${escapeHtml(assetTag)}" 
                                        data-serial="${escapeHtml(serialNo)}" data-category="${escapeHtml(category)}" 
                                        data-brand="${escapeHtml(brand)}" data-model="${escapeHtml(model)}" 
                                        data-location="${escapeHtml(location)}" data-reason="${escapeHtml(reason)}" 
                                        data-yard="${escapeHtml(yardLocation)}" data-status="${escapeHtml(statusLabel)}" 
                                        data-value="${salvageVal}" data-approver="${escapeHtml(approver)}" 
                                        data-notes="${escapeHtml(notes)}" data-date="${dateStr}"
                                        data-components="${escapeHtml(JSON.stringify(compDisposition))}"
                                        title="View Decommission Details & Slip">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <button type="button" class="action-icon-btn btn-qr direct-print-slip-btn" 
                                        data-ref="${escapeHtml(refId)}" data-name="${escapeHtml(assetName)}" data-tag="${escapeHtml(assetTag)}" 
                                        data-serial="${escapeHtml(serialNo)}" data-category="${escapeHtml(category)}" 
                                        data-brand="${escapeHtml(brand)}" data-model="${escapeHtml(model)}" 
                                        data-location="${escapeHtml(location)}" data-reason="${escapeHtml(reason)}" 
                                        data-yard="${escapeHtml(yardLocation)}" data-status="${escapeHtml(statusLabel)}" 
                                        data-value="${salvageVal}" data-approver="${escapeHtml(approver)}" 
                                        data-notes="${escapeHtml(notes)}" data-date="${dateStr}"
                                        data-components="${escapeHtml(JSON.stringify(compDisposition))}"
                                        title="Print Official Scrap Slip">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    `;

                    tbody.insertBefore(tr, tbody.firstChild);

                    // Attach events
                    const newViewBtn = tr.querySelector(".view-slip-btn");
                    if (newViewBtn) {
                        newViewBtn.addEventListener("click", function () {
                            populateAndOpenSlip(this);
                        });
                    }

                    const newPrintBtn = tr.querySelector(".direct-print-slip-btn");
                    if (newPrintBtn) {
                        newPrintBtn.addEventListener("click", function () {
                            directPrintAssetSlip(extractSlipDataFromBtn(this));
                        });
                    }

                    updateDynamicTabCounts();
                }

                // Remove selected asset option from dropdown
                if (selectedOpt) {
                    selectedOpt.remove();
                }

                closeModal(addModal);
                scrapForm.reset();
                switchScrapModalTab("general");
                renderInstalledComponents([]);
                if (assetTagInput) assetTagInput.value = "";
                if (assetSerialInput) assetSerialInput.value = "";
                if (assetCategoryInput) assetCategoryInput.value = "";
                if (assetLocationInput) assetLocationInput.value = "";
                if (installedComponentsSection) installedComponentsSection.style.display = "none";

                const partsNotice = compDisposition.length > 0 ? ` with ${compDisposition.length} component parts processed` : "";
                if (typeof showToast === "function") {
                    showToast(`Asset scrap record ${refId} created successfully${partsNotice}!`, "success");
                } else {
                    alert(`Asset scrap record ${refId} created successfully${partsNotice}!`);
                }
            }

            const payload = {
                action: "create",
                asset_id: parseInt(selectedOpt.value || "0", 10),
                asset_tag: assetTag,
                asset_name: assetName,
                category: category,
                brand: brand,
                model: model,
                serial_no: serialNo,
                origin_location: location,
                reason: reason,
                disposal_status: statusLabel,
                yard_location: yardLocation,
                salvage_value: salvageVal,
                authorized_by: approver,
                notes: notes,
                components_disposition: compDisposition
            };

            const isPreview = window.location.search.includes("preview");
            const apiUrl = "api/scrap_assets.php?action=create" + (isPreview ? "&preview=1" : "");

            fetch(apiUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(resData => {
                if (resData.success && resData.data && resData.data.ref) {
                    insertRowAndFinish(resData.data.ref);
                } else if (resData.success) {
                    insertRowAndFinish(fallbackRefId);
                } else {
                    if (typeof showToast === "function") {
                        showToast(resData.message || "Failed to record scrap asset.", "danger");
                    } else {
                        alert(resData.message || "Failed to record scrap asset.");
                    }
                }
            })
            .catch(err => {
                console.error("Scrap API error:", err);
                insertRowAndFinish(fallbackRefId);
            })
            .finally(() => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = "Confirm Scrap Asset";
                }
            });
        });
    }

    // 7. Extract Data From Button & Populate Slip
    function extractSlipDataFromBtn(btn) {
        return {
            ref: btn.getAttribute("data-ref") || "",
            name: btn.getAttribute("data-name") || "",
            tag: btn.getAttribute("data-tag") || "",
            serial: btn.getAttribute("data-serial") || "N/A",
            category: btn.getAttribute("data-category") || "",
            brand: btn.getAttribute("data-brand") || "",
            model: btn.getAttribute("data-model") || "",
            location: btn.getAttribute("data-location") || "",
            reason: btn.getAttribute("data-reason") || "",
            yard: btn.getAttribute("data-yard") || "",
            status_text: btn.getAttribute("data-status") || "",
            salvage_val: btn.getAttribute("data-value") || "0",
            approver: btn.getAttribute("data-approver") || "",
            notes: btn.getAttribute("data-notes") || "",
            date: btn.getAttribute("data-date") || "",
            components: btn.getAttribute("data-components") || ""
        };
    }

    function populateAndOpenSlip(dataOrBtn) {
        const data = (dataOrBtn instanceof Element) ? extractSlipDataFromBtn(dataOrBtn) : dataOrBtn;
        currentSlipData = data;

        const refEl = document.getElementById("slipRefCode");
        if (refEl) refEl.textContent = data.ref;
        const drawerRef = document.getElementById("drawerSlipRef");
        if (drawerRef) drawerRef.textContent = data.ref;

        const dateEl = document.getElementById("slipDate");
        if (dateEl) dateEl.textContent = data.date;
        const drawerDate = document.getElementById("drawerSlipDate");
        if (drawerDate) drawerDate.textContent = data.date;

        const tagEl = document.getElementById("slipAssetTag");
        if (tagEl) tagEl.textContent = data.tag;

        const nameEl = document.getElementById("slipAssetName");
        if (nameEl) nameEl.textContent = data.name;

        const catEl = document.getElementById("slipCategory");
        if (catEl) catEl.textContent = data.category;

        const brandModelEl = document.getElementById("slipBrandModel");
        if (brandModelEl) {
            brandModelEl.textContent = [data.brand, data.model].filter(Boolean).join(" ") || "Standard Model";
        }

        const serialEl = document.getElementById("slipSerial");
        if (serialEl) serialEl.textContent = data.serial;

        const locEl = document.getElementById("slipOriginLocation");
        if (locEl) locEl.textContent = data.location;

        const statusEl = document.getElementById("slipStatusText");
        if (statusEl) statusEl.textContent = data.status_text;

        const yardEl = document.getElementById("slipYardLocation");
        if (yardEl) yardEl.textContent = data.yard;

        const salvageEl = document.getElementById("slipSalvageVal");
        if (salvageEl) {
            salvageEl.textContent = "₹" + Number(data.salvage_val).toLocaleString();
        }

        const reasonEl = document.getElementById("slipReason");
        if (reasonEl) reasonEl.textContent = data.reason;

        const approverEl = document.getElementById("slipApprover");
        if (approverEl) approverEl.textContent = data.approver;

        const notesEl = document.getElementById("slipNotesText");
        if (notesEl) notesEl.textContent = data.notes;

        // Populate attached components in slip
        const compBox = document.getElementById("slipComponentsBox");
        const compList = document.getElementById("slipComponentsList");
        const compCount = document.getElementById("slipComponentsCount");
        if (compBox && compList) {
            let comps = [];
            if (data.components) {
                try {
                    comps = typeof data.components === "string" ? JSON.parse(data.components) : data.components;
                } catch (e) {
                    comps = [];
                }
            }
            if (Array.isArray(comps) && comps.length > 0) {
                compBox.style.display = "block";
                if (compCount) compCount.textContent = comps.length + " Part" + (comps.length > 1 ? "s" : "");
                compList.innerHTML = comps.map(c => `
                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; padding: 4px 8px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <div>
                            <strong style="color: #0f172a;">${escapeHtml(c.name || "Hardware Component")}</strong>
                            ${c.serial ? `<span style="font-family: monospace; color: #64748b; margin-left: 6px;">SN: ${escapeHtml(c.serial)}</span>` : ""}
                        </div>
                        <span style="font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 10px; ${c.action === 'scrap' ? 'background: #fee2e2; color: #b91c1c;' : 'background: #dcfce7; color: #15803d;'}">
                            ${c.action === 'scrap' ? 'Scrapped with Asset' : 'Salvaged to Spare Stock'}
                        </span>
                    </div>
                `).join("");
            } else {
                compBox.style.display = "none";
                compList.innerHTML = "";
            }
        }

        openModal(slipModal);
    }

    // Attach click events to initial View Slip buttons
    document.querySelectorAll(".view-slip-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            populateAndOpenSlip(this);
        });
    });

    // 8. Standalone A4 Corporate Print Engine
    function directPrintAssetSlip(data) {
        if (!data) return;

        const orgName = window.ORG_NAME || "VIROS IT PORTAL";
        const orgLogo = window.ORG_LOGO || "assets/images/logo.png";

        const slipNo = escapeHtml(data.ref || "");
        const slipDate = escapeHtml(data.date || new Date().toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" }));
        const assetName = escapeHtml(data.name || "");
        const assetTag = escapeHtml(data.tag || "");
        const serialNo = escapeHtml(data.serial || "N/A");
        const category = escapeHtml(data.category || "");
        const brandModel = escapeHtml([data.brand, data.model].filter(Boolean).join(" ") || "");
        const location = escapeHtml(data.location || "");
        const reason = escapeHtml(data.reason || "");
        const yard = escapeHtml(data.yard || "");
        const statusText = escapeHtml(data.status_text || "In Scrap Yard");
        const salvageVal = Number(data.salvage_val || 0).toLocaleString();
        const approver = escapeHtml(data.approver || "Abhishek Sharma");
        const notes = escapeHtml(data.notes || "Asset evaluated, found beyond economic repair, decommissioned from active asset register.");

        let printComps = [];
        if (data.components) {
            try {
                printComps = typeof data.components === "string" ? JSON.parse(data.components) : data.components;
            } catch (e) {
                printComps = [];
            }
        }

        let compPrintHtml = "";
        if (Array.isArray(printComps) && printComps.length > 0) {
            compPrintHtml = `
            <div class="sec-title">3. Attached Hardware Components Disposition (${printComps.length} Parts)</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 32px; text-align: center;">#</th>
                        <th>Component Part Name</th>
                        <th style="width: 140px;">Serial / Code</th>
                        <th style="width: 180px;">Disposition Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${printComps.map((c, idx) => `
                        <tr>
                            <td style="text-align: center;">${idx + 1}</td>
                            <td><strong>${escapeHtml(c.name || 'Component')}</strong></td>
                            <td style="font-family: monospace;">${escapeHtml(c.serial || c.sku || '-')}</td>
                            <td>
                                ${c.action === 'salvage' 
                                    ? '<span style="color: #0284c7; font-weight: 700;">Salvaged to Stock (Detached)</span>' 
                                    : '<span style="color: #b91c1c; font-weight: 700;">Scrapped with Host Asset</span>'}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>`;
        }

        const printHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Asset Scrap Slip - ${slipNo}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #0f172a;
            background: #ffffff;
        }
        .slip-container {
            width: 100%;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 9px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #334155;
            padding: 5px 8px;
            vertical-align: middle;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 700;
            text-align: left;
            font-size: 10.5px;
            color: #0f172a;
        }
        .header-tbl {
            border: none !important;
            margin-bottom: 14px;
        }
        .header-tbl td {
            border: none !important;
            padding: 4px 8px;
        }
        .header-logo {
            width: 22%;
            text-align: left;
            vertical-align: middle;
            border: none !important;
        }
        .header-logo img {
            max-height: 72px;
            max-width: 180px;
            object-fit: contain;
            display: block;
        }
        .header-title {
            text-align: center;
            vertical-align: middle;
        }
        .header-title h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #001938;
            text-transform: uppercase;
        }
        .header-title .sub {
            font-size: 11.5px;
            font-weight: 600;
            color: #475569;
            margin-top: 2px;
        }
        .header-title .doc-name {
            font-size: 12.5px;
            font-weight: 800;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            border-top: 1.5px solid #001938;
            padding-top: 2px;
            color: #0093A7;
        }
        .header-meta {
            width: 28%;
            border: none !important;
            font-size: 10.5px;
            line-height: 1.55;
            vertical-align: middle;
            text-align: right;
            background: transparent !important;
        }
        .sec-title {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            padding: 4px 8px;
            border: 1px solid #334155;
            border-bottom: none;
            margin-top: 9px;
            color: #0f172a;
            letter-spacing: 0.3px;
        }
        .lbl {
            background: #f8fafc;
            font-weight: 600;
            color: #334155;
            width: 22%;
        }
        .val {
            color: #0f172a;
            width: 28%;
        }
        .declaration-box {
            border: 1px solid #334155;
            padding: 8px 10px;
            font-size: 10px;
            color: #334155;
            background: #f8fafc;
            margin-top: 9px;
            line-height: 1.45;
        }
        .signatures-tbl {
            border: 1px solid #334155;
            border-top: none;
            margin-top: 0;
            margin-bottom: 0;
        }
        .signatures-tbl td {
            border: none;
            padding: 38px 14px 10px;
            vertical-align: bottom;
        }
        .sig-line {
            border-top: 1.5px solid #0f172a;
            padding-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .sig-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
            line-height: 1.35;
        }
    </style>
</head>
<body>
    <div class="slip-container">
        <!-- Document Header Table -->
        <table class="header-tbl">
            <tr>
                <td class="header-logo">
                    <img src="${escapeHtml(orgLogo)}" alt="${escapeHtml(orgName)} Logo">
                </td>
                <td class="header-title">
                    <h1>${escapeHtml(orgName)}</h1>
                    <div class="sub">IT Asset Management &amp; Helpdesk</div>
                    <div class="doc-name">ASSET SCRAP &amp; DECOMMISSION DISPOSAL SLIP</div>
                </td>
                <td class="header-meta">
                    <div><strong>Slip No:</strong> ${slipNo}</div>
                    <div><strong>Date:</strong> ${slipDate}</div>
                    <div><strong>Disposition:</strong> Asset Decommission</div>
                    <div><strong>Status:</strong> ${statusText}</div>
                </td>
            </tr>
        </table>

        <!-- 1. Decommissioned Asset Details -->
        <div class="sec-title">1. Decommissioned Hardware Asset Details</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;">#</th>
                    <th>Asset Name &amp; Description</th>
                    <th style="width: 120px;">Asset Tag</th>
                    <th style="width: 130px;">Serial Number</th>
                    <th style="width: 110px;">Category</th>
                    <th style="width: 130px;">Origin Branch</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td><strong>${assetName}</strong>${brandModel ? ` <div style="font-size: 10px; color: #475569;">${brandModel}</div>` : ''}</td>
                    <td style="font-family: monospace; font-weight: 700; color: #1d4ed8;">${assetTag}</td>
                    <td style="font-family: monospace; font-weight: 700;">${serialNo}</td>
                    <td>${category}</td>
                    <td>${location}</td>
                </tr>
            </tbody>
        </table>

        <!-- 2. Technical Diagnostics & Disposal Target -->
        <div class="sec-title">2. Technical Diagnostics &amp; Disposal Target</div>
        <table>
            <tr>
                <td class="lbl">Defect / Condemnation Reason</td>
                <td class="val" style="color: #b91c1c; font-weight: 600;">${reason}</td>
                <td class="lbl">Storage / Yard Bay</td>
                <td class="val">${yard}</td>
            </tr>
            <tr>
                <td class="lbl">Disposal Method / Target</td>
                <td class="val" style="font-weight: 700; color: #b45309;">${statusText}</td>
                <td class="lbl">Estimated Salvage Realization</td>
                <td class="val" style="font-weight: 700; color: #0284c7;">₹${salvageVal}</td>
            </tr>
            <tr>
                <td class="lbl">Authorized By</td>
                <td class="val" style="font-weight: 700;">${approver}</td>
                <td class="lbl">Decommission Date</td>
                <td class="val">${slipDate}</td>
            </tr>
        </table>

        <!-- 3. Attached Hardware Components Disposition -->
        ${compPrintHtml}

        <!-- 4. Diagnostic Findings & Technical Authorization -->
        <div class="sec-title">4. Technical Inspection &amp; Assessment Notes</div>
        <table>
            <tr>
                <td class="lbl" style="width: 22%;">Technical Findings</td>
                <td class="val" colspan="3" style="width: 78%;">${notes}</td>
            </tr>
        </table>

        <!-- 5. Declaration & Compliance -->
        <div class="declaration-box">
            <strong>Official Decommission Certification:</strong> I hereby certify that the IT hardware asset listed above has undergone technical inspection and diagnostic testing. It is confirmed beyond economic repair (BER), obsolete, or critically defective. The asset has been decommissioned from active corporate IT inventory, security wiped/degaussed where applicable, and handed over for scrap disposal / e-waste recycling in compliance with electronic waste management regulations.
        </div>

        <!-- 6. Tripartite Signatures -->
        <table class="signatures-tbl">
            <tr>
                <td style="width: 34%;">
                    <div class="sig-line">IT Asset Custodian</div>
                    <div class="sig-sub">Device Handed Over<br>Signature: ________________<br>Date: ________________</div>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="sig-line">Technical Inspector</div>
                    <div class="sig-sub">Defect Certified<br>Signature: ________________<br>Date: ________________</div>
                </td>
                <td style="width: 33%; text-align: right;">
                    <div class="sig-line">Authorized Disposal Officer / IT Head</div>
                    <div class="sig-sub">${approver}<br>IT Asset Dept &bull; Date: ________________</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>`;

        let printIframe = document.getElementById('scrapAssetSlipPrintIframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'scrapAssetSlipPrintIframe';
            printIframe.style.position = 'fixed';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            printIframe.style.width = '0';
            printIframe.style.height = '0';
            printIframe.style.border = '0';
            printIframe.style.visibility = 'hidden';
            document.body.appendChild(printIframe);
        }

        const frameDoc = printIframe.contentWindow.document;
        frameDoc.open();
        frameDoc.write(printHtml);
        frameDoc.close();

        setTimeout(() => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        }, 200);
    }

    // Attach click events to Direct Print buttons in table rows
    document.querySelectorAll(".direct-print-slip-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            directPrintAssetSlip(extractSlipDataFromBtn(this));
        });
    });

    if (printSlipBtn) {
        printSlipBtn.addEventListener("click", function () {
            if (currentSlipData) {
                directPrintAssetSlip(currentSlipData);
            } else {
                window.print();
            }
        });
    }

    // 9. CSV Export Feature
    if (exportBtn) {
        exportBtn.addEventListener("click", function () {
            if (!table) return;
            const rows = table.querySelectorAll("tr");
            let csvContent = "";

            rows.forEach((row, rowIndex) => {
                const cols = row.querySelectorAll("th, td");
                const rowData = [];

                cols.forEach((col, colIndex) => {
                    // Skip action column
                    if (colIndex === cols.length - 1) return;
                    let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    text = text.replace(/"/g, '""');
                    rowData.push(`"${text}"`);
                });

                if (rowData.length > 0) {
                    csvContent += rowData.join(",") + "\r\n";
                }
            });

            const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", `scrap_assets_report_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = "hidden";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            if (typeof showToast === "function") {
                showToast("Scrap Assets CSV report downloaded.", "success");
            }
        });
    }
});
