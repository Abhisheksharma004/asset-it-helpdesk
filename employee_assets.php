<?php
// Asset Management & IT Service Desk Portal - Dedicated Employee My Assets Page (UI)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title  = "My IT Assets & Custody - VIROS IT Portal";
$active_page = "employee_assets";
$extra_css   = ['css/employee_dashboard.css', 'css/employee_assets.css'];
$extra_js    = ['js/employee_dashboard.js', 'js/employee_assets.js'];

// Include Database Configuration
require_once __DIR__ . '/config/db.php';

// Dynamically resolve target employee (Session or GET request or Top 1 in DB)
$targetEmpId   = !empty($_GET['id']) ? intval($_GET['id']) : (!empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);
$targetEmpMail = !empty($_GET['email']) ? trim($_GET['email']) : (!empty($_SESSION['user_email']) ? trim($_SESSION['user_email']) : '');
$targetEmpCode = !empty($_GET['emp']) ? trim($_GET['emp']) : (!empty($_SESSION['user_username']) ? trim($_SESSION['user_username']) : '');

$activeEmployee = null;

if (isset($conn) && $conn !== false) {
    $currStmt = null;

    // 1. Fetch matching employee if session or parameter is present
    if ($targetEmpId > 0 || !empty($targetEmpMail) || !empty($targetEmpCode)) {
        $currStmt = sqlsrv_query(
            $conn, 
            "SELECT e.*, d.department_name, l.location_name 
             FROM employees e 
             LEFT JOIN departments d ON e.department_id = d.id 
             LEFT JOIN locations l ON e.location_id = l.id 
             WHERE e.id = ? OR LOWER(e.email) = LOWER(?) OR LOWER(e.emp_code) = LOWER(?)", 
            [$targetEmpId, $targetEmpMail, $targetEmpCode]
        );
    }

    // 2. Fallback: if session was not set or no match, fetch first active employee from database
    if (!$currStmt || !($currRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC))) {
        $currStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 e.*, d.department_name, l.location_name 
             FROM employees e 
             LEFT JOIN departments d ON e.department_id = d.id 
             LEFT JOIN locations l ON e.location_id = l.id 
             WHERE e.status = 'Active' OR e.status IS NULL 
             ORDER BY e.id ASC"
        );
        if ($currStmt) {
            $currRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC);
        }
    }

    if (!empty($currRow)) {
        $fullName = trim(($currRow['first_name'] ?? '') . ' ' . ($currRow['last_name'] ?? ''));

        // Dynamic Date Formatter from Database
        $joinDateFormatted = 'Not Provided';
        if (!empty($currRow['joining_date'])) {
            $joinDateFormatted = is_object($currRow['joining_date']) 
                ? $currRow['joining_date']->format('d M Y') 
                : date('d M Y', strtotime($currRow['joining_date']));
        } elseif (!empty($currRow['created_at'])) {
            $joinDateFormatted = is_object($currRow['created_at']) 
                ? $currRow['created_at']->format('d M Y') 
                : date('d M Y', strtotime($currRow['created_at']));
        }

        $activeEmployee = [
            'id'           => (int)$currRow['id'],
            'name'         => $fullName ?: 'Employee',
            'code'         => $currRow['emp_code'] ?? 'EMP-001',
            'designation'  => !empty($currRow['designation']) ? $currRow['designation'] : 'Staff Member',
            'department'   => !empty($currRow['department_name']) ? $currRow['department_name'] : 'General',
            'email'        => $currRow['email'] ?? '',
            'phone'        => !empty($currRow['phone']) ? $currRow['phone'] : 'Not Provided',
            'location'     => !empty($currRow['location_name']) ? $currRow['location_name'] : 'Corporate Office',
            'joining_date' => $joinDateFormatted,
            'status'       => $currRow['status'] ?? 'Active',
            'custody'      => (strtolower(trim($currRow['status'] ?? '')) === 'active') ? 'Verified & Active' : 'Inactive'
        ];

        // Ensure session has active employee credentials for seamless actions
        if (empty($_SESSION['user_id'])) {
            $_SESSION['user_id']        = $activeEmployee['id'];
            $_SESSION['user_name']      = $activeEmployee['name'];
            $_SESSION['user_username']  = $activeEmployee['code'];
            $_SESSION['user_email']     = $activeEmployee['email'];
            $_SESSION['user_role']      = 'Employee';
            $_SESSION['user_dept']      = $activeEmployee['department'];
            $_SESSION['logged_in']      = true;
            $_SESSION['is_first_login'] = 0;
        }

        if ($currStmt) {
            sqlsrv_free_stmt($currStmt);
        }
    }
}

