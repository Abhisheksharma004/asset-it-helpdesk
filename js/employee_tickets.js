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
    updateAllTicketCounters();
});

// Calculate and update all KPI stats, pill counter badges, and empty-state display
function updateAllTicketCounters() {
    const rows = document.querySelectorAll('#empTicketsTable tbody tr.emp-ticket-row');
    const total = rows.length;
    let inProgress = 0;
    let resolved = 0;

    rows.forEach(r => {
        const st = (r.getAttribute('data-status') || '').toLowerCase();
        if (st === 'resolved' || st.includes('resolved') || st.includes('closed')) {
            resolved++;
        } else {
            inProgress++;
        }
    });

    // Update filter pill counts
    const pAll = document.getElementById('pillAllCount');
    const pProg = document.getElementById('pillProgressCount');
    const pRes = document.getElementById('pillResolvedCount');
    if (pAll) pAll.textContent = total;
    if (pProg) pProg.textContent = inProgress;
    if (pRes) pRes.textContent = resolved;

    // Update top KPI cards
    const statTotal = document.getElementById('statTotalTickets');
    const statOpen = document.getElementById('statOpenTickets');
    const statResolved = document.getElementById('statResolvedTickets');
    if (statTotal) statTotal.textContent = total;
    if (statOpen) statOpen.textContent = inProgress;
    if (statResolved) statResolved.textContent = resolved;

    // Toggle Empty State message
    const emptyState = document.getElementById('ticketsEmptyState');
    if (emptyState) {
        emptyState.style.display = total === 0 ? 'block' : 'none';
    }
}

// Unified filter & search application across all current table rows
function applyTicketFilterAndSearch() {
    const activeFilterBtn = document.querySelector('.tickets-pill-btn.active');
    const filterStatus = activeFilterBtn ? activeFilterBtn.getAttribute('data-status') : 'all';
    const searchVal = document.getElementById('ticketSearchInput')?.value.toLowerCase().trim() || '';
    const tableRows = document.querySelectorAll('#empTicketsTable tbody tr.emp-ticket-row');
    const emptyState = document.getElementById('ticketsEmptyState');
    let visibleCount = 0;

    tableRows.forEach(row => {
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
        const rowText = row.innerText.toLowerCase();

        let matchesFilter = false;
        if (filterStatus === 'all') {
            matchesFilter = true;
        } else if (filterStatus === 'resolved') {
            matchesFilter = (rowStatus === 'resolved' || rowStatus.includes('resolved') || rowStatus.includes('closed'));
        } else if (filterStatus === 'in-progress') {
            matchesFilter = (rowStatus === 'in-progress' || rowStatus === 'open' || !rowStatus.includes('resolved'));
        } else {
            matchesFilter = (rowStatus === filterStatus);
        }

        const matchesSearch = (searchVal === '' || rowText.includes(searchVal));

        if (matchesFilter && matchesSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (emptyState) {
        emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}

// Status Pill Filter Setup
function initTicketFilters() {
    const pillBtns = document.querySelectorAll('.tickets-pill-btn');
    pillBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            pillBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyTicketFilterAndSearch();
        });
    });
}

