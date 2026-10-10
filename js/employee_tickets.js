/**
 * VIROS Asset & IT Service Desk - Employee Support Tickets Dedicated Scripts
 * Handles live search, status filtering, database ticket creation, dynamic timestamps,
 * slide-over drawer quickview, and interactive chat conversation stream.
 */

let currentOpenTicket = null;

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

// Helper to format current local time for datetime-local input
function getNowLocalDatetimeString() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Submit New Ticket Form Handler (Integrated with backend api/tickets.php)
function initNewTicketForm() {
    const form = document.getElementById('employeeTicketForm') || document.getElementById('newTicketForm');
    if (!form) return;

    // Ensure datetime input starts with live local now
    const dtEl = form.querySelector('#ticketDateTimeInput') || document.getElementById('ticketDateTimeInput');
    if (dtEl && !dtEl.value) {
        dtEl.value = getNowLocalDatetimeString();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const origContent = btn ? btn.innerHTML : '';

        const subjectEl  = form.querySelector('#ticketSubjectInput') || document.getElementById('ticketSubjectInput');
        const descEl     = form.querySelector('#ticketDescTextarea') || document.getElementById('ticketDescTextarea');
        const assetEl    = form.querySelector('#ticketAssetSelect') || document.getElementById('ticketAssetSelect');
        const priorityEl = form.querySelector('#ticketUrgencySelect') || document.getElementById('ticketUrgencySelect');
        const dtInput    = form.querySelector('#ticketDateTimeInput') || document.getElementById('ticketDateTimeInput');

        const subjectVal  = subjectEl ? subjectEl.value.trim() : '';
        const descVal     = descEl ? descEl.value.trim() : '';
        const assetVal    = (assetEl && assetEl.value) ? assetEl.value.trim() : 'General Workstation / Laptop';
        const priorityVal = (priorityEl && priorityEl.value) ? priorityEl.value.trim() : 'Medium';
        let rawDt         = dtInput ? dtInput.value.trim() : '';

        if (!subjectVal) {
            alert('Please enter an Issue Subject.');
            if (subjectEl) subjectEl.focus();
            return;
        }

        if (!descVal) {
            alert('Please enter a Detailed Description.');
            if (descEl) descEl.focus();
            return;
        }

        if (!rawDt) {
            rawDt = getNowLocalDatetimeString();
            if (dtInput) dtInput.value = rawDt;
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Submitting...';
        }

        const emp = window.currentActiveEmployee || {};
        const empId   = form.querySelector('input[name="employee_id"]')?.value || emp.id || 0;
        const empName = form.querySelector('input[name="employee_name"]')?.value || emp.name || 'Employee Member';
        const empCode = form.querySelector('input[name="emp_code"]')?.value || emp.code || 'EMP-001';
        const empDept = form.querySelector('input[name="department"]')?.value || emp.department || 'General Staff';

        const payload = {
            subject: subjectVal,
            description: descVal,
            asset_name: assetVal,
            priority: priorityVal,
            incident_date: rawDt,
            employee_id: empId,
            employee_name: empName,
            emp_code: empCode,
            department: empDept
        };

        fetch('api/tickets.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origContent;
            }

            if (data.success && data.ticket) {
                closeModal('employeeTicketModal');
                closeModal('newTicketModal');
                form.reset();

                // Re-sync date time to live now after reset
                if (dtInput) {
                    dtInput.value = getNowLocalDatetimeString();
                }

                const tkt = data.ticket;
                insertTicketTableRow(tkt);

                // Update counter numbers
                const totalCountEl = document.getElementById('statTotalTickets');
                const openCountEl = document.getElementById('statOpenTickets');
                if (totalCountEl) totalCountEl.textContent = parseInt(totalCountEl.textContent || '0') + 1;
                if (openCountEl) openCountEl.textContent = parseInt(openCountEl.textContent || '0') + 1;

                if (typeof showPortalToast === 'function') {
                    showPortalToast(`Ticket ${tkt.id} registered successfully in IT Helpdesk!`, 'success');
                } else {
                    alert(`Ticket ${tkt.id} created successfully.`);
                }
            } else {
                throw new Error(data.message || 'Submission failed');
            }
        })
        .catch(err => {
            console.error('API submission failed:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origContent;
            }
            const errMsg = err.message || 'Network error occurred while submitting ticket.';
            if (typeof showPortalToast === 'function') {
                showPortalToast(`Ticket registration failed: ${errMsg}`, 'error');
            } else {
                alert(`Error: ${errMsg}`);
            }
        });
    });
}

