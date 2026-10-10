/**
 * VIROS Asset & IT Service Desk - Admin All Support Tickets Interactive Scripts
 * Styled & architected in complete alignment with assets.php
 * Handles status tabs, search & dropdown filters, executive KPI cards,
 * slide-over drawer navigation, triage actions, live chat thread & CSV export.
 */

let currentOpenTicket = null;

document.addEventListener('DOMContentLoaded', function () {
    initStatusTabs();
    initSearchAndDropdownFilters();
    initUrlFilter();
    initDrawerAdminActions();
    initDrawerChatReply();
});

// Parse URL search parameters (e.g. tickets.php?status=open)
function initUrlFilter() {
    const urlParams = new URLSearchParams(window.location.search);
    const statusParam = urlParams.get('status');
    if (statusParam) {
        filterByStatusTab(statusParam);
    }
}

// Status Tabs Navigation (aligns with .asset-status-tabs from assets.php)
function initStatusTabs() {
    const tabBtns = document.querySelectorAll('.status-tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyAllFilters();
        });
    });
}

// Switch status filter tab programmatically
function filterByStatusTab(statusKey) {
    const tabBtns = document.querySelectorAll('.status-tab-btn');
    let matched = false;
    tabBtns.forEach(btn => {
        if (btn.getAttribute('data-status') === statusKey) {
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            matched = true;
        }
    });
    if (!matched && statusKey === 'all') {
        const firstTab = document.querySelector('.status-tab-btn[data-status="all"]');
        if (firstTab) firstTab.classList.add('active');
    }
    applyAllFilters();
}
window.filterByStatusTab = filterByStatusTab;

// Filter specifically by Urgency (e.g. when clicking Critical/Urgent KPI card)
function filterByUrgency(urgencyLevel) {
    const urgencySelect = document.getElementById('urgencyFilter');
    if (urgencySelect) {
        urgencySelect.value = urgencyLevel.toLowerCase();
    }
    applyAllFilters();
}
window.filterByUrgency = filterByUrgency;

// Search and Dropdown Filter Handlers
function initSearchAndDropdownFilters() {
    const searchInput = document.getElementById('ticketSearchInput');
    const deptSelect = document.getElementById('deptFilter');
    const urgencySelect = document.getElementById('urgencyFilter');
    const categorySelect = document.getElementById('categoryFilter');

    if (searchInput) searchInput.addEventListener('input', applyAllFilters);
    if (deptSelect) deptSelect.addEventListener('change', applyAllFilters);
    if (urgencySelect) urgencySelect.addEventListener('change', applyAllFilters);
    if (categorySelect) categorySelect.addEventListener('change', applyAllFilters);
}