// Live Search Input Setup
function initTicketSearch() {
    const searchInput = document.getElementById('ticketSearchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        applyTicketFilterAndSearch();
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
    if (!form || form.dataset.ticketsJsBound) return;
    form.dataset.ticketsJsBound = 'true';

    // Ensure datetime input starts with live local now
    const dtEl = form.querySelector('#ticketDateTimeInput') || document.getElementById('ticketDateTimeInput');
    if (dtEl && !dtEl.value) {
        dtEl.value = getNowLocalDatetimeString();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        if (form.dataset.submitting === 'true') {
            return;
        }

        const btn = form.querySelector('button[type="submit"]');
        const origContent = btn ? btn.innerHTML : '';

        const subjectEl = form.querySelector('#ticketSubjectInput') || document.getElementById('ticketSubjectInput');
        const descEl = form.querySelector('#ticketDescTextarea') || document.getElementById('ticketDescTextarea');
        const assetEl = form.querySelector('#ticketAssetSelect') || document.getElementById('ticketAssetSelect');
        const priorityEl = form.querySelector('#ticketUrgencySelect') || document.getElementById('ticketUrgencySelect');
        const dtInput = form.querySelector('#ticketDateTimeInput') || document.getElementById('ticketDateTimeInput');

        const subjectVal = subjectEl ? subjectEl.value.trim() : '';
        const descVal = descEl ? descEl.value.trim() : '';
        const assetVal = (assetEl && assetEl.value) ? assetEl.value.trim() : 'General Ticket';
        const priorityVal = (priorityEl && priorityEl.value) ? priorityEl.value.trim() : 'Medium';
        let rawDt = dtInput ? dtInput.value.trim() : '';

        if (!subjectVal) {
            if (typeof showToast === 'function') {
                showToast('Please enter an Issue Subject.', 'warning');
            } else {
                alert('Please enter an Issue Subject.');
            }
            if (subjectEl) subjectEl.focus();
            return;
        }

        if (!descVal) {
            if (typeof showToast === 'function') {
                showToast('Please enter a Detailed Description.', 'warning');
            } else {
                alert('Please enter a Detailed Description.');
            }
            if (descEl) descEl.focus();
            return;
        }

        if (!rawDt) {
            rawDt = getNowLocalDatetimeString();
            if (dtInput) dtInput.value = rawDt;
        }

        form.dataset.submitting = 'true';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Submitting...';
        }

        const emp = window.currentActiveEmployee || {};
        const empId = form.querySelector('input[name="employee_id"]')?.value || emp.id || 0;
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
                delete form.dataset.submitting;
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

                    // Refresh all counters, pill badges, and hide empty state
                    updateAllTicketCounters();
                    applyTicketFilterAndSearch();

                    if (typeof showToast === 'function') {
                        showToast(`Ticket ${tkt.id} registered successfully in IT Helpdesk!`, 'success');
                    } else if (typeof showPortalToast === 'function') {
                        showPortalToast(`Ticket ${tkt.id} registered successfully in IT Helpdesk!`, 'success');
                    } else {
                        alert(`Ticket ${tkt.id} created successfully.`);
                    }
                } else {
                    throw new Error(data.message || 'Submission failed');
                }
            })
            .catch(err => {
                delete form.dataset.submitting;
                console.error('API submission failed:', err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origContent;
                }
                const errMsg = err.message || 'Network error occurred while submitting ticket.';
                if (typeof showToast === 'function') {
                    showToast(`Ticket registration failed: ${errMsg}`, 'error');
                } else if (typeof showPortalToast === 'function') {
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
                ${escapeHtml(tkt.asset || 'General Ticket')}
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

    // Immediately hide empty state & refresh all pill counts + stats
    const emptyState = document.getElementById('ticketsEmptyState');
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    updateAllTicketCounters();
    applyTicketFilterAndSearch();
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
    document.getElementById('detTicketAsset').textContent = tkt.asset || 'General Ticket';
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

    // Initial fresh live messages fetch
    fetch(`api/tickets.php?action=get_messages&ticket_no=${encodeURIComponent(tkt.id)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                tkt.chat_thread = data.messages;
                if (currentOpenTicket && currentOpenTicket.id === tkt.id) {
                    renderDrawerChatMessages(tkt, true);
                }
            }
        })
        .catch(e => console.warn('Could not refresh messages:', e));

    // Start Live Real-time Polling for Active Drawer (every 2.5s)
    startEmployeeChatPolling(tkt.id);

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

// Subtle Audio Chime for Incoming Real-Time Chat (Web Audio API)
function playMessageChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12); // A5
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch (e) {
        // Silently ignore if blocked by browser policy
    }
}

// Real-Time Polling Engine for Employee Ticket Details Drawer
let empChatPollTimer = null;
let isPollingEmpChat = false;

function startEmployeeChatPolling(ticketId) {
    stopEmployeeChatPolling();

    empChatPollTimer = setInterval(() => {
        if (!currentOpenTicket || currentOpenTicket.id !== ticketId) {
            stopEmployeeChatPolling();
            return;
        }
        if (document.hidden) return; // Tab in background, skip cycle to save bandwidth
        if (isPollingEmpChat) return;

        isPollingEmpChat = true;
        fetch(`api/tickets.php?action=get_messages&ticket_no=${encodeURIComponent(ticketId)}`)
            .then(res => res.json())
            .then(data => {
                isPollingEmpChat = false;
                if (!currentOpenTicket || currentOpenTicket.id !== ticketId) return;

                if (data.success && Array.isArray(data.messages)) {
                    const prevCount = Array.isArray(currentOpenTicket.chat_thread) ? currentOpenTicket.chat_thread.length : 0;
                    const newCount = data.messages.length;

                    let hasNewMessage = (newCount > prevCount);
                    let contentChanged = false;

                    // Check if content changed
                    if (!hasNewMessage && newCount > 0 && prevCount > 0) {
                        const lastLocal = currentOpenTicket.chat_thread[prevCount - 1];
                        const lastRemote = data.messages[newCount - 1];
                        if (lastLocal && lastRemote && (lastLocal.text !== lastRemote.text || lastLocal.id !== lastRemote.id)) {
                            contentChanged = true;
                        }
                    }

                    if (hasNewMessage || contentChanged) {
                        const latest = data.messages[data.messages.length - 1];
                        const wasIncoming = latest && (latest.type === 'admin' || latest.type === 'tech');

                        currentOpenTicket.chat_thread = data.messages;
                        renderDrawerChatMessages(currentOpenTicket);

                        if (hasNewMessage && wasIncoming) {
                            playMessageChime();
                            if (typeof showPortalToast === 'function') {
                                showPortalToast(`IT Helpdesk sent an update on ticket #${ticketId}`, 'info');
                            }
                        }
                    }

                    // Live synchronize status if updated by IT Support team
                    if (data.status && data.status !== currentOpenTicket.status) {
                        currentOpenTicket.status = data.status;
                        const sBadge = document.getElementById('detTicketStatus');
                        if (sBadge) sBadge.textContent = data.status;

                        // Also update table row in DOM if present
                        const row = document.querySelector(`.ticket-data-row[data-id="${ticketId}"]`);
                        if (row) {
                            const statusCell = row.querySelector('.col-ticket-status');
                            if (statusCell) {
                                statusCell.innerHTML = `<span class="badge badge-status-progress">${escapeHtml(data.status)}</span>`;
                            }
                        }
                    }

                    // Real-time synchronization of Typing Indicator
                    updateEmployeeTypingIndicator(!!data.admin_typing, 'IT Service Desk');
                }
            })
            .catch(e => {
                isPollingEmpChat = false;
            });
    }, 1000);
}