// Insert dynamically generated row into Table
function insertTicketTableRow(tkt) {
    const tbody = document.querySelector('#empTicketsTable tbody');
    if (!tbody) return;

    let pBadgeClass = 'badge-medium';
    if (tkt.priority === 'Urgent') pBadgeClass = 'badge-urgent';
    else if (tkt.priority === 'High') pBadgeClass = 'badge-high';
    else if (tkt.priority === 'Low') pBadgeClass = 'badge-low';

    const newRow = document.createElement('tr');
    newRow.className = 'emp-ticket-row';
    newRow.setAttribute('data-status', (tkt.s_filter || 'open').toLowerCase());

    newRow.innerHTML = `
        <td>
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <span class="ticket-id-pill" style="align-self: flex-start;">${escapeHtml(tkt.id)}</span>
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; white-space: nowrap;">${escapeHtml(tkt.date)}</span>
            </div>
        </td>
        <td>
            <div class="ticket-subject-title">${escapeHtml(tkt.subject)}</div>
            ${tkt.description ? `<div class="ticket-meta-desc" title="${escapeHtml(tkt.description)}">${escapeHtml(tkt.description)}</div>` : ''}
        </td>
        <td>
            <span class="badge ${pBadgeClass}" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                ${escapeHtml(tkt.priority)}
            </span>
        </td>
        <td>
            <span class="ticket-asset-pill">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line></svg>
                ${escapeHtml(tkt.asset || 'General Workstation / Laptop')}
            </span>
        </td>
        <td>
            <span class="badge badge-status-open" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                ${escapeHtml(tkt.status || 'Open')}
            </span>
        </td>
        <td style="text-align: right; padding-right: 24px;">
            <button type="button" class="btn-ticket-view" title="View Ticket Details & Chat with IT Support">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>View & Chat</span>
            </button>
        </td>
    `;

    const viewBtn = newRow.querySelector('.btn-ticket-view');
    if (viewBtn) {
        viewBtn.addEventListener('click', () => viewTicketDetails(tkt));
    }

    tbody.insertBefore(newRow, tbody.firstChild);
}

