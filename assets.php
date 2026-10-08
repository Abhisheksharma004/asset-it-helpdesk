<?php
// Asset Management & IT Service Desk Portal - Hardware & IT Asset Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "IT Asset Management - VIROS Portal";
$active_page = "assets";
$extra_css = ['css/categories.css', 'css/assets.css'];
$extra_js  = ['js/assets.js'];

// Include database to fetch dynamic components/parts
require_once __DIR__ . '/config/db.php';

$dynamicPartComponents = [];
if (isset($conn) && $conn !== false) {
    $partsQuery = "SELECT id, sku, serial, name, category, brand, model, specs, status, installed_asset 
                   FROM components 
                   WHERE status = 'Available' AND (installed_asset IS NULL OR installed_asset = '')
                   ORDER BY category ASC, name ASC";
    $partsStmt = sqlsrv_query($conn, $partsQuery);
    if ($partsStmt !== false) {
        while ($row = sqlsrv_fetch_array($partsStmt, SQLSRV_FETCH_ASSOC)) {
            $dynamicPartComponents[] = [
                'id'       => intval($row['id']),
                'sku'      => $row['sku'] ?? '',
                'serial'   => $row['serial'] ?? '',
                'name'     => $row['name'] ?? '',
                'category' => !empty($row['category']) ? $row['category'] : 'General Components',
                'brand'    => $row['brand'] ?? '',
                'model'    => $row['model'] ?? '',
                'specs'    => $row['specs'] ?? '',
                'status'   => $row['status'] ?? 'Available'
            ];
        }
        sqlsrv_free_stmt($partsStmt);
    }
}

if (empty($dynamicPartComponents)) {
    $dynamicPartComponents = [
        ['id' => 1, 'name' => 'Crucial 16GB DDR4 3200MHz SO-DIMM', 'category' => 'RAM & Memory Modules', 'serial' => 'CRU-DDR4-88492', 'sku' => 'PRT1026002', 'status' => 'Available'],
        ['id' => 2, 'name' => 'Kingston Fury Beast 32GB DDR5 5600MHz', 'category' => 'RAM & Memory Modules', 'serial' => 'KNG-DDR5-10293', 'sku' => 'PRT1026003', 'status' => 'Available'],
        ['id' => 3, 'name' => 'Samsung 980 PRO 1TB PCIe 4.0 NVMe M.2', 'category' => 'Solid State Drives (SSD)', 'serial' => 'SAM-NVME-99103', 'sku' => 'PRT1026005', 'status' => 'Available'],
        ['id' => 4, 'name' => 'Crucial MX500 500GB 2.5-Inch SATA SSD', 'category' => 'Solid State Drives (SSD)', 'serial' => 'CRU-SATA-44129', 'sku' => 'PRT1026006', 'status' => 'Available'],
        ['id' => 5, 'name' => 'Seagate IronWolf 4TB NAS Hard Drive', 'category' => 'Hard Disk Drives (HDD)', 'serial' => 'SEA-NAS-77218', 'sku' => 'PRT1026007', 'status' => 'Available'],
        ['id' => 6, 'name' => 'NVIDIA RTX A2000 12GB Workstation GPU', 'category' => 'Graphics & GPU Cards', 'serial' => 'NV-RTX-55102', 'sku' => 'PRT1026008', 'status' => 'Available'],
        ['id' => 7, 'name' => 'Intel Core i7-13700 Desktop Processor', 'category' => 'Processors & CPUs', 'serial' => 'INT-I7-33910', 'sku' => 'PRT1026009', 'status' => 'Available'],
        ['id' => 8, 'name' => 'Dell 58Wh 4-Cell Laptop Replacement Battery', 'category' => 'Laptop Batteries', 'serial' => 'DEL-BAT-22019', 'sku' => 'PRT1026010', 'status' => 'Available'],
        ['id' => 9, 'name' => 'Corsair RM750x 750W Fully Modular PSU', 'category' => 'Power Supply Units (PSU)', 'serial' => 'COR-750-66014', 'sku' => 'PRT1026011', 'status' => 'Available'],
        ['id' => 10, 'name' => 'Intel X550-T2 Dual Port 10GbE Network Card', 'category' => 'Network Interface Cards (NIC)', 'serial' => 'INT-NIC-12004', 'sku' => 'PRT1026012', 'status' => 'Available']
    ];
}
// Fetch active vendors / suppliers from master
$vendorsList = [];
if (isset($conn) && $conn !== false) {
    $vendorQuery = "SELECT id, vendor_name, status FROM vendors WHERE status = 'Active' ORDER BY vendor_name ASC";
    $vendorStmt = sqlsrv_query($conn, $vendorQuery);
    if ($vendorStmt !== false) {
        while ($row = sqlsrv_fetch_array($vendorStmt, SQLSRV_FETCH_ASSOC)) {
            $vendorsList[] = $row;
        }
        sqlsrv_free_stmt($vendorStmt);
    }
}

// Fallback vendor list if table is empty or offline
if (empty($vendorsList)) {
    $fallbackVendors = [
        'Airtel Enterprise Services',
        'Amazon Business India',
        'Apple Business Direct',
        'Canon India Pvt Ltd',
        'CDW Logistics',
        'Cisco Systems India',
        'Dell Technologies India',
        'HP India Sales Pvt Ltd',
        'Lenovo Global Technology',
        'Microsoft Corporation India',
        'QuickHeal & Seqrite Antivirus',
        'Redington India Ltd',
        'Tata Communications'
    ];
    foreach ($fallbackVendors as $idx => $vName) {
        $vendorsList[] = [
            'id'          => $idx + 1,
            'vendor_name' => $vName,
            'status'      => 'Active'
        ];
    }
}

