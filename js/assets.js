/**
 * Asset Management Page Interactive Script
 * VIROS IT Asset & Service Desk Portal
 */

document.addEventListener('DOMContentLoaded', function () {
    // =========================================================================
    // 1. Initial Mock Asset Data Store
    // =========================================================================
    const initialAssets = [
        {
            id: 1,
            tag: 'AST2024001',
            name: 'MacBook Pro 16" M3 Max',
            category: 'Laptops',
            brand: 'Apple',
            model: 'MacBookPro18,1 (2023)',
            serial: 'C02G40PZMD6T',
            status: 'In Use',
            condition: 'Excellent',
            assignedTo: {
                name: 'Sarah Jenkins',
                empCode: 'EMP-1002',
                email: 'sarah.jenkins@viros.com',
                department: 'Software Engineering',
                role: 'Staff Engineer',
                assignedDate: '15 Jan 2024'
            },
            department: 'Software Engineering',
            location: 'HQ - New York',
            specs: {
                processor: 'Apple M3 Max (16-core)',
                ram: '36 GB Unified',
                storage: '1 TB NVMe SSD',
                os: 'macOS Sonoma 14.5',
                macAddress: 'F0:18:98:4C:AA:32',
                ipAddress: '10.20.104.42'
            },
            financials: {
                vendor: 'Apple Business Direct',
                poNumber: 'PO-2024-8901',
                purchaseDate: '2024-01-08',
                cost: 289900.00,
                warrantyExpiry: '2027-01-08'
            },
            history: [
                { date: '15 Jan 2024', title: 'Assigned to Sarah Jenkins', desc: 'Handed over during engineering tech refresh cycle.' },
                { date: '10 Jan 2024', title: 'Asset Tagged & Configured', desc: 'MDM enrolled via Jamf Pro, Jamf Connect & CrowdStrike installed.' },
                { date: '08 Jan 2024', title: 'Procured & Received', desc: 'Received from Apple Direct under PO-2024-8901.' }
            ],
            tickets: [
                { id: 'TKT-1082', title: 'External display resolution issue on dual 4K monitors', status: 'Resolved', date: '22 Feb 2024' }
            ]
        },
        {
            id: 2,
            tag: 'AST2024002',
            name: 'Dell XPS 15 9530',
            category: 'Laptops',
            brand: 'Dell',
            model: 'XPS 15 (2023 Edition)',
            serial: 'DELL-984210-X',
            status: 'In Use',
            condition: 'Excellent',
            assignedTo: {
                name: 'Marcus Vance',
                empCode: 'EMP-1045',
                email: 'marcus.v@viros.com',
                department: 'IT Infrastructure',
                role: 'Senior DevOps Architect',
                assignedDate: '02 Feb 2024'
            },
            department: 'IT Infrastructure',
            location: 'Austin Hub',
            specs: {
                processor: 'Intel Core i9-13900H (14-Core)',
                ram: '32 GB DDR5 4800MHz',
                storage: '1 TB M.2 PCIe Gen4 NVMe',
                os: 'Ubuntu 24.04 LTS / Win 11 Pro Dual',
                macAddress: '3C:52:82:1D:90:E5',
                ipAddress: '10.30.22.18'
            },
            financials: {
                vendor: 'Dell Enterprise Solutions',
                poNumber: 'PO-2024-7721',
                purchaseDate: '2024-01-18',
                cost: 219900.00,
                warrantyExpiry: '2027-01-18'
            },
            history: [
                { date: '02 Feb 2024', title: 'Issued to Marcus Vance', desc: 'DevOps setup with Linux kernel headers and Docker.' },
                { date: '20 Jan 2024', title: 'Initial Asset Staging', desc: 'Configured BIOS password, BitLocker, secure boot.' }
            ],
            tickets: []
        },
        {
            id: 3,
            tag: 'AST2024003',
            name: 'Dell PowerEdge R750 Server',
            category: 'Servers',
            brand: 'Dell',
            model: 'PowerEdge R750 2U Rack',
            serial: 'PE-750-SRV-09',
            status: 'In Use',
            condition: 'Excellent',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'Singapore DC',
            specs: {
                processor: '2x Intel Xeon Gold 6338 (64-threads)',
                ram: '128 GB ECC DDR4 RDIMM',
                storage: '4x 1.92TB NVMe PCIe Gen4 in RAID 10',
                os: 'VMware ESXi 8.0 Update 2',
                macAddress: '00:1E:67:D8:1A:F0',
                ipAddress: '172.16.10.15'
            },
            financials: {
                vendor: 'Dell Global Infrastructure',
                poNumber: 'PO-2023-4100',
                purchaseDate: '2023-11-12',
                cost: 950000.00,
                warrantyExpiry: '2028-11-12'
            },
            history: [
                { date: '15 Dec 2023', title: 'Deployed in Singapore Rack 04B', desc: 'Integrated into primary hypervisor cluster.' }
            ],
            tickets: []
        },
        {
            id: 4,
            tag: 'AST2024004',
            name: 'Lenovo ThinkPad X1 Carbon Gen 11',
            category: 'Laptops',
            brand: 'Lenovo',
            model: 'ThinkPad X1 Carbon Gen 11',
            serial: 'PF-39X1-LNV',
            status: 'Available',
            condition: 'Brand New',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'HQ - New York',
            specs: {
                processor: 'Intel Core i7-1365U vPro',
                ram: '16 GB LPDDR5',
                storage: '512 GB NVMe Opal2',
                os: 'Windows 11 Pro Enterprise',
                macAddress: 'E8:80:88:51:7A:B4',
                ipAddress: 'DHCP Reserved'
            },
            financials: {
                vendor: 'Insight Direct IT',
                poNumber: 'PO-2024-9122',
                purchaseDate: '2024-02-10',
                cost: 149900.00,
                warrantyExpiry: '2027-02-10'
            },
            history: [
                { date: '12 Feb 2024', title: 'Unboxed & Enrolled', desc: 'Stored in IT Depot Secure Cage Room 3.' }
            ],
            tickets: []
        },
        {
            id: 5,
            tag: 'AST2024005',
            name: 'Cisco Catalyst 9300 48-Port PoE+',
            category: 'Networking',
            brand: 'Cisco',
            model: 'C9300-48P-A',
            serial: 'FOC2408W0AB',
            status: 'In Use',
            condition: 'Good',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'London Office',
            specs: {
                processor: 'Cisco UADP 2.0 ASIC',
                ram: '16 GB Flash / 8 GB DRAM',
                storage: 'Internal Flash Memory',
                os: 'Cisco IOS XE 17.9',
                macAddress: '70:69:79:B0:12:00',
                ipAddress: '10.50.1.2'
            },
            financials: {
                vendor: 'CDW UK',
                poNumber: 'PO-2023-1109',
                purchaseDate: '2023-05-14',
                cost: 410000.00,
                warrantyExpiry: '2026-05-14'
            },
            history: [
                { date: '20 May 2023', title: 'Installed in IDF-London-2', desc: 'Serves London Floor 2 workstations and APs.' }
            ],
            tickets: []
        },
        {
            id: 6,
            tag: 'AST2024006',
            name: 'Apple iMac 24" M3',
            category: 'Desktops',
            brand: 'Apple',
            model: 'iMac 24 (4.5K Retina Display)',
            serial: 'C02K98LLM3',
            status: 'In Use',
            condition: 'Excellent',
            assignedTo: {
                name: 'Elena Rostova',
                empCode: 'EMP-1108',
                email: 'elena.rostova@viros.com',
                department: 'Design & Creative',
                role: 'Lead UI/UX Designer',
                assignedDate: '01 Mar 2024'
            },
            department: 'Design & Creative',
            location: 'HQ - New York',
            specs: {
                processor: 'Apple M3 (8-core CPU / 10-core GPU)',
                ram: '24 GB Unified Memory',
                storage: '512 GB SSD',
                os: 'macOS Sonoma 14.4',
                macAddress: 'F4:D4:88:9C:11:78',
                ipAddress: '10.20.106.88'
            },
            financials: {
                vendor: 'Apple Business Direct',
                poNumber: 'PO-2024-9400',
                purchaseDate: '2024-02-25',
                cost: 179900.00,
                warrantyExpiry: '2027-02-25'
            },
            history: [
                { date: '01 Mar 2024', title: 'Assigned to Elena Rostova', desc: 'Design workstation with Adobe CC & Figma.' }
            ],
            tickets: []
        },
        {
            id: 7,
            tag: 'AST2024007',
            name: 'Dell UltraSharp 32" 4K USB-C Hub Monitor',
            category: 'Monitors',
            brand: 'Dell',
            model: 'U3223QE PremierColor',
            serial: 'CN-0M9Y87-74261',
            status: 'In Use',
            condition: 'Good',
            assignedTo: {
                name: 'Sarah Jenkins',
                empCode: 'EMP-1002',
                email: 'sarah.jenkins@viros.com',
                department: 'Software Engineering',
                role: 'Staff Engineer',
                assignedDate: '15 Jan 2024'
            },
            department: 'Software Engineering',
            location: 'HQ - New York',
            specs: {
                processor: 'IPS Black Display Engine',
                ram: 'N/A',
                storage: 'Integrated 90W USB-C PD Hub',
                os: 'Firmware vM2T102',
                macAddress: 'Ethernet Pass-thru 3C:52:82:11:00',
                ipAddress: 'Bridged via Thunderbolt'
            },
            financials: {
                vendor: 'Dell Enterprise Solutions',
                poNumber: 'PO-2024-8902',
                purchaseDate: '2024-01-08',
                cost: 69900.00,
                warrantyExpiry: '2027-01-08'
            },
            history: [
                { date: '15 Jan 2024', title: 'Desk Docking Station Pair', desc: 'Paired with AST2024001.' }
            ],
            tickets: []
        },
        {
            id: 8,
            tag: 'AST2024008',
            name: 'HP EliteBook 840 G10',
            category: 'Laptops',
            brand: 'HP',
            model: 'EliteBook 840 G10',
            serial: '5CG3290ABC',
            status: 'Under Maintenance',
            condition: 'Fair',
            assignedTo: {
                name: 'David Chen',
                empCode: 'EMP-1033',
                email: 'david.chen@viros.com',
                department: 'Finance',
                role: 'Finance Director',
                assignedDate: '14 Oct 2023'
            },
            department: 'Finance',
            location: 'Austin Hub',
            specs: {
                processor: 'Intel Core i7-1365U',
                ram: '16 GB DDR5',
                storage: '512 GB SSD',
                os: 'Windows 11 Enterprise',
                macAddress: 'B8:85:84:10:98:C3',
                ipAddress: '10.30.22.44'
            },
            financials: {
                vendor: 'HP Direct',
                poNumber: 'PO-2023-3881',
                purchaseDate: '2023-10-01',
                cost: 139900.00,
                warrantyExpiry: '2026-10-01'
            },
            history: [
                { date: '28 Sep 2024', title: 'Submitted to IT Depot', desc: 'Screen flicker & USB-C port physical loose pin.' },
                { date: '14 Oct 2023', title: 'Assigned to David Chen', desc: 'Standard executive deployment.' }
            ],
            tickets: [
                { id: 'TKT-1440', title: 'Display flickers when lid is adjusted >90 degrees', status: 'In Progress', date: '28 Sep 2024' }
            ]
        },
        {
            id: 9,
            tag: 'AST2024009',
            name: 'Apple iPad Pro 12.9" M2 Cellular',
            category: 'Tablets & Mobile',
            brand: 'Apple',
            model: 'iPad Pro 12.9 6th Gen (Wi-Fi + 5G)',
            serial: 'DMPF7829Q921',
            status: 'In Use',
            condition: 'Excellent',
            assignedTo: {
                name: 'Rachel Adams',
                empCode: 'EMP-1008',
                email: 'rachel.a@viros.com',
                department: 'Executive Management',
                role: 'VP Operations',
                assignedDate: '20 Jan 2024'
            },
            department: 'Executive Management',
            location: 'HQ - New York',
            specs: {
                processor: 'Apple M2 (8-core CPU)',
                ram: '16 GB RAM',
                storage: '256 GB Liquid Retina XDR',
                os: 'iPadOS 17.5',
                macAddress: 'DC:A9:04:77:23:FE',
                ipAddress: '10.20.108.92'
            },
            financials: {
                vendor: 'Apple Business Direct',
                poNumber: 'PO-2024-8995',
                purchaseDate: '2024-01-12',
                cost: 114900.00,
                warrantyExpiry: '2025-10-25' // Expiring soon (<30 days mock)
            },
            history: [
                { date: '20 Jan 2024', title: 'Executive Mobilization Package', desc: 'Issued with Apple Pencil 2 & Magic Keyboard.' }
            ],
            tickets: []
        },
        {
            id: 10,
            tag: 'AST2024010',
            name: 'Lenovo ThinkPad T14 Gen 4',
            category: 'Laptops',
            brand: 'Lenovo',
            model: 'ThinkPad T14 Gen 4 AMD',
            serial: 'PF-478K20-LNV',
            status: 'Available',
            condition: 'Good',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'Austin Hub',
            specs: {
                processor: 'AMD Ryzen 7 PRO 7840U',
                ram: '32 GB LPDDR5x',
                storage: '1 TB NVMe SSD',
                os: 'Windows 11 Pro Enterprise',
                macAddress: '48:2A:E3:42:19:6F',
                ipAddress: 'DHCP Pool'
            },
            financials: {
                vendor: 'Insight Direct IT',
                poNumber: 'PO-2023-6620',
                purchaseDate: '2023-08-15',
                cost: 124900.00,
                warrantyExpiry: '2026-08-15'
            },
            history: [
                { date: '05 Sep 2024', title: 'Returned by Contractor', desc: 'Checked in, sanitized, reimaged and placed in ready stock.' }
            ],
            tickets: []
        },
        {
            id: 11,
            tag: 'AST2024011',
            name: 'Zebra ZT411 Industrial Label Printer',
            category: 'Printers',
            brand: 'Zebra Technologies',
            model: 'ZT411 Thermal Transfer 300dpi',
            serial: 'ZEB-99214-IND',
            status: 'In Use',
            condition: 'Good',
            assignedTo: null,
            department: 'Operations',
            location: 'London Office',
            specs: {
                processor: 'ARM Cortex A9 800MHz',
                ram: '512 MB RAM / 2 GB Flash',
                storage: 'Onboard Flash storage',
                os: 'Link-OS v6.8',
                macAddress: '00:07:4D:99:A2:30',
                ipAddress: '10.50.4.19'
            },
            financials: {
                vendor: 'BarcodesInc UK',
                poNumber: 'PO-2023-2940',
                purchaseDate: '2023-04-10',
                cost: 175000.00,
                warrantyExpiry: '2026-04-10'
            },
            history: [
                { date: '18 Apr 2023', title: 'Asset Tagging Station Configured', desc: 'Primary printer for IT asset QR labels.' }
            ],
            tickets: []
        },
        {
            id: 12,
            tag: 'AST2024012',
            name: 'Fortinet FortiGate 100F Firewall',
            category: 'Networking',
            brand: 'Fortinet',
            model: 'FG-100F Next-Gen Security Gateway',
            serial: 'FG100FTK23-908',
            status: 'In Use',
            condition: 'Excellent',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'HQ - New York',
            specs: {
                processor: 'Fortinet SOC4 Security Processor',
                ram: '8 GB Hardware Memory',
                storage: 'Dual Power Supply Unit',
                os: 'FortiOS 7.4.3',
                macAddress: '70:4C:A5:18:22:90',
                ipAddress: '10.20.0.1'
            },
            financials: {
                vendor: 'Presidio Enterprise Solutions',
                poNumber: 'PO-2023-5501',
                purchaseDate: '2023-09-01',
                cost: 480000.00,
                warrantyExpiry: '2026-09-01'
            },
            history: [
                { date: '12 Sep 2023', title: 'Core Edge Routing Cutover', desc: 'Configured redundant IPsec VPN and SD-WAN.' }
            ],
            tickets: []
        },
        {
            id: 13,
            tag: 'AST2024013',
            name: 'Apple MacBook Air 15" M2',
            category: 'Laptops',
            brand: 'Apple',
            model: 'MacBook Air 15 (2023 Midnight)',
            serial: 'C02HQ81LMD91',
            status: 'Available',
            condition: 'Brand New',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'London Office',
            specs: {
                processor: 'Apple M2 (8-core CPU / 10-core GPU)',
                ram: '16 GB Unified Memory',
                storage: '512 GB SSD',
                os: 'macOS Sonoma 14.5',
                macAddress: '3C:06:30:19:D4:56',
                ipAddress: 'DHCP Pool'
            },
            financials: {
                vendor: 'Apple Business UK',
                poNumber: 'PO-2024-9810',
                purchaseDate: '2024-03-01',
                cost: 139900.00,
                warrantyExpiry: '2027-03-01'
            },
            history: [
                { date: '05 Mar 2024', title: 'Received & Staged', desc: 'Assigned to London general onboarding buffer pool.' }
            ],
            tickets: []
        },
        {
            id: 14,
            tag: 'AST2024014',
            name: 'Microsoft Surface Pro 9',
            category: 'Tablets & Mobile',
            brand: 'Microsoft',
            model: 'Surface Pro 9 Platinum',
            serial: '029384729153',
            status: 'Reserved',
            condition: 'Excellent',
            assignedTo: null,
            department: 'Human Resources',
            location: 'HQ - New York',
            specs: {
                processor: 'Intel Core i7-1255U (10-Core)',
                ram: '16 GB LPDDR5',
                storage: '256 GB Removable SSD',
                os: 'Windows 11 Pro',
                macAddress: '58:11:22:98:AC:31',
                ipAddress: 'DHCP Pool'
            },
            financials: {
                vendor: 'Microsoft Commercial Direct',
                poNumber: 'PO-2024-8840',
                purchaseDate: '2024-01-05',
                cost: 119900.00,
                warrantyExpiry: '2026-01-05'
            },
            history: [
                { date: '10 Jan 2024', title: 'Reserved for New HR Lead', desc: 'Hold until joining date on 15 Oct.' }
            ],
            tickets: []
        },
        {
            id: 15,
            tag: 'AST2024015',
            name: 'Dell Latitude 5420',
            category: 'Laptops',
            brand: 'Dell',
            model: 'Latitude 5420 Rugged Finish',
            serial: 'DELL-5420-OLD',
            status: 'Retired',
            condition: 'Damaged',
            assignedTo: null,
            department: 'IT Infrastructure',
            location: 'Austin Hub',
            specs: {
                processor: 'Intel Core i5-1135G7',
                ram: '8 GB DDR4',
                storage: '256 GB SSD (Wiped & Certified)',
                os: 'Decommissioned',
                macAddress: '10:65:30:22:11:FE',
                ipAddress: 'N/A'
            },
            financials: {
                vendor: 'Dell Financial Services',
                poNumber: 'PO-2020-0012',
                purchaseDate: '2020-03-10',
                cost: 89900.00,
                warrantyExpiry: '2023-03-10'
            },
            history: [
                { date: '12 Jan 2024', title: 'NIST 800-88 Wiped & Retired', desc: 'Disposed via certified e-waste recycling vendor.' }
            ],
            tickets: []
        }
    ];

    // Working dataset in memory (Synced with MSSQL database)
    let assetsData = (typeof window !== 'undefined' && Array.isArray(window.INITIAL_ASSETS) && window.INITIAL_ASSETS.length > 0)
        ? window.INITIAL_ASSETS
        : [...initialAssets];

    // Helper: Build API URL
    function getApiUrl(params = {}) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('preview')) {
            params.preview = 1;
        }
        const qs = new URLSearchParams(params).toString();
        return 'api/assets.php' + (qs ? '?' + qs : '');
    }

    // Helper: Sync / Reload Assets from Database
    function loadAssetsFromDB(callback) {
        fetch(getApiUrl())
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.assets)) {
                    assetsData = data.assets;
                    renderAssets();
                    updateKPIs();
                    if (typeof callback === 'function') callback();
                }
            })
            .catch(err => {
                console.warn('Could not load assets from API:', err);
                if (typeof callback === 'function') callback();
            });
    }

    // State Variables
    let currentFilterStatus = 'all';
    let currentSearchQuery = '';
    let currentCategoryFilter = 'all';
    let currentDeptFilter = 'all';
    let currentLocationFilter = 'all';
    let currentConditionFilter = 'all';
    let currentViewMode = 'table';
    let selectedAssetIds = new Set();
    let currentPage = 1;
    const pageSize = 10;
    let activeDrawerAssetId = null;

    // DOM Elements
    const assetTableBody = document.getElementById('assetTableBody');
    const assetGridContainer = document.getElementById('assetGridContainer');
    const assetTableView = document.getElementById('assetTableView');
    const searchInput = document.getElementById('assetSearchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const deptFilter = document.getElementById('deptFilter');
    const locationFilter = document.getElementById('locationFilter');
    const conditionFilter = document.getElementById('conditionFilter');
    const statusTabBtns = document.querySelectorAll('.status-tab-btn');
    const selectAllCheckbox = document.getElementById('selectAllAssets');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const bulkCountEl = document.getElementById('bulkSelectedCount');
    const viewTableBtn = document.getElementById('viewTableBtn');
    const viewGridBtn = document.getElementById('viewGridBtn');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationControls = document.getElementById('paginationControls');

    // Drawer Elements
    const assetDrawer = document.getElementById('assetDrawer');
    const drawerBackdrop = document.getElementById('drawerBackdrop');
    const closeDrawerBtn = document.getElementById('closeDrawerBtn');

    // =========================================================================
    // 2. Helper Functions
    // =========================================================================

    function getCategoryIconClass(category) {
        switch (category) {
            case 'Laptops': return 'laptop';
            case 'Desktops': return 'laptop';
            case 'Servers': return 'server';
            case 'Networking': return 'network';
            case 'Monitors': return 'monitor';
            case 'Tablets & Mobile': return 'mobile';
            case 'Printers': return 'printer';
            default: return 'laptop';
        }
    }

    function getDeviceSvg(category) {
        switch (category) {
            case 'Servers':
                return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>`;
            case 'Networking':
                return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="12" rx="2"></rect><path d="M6 20h12"></path><path d="M12 16v4"></path></svg>`;
            case 'Monitors':
                return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>`;
            case 'Tablets & Mobile':
                return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>`;
            case 'Printers':
                return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>`;
            default:
                return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>`;
        }
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'In Use':
                return `<span class="asset-status-badge status-in-use"><span class="dot"></span>In Use</span>`;
            case 'Available':
                return `<span class="asset-status-badge status-available"><span class="dot"></span>In Stock</span>`;
            case 'Under Maintenance':
                return `<span class="asset-status-badge status-maintenance"><span class="dot"></span>Maintenance</span>`;
            case 'Reserved':
                return `<span class="asset-status-badge status-reserved"><span class="dot"></span>Reserved</span>`;
            case 'Retired':
                return `<span class="asset-status-badge status-retired"><span class="dot"></span>Retired</span>`;
            default:
                return `<span class="asset-status-badge">${status}</span>`;
        }
    }

    function getWarrantyBadge(expiryDateStr) {
        if (!expiryDateStr) return `<span class="warranty-pill">N/A</span>`;
        const expiry = new Date(expiryDateStr);
        const today = new Date('2024-10-03'); // Reference date
        const diffDays = Math.round((expiry - today) / (1000 * 60 * 60 * 24));

        if (diffDays < 0) {
            return `<span class="warranty-pill warranty-expired">Expired</span>`;
        } else if (diffDays <= 30) {
            return `<span class="warranty-pill warranty-expiring">Expiring (${diffDays}d)</span>`;
        } else {
            return `<span class="warranty-pill warranty-active">Active (${diffDays}d)</span>`;
        }
    }

    function getInitials(name) {
        if (!name) return 'NA';
        const parts = name.split(' ');
        let initials = '';
        for (let p of parts) {
            if (p) initials += p[0].toUpperCase();
            if (initials.length >= 2) break;
        }
        return initials;
    }

    function formatCurrency(val) {
        return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    // =========================================================================
    // 3. Filtering & Metrics Calculation
    // =========================================================================

    function getFilteredAssets() {
        return assetsData.filter(item => {
            // Status Tab Filter
            if (currentFilterStatus === 'in-use' && item.status !== 'In Use') return false;
            if (currentFilterStatus === 'available' && item.status !== 'Available') return false;
            if (currentFilterStatus === 'maintenance' && item.status !== 'Under Maintenance') return false;
            if (currentFilterStatus === 'reserved' && item.status !== 'Reserved') return false;
            if (currentFilterStatus === 'retired' && item.status !== 'Retired') return false;

            // Category Filter
            if (currentCategoryFilter !== 'all' && item.category !== currentCategoryFilter) return false;

            // Department Filter
            if (currentDeptFilter !== 'all' && item.department !== currentDeptFilter) return false;

            // Location Filter
            if (currentLocationFilter !== 'all' && item.location !== currentLocationFilter) return false;

            // Condition Filter
            if (currentConditionFilter !== 'all' && item.condition !== currentConditionFilter) return false;

            // Live Search Query
            if (currentSearchQuery.trim() !== '') {
                const q = currentSearchQuery.toLowerCase();
                const matchTag = item.tag ? item.tag.toLowerCase().includes(q) : false;
                const matchName = item.name.toLowerCase().includes(q);
                const matchSerial = item.serial.toLowerCase().includes(q);
                const matchBrand = item.brand.toLowerCase().includes(q);
                const matchModel = item.model.toLowerCase().includes(q);
                const matchUser = item.assignedTo ? item.assignedTo.name.toLowerCase().includes(q) : false;
                const matchDept = item.department.toLowerCase().includes(q);
                if (!matchTag && !matchName && !matchSerial && !matchBrand && !matchModel && !matchUser && !matchDept) {
                    return false;
                }
            }

            return true;
        });
    }

    function updateKPIs() {
        let total = assetsData.length;
        let inUse = 0;
        let available = 0;
        let maintenance = 0;
        let reserved = 0;
        let retired = 0;
        let expiringSoon = 0;
        let totalValue = 0;

        const refDate = new Date();

        assetsData.forEach(a => {
            if (a.status === 'In Use') inUse++;
            else if (a.status === 'Available') available++;
            else if (a.status === 'Under Maintenance') maintenance++;
            else if (a.status === 'Reserved') reserved++;
            else if (a.status === 'Retired') retired++;

            if (a.financials && a.financials.cost) {
                totalValue += a.financials.cost;
            }

            if (a.financials && a.financials.warrantyExpiry) {
                const exp = new Date(a.financials.warrantyExpiry);
                const diff = (exp - refDate) / (1000 * 60 * 60 * 24);
                if (diff >= 0 && diff <= 30) {
                    expiringSoon++;
                }
            }
        });

        // DOM elements
        const statTotalEl = document.getElementById('statTotalAssets');
        const statInUseEl = document.getElementById('statInUseAssets');
        const statAvailableEl = document.getElementById('statAvailableAssets');
        const statMaintEl = document.getElementById('statMaintenanceAssets');
        const statExpiringEl = document.getElementById('statExpiringAssets');
        const statValueEl = document.getElementById('statTotalValue');
        const headerBadgeEl = document.getElementById('headerAssetBadge');

        if (statTotalEl) statTotalEl.textContent = total;
        if (statInUseEl) statInUseEl.textContent = inUse;
        if (statAvailableEl) statAvailableEl.textContent = available;
        if (statMaintEl) statMaintEl.textContent = maintenance;
        if (statExpiringEl) statExpiringEl.textContent = expiringSoon;
        if (statValueEl) statValueEl.textContent = formatCurrency(totalValue);
        if (headerBadgeEl) headerBadgeEl.textContent = `${total} Assets`;

        // Update Tab Badges
        const tabAllBadge = document.getElementById('tabBadgeAll');
        const tabInUseBadge = document.getElementById('tabBadgeInUse');
        const tabAvailBadge = document.getElementById('tabBadgeAvail');
        const tabMaintBadge = document.getElementById('tabBadgeMaint');
        const tabResBadge = document.getElementById('tabBadgeRes');
        const tabRetBadge = document.getElementById('tabBadgeRet');

        if (tabAllBadge) tabAllBadge.textContent = total;
        if (tabInUseBadge) tabInUseBadge.textContent = inUse;
        if (tabAvailBadge) tabAvailBadge.textContent = available;
        if (tabMaintBadge) tabMaintBadge.textContent = maintenance;
        if (tabResBadge) tabResBadge.textContent = reserved;
        if (tabRetBadge) tabRetBadge.textContent = retired;
    }

    // =========================================================================
    // 4. Render Table & Grid Views
    // =========================================================================

    function renderAssets() {
        const filtered = getFilteredAssets();
        const total = filtered.length;

        // Pagination calculation
        const totalPages = Math.ceil(total / pageSize) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, total);
        const pagedItems = filtered.slice(startIndex, endIndex);

        // Render Table View
        if (assetTableBody) {
            if (pagedItems.length === 0) {
                assetTableBody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                            <div style="font-size: 38px; margin-bottom: 12px; opacity: 0.6;">📦</div>
                            <h4 style="color: var(--text-primary); font-size: 15px; margin-bottom: 4px;">No matching assets found</h4>
                            <p style="font-size: 13px;">Try clearing search filters or changing status criteria.</p>
                            <button class="btn-secondary" style="margin-top: 14px; padding: 6px 14px;" onclick="resetAllFilters()">Reset All Filters</button>
                        </td>
                    </tr>
                `;
            } else {
                assetTableBody.innerHTML = pagedItems.map(item => {
                    const isChecked = selectedAssetIds.has(item.id);
                    const iconClass = getCategoryIconClass(item.category);
                    const iconSvg = getDeviceSvg(item.category);

                    let assigneeHtml = '';
                    if (item.assignedTo) {
                        assigneeHtml = `
                            <div class="assignee-meta">
                                <span class="assignee-name">${escapeHtml(item.assignedTo.name)}</span>
                                <span class="assignee-dept">${escapeHtml(item.assignedTo.department)}</span>
                            </div>
                        `;
                    } else {
                        assigneeHtml = `<span class="unassigned-badge">In Stock / Pool</span>`;
                    }

                    return `
                        <tr class="${isChecked ? 'row-selected' : ''}" data-id="${item.id}">
                            <td class="checkbox-cell">
                                <input type="checkbox" class="custom-checkbox asset-item-checkbox" data-id="${item.id}" ${isChecked ? 'checked' : ''}>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <span class="asset-name-title" onclick="openAssetDrawer(${item.id})">
                                        ${escapeHtml(item.name)}
                                    </span>
                                    <div class="asset-tag-cell">
                                        <span class="asset-tag-badge" onclick="openAssetDrawer(${item.id})" title="Click to view asset details">${escapeHtml(item.tag)}</span>
                                        <button class="copy-tag-btn" title="Copy Asset Tag" onclick="copyToClipboard('${item.tag}', 'Asset Tag copied')">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="category-pill">${escapeHtml(item.category)}</span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="serial-badge" onclick="openAssetDrawer(${item.id})" style="cursor: pointer;" title="View details">${escapeHtml(item.serial)}</span>
                                    <button class="copy-tag-btn" title="Copy Serial Number" onclick="copyToClipboard('${item.serial}', 'Serial Number copied')">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                ${assigneeHtml}
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <span style="font-weight: 500; font-size: 12.5px;">${escapeHtml(item.location)}</span>
                                    <span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(item.department)}</span>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
                                    ${getStatusBadge(item.status)}
                                    ${getWarrantyBadge(item.financials.warrantyExpiry)}
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons-wrap">
                                    <button class="action-icon-btn btn-view" title="View Details" onclick="openAssetDrawer(${item.id})">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button class="action-icon-btn btn-qr" title="Print Barcode / QR Label" onclick="openLabelModal(${item.id})">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    </button>
                                    <button class="action-icon-btn btn-edit" title="Edit Asset" onclick="openEditModal(${item.id})">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                    </button>
                                    <button class="action-icon-btn btn-delete" title="Delete Asset" onclick="deleteAsset(${item.id})">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

        // Render Card / Grid View
        if (assetGridContainer) {
            if (pagedItems.length === 0) {
                assetGridContainer.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 48px; background: #fff; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <p style="color: var(--text-muted); font-size: 14px;">No matching assets found.</p>
                    </div>
                `;
            } else {
                assetGridContainer.innerHTML = pagedItems.map(item => {
                    const iconClass = getCategoryIconClass(item.category);
                    const iconSvg = getDeviceSvg(item.category);

                    return `
                        <div class="asset-card" data-id="${item.id}">
                            <div>
                                <div class="asset-card-header">
                                    <div class="asset-card-type">
                                        <div class="asset-device-icon ${iconClass}">
                                            ${iconSvg}
                                        </div>
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <span class="asset-tag-badge" onclick="openAssetDrawer(${item.id})" style="font-size: 11px;">${escapeHtml(item.tag)}</span>
                                            <span class="category-pill">${escapeHtml(item.category)}</span>
                                        </div>
                                    </div>
                                    <div>
                                        ${getStatusBadge(item.status)}
                                    </div>
                                </div>

                                <h4 class="asset-card-title" onclick="openAssetDrawer(${item.id})">${escapeHtml(item.name)}</h4>
                                <div class="asset-card-specs">${escapeHtml(item.brand)} • ${escapeHtml(item.model)}</div>

                                <div class="asset-card-meta-list">
                                    <div class="meta-field">
                                        <div class="meta-title">Asset Tag No.</div>
                                        <div class="meta-val" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">${escapeHtml(item.tag)}</div>
                                    </div>
                                    <div class="meta-field">
                                        <div class="meta-title">Serial Number</div>
                                        <div class="meta-val">${escapeHtml(item.serial)}</div>
                                    </div>
                                    <div class="meta-field">
                                        <div class="meta-title">Location</div>
                                        <div class="meta-val">${escapeHtml(item.location)}</div>
                                    </div>
                                    <div class="meta-field">
                                        <div class="meta-title">Category</div>
                                        <div class="meta-val">${escapeHtml(item.category)}</div>
                                    </div>
                                    <div class="meta-field">
                                        <div class="meta-title">Warranty</div>
                                        <div class="meta-val">${getWarrantyBadge(item.financials.warrantyExpiry)}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="asset-card-user">
                                <div>
                                    ${item.assignedTo ? `
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div class="assignee-avatar" style="width: 28px; height: 28px; font-size: 11px;">
                                                ${getInitials(item.assignedTo.name)}
                                            </div>
                                            <div style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                                ${escapeHtml(item.assignedTo.name)}
                                            </div>
                                        </div>
                                    ` : `<span class="unassigned-badge" style="font-size: 11px;">Unassigned</span>`}
                                </div>
                                <div class="asset-card-actions">
                                    <button class="action-icon-btn btn-view" title="Details" onclick="openAssetDrawer(${item.id})">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button class="action-icon-btn btn-qr" title="Print Sticker" onclick="openLabelModal(${item.id})">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    </button>
                                    <button class="action-icon-btn btn-edit" title="Edit Asset" onclick="openEditModal(${item.id})">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                    </button>
                                    <button class="action-icon-btn btn-delete" title="Delete Asset" onclick="deleteAsset(${item.id})">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Pagination Info
        if (paginationInfo) {
            if (total === 0) {
                paginationInfo.innerHTML = 'Showing <strong>0</strong> assets';
            } else {
                paginationInfo.innerHTML = `Showing <strong>${startIndex + 1}</strong> to <strong>${endIndex}</strong> of <strong>${total}</strong> assets`;
            }
        }

        // Pagination Controls
        if (paginationControls) {
            let pagHtml = '';

            function getPaginationPageList(curr, totalP) {
                if (totalP <= 7) {
                    const pages = [];
                    for (let i = 1; i <= totalP; i++) pages.push(i);
                    return pages;
                }
                if (curr <= 4) {
                    return [1, 2, 3, 4, 5, '...', totalP];
                }
                if (curr >= totalP - 3) {
                    return [1, '...', totalP - 4, totalP - 3, totalP - 2, totalP - 1, totalP];
                }
                return [1, '...', curr - 1, curr, curr + 1, '...', totalP];
            }

            const isPrevDisabled = currentPage <= 1;
            pagHtml += `<button type="button" class="pagination-btn ${isPrevDisabled ? 'disabled' : ''}" onclick="${isPrevDisabled ? '' : `goToPage(${currentPage - 1})`}" ${isPrevDisabled ? 'disabled' : ''} aria-label="Previous Page">
                <svg class="prev-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                <span>Prev</span>
            </button>`;

            const pagesList = getPaginationPageList(currentPage, totalPages);
            pagesList.forEach(p => {
                if (p === '...') {
                    pagHtml += `<span class="pagination-ellipsis">&hellip;</span>`;
                } else {
                    const isActive = p === currentPage;
                    pagHtml += `<button type="button" class="pagination-btn pagination-num ${isActive ? 'active' : ''}" onclick="goToPage(${p})" ${isActive ? 'aria-current="page"' : ''}>${p}</button>`;
                }
            });

            const isNextDisabled = currentPage >= totalPages;
            pagHtml += `<button type="button" class="pagination-btn ${isNextDisabled ? 'disabled' : ''}" onclick="${isNextDisabled ? '' : `goToPage(${currentPage + 1})`}" ${isNextDisabled ? 'disabled' : ''} aria-label="Next Page">
                <span>Next</span>
                <svg class="next-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>`;

            paginationControls.innerHTML = pagHtml;
        }

        // Update bulk selection toolbar
        updateBulkBar();
        updateKPIs();
    }

    // =========================================================================
    // 5. Drawer (Asset Details Slide-Over)
    // =========================================================================

    window.openAssetDrawer = function (id) {
        const parsedId = parseInt(id, 10);
        const asset = assetsData.find(a => a.id === id || a.id === parsedId || a.tag === id);
        if (!asset) return;
        activeDrawerAssetId = asset.id;
        window.activeDrawerAssetId = asset.id;

        // Drawer Header Info
        const tagEl = document.getElementById('drawerAssetTag');
        if (tagEl) tagEl.textContent = asset.tag;
        const serialEl = document.getElementById('drawerAssetSerial');
        if (serialEl) serialEl.textContent = 'SN: ' + asset.serial;
        document.getElementById('drawerAssetName').textContent = asset.name;
        document.getElementById('drawerAssetStatus').innerHTML = getStatusBadge(asset.status);

        // Populate Specs Tab
        const specTagEl = document.getElementById('specAssetTag');
        if (specTagEl) specTagEl.textContent = asset.tag;
        document.getElementById('specCategory').textContent = asset.category;
        document.getElementById('specBrand').textContent = asset.brand;
        document.getElementById('specModel').textContent = asset.model;
        document.getElementById('specSerial').textContent = asset.serial;
        document.getElementById('specCondition').textContent = asset.condition;
        document.getElementById('specLocation').textContent = asset.location;
        document.getElementById('specDepartment').textContent = asset.department;
        document.getElementById('specProcessor').textContent = asset.specs.processor || 'N/A';
        document.getElementById('specRam').textContent = asset.specs.ram || 'N/A';
        document.getElementById('specStorage').textContent = asset.specs.storage || 'N/A';
        document.getElementById('specOs').textContent = asset.specs.os || 'N/A';
        document.getElementById('specMac').textContent = asset.specs.macAddress || 'N/A';
        document.getElementById('specIp').textContent = asset.specs.ipAddress || 'N/A';

        // Financials
        document.getElementById('specVendor').textContent = asset.financials.vendor || 'N/A';
        document.getElementById('specPo').textContent = asset.financials.poNumber || 'N/A';
        document.getElementById('specPurchaseDate').textContent = asset.financials.purchaseDate || 'N/A';
        document.getElementById('specCost').textContent = formatCurrency(asset.financials.cost || 0);
        document.getElementById('specWarranty').innerHTML = getWarrantyBadge(asset.financials.warrantyExpiry);

        // Populate Assignee Section
        const drawerAssigneeWrap = document.getElementById('drawerAssigneeWrap');
        if (asset.assignedTo) {
            drawerAssigneeWrap.innerHTML = `
                <div style="display: flex; align-items: center; gap: 14px; background: #f8fafc; padding: 14px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div class="assignee-avatar" style="width: 44px; height: 44px; font-size: 15px;">
                        ${getInitials(asset.assignedTo.name)}
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 14.5px; font-weight: 700; color: var(--text-primary);">${escapeHtml(asset.assignedTo.name)}</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 1px;">
                            ${escapeHtml(asset.assignedTo.role)} • ${escapeHtml(asset.assignedTo.department)}
                        </div>
                        <div style="font-size: 11.5px; color: var(--cyan-primary); margin-top: 3px;">
                            ${escapeHtml(asset.assignedTo.email)}
                        </div>
                    </div>
                    <div>
                        <button class="btn-secondary" style="padding: 5px 10px; font-size: 12px;" onclick="openReassignModal(${asset.id})">Transfer / Reassign</button>
                    </div>
                </div>
            `;
        } else {
            drawerAssigneeWrap.innerHTML = `
                <div style="background: #f8fafc; padding: 14px 16px; border-radius: var(--radius-md); border: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 13.5px; font-weight: 600; color: #1e40af;">Currently In Stock / Unallocated</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">This device is staged in the IT depot and ready for deployment.</div>
                    </div>
                    <button class="btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="openReassignModal(${asset.id})">Assign Asset</button>
                </div>
            `;
        }

        // Timeline History
        const timelineWrap = document.getElementById('drawerTimelineWrap');
        if (timelineWrap) {
            if (asset.history && asset.history.length > 0) {
                timelineWrap.innerHTML = asset.history.map((h, i) => `
                    <div class="timeline-item">
                        <div class="timeline-dot ${i === 0 ? 'green' : ''}"></div>
                        <div class="timeline-date">${escapeHtml(h.date)}</div>
                        <div class="timeline-content">
                            <div class="timeline-title">${escapeHtml(h.title)}</div>
                            <div class="timeline-desc">${escapeHtml(h.desc)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                timelineWrap.innerHTML = `<p style="font-size: 12.5px; color: var(--text-muted);">No recorded movement logs.</p>`;
            }
        }

        // Tickets History
        const ticketsWrap = document.getElementById('drawerTicketsWrap');
        if (ticketsWrap) {
            if (asset.tickets && asset.tickets.length > 0) {
                ticketsWrap.innerHTML = asset.tickets.map(t => `
                    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-weight: 700; font-size: 12px; color: var(--cyan-primary);">${t.id}</span>
                            <span class="warranty-pill warranty-active">${t.status}</span>
                        </div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">${escapeHtml(t.title)}</div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Logged on ${t.date}</div>
                    </div>
                `).join('');
            } else {
                ticketsWrap.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 12.5px;">
                        No support tickets linked to this asset hardware.
                    </div>
                `;
            }
        }

        // Attached Hardware Components & Upgrades
        const attachedComps = Array.isArray(asset.components) ? asset.components : [];
        const compCount = attachedComps.length;

        const badgeEl = document.getElementById('drawerCompBadge');
        if (badgeEl) badgeEl.textContent = compCount;

        const ovCountEl = document.getElementById('drawerOverviewCompCount');
        if (ovCountEl) ovCountEl.textContent = `${compCount} ${compCount === 1 ? 'Part' : 'Parts'}`;

        const paneCountEl = document.getElementById('drawerPaneCompCount');
        if (paneCountEl) paneCountEl.textContent = compCount;

        const ovWrap = document.getElementById('drawerOverviewComponentsWrap');
        const tabWrap = document.getElementById('drawerTabComponentsWrap');

        if (compCount === 0) {
            const emptyHtml = `
                <div style="text-align: center; padding: 22px 16px; background: #f8fafc; border: 1px dashed var(--border-color); border-radius: var(--radius-md); color: var(--text-muted);">
                    <div style="font-size: 24px; margin-bottom: 6px; opacity: 0.7;">🔌</div>
                    <div style="font-size: 13px; font-weight: 600; color: var(--text-secondary);">No attached hardware components</div>
                    <div style="font-size: 11.5px; margin-top: 3px;">No modular components (RAM, SSD, GPU, etc.) are currently installed in this device.</div>
                    <button type="button" class="btn-secondary" style="margin-top: 10px; padding: 5px 12px; font-size: 12px;" onclick="openEditModal(${asset.id}, 'components')">
                        + Attach Components
                    </button>
                </div>
            `;
            if (ovWrap) ovWrap.innerHTML = emptyHtml;
            if (tabWrap) tabWrap.innerHTML = emptyHtml;
        } else {
            const renderCompItem = (c) => {
                const cName = c.name || 'Component / Module';
                const cTag = (c.tag || c.sku) ? `<span class="asset-tag-badge" style="font-size: 11px; padding: 1px 7px;">${escapeHtml(c.tag || c.sku)}</span>` : '';
                const cSerial = c.serial ? `<span class="serial-badge" style="font-size: 11px; padding: 1px 7px;">SN: ${escapeHtml(c.serial)}</span>` : '';
                const cCat = c.category ? `<span class="category-pill" style="font-size: 11px; padding: 1px 7px;">${escapeHtml(c.category)}</span>` : '';
                const cSpecs = c.specs ? `<div style="font-size: 11.5px; color: var(--text-muted); margin-top: 3px;">${escapeHtml(c.specs)}</div>` : '';
                const cBrandModel = (c.brand || c.model) ? `<div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">${escapeHtml([c.brand, c.model].filter(Boolean).join(' '))}</div>` : '';

                return `
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 9px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                    <rect x="9" y="9" width="6" height="6"></rect>
                                    <line x1="9" y1="1" x2="9" y2="4"></line>
                                    <line x1="15" y1="1" x2="15" y2="4"></line>
                                    <line x1="9" y1="20" x2="9" y2="23"></line>
                                    <line x1="15" y1="20" x2="15" y2="23"></line>
                                    <line x1="20" y1="9" x2="23" y2="9"></line>
                                    <line x1="20" y1="14" x2="23" y2="14"></line>
                                    <line x1="1" y1="9" x2="4" y2="9"></line>
                                    <line x1="1" y1="14" x2="4" y2="14"></line>
                                </svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 13.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(cName)}">
                                    ${escapeHtml(cName)}
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px; flex-wrap: wrap;">
                                    ${cCat}
                                    ${cTag}
                                    ${cSerial}
                                </div>
                                ${cBrandModel}
                                ${cSpecs}
                            </div>
                        </div>
                        <div style="flex-shrink: 0; text-align: right;">
                            <span class="warranty-pill warranty-active" style="font-size: 11px;">Installed</span>
                        </div>
                    </div>
                `;
            };

            const cardsHtml = attachedComps.map(c => renderCompItem(c)).join('');
            if (ovWrap) ovWrap.innerHTML = cardsHtml;
            if (tabWrap) tabWrap.innerHTML = cardsHtml;
        }

        // Set Tab 1 as active by default in drawer
        switchDrawerTab('overview');

        // Open Drawer
        if (assetDrawer) assetDrawer.classList.add('open');
        if (drawerBackdrop) drawerBackdrop.classList.add('open');
    };

    window.closeAssetDrawer = function () {
        if (assetDrawer) assetDrawer.classList.remove('open');
        if (drawerBackdrop) drawerBackdrop.classList.remove('open');
        activeDrawerAssetId = null;
        window.activeDrawerAssetId = null;
    };

    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', closeAssetDrawer);
    }
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeAssetDrawer);
    }

    // Drawer Tabs Navigation
    window.switchDrawerTab = function (tabName) {
        document.querySelectorAll('.drawer-tab').forEach(b => {
            b.classList.toggle('active', b.dataset.tab === tabName);
        });
        document.querySelectorAll('.drawer-tab-pane').forEach(p => {
            p.classList.toggle('active', p.id === 'pane_' + tabName);
        });
    };

    document.querySelectorAll('.drawer-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            switchDrawerTab(this.dataset.tab);
        });
    });

    // =========================================================================
    // 6. Thermal Label & QR Print Modal
    // =========================================================================

    let currentLabelAsset = null;

    window.openLabelModal = function (assetId) {
        const asset = assetsData.find(a => a.id === assetId);
        if (!asset) return;
        currentLabelAsset = asset;

        const tagEl = document.getElementById('lblStickerTag');
        if (tagEl) tagEl.textContent = 'TAG: ' + (asset.tag || '');
        const nameEl = document.getElementById('lblStickerName');
        if (nameEl) nameEl.textContent = asset.name || '';
        const serialEl = document.getElementById('lblStickerSerial');
        if (serialEl) serialEl.textContent = 'SN: ' + (asset.serial || 'N/A');
        const catEl = document.getElementById('lblStickerCategory');
        if (catEl) catEl.textContent = 'Category: ' + (asset.category || '');
        const barcodeEl = document.getElementById('lblBarcodeNumber');
        if (barcodeEl) barcodeEl.textContent = '*' + (asset.tag || '') + '*';

        // Check and fetch latest system printers asynchronously if needed
        if (typeof window.refreshSystemPrinters === 'function') {
            window.refreshSystemPrinters(false);
        }

        // Restore preferred printer from localStorage if saved
        try {
            const savedPrinter = localStorage.getItem('viros_preferred_printer');
            const printerSelect = document.getElementById('labelPrinterSelect');
            if (printerSelect && savedPrinter) {
                printerSelect.value = savedPrinter;
                if (typeof window.handleLabelPrinterChange === 'function') {
                    window.handleLabelPrinterChange(savedPrinter);
                }
            } else if (printerSelect) {
                window.handleLabelPrinterChange(printerSelect.value);
            }
        } catch (e) {}

        const modal = document.getElementById('labelModal');
        if (modal) modal.style.display = 'flex';
    };

    window.closeLabelModal = function () {
        const modal = document.getElementById('labelModal');
        if (modal) modal.style.display = 'none';
    };

    window.refreshSystemPrinters = function (force = false) {
        const btn = document.getElementById('refreshPrintersBtn');
        if (btn) {
            btn.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> Scanning...`;
            btn.disabled = true;
        }

        fetch(getApiUrl() + '?action=get_system_printers' + (force ? '&refresh=1' : ''))
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.printers)) {
                    updatePrinterSelectOptions(data.printers);
                    if (force && typeof showToast === 'function') {
                        showToast(`Detected ${data.printers.length} installed system printers`, 'info');
                    }
                }
            })
            .catch(() => {})
            .finally(() => {
                if (btn) {
                    btn.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg> Refresh`;
                    btn.disabled = false;
                }
            });
    };

    function updatePrinterSelectOptions(printers) {
        const select = document.getElementById('labelPrinterSelect');
        if (!select || !Array.isArray(printers) || printers.length === 0) return;

        const currentVal = select.value;
        select.innerHTML = '';

        printers.forEach(p => {
            if (!p || !p.name) return;
            const opt = document.createElement('option');
            opt.value = p.name;
            opt.textContent = p.name;
            select.appendChild(opt);
        });

        // Keep previous or saved selection if still valid, or default to first
        const saved = localStorage.getItem('viros_preferred_printer') || currentVal;
        const exists = Array.from(select.options).some(o => o.value === saved);
        if (exists) {
            select.value = saved;
        } else if (select.options.length > 0) {
            select.value = select.options[0].value;
        }
        handleLabelPrinterChange(select.value);

        // Sync custom searchable dropdown UI
        if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
            window.SearchableSelect.sync(select);
        }
    }

    // Auto-detect installed printers on initialization
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(() => refreshSystemPrinters(false), 200);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => refreshSystemPrinters(false), 200);
        });
    }

    window.handleLabelPrinterChange = function (val) {
        try {
            localStorage.setItem('viros_preferred_printer', val);
        } catch (e) {}

        const textEl = document.getElementById('selectedPrinterNameText');
        if (textEl) {
            textEl.innerHTML = `Printer: <strong>${escapeHtml(val || 'Default')}</strong>`;
        }
    };

    window.downloadLabelZpl = function () {
        if (!currentLabelAsset) return;
        const copies = parseInt(document.getElementById('labelCopiesCount')?.value || '1', 10);

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_zpl',
                tag: currentLabelAsset.tag,
                serial: currentLabelAsset.serial,
                name: currentLabelAsset.name,
                copies: copies
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success && data.zpl) {
                const blob = new Blob([data.zpl], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `label_${currentLabelAsset.tag}.prn`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                if (typeof showToast === 'function') {
                    showToast(`ZPL print file exported for ${currentLabelAsset.tag}`, 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('Failed to generate ZPL code', 'error');
                }
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') {
                showToast('Error downloading ZPL template', 'error');
            }
        });
    };

    window.printSticker = function () {
        if (!currentLabelAsset) {
            if (typeof showToast === 'function') showToast('No asset selected for printing', 'warning');
            return;
        }

        const printerSelect = document.getElementById('labelPrinterSelect');
        const printerName = printerSelect ? printerSelect.value.trim() : '';
        const copies = parseInt(document.getElementById('labelCopiesCount')?.value || '1', 10);

        if (!printerName) {
            if (typeof showToast === 'function') showToast('Please select a system printer first', 'warning');
            return;
        }

        // If explicitly set to system default browser dialog
        if (printerName === 'system_default') {
            window.print();
            return;
        }

        const btn = document.getElementById('btnPrintStickerBtn');
        const origContent = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> Sending to Printer...`;
            btn.disabled = true;
        }

        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'print_label',
                printer_name: printerName,
                tag: currentLabelAsset.tag,
                serial: currentLabelAsset.serial,
                name: currentLabelAsset.name,
                copies: copies
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || `Print job sent to "${printerName}" (${copies} ${copies === 1 ? 'copy' : 'copies'})`, 'success');
                }
                closeLabelModal();
            } else if (data && data.fallback_browser) {
                window.print();
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Failed to send label to printer', 'error');
                }
            }
        })
        .catch(err => {
            if (typeof showToast === 'function') {
                showToast('Error communicating with print service', 'error');
            }
        })
        .finally(() => {
            if (btn) {
                btn.innerHTML = origContent;
                btn.disabled = false;
            }
        });
    };

    // =========================================================================
    // 7. QR & Barcode Scanner Simulation
    // =========================================================================

    window.openScannerModal = function () {
        const modal = document.getElementById('scannerModal');
        if (modal) {
            modal.style.display = 'flex';
            const scanInput = document.getElementById('manualScanInput');
            if (scanInput) {
                scanInput.value = '';
                scanInput.focus();
            }
        }
    };

    window.closeScannerModal = function () {
        const modal = document.getElementById('scannerModal');
        if (modal) modal.style.display = 'none';
    };

    window.handleManualScan = function () {
        const input = document.getElementById('manualScanInput');
        if (!input || !input.value.trim()) {
            if (typeof showToast === 'function') showToast('Please enter a Serial Number', 'warning');
            return;
        }

        const query = input.value.trim().toLowerCase();
        const found = assetsData.find(a =>
            (a.tag && a.tag.toLowerCase() === query) ||
            a.serial.toLowerCase() === query ||
            a.name.toLowerCase().includes(query)
        );

        if (found) {
            closeScannerModal();
            if (typeof showToast === 'function') showToast(`Found asset: ${found.name} (Tag: ${found.tag} | SN: ${found.serial})`, 'success');
            openAssetDrawer(found.id);
        } else {
            if (typeof showToast === 'function') showToast(`No asset found matching "${input.value}"`, 'danger');
        }
    };

    // =========================================================================
    // 8. Add / Edit Asset Modal
    // =========================================================================

    const assetModal = document.getElementById('assetModal');
    const assetModalTitle = document.getElementById('assetModalTitle');
    const assetForm = document.getElementById('assetForm');
    const openAddModalBtn = document.getElementById('openAddModalBtn');
    const closeAssetModalBtn = document.getElementById('closeAssetModalBtn');
    const cancelAssetModalBtn = document.getElementById('cancelAssetModalBtn');



    // =========================================================================
    // Attached Components (Part To-Do List)
    // =========================================================================
    let currentAssetComponents = []; // Array of { name, serial, tag, sku, id, component_id }

    function buildComponentOptionsHtml(selectedVal = '') {
        if (!Array.isArray(window.dynamicPartComponents)) return '<option value="">Select Part / Component</option>';

        // Filter: ONLY parts whose status is Available and NOT installed / in-use anywhere
        const availableParts = window.dynamicPartComponents.filter(item => {
            const status = (item.status || 'Available').trim().toLowerCase();
            if (status !== 'available') return false;

            const inst = (item.installedAsset || item.installed_asset || '').trim();
            if (inst && inst !== '— (Unassigned / In Stock)' && inst !== '') return false;

            // Also exclude if already attached in current modal list
            const alreadyInList = currentAssetComponents.some(c => 
                (c.component_id && item.id && c.component_id == item.id) ||
                (c.id && item.id && c.id == item.id) ||
                (c.serial && item.serial && c.serial.trim().toLowerCase() === item.serial.trim().toLowerCase())
            );
            if (alreadyInList) return false;

            return true;
        });

        const groups = {};
        availableParts.forEach(item => {
            const cat = item.category || 'General Components';
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push(item);
        });

        let html = '<option value="">Select Part / Component</option>';
        Object.keys(groups).sort().forEach(cat => {
            html += `<optgroup label="${escapeHtml(cat)}">`;
            groups[cat].forEach(item => {
                const isSel = (item.name === selectedVal) ? 'selected' : '';
                html += `<option value="${escapeHtml(item.name)}" 
                                data-id="${escapeHtml(item.id || '')}"
                                data-serial="${escapeHtml(item.serial || '')}" 
                                data-sku="${escapeHtml(item.sku || item.tag || '')}" 
                                data-tag="${escapeHtml(item.sku || item.tag || '')}" ${isSel}>${escapeHtml(item.name)}</option>`;
            });
            html += `</optgroup>`;
        });
        return html;
    }

    function renderPartTodoList() {
        const container = document.getElementById('partTodoListContainer');
        const countBadge = document.getElementById('partTodoCount');
        const modalTabBadge = document.getElementById('modalCompTabBadge');
        if (!container) return;

        if (countBadge) {
            countBadge.textContent = currentAssetComponents.length;
        }
        if (modalTabBadge) {
            modalTabBadge.textContent = currentAssetComponents.length;
            modalTabBadge.style.display = currentAssetComponents.length > 0 ? 'inline-block' : 'none';
        }

        if (currentAssetComponents.length === 0) {
            container.innerHTML = `
                <div class="part-todo-empty">
                    No hardware parts attached yet. Select a component above and click <strong>+</strong> to add.
                </div>
            `;
            return;
        }

        container.innerHTML = currentAssetComponents.map((item, idx) => {
            const snVal = (item.serial || '').trim();
            const tagVal = (item.tag || item.sku || '').trim();

            let badgesHtml = '';
            if (tagVal) {
                badgesHtml += `
                    <span class="part-todo-badge tag-badge" title="Tag Number">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:3px;vertical-align:-1px;">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>Tag: ${escapeHtml(tagVal)}
                    </span>
                `;
            }
            if (snVal && snVal !== tagVal) {
                badgesHtml += `
                    <span class="part-todo-badge" title="Serial Number">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:3px;vertical-align:-1px;">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>SN: ${escapeHtml(snVal)}
                    </span>
                `;
            } else if (snVal && !tagVal) {
                badgesHtml += `
                    <span class="part-todo-badge" title="Serial Number">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:3px;vertical-align:-1px;">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>SN: ${escapeHtml(snVal)}
                    </span>
                `;
            }
            if (!badgesHtml) {
                badgesHtml = `<span class="part-todo-badge no-serial">No Serial / Tag</span>`;
            }

            return `
                <div class="part-todo-item" data-index="${idx}">
                    <div class="part-todo-left">
                        <div class="part-todo-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                <rect x="9" y="9" width="6" height="6"></rect>
                                <line x1="9" y1="1" x2="9" y2="4"></line>
                                <line x1="15" y1="1" x2="15" y2="4"></line>
                                <line x1="9" y1="20" x2="9" y2="23"></line>
                                <line x1="15" y1="20" x2="15" y2="23"></line>
                                <line x1="20" y1="9" x2="23" y2="9"></line>
                                <line x1="20" y1="14" x2="23" y2="14"></line>
                                <line x1="1" y1="9" x2="4" y2="9"></line>
                                <line x1="1" y1="14" x2="4" y2="14"></line>
                            </svg>
                        </div>
                        <div class="part-todo-info">
                            <div class="part-todo-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                            <div class="part-todo-serial">${badgesHtml}</div>
                        </div>
                    </div>
                    <button type="button" class="part-todo-remove-btn" onclick="removePartTodo(${idx})" title="Remove Part">&times;</button>
                </div>
            `;
        }).join('');
    }

    window.addPartTodo = function () {
        const select = document.getElementById('newCompName');
        const serialInput = document.getElementById('newCompSerial');
        if (!select) return;

        const name = select.value.trim();
        if (!name) {
            if (typeof showToast === 'function') {
                showToast('Please select a Component Name first', 'warning');
            }
            return;
        }

        const serial = serialInput ? serialInput.value.trim() : '';
        const opt = select.options[select.selectedIndex];
        let tag = opt ? (opt.dataset.tag || opt.dataset.sku || '') : '';
        let sn = opt ? (opt.dataset.serial || '') : '';
        let compId = opt ? (opt.dataset.id || '') : '';

        // If tag, sn or compId missing from option, lookup in dynamicPartComponents
        if (window.dynamicPartComponents) {
            const match = window.dynamicPartComponents.find(p => 
                (compId && p.id == compId) ||
                (sn && p.serial === sn) ||
                (tag && (p.sku === tag || p.tag === tag)) ||
                (p.name === name)
            );
            if (match) {
                if (!compId) compId = match.id || '';
                if (!tag) tag = match.tag || match.sku || '';
                if (!sn) sn = match.serial || '';
            }
        }

        const finalSerial = serial || sn;

        // Add to array
        currentAssetComponents.push({
            component_id: compId ? parseInt(compId, 10) : null,
            name,
            serial: finalSerial,
            tag: tag,
            sku: tag,
            id: compId ? parseInt(compId, 10) : (Date.now() + Math.random())
        });

        // Reset inputs and re-render dropdown so the added item disappears
        if (serialInput) {
            serialInput.value = '';
            serialInput.dataset.autofilled = 'false';
        }
        select.innerHTML = buildComponentOptionsHtml();
        select.value = '';
        if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
            window.SearchableSelect.sync(select);
        }

        renderPartTodoList();
        if (typeof showToast === 'function') {
            showToast(`Part "${name}" added to list`, 'success');
        }
    };

    window.removePartTodo = function (index) {
        if (index >= 0 && index < currentAssetComponents.length) {
            const removed = currentAssetComponents.splice(index, 1);
            const select = document.getElementById('newCompName');
            if (select) {
                select.innerHTML = buildComponentOptionsHtml();
                select.value = '';
                if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
                    window.SearchableSelect.sync(select);
                }
            }
            renderPartTodoList();
            if (removed.length > 0 && typeof showToast === 'function') {
                showToast(`Removed "${removed[0].name}"`, 'info');
            }
        }
    };

    // Backward compatibility aliases
    window.addComponentRow = window.addPartTodo;
    window.removeComponentRow = function () {};

    function resetComponentRows(components = []) {
        currentAssetComponents = Array.isArray(components) ? components.map(c => ({
            component_id: c.component_id || (typeof c.id === 'number' && c.id < 1000000 ? c.id : null),
            name: c.name || '',
            serial: c.serial || '',
            tag: c.tag || c.sku || '',
            sku: c.tag || c.sku || '',
            id: c.component_id || (typeof c.id === 'number' && c.id < 1000000 ? c.id : (Date.now() + Math.random()))
        })) : [];

        // Reset inputs & refresh dropdown options excluding parts in current list
        const serialInput = document.getElementById('newCompSerial');
        const select = document.getElementById('newCompName');
        if (serialInput) {
            serialInput.value = '';
            serialInput.dataset.autofilled = 'false';
        }
        if (select) {
            select.innerHTML = buildComponentOptionsHtml();
            select.value = '';
            if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
                window.SearchableSelect.sync(select);
            }
        }

        renderPartTodoList();
    }

    window.openAddModal = function () {
        if (assetModalTitle) assetModalTitle.textContent = 'Register New IT Hardware Asset';
        if (assetForm) assetForm.reset();
        document.getElementById('editAssetId').value = '';

        const tagInput = document.getElementById('modalAssetTag');
        if (tagInput) {
            const nextNum = assetsData.length + 1;
            tagInput.value = 'AST2024' + String(nextNum).padStart(3, '0');

            // Asynchronously fetch next exact database sequence tag
            fetch(getApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_next_tag' })
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.next_tag && !document.getElementById('editAssetId').value) {
                    tagInput.value = data.next_tag;
                }
            })
            .catch(() => {});
        }

        resetComponentRows([]);

        const vendorSelect = document.getElementById('modalVendor');
        if (vendorSelect) vendorSelect.value = '';

        switchModalTab('general');
        if (assetModal) assetModal.style.display = 'flex';
    };

    window.openEditModal = function (id, initialTab = 'general') {
        if (id === undefined || id === null || id === '') {
            id = window.activeDrawerAssetId;
        }
        const parsedId = parseInt(id, 10);
        const asset = assetsData.find(a => a.id === id || a.id === parsedId || a.tag === id);
        if (!asset) return;

        // Close drawer if open
        if (typeof closeAssetDrawer === 'function') closeAssetDrawer();

        if (assetModalTitle) assetModalTitle.textContent = `Edit Asset: ${asset.name}`;
        document.getElementById('editAssetId').value = asset.id;
        const tagInput = document.getElementById('modalAssetTag');
        if (tagInput) tagInput.value = asset.tag || '';
        document.getElementById('modalAssetName').value = asset.name;
        document.getElementById('modalCategory').value = asset.category;
        document.getElementById('modalBrand').value = asset.brand;
        document.getElementById('modalModel').value = asset.model;
        document.getElementById('modalSerial').value = asset.serial;
        document.getElementById('modalCondition').value = asset.condition;
        document.getElementById('modalStatus').value = asset.status;
        document.getElementById('modalLocation').value = asset.location;
        document.getElementById('modalDepartment').value = asset.department;

        // Specs
        const specs = asset.specs || {};
        document.getElementById('modalProcessor').value = specs.processor || '';
        document.getElementById('modalRam').value = specs.ram || '';
        document.getElementById('modalStorage').value = specs.storage || '';
        document.getElementById('modalOs').value = specs.os || '';
        document.getElementById('modalMac').value = specs.macAddress || '';
        document.getElementById('modalIp').value = specs.ipAddress || '';

        // Components
        resetComponentRows(asset.components || []);

        // Financials
        const fin = asset.financials || {};
        const vendorSelect = document.getElementById('modalVendor');
        if (vendorSelect) {
            const vVal = fin.vendor || '';
            if (vVal) {
                const optExists = Array.from(vendorSelect.options).some(o => o.value.toLowerCase() === vVal.toLowerCase());
                if (!optExists) {
                    const opt = document.createElement('option');
                    opt.value = vVal;
                    opt.textContent = vVal;
                    vendorSelect.appendChild(opt);
                }
            }
            vendorSelect.value = vVal;
        }
        document.getElementById('modalPoNumber').value = fin.poNumber || '';
        document.getElementById('modalPurchaseDate').value = fin.purchaseDate || '';
        document.getElementById('modalCost').value = fin.cost || '';
        document.getElementById('modalWarrantyExpiry').value = fin.warrantyExpiry || '';

        switchModalTab(initialTab || 'general');
        if (assetModal) assetModal.style.display = 'flex';
    };

    window.closeAssetModal = function () {
        if (assetModal) assetModal.style.display = 'none';
    };

    if (openAddModalBtn) openAddModalBtn.addEventListener('click', openAddModal);
    if (closeAssetModalBtn) closeAssetModalBtn.addEventListener('click', closeAssetModal);
    if (cancelAssetModalBtn) cancelAssetModalBtn.addEventListener('click', closeAssetModal);

    window.switchModalTab = function (tabName) {
        document.querySelectorAll('.modal-tab-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.tab === tabName);
        });
        document.querySelectorAll('.modal-tab-pane').forEach(p => {
            p.classList.toggle('active', p.id === 'modal_pane_' + tabName);
        });
    };

    document.querySelectorAll('.modal-tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            switchModalTab(this.dataset.tab);
        });
    });

    if (assetForm) {
        assetForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const editId = document.getElementById('editAssetId').value;
            let tag = document.getElementById('modalAssetTag') ? document.getElementById('modalAssetTag').value.trim() : '';
            if (!tag) {
                tag = 'AST2024' + String(assetsData.length + 1).padStart(3, '0');
            }
            const name = document.getElementById('modalAssetName').value.trim();
            const category = document.getElementById('modalCategory').value;
            const brand = document.getElementById('modalBrand').value.trim();
            const model = document.getElementById('modalModel').value.trim();
            const serial = document.getElementById('modalSerial').value.trim();
            const condition = document.getElementById('modalCondition').value;
            const status = document.getElementById('modalStatus').value;
            const location = document.getElementById('modalLocation').value;
            const department = document.getElementById('modalDepartment').value;

            if (!name || !serial) {
                if (typeof showToast === 'function') showToast('Please complete required fields (Asset Name, Serial Number)', 'danger');
                return;
            }

            // Extract dynamic components from the todo list
            const activeSel = document.getElementById('newCompName');
            const activeSerial = document.getElementById('newCompSerial');
            if (activeSel && activeSel.value.trim()) {
                const aName = activeSel.value.trim();
                const aOpt = activeSel.options[activeSel.selectedIndex];
                let aTag = aOpt ? (aOpt.dataset.tag || aOpt.dataset.sku || '') : '';
                let aSn = aOpt ? (aOpt.dataset.serial || '') : '';
                let aId = aOpt ? (aOpt.dataset.id || '') : '';
                if (window.dynamicPartComponents) {
                    const match = window.dynamicPartComponents.find(p => 
                        (aId && p.id == aId) ||
                        (aSn && p.serial === aSn) ||
                        (aTag && (p.sku === aTag || p.tag === aTag)) ||
                        (p.name === aName)
                    );
                    if (match) {
                        if (!aId) aId = match.id || '';
                        if (!aTag) aTag = match.tag || match.sku || '';
                        if (!aSn) aSn = match.serial || '';
                    }
                }
                const aSerial = activeSerial ? activeSerial.value.trim() : '';
                currentAssetComponents.push({
                    component_id: aId ? parseInt(aId, 10) : null,
                    name: aName,
                    serial: aSerial || aSn,
                    tag: aTag,
                    sku: aTag,
                    id: aId ? parseInt(aId, 10) : Date.now()
                });
            }

            const compRows = currentAssetComponents.map(item => ({
                component_id: item.component_id || (typeof item.id === 'number' && item.id < 1000000 ? item.id : null),
                name: item.name,
                serial: item.serial,
                tag: item.tag || item.sku || '',
                sku: item.tag || item.sku || ''
            }));

            const payload = {
                action: editId ? 'edit' : 'create',
                id: editId ? parseInt(editId, 10) : undefined,
                tag: tag,
                name: name,
                category: category,
                brand: brand,
                model: model,
                serial: serial,
                condition: condition,
                status: status,
                location: location,
                department: department,
                specs: {
                    processor: document.getElementById('modalProcessor').value.trim(),
                    ram: document.getElementById('modalRam').value.trim(),
                    storage: document.getElementById('modalStorage').value.trim(),
                    os: document.getElementById('modalOs').value.trim(),
                    macAddress: document.getElementById('modalMac').value.trim(),
                    ipAddress: document.getElementById('modalIp').value.trim()
                },
                components: compRows,
                financials: {
                    vendor: document.getElementById('modalVendor').value.trim(),
                    poNumber: document.getElementById('modalPoNumber').value.trim(),
                    purchaseDate: document.getElementById('modalPurchaseDate').value,
                    cost: parseFloat(document.getElementById('modalCost').value) || 0,
                    warrantyExpiry: document.getElementById('modalWarrantyExpiry').value
                }
            };

            const submitBtn = assetForm.querySelector('button[type="submit"]');
            const origBtnText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Saving to DB...</span>';
            }

            fetch(getApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnText;
                }
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'Failed to save asset into database');
                }

                if (editId) {
                    const parsedId = parseInt(editId, 10);
                    const idx = assetsData.findIndex(a => a.id === parsedId);
                    if (idx !== -1) {
                        assetsData[idx] = data.asset || { ...assetsData[idx], ...payload };
                    }
                    if (typeof showToast === 'function') showToast(`Asset "${name}" updated successfully in database!`, 'success');
                } else {
                    const newAsset = data.asset || {
                        id: Date.now(),
                        ...payload,
                        assignedTo: null,
                        history: [{ date: 'Today', title: 'Asset Registered', desc: 'Added into database.' }],
                        tickets: []
                    };
                    assetsData.unshift(newAsset);
                    if (typeof showToast === 'function') showToast(`Asset "${name}" (${newAsset.tag}) registered in database!`, 'success');
                }

                closeAssetModal();
                if (typeof refreshAvailableComponents === 'function') {
                    refreshAvailableComponents();
                }
                renderAssets();
                updateKPIs();
                if (editId && activeDrawerAssetId === parseInt(editId, 10)) {
                    openAssetDrawer(parseInt(editId, 10));
                }
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnText;
                }
                console.error(err);
                if (typeof showToast === 'function') showToast(err.message || 'Error communicating with database', 'danger');
            });
        });
    }

    // =========================================================================
    // 9. Quick Reassign / Transfer Modal
    // =========================================================================

    window.openReassignModal = function (assetId) {
        const asset = assetsData.find(a => a.id === assetId);
        if (!asset) return;

        const modal = document.getElementById('reassignModal');
        document.getElementById('reassignAssetId').value = asset.id;
        document.getElementById('reassignAssetName').textContent = asset.name;
        const serialEl = document.getElementById('reassignAssetSerial');
        if (serialEl) serialEl.textContent = `Tag: ${asset.tag} • SN: ${asset.serial}`;

        const empNameInput = document.getElementById('reassignEmpName');
        const deptSelect = document.getElementById('reassignDept');
        const emailInput = document.getElementById('reassignEmail');
        const actionSelect = document.getElementById('reassignActionType');

        if (actionSelect) {
            actionSelect.value = 'assign_employee';
            toggleReassignFields('assign_employee');
        }

        if (asset.assignedTo && asset.assignedTo.name) {
            if (empNameInput) empNameInput.value = asset.assignedTo.name;
            if (deptSelect && asset.assignedTo.department) deptSelect.value = asset.assignedTo.department;
            if (emailInput && asset.assignedTo.email) emailInput.value = asset.assignedTo.email;
        } else {
            if (empNameInput) empNameInput.value = '';
            if (emailInput) emailInput.value = '';
        }

        if (modal) modal.style.display = 'flex';
    };

    window.closeReassignModal = function () {
        const modal = document.getElementById('reassignModal');
        if (modal) modal.style.display = 'none';
    };

    function toggleReassignFields(actionType) {
        const isReturn = actionType === 'return_to_stock';
        const empInput = document.getElementById('reassignEmpName');
        const deptInput = document.getElementById('reassignDept');
        const emailInput = document.getElementById('reassignEmail');

        const empGroup = empInput ? empInput.closest('.modal-form-group') : null;
        const deptGroup = deptInput ? deptInput.closest('.modal-form-group') : null;
        const emailGroup = emailInput ? emailInput.closest('.modal-form-group') : null;

        if (empGroup) empGroup.style.display = isReturn ? 'none' : 'block';
        if (deptGroup) deptGroup.style.display = isReturn ? 'none' : 'block';
        if (emailGroup) emailGroup.style.display = isReturn ? 'none' : 'block';
    }

    const reassignActionSelect = document.getElementById('reassignActionType');
    if (reassignActionSelect) {
        reassignActionSelect.addEventListener('change', function () {
            toggleReassignFields(this.value);
        });
    }

    const reassignEmpInput = document.getElementById('reassignEmpName');
    if (reassignEmpInput) {
        reassignEmpInput.addEventListener('input', function () {
            const val = this.value.trim().toLowerCase();
            if (!val || !Array.isArray(window.DB_EMPLOYEES)) return;

            const matched = window.DB_EMPLOYEES.find(e =>
                (e.name && e.name.toLowerCase() === val) ||
                (e.emp_code && e.emp_code.toLowerCase() === val) ||
                ((e.emp_code + ' - ' + e.name).toLowerCase() === val)
            );

            if (matched) {
                const emailInput = document.getElementById('reassignEmail');
                const deptInput = document.getElementById('reassignDept');
                if (emailInput && matched.email) emailInput.value = matched.email;
                if (deptInput && (matched.designation || matched.department)) {
                    // Try to match department
                    const matchDept = matched.department || matched.designation;
                    const optExists = Array.from(deptInput.options).some(o => o.value.toLowerCase() === matchDept.toLowerCase());
                    if (optExists) {
                        deptInput.value = matchDept;
                    }
                }
            }
        });
    }

    const reassignForm = document.getElementById('reassignForm');
    if (reassignForm) {
        reassignForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = parseInt(document.getElementById('reassignAssetId').value, 10);
            const employeeName = document.getElementById('reassignEmpName').value.trim();
            const empDept = document.getElementById('reassignDept').value;
            const empEmail = document.getElementById('reassignEmail').value.trim();
            const actionType = document.getElementById('reassignActionType').value;

            const asset = assetsData.find(a => a.id === id);
            if (!asset) return;

            if (actionType !== 'return_to_stock' && !employeeName) {
                if (typeof showToast === 'function') showToast('Please specify recipient employee name', 'warning');
                return;
            }

            const submitBtn = reassignForm.querySelector('button[type="submit"]');
            const origBtnText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Updating DB...</span>';
            }

            const payload = {
                action: 'reassign',
                id: id,
                actionType: actionType,
                employeeName: employeeName,
                department: empDept,
                email: empEmail,
                role: 'Team Member'
            };

            fetch(getApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnText;
                }
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'Failed to update asset assignment in database');
                }

                if (data.asset) {
                    const idx = assetsData.findIndex(a => a.id === id);
                    if (idx !== -1) assetsData[idx] = data.asset;
                } else {
                    if (actionType === 'return_to_stock') {
                        asset.status = 'Available';
                        asset.assignedTo = null;
                    } else {
                        asset.status = 'In Use';
                        asset.assignedTo = {
                            name: employeeName,
                            department: empDept,
                            email: empEmail || `${employeeName.toLowerCase().replace(/\s+/g, '.')}@viros.com`,
                            role: 'Team Member',
                            assignedDate: 'Today'
                        };
                        asset.department = empDept;
                    }
                }

                if (actionType === 'return_to_stock') {
                    if (typeof showToast === 'function') showToast(`Asset "${asset.name}" returned to ready stock in database`, 'info');
                } else {
                    if (typeof showToast === 'function') showToast(`Asset "${asset.name}" assigned to ${employeeName} in database!`, 'success');
                }

                closeReassignModal();
                if (activeDrawerAssetId === id) {
                    openAssetDrawer(id);
                }
                renderAssets();
                updateKPIs();
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnText;
                }
                console.error(err);
                if (typeof showToast === 'function') showToast(err.message || 'Error updating assignment in database', 'danger');
            });
        });
    }

    // =========================================================================
    // 10. Delete / Retire Asset
    // =========================================================================

    let pendingDeleteAsset = null;
    let pendingBulkDeleteIds = [];

    const deleteAssetModal = document.getElementById('deleteAssetModal');
    const bulkDeleteModal = document.getElementById('bulkDeleteModal');

    window.openDeleteAssetModal = function (id) {
        const parsedId = parseInt(id, 10);
        const asset = assetsData.find(a => a.id === id || a.id === parsedId || a.tag === id);
        if (!asset) return;

        pendingDeleteAsset = asset;
        const nameEl = document.getElementById('deleteModalAssetName');
        const tagEl = document.getElementById('deleteModalAssetTag');
        if (nameEl) nameEl.textContent = `"${asset.name}"`;
        if (tagEl) tagEl.textContent = asset.tag ? `(${asset.tag})` : '';

        if (deleteAssetModal) {
            deleteAssetModal.style.display = 'flex';
            deleteAssetModal.classList.add('active');
        }
    };

    window.closeDeleteAssetModal = function () {
        if (deleteAssetModal) {
            deleteAssetModal.style.display = 'none';
            deleteAssetModal.classList.remove('active');
        }
        pendingDeleteAsset = null;
        const btn = document.getElementById('confirmDeleteAssetModalBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Yes, Delete Asset
            `;
        }
    };

    window.deleteAsset = function (id) {
        window.openDeleteAssetModal(id);
    };

    const confirmDeleteAssetBtn = document.getElementById('confirmDeleteAssetModalBtn');
    if (confirmDeleteAssetBtn) {
        confirmDeleteAssetBtn.addEventListener('click', function () {
            if (!pendingDeleteAsset) return;
            const asset = pendingDeleteAsset;

            confirmDeleteAssetBtn.disabled = true;
            confirmDeleteAssetBtn.innerHTML = `
                <span style="display:inline-flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    Deleting...
                </span>
            `;

            fetch(getApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: asset.id, hard: true })
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    // Completely remove from local assetsData
                    assetsData = assetsData.filter(a => a.id !== asset.id);
                    selectedAssetIds.delete(asset.id);

                    // Close slide-over drawer if open on this asset
                    if (window.activeDrawerAssetId === asset.id || activeDrawerAssetId === asset.id) {
                        closeAssetDrawer();
                    }

                    closeDeleteAssetModal();

                    if (typeof showToast === 'function') {
                        showToast(`Asset "${asset.name}" completely deleted. Attached components updated to Available.`, 'success');
                    }

                    // Refresh available components from backend to immediately sync parts dropdown
                    if (typeof refreshAvailableComponents === 'function') {
                        refreshAvailableComponents();
                    }

                    renderAssets();
                    updateKPIs();
                } else {
                    confirmDeleteAssetBtn.disabled = false;
                    confirmDeleteAssetBtn.innerHTML = `Yes, Delete Asset`;
                    if (typeof showToast === 'function') showToast((data && data.message) || 'Failed to delete asset', 'danger');
                }
            })
            .catch(err => {
                confirmDeleteAssetBtn.disabled = false;
                confirmDeleteAssetBtn.innerHTML = `Yes, Delete Asset`;
                console.error(err);
                if (typeof showToast === 'function') showToast('Network error while deleting asset', 'danger');
            });
        });
    }

    if (deleteAssetModal) {
        deleteAssetModal.addEventListener('click', function (e) {
            if (e.target === this) closeDeleteAssetModal();
        });
    }

    // =========================================================================
    // 11. Bulk Selection & Actions
    // =========================================================================

    function updateBulkBar() {
        if (!bulkActionsBar || !bulkCountEl) return;
        const count = selectedAssetIds.size;
        bulkCountEl.textContent = count;

        if (count > 0) {
            bulkActionsBar.classList.add('active');
        } else {
            bulkActionsBar.classList.remove('active');
        }

        // Sync header checkbox
        if (selectAllCheckbox) {
            const filtered = getFilteredAssets();
            selectAllCheckbox.checked = filtered.length > 0 && filtered.every(item => selectedAssetIds.has(item.id));
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const filtered = getFilteredAssets();
            if (this.checked) {
                filtered.forEach(item => selectedAssetIds.add(item.id));
            } else {
                filtered.forEach(item => selectedAssetIds.delete(item.id));
            }
            renderAssets();
        });
    }

    if (assetTableBody) {
        assetTableBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('asset-item-checkbox')) {
                const id = parseInt(e.target.dataset.id, 10);
                if (e.target.checked) {
                    selectedAssetIds.add(id);
                } else {
                    selectedAssetIds.delete(id);
                }
                updateBulkBar();
                // Toggle row class
                const tr = e.target.closest('tr');
                if (tr) tr.classList.toggle('row-selected', e.target.checked);
            }
        });
    }

    window.clearBulkSelection = function () {
        selectedAssetIds.clear();
        renderAssets();
    };

    window.bulkMarkStatus = function (newStatus) {
        if (selectedAssetIds.size === 0) return;
        const ids = Array.from(selectedAssetIds);
        fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'bulk_status', ids: ids, status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                assetsData.forEach(a => {
                    if (selectedAssetIds.has(a.id)) {
                        a.status = newStatus;
                        if (newStatus === 'Available' || newStatus === 'Retired') {
                            a.assignedTo = null;
                        }
                    }
                });
                if (typeof showToast === 'function') showToast(data.message || `Updated ${ids.length} assets to "${newStatus}" in database`, 'success');
                clearBulkSelection();
                updateKPIs();
            } else {
                if (typeof showToast === 'function') showToast((data && data.message) || 'Bulk update failed', 'danger');
            }
        })
        .catch(err => {
            console.error(err);
            if (typeof showToast === 'function') showToast('Network error on bulk update', 'danger');
        });
    };

    window.openBulkDeleteModal = function () {
        if (selectedAssetIds.size === 0) return;
        pendingBulkDeleteIds = Array.from(selectedAssetIds);
        const countText = document.getElementById('bulkDeleteCountText');
        if (countText) countText.textContent = `${pendingBulkDeleteIds.length}`;

        if (bulkDeleteModal) {
            bulkDeleteModal.style.display = 'flex';
            bulkDeleteModal.classList.add('active');
        }
    };

    window.closeBulkDeleteModal = function () {
        if (bulkDeleteModal) {
            bulkDeleteModal.style.display = 'none';
            bulkDeleteModal.classList.remove('active');
        }
        pendingBulkDeleteIds = [];
        const btn = document.getElementById('confirmBulkDeleteModalBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Yes, Delete Selected
            `;
        }
    };

    window.bulkDeleteAssets = function () {
        window.openBulkDeleteModal();
    };

    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDeleteModalBtn');
    if (confirmBulkDeleteBtn) {
        confirmBulkDeleteBtn.addEventListener('click', function () {
            if (pendingBulkDeleteIds.length === 0) return;
            const ids = [...pendingBulkDeleteIds];
            const count = ids.length;

            confirmBulkDeleteBtn.disabled = true;
            confirmBulkDeleteBtn.innerHTML = `
                <span style="display:inline-flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    Deleting...
                </span>
            `;

            fetch(getApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'bulk_delete', ids: ids })
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    const idSet = new Set(ids);
                    assetsData = assetsData.filter(a => !idSet.has(a.id));
                    selectedAssetIds.clear();

                    if (window.activeDrawerAssetId && idSet.has(window.activeDrawerAssetId)) {
                        closeAssetDrawer();
                    }

                    closeBulkDeleteModal();

                    if (typeof showToast === 'function') {
                        showToast(data.message || `Deleted ${count} asset(s) and released components to available inventory.`, 'success');
                    }

                    if (typeof refreshAvailableComponents === 'function') {
                        refreshAvailableComponents();
                    }

                    renderAssets();
                    updateKPIs();
                } else {
                    confirmBulkDeleteBtn.disabled = false;
                    confirmBulkDeleteBtn.innerHTML = `Yes, Delete Selected`;
                    if (typeof showToast === 'function') showToast((data && data.message) || 'Bulk delete failed', 'danger');
                }
            })
            .catch(err => {
                confirmBulkDeleteBtn.disabled = false;
                confirmBulkDeleteBtn.innerHTML = `Yes, Delete Selected`;
                console.error(err);
                if (typeof showToast === 'function') showToast('Network error on bulk delete', 'danger');
            });
        });
    }

    if (bulkDeleteModal) {
        bulkDeleteModal.addEventListener('click', function (e) {
            if (e.target === this) closeBulkDeleteModal();
        });
    }

    window.bulkPrintLabels = function () {
        if (selectedAssetIds.size === 0) return;
        if (typeof showToast === 'function') showToast(`Preparing barcode stickers for ${selectedAssetIds.size} assets...`, 'info');
        const firstId = Array.from(selectedAssetIds)[0];
        openLabelModal(firstId);
    };

    // =========================================================================
    // 12. CSV Export & Import (Real Database Integration)
    // =========================================================================

    window.exportAssetsCsv = function () {
        const filtered = getFilteredAssets();
        if (filtered.length === 0) {
            if (typeof showToast === 'function') showToast('No assets to export with current filters', 'warning');
            return;
        }

        const headers = ['Asset Tag Number', 'Asset Name', 'Category', 'Brand', 'Model', 'Serial Number', 'Status', 'Condition', 'Assigned To', 'Department', 'Location', 'Purchase Cost (INR)', 'Warranty Expiry'];
        const rows = filtered.map(a => [
            `"${a.tag || ''}"`,
            `"${a.name || ''}"`,
            `"${a.category || ''}"`,
            `"${a.brand || ''}"`,
            `"${a.model || ''}"`,
            `"${a.serial || ''}"`,
            `"${a.status || ''}"`,
            `"${a.condition || ''}"`,
            `"${a.assignedTo ? a.assignedTo.name : 'Unassigned'}"`,
            `"${a.department || ''}"`,
            `"${a.location || ''}"`,
            `"${(a.financials && a.financials.cost) || 0}"`,
            `"${(a.financials && a.financials.warrantyExpiry) || ''}"`
        ]);

        const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `viros_it_assets_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        if (typeof showToast === 'function') showToast(`Exported ${filtered.length} assets to CSV`, 'success');
    };

    window.openImportModal = function () {
        const modal = document.getElementById('importModal');
        if (modal) modal.style.display = 'flex';
    };

    window.closeImportModal = function () {
        const modal = document.getElementById('importModal');
        if (modal) modal.style.display = 'none';
    };

    // CSV File Import Handler
    const csvFileInput = document.getElementById('csvAssetFile');
    if (csvFileInput) {
        csvFileInput.addEventListener('change', function (e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (evt) {
                const text = evt.target.result;
                const rows = parseCsvContent(text);
                if (!rows || rows.length === 0) {
                    if (typeof showToast === 'function') showToast('No valid asset rows found in CSV file', 'warning');
                    return;
                }

                if (typeof showToast === 'function') showToast(`Importing ${rows.length} assets into database...`, 'info');

                fetch(getApiUrl(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'import', items: rows })
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        if (typeof showToast === 'function') showToast(data.message || `Successfully imported ${rows.length} assets!`, 'success');
                        closeImportModal();
                        loadAssetsFromDB();
                    } else {
                        if (typeof showToast === 'function') showToast((data && data.message) || 'CSV import failed', 'danger');
                    }
                    csvFileInput.value = '';
                })
                .catch(err => {
                    console.error(err);
                    if (typeof showToast === 'function') showToast('Network error during CSV import', 'danger');
                    csvFileInput.value = '';
                });
            };
            reader.readAsText(file);
        });
    }

    function parseCsvContent(csvText) {
        const lines = csvText.split(/\r?\n/).filter(l => l.trim().length > 0);
        if (lines.length < 2) return [];

        function parseLine(line) {
            const values = [];
            let inQuotes = false;
            let current = '';
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                if (char === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        current += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === ',' && !inQuotes) {
                    values.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            values.push(current.trim());
            return values;
        }

        const headers = parseLine(lines[0]).map(h => h.toLowerCase().replace(/[^a-z0-9]/g, ''));
        const items = [];

        for (let i = 1; i < lines.length; i++) {
            const cols = parseLine(lines[i]);
            if (!cols || cols.length === 0 || cols.every(c => !c)) continue;

            const rowObj = {};
            headers.forEach((h, idx) => {
                rowObj[h] = cols[idx] !== undefined ? cols[idx] : '';
            });

            const tag = rowObj['assettagnumber'] || rowObj['assettag'] || rowObj['tag'] || '';
            const name = rowObj['assetname'] || rowObj['name'] || '';
            const category = rowObj['category'] || 'General';
            const brand = rowObj['brand'] || '';
            const model = rowObj['model'] || '';
            const serial = rowObj['serialnumber'] || rowObj['serial'] || '';
            const status = rowObj['status'] || 'Available';
            const condition = rowObj['condition'] || 'Good';
            const location = rowObj['location'] || '';
            const department = rowObj['department'] || '';
            const cost = parseFloat(rowObj['purchasecostinr'] || rowObj['cost'] || rowObj['purchasecost'] || '0') || 0;
            const warranty = rowObj['warrantyexpiry'] || rowObj['warranty'] || '';

            if (name && serial) {
                items.push({
                    tag, name, category, brand, model, serial,
                    status, condition, location, department,
                    cost, warrantyExpiry: warranty
                });
            }
        }
        return items;
    }

    // =========================================================================
    // 13. Event Listeners for Filters & Search
    // =========================================================================

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentSearchQuery = this.value;
            currentPage = 1;
            renderAssets();
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', function () {
            currentCategoryFilter = this.value;
            currentPage = 1;
            renderAssets();
        });
    }

    if (deptFilter) {
        deptFilter.addEventListener('change', function () {
            currentDeptFilter = this.value;
            currentPage = 1;
            renderAssets();
        });
    }

    if (locationFilter) {
        locationFilter.addEventListener('change', function () {
            currentLocationFilter = this.value;
            currentPage = 1;
            renderAssets();
        });
    }

    if (conditionFilter) {
        conditionFilter.addEventListener('change', function () {
            currentConditionFilter = this.value;
            currentPage = 1;
            renderAssets();
        });
    }

    statusTabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            statusTabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilterStatus = this.dataset.status;
            currentPage = 1;
            renderAssets();
        });
    });

    // View Switcher (Table vs Grid)
    if (viewTableBtn && viewGridBtn) {
        viewTableBtn.addEventListener('click', function () {
            viewTableBtn.classList.add('active');
            viewGridBtn.classList.remove('active');
            currentViewMode = 'table';
            if (assetTableView) assetTableView.style.display = 'block';
            if (assetGridContainer) assetGridContainer.style.display = 'none';
        });

        viewGridBtn.addEventListener('click', function () {
            viewGridBtn.classList.add('active');
            viewTableBtn.classList.remove('active');
            currentViewMode = 'grid';
            if (assetTableView) assetTableView.style.display = 'none';
            if (assetGridContainer) assetGridContainer.style.display = 'grid';
        });
    }

    // Pagination helper
    window.goToPage = function (pageNum) {
        const filtered = getFilteredAssets();
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        if (pageNum < 1 || pageNum > totalPages || pageNum === currentPage) return;
        currentPage = pageNum;
        renderAssets();
        const tableCard = document.querySelector('.asset-table-card') || document.getElementById('assetTableView');
        if (tableCard) {
            tableCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 300, behavior: 'smooth' });
        }
    };

    window.resetAllFilters = function () {
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = 'all';
        if (deptFilter) deptFilter.value = 'all';
        if (locationFilter) locationFilter.value = 'all';
        if (conditionFilter) conditionFilter.value = 'all';

        currentSearchQuery = '';
        currentCategoryFilter = 'all';
        currentDeptFilter = 'all';
        currentLocationFilter = 'all';
        currentConditionFilter = 'all';
        currentFilterStatus = 'all';

        statusTabBtns.forEach(b => {
            b.classList.toggle('active', b.dataset.status === 'all');
        });

        currentPage = 1;
        renderAssets();
        if (typeof showToast === 'function') showToast('Filters reset', 'info');
    };

    // Copy to clipboard
    window.copyToClipboard = function (text, msg) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof showToast === 'function') showToast(msg || 'Copied to clipboard', 'info');
            });
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize Component input & SearchableSelect
    const compSelect = document.getElementById('newCompName');
    const compSerial = document.getElementById('newCompSerial');

    function syncPartSerialFromSelection() {
        if (!compSelect || !compSerial) return;
        const opt = compSelect.options[compSelect.selectedIndex];
        if (!opt || !compSelect.value) return;

        let sn = opt.dataset.serial || '';
        let tag = opt.dataset.tag || opt.dataset.sku || '';

        // If not found directly on option, check window.dynamicPartComponents
        if ((!sn || !tag) && window.dynamicPartComponents) {
            const match = window.dynamicPartComponents.find(p => p.name === compSelect.value && (p.serial || p.sku || p.tag));
            if (match) {
                if (!sn) sn = match.serial || '';
                if (!tag) tag = match.tag || match.sku || '';
            }
        }

        // Auto-fill serial input with serial number, or tag if no serial
        const valToFill = sn || tag || '';
        if (valToFill) {
            compSerial.value = valToFill;
            // Visual highlight/feedback
            compSerial.style.transition = 'background-color 0.25s ease, border-color 0.25s ease';
            compSerial.style.backgroundColor = '#ecfdf5';
            compSerial.style.borderColor = '#10b981';
            setTimeout(() => {
                compSerial.style.backgroundColor = '';
                compSerial.style.borderColor = '';
            }, 600);
        }
    }

    if (compSelect) {
        if (window.SearchableSelect && typeof window.SearchableSelect.init === 'function') {
            window.SearchableSelect.init(compSelect);
        }
        compSelect.addEventListener('change', syncPartSerialFromSelection);
    }

    if (compSerial) {
        compSerial.addEventListener('input', function () {
            const val = this.value.trim().toLowerCase();
            if (!val || !window.dynamicPartComponents || !compSelect) return;
            const match = window.dynamicPartComponents.find(p =>
                (p.serial && p.serial.toLowerCase() === val) ||
                (p.sku && p.sku.toLowerCase() === val)
            );
            if (match && compSelect.value !== match.name) {
                compSelect.value = match.name;
                if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
                    window.SearchableSelect.sync(compSelect);
                }
            }
        });
    }

    renderPartTodoList();

    // Fetch dynamic parts from API to ensure fresh options (Available only)
    window.refreshAvailableComponents = function () {
        if (typeof fetch !== 'function') return;
        fetch('api/components.php?status=Available')
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.components)) {
                    // Filter to Available only with no installed host asset
                    window.dynamicPartComponents = data.components.filter(c => 
                        (c.status || '').toLowerCase() === 'available' && 
                        (!c.installedAsset || c.installedAsset === '— (Unassigned / In Stock)' || c.installedAsset === '')
                    );
                    const sel = document.getElementById('newCompName');
                    if (sel) {
                        const curVal = sel.value;
                        sel.innerHTML = buildComponentOptionsHtml(curVal);
                        if (window.SearchableSelect && typeof window.SearchableSelect.sync === 'function') {
                            window.SearchableSelect.sync(sel);
                        }
                    }
                }
            })
            .catch(() => {});
    };

    window.refreshAvailableComponents();

    // Initial render
    renderAssets();
});
