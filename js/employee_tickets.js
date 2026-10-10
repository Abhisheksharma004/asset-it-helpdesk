/**
 * VIROS Asset & IT Service Desk - Employee Support Tickets Dedicated Scripts
 * Handles live search, status filtering, ticket creation modal, and ticket details timeline
 */

document.addEventListener('DOMContentLoaded', function () {
    initTicketFilters();
    initTicketSearch();
    initNewTicketForm();
    initTicketReplyForm();
});

// Status Pill Filter
function initTicketFilters() {
    const pillBtns = document.querySelectorAll('.tickets-pill-btn');
    const tableRows = document.querySelectorAll('.emp-ticket-row');
    const emptyState = document.getElementById('ticketsEmptyState');

    pillBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            pillBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filterStatus = this.getAttribute('data-status');
            const searchVal = document.getElementById('ticketSearchInput')?.value.toLowerCase().trim() || '';
            let visibleCount = 0;

            tableRows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowText = row.innerText.toLowerCase();

                const matchesFilter = (filterStatus === 'all' || rowStatus === filterStatus);
                const matchesSearch = (searchVal === '' || rowText.includes(searchVal));

                if (matchesFilter && matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    });
}

// Live Search Input
function initTicketSearch() {
    const searchInput = document.getElementById('ticketSearchInput');
    const tableRows = document.querySelectorAll('.emp-ticket-row');
    const emptyState = document.getElementById('ticketsEmptyState');

    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        const searchVal = this.value.toLowerCase().trim();
        const activeFilterBtn = document.querySelector('.tickets-pill-btn.active');
        const activeFilter = activeFilterBtn ? activeFilterBtn.getAttribute('data-status') : 'all';
        let visibleCount = 0;

        tableRows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowText = row.innerText.toLowerCase();

            const matchesFilter = (activeFilter === 'all' || rowStatus === activeFilter);
            const matchesSearch = (searchVal === '' || rowText.includes(searchVal));

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    });
}