// Fallback in case database was unreachable
if (!$activeEmployee) {
    $activeEmployee = [
        'id'           => 1,
        'name'         => $_SESSION['user_name'] ?? 'Employee Member',
        'code'         => $_SESSION['user_username'] ?? 'EMP-001',
        'designation'  => 'Staff Member',
        'department'   => $_SESSION['user_dept'] ?? 'General',
        'email'        => $_SESSION['user_email'] ?? 'employee@viros.in',
        'phone'        => 'Not Provided',
        'location'     => 'Corporate Office',
        'joining_date' => 'Not Provided',
        'status'       => 'Active',
        'custody'      => 'Verified & Active'
    ];
}

// Compute initials for Avatar
$avatarInitials = '';
foreach (explode(' ', trim($activeEmployee['name'])) as $w) {
    if (!empty($w)) $avatarInitials .= strtoupper($w[0]);
    if (strlen($avatarInitials) >= 2) break;
}
if (empty($avatarInitials)) $avatarInitials = 'EM';

// -----------------------------------------------------------------------------
// DYNAMIC FETCH: Active Employee Allocated Inventory (Assets, Accessories, Software)
// -----------------------------------------------------------------------------
$allocatedItems = [];
$seenTags       = [];

if (isset($conn) && $conn !== false && !empty($activeEmployee['id'])) {
    $empId   = $activeEmployee['id'];
    $empEmail= $activeEmployee['email'];
    $empCode = $activeEmployee['code'];
    $empName = $activeEmployee['name'];

    // 1. Fetch Active Handover Slips & Allocated Items from asset_assignments
    $assignSql = "SELECT a.* 
                  FROM asset_assignments a 
                  WHERE (a.employee_id = ? OR LOWER(a.employee_email) = LOWER(?) OR LOWER(a.emp_code) = LOWER(?))
                    AND a.custody_status = 'Active'
                  ORDER BY a.id DESC";
    $assignStmt = sqlsrv_query($conn, $assignSql, [$empId, $empEmail, $empCode]);

    if ($assignStmt) {
        while ($r = sqlsrv_fetch_array($assignStmt, SQLSRV_FETCH_ASSOC)) {
            $assignedDate = 'Recent';
            if (!empty($r['assigned_date'])) {
                $assignedDate = is_object($r['assigned_date']) 
                    ? $r['assigned_date']->format('d M Y') 
                    : date('d M Y', strtotime($r['assigned_date']));
            }

            // Primary Allocated Hardware Asset
            if (!empty($r['asset_tag']) && !in_array($r['asset_tag'], $seenTags)) {
                $seenTags[] = $r['asset_tag'];
                $catLower   = strtolower($r['category'] ?? '');
                $nameLower  = strtolower($r['asset_name'] ?? '');

                $iconType = 'hardware';
                if (strpos($catLower, 'laptop') !== false || strpos($nameLower, 'laptop') !== false) {
                    $iconType = 'laptop';
                } elseif (strpos($catLower, 'monitor') !== false || strpos($nameLower, 'monitor') !== false || strpos($catLower, 'display') !== false) {
                    $iconType = 'monitor';
                }

                $specs = !empty($r['specs']) ? $r['specs'] : trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? ''));

                $allocatedItems[] = [
                    'tag'       => $r['asset_tag'],
                    'name'      => !empty($r['asset_name']) ? $r['asset_name'] : 'Corporate Hardware Unit',
                    'specs'     => $specs ?: 'Configured computing system',
                    'category'  => !empty($r['category']) ? $r['category'] : 'Computing Unit',
                    'type'      => 'hardware',
                    'serial'    => !empty($r['serial']) ? $r['serial'] : 'S/N: Not Specified',
                    'date'      => $assignedDate,
                    'condition' => !empty($r['condition']) ? $r['condition'] : 'Good',
                    'status'    => 'In Active Custody',
                    'badge'     => 'badge-resolved',
                    'icon_type' => $iconType,
                    'slip_no'   => $r['slip_no']
                ];
            }

            // Additional Bundled Hardware Assets from assets_json
            if (!empty($r['assets_json'])) {
                $bundledAssets = json_decode($r['assets_json'], true);
                if (is_array($bundledAssets)) {
                    foreach ($bundledAssets as $ba) {
                        $baTag = $ba['tag'] ?? '';
                        if (!empty($baTag) && !in_array($baTag, $seenTags)) {
                            $seenTags[] = $baTag;
                            $baCatLower = strtolower($ba['category'] ?? '');
                            $baIcon = (strpos($baCatLower, 'laptop') !== false) ? 'laptop' : ((strpos($baCatLower, 'monitor') !== false) ? 'monitor' : 'hardware');

                            $allocatedItems[] = [
                                'tag'       => $baTag,
                                'name'      => $ba['name'] ?? 'Hardware Unit',
                                'specs'     => $ba['specs'] ?? ($ba['brand'] . ' ' . ($ba['model'] ?? '')),
                                'category'  => $ba['category'] ?? 'Hardware',
                                'type'      => 'hardware',
                                'serial'    => !empty($ba['serial']) ? $ba['serial'] : 'S/N: N/A',
                                'date'      => $assignedDate,
                                'condition' => $ba['condition'] ?? 'Good',
                                'status'    => 'In Active Custody',
                                'badge'     => 'badge-resolved',
                                'icon_type' => $baIcon,
                                'slip_no'   => $r['slip_no']
                            ];
                        }
                    }
                }
            }

            // Bundled Peripherals & Accessories from accessories_json
            if (!empty($r['accessories_json'])) {
                $accList = json_decode($r['accessories_json'], true);
                if (is_array($accList)) {
                    foreach ($accList as $acc) {
                        $accTag = !empty($acc['sku']) ? $acc['sku'] : ('ACC-' . ($acc['id'] ?? uniqid()));
                        if (in_array($accTag, $seenTags)) continue;
                        $seenTags[] = $accTag;

                        $accName  = $acc['name'] ?? 'Workstation Accessory';
                        $accCat   = $acc['category'] ?? 'Peripheral';
                        $accBrand = $acc['brand'] ?? '';
                        $accQty   = $acc['qty'] ?? 1;

                        $catLower = strtolower($accCat . ' ' . $accName);
                        $iconType = 'dock';
                        if (strpos($catLower, 'keyboard') !== false) $iconType = 'keyboard';
                        elseif (strpos($catLower, 'mouse') !== false) $iconType = 'mouse';
                        elseif (strpos($catLower, 'headset') !== false || strpos($catLower, 'audio') !== false) $iconType = 'headset';

                        $allocatedItems[] = [
                            'tag'       => $accTag,
                            'name'      => $accName,
                            'specs'     => ($accBrand ? $accBrand . ' • ' : '') . 'Qty: ' . $accQty . ' Unit(s)',
                            'category'  => $accCat ?: 'Workstation Accessory',
                            'type'      => 'accessory',
                            'serial'    => 'SKU: ' . $accTag,
                            'date'      => $assignedDate,
                            'condition' => 'Good',
                            'status'    => 'Issued & In Use',
                            'badge'     => 'badge-progress',
                            'icon_type' => $iconType,
                            'slip_no'   => $r['slip_no']
                        ];
                    }
                }
            }
        }
        sqlsrv_free_stmt($assignStmt);
    }

    // 2. Also check assets table directly (where assigned_to matches employee name or code)
    $assetSql = "SELECT ast.* FROM assets ast WHERE (LOWER(ast.assigned_to) = LOWER(?) OR LOWER(ast.assigned_to) = LOWER(?)) AND ast.status = 'In Use'";
    $assetStmt = sqlsrv_query($conn, $assetSql, [$empName, $empCode]);
    if ($assetStmt) {
        while ($ast = sqlsrv_fetch_array($assetStmt, SQLSRV_FETCH_ASSOC)) {
            if (!empty($ast['tag']) && !in_array($ast['tag'], $seenTags)) {
                $seenTags[] = $ast['tag'];
                $specsParts = array_filter([$ast['processor'] ?? '', $ast['ram'] ?? '', $ast['storage'] ?? '', $ast['os'] ?? '']);
                $specs = !empty($specsParts) ? implode(' • ', $specsParts) : trim(($ast['brand'] ?? '') . ' ' . ($ast['model'] ?? ''));

                $catLower = strtolower($ast['category'] ?? '');
                $iconType = (strpos($catLower, 'laptop') !== false) ? 'laptop' : ((strpos($catLower, 'monitor') !== false) ? 'monitor' : 'hardware');

                $astDate = 'Recent';
                if (!empty($ast['updated_at'])) {
                    $astDate = is_object($ast['updated_at']) ? $ast['updated_at']->format('d M Y') : date('d M Y', strtotime($ast['updated_at']));
                }

                $allocatedItems[] = [
                    'tag'       => $ast['tag'],
                    'name'      => $ast['name'],
                    'specs'     => $specs ?: 'Hardware computing unit',
                    'category'  => $ast['category'] ?: 'Hardware',
                    'type'      => 'hardware',
                    'serial'    => !empty($ast['serial']) ? $ast['serial'] : 'S/N: Not Specified',
                    'date'      => $astDate,
                    'condition' => !empty($ast['condition']) ? $ast['condition'] : 'Good',
                    'status'    => 'In Active Custody',
                    'badge'     => 'badge-resolved',
                    'icon_type' => $iconType,
                    'slip_no'   => 'HS-' . $ast['tag']
                ];
            }
        }
        sqlsrv_free_stmt($assetStmt);
    }
}

