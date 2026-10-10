/**
 * VIROS Asset & IT Service Desk - Employee Assets Dedicated Scripts
 * Handles live search, category filtering, handover slip modal, and equipment request
 */

document.addEventListener('DOMContentLoaded', function () {
    initAssetFilters();
    initAssetSearch();
});

// Category Pill Filter
function initAssetFilters() {
    const pillBtns = document.querySelectorAll('.assets-pill-btn');
    const tableRows = document.querySelectorAll('.emp-asset-row');
    const emptyState = document.getElementById('assetsEmptyState');

    pillBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            pillBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filterType = this.getAttribute('data-filter');
            const searchVal = document.getElementById('assetSearchInput')?.value.toLowerCase().trim() || '';
            let visibleCount = 0;

            tableRows.forEach(row => {
                const rowType = row.getAttribute('data-type');
                const rowText = row.innerText.toLowerCase();

                const matchesFilter = (filterType === 'all' || rowType === filterType);
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
function initAssetSearch() {
    const searchInput = document.getElementById('assetSearchInput');
    const tableRows = document.querySelectorAll('.emp-asset-row');
    const emptyState = document.getElementById('assetsEmptyState');

    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        const searchVal = this.value.toLowerCase().trim();
        const activeFilterBtn = document.querySelector('.assets-pill-btn.active');
        const activeFilter = activeFilterBtn ? activeFilterBtn.getAttribute('data-filter') : 'all';
        let visibleCount = 0;

        tableRows.forEach(row => {
            const rowType = row.getAttribute('data-type');
            const rowText = row.innerText.toLowerCase();

            const matchesFilter = (activeFilter === 'all' || rowType === activeFilter);
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

// Open Handover Slip Modal
function openHandoverSlipModal(itemData) {
    const modal = document.getElementById('handoverSlipModal');
    if (!modal) return;

    // Populate data
    document.getElementById('slipAssetTag').textContent = itemData.tag || 'N/A';
    document.getElementById('slipAssetName').textContent = itemData.name || 'N/A';
    document.getElementById('slipAssetCategory').textContent = itemData.category || 'N/A';
    document.getElementById('slipAssetSerial').textContent = itemData.serial || 'N/A';
    document.getElementById('slipAssetSpecs').textContent = itemData.specs || 'N/A';
    document.getElementById('slipAssetDate').textContent = itemData.date || 'N/A';
    document.getElementById('slipAssetCondition').textContent = itemData.condition || 'Good';
    document.getElementById('slipAssetDocId').textContent = 'HS-' + (itemData.tag || '2026').replace(/[^0-9]/g, '').padEnd(6, '0');

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Print / Download Handover Slip
function printHandoverSlip() {
    window.print();
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