// Open Raise New Ticket Modal
function openNewTicketModal(assetTag = '') {
    if (typeof window.openEmployeeTicketModal === 'function') {
        window.openEmployeeTicketModal(assetTag);
    } else {
        const modal = document.getElementById('employeeTicketModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }
}

// Submit New Ticket Form Handler
function initNewTicketForm() {
    const form = document.getElementById('employeeTicketForm') || document.getElementById('newTicketForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const origContent = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Submitting...';
        }

        const subjectVal  = (document.getElementById('ticketSubjectInput') || document.getElementById('newTktSubject'))?.value.trim() || 'IT Support Ticket';
        const catVal      = (document.getElementById('ticketCategorySelect') || document.getElementById('newTktCategory'))?.value || 'Hardware / Laptop & PC';
        const assetVal    = (document.getElementById('ticketAssetSelect') || document.getElementById('newTktAsset'))?.value || 'General Workstation / Laptop';
        const priorityVal = (document.getElementById('ticketUrgencySelect') || document.getElementById('newTktPriority'))?.value || 'Medium';

        setTimeout(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origContent;
            }
            closeModal('employeeTicketModal');
            closeModal('newTicketModal');
            form.reset();

            // Insert new row into the table dynamically
            const tbody = document.querySelector('#empTicketsTable tbody');
            const newTktId = 'TKT-2026-' + Math.floor(1000 + Math.random() * 9000);
            const dateStr = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            let pBadgeClass = 'badge-medium';
            if (priorityVal === 'Urgent') pBadgeClass = 'badge-urgent';
            else if (priorityVal === 'High') pBadgeClass = 'badge-high';
            else if (priorityVal === 'Low') pBadgeClass = 'badge-low';

            const newRow = document.createElement('tr');
            newRow.className = 'emp-ticket-row';
            newRow.setAttribute('data-status', 'open');

            const itemPayload = {
                id: newTktId,
                subject: subjectVal,
                category: catVal,
                asset: assetVal,
                priority: priorityVal,
                status: 'Open',
                p_class: pBadgeClass,
                s_class: 'badge-status-open',
                date: dateStr,
                tech: 'Auto-Triage IT Queue'
            };

            newRow.innerHTML = `
                <td>
                    <span class="ticket-id-pill">${newTktId}</span>
                </td>
                <td>
                    <div class="ticket-subject-title">${escapeHtml(subjectVal)}</div>
                    <div class="ticket-meta-subtitle">
                        <span>${escapeHtml(catVal)}</span>
                    </div>
                </td>
                <td>
                    <span class="ticket-asset-pill">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line></svg>
                        ${escapeHtml(assetVal)}
                    </span>
                </td>
                <td>
                    <span class="badge ${pBadgeClass}" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                        ${escapeHtml(priorityVal)}
                    </span>
                </td>
                <td>
                    <span class="badge badge-status-open" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                        Open
                    </span>
                </td>
                <td>
                    <span style="font-size: 12.5px; font-weight: 500; color: var(--text-secondary);">${dateStr}</span>
                </td>
                <td>
                    <div class="ticket-tech-box">
                        <div class="ticket-tech-avatar">IT</div>
                        <span class="ticket-tech-name">Auto-Triage Queue</span>
                    </div>
                </td>
                <td style="text-align: right; padding-right: 24px;">
                    <button type="button" class="btn-ticket-view" onclick='viewTicketDetails(${JSON.stringify(itemPayload)})'>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        View Details
                    </button>
                </td>
            `;

            if (tbody) {
                tbody.insertBefore(newRow, tbody.firstChild);
            }

            // Update stats
            const totalCountEl = document.getElementById('statTotalTickets');
            const openCountEl = document.getElementById('statOpenTickets');
            if (totalCountEl) totalCountEl.textContent = parseInt(totalCountEl.textContent || '0') + 1;
            if (openCountEl) openCountEl.textContent = parseInt(openCountEl.textContent || '0') + 1;

            if (typeof showPortalToast === 'function') {
                showPortalToast(`Ticket ${newTktId} raised successfully and routed to IT Helpdesk!`, 'success');
            } else {
                alert(`Ticket ${newTktId} created successfully. IT Support team has been notified.`);
            }
        }, 800);
    });
}

// View Ticket Details Modal
function viewTicketDetails(tkt) {
    const modal = document.getElementById('ticketDetailsModal');
    if (!modal) return;

    document.getElementById('detTicketId').textContent = tkt.id;
    document.getElementById('detTicketSubject').textContent = tkt.subject;
    document.getElementById('detTicketAsset').textContent = tkt.asset;
    document.getElementById('detTicketDate').textContent = tkt.date;
    document.getElementById('detTicketTech').textContent = tkt.tech;

    const pBadge = document.getElementById('detTicketPriority');
    pBadge.textContent = tkt.priority;
    pBadge.className = 'badge ' + (tkt.p_class || 'badge-medium');

    const sBadge = document.getElementById('detTicketStatus');
    sBadge.textContent = tkt.status;
    sBadge.className = 'badge ' + (tkt.s_class || 'badge-status-progress');

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Reply form inside ticket details
function initTicketReplyForm() {
    const replyForm = document.getElementById('ticketReplyForm');
    if (!replyForm) return;

    replyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const input = document.getElementById('ticketReplyInput');
        const text = input.value.trim();
        if (!text) return;

        const timelineList = document.getElementById('ticketActivityList');
        if (timelineList) {
            const newItem = document.createElement('div');
            newItem.className = 'timeline-item active';
            newItem.innerHTML = `
                <div class="timeline-dot">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <div class="timeline-content">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="timeline-title">You (Employee Note)</span>
                        <span class="timeline-time">Just now</span>
                    </div>
                    <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 4px;">
                        ${escapeHtml(text)}
                    </div>
                </div>
            `;
            timelineList.appendChild(newItem);
            input.value = '';

            if (typeof showPortalToast === 'function') {
                showPortalToast('Your message has been added to ticket history.', 'success');
            }
        }
    });
}

// Generic Close Modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Close on backdrop click
window.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

function escapeHtml(string) {
    return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