// Compute dynamic item counters
$totalAssetCount = count($allocatedItems);
$hardwareCount   = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'hardware'));
$accessoryCount  = count(array_filter($allocatedItems, fn($i) => $i['type'] === 'accessory'));

// Layout Components
include 'includes/header.php';
include 'includes/employee_sidebar.php';
include 'includes/employee_topbar.php';
?>

<!-- My Assets Dedicated Page Content -->
<main class="dashboard-content">

    <!-- Breadcrumb -->
    <nav class="assets-breadcrumb">
        <a href="employee_dashboard.php">Home</a>
        <span>/</span>
        <a href="employee_dashboard.php">My Dashboard</a>
        <span>/</span>
        <span class="current">My Allocated Assets</span>
    </nav>

    <!-- Metric Summary Stats Row (SVG Icons Only) -->
    <div class="assets-stats-grid">
        <div class="assets-stat-card">
            <div class="assets-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $totalAssetCount; ?> Total</div>
                <div class="lbl">Active Items in Custody</div>
            </div>
        </div>

        <div class="assets-stat-card">
            <div class="assets-stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                    <line x1="2" y1="20" x2="22" y2="20"></line>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $hardwareCount; ?> Devices</div>
                <div class="lbl">Computing Laptops & Displays</div>
            </div>
        </div>

        <div class="assets-stat-card">
            <div class="assets-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="6" y1="8" x2="6.01" y2="8"></line>
                    <line x1="10" y1="8" x2="10.01" y2="8"></line>
                    <line x1="14" y1="8" x2="14.01" y2="8"></line>
                    <line x1="18" y1="8" x2="18.01" y2="8"></line>
                    <line x1="7" y1="16" x2="17" y2="16"></line>
                </svg>
            </div>
            <div class="assets-stat-text">
                <div class="val"><?php echo $accessoryCount; ?> Peripherals</div>
                <div class="lbl">Docks, Keyboards & Mouse</div>
            </div>
        </div>
    </div>

    <!-- Main Assets Inventory Card -->
    <div class="assets-main-card">
        <div class="assets-card-header">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                Registered IT Inventory & Custody Receipts
            </h2>
        </div>

        <!-- Responsive Table -->
        <div class="table-responsive">
            <table class="custom-table" id="empAssetsTable">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item Name & Tag</th>
                        <th style="width: 28%;">Category & Specifications</th>
                        <th style="width: 18%;">Serial / Key</th>
                        <th style="width: 12%;">Assigned Date</th>
                        <th style="width: 12%;">Custody Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allocatedItems)): ?>
                        <tr>
                            <td colspan="5" style="padding: 48px 20px; text-align: center; color: var(--text-muted);">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #94a3b8; margin-bottom: 12px;">
                                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                    <div style="font-size: 14.5px; font-weight: 700; color: var(--navy-primary); margin-bottom: 4px;">
                                        No IT Assets Currently in Custody
                                    </div>
                                    <div style="font-size: 12.5px; color: var(--text-muted); max-width: 460px; line-height: 1.4;">
                                        You currently have no hardware units or workstation peripherals registered in your custody.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allocatedItems as $item): ?>
                            <tr class="emp-asset-row" data-type="<?php echo $item['type']; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="asset-item-icon-box">
                                            <?php if ($item['icon_type'] === 'laptop'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>
                                            <?php elseif ($item['icon_type'] === 'monitor'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                            <?php elseif ($item['icon_type'] === 'dock'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v6m4-6v6M8 8h8a2 2 0 0 1 2 2v2a6 6 0 0 1-12 0v-2a2 2 0 0 1 2-2zm4 10v4"></path></svg>
                                            <?php elseif ($item['icon_type'] === 'keyboard'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="6" y1="8" x2="6.01" y2="8"></line><line x1="10" y1="8" x2="10.01" y2="8"></line><line x1="14" y1="8" x2="14.01" y2="8"></line><line x1="18" y1="8" x2="18.01" y2="8"></line><line x1="7" y1="16" x2="17" y2="16"></line></svg>
                                            <?php elseif ($item['icon_type'] === 'mouse'): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="7"></rect><line x1="12" y1="6" x2="12" y2="10"></line></svg>
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3"></path></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--navy-primary); font-size: 13.5px;">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                            <div style="margin-top: 3px;">
                                                <span class="asset-tag-pill"><?php echo htmlspecialchars($item['tag']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--navy-primary);">
                                        <?php echo htmlspecialchars($item['category']); ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px; line-height: 1.35;">
                                        <?php echo htmlspecialchars($item['specs']); ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="asset-serial-pill">
                                        <?php echo htmlspecialchars($item['serial']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span style="font-size: 12.5px; font-weight: 500; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($item['date']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge <?php echo $item['badge']; ?>" style="font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;">
                                        <?php echo htmlspecialchars($item['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Empty Search State -->
            <div id="assetsEmptyState" class="assets-empty-state" style="display: none;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <div style="font-size: 14px; font-weight: 700; color: var(--navy-primary);">No Matching Assets Found</div>
                <div style="font-size: 12px; margin-top: 4px;">Try searching with another asset tag, model name or serial number.</div>
            </div>
        </div>
    </div>

</main>

<?php
// Layout Footer
include 'includes/footer.php';
?>