// Master filter applying Status Tabs, Search Term, Department, Urgency and Category
function applyAllFilters() {
    const activeTab = document.querySelector('.status-tab-btn.active');
    const statusFilter = activeTab ? activeTab.getAttribute('data-status') : 'all';

    const searchVal = document.getElementById('ticketSearchInput')?.value.toLowerCase().trim() || '';
    const deptFilter = document.getElementById('deptFilter')?.value.toLowerCase().trim() || 'all';
    const urgencyFilter = document.getElementById('urgencyFilter')?.value.toLowerCase().trim() || 'all';
    const categoryFilter = document.getElementById('categoryFilter')?.value.toLowerCase().trim() || 'all';

    const tableRows = document.querySelectorAll('.ticket-data-row');
    const emptyState = document.getElementById('ticketsEmptyState');
    let visibleCount = 0;

    tableRows.forEach(row => {
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
        const rowDept = (row.getAttribute('data-dept') || '').toLowerCase();
        const rowUrgency = (row.getAttribute('data-urgency') || '').toLowerCase();
        const rowCategory = (row.getAttribute('data-category') || '').toLowerCase();
        const rowText = row.innerText.toLowerCase();

        const matchStatus = (statusFilter === 'all' || rowStatus === statusFilter);
        const matchSearch = (searchVal === '' || rowText.includes(searchVal));
        const matchDept = (deptFilter === 'all' || rowDept.includes(deptFilter));
        const matchUrgency = (urgencyFilter === 'all' || rowUrgency === urgencyFilter);
        const matchCategory = (categoryFilter === 'all' || rowCategory.includes(categoryFilter));

        if (matchStatus && matchSearch && matchDept && matchUrgency && matchCategory) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    // Update Pagination count text
    const paginationInfo = document.getElementById('paginationInfo');
    if (paginationInfo) {
        const total = tableRows.length;
        if (visibleCount === 0) {
            paginationInfo.innerHTML = `Showing <strong>0</strong> tickets (0 of ${total} total)`;
        } else {
            paginationInfo.innerHTML = `Showing <strong>1</strong> to <strong>${visibleCount}</strong> of <strong>${total}</strong> support tickets`;
        }
    }
}

// Reset all filters back to default
function resetAllFilters() {
    const searchInput = document.getElementById('ticketSearchInput');
    if (searchInput) searchInput.value = '';

    const deptSelect = document.getElementById('deptFilter');
    if (deptSelect) deptSelect.value = 'all';

    const urgencySelect = document.getElementById('urgencyFilter');
    if (urgencySelect) urgencySelect.value = 'all';

    const categorySelect = document.getElementById('categoryFilter');
    if (categorySelect) categorySelect.value = 'all';

    const tabBtns = document.querySelectorAll('.status-tab-btn');
    tabBtns.forEach(btn => btn.classList.remove('active'));
    const allTab = document.querySelector('.status-tab-btn[data-status="all"]');
    if (allTab) allTab.classList.add('active');

    applyAllFilters();
}
window.resetAllFilters = resetAllFilters;

// Drawer Tab Switcher (.drawer-tab / .drawer-tab-pane)
function switchDrawerTab(tabName) {
    const tabs = document.querySelectorAll('.drawer-tabs .drawer-tab');
    tabs.forEach(tab => {
        if (tab.getAttribute('data-tab') === tabName) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });

    const paneOverview = document.getElementById('pane_overview');
    const paneChat = document.getElementById('pane_chat');

    if (tabName === 'chat') {
        if (paneOverview) paneOverview.style.display = 'none';
        if (paneChat) {
            paneChat.style.display = 'block';
            const chatStream = document.getElementById('adminChatStream');
            if (chatStream) {
                setTimeout(() => { chatStream.scrollTop = chatStream.scrollHeight; }, 60);
            }
        }
    } else {
        if (paneOverview) paneOverview.style.display = 'block';
        if (paneChat) paneChat.style.display = 'none';
    }
}
window.switchDrawerTab = switchDrawerTab;

// Open Slide-Over Drawer
function openAdminTicketDrawer(ticketData) {
    if (typeof ticketData === 'string') {
        try {
            ticketData = JSON.parse(ticketData);
        } catch (e) {
            console.error('Invalid ticket data JSON', e);
            return;
        }
    }

    currentOpenTicket = ticketData;

    const drawer = document.getElementById('adminTicketDrawer');
    const backdrop = document.getElementById('drawerBackdrop');
    if (!drawer) return;

    // Header values
    const idEl = document.getElementById('drawerTicketId');
    if (idEl) idEl.textContent = ticketData.id;

    const statusBadge = document.getElementById('drawerTicketStatusBadge');
    if (statusBadge) {
        statusBadge.textContent = ticketData.status;
        statusBadge.className = 'badge ' + (ticketData.s_class || 'badge-status-progress');
    }

    const dateBadge = document.getElementById('drawerTicketDateBadge');
    if (dateBadge) dateBadge.textContent = ticketData.date;

    const subjEl = document.getElementById('drawerTicketSubject');
    if (subjEl) subjEl.textContent = ticketData.subject;

    // Requester Profile
    const req = ticketData.requester || {};
    const nmEl = document.getElementById('drawerReqName');
    if (nmEl) nmEl.textContent = req.name || 'Employee Member';
    const cdEl = document.getElementById('drawerReqCode');
    if (cdEl) cdEl.textContent = req.code || 'EMP-001';
    const dpEl = document.getElementById('drawerReqDept');
    if (dpEl) dpEl.textContent = req.department || 'General Staff';
    const emEl = document.getElementById('drawerReqEmail');
    if (emEl) emEl.textContent = req.email || 'employee@viros.in';
    const phEl = document.getElementById('drawerReqPhone');
    if (phEl) phEl.textContent = req.phone || '+91 98765 00000';
    const lcEl = document.getElementById('drawerReqLocation');
    if (lcEl) lcEl.textContent = req.location || 'Corporate Office';

    // Incident & Device Attributes
    const specStatus = document.getElementById('drawerSpecStatus');
    if (specStatus) {
        specStatus.textContent = ticketData.status;
        specStatus.className = 'badge ' + (ticketData.s_class || 'badge-status-progress');
    }

    const specUrgency = document.getElementById('drawerSpecUrgency');
    if (specUrgency) {
        specUrgency.textContent = ticketData.priority;
        specUrgency.className = 'badge ' + (ticketData.p_class || 'badge-medium');
    }

    const specAsset = document.getElementById('drawerSpecAsset');
    if (specAsset) specAsset.textContent = ticketData.asset || 'General Workstation / Laptop';

    const specCat = document.getElementById('drawerSpecCategory');
    if (specCat) specCat.textContent = ticketData.category || 'Hardware / IT Equipment';

    const specDate = document.getElementById('drawerSpecDate');
    if (specDate) specDate.textContent = ticketData.date || '';

    // Detailed Description
    const descEl = document.getElementById('drawerFullDesc');
    if (descEl) descEl.textContent = ticketData.description || ticketData.subject || 'No detailed issue description provided.';

    // Admin Triage Select Controls
    const statusSelect = document.getElementById('triageStatusSelect');
    if (statusSelect) {
        statusSelect.value = ticketData.s_filter || (ticketData.status.toLowerCase().includes('progress') ? 'in-progress' : (ticketData.status.toLowerCase().includes('open') ? 'open' : 'resolved'));
    }

    const prioritySelect = document.getElementById('triagePrioritySelect');
    if (prioritySelect) {
        prioritySelect.value = ticketData.priority || 'Medium';
    }

    // Render Conversation Stream
    renderDrawerChatStream(ticketData);

    // Fetch fresh live messages asynchronously from dedicated ticket_messages table
    fetch(`api/tickets.php?action=get_messages&ticket_no=${encodeURIComponent(ticketData.id)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                ticketData.chat_thread = data.messages;
                if (currentOpenTicket && currentOpenTicket.id === ticketData.id) {
                    renderDrawerChatStream(ticketData);
                }
            }
        })
        .catch(e => console.warn('Could not refresh messages:', e));

    // Default to Overview tab
    switchDrawerTab('overview');

    // Show Drawer & Backdrop
    if (backdrop) {
        backdrop.classList.add('open');
    }
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';
}
window.openAdminTicketDrawer = openAdminTicketDrawer;

// Render Chat Conversation in Drawer
function renderDrawerChatStream(ticketData) {
    const chatStream = document.getElementById('adminChatStream');
    if (!chatStream) return;

    chatStream.innerHTML = '';
    let messageCount = 0;

    // 1. System Auto-ACK Event
    const sysEvent = document.createElement('div');
    sysEvent.className = 'chat-system-event';
    sysEvent.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Incident Registered in Service Desk Queue (${ticketData.date})</span>
    `;
    chatStream.appendChild(sysEvent);

    const reqInitials = (ticketData.requester && ticketData.requester.initials) ? ticketData.requester.initials : 'EM';
    const reqName = (ticketData.requester && ticketData.requester.name) ? ticketData.requester.name : 'Requester';

    // 2. Chat messages from separate ticket_messages table
    if (Array.isArray(ticketData.chat_thread) && ticketData.chat_thread.length > 0) {
        ticketData.chat_thread.forEach(msg => {
            const msgRow = document.createElement('div');
            const isOutgoing = (msg.type === 'admin' || msg.type === 'tech');
            msgRow.className = 'chat-message-row ' + (isOutgoing ? 'outgoing' : 'incoming');

            msgRow.innerHTML = `
                <div class="chat-bubble-wrap">
                    <div class="chat-meta">
                        <span class="chat-author">${escapeHtml(msg.author || (isOutgoing ? 'IT Support' : reqName))}</span>
                        <span class="chat-timestamp">${escapeHtml(msg.time || '')}</span>
                    </div>
                    <div class="chat-bubble">
                        ${escapeHtml(msg.text)}
                    </div>
                </div>
                <div class="chat-avatar ${isOutgoing ? 'tech' : 'user'}">${escapeHtml(msg.avatar || (isOutgoing ? 'IT' : reqInitials))}</div>
            `;
            chatStream.appendChild(msgRow);
            messageCount++;
        });
    } else {
        // Fallback: Initial Employee Note (Issue Description)
        const empBubble = document.createElement('div');
        empBubble.className = 'chat-message-row incoming';
        empBubble.innerHTML = `
            <div class="chat-avatar user">${reqInitials}</div>
            <div class="chat-bubble-wrap">
                <div class="chat-meta">
                    <span class="chat-author">${escapeHtml(reqName)}</span>
                    <span class="chat-timestamp">${escapeHtml(ticketData.date)}</span>
                </div>
                <div class="chat-bubble">
                    ${escapeHtml(ticketData.description || ticketData.subject)}
                </div>
            </div>
        `;
        chatStream.appendChild(empBubble);
        messageCount++;
    }

    // Update Chat count badge in drawer tab
    const chatBadge = document.getElementById('drawerChatCountBadge');
    if (chatBadge) {
        chatBadge.textContent = messageCount;
    }

    // Scroll to bottom
    setTimeout(() => {
        chatStream.scrollTop = chatStream.scrollHeight;
    }, 60);
}

// Close Drawer
function closeAdminTicketDrawer() {
    const drawer = document.getElementById('adminTicketDrawer');
    const backdrop = document.getElementById('drawerBackdrop');

    if (drawer) {
        drawer.classList.remove('open');
    }

    if (backdrop) {
        backdrop.classList.remove('open');
    }

    document.body.style.overflow = '';
}
window.closeAdminTicketDrawer = closeAdminTicketDrawer;

// Drawer Triage Save & Status Update
function initDrawerAdminActions() {
    const updateBtn = document.getElementById('btnUpdateAdminTriage');
    if (!updateBtn) return;

    updateBtn.addEventListener('click', function () {
        if (!currentOpenTicket) return;

        const newStatusKey = document.getElementById('triageStatusSelect').value;
        const newPriorityVal = document.getElementById('triagePrioritySelect').value;

        let statusText = 'In Progress';
        let statusClass = 'badge-status-progress';
        if (newStatusKey === 'open') {
            statusText = 'Open / Triage';
            statusClass = 'badge-status-open';
        } else if (newStatusKey === 'resolved') {
            statusText = 'Resolved & Closed';
            statusClass = 'badge-status-resolved';
        }

        let pClass = 'badge-medium';
        if (newPriorityVal === 'Urgent') pClass = 'badge-urgent';
        else if (newPriorityVal === 'High') pClass = 'badge-high';
        else if (newPriorityVal === 'Low') pClass = 'badge-low';

        // Update currentOpenTicket object
        currentOpenTicket.status = statusText;
        currentOpenTicket.s_class = statusClass;
        currentOpenTicket.s_filter = newStatusKey;
        currentOpenTicket.priority = newPriorityVal;
        currentOpenTicket.p_class = pClass;

        // Update UI inside drawer
        const statusBadge = document.getElementById('drawerTicketStatusBadge');
        if (statusBadge) {
            statusBadge.textContent = statusText;
            statusBadge.className = 'badge ' + statusClass;
        }

        const specStatus = document.getElementById('drawerSpecStatus');
        if (specStatus) {
            specStatus.textContent = statusText;
            specStatus.className = 'badge ' + statusClass;
        }

        const specUrgency = document.getElementById('drawerSpecUrgency');
        if (specUrgency) {
            specUrgency.textContent = newPriorityVal;
            specUrgency.className = 'badge ' + pClass;
        }

        // Update row in table
        const row = document.querySelector(`.ticket-data-row[data-id="${currentOpenTicket.id}"]`);
        if (row) {
            row.setAttribute('data-status', newStatusKey);
            row.setAttribute('data-urgency', newPriorityVal.toLowerCase());

            const statusCell = row.querySelector('.col-ticket-status');
            if (statusCell) {
                statusCell.innerHTML = `<span class="badge ${statusClass}" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">${statusText}</span>`;
            }

            const priorityCell = row.querySelector('.col-ticket-urgency');
            if (priorityCell) {
                priorityCell.innerHTML = `<span class="badge ${pClass}" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">${newPriorityVal}</span>`;
            }
        }

        // Add a System Note to the Chat Thread
        const chatStream = document.getElementById('adminChatStream');
        if (chatStream) {
            const eventBubble = document.createElement('div');
            eventBubble.className = 'chat-system-event';
            eventBubble.innerHTML = `
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Triage Updated: Status set to "${statusText}", Priority set to "${newPriorityVal}"</span>
            `;
            chatStream.appendChild(eventBubble);
            chatStream.scrollTop = chatStream.scrollHeight;
        }

        // Persist to backend API
        fetch('api/tickets.php?action=update_triage', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_no: currentOpenTicket.id,
                status: statusText,
                priority: newPriorityVal
            })
        }).catch(err => console.warn('Triage update save error:', err));

        if (typeof showPortalToast === 'function') {
            showPortalToast(`Ticket ${currentOpenTicket.id} triage updated successfully!`, 'success');
        } else if (typeof showToast === 'function') {
            showToast(`Ticket ${currentOpenTicket.id} triage updated.`, 'success');
        } else {
            alert(`Ticket ${currentOpenTicket.id} updated: Status is now "${statusText}"!`);
        }
    });
}

