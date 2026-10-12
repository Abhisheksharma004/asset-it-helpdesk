/**
 * Scrap Parts & Components Interactive UI Handler
 * VIROS IT Asset & Helpdesk Portal
 */

document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("scrapSearchInput");
    const reasonFilter = document.getElementById("scrapReasonFilter");
    const table = document.getElementById("scrapTable");
    const tabButtons = document.querySelectorAll(".scrap-tab-btn");
    
    let activeTab = "all";

    // 1. Live Table Search & Filter
    function filterTable() {
        if (!table) return;
        const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const selectedReason = reasonFilter ? reasonFilter.value.toLowerCase().trim() : "";
        const rows = table.querySelectorAll("tbody tr");

        rows.forEach(function (row) {
            if (row.id === "noRecordsRow") return;
            const rowText = row.textContent.toLowerCase();
            const rowStatus = (row.getAttribute("data-status") || "").toLowerCase().trim();
            const rowReason = (row.getAttribute("data-reason") || "").toLowerCase().trim();

            const matchesQuery = !query || rowText.includes(query);
            const matchesReason = !selectedReason || rowReason.includes(selectedReason);
            const matchesTab = (activeTab === "all") || (rowStatus === activeTab.toLowerCase());

            if (matchesQuery && matchesReason && matchesTab) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener("input", filterTable);
    }

    if (reasonFilter) {
        reasonFilter.addEventListener("change", filterTable);
    }

    // 2. Tab Navigation & Dynamic Record Counts
    function updateDynamicTabCounts() {
        if (!table) return;
        const rows = table.querySelectorAll("tbody tr");
        let total = 0;
        let yard = 0;
        let recycled = 0;
        let sold = 0;
        let writtenOff = 0;

        rows.forEach(function (row) {
            if (row.id === "noRecordsRow" || !row.hasAttribute("data-status")) return;
            total++;
            const st = (row.getAttribute("data-status") || "").toLowerCase().trim();
            if (st === "yard") yard++;
            else if (st === "recycled") recycled++;
            else if (st === "sold") sold++;
            else if (st === "written-off" || st === "written") writtenOff++;
        });

        const elAll = document.getElementById("tabCountAll");
        const elYard = document.getElementById("tabCountYard");
        const elRecycled = document.getElementById("tabCountRecycled");
        const elSold = document.getElementById("tabCountSold");
        const elWrittenOff = document.getElementById("tabCountWrittenOff");

        if (elAll) elAll.textContent = total;
        if (elYard) elYard.textContent = yard;
        if (elRecycled) elRecycled.textContent = recycled;
        if (elSold) elSold.textContent = sold;
        if (elWrittenOff) elWrittenOff.textContent = writtenOff;
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            tabButtons.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            activeTab = this.getAttribute("data-tab") || "all";
            filterTable();
        });
    });

    // Initial count calculation
    updateDynamicTabCounts();

    // 3. Record Scrap Component Modal Logic
    const openAddModalBtn = document.getElementById("openRecordScrapModalBtn");
    const addModal = document.getElementById("recordScrapModal");
    const closeAddModalBtn = document.getElementById("closeRecordScrapModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelRecordScrapModalBtn");
    const scrapForm = document.getElementById("recordScrapForm");
    const compSelect = document.getElementById("scrapCompSelect");
    const serialInput = document.getElementById("scrapSerial");
    const hostAssetInput = document.getElementById("scrapHostAsset");

    const slipBackdrop = document.getElementById("scrapSlipBackdrop");
    const closeSlipModalFooterBtn = document.getElementById("closeSlipModalFooterBtn");

    function openModal(m) {
        if (!m) return;
        m.classList.add("active");
        m.classList.add("open");
        if (m === slipModal && slipBackdrop) {
            slipBackdrop.classList.add("open");
        }
        document.body.style.overflow = "hidden";
    }

    function closeModal(m) {
        if (!m) return;
        m.classList.remove("active");
        m.classList.remove("open");
        if (m === slipModal && slipBackdrop) {
            slipBackdrop.classList.remove("open");
        }
        document.body.style.overflow = "";
    }

    // Auto populate serial and host asset on component selection
    if (compSelect) {
        compSelect.addEventListener("change", function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt && this.value) {
                if (serialInput) serialInput.value = selectedOpt.getAttribute("data-serial") || "N/A";
                if (hostAssetInput) hostAssetInput.value = selectedOpt.getAttribute("data-host") || "Standalone Spare";
            } else {
                if (serialInput) serialInput.value = "";
                if (hostAssetInput) hostAssetInput.value = "";
            }
        });
    }

    if (openAddModalBtn && addModal) {
        openAddModalBtn.addEventListener("click", function () {
            openModal(addModal);
            if (compSelect && compSelect.value) {
                compSelect.dispatchEvent(new Event("change"));
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

    // 4. View Slip Modal Logic
    const slipModal = document.getElementById("scrapSlipModal");
    const closeSlipModalBtn = document.getElementById("closeSlipModalBtn");

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

    // 5. Form Submit (Database Persistence & Component Status Update)
    if (scrapForm) {
        scrapForm.addEventListener("submit", function (e) {
            e.preventDefault();

            if (!compSelect || !compSelect.value) {
                if (typeof showToast === "function") showToast("Please select a hardware component to scrap.", "warning");
                if (compSelect) compSelect.focus();
                return;
            }

            const selectedOpt = compSelect.options[compSelect.selectedIndex];
            const componentId = parseInt(compSelect.value, 10);
            const hostAsset = hostAssetInput ? hostAssetInput.value : (selectedOpt.getAttribute("data-host") || "Standalone Spare");
            const reason = document.getElementById("scrapReason").value || "Hardware Defective";
            const statusLabel = document.getElementById("scrapStatus").value || "In Scrap Yard";
            const salvageVal = parseFloat(document.getElementById("scrapValue").value || "0");
            const binLocation = document.getElementById("scrapLocation").value || "Bin C-01 (Component Yard)";
            const approver = document.getElementById("scrapApprover").value || "Abhishek Sharma";
            const notes = document.getElementById("scrapNotes") ? document.getElementById("scrapNotes").value : "";

            const saveBtn = document.getElementById("saveScrapBtn");
            const originalBtnText = saveBtn ? saveBtn.textContent : "Confirm Scrap Entry";
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = "Saving to Database...";
            }

            const payload = {
                action: 'create',
                component_id: componentId,
                reason: reason,
                bin_location: binLocation,
                disposal_status: statusLabel,
                salvage_value: salvageVal,
                authorized_by: approver,
                notes: notes,
                host_asset: hostAsset
            };

            fetch('api/scrap_components.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(result => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalBtnText;
                }

                if (!result.success) {
                    if (typeof showToast === "function") {
                        showToast(result.message || "Failed to record scrap component.", "error");
                    } else {
                        alert(result.message || "Failed to record scrap component.");
                    }
                    return;
                }

                const rec = result.data;
                const refId = rec.ref;
                const compName = rec.name;
                const sku = rec.sku;
                const serialNo = rec.serial;
                const category = rec.category;
                const recHostAsset = rec.host_asset;
                const recReason = rec.reason;
                const recBinLocation = rec.location;
                const statusKey = rec.status;
                const recStatusLabel = rec.status_text;
                const recSalvageVal = rec.salvage_val;
                const recApprover = rec.approver;
                const dateStr = rec.date;
                const recNotes = rec.notes || "Component decommissioned and struck off from inventory.";

                // Insert into table
                const tbody = table ? table.querySelector("tbody") : null;
                if (tbody) {
                    const noRecordsRow = tbody.querySelector("#noRecordsRow");
                    if (noRecordsRow) {
                        noRecordsRow.remove();
                    }

                    const tr = document.createElement("tr");
                    tr.setAttribute("data-status", statusKey);
                    tr.setAttribute("data-reason", recReason);
                    tr.style.borderBottom = "1px solid var(--border-color)";

                    const isStandalone = recHostAsset.toLowerCase().includes("standalone");

                    tr.innerHTML = `
                        <td style="padding: 12px 14px;"><span class="ref-code">${escapeHtml(refId)}</span></td>
                        <td style="padding: 12px 14px;">
                            <strong style="color: var(--navy-deep);">${escapeHtml(compName)}</strong>
                            <div style="margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                                <span class="sku-badge">${escapeHtml(sku)}</span>
                                <span style="font-size: 11px; color: var(--text-secondary);">${escapeHtml(category)}</span>
                            </div>
                        </td>
                        <td style="padding: 12px 14px;"><span class="serial-badge">${escapeHtml(serialNo)}</span></td>
                        <td style="padding: 12px 14px;">
                            <span class="host-asset-tag ${isStandalone ? 'standalone' : ''}">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                    <line x1="8" y1="21" x2="16" y2="21"></line>
                                    <line x1="12" y1="17" x2="12" y2="21"></line>
                                </svg>
                                ${escapeHtml(recHostAsset)}
                            </span>
                        </td>
                        <td style="padding: 12px 14px;"><span class="reason-tag">${escapeHtml(recReason)}</span></td>
                        <td style="padding: 12px 14px; color: var(--text-secondary); font-size: 12.5px;">${escapeHtml(recBinLocation)}</td>
                        <td style="padding: 12px 14px;">
                            <span class="scrap-status-badge status-${statusKey}">${escapeHtml(recStatusLabel)}</span>
                        </td>
                        <td style="padding: 12px 14px; font-weight: 600; color: var(--navy-deep);">₹${Number(recSalvageVal).toLocaleString()}</td>
                        <td style="padding: 12px 14px; font-size: 12px; color: var(--text-secondary);">
                            <div>${dateStr}</div>
                            <div style="font-size: 11px; opacity: 0.8;">By ${escapeHtml(recApprover)}</div>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                            <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                <button type="button" class="action-icon-btn btn-view view-slip-btn" 
                                        data-ref="${escapeHtml(refId)}" data-name="${escapeHtml(compName)}" data-sku="${escapeHtml(sku)}" 
                                        data-serial="${escapeHtml(serialNo)}" data-category="${escapeHtml(category)}" 
                                        data-host="${escapeHtml(recHostAsset)}" data-qty="1" data-reason="${escapeHtml(recReason)}" 
                                        data-location="${escapeHtml(recBinLocation)}" data-status="${escapeHtml(recStatusLabel)}" 
                                        data-value="${recSalvageVal}" data-approver="${escapeHtml(recApprover)}" 
                                        data-notes="${escapeHtml(recNotes)}" data-date="${dateStr}"
                                        title="View Component Scrap Slip">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <button type="button" class="action-icon-btn btn-qr direct-print-slip-btn" 
                                        data-ref="${escapeHtml(refId)}" data-name="${escapeHtml(compName)}" data-sku="${escapeHtml(sku)}" 
                                        data-serial="${escapeHtml(serialNo)}" data-category="${escapeHtml(category)}" 
                                        data-host="${escapeHtml(recHostAsset)}" data-qty="1" data-reason="${escapeHtml(recReason)}" 
                                        data-location="${escapeHtml(recBinLocation)}" data-status="${escapeHtml(recStatusLabel)}" 
                                        data-value="${recSalvageVal}" data-approver="${escapeHtml(recApprover)}" 
                                        data-notes="${escapeHtml(recNotes)}" data-date="${dateStr}"
                                        title="Print Official Component Scrap Slip">
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

                    // Attach events to new buttons
                    const newViewBtn = tr.querySelector(".view-slip-btn");
                    if (newViewBtn) {
                        newViewBtn.addEventListener("click", function () {
                            populateAndOpenSlip(this);
                        });
                    }

                    const newPrintBtn = tr.querySelector(".direct-print-slip-btn");
                    if (newPrintBtn) {
                        newPrintBtn.addEventListener("click", function () {
                            directPrintComponentSlip(extractSlipDataFromBtn(this));
                        });
                    }

                    // Dynamically refresh counts
                    updateDynamicTabCounts();
                }

                // Remove the scrapped component option from dropdown so it cannot be selected again
                if (selectedOpt) {
                    selectedOpt.remove();
                }

                closeModal(addModal);
                scrapForm.reset();
                if (serialInput) serialInput.value = "";
                if (hostAssetInput) hostAssetInput.value = "";

                if (typeof showToast === "function") {
                    showToast(`Component scrap entry ${refId} recorded and status updated to 'Scrapped' in database!`, "success");
                } else {
                    alert(`Component scrap entry ${refId} recorded and status updated to 'Scrapped' in database!`);
                }
            })
            .catch(err => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalBtnText;
                }
                console.error("Scrap component submit error:", err);
                if (typeof showToast === "function") {
                    showToast("Error connecting to server. Please try again.", "error");
                } else {
                    alert("Error connecting to server. Please try again.");
                }
            });
        });
    }

    // 6. Slip Data Extraction & Modal Population
    let currentSlipData = null;

    function extractSlipDataFromBtn(btn) {
        if (!btn) return {};
        return {
            ref: btn.getAttribute("data-ref") || "SCR1026001",
            name: btn.getAttribute("data-name") || "Hardware Component",
            sku: btn.getAttribute("data-sku") || "N/A",
            serial: btn.getAttribute("data-serial") || "N/A",
            category: btn.getAttribute("data-category") || "General Component",
            host_asset: btn.getAttribute("data-host") || "Standalone Spare",
            qty: btn.getAttribute("data-qty") || "1",
            reason: btn.getAttribute("data-reason") || "Component Failure",
            condition: btn.getAttribute("data-condition") || "Beyond Economic Repair (BER)",
            location: btn.getAttribute("data-location") || "Bin C-01 (Component Yard)",
            status: btn.getAttribute("data-status") || "In Scrap Yard",
            status_text: btn.getAttribute("data-status") || "In Scrap Yard",
            salvage_val: btn.getAttribute("data-value") || "0",
            value: btn.getAttribute("data-value") || "0",
            approver: btn.getAttribute("data-approver") || "Authorized IT Officer",
            notes: btn.getAttribute("data-notes") || "Hardware evaluated beyond economic repair. Condemned and cleared for disposal.",
            date: btn.getAttribute("data-date") || new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        };
    }

    function populateAndOpenSlip(dataOrBtn) {
        const data = (dataOrBtn instanceof Element) ? extractSlipDataFromBtn(dataOrBtn) : dataOrBtn;
        currentSlipData = data;

        const refEl = document.getElementById("slipRefId");
        if (refEl) refEl.textContent = data.ref;
        const drawerRef = document.getElementById("drawerCompRef");
        if (drawerRef) drawerRef.textContent = data.ref;

        const dateEl = document.getElementById("slipDate");
        if (dateEl) dateEl.textContent = data.date;
        const drawerDate = document.getElementById("drawerCompDate");
        if (drawerDate) drawerDate.textContent = data.date;

        const dateAltEl = document.getElementById("slipDateAlt");
        if (dateAltEl) dateAltEl.textContent = data.date;

        const statusBadgeEl = document.getElementById("slipStatusBadge");
        if (statusBadgeEl) {
            statusBadgeEl.textContent = data.status_text || data.status;
        }

        const compNameEl = document.getElementById("slipCompName");
        if (compNameEl) compNameEl.textContent = data.name;

        const skuEl = document.getElementById("slipSku");
        if (skuEl) skuEl.textContent = data.sku;

        const serialEl = document.getElementById("slipSerial");
        if (serialEl) serialEl.textContent = data.serial || "N/A";

        const hostEl = document.getElementById("slipHostAsset");
        if (hostEl) hostEl.textContent = data.host_asset || "Standalone Spare";

        const qtyEl = document.getElementById("slipQty");
        if (qtyEl) qtyEl.textContent = data.qty + " Units";

        const reasonEl = document.getElementById("slipReason");
        if (reasonEl) reasonEl.textContent = data.reason;

        const conditionEl = document.getElementById("slipCondition");
        if (conditionEl) conditionEl.textContent = data.condition || "Beyond Economic Repair (BER)";

        const locEl = document.getElementById("slipLocation");
        if (locEl) locEl.textContent = data.location || "Bin C-01 (Component Yard)";

        const statusEl = document.getElementById("slipStatus");
        if (statusEl) statusEl.textContent = data.status_text || data.status;

        const valEl = document.getElementById("slipValue");
        if (valEl) {
            valEl.textContent = "₹" + Number(data.salvage_val || data.value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        const approverEl = document.getElementById("slipApprover");
        if (approverEl) approverEl.textContent = data.approver;

        const approverSubEl = document.getElementById("slipApproverSub");
        if (approverSubEl) {
            approverSubEl.innerHTML = `${escapeHtml(data.approver)}<br>IT Asset Dept &bull; Date: ________________`;
        }

        const notesEl = document.getElementById("slipNotes");
        if (notesEl) {
            notesEl.textContent = data.notes || "Hardware evaluated beyond economic repair. Condemned and cleared for disposal.";
        }

        openModal(slipModal);
    }

    // 7. Direct Browser Native Print Engine
    function directPrintComponentSlip(data) {
        if (!data) {
            if (typeof showToast === "function") showToast("Record details not found for printing.", "warning");
            return;
        }

        const slipNo = escapeHtml(data.ref || 'SCR1026001');
        const todayStr = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        const slipDate = escapeHtml(data.date || todayStr);
        const compName = escapeHtml(data.name || 'Hardware Component');
        const skuCode = escapeHtml(data.sku || 'N/A');
        const serialNo = escapeHtml(data.serial || 'N/A');
        const category = escapeHtml(data.category || 'General Component');
        const hostAsset = escapeHtml(data.host_asset || 'Standalone Spare');
        const qty = escapeHtml(String(data.qty || 1));
        const reason = escapeHtml(data.reason || 'Hardware Defective');
        const condition = escapeHtml(data.condition || 'Beyond Economic Repair (BER)');
        const binLocation = escapeHtml(data.location || 'Bin C-01 (Component Yard)');
        const statusText = escapeHtml(data.status_text || data.status || 'In Scrap Yard');
        const salvageVal = Number(data.salvage_val || data.value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const approver = escapeHtml(data.approver || 'Authorized IT Officer');
        const notes = escapeHtml(data.notes || 'Hardware evaluated beyond economic repair. Condemned and cleared for disposal.');

        const orgData = window.ORG_SETTINGS || {};
        const orgLogo = orgData.primary_brand_logo || 'assets/images/logo.png';
        const orgName = orgData.organization_legal_name || 'VIROS PORTAL';

        const printHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Component Scrap Disposal Slip - ${slipNo}</title>
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
                    <div class="doc-name">COMPONENT DISMANTLE &amp; SCRAP DISPOSAL SLIP</div>
                </td>
                <td class="header-meta">
                    <div><strong>Slip No:</strong> ${slipNo}</div>
                    <div><strong>Date:</strong> ${slipDate}</div>
                    <div><strong>Disposition:</strong> Component Decommission</div>
                    <div><strong>Status:</strong> ${statusText}</div>
                </td>
            </tr>
        </table>

        <!-- 1. Decommissioned Component Details -->
        <div class="sec-title">1. Decommissioned Hardware Component Details</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;">#</th>
                    <th>Component Name &amp; Specifications</th>
                    <th style="width: 120px;">SKU / Code</th>
                    <th style="width: 130px;">Serial Number</th>
                    <th style="width: 150px;">Dismantled From</th>
                    <th style="width: 70px; text-align: center;">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td><strong>${compName}</strong></td>
                    <td style="font-family: monospace; font-weight: 700;">${skuCode}</td>
                    <td style="font-family: monospace; font-weight: 700;">${serialNo}</td>
                    <td>${hostAsset}</td>
                    <td style="text-align: center;"><strong>${qty} Units</strong></td>
                </tr>
            </tbody>
        </table>

        <!-- 2. Technical Diagnostics & Disposal Target -->
        <div class="sec-title">2. Technical Diagnostics &amp; Disposal Target</div>
        <table>
            <tr>
                <td class="lbl">Defect / Failure Reason</td>
                <td class="val">${reason}</td>
                <td class="lbl">Hardware Condition</td>
                <td class="val">${condition}</td>
            </tr>
            <tr>
                <td class="lbl">Storage / Bin Location</td>
                <td class="val">${binLocation}</td>
                <td class="lbl">Disposal Method / Target</td>
                <td class="val">${statusText}</td>
            </tr>
            <tr>
                <td class="lbl">Estimated Salvage Realization</td>
                <td class="val" style="font-weight: 700; color: #0284c7;">₹${salvageVal}</td>
                <td class="lbl">Decommission Date</td>
                <td class="val">${slipDate}</td>
            </tr>
        </table>

        <!-- 3. Technical Inspection & Approval Authorization -->
        <div class="sec-title">3. Diagnostic Findings &amp; Technical Authorization</div>
        <table>
            <tr>
                <td class="lbl" style="width: 22%;">Authorized By</td>
                <td class="val" style="width: 28%; font-weight: 700;">${approver}</td>
                <td class="lbl" style="width: 20%;">Inspection Notes</td>
                <td class="val" style="width: 30%;">${notes}</td>
            </tr>
        </table>

        <!-- 4. Declaration & Compliance -->
        <div class="declaration-box">
            <strong>Official Decommission Certification:</strong> I hereby certify that the internal hardware component listed above has undergone technical diagnostic evaluation and is confirmed beyond economic repair (BER) or unserviceable. The item has been struck off from active inventory stock and scheduled for authorized scrap disposal / e-waste recycling in compliance with corporate electronic waste management regulations.
        </div>

        <!-- 5. Tripartite Signatures -->
        <table class="signatures-tbl">
            <tr>
                <td style="width: 34%;">
                    <div class="sig-line">Inspecting IT Technician</div>
                    <div class="sig-sub">Signature: ________________<br>Date: ________________</div>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="sig-line">Scrap Yard / Store Officer</div>
                    <div class="sig-sub">Signature: ________________<br>Date: ________________</div>
                </td>
                <td style="width: 33%; text-align: right;">
                    <div class="sig-line">Authorized IT Head / Seal</div>
                    <div class="sig-sub">${approver}<br>IT Asset Dept &bull; Date: ________________</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>`;

        // Direct Browser Print via hidden iframe
        let printIframe = document.getElementById('scrapCompSlipPrintIframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'scrapCompSlipPrintIframe';
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

        // Trigger native browser print modal
        setTimeout(() => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        }, 200);
    }

    // 8. Wire Modal Print and Row Buttons
    const printScrapSlipBtn = document.getElementById("printScrapSlipBtn");
    if (printScrapSlipBtn) {
        printScrapSlipBtn.addEventListener("click", function () {
            if (currentSlipData) {
                directPrintComponentSlip(currentSlipData);
            } else {
                window.print();
            }
        });
    }

    document.querySelectorAll(".view-slip-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            populateAndOpenSlip(this);
        });
    });

    document.querySelectorAll(".direct-print-slip-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            directPrintComponentSlip(extractSlipDataFromBtn(this));
        });
    });

    // Expose print functions globally
    window.directPrintComponentSlip = directPrintComponentSlip;
    window.openComponentSlipModal = populateAndOpenSlip;
});

// Global Export to CSV
function exportScrapToCSV() {
    const table = document.getElementById("scrapTable");
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll("tr");
    rows.forEach(function (row) {
        if (row.style.display === "none") return;
        const cols = row.querySelectorAll("th, td");
        let rowData = [];
        cols.forEach(function (col, idx) {
            if (idx === cols.length - 1) return; // skip action buttons
            rowData.push('"' + col.innerText.replace(/"/g, '""').trim() + '"');
        });
        csv.push(rowData.join(","));
    });
    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "viros_scrap_components_" + new Date().toISOString().slice(0, 10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}