// Fetch dynamic Categories from asset_categories table
$dbCategories = [];
if (isset($conn) && $conn !== false) {
    $cStmt = sqlsrv_query($conn, "SELECT category_name FROM asset_categories WHERE status = 'Active' ORDER BY category_name ASC");
    if ($cStmt !== false) {
        while ($r = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($r['category_name'])) $dbCategories[] = $r['category_name'];
        }
        sqlsrv_free_stmt($cStmt);
    }
}
if (empty($dbCategories)) {
    $dbCategories = ['Laptops', 'Desktops', 'Servers', 'Networking', 'Monitors', 'Tablets & Mobile', 'Printers'];
}

// Fetch dynamic Locations from locations table
$dbLocations = [];
if (isset($conn) && $conn !== false) {
    $lStmt = sqlsrv_query($conn, "SELECT location_name FROM locations WHERE status = 'Active' ORDER BY location_name ASC");
    if ($lStmt !== false) {
        while ($r = sqlsrv_fetch_array($lStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($r['location_name'])) $dbLocations[] = $r['location_name'];
        }
        sqlsrv_free_stmt($lStmt);
    }
}
if (empty($dbLocations)) {
    $dbLocations = [
        'Corporate HQ - Mumbai', 'Tech Hub - Bangalore', 'Branch Office - Delhi NCR',
        'Delivery Center - Hyderabad', 'Development Center - Pune', 'Operations Center - Chennai',
        'Regional Hub - Kolkata', 'Support Center - Ahmedabad', 'HQ - New York', 'Austin Hub', 'London Office', 'Singapore DC'
    ];
}

// Fetch dynamic Departments from departments table
$dbDepartments = [];
if (isset($conn) && $conn !== false) {
    $dStmt = sqlsrv_query($conn, "SELECT department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    if ($dStmt !== false) {
        while ($r = sqlsrv_fetch_array($dStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($r['department_name'])) $dbDepartments[] = $r['department_name'];
        }
        sqlsrv_free_stmt($dStmt);
    }
}
if (empty($dbDepartments)) {
    $dbDepartments = ['Software Engineering', 'IT Infrastructure', 'Design & Creative', 'Finance', 'Operations', 'Human Resources', 'Executive Management'];
}

// Fetch active Employees from employees table
$dbEmployees = [];
if (isset($conn) && $conn !== false) {
    $eStmt = sqlsrv_query($conn, "SELECT id, emp_code, first_name, last_name, email, designation FROM employees WHERE status = 'Active' ORDER BY first_name ASC");
    if ($eStmt !== false) {
        while ($r = sqlsrv_fetch_array($eStmt, SQLSRV_FETCH_ASSOC)) {
            $fullName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            if (!empty($fullName)) {
                $dbEmployees[] = [
                    'id'          => $r['id'],
                    'emp_code'    => $r['emp_code'] ?? '',
                    'name'        => $fullName,
                    'email'       => $r['email'] ?? '',
                    'designation' => $r['designation'] ?? ''
                ];
            }
        }
        sqlsrv_free_stmt($eStmt);
    }
}

// Fetch Real Assets from MS SQL Server database
$dbAssets = [];
if (isset($conn) && $conn !== false) {
    // Prefetch all components currently linked to assets in components table
    $installedComponentsByAsset = [];
    $icStmt = sqlsrv_query($conn, "SELECT id, sku, serial, name, category, brand, model, specs, status, installed_asset FROM components WHERE installed_asset IS NOT NULL AND installed_asset != ''");
    if ($icStmt !== false) {
        while ($icRow = sqlsrv_fetch_array($icStmt, SQLSRV_FETCH_ASSOC)) {
            $instAsset = trim(strval($icRow['installed_asset']));
            if (!isset($installedComponentsByAsset[$instAsset])) {
                $installedComponentsByAsset[$instAsset] = [];
            }
            $installedComponentsByAsset[$instAsset][] = [
                'component_id' => intval($icRow['id']),
                'id'           => intval($icRow['id']),
                'name'         => $icRow['name'] ?? '',
                'serial'       => $icRow['serial'] ?? '',
                'tag'          => $icRow['sku'] ?? '',
                'sku'          => $icRow['sku'] ?? '',
                'category'     => $icRow['category'] ?? '',
                'brand'        => $icRow['brand'] ?? '',
                'model'        => $icRow['model'] ?? '',
                'specs'        => $icRow['specs'] ?? '',
                'status'       => $icRow['status'] ?? 'Installed'
            ];
        }
        sqlsrv_free_stmt($icStmt);
    }

    $assetsStmt = sqlsrv_query($conn, "SELECT * FROM assets ORDER BY id DESC");
    if ($assetsStmt !== false) {
        while ($row = sqlsrv_fetch_array($assetsStmt, SQLSRV_FETCH_ASSOC)) {
            $assignedTo = null;
            $assignedVal = !empty($row['assigned_to']) ? trim($row['assigned_to']) : '';
            if (!empty($assignedVal)) {
                $eMatch = null;
                foreach ($dbEmployees as $e) {
                    if (strcasecmp($e['name'], $assignedVal) === 0 || (!empty($e['emp_code']) && strcasecmp($e['emp_code'], $assignedVal) === 0)) {
                        $eMatch = $e;
                        break;
                    }
                }
                if ($eMatch) {
                    $assignedTo = [
                        'name'         => $eMatch['name'],
                        'empCode'      => $eMatch['emp_code'] ?? '',
                        'email'        => $eMatch['email'] ?? '',
                        'department'   => $row['department'] ?: ($eMatch['designation'] ?? ''),
                        'role'         => $eMatch['designation'] ?? 'Team Member',
                        'assignedDate' => 'Active'
                    ];
                } else {
                    $assignedTo = [
                        'name'         => $assignedVal,
                        'empCode'      => '',
                        'email'        => strtolower(preg_replace('/\s+/', '.', $assignedVal)) . '@viros.com',
                        'department'   => $row['department'] ?? '',
                        'role'         => 'Team Member',
                        'assignedDate' => 'Active'
                    ];
                }
            }
            $aid = strval($row['id']);
            $atag = strval($row['tag'] ?? '');
            $components = $installedComponentsByAsset[$aid] ?? ($installedComponentsByAsset[$atag] ?? []);
            if (!empty($row['components_json'])) {
                $dec = json_decode($row['components_json'], true);
                if (is_array($dec)) {
                    foreach ($dec as $item) {
                        $exists = false;
                        foreach ($components as $cEx) {
                            if ((!empty($item['serial']) && $cEx['serial'] === $item['serial']) ||
                                (!empty($item['component_id']) && $cEx['component_id'] === $item['component_id'])) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $components[] = $item;
                        }
                    }
                }
            }
            $history = [];
            $tickets = [];
            $dbAssets[] = [
                'id'          => intval($row['id']),
                'tag'         => $row['tag'] ?? '',
                'name'        => $row['name'] ?? '',
                'category'    => $row['category'] ?? '',
                'brand'       => $row['brand'] ?? '',
                'model'       => $row['model'] ?? '',
                'serial'      => $row['serial'] ?? '',
                'status'      => $row['status'] ?? 'Available',
                'condition'   => $row['condition'] ?? 'Good',
                'location'    => $row['location'] ?? '',
                'department'  => $row['department'] ?? '',
                'assignedTo'  => $assignedTo,
                'specs'       => [
                    'processor'  => $row['processor'] ?? '',
                    'ram'        => $row['ram'] ?? '',
                    'storage'    => $row['storage'] ?? '',
                    'os'         => $row['os'] ?? '',
                    'macAddress' => $row['mac_address'] ?? '',
                    'ipAddress'  => $row['ip_address'] ?? ''
                ],
                'financials'  => [
                    'vendor'         => $row['vendor'] ?? '',
                    'poNumber'       => $row['po_number'] ?? '',
                    'purchaseDate'   => $row['purchase_date'] ?? '',
                    'cost'           => floatval($row['cost'] ?? 0),
                    'warrantyExpiry' => $row['warranty_expiry'] ?? ''
                ],
                'components'  => $components,
                'history'     => $history,
                'tickets'     => $tickets,
                'created_at'  => isset($row['created_at']) && $row['created_at'] instanceof DateTime ? $row['created_at']->format('Y-m-d H:i:s') : '',
                'updated_at'  => isset($row['updated_at']) && $row['updated_at'] instanceof DateTime ? $row['updated_at']->format('Y-m-d H:i:s') : ''
            ];
        }
        sqlsrv_free_stmt($assetsStmt);
    }
}

