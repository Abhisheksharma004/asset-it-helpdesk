/**
 * Scrap Accessories Interactive UI Handler
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
            const rowText = row.textContent.toLowerCase();
            const rowStatus = row.getAttribute("data-status") || "";
            const rowReason = row.getAttribute("data-reason") || "";

            const matchesQuery = !query || rowText.includes(query);
            const matchesReason = !selectedReason || rowReason.toLowerCase().includes(selectedReason);
            const matchesTab = (activeTab === "all") || (rowStatus.toLowerCase() === activeTab.toLowerCase());

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

    // Initial calculation of dynamic tab counts
    updateDynamicTabCounts();


    // 3. Record Scrap Modal Logic
    const openAddModalBtn = document.getElementById("openRecordScrapModalBtn");
    const addModal = document.getElementById("recordScrapModal");
    const closeAddModalBtn = document.getElementById("closeRecordScrapModalBtn");
    const cancelAddModalBtn = document.getElementById("cancelRecordScrapModalBtn");
    const scrapForm = document.getElementById("recordScrapForm");

    const accSelect = document.getElementById("scrapAccSelect");
    const qtyInput = document.getElementById("scrapQty");
    const stockBadge = document.getElementById("availableStockBadge");
    const stockValSpan = document.getElementById("availableStockVal");
    const qtyError = document.getElementById("scrapQtyError");
    const maxStockSpan = document.getElementById("maxAllowedStock");
    const saveBtn = document.getElementById("saveScrapBtn");

    function openModal(m) {
        if (m) m.classList.add("active");
    }

    function closeModal(m) {
        if (m) m.classList.remove("active");
    }

    // Function to calculate and update available stock limits
    function updateStockLimits() {
        if (!accSelect) return;
        const selectedOpt = accSelect.options[accSelect.selectedIndex];
        const stockAttr = selectedOpt ? selectedOpt.getAttribute("data-stock") : null;

        if (!selectedOpt || !accSelect.value || stockAttr === null) {
            if (stockBadge) stockBadge.style.display = "none";
            if (qtyError) qtyError.style.display = "none";
            if (qtyInput) {
                qtyInput.removeAttribute("max");
                qtyInput.classList.remove("input-error");
                qtyInput.disabled = false;
            }
            if (saveBtn) saveBtn.disabled = false;
            return;
        }

        const availableStock = parseInt(stockAttr, 10);
        
        if (stockBadge && stockValSpan) {
            stockValSpan.textContent = availableStock;
            stockBadge.style.display = "inline-flex";
            if (availableStock <= 0) {
                stockBadge.className = "stock-badge-empty";
                stockBadge.innerHTML = 'Out of Stock: 0 pcs';
            } else {
                stockBadge.className = "stock-badge-available";
                stockBadge.innerHTML = `Available: ${availableStock} pcs`;
            }
        }

        if (maxStockSpan) {
            maxStockSpan.textContent = availableStock;
        }

        if (qtyInput) {
            qtyInput.max = availableStock;
            if (availableStock <= 0) {
                qtyInput.value = 0;
                qtyInput.disabled = true;
                qtyInput.classList.add("input-error");
                if (qtyError) {
                    qtyError.textContent = "This accessory has 0 units in stock. Cannot scrap!";
                    qtyError.style.display = "block";
                }
                if (saveBtn) saveBtn.disabled = true;
            } else {
                qtyInput.disabled = false;
                const currentVal = parseInt(qtyInput.value, 10);
                if (isNaN(currentVal) || currentVal <= 0) {
                    qtyInput.value = 1;
                } else if (currentVal > availableStock) {
                    qtyInput.value = availableStock;
                }
                validateQty();
            }
        }
    }

    // Function to strictly validate entered quantity against available stock
    function validateQty() {
        if (!qtyInput || !accSelect) return true;
        const selectedOpt = accSelect.options[accSelect.selectedIndex];
        if (!accSelect.value || !selectedOpt) return true;

        const availableStock = parseInt(selectedOpt.getAttribute("data-stock") || "0", 10);
        const enteredQty = parseInt(qtyInput.value, 10);

        if (availableStock <= 0) {
            qtyInput.classList.add("input-error");
            if (qtyError) {
                qtyError.textContent = "Cannot scrap: 0 units available in stock!";
                qtyError.style.display = "block";
            }
            if (saveBtn) saveBtn.disabled = true;
            return false;
        }

        if (isNaN(enteredQty) || enteredQty <= 0) {
            qtyInput.classList.add("input-error");
            if (qtyError) {
                qtyError.textContent = "Please enter a valid quantity of at least 1.";
                qtyError.style.display = "block";
            }
            if (saveBtn) saveBtn.disabled = true;
            return false;
        }

        if (enteredQty > availableStock) {
            qtyInput.classList.add("input-error");
            if (qtyError) {
                qtyError.textContent = `Quantity (${enteredQty}) exceeds available stock of ${availableStock} pcs!`;
                qtyError.style.display = "block";
            }
            if (saveBtn) saveBtn.disabled = true;
            return false;
        }

        // Passed validation
        qtyInput.classList.remove("input-error");
        if (qtyError) qtyError.style.display = "none";
        if (saveBtn) saveBtn.disabled = false;
        return true;
    }

    if (accSelect) {
        accSelect.addEventListener("change", updateStockLimits);
    }

    if (qtyInput) {
        qtyInput.addEventListener("input", validateQty);
        qtyInput.addEventListener("change", validateQty);
        qtyInput.addEventListener("keyup", validateQty);
    }

    if (openAddModalBtn && addModal) {
        openAddModalBtn.addEventListener("click", function () {
            openModal(addModal);
            updateStockLimits();
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

    // Close on overlay backdrop click
    window.addEventListener("click", function (e) {
        if (e.target === addModal) closeModal(addModal);
        if (e.target === slipModal) closeModal(slipModal);
    });

    // Escape key
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeModal(addModal);
            closeModal(slipModal);
        }
    });

    // 5. Form Submit (Dynamic Record Addition UI with Strict Stock Guard)
    if (scrapForm) {
        scrapForm.addEventListener("submit", function (e) {
            e.preventDefault();

            if (!accSelect || !accSelect.value) {
                if (typeof showToast === "function") showToast("Please select an accessory to scrap.", "warning");
                if (accSelect) accSelect.focus();
                return;
            }

            const selectedOpt = accSelect.options[accSelect.selectedIndex];
            const availableStock = parseInt(selectedOpt.getAttribute("data-stock") || "0", 10);
            const qty = parseInt(qtyInput ? qtyInput.value : "1", 10);

            // Double check stock limit before saving
            if (availableStock <= 0) {
                if (typeof showToast === "function") {
                    showToast("Cannot scrap this accessory: 0 units currently in stock!", "error");
                } else {
                    alert("Cannot scrap this accessory: 0 units currently in stock!");
                }
                return;
            }

            if (qty > availableStock) {
                if (typeof showToast === "function") {
                    showToast(`Scrap quantity (${qty}) cannot exceed available stock (${availableStock})!`, "error");
                } else {
                    alert(`Scrap quantity (${qty}) cannot exceed available stock (${availableStock})!`);
                }
                if (qtyInput) {
                    qtyInput.value = availableStock;
                    validateQty();
                    qtyInput.focus();
                }
                return;
            }

            const payload = {
                action: 'create',
                accessory_id: parseInt(accSelect.value, 10),
                quantity: qty,
                reason: document.getElementById("scrapReason").value || "Physically Damaged",
                bin_location: document.getElementById("scrapLocation").value || "IT Scrap Yard Bin 1",
                disposal_status: document.getElementById("scrapStatus").value || "In Scrap Yard",
                salvage_value: parseFloat(document.getElementById("scrapValue").value || "0"),
                authorized_by: document.getElementById("scrapApprover").value || "Abhishek Sharma",
                notes: document.getElementById("scrapNotes") ? document.getElementById("scrapNotes").value : ""
            };

            const originalBtnText = saveBtn ? saveBtn.textContent : "Confirm Scrap Entry";
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = "Saving to Database...";
            }

            fetch('api/scrap_accessories.php', {
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
                        showToast(result.message || "Failed to record scrap entry.", "error");
                    } else {
                        alert(result.message || "Failed to record scrap entry.");
                    }
                    return;
                }

                const rec = result.data;
                const refId = rec.ref;
                const accName = rec.name;
                const sku = rec.sku;
                const category = rec.category || "General";
                const reason = rec.reason;
                const location = rec.location;
                const statusClass = "status-" + rec.status;
                const statusLabel = rec.status_text;
                const salvageVal = rec.salvage_val;
                const approver = rec.approver;
                const dateStr = rec.date;
                const notesStr = rec.notes || "Hardware verified defective, non-repairable, and approved for scrap write-off.";

                // Insert into table
                const tbody = table ? table.querySelector("tbody") : null;
                if (tbody) {
                    const tr = document.createElement("tr");
                    tr.setAttribute("data-status", rec.status);
                    tr.setAttribute("data-reason", reason);
                    tr.style.borderBottom = "1px solid var(--border-color)";

                    tr.innerHTML = `
                        <td style="padding: 12px 14px;"><span class="ref-code">${escapeHtml(refId)}</span></td>
                        <td style="padding: 12px 14px;">
                            <strong style="color: var(--navy-deep);">${escapeHtml(accName)}</strong>
                            <div style="margin-top: 2px;">
                                <span class="sku-badge">${escapeHtml(sku)}</span>
                                <span style="font-size: 11px; color: var(--text-secondary); margin-left: 6px;">${escapeHtml(category)}</span>
                            </div>
                        </td>
                        <td style="padding: 12px 14px;"><strong style="font-size: 14px; color: var(--navy-deep);">${qty}</strong> pcs</td>
                        <td style="padding: 12px 14px;"><span class="reason-tag">${escapeHtml(reason)}</span></td>
                        <td style="padding: 12px 14px; color: var(--text-secondary); font-size: 12.5px;">${escapeHtml(location)}</td>
                        <td style="padding: 12px 14px;">
                            <span class="scrap-status-badge ${statusClass}">${escapeHtml(statusLabel)}</span>
                        </td>
                        <td style="padding: 12px 14px; font-weight: 600; color: var(--navy-deep);">₹${Number(salvageVal).toLocaleString()}</td>
                        <td style="padding: 12px 14px; font-size: 12px; color: var(--text-secondary);">
                            <div>${dateStr}</div>
                            <div style="font-size: 11px; opacity: 0.8;">By ${escapeHtml(approver)}</div>
                        </td>
                        <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                            <button type="button" class="btn btn-outline btn-sm view-slip-btn" 
                                    data-ref="${escapeHtml(refId)}" data-name="${escapeHtml(accName)}" data-sku="${escapeHtml(sku)}" 
                                    data-category="${escapeHtml(category)}" data-qty="${qty}" data-reason="${escapeHtml(reason)}" 
                                    data-location="${escapeHtml(location)}" data-status="${escapeHtml(statusLabel)}" 
                                    data-value="${salvageVal}" data-approver="${escapeHtml(approver)}" 
                                    data-notes="${escapeHtml(notesStr)}" data-date="${dateStr}"
                                    style="padding: 4px 8px; font-size: 11.5px; border-radius: 4px; border: 1px solid var(--border-color); cursor: pointer; background: #fff;">
                                View Slip
                            </button>
                            <button type="button" class="btn btn-primary btn-sm direct-print-slip-btn" 
                                    data-ref="${escapeHtml(refId)}" data-name="${escapeHtml(accName)}" data-sku="${escapeHtml(sku)}" 
                                    data-category="${escapeHtml(category)}" data-qty="${qty}" data-reason="${escapeHtml(reason)}" 
                                    data-location="${escapeHtml(location)}" data-status="${escapeHtml(statusLabel)}" 
                                    data-value="${salvageVal}" data-approver="${escapeHtml(approver)}" 
                                    data-notes="${escapeHtml(notesStr)}" data-date="${dateStr}"
                                    style="padding: 4px 8px; font-size: 11.5px; border-radius: 4px; border: none; cursor: pointer; background: var(--cyan-primary); color: #fff; margin-left: 4px;" title="Print Slip Directly">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 2px;">
                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                                Print
                            </button>
                        </td>
                    `;

                    tbody.insertBefore(tr, tbody.firstChild);

                    // Attach events to newly inserted buttons
                    const newViewBtn = tr.querySelector(".view-slip-btn");
                    if (newViewBtn) {
                        newViewBtn.addEventListener("click", function () {
                            populateAndOpenSlip(this);
                        });
                    }

                    const newPrintBtn = tr.querySelector(".direct-print-slip-btn");
                    if (newPrintBtn) {
                        newPrintBtn.addEventListener("click", function () {
                            directPrintScrapSlip(extractSlipDataFromBtn(this));
                        });
                    }

                    // Dynamically refresh tab count badges
                    updateDynamicTabCounts();
                }

                // Update stock in dropdown in memory
                const newStock = rec.new_in_stock !== undefined ? rec.new_in_stock : Math.max(0, availableStock - qty);
                selectedOpt.setAttribute("data-stock", newStock);
                selectedOpt.text = `${accName} (${sku}) — Available: ${newStock} pcs`;

                closeModal(addModal);
                scrapForm.reset();
                updateStockLimits();

                if (typeof showToast === "function") {
                    showToast(`Scrap record ${refId} saved to database! Accessories in_stock updated to ${newStock}.`, "success");
                } else {
                    alert(`Scrap record ${refId} saved to database! Accessories in_stock updated to ${newStock}.`);
                }
            })
            .catch(err => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalBtnText;
                }
                console.error("Scrap submit error:", err);
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
            name: btn.getAttribute("data-name") || "Accessory Item",
            sku: btn.getAttribute("data-sku") || "N/A",
            category: btn.getAttribute("data-category") || "General",
            qty: btn.getAttribute("data-qty") || "1",
            reason: btn.getAttribute("data-reason") || "Defective / Damaged",
            condition: btn.getAttribute("data-condition") || "Beyond Economic Repair (BER)",
            location: btn.getAttribute("data-location") || "IT Scrap Yard Bin 1",
            status: btn.getAttribute("data-status") || "In Scrap Yard",
            status_text: btn.getAttribute("data-status") || "In Scrap Yard",
            salvage_val: btn.getAttribute("data-value") || "0",
            value: btn.getAttribute("data-value") || "0",
            approver: btn.getAttribute("data-approver") || "Authorized IT Officer",
            notes: btn.getAttribute("data-notes") || "Hardware verified defective, non-repairable, and approved for scrap write-off.",
            date: btn.getAttribute("data-date") || new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        };
    }

    function populateAndOpenSlip(dataOrBtn) {
        const data = (dataOrBtn instanceof Element) ? extractSlipDataFromBtn(dataOrBtn) : dataOrBtn;
        currentSlipData = data;

        const refEl = document.getElementById("slipRefId");
        if (refEl) refEl.textContent = data.ref;

        const dateEl = document.getElementById("slipDate");
        if (dateEl) dateEl.textContent = data.date;

        const dateAltEl = document.getElementById("slipDateAlt");
        if (dateAltEl) dateAltEl.textContent = data.date;

        const statusBadgeEl = document.getElementById("slipStatusBadge");
        if (statusBadgeEl) {
            statusBadgeEl.textContent = data.status_text || data.status;
        }

        const accNameEl = document.getElementById("slipAccName");
        if (accNameEl) accNameEl.textContent = data.name;

        const skuEl = document.getElementById("slipSku");
        if (skuEl) skuEl.textContent = data.sku;

        const catEl = document.getElementById("slipCategory");
        if (catEl) catEl.textContent = data.category || "General";

        const qtyEl = document.getElementById("slipQty");
        if (qtyEl) qtyEl.textContent = data.qty + " Units";

        const reasonEl = document.getElementById("slipReason");
        if (reasonEl) reasonEl.textContent = data.reason;

        const conditionEl = document.getElementById("slipCondition");
        if (conditionEl) conditionEl.textContent = data.condition || "Beyond Economic Repair (BER)";

        const locEl = document.getElementById("slipLocation");
        if (locEl) locEl.textContent = data.location || "IT Scrap Yard Bin 1";

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
            notesEl.textContent = data.notes || "Hardware verified defective, non-repairable, and approved for scrap write-off.";
        }

        openModal(slipModal);
    }

    // 7. Direct Browser Native Print Engine (Identical layout to Asset Handover & Return Slips)
    function directPrintScrapSlip(data) {
        if (!data) {
            if (typeof showToast === "function") showToast("Record details not found for printing.", "warning");
            return;
        }

        const slipNo = escapeHtml(data.ref || 'SCR1026001');
        const todayStr = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        const slipDate = escapeHtml(data.date || todayStr);
        const accName = escapeHtml(data.name || 'Accessory Item');
        const skuCode = escapeHtml(data.sku || 'N/A');
        const category = escapeHtml(data.category || 'General Accessory');
        const qty = escapeHtml(String(data.qty || 1));
        const reason = escapeHtml(data.reason || 'Physically Damaged');
        const condition = escapeHtml(data.condition || 'Beyond Economic Repair (BER)');
        const binLocation = escapeHtml(data.location || 'IT Scrap Yard Bin 1');
        const statusText = escapeHtml(data.status_text || data.status || 'In Scrap Yard');
        const salvageVal = Number(data.salvage_val || data.value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const approver = escapeHtml(data.approver || 'Authorized IT Officer');
        const notes = escapeHtml(data.notes || 'Hardware verified defective, non-repairable, and approved for scrap write-off.');

        const orgData = window.ORG_SETTINGS || {};
        const orgLogo = orgData.primary_brand_logo || 'assets/images/logo.png';
        const orgName = orgData.organization_legal_name || 'VIROS PORTAL';

        const printHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Peripheral Scrap Disposal Slip - ${slipNo}</title>
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
                    <div class="doc-name">PERIPHERAL DECOMMISSION &amp; SCRAP DISPOSAL SLIP</div>
                </td>
                <td class="header-meta">
                    <div><strong>Slip No:</strong> ${slipNo}</div>
                    <div><strong>Date:</strong> ${slipDate}</div>
                    <div><strong>Disposition:</strong> Scrap / Decommission</div>
                    <div><strong>Status:</strong> ${statusText}</div>
                </td>
            </tr>
        </table>

        <!-- 1. Decommissioned Peripheral / Accessory Details -->
        <div class="sec-title">1. Decommissioned Peripheral / Accessory Details</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 32px; text-align: center;">#</th>
                    <th>Accessory Name &amp; Specifications</th>
                    <th style="width: 140px;">SKU / Part Code</th>
                    <th style="width: 150px;">Category</th>
                    <th style="width: 90px; text-align: center;">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td><strong>${accName}</strong></td>
                    <td style="font-family: monospace; font-weight: 700;">${skuCode}</td>
                    <td>${category}</td>
                    <td style="text-align: center;"><strong>${qty} Units</strong></td>
                </tr>
            </tbody>
        </table>

        <!-- 2. Technical Diagnostics & Disposal Target -->
        <div class="sec-title">2. Technical Diagnostics &amp; Disposal Target</div>
        <table>
            <tr>
                <td class="lbl">Defect / Scrap Reason</td>
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
        <div class="sec-title">3. Diagnostic Findings &amp; Authorization</div>
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
            <strong>Official Decommission Certification:</strong> I hereby certify that the physical hardware / accessory items listed above have undergone technical diagnostic evaluation and are confirmed beyond economic repair (BER) or unserviceable. These items have been struck off from active inventory stock and scheduled for authorized scrap disposal / e-waste recycling in compliance with corporate IT asset disposal policies and e-waste management guidelines.
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
        let printIframe = document.getElementById('scrapSlipPrintIframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'scrapSlipPrintIframe';
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
                directPrintScrapSlip(currentSlipData);
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
            directPrintScrapSlip(extractSlipDataFromBtn(this));
        });
    });

    // Expose print functions globally
    window.directPrintScrapSlip = directPrintScrapSlip;
    window.openScrapSlipModal = populateAndOpenSlip;
    window.printScrapReceipt = directPrintScrapSlip;

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
    link.setAttribute("download", "viros_scrap_accessories_" + new Date().toISOString().slice(0, 10) + ".csv");
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
