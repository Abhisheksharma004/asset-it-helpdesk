/**
 * Dashboard UI Interactions & Events
 * Asset Management & IT Service Desk
 */

document.addEventListener("DOMContentLoaded", function () {
    
    // Elements
    const sidebar = document.getElementById("sidebar");
    const mainWrapper = document.getElementById("mainWrapper");
    const sidebarToggleBtn = document.getElementById("sidebarToggleBtn");
    
    const openTicketModalBtn = document.getElementById("openTicketModalBtn");
    const closeTicketModalBtn = document.getElementById("closeTicketModalBtn");
    const cancelTicketModalBtn = document.getElementById("cancelTicketModalBtn");
    const ticketModal = document.getElementById("ticketModal");
    const newTicketForm = document.getElementById("newTicketForm");
    
    const globalSearchInput = document.getElementById("globalSearchInput");
    const ticketsTable = document.getElementById("ticketsTable");

    // 1. Sidebar Toggle (Desktop collapse & Mobile drawer)
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener("click", function () {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle("mobile-open");
            } else {
                sidebar.classList.toggle("collapsed");
                mainWrapper.classList.toggle("sidebar-collapsed");
            }
        });
    }

    // Close mobile sidebar on window resize if enlarged
    window.addEventListener("resize", function () {
        if (window.innerWidth > 768) {
            sidebar.classList.remove("mobile-open");
        }
    });

    // 2. Sidebar Dropdown Submenus
    const dropdownToggles = document.querySelectorAll(".nav-item-dropdown .dropdown-toggle");
    dropdownToggles.forEach(function (toggle) {
        toggle.addEventListener("click", function (e) {
            e.preventDefault();
            const parent = this.closest(".nav-item-dropdown");

            // If sidebar is collapsed on desktop, expand it so submenu is visible
            if (sidebar && sidebar.classList.contains("collapsed")) {
                sidebar.classList.remove("collapsed");
                if (mainWrapper) mainWrapper.classList.remove("sidebar-collapsed");
            }

            // Optional: close other dropdowns (accordion effect)
            document.querySelectorAll(".nav-item-dropdown.open").forEach(function (openItem) {
                if (openItem !== parent) {
                    openItem.classList.remove("open");
                }
            });

            parent.classList.toggle("open");
        });
    });

    // 3. Quick Ticket Modal
    function openModal() {
        ticketModal.classList.add("active");
        const titleInput = document.getElementById("ticketTitle");
        if (titleInput) titleInput.focus();
    }

    function closeModal() {
        ticketModal.classList.remove("active");
        newTicketForm.reset();
    }

    if (openTicketModalBtn) openTicketModalBtn.addEventListener("click", openModal);
    if (closeTicketModalBtn) closeTicketModalBtn.addEventListener("click", closeModal);
    if (cancelTicketModalBtn) cancelTicketModalBtn.addEventListener("click", closeModal);

    // Close modal on overlay background click
    ticketModal.addEventListener("click", function (e) {
        if (e.target === ticketModal) {
            closeModal();
        }
    });

    // Escape key to close modal
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && ticketModal.classList.contains("active")) {
            closeModal();
        }
    });

    // 3. New Ticket Submission
    if (newTicketForm) {
        newTicketForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const title = document.getElementById("ticketTitle").value.trim();
            const category = document.getElementById("ticketCategory").value;
            const priority = document.getElementById("ticketPriority").value;
            const user = document.getElementById("ticketUser").value.trim();

            if (!title || !category || !user) {
                if (typeof showToast === "function") {
                    showToast("Please fill all required ticket fields.", "warning");
                }
                return;
            }

            // Generate random Ticket ID
            const newId = "#TK-" + Math.floor(1000 + Math.random() * 9000);

            // Priority badge styling
            let priorityBadge = `<span class="badge badge-medium">${priority}</span>`;
            if (priority === "Urgent") {
                priorityBadge = `<span class="badge badge-urgent">Urgent</span>`;
            } else if (priority === "Low") {
                priorityBadge = `<span class="badge badge-medium">Low</span>`;
            }

            // Add new row at top of tickets table
            const tbody = ticketsTable.querySelector("tbody");
            if (tbody) {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td><span class="ticket-id">${newId}</span></td>
                    <td>
                        <span class="ticket-subject">${escapeHtml(title)}</span>
                        <span class="ticket-category">${escapeHtml(category)}</span>
                    </td>
                    <td>${escapeHtml(user)}</td>
                    <td>${priorityBadge}</td>
                    <td><span class="badge badge-open">Open</span></td>
                    <td>
                        <button class="action-btn-sm" title="View Ticket" onclick="showToast('Ticket ${newId} opened', 'info')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </td>
                `;
                tbody.insertBefore(tr, tbody.firstChild);
            }

            closeModal();

            if (typeof showToast === "function") {
                showToast(`Ticket ${newId} created successfully!`, "success");
            }
        });
    }

    // 4. Live Table Search Filter
    if (globalSearchInput && ticketsTable) {
        globalSearchInput.addEventListener("input", function () {
            const query = this.value.toLowerCase().trim();
            const rows = ticketsTable.querySelectorAll("tbody tr");

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    }

});

// Global menu navigation handler
function handleMenuClick(event, sectionName) {
    event.preventDefault();

    // Update active class
    const links = document.querySelectorAll(".sidebar-menu .nav-link");
    links.forEach(l => l.classList.remove("active"));
    event.currentTarget.classList.add("active");

    if (typeof showToast === "function") {
        showToast(`Navigated to ${sectionName}`, "info");
    }
}

function escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}