// Compute live real KPI statistics
$statsTotal = count($dbAssets);
$statsInUse = 0;
$statsAvailable = 0;
$statsMaintenance = 0;
$statsReserved = 0;
$statsRetired = 0;
$statsExpiring = 0;
$statsTotalValue = 0;
$refDate = (new DateTime())->setTime(0, 0, 0);
$thirtyDaysLater = (new DateTime())->modify('+30 days')->setTime(23, 59, 59);

foreach ($dbAssets as $a) {
    $st = $a['status'] ?? '';
    if ($st === 'In Use') $statsInUse++;
    elseif ($st === 'Available') $statsAvailable++;
    elseif ($st === 'Under Maintenance') $statsMaintenance++;
    elseif ($st === 'Reserved') $statsReserved++;
    elseif ($st === 'Retired') $statsRetired++;

    $costVal = floatval($a['financials']['cost'] ?? 0);
    $statsTotalValue += $costVal;

    if (!empty($a['financials']['warrantyExpiry'])) {
        try {
            $wDate = new DateTime($a['financials']['warrantyExpiry']);
            $wDate->setTime(0, 0, 0);
            if ($wDate >= $refDate && $wDate <= $thirtyDaysLater) {
                $statsExpiring++;
            }
        } catch (Exception $e) {}
    }
}
$stats = [
    'total'         => $statsTotal,
    'in_use'        => $statsInUse,
    'available'     => $statsAvailable,
    'maintenance'   => $statsMaintenance,
    'reserved'      => $statsReserved,
    'retired'       => $statsRetired,
    'expiring_soon' => $statsExpiring,
    'total_value'   => $statsTotalValue
];

$groupedComponents = [];
foreach ($dynamicPartComponents as $comp) {
    $cat = !empty($comp['category']) ? $comp['category'] : 'Other Components';
    if (!isset($groupedComponents[$cat])) {
        $groupedComponents[$cat] = [];
    }
    $groupedComponents[$cat][] = $comp;
}

