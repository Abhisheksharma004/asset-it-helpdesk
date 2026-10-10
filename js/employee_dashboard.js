/**
 * VIROS Asset & IT Service Desk - Employee Dashboard Controller
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Category Tab Filter for Assigned Items
    const tabBtns = document.querySelectorAll('.emp-tab-btn');
    const tableRows = document.querySelectorAll('.emp-item-row');
    const searchInput = document.getElementById('empItemSearchInput');

    function filterItems() {
        const activeTab = document.querySelector('.emp-tab-btn.active')?.dataset.filter || 'all';
        const query = (searchInput?.value || '').trim().toLowerCase();

        let visibleCount = 0;

        tableRows.forEach(row => {
            const itemType = row.dataset.type || '';
            const rowText = row.textContent.toLowerCase();

            const matchesTab = (activeTab === 'all') || (itemType === activeTab);
            const matchesQuery = !query || rowText.includes(query);

            if (matchesTab && matchesQuery) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noDataRow = document.getElementById('empNoItemsRow');
        if (noDataRow) {
            noDataRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterItems();
        });
    });

    window.filterEmployeeTab = function (tabType) {
        const targetBtn = document.querySelector(`.emp-tab-btn[data-filter="${tabType}"]`);
        if (targetBtn) {
            targetBtn.click();
            document.getElementById('empAllocatedTable')?.scrollIntoView({ behavior: 'smooth' });
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', filterItems);
    }

    // 2. Modals Helper
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    // Close buttons
    document.querySelectorAll('.modal-close-btn, .modal-cancel-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal-overlay');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
            }
        });
    });

    // Close on overlay backdrop click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.style.display = 'none';
                this.classList.remove('active');
            }
        });
    });

    // ESC to close
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(m => {
                m.style.display = 'none';
                m.classList.remove('active');
            });
        }
    });

    // 3. Quick Action Buttons Bindings
    window.openEmployeeTicketModal = function (assetTag = '') {
        const assetSelect = document.getElementById('ticketAssetSelect');
        if (assetSelect && assetTag) {
            assetSelect.value = assetTag;
        }
        openModal('employeeTicketModal');
    };

    window.openEmployeeRequestModal = function (itemCategory = '') {
        const catSelect = document.getElementById('requestCategorySelect');
        if (catSelect && itemCategory) {
            catSelect.value = itemCategory;
        }
        openModal('employeeRequestModal');
    };

    window.openEmployeeHandoverSlip = function (assetId = '') {
        openModal('employeeHandoverSlipModal');
    };

    window.openEmployeeProfileModal = function () {
        openModal('employeeProfileModal');
    };

    window.scrollToMyAssets = function () {
        const sec = document.getElementById('secMyAssets');
        if (sec) {
            sec.scrollIntoView({ behavior: 'smooth' });
            sec.style.transition = 'box-shadow 0.4s ease';
            sec.style.boxShadow = '0 0 0 3px rgba(0, 147, 167, 0.4)';
            setTimeout(() => sec.style.boxShadow = '', 1500);
        }
    };

    window.scrollToMyTickets = function () {
        const sec = document.getElementById('secMyTickets');
        if (sec) {
            sec.scrollIntoView({ behavior: 'smooth' });
            sec.style.transition = 'box-shadow 0.4s ease';
            sec.style.boxShadow = '0 0 0 3px rgba(0, 147, 167, 0.4)';
            setTimeout(() => sec.style.boxShadow = '', 1500);
        }
    };

    window.printHandoverSlip = function () {
        window.print();
    };

    // 4. Form Submissions (Demo Toast & Interactivity)
    const ticketForm = document.getElementById('employeeTicketForm');
    if (ticketForm) {
        ticketForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const subject = document.getElementById('ticketSubjectInput')?.value || 'Support Request';
            const ticketId = 'TKT-' + Math.floor(1000 + Math.random() * 9000);

            closeModal('employeeTicketModal');
            ticketForm.reset();

            if (typeof showToast === 'function') {
                showToast(`Ticket #${ticketId} created successfully! IT Desk will respond within 2 hours.`, 'success');
            } else {
                alert(`Ticket #${ticketId} created successfully!`);
            }
        });
    }

    const requestForm = document.getElementById('employeeRequestForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const itemName = document.getElementById('requestItemNameInput')?.value || 'Hardware/Peripherals';

            closeModal('employeeRequestModal');
            requestForm.reset();

            if (typeof showToast === 'function') {
                showToast(`Request for "${itemName}" has been submitted to IT Procurement!`, 'success');
            } else {
                alert(`Request for "${itemName}" submitted!`);
            }
        });
    }

    // 5. Employee Profile Switcher (For rapid testing / demo)
    const empSelector = document.getElementById('employeeProfileSelector');
    if (empSelector) {
        empSelector.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('emp', this.value);
            window.location.href = url.toString();
        });
    }
});
