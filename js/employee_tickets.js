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
        const descVal     = (document.getElementById('ticketDescTextarea') || document.getElementById('newTktDescription'))?.value.trim() || '';
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
            const rawDt = document.getElementById('ticketDateTimeInput')?.value;
            let dateStr = new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
            if (rawDt) {
                const parsedDate = new Date(rawDt);
                if (!isNaN(parsedDate.getTime())) {
                    dateStr = parsedDate.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
                }
            }

            let pBadgeClass = 'badge-medium';
            if (priorityVal === 'Urgent') pBadgeClass = 'badge-urgent';
            else if (priorityVal === 'High') pBadgeClass = 'badge-high';
            else if (priorityVal === 'Low') pBadgeClass = 'badge-low';

            const tktNow = new Date();
            const tktMM = String(tktNow.getMonth() + 1).padStart(2, '0');
            const tktYY = String(tktNow.getFullYear()).slice(-2);
            const tktSerial = Math.floor(1110 + Math.random() * 800);
            const newTktId = `TKT${tktMM}${tktYY}${tktSerial}`;

            const newRow = document.createElement('tr');
            newRow.className = 'emp-ticket-row';
            newRow.setAttribute('data-status', 'open');

            const itemPayload = {
                id: newTktId,
                subject: subjectVal,
                description: descVal,
                asset: assetVal || 'General Workstation / Laptop',
                priority: priorityVal,
                status: 'Open',
                p_class: pBadgeClass,
                s_class: 'badge-status-open',
                date: dateStr,
                tech: 'Auto-Triage IT Queue'
            };

            newRow.innerHTML = `
                <td>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <span class="ticket-id-pill" style="align-self: flex-start;">${newTktId}</span>
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; white-space: nowrap;">${dateStr}</span>
                    </div>
                </td>
                <td>
                    <div class="ticket-subject-title">${escapeHtml(subjectVal)}</div>
                    ${descVal ? `<div class="ticket-meta-desc" title="${escapeHtml(descVal)}">${escapeHtml(descVal)}</div>` : ''}
                </td>
                <td>
                    <span class="badge ${pBadgeClass}" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                        ${escapeHtml(priorityVal)}
                    </span>
                </td>
                <td>
                    <span class="ticket-asset-pill">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line></svg>
                        ${escapeHtml(assetVal || 'General Workstation / Laptop')}
                    </span>
                </td>
                <td>
                    <span class="badge badge-status-open" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                        Open
                    </span>
                </td>
                <td style="text-align: right; padding-right: 24px;">
                    <button type="button" class="btn-ticket-view" onclick='viewTicketDetails(${JSON.stringify(itemPayload)})' title="View Ticket Details & Chat with IT Support">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <span>View & Chat</span>
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

// View Ticket Details Slide-Over Drawer (matching assets.php)
function viewTicketDetails(tkt) {
    const drawer = document.getElementById('ticketDetailsModal');
    const backdrop = document.getElementById('ticketDetailsBackdrop');
    if (!drawer) return;

    document.getElementById('detTicketId').textContent = tkt.id;
    document.getElementById('detTicketSubject').textContent = tkt.subject;
    document.getElementById('detTicketAsset').textContent = tkt.asset || 'General Workstation / Laptop';
    document.getElementById('detTicketDate').textContent = tkt.date;
    const techEl = document.getElementById('detTicketTech');
    if (techEl) techEl.textContent = tkt.tech || '';

    const descEl = document.getElementById('detTicketDescription');
    if (descEl) {
        descEl.textContent = tkt.description || 'No additional description provided.';
    }

    const pBadge = document.getElementById('detTicketPriority');
    if (pBadge) {
        pBadge.textContent = tkt.priority;
        pBadge.className = 'badge ' + (tkt.p_class || 'badge-medium');
    }

    const sBadge = document.getElementById('detTicketStatus');
    if (sBadge) {
        sBadge.textContent = tkt.status;
        sBadge.className = 'badge ' + (tkt.s_class || 'badge-status-progress');
    }

    // Dynamically align chat stream data with the ticket
    const chatAckText = document.getElementById('chatSystemAckText');
    if (chatAckText) {
        chatAckText.textContent = `Ticket ${tkt.id} Logged • Incident assigned to IT Service Desk Queue`;
    }

    const tktDateStr = tkt.date || 'Today';
    const cTime1 = document.getElementById('chatTime1');
    const cTime2 = document.getElementById('chatTime2');
    if (cTime1) cTime1.textContent = tktDateStr;
    if (cTime2) cTime2.textContent = tktDateStr;

    const techNameEl = document.getElementById('chatTechName');
    if (techNameEl && tkt.tech) techNameEl.textContent = tkt.tech;

    const techAvatarEl = document.getElementById('chatTechAvatar');
    if (techAvatarEl) techAvatarEl.textContent = tkt.tech_init || 'IT';

    if (backdrop) {
        backdrop.style.display = 'block';
        requestAnimationFrame(() => {
            backdrop.classList.add('open');
        });
    }
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Auto scroll chat to bottom
    const chatStream = document.getElementById('ticketActivityList');
    if (chatStream) {
        setTimeout(() => { chatStream.scrollTop = chatStream.scrollHeight; }, 100);
    }
}

// Reply form inside ticket details (Chat message sender)
function initTicketReplyForm() {
    const replyForm = document.getElementById('ticketReplyForm');
    if (!replyForm) return;

    replyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const input = document.getElementById('ticketReplyInput');
        const text = input.value.trim();
        if (!text) return;

        const chatStream = document.getElementById('ticketActivityList');
        if (chatStream) {
            const now = new Date();
            const nowFormatted = now.toLocaleString('en-GB', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit', 
                hour12: true 
            });

            const newMsgRow = document.createElement('div');
            newMsgRow.className = 'chat-message-row outgoing';
            newMsgRow.innerHTML = `
                <div class="chat-bubble-wrap">
                    <div class="chat-meta">
                        <span class="chat-author">You (Employee Note)</span>
                        <span class="chat-timestamp">${nowFormatted}</span>
                    </div>
                    <div class="chat-bubble">
                        ${escapeHtml(text)}
                    </div>
                </div>
                <div class="chat-avatar user">You</div>
            `;
            chatStream.appendChild(newMsgRow);
            input.value = '';

            // Smooth scroll to bottom of chat
            chatStream.scrollTop = chatStream.scrollHeight;

            if (typeof showPortalToast === 'function') {
                showPortalToast('Message posted to IT Support conversation.', 'success');
            }
        }
    });
}

// Close Ticket Details Slide-Over Drawer
function closeTicketDetailsDrawer() {
    const drawer = document.getElementById('ticketDetailsModal');
    const backdrop = document.getElementById('ticketDetailsBackdrop');
    if (drawer) {
        drawer.classList.remove('open');
    }
    if (backdrop) {
        backdrop.classList.remove('open');
        backdrop.style.pointerEvents = 'none';
        backdrop.style.opacity = '0';
        backdrop.style.backdropFilter = 'none';
        backdrop.style.webkitBackdropFilter = 'none';
        setTimeout(() => {
            if (!drawer || !drawer.classList.contains('open')) {
                backdrop.style.display = 'none';
                backdrop.style.visibility = 'hidden';
                backdrop.style.backdropFilter = '';
                backdrop.style.webkitBackdropFilter = '';
                backdrop.style.opacity = '';
            }
        }, 300);
    }
    document.body.style.overflow = '';
}
window.closeTicketDetailsDrawer = closeTicketDetailsDrawer;

// Generic Close Modal & Drawer
function closeModal(modalId) {
    if (modalId === 'ticketDetailsModal') {
        closeTicketDetailsDrawer();
        return;
    }
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}
window.closeModal = closeModal;

// ESC to close drawer
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const drawer = document.getElementById('ticketDetailsModal');
        if (drawer && drawer.classList.contains('open')) {
            closeTicketDetailsDrawer();
        }
    }
});

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