// Drawer Chat Reply Submission
function initDrawerChatReply() {
    const form = document.getElementById('adminDrawerChatForm');
    const input = document.getElementById('adminDrawerChatInput');
    const chatStream = document.getElementById('adminChatStream');

    if (!form || !input || !chatStream) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        const nowFormatted = new Date().toLocaleString('en-GB', { 
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
                    <span class="chat-author">IT Service Desk Admin</span>
                    <span class="chat-timestamp">${nowFormatted}</span>
                </div>
                <div class="chat-bubble">
                    ${escapeHtml(text)}
                </div>
            </div>
            <div class="chat-avatar admin">ADM</div>
        `;

        chatStream.appendChild(newMsgRow);
        input.value = '';
        chatStream.scrollTop = chatStream.scrollHeight;

        // Increment Chat badge count
        const chatBadge = document.getElementById('drawerChatCountBadge');
        if (chatBadge) {
            const currentCount = parseInt(chatBadge.textContent, 10) || 0;
            chatBadge.textContent = currentCount + 1;
        }

        // Persist admin message to backend API
        fetch('api/tickets.php?action=reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ticket_no: currentOpenTicket.id,
                message: text,
                type: 'admin',
                author: 'IT Service Desk Admin'
            })
        }).catch(err => console.warn('Admin reply save error:', err));

        if (typeof showPortalToast === 'function') {
            showPortalToast('Message posted to ticket conversation thread.', 'success');
        } else if (typeof showToast === 'function') {
            showToast('Message sent to employee.', 'success');
        }
    });
}

// Export Filtered Table to CSV (matching exportAssetsCsv from assets.js)
function exportTicketsCSV() {
    const visibleRows = Array.from(document.querySelectorAll('.ticket-data-row')).filter(r => r.style.display !== 'none');
    if (visibleRows.length === 0) {
        if (typeof showPortalToast === 'function') showPortalToast('No visible tickets to export.', 'warning');
        else if (typeof showToast === 'function') showToast('No visible tickets to export.', 'warning');
        return;
    }

    let csvContent = 'data:text/csv;charset=utf-8,';
    csvContent += 'Ticket ID,Incident Date,Requester Name,Employee Code,Department,Subject,Category,Urgency,Affected Device,Status\r\n';

    visibleRows.forEach(row => {
        const id = row.querySelector('.asset-tag-badge')?.textContent.trim() || '';
        const date = row.querySelector('td:first-child span:nth-child(2)')?.textContent.trim() || '';
        const name = row.querySelector('.assignee-cell div > div:first-child')?.textContent.trim() || '';
        const code = row.querySelector('.assignee-cell div > div:nth-child(2) span:first-child')?.textContent.trim() || '';
        const dept = row.querySelector('.assignee-cell div > div:nth-child(2) span:last-child')?.textContent.trim() || '';
        const subject = (row.querySelector('.asset-name-title')?.textContent.trim() || '').replace(/"/g, '""');
        const category = (row.querySelector('td:nth-child(3) span:last-child')?.textContent.trim() || '').replace(/"/g, '""');
        const urgency = row.querySelector('.col-ticket-urgency .badge')?.textContent.trim() || '';
        const asset = (row.querySelector('.category-pill')?.textContent.trim() || '').replace(/"/g, '""');
        const status = row.querySelector('.col-ticket-status .badge')?.textContent.trim() || '';

        csvContent += `"${id}","${date}","${name}","${code}","${dept}","${subject}","${category}","${urgency}","${asset}","${status}"\r\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `VIROS_Support_Tickets_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    if (typeof showPortalToast === 'function') {
        showPortalToast('Tickets exported to CSV successfully.', 'success');
    } else if (typeof showToast === 'function') {
        showToast('Tickets exported to CSV.', 'success');
    }
}
window.exportTicketsCSV = exportTicketsCSV;

// ESC Key closes Drawer
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const drawer = document.getElementById('adminTicketDrawer');
        if (drawer && drawer.classList.contains('open')) {
            closeAdminTicketDrawer();
        }
    }
});

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