// View Ticket Details Slide-Over Drawer
function viewTicketDetails(tkt) {
    if (typeof tkt === 'string') {
        try {
            tkt = JSON.parse(tkt);
        } catch (e) {
            console.error('Invalid ticket JSON', e);
            return;
        }
    }

    currentOpenTicket = tkt;

    const drawer = document.getElementById('ticketDetailsModal');
    const backdrop = document.getElementById('ticketDetailsBackdrop');
    if (!drawer) return;

    // Header & Meta Bar
    document.getElementById('detTicketId').textContent = tkt.id;
    document.getElementById('detTicketSubject').textContent = tkt.subject;
    document.getElementById('detTicketAsset').textContent = tkt.asset || 'General Workstation / Laptop';
    document.getElementById('detTicketDate').textContent = tkt.date || '';

    const descEl = document.getElementById('detTicketDescription');
    if (descEl) {
        descEl.textContent = tkt.description || tkt.subject || 'No additional description provided.';
    }

    const pBadge = document.getElementById('detTicketPriority');
    if (pBadge) {
        pBadge.textContent = tkt.priority;
        pBadge.className = 'badge ' + (tkt.p_class || (tkt.priority === 'Urgent' ? 'badge-urgent' : (tkt.priority === 'High' ? 'badge-high' : 'badge-medium')));
    }

    const sBadge = document.getElementById('detTicketStatus');
    if (sBadge) {
        sBadge.textContent = tkt.status;
        sBadge.className = 'badge ' + (tkt.s_class || 'badge-status-progress');
    }

    // Render Chat Stream Dynamically with real timestamps
    renderDrawerChatMessages(tkt);

    // Refresh messages asynchronously from dedicated ticket_messages table
    fetch(`api/tickets.php?action=get_messages&ticket_no=${encodeURIComponent(tkt.id)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                tkt.chat_thread = data.messages;
                if (currentOpenTicket && currentOpenTicket.id === tkt.id) {
                    renderDrawerChatMessages(tkt);
                }
            }
        })
        .catch(e => console.warn('Could not refresh messages:', e));

    // Show Drawer
    if (backdrop) {
        backdrop.style.display = 'block';
        requestAnimationFrame(() => {
            backdrop.classList.add('open');
        });
    }
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';
}
window.viewTicketDetails = viewTicketDetails;

// Dynamically Render Chat Messages in Drawer
function renderDrawerChatMessages(tkt) {
    const chatStream = document.getElementById('ticketActivityList');
    if (!chatStream) return;

    chatStream.innerHTML = '';

    // 1. System Acknowledged Event
    const sysEvent = document.createElement('div');
    sysEvent.className = 'chat-system-event';
    sysEvent.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Ticket ${escapeHtml(tkt.id)} Registered in IT Service Desk Queue (${escapeHtml(tkt.date)})</span>
    `;
    chatStream.appendChild(sysEvent);

    // 2. Chat Thread Conversation History
    if (Array.isArray(tkt.chat_thread) && tkt.chat_thread.length > 0) {
        tkt.chat_thread.forEach(msg => {
            const isOutgoing = (msg.type === 'employee' || msg.author === 'You' || (window.currentActiveEmployee && msg.author === window.currentActiveEmployee.name));
            const msgRow = document.createElement('div');
            msgRow.className = 'chat-message-row ' + (isOutgoing ? 'outgoing' : 'incoming');

            msgRow.innerHTML = `
                <div class="chat-bubble-wrap">
                    <div class="chat-meta">
                        <span class="chat-author">${escapeHtml(msg.author || (isOutgoing ? 'You' : 'IT Support'))}</span>
                        <span class="chat-timestamp">${escapeHtml(msg.time || tkt.date)}</span>
                    </div>
                    <div class="chat-bubble">
                        ${escapeHtml(msg.text)}
                    </div>
                </div>
                <div class="chat-avatar ${isOutgoing ? 'user' : 'tech'}">${escapeHtml(msg.avatar || (isOutgoing ? 'You' : 'IT'))}</div>
            `;
            chatStream.appendChild(msgRow);
        });
    } else {
        // Fallback Initial Problem Bubble
        const empBubble = document.createElement('div');
        empBubble.className = 'chat-message-row outgoing';
        empBubble.innerHTML = `
            <div class="chat-bubble-wrap">
                <div class="chat-meta">
                    <span class="chat-author">You (Problem Report)</span>
                    <span class="chat-timestamp">${escapeHtml(tkt.date)}</span>
                </div>
                <div class="chat-bubble">
                    ${escapeHtml(tkt.description || tkt.subject)}
                </div>
            </div>
            <div class="chat-avatar user">You</div>
        `;
        chatStream.appendChild(empBubble);
    }

    // Scroll to bottom
    setTimeout(() => {
        chatStream.scrollTop = chatStream.scrollHeight;
    }, 80);
}

// Reply form inside ticket details (Chat message sender)
function initTicketReplyForm() {
    const replyForm = document.getElementById('ticketReplyForm');
    if (!replyForm) return;

    replyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const input = document.getElementById('ticketReplyInput');
        const text = input.value.trim();
        if (!text || !currentOpenTicket) return;

        const chatStream = document.getElementById('ticketActivityList');
        const emp = window.currentActiveEmployee || {};
        const authorName = emp.name || 'You';

        const now = new Date();
        const nowFormatted = now.toLocaleString('en-GB', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit', 
            hour12: true 
        });

        // Optimistically append outgoing chat message
        const newMsgRow = document.createElement('div');
        newMsgRow.className = 'chat-message-row outgoing';
        newMsgRow.innerHTML = `
            <div class="chat-bubble-wrap">
                <div class="chat-meta">
                    <span class="chat-author">${escapeHtml(authorName)}</span>
                    <span class="chat-timestamp">${nowFormatted}</span>
                </div>
                <div class="chat-bubble">
                    ${escapeHtml(text)}
                </div>
            </div>
            <div class="chat-avatar user">You</div>
        `;
        if (chatStream) {
            chatStream.appendChild(newMsgRow);
            chatStream.scrollTop = chatStream.scrollHeight;
        }

        input.value = '';

        // Push to currentOpenTicket object
        if (!Array.isArray(currentOpenTicket.chat_thread)) {
            currentOpenTicket.chat_thread = [];
        }
        currentOpenTicket.chat_thread.push({
            author: authorName,
            avatar: 'You',
            type: 'employee',
            time: nowFormatted,
            text: text
        });

        // Persist to backend database via API
        fetch('api/tickets.php?action=reply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ticket_no: currentOpenTicket.id,
                message: text,
                type: 'employee',
                author: authorName
            })
        })
        .then(res => res.json())
        .then(data => {
            if (typeof showPortalToast === 'function') {
                showPortalToast('Message posted to IT Support conversation.', 'success');
            }
        })
        .catch(err => {
            console.warn('Reply save error:', err);
        });
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
    if (!string) return '';
    return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