function stopEmployeeChatPolling() {
    if (empChatPollTimer) {
        clearInterval(empChatPollTimer);
        empChatPollTimer = null;
    }
    isPollingEmpChat = false;
    const typingRow = document.getElementById('empTypingIndicatorRow');
    if (typingRow) typingRow.remove();
}

// Render or Remove Typing Indicator in Employee Drawer
function updateEmployeeTypingIndicator(isTyping, authorName = 'IT Service Desk') {
    const chatStream = document.getElementById('ticketActivityList');
    if (!chatStream) return;
    let indicator = document.getElementById('empTypingIndicatorRow');

    if (isTyping) {
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'empTypingIndicatorRow';
            indicator.className = 'chat-message-row incoming typing-indicator-row';
            indicator.innerHTML = `
                <div class="chat-avatar tech">IT</div>
                <div class="chat-bubble-wrap">
                    <div class="typing-bubble">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-text">${escapeHtml(authorName)} is typing...</span>
                    </div>
                </div>
            `;
            chatStream.appendChild(indicator);
            chatStream.scrollTop = chatStream.scrollHeight;
        } else {
            const textEl = indicator.querySelector('.typing-text');
            if (textEl) textEl.textContent = `${authorName} is typing...`;
        }
    } else {
        if (indicator) {
            indicator.remove();
        }
    }
}