// Include Modular Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Asset Management Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb & Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Assets</span>
                <span>/</span>
                <span>Asset Management</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                Hardware & IT Asset Management
            </h1>
            <p>Track hardware inventory, warranty lifecycles, user allocations, and asset depreciation across all branch offices.</p>
        </div>

        <div class="page-header-actions">

            <!-- Export CSV -->
            <button type="button" class="btn-secondary" id="exportAssetsBtn" title="Export assets to CSV" onclick="exportAssetsCsv()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Export CSV</span>
            </button>

            <!-- Import CSV -->
            <button type="button" class="btn-secondary" id="openImportBtn" title="Import assets from CSV" onclick="openImportModal()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span>Import CSV</span>
            </button>

            <!-- Add Asset Modal Trigger -->
            <button type="button" class="btn-primary" id="openAddModalBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add Asset</span>
            </button>
        </div>
    </div>

    <!-- Executive Metric KPI Strip -->
    <div class="asset-stats-grid">
        <!-- Total Assets -->
        <div class="asset-stat-card card-total" onclick="resetAllFilters()">
            <div class="asset-stat-info">
                <div class="stat-lbl">Total Assets</div>
                <div class="stat-val" id="statTotalAssets"><?php echo $stats['total']; ?></div>
                <div class="stat-sub">
                    <span class="stat-trend-up">Database Synced</span>
                </div>
            </div>
            <div class="asset-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <!-- In Use -->
        <div class="asset-stat-card card-in-use" onclick="document.querySelector('[data-status=in-use]').click()">
            <div class="asset-stat-info">
                <div class="stat-lbl">In Use / Assigned</div>
                <div class="stat-val" id="statInUseAssets"><?php echo $stats['in_use']; ?></div>
                <div class="stat-sub">
                    <span style="color: var(--success); font-weight: 600;">Active</span> deployments
                </div>
            </div>
            <div class="asset-stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
            </div>
        </div>

        <!-- Available / Ready Stock -->
        <div class="asset-stat-card card-available" onclick="document.querySelector('[data-status=available]').click()">
            <div class="asset-stat-info">
                <div class="stat-lbl">Available in Stock</div>
                <div class="stat-val" id="statAvailableAssets"><?php echo $stats['available']; ?></div>
                <div class="stat-sub">Ready for issue</div>
            </div>
            <div class="asset-stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
        </div>

        <!-- In Maintenance -->
        <div class="asset-stat-card card-maintenance" onclick="document.querySelector('[data-status=maintenance]').click()">
            <div class="asset-stat-info">
                <div class="stat-lbl">Under Maintenance</div>
                <div class="stat-val" id="statMaintenanceAssets"><?php echo $stats['maintenance']; ?></div>
                <div class="stat-sub">IT Depot & RMA</div>
            </div>
            <div class="asset-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
        </div>

        <!-- Expiring Warranty -->
        <div class="asset-stat-card card-expiring" style="cursor: pointer;" title="Click to filter assets with warranty expiring within 30 days">
            <div class="asset-stat-info">
                <div class="stat-lbl">Warranty Expiring</div>
                <div class="stat-val" id="statExpiringAssets" style="color: #ea580c;"><?php echo $stats['expiring_soon']; ?></div>
                <div class="stat-sub">Within next 30 days</div>
            </div>
            <div class="asset-stat-icon orange">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
        </div>

        <!-- Book Value -->
        <div class="asset-stat-card card-retired">
            <div class="asset-stat-info">
                <div class="stat-lbl">Est. Inventory Value</div>
                <div class="stat-val" id="statTotalValue">₹<?php echo number_format($stats['total_value']); ?></div>
                <div class="stat-sub">Historical PO total</div>
            </div>
            <div class="asset-stat-icon slate">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="4" x2="18" y2="4"></line>
                    <line x1="6" y1="9" x2="18" y2="9"></line>
                    <path d="M6 14h5a4 4 0 0 0 0-8H6"></path>
                    <path d="M11 14l6 7"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Status Tabs Navigation -->
    <div class="asset-status-tabs">
        <button type="button" class="status-tab-btn active" data-status="all">
            All Assets
            <span class="status-tab-badge" id="tabBadgeAll"><?php echo $stats['total']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="in-use">
            In Use
            <span class="status-tab-badge" id="tabBadgeInUse"><?php echo $stats['in_use']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="available">
            Available / In Stock
            <span class="status-tab-badge" id="tabBadgeAvail"><?php echo $stats['available']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="maintenance">
            Under Maintenance
            <span class="status-tab-badge" id="tabBadgeMaint"><?php echo $stats['maintenance']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="expiring" title="Assets with warranty expiring in next 30 days">
            Warranty Expiring
            <span class="status-tab-badge" id="tabBadgeExpiring" style="background: #ea580c; color: #fff;"><?php echo $stats['expiring_soon']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="reserved">
            Reserved
            <span class="status-tab-badge" id="tabBadgeRes"><?php echo $stats['reserved']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-status="retired">
            Retired / Disposed
            <span class="status-tab-badge" id="tabBadgeRet"><?php echo $stats['retired']; ?></span>
        </button>
    </div>

    <!-- Toolbar: Search, Filters & View Toggle -->
    <div class="asset-toolbar">
        <div class="toolbar-left">
            <!-- Search Input -->
            <div class="asset-search-wrapper">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="assetSearchInput" placeholder="Search by Asset Tag, Serial Number, Name, Model, Assignee...">
            </div>

            <!-- Category Filter -->
            <select class="asset-filter-select" id="categoryFilter">
                <option value="all">All Categories</option>
                <?php foreach ($dbCategories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Department Filter -->
            <select class="asset-filter-select" id="deptFilter">
                <option value="all">All Departments</option>
                <?php foreach ($dbDepartments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Location Filter -->
            <select class="asset-filter-select" id="locationFilter">
                <option value="all">All Locations</option>
                <?php foreach ($dbLocations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Condition Filter -->
            <select class="asset-filter-select" id="conditionFilter">
                <option value="all">All Conditions</option>
                <option value="Brand New">Brand New</option>
                <option value="Excellent">Excellent</option>
                <option value="Good">Good</option>
                <option value="Fair">Fair</option>
                <option value="Damaged">Damaged</option>
            </select>
        </div>

        <div class="toolbar-right">
            <!-- View Mode Switcher -->
            <div class="view-mode-toggle">
                <button type="button" class="view-btn active" id="viewTableBtn" title="Table View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                </button>
                <button type="button" class="view-btn" id="viewGridBtn" title="Grid / Card View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Bulk Actions Bar -->
    <div class="bulk-actions-bar" id="bulkActionsBar">
        <div class="bulk-info">
            <span class="bulk-selected-count" id="bulkSelectedCount">0</span>
            <span>assets selected</span>
        </div>
        <div class="bulk-btn-group">
            <button type="button" class="bulk-btn" onclick="bulkMarkStatus('Available')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Return to Stock
            </button>
            <button type="button" class="bulk-btn" onclick="bulkMarkStatus('Under Maintenance')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                Send to Repair
            </button>
            <button type="button" class="bulk-btn" onclick="bulkPrintLabels()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Print QR Labels
            </button>
            <button type="button" class="bulk-btn bulk-btn-danger" onclick="bulkDeleteAssets()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Delete Selected
            </button>
            <button type="button" class="bulk-btn" onclick="clearBulkSelection()">
                &times; Deselect All
            </button>
        </div>
    </div>

    <!-- Table View Container -->
    <div class="asset-table-card" id="assetTableView">
        <div class="asset-table-responsive">
            <table class="asset-data-table">
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" class="custom-checkbox" id="selectAllAssets">
                        </th>
                        <th>Asset Name & Tag</th>
                        <th>Category</th>
                        <th>Serial Number (S/N)</th>
                        <th>Assigned To</th>
                        <th>Location & Dept</th>
                        <th>Status & Warranty</th>
                        <th style="text-align: right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="assetTableBody">
                    <!-- Populated dynamically via js/assets.js -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grid / Card View Container -->
    <div class="asset-grid-view" id="assetGridContainer" style="display: none;">
        <!-- Populated dynamically via js/assets.js -->
    </div>

    <!-- Pagination Footer -->
    <div class="assets-pagination-card">
        <div class="pagination-info" id="paginationInfo">
            Showing <strong>1</strong> to <strong>10</strong> of <strong>842</strong> assets
        </div>
        <div class="pagination-controls" id="paginationControls">
            <!-- Dynamically populated pagination buttons -->
        </div>
    </div>

</main>

<!-- =========================================================================
     SLIDE-OVER DRAWER (Asset Details & Specifications)
     ========================================================================= -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>

<aside class="asset-drawer" id="assetDrawer">
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerAssetTag" style="font-size: 12px; font-weight: 700;">AST2024001</span>
                    <span id="drawerAssetStatus"></span>
                    <span class="serial-badge" id="drawerAssetSerial" style="font-size: 12px; font-weight: 600;">SN: C02G40PZMD6T</span>
                </div>
                <h3 id="drawerAssetName" style="margin-top: 4px;">MacBook Pro 16" M3 Max</h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeDrawerBtn" title="Close Drawer">&times;</button>
    </div>

    <!-- Drawer Navigation Tabs -->
    <!-- Drawer Navigation Tabs -->
    <div class="drawer-tabs">
        <button type="button" class="drawer-tab active" data-tab="overview">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Overview & Specs
        </button>
        <button type="button" class="drawer-tab" data-tab="components">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
            Components & Parts <span id="drawerCompBadge" style="margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span>
        </button>
        <button type="button" class="drawer-tab" data-tab="custody">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
            Custody & History
        </button>
        <button type="button" class="drawer-tab" data-tab="tickets">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Tickets & Maintenance
        </button>
    </div>

    <!-- Drawer Tab Panes -->
    <div class="drawer-content">
        <!-- Pane 1: Overview & Specs -->
        <div class="drawer-tab-pane active" id="pane_overview">
            <!-- Hardware Specifications -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    Hardware Specifications
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Asset Tag Number</div>
                        <div class="value" id="specAssetTag" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">AST2024001</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Category</div>
                        <div class="value" id="specCategory">Laptops</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Manufacturer / Brand</div>
                        <div class="value" id="specBrand">Apple</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Model Number</div>
                        <div class="value" id="specModel">MacBookPro18,1</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Serial Number</div>
                        <div class="value" id="specSerial">C02G40PZMD6T</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Processor / CPU</div>
                        <div class="value" id="specProcessor">M3 Max</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Installed Memory (RAM)</div>
                        <div class="value" id="specRam">36 GB</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Internal Storage</div>
                        <div class="value" id="specStorage">1 TB SSD</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Operating System</div>
                        <div class="value" id="specOs">macOS Sonoma</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">MAC Address</div>
                        <div class="value" id="specMac">F0:18:98:4C:AA:32</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">IP Address</div>
                        <div class="value" id="specIp">10.20.104.42</div>
                    </div>
                </div>
            </div>

            <!-- Installed Hardware Components Section in Overview -->
            <div class="drawer-section">
                <div class="drawer-section-title" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
                        Installed Hardware Components
                    </div>
                    <span id="drawerOverviewCompCount" style="font-size: 11.5px; font-weight: 700; color: var(--cyan-primary); background: #e0f2fe; padding: 2px 8px; border-radius: 12px;">0 Parts</span>
                </div>
                <div id="drawerOverviewComponentsWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Financial & Procurement Details -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Procurement & Warranty Lifecycle
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Supplier / Vendor</div>
                        <div class="value" id="specVendor">Apple Direct</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Purchase Order (PO #) / Invoice #</div>
                        <div class="value" id="specPo">PO-2024-8901</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Purchase Date</div>
                        <div class="value" id="specPurchaseDate">2024-01-08</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Original Purchase Cost</div>
                        <div class="value" id="specCost">₹2,89,900</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Warranty Status</div>
                        <div class="value" id="specWarranty">Active</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Condition Grade</div>
                        <div class="value" id="specCondition">Excellent</div>
                    </div>
                </div>
            </div>

            <!-- Location & Deployment -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Deployment Location
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Assigned Branch / Site</div>
                        <div class="value" id="specLocation">HQ - New York</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Department</div>
                        <div class="value" id="specDepartment">Software Engineering</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pane 2: Custody & History -->
        <div class="drawer-tab-pane" id="pane_custody">
            <div class="drawer-section">
                <div class="drawer-section-title">Current Custodian</div>
                <div id="drawerAssigneeWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title">Movement & Assignment Audit Trail</div>
                <div class="history-timeline" id="drawerTimelineWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <!-- Pane 3: Tickets & Maintenance -->
        <div class="drawer-tab-pane" id="pane_tickets">
            <div class="drawer-section">
                <div class="drawer-section-title">Linked IT Service Desk Incidents</div>
                <div id="drawerTicketsWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <!-- Pane 4: Components & Parts Detailed Tab -->
        <div class="drawer-tab-pane" id="pane_components">
            <div class="drawer-section">
                <div class="drawer-section-title" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>Linked Hardware Components & Upgrades (<span id="drawerPaneCompCount">0</span>)</span>
                    <button type="button" class="btn-secondary" style="padding: 4px 10px; font-size: 11.5px;" onclick="openEditModal(activeDrawerAssetId, 'components')">
                        + Attach / Edit Parts
                    </button>
                </div>
                <div id="drawerTabComponentsWrap">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div class="drawer-footer" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        <button type="button" class="btn-danger" style="background: #ef4444; color: #fff; border: 1px solid #dc2626; padding: 8px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="deleteAsset(activeDrawerAssetId)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Delete Asset
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn-secondary" onclick="openLabelModal(activeDrawerAssetId)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Print Label
            </button>
            <button type="button" class="btn-primary" onclick="openEditModal(activeDrawerAssetId)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                Edit Asset
            </button>
        </div>
    </div>
</aside>

<!-- =========================================================================
     MODAL 1: Add / Edit Asset Modal
     ========================================================================= -->
<div class="modal-overlay" id="assetModal" style="display: none;">
    <div class="modal-box" style="max-width: 780px;">
        <div class="modal-header">
            <h3 id="assetModalTitle">Register New IT Hardware Asset</h3>
            <button type="button" class="modal-close-btn" id="closeAssetModalBtn">&times;</button>
        </div>

        <!-- Modal Tab Headers -->
        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-btn active" data-tab="general">General Info</button>
            <button type="button" class="modal-tab-btn" data-tab="specs">Specs & Network</button>
            <button type="button" class="modal-tab-btn" data-tab="components">Components <span id="modalCompTabBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span></button>
            <button type="button" class="modal-tab-btn" data-tab="procurement">Procurement & Cost</button>
            <button type="button" class="modal-tab-btn" data-tab="placement">Location & Status</button>
        </div>

        <form id="assetForm">
            <input type="hidden" id="editAssetId">
            <div class="modal-body">
                
                <!-- Tab Pane 1: General Info -->
                <div class="modal-tab-pane active" id="modal_pane_general">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalLocation">Select Branch Location *</label>
                            <select id="modalLocation" required>
                                <option value="">Select Branch Location</option>
                                <?php foreach ($dbLocations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalCategory">Asset Category *</label>
                            <select id="modalCategory" required>
                                <?php foreach ($dbCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalAssetTag">Asset Tag Number <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Auto Generated)</span></label>
                            <input type="text" id="modalAssetTag" readonly style="background-color: #f1f5f9; cursor: not-allowed; font-family: monospace; font-weight: 600; color: var(--cyan-primary);">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalCondition">Physical Condition</label>
                            <select id="modalCondition">
                                <option value="Brand New">Brand New</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="modalAssetName">Asset Display Name *</label>
                        <input type="text" id="modalAssetName" placeholder="Enter asset name" required>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalBrand">Brand / Manufacturer *</label>
                            <input type="text" id="modalBrand" placeholder="Enter brand name" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalModel">Model Number / Specification</label>
                            <input type="text" id="modalModel" placeholder="Enter model number">
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="modalSerial">Serial Number (S/N)</label>
                        <input type="text" id="modalSerial" placeholder="Enter serial number">
                    </div>
                </div>

                <!-- Tab Pane 2: Specs & Network -->
                <div class="modal-tab-pane" id="modal_pane_specs">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalProcessor">Processor / CPU</label>
                            <input type="text" id="modalProcessor" placeholder="Enter processor">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalRam">Installed RAM</label>
                            <input type="text" id="modalRam" placeholder="Enter RAM">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalStorage">Storage Capacity & Type</label>
                            <input type="text" id="modalStorage" placeholder="Enter storage (e.g. 512 GB SSD)">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalOs">Installed OS / Firmware</label>
                            <input type="text" id="modalOs" placeholder="Enter OS (e.g. Windows 11)">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalMac">MAC Address</label>
                            <input type="text" id="modalMac" placeholder="Enter MAC address">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalIp">Static IP / IP Reservation</label>
                            <input type="text" id="modalIp" placeholder="Enter IP address (or DHCP)">
                        </div>
                    </div>
                </div>

                <!-- Tab Pane: Components -->
                <div class="modal-tab-pane" id="modal_pane_components">
                    <!-- Component Input Row -->
                    <div class="component-input-row" style="margin-bottom: 16px;">
                        <div class="modal-form-group" style="flex: 1;">
                            <label for="newCompSerial">Serial Number / Tag Number</label>
                            <input type="text" id="newCompSerial" class="component-serial" placeholder="Enter or scan serial number">
                        </div>
                        <div class="modal-form-group" style="flex: 1;">
                            <label for="newCompName">Component Name</label>
                            <select id="newCompName" class="component-name">
                                <option value="">Select Part / Component</option>
                                <?php foreach ($groupedComponents as $catName => $items): ?>
                                    <optgroup label="<?php echo htmlspecialchars($catName); ?>">
                                        <?php foreach ($items as $item): 
                                            $val = $item['name'];
                                            $sn  = $item['serial'] ?? '';
                                            $tag = $item['sku'] ?? ($item['tag'] ?? '');
                                        ?>
                                            <option value="<?php echo htmlspecialchars($val); ?>" 
                                                    data-id="<?php echo htmlspecialchars($item['id'] ?? ''); ?>"
                                                    data-serial="<?php echo htmlspecialchars($sn); ?>" 
                                                    data-sku="<?php echo htmlspecialchars($tag); ?>"
                                                    data-tag="<?php echo htmlspecialchars($tag); ?>">
                                                <?php echo htmlspecialchars($val); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group" style="flex: 0 0 38px;">
                            <label>&nbsp;</label>
                            <button type="button" class="btn-add-comp-row" id="btnAddPartTodo" onclick="addPartTodo()" title="Add Part to List">+</button>
                        </div>
                    </div>

                    <!-- Added Parts To-Do List Container -->
                    <div class="added-parts-section">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color: var(--cyan-primary);">
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
                                <span>Attached Hardware Components</span>
                                <span id="partTodoCount" style="background: var(--cyan-light, #e0f2fe); color: var(--cyan-primary, #0093a7); font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span>
                            </div>
                        </div>
                        <div id="partTodoListContainer" class="part-todo-list">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 3: Procurement & Cost -->
                <div class="modal-tab-pane" id="modal_pane_procurement">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalVendor">Supplier / Vendor</label>
                            <select id="modalVendor">
                                <option value="">Select Supplier / Vendor</option>
                                <?php foreach ($vendorsList as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v['vendor_name']); ?>">
                                        <?php echo htmlspecialchars($v['vendor_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalPoNumber">Purchase Order (PO #) / Invoice Number</label>
                            <input type="text" id="modalPoNumber" placeholder="Enter PO or Invoice number">
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="modal-form-group">
                            <label for="modalPurchaseDate">Purchase Date</label>
                            <input type="date" id="modalPurchaseDate">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalCost">Purchase Price (₹ INR)</label>
                            <input type="number" step="0.01" id="modalCost" placeholder="0.00">
                        </div>
                        <div class="modal-form-group">
                            <label for="modalWarrantyExpiry">Warranty Expiry Date</label>
                            <input type="date" id="modalWarrantyExpiry">
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 4: Location & Status -->
                <div class="modal-tab-pane" id="modal_pane_placement">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="modalStatus">Initial Asset Status *</label>
                            <select id="modalStatus" required>
                                <option value="Available">Available (In Stock)</option>
                                <option value="In Use">In Use (Assigned)</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Reserved">Reserved</option>
                                <option value="Retired">Retired / Scrapped</option>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="modalDepartment">Assigned Department</label>
                            <select id="modalDepartment">
                                <?php foreach ($dbDepartments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAssetModalBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveAssetBtn">Save Asset Record</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: Print Thermal Barcode & QR Sticker Modal
     ========================================================================= -->
<div class="modal-overlay" id="labelModal" style="display: none;">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Print Physical Asset Label</h3>
            <button type="button" class="modal-close-btn" onclick="closeLabelModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Select Printer & Hardware Configuration Panel -->
            <div class="printer-selection-panel" style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 13px 15px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <label for="labelPrinterSelect" style="font-size: 12.5px; font-weight: 700; color: var(--navy-primary); display: flex; align-items: center; gap: 7px; margin: 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Select System Printer
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span id="printerStatusBadge" style="font-size: 11px; font-weight: 600; color: #16a34a; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
                            Ready / Online
                        </span>
                        <button type="button" id="refreshPrintersBtn" onclick="refreshSystemPrinters(true)" title="Scan / Refresh System Printers" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <div style="position: relative;">
                    <select id="labelPrinterSelect" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 13px; font-weight: 500; background: #ffffff; color: var(--text-primary); cursor: pointer;" onchange="handleLabelPrinterChange(this.value)">
                        <option value="" disabled selected>Detecting installed system printers...</option>
                    </select>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 9px; padding-top: 8px; border-top: 1px dashed #e2e8f0; font-size: 11.5px; color: var(--text-secondary);">
                    <div id="printerMediaInfo" style="display: flex; align-items: center; gap: 5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        <span id="selectedPrinterNameText">Printer: <strong>Detecting...</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="labelCopiesCount" style="font-size: 11px; font-weight: 600; color: var(--text-muted); margin: 0;">Copies:</label>
                        <select id="labelCopiesCount" style="padding: 2px 6px; font-size: 11.5px; border: 1px solid var(--border-color); border-radius: 4px; background: #ffffff; cursor: pointer;">
                            <option value="1" selected>1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Asset Details Summary Card -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; margin-bottom: 16px; box-shadow: var(--shadow-sm);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Asset To Print</span>
                    <span id="lblStickerTag" style="font-family: monospace; font-size: 12px; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 2px 8px; border-radius: 4px;"></span>
                </div>
                <div style="font-size: 14.5px; font-weight: 700; color: var(--navy-primary); margin-bottom: 4px;" id="lblStickerName"></div>
                <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: var(--text-secondary);">
                    <div id="lblStickerSerial"></div>
                    <div id="lblStickerCategory"></div>
                </div>
                <div style="font-size: 11px; color: #0369a1; background: #f0f9ff; border: 1px solid #bae6fd; padding: 7px 10px; border-radius: 6px; margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span>Template: <strong>Fortune Marketing (50x25mm ZPL)</strong> will be sent directly to your thermal printer.</span>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-secondary" onclick="closeLabelModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="printSticker()" id="btnPrintStickerBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Sticker
            </button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: QR & Barcode Scanner Simulation Modal
     ========================================================================= -->
<div class="modal-overlay" id="scannerModal" style="display: none;">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Asset Barcode & QR Scanner</h3>
            <button type="button" class="modal-close-btn" onclick="closeScannerModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="scanner-viewport">
                <div class="scan-reticle">
                    <div class="reticle-corner corner-tl"></div>
                    <div class="reticle-corner corner-tr"></div>
                    <div class="reticle-corner corner-bl"></div>
                    <div class="reticle-corner corner-br"></div>
                    <div class="scan-laser"></div>
                </div>
                <div class="scanner-hint">Align QR Code or Barcode within frame</div>
            </div>

            <div class="modal-form-group">
                <label for="manualScanInput">Scan with USB Gun or Type Tag / Serial Number</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="manualScanInput" placeholder="Scan or enter tag / serial number">
                    <button type="button" class="btn-primary" onclick="handleManualScan()">Lookup</button>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
                    Sample: <code>AST2024001</code> or <code>C02G40PZMD6T</code>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeScannerModal()">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: Reassign / Check-out Asset Modal
     ========================================================================= -->
<div class="modal-overlay" id="reassignModal" style="display: none;">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Transfer & Assign Asset</h3>
            <button type="button" class="modal-close-btn" onclick="closeReassignModal()">&times;</button>
        </div>
        <form id="reassignForm">
            <input type="hidden" id="reassignAssetId">
            <div class="modal-body">
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--text-muted);">Target Asset</div>
                    <div style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-top: 2px;" id="reassignAssetName">MacBook Pro 16" M3 Max</div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="reassignAssetSerial">SN: C02G40PZMD6T</div>
                </div>

                <div class="modal-form-group">
                    <label for="reassignActionType">Action Type *</label>
                    <select id="reassignActionType">
                        <option value="assign_employee">Issue / Assign to Employee</option>
                        <option value="return_to_stock">Return to Ready Stock (Unassign)</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="reassignEmpName">Recipient Employee *</label>
                    <input type="text" id="reassignEmpName" list="reassignEmpDatalist" placeholder="Select or type employee name">
                    <datalist id="reassignEmpDatalist">
                        <?php foreach ($dbEmployees as $emp): ?>
                            <option value="<?php echo htmlspecialchars($emp['name']); ?>" data-dept="<?php echo htmlspecialchars($emp['designation'] ?? ''); ?>" data-email="<?php echo htmlspecialchars($emp['email']); ?>" data-code="<?php echo htmlspecialchars($emp['emp_code']); ?>">
                                <?php echo htmlspecialchars($emp['emp_code'] . ' - ' . $emp['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="modal-form-group">
                    <label for="reassignDept">Department *</label>
                    <select id="reassignDept">
                        <?php foreach ($dbDepartments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="reassignEmail">Work Email</label>
                    <input type="email" id="reassignEmail" placeholder="Enter work email">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeReassignModal()">Cancel</button>
                <button type="submit" class="btn-primary">Confirm Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 5: Import CSV Modal
     ========================================================================= -->
<div class="modal-overlay" id="importModal" style="display: none;">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Import Assets from CSV</h3>
            <button type="button" class="modal-close-btn" onclick="closeImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="file-dropzone" style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 32px 20px; text-align: center; background: #f8fafc; cursor: pointer;" onclick="document.getElementById('csvAssetFile').click()">
                <input type="file" id="csvAssetFile" accept=".csv" style="display: none;">
                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary); margin-bottom: 10px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <line x1="9" y1="15" x2="12" y2="12"></line>
                    <line x1="15" y1="15" x2="12" y2="12"></line>
                </svg>
                <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Click or drag & drop asset CSV here</div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Supports Asset Tag, Serial, Category, Brand, Specs & Cost</div>
            </div>
            <div style="margin-top: 14px; text-align: right;">
                <a href="#" style="font-size: 12px; color: var(--cyan-primary); text-decoration: none;" onclick="exportAssetsCsv(); return false;">Download Sample Template CSV</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeImportModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 6: Delete Asset Confirmation Modal (Portal Design System)
     ========================================================================= -->
<div class="modal-overlay" id="deleteAssetModal" style="display: none;">
    <div class="modal-box" style="max-width: 460px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 style="font-size: 18px; font-weight: 700; color: var(--navy-primary); margin-bottom: 8px;">Delete Asset Completely?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 14px;">
                Are you sure you want to delete asset <strong id="deleteModalAssetName" style="color: var(--text-primary);"></strong> <span id="deleteModalAssetTag" style="color: var(--cyan-primary); font-weight: 600;"></span>?
            </p>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; margin-bottom: 22px; text-align: left; font-size: 12.5px; color: #991b1b; display: flex; gap: 10px; align-items: flex-start; line-height: 1.45;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>This will permanently remove the asset from the database. <strong>All hardware components</strong> attached to this asset will automatically be released back to <strong>Available</strong> inventory.</span>
            </div>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelDeleteAssetModalBtn" onclick="closeDeleteAssetModal()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteAssetModalBtn" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Yes, Delete Asset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 7: Bulk Delete Confirmation Modal (Portal Design System)
     ========================================================================= -->
<div class="modal-overlay" id="bulkDeleteModal" style="display: none;">
    <div class="modal-box" style="max-width: 460px;">
        <div class="delete-modal-content">
            <div class="delete-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </div>
            <h3 style="font-size: 18px; font-weight: 700; color: var(--navy-primary); margin-bottom: 8px;">Delete Selected Assets?</h3>
            <p style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 14px;">
                Are you sure you want to permanently delete <strong id="bulkDeleteCountText" style="color: var(--text-primary);"></strong> selected assets?
            </p>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; margin-bottom: 22px; text-align: left; font-size: 12.5px; color: #991b1b; display: flex; gap: 10px; align-items: flex-start; line-height: 1.45;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>These assets will be permanently deleted from the database. All hardware components attached to them will automatically be updated to <strong>Available</strong> stock.</span>
            </div>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn-secondary" id="cancelBulkDeleteModalBtn" onclick="closeBulkDeleteModal()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmBulkDeleteModalBtn" style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Yes, Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.dynamicPartComponents = <?php echo json_encode($dynamicPartComponents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.INITIAL_ASSETS = <?php echo json_encode($dbAssets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.INITIAL_STATS = <?php echo json_encode($stats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.DB_EMPLOYEES = <?php echo json_encode($dbEmployees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Global Footer & Modals
include 'includes/footer.php';
?>