// Dynamically Render Chat Messages in Drawer
function renderDrawerChatMessages(tkt, forceScroll = false) {
    const chatStream = document.getElementById('ticketActivityList');
    if (!chatStream) return;

    // Detect if user was already near bottom
    const wasNearBottom = forceScroll || (chatStream.scrollHeight - chatStream.scrollTop - chatStream.clientHeight < 120);

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

            if (isOutgoing) {
                msgRow.innerHTML = `
                    <div class="chat-bubble-wrap">
                        <div class="chat-meta">
                            <span class="chat-author">${escapeHtml(msg.author || 'You')}</span>
                            <span class="chat-timestamp">${escapeHtml(msg.time || tkt.date)}</span>
                        </div>
                        <div class="chat-bubble">${escapeHtml(msg.text ? String(msg.text).trim() : '')}</div>
                    </div>
                    <div class="chat-avatar user">${escapeHtml(msg.avatar || 'You')}</div>
                `;
            } else {
                msgRow.innerHTML = `
                    <div class="chat-avatar tech">${escapeHtml(msg.avatar || 'IT')}</div>
                    <div class="chat-bubble-wrap">
                        <div class="chat-meta">
                            <span class="chat-author">${escapeHtml(msg.author || 'IT Support')}</span>
                            <span class="chat-timestamp">${escapeHtml(msg.time || tkt.date)}</span>
                        </div>
                        <div class="chat-bubble">${escapeHtml(msg.text ? String(msg.text).trim() : '')}</div>
                    </div>
                `;
            }
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
                <div class="chat-bubble">${escapeHtml((tkt.description || tkt.subject || '').trim())}</div>
            </div>
            <div class="chat-avatar user">You</div>
        `;
        chatStream.appendChild(empBubble);
    }

    // Scroll to bottom if user was near bottom or forced
    if (wasNearBottom) {
        setTimeout(() => {
            chatStream.scrollTop = chatStream.scrollHeight;
        }, 50);
    }
}

// Reply form inside ticket details (Chat message sender)
function initTicketReplyForm() {
    const replyForm = document.getElementById('ticketReplyForm');
    const input = document.getElementById('ticketReplyInput');
    if (!replyForm || !input) return;

    let lastEmpTypingPing = 0;
    function sendEmpTypingPing(ticketId, isTyping = true) {
        const now = Date.now();
        if (isTyping && now - lastEmpTypingPing < 2000) return;
        lastEmpTypingPing = isTyping ? now : 0;
        const emp = window.currentActiveEmployee || {};
        fetch('api/tickets.php?action=typing', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_no: ticketId,
                type: 'employee',
                name: emp.name || 'Employee',
                status: isTyping ? 'start' : 'stop'
            })
        }).catch(() => {});
    }

    input.addEventListener('input', function () {
        if (currentOpenTicket) {
            sendEmpTypingPing(currentOpenTicket.id, true);
        }
    });

    input.addEventListener('blur', function () {
        if (currentOpenTicket) {
            sendEmpTypingPing(currentOpenTicket.id, false);
        }
    });

    replyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || !currentOpenTicket) return;

        sendEmpTypingPing(currentOpenTicket.id, false);

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
                <div class="chat-bubble">${escapeHtml(text ? String(text).trim() : '')}</div>
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
                if (typeof showToast === 'function') {
                    showToast('Message posted to IT Support conversation.', 'success');
                } else if (typeof showPortalToast === 'function') {
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
    if (currentOpenTicket) {
        fetch('api/tickets.php?action=typing', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_no: currentOpenTicket.id, type: 'employee', status: 'stop' })
        }).catch(() => {});
    }

    stopEmployeeChatPolling();

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
    currentOpenTicket = null;
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
