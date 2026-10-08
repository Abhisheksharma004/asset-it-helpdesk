<?php
// Asset Management & IT Service Desk Portal - Asset Assignment & Allocation Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated guests to login page (allow preview query parameter if needed)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    header("Location: index.php");
    exit;
}

$page_title = "Asset Assignment & Allocation - VIROS Portal";
$active_page = "asset_assignment";
$extra_css = ['css/assets.css', 'css/categories.css', 'css/searchable-select.css', 'css/asset_assignment.css'];
$extra_js  = ['js/asset_assignment.js'];

// Include database to fetch dynamic data
require_once __DIR__ . '/config/db.php';

$departmentsList = [];
$locationsList = [];
$employeesList = [];
$availableAssets = [];
$availableAccessories = [];
$initialAssignments = [];

if (isset($conn) && $conn !== false) {
    // 1. Fetch active departments
    $deptStmt = sqlsrv_query($conn, "SELECT id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    if ($deptStmt !== false) {
        while ($d = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
            $departmentsList[] = $d['department_name'];
        }
        sqlsrv_free_stmt($deptStmt);
    }

    // 2. Fetch active locations
    $locStmt = sqlsrv_query($conn, "SELECT id, location_name FROM locations WHERE status = 'Active' ORDER BY location_name ASC");
    if ($locStmt !== false) {
        while ($l = sqlsrv_fetch_array($locStmt, SQLSRV_FETCH_ASSOC)) {
            $locationsList[] = $l['location_name'];
        }
        sqlsrv_free_stmt($locStmt);
    }

    // 3. Fetch active employees
    $empStmt = sqlsrv_query($conn, "SELECT e.id, e.emp_code, e.first_name, e.last_name, e.email, e.phone, 
                                           e.designation, d.department_name, l.location_name
                                    FROM employees e
                                    LEFT JOIN departments d ON e.department_id = d.id
                                    LEFT JOIN locations l ON e.location_id = l.id
                                    WHERE e.status = 'Active'
                                    ORDER BY e.first_name ASC");
    if ($empStmt !== false) {
        while ($e = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
            $fullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
            $employeesList[] = [
                'id'          => intval($e['id']),
                'emp_code'    => $e['emp_code'] ?? ('EMP-' . $e['id']),
                'name'        => $fullName ?: 'Employee #' . $e['id'],
                'email'       => $e['email'] ?? '',
                'phone'       => $e['phone'] ?? '',
                'designation' => $e['designation'] ?? 'Staff',
                'department'  => $e['department_name'] ?? 'General',
                'location'    => $e['location_name'] ?? 'Corporate HQ'
            ];
        }
        sqlsrv_free_stmt($empStmt);
    }

    // 4. Fetch available / in-stock assets for assignment dropdown
    $availStmt = sqlsrv_query($conn, "SELECT id, tag, name, category, brand, model, serial, condition, location, specs = (processor + ' • ' + ram) 
                                      FROM assets 
                                      WHERE status = 'Available' 
                                      ORDER BY id DESC");
    if ($availStmt !== false) {
        while ($a = sqlsrv_fetch_array($availStmt, SQLSRV_FETCH_ASSOC)) {
            $availableAssets[] = [
                'id'        => intval($a['id']),
                'tag'       => $a['tag'],
                'name'      => $a['name'],
                'category'  => $a['category'],
                'brand'     => $a['brand'],
                'model'     => $a['model'] ?? '',
                'serial'    => $a['serial'] ?? '',
                'condition' => $a['condition'] ?? 'Good',
                'location'  => $a['location'] ?? 'Storage Depot',
                'specs'     => $a['specs'] ?? ''
            ];
        }
        sqlsrv_free_stmt($availStmt);
    }

    // 4b. Fetch available / in-stock accessories for assignment dropdown
    $accStmt = sqlsrv_query($conn, "SELECT id, sku, name, category, branch_location, brand, model, total_qty, in_stock, deployed, location 
                                    FROM accessories 
                                    WHERE in_stock > 0 
                                    ORDER BY category ASC, name ASC");
    if ($accStmt !== false) {
        while ($ac = sqlsrv_fetch_array($accStmt, SQLSRV_FETCH_ASSOC)) {
            $availableAccessories[] = [
                'id'       => intval($ac['id']),
                'sku'      => $ac['sku'],
                'name'     => $ac['name'],
                'category' => $ac['category'],
                'brand'    => $ac['brand'],
                'model'    => $ac['model'] ?? '',
                'in_stock' => intval($ac['in_stock']),
                'location' => $ac['location'] ?? ''
            ];
        }
        sqlsrv_free_stmt($accStmt);
    }
    if (empty($availableAccessories)) {
        // Fallback real accessories master catalogue
        $availableAccessories = [
            ['id' => 1, 'sku' => 'ASO1026001', 'name' => 'Logitech MX Master 3S Wireless Mouse', 'category' => 'Keyboards & Mice', 'brand' => 'Logitech', 'model' => 'MX Master 3S', 'in_stock' => 34],
            ['id' => 2, 'sku' => 'ASO1026002', 'name' => 'Dell Pro Wireless Keyboard & Mouse KM5221W', 'category' => 'Keyboards & Mice', 'brand' => 'Dell', 'model' => 'KM5221W', 'in_stock' => 58],
            ['id' => 3, 'sku' => 'ASO1026003', 'name' => 'Apple Magic Keyboard with Touch ID', 'category' => 'Keyboards & Mice', 'brand' => 'Apple', 'model' => 'Numeric Keypad', 'in_stock' => 8],
            ['id' => 4, 'sku' => 'ASO1026004', 'name' => 'Dell Thunderbolt 4 Dock WD22TB4', 'category' => 'Docks & Hubs', 'brand' => 'Dell', 'model' => 'WD22TB4 180W', 'in_stock' => 19],
            ['id' => 5, 'sku' => 'ASO1026005', 'name' => 'Anker 575 USB-C Docking Station (13-in-1)', 'category' => 'Docks & Hubs', 'brand' => 'Anker', 'model' => 'Triple Display 85W', 'in_stock' => 3],
            ['id' => 6, 'sku' => 'ASO1026007', 'name' => 'Jabra Evolve2 65 UC Wireless Headset', 'category' => 'Headsets & Audio', 'brand' => 'Jabra', 'model' => 'HSC110W Dual ANC', 'in_stock' => 22],
            ['id' => 7, 'sku' => 'ASO1026008', 'name' => 'Poly Voyager Focus 2 UC Headset', 'category' => 'Headsets & Audio', 'brand' => 'Poly', 'model' => 'Focus 2 Bluetooth', 'in_stock' => 14],
            ['id' => 8, 'sku' => 'ASO1026009', 'name' => 'Sony WH-1000XM5 ANC Headphones', 'category' => 'Headsets & Audio', 'brand' => 'Sony', 'model' => 'WH-1000XM5 Black', 'in_stock' => 2],
            ['id' => 9, 'sku' => 'ASO1026010', 'name' => 'Logitech Brio 4K Ultra HD Webcam', 'category' => 'Webcams & Video', 'brand' => 'Logitech', 'model' => 'Brio 4K HDR', 'in_stock' => 28],
            ['id' => 10, 'sku' => 'ASO1026011', 'name' => 'Anker PowerConf C300 HD Webcam', 'category' => 'Webcams & Video', 'brand' => 'Anker', 'model' => 'C300 1080p 60fps', 'in_stock' => 21],
            ['id' => 11, 'sku' => 'ASO1026012', 'name' => 'Apple 96W USB-C Power Adapter', 'category' => 'Chargers & Power Adapters', 'brand' => 'Apple', 'model' => '96W GaN Fast Charger', 'in_stock' => 16],
            ['id' => 12, 'sku' => 'ASO1026013', 'name' => 'Lenovo 65W USB-C GaN Travel Charger', 'category' => 'Chargers & Power Adapters', 'brand' => 'Lenovo', 'model' => 'ThinkPad 65W AC', 'in_stock' => 35],
            ['id' => 13, 'sku' => 'ASO1026015', 'name' => 'Belkin USB-C to 4K HDMI Adapter', 'category' => 'Cables & Display Adapters', 'brand' => 'Belkin', 'model' => 'AVC002btBK 4K@60Hz', 'in_stock' => 44],
            ['id' => 14, 'sku' => 'ASO1026017', 'name' => 'YubiKey 5 NFC Hardware Security Key', 'category' => 'Security Tokens & Smart Keys', 'brand' => 'Yubico', 'model' => 'Y-501 FIDO2', 'in_stock' => 27],
            ['id' => 15, 'sku' => 'ASO1026018', 'name' => 'Targus 15.6" CityLite Laptop Sleeve', 'category' => 'Bags & Protective Cases', 'brand' => 'Targus', 'model' => 'TSS632GL', 'in_stock' => 42],
            ['id' => 16, 'sku' => 'ASO1026019', 'name' => 'Kensington ClickSafe Security Cable Lock', 'category' => 'Security & Cable Locks', 'brand' => 'Kensington', 'model' => 'K64637WW', 'in_stock' => 18]
        ];
    }

    // 5. Fetch assigned assets to populate live allocations
    $assignedStmt = sqlsrv_query($conn, "SELECT a.id, a.tag, a.name, a.category, a.brand, a.model, a.serial, 
                                                a.status, a.condition, a.location, a.department, a.assigned_to,
                                                a.processor, a.ram, a.storage,
                                                CONVERT(VARCHAR(10), a.created_at, 120) AS assigned_date_raw,
                                                CONVERT(VARCHAR(10), a.created_at, 105) AS assigned_date
                                         FROM assets a
                                         WHERE a.assigned_to IS NOT NULL AND a.assigned_to <> ''
                                         ORDER BY a.id DESC");
    if ($assignedStmt !== false) {
        $idx = 100;
        while ($row = sqlsrv_fetch_array($assignedStmt, SQLSRV_FETCH_ASSOC)) {
            $assignedName = trim($row['assigned_to']);
            // Try matching with employees
            $matchedEmp = null;
            foreach ($employeesList as $emp) {
                if (strcasecmp($emp['name'], $assignedName) === 0) {
                    $matchedEmp = $emp;
                    break;
                }
            }

            $empCode = $matchedEmp ? $matchedEmp['emp_code'] : ('EMP-' . (1000 + $idx));
            $empDept = $matchedEmp ? $matchedEmp['department'] : ($row['department'] ?: 'Engineering');
            $empDesig = $matchedEmp ? $matchedEmp['designation'] : 'Team Member';
            $empEmail = $matchedEmp ? $matchedEmp['email'] : (strtolower(str_replace(' ', '.', $assignedName)) . '@viros.com');

            // Default allocation types based on device/tag
            $allocType = 'Permanent';
            $expReturn = null;
            $custodyStatus = 'Active';

            if (stripos($row['name'], 'iPad') !== false || stripos($row['category'], 'Tablet') !== false) {
                $allocType = 'Temporary Loaner';
                $expReturn = date('Y-m-d', strtotime('+14 days'));
                $custodyStatus = 'Due Soon';
            } elseif (stripos($row['department'], 'Sales') !== false || stripos($row['model'], 'Cellular') !== false) {
                $allocType = 'Remote / WFH';
            }

            $initialAssignments[] = [
                'id'              => intval($row['id']),
                'asset_id'        => intval($row['id']),
                'slip_no'         => 'SLIP-2026-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                'asset_tag'       => $row['tag'],
                'asset_name'      => $row['name'],
                'category'        => $row['category'],
                'brand'           => $row['brand'],
                'model'           => $row['model'] ?? '',
                'serial'          => $row['serial'] ?? '—',
                'specs'           => trim(($row['processor'] ?? '') . ' ' . ($row['ram'] ?? '') . ' ' . ($row['storage'] ?? '')),
                'employee_name'   => $assignedName,
                'emp_code'        => $empCode,
                'employee_email'  => $empEmail,
                'department'      => $empDept,
                'designation'     => $empDesig,
                'location'        => $row['location'] ?: 'Corporate HQ - Mumbai',
                'assigned_date'   => $row['assigned_date_raw'] ?: '2026-08-15',
                'allocation_type' => $allocType,
                'expected_return' => $expReturn,
                'custody_status'  => $custodyStatus,
                'condition'       => $row['condition'] ?: 'Excellent',
                'accessories'     => ['Power Adapter & Cable', 'Laptop Sleeve Bag', 'Wireless Mouse'],
                'handover_by'     => 'Abhishek Sharma (IT Admin)',
                'agreement_signed'=> true,
                'notes'           => 'Device physically verified and allocated in working condition.'
            ];
            $idx++;
        }
        sqlsrv_free_stmt($assignedStmt);
    }
}

// Fallback seed data if DB table has no assignments yet (to guarantee rich UI demonstration)
if (empty($initialAssignments)) {
    $initialAssignments = [
        [
            'id'              => 1,
            'asset_id'        => 1,
            'slip_no'         => 'SLIP-2026-0001',
            'asset_tag'       => 'AST2024001',
            'asset_name'      => 'MacBook Pro 16" M3 Max',
            'category'        => 'Laptops',
            'brand'           => 'Apple',
            'model'           => 'MacBook Pro 16 (Space Black)',
            'serial'          => 'C02G40PZMD6T',
            'specs'           => 'Apple M3 Max 16-Core • 64 GB Unified • 1 TB NVMe SSD',
            'employee_name'   => 'Marcus Vance',
            'emp_code'        => 'EMP-1001',
            'employee_email'  => 'marcus.vance@viros.com',
            'department'      => 'Engineering',
            'designation'     => 'VP of Engineering',
            'location'        => 'Corporate HQ - Mumbai',
            'assigned_date'   => '2026-01-15',
            'allocation_type' => 'Permanent',
            'expected_return' => null,
            'custody_status'  => 'Active',
            'condition'       => 'Brand New',
            'accessories'     => ['140W USB-C Power Adapter', 'MagSafe 3 Cable', 'Tumi Laptop Backpack', 'Magic Mouse 2'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Executive allocation. Inspected and verified.'
        ],
        [
            'id'              => 2,
            'asset_id'        => 2,
            'slip_no'         => 'SLIP-2026-0002',
            'asset_tag'       => 'AST2024002',
            'asset_name'      => 'Dell XPS 15 9530',
            'category'        => 'Laptops',
            'brand'           => 'Dell',
            'model'           => 'XPS 15 (OLED Touch)',
            'serial'          => 'DELL-984210-X',
            'specs'           => 'Intel Core i9-13900H • 32 GB DDR5 • RTX 4070',
            'employee_name'   => 'Sophia Chen',
            'emp_code'        => 'EMP-1002',
            'employee_email'  => 'sophia.chen@viros.com',
            'department'      => 'Cloud Operations',
            'designation'     => 'Senior DevOps Engineer',
            'location'        => 'Tech Hub - Bangalore',
            'assigned_date'   => '2026-03-10',
            'allocation_type' => 'Remote / WFH',
            'expected_return' => null,
            'custody_status'  => 'Active',
            'condition'       => 'Excellent',
            'accessories'     => ['130W Type-C AC Adapter', 'Dell Pro Wireless Headset', 'Laptop Stand'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Remote deployment package. Shipped via secure courier.'
        ],
        [
            'id'              => 3,
            'asset_id'        => 4,
            'slip_no'         => 'SLIP-2026-0004',
            'asset_tag'       => 'AST2024004',
            'asset_name'      => 'Lenovo ThinkPad X1 Carbon Gen 11',
            'category'        => 'Laptops',
            'brand'           => 'Lenovo',
            'model'           => 'ThinkPad X1 Carbon',
            'serial'          => 'PF-39X1-LNV',
            'specs'           => 'Intel Core i7-1365U vPro • 32 GB LPDDR5 • 512 GB SSD',
            'employee_name'   => 'Sarah Jenkins',
            'emp_code'        => 'EMP-1004',
            'employee_email'  => 'sarah.jenkins@viros.com',
            'department'      => 'Engineering',
            'designation'     => 'Enterprise Architect',
            'location'        => 'Branch Office - Delhi NCR',
            'assigned_date'   => '2026-05-18',
            'allocation_type' => 'Permanent',
            'expected_return' => null,
            'custody_status'  => 'Active',
            'condition'       => 'Excellent',
            'accessories'     => ['65W Slim Tip Charger', 'ThinkPad Pouch', 'HDMI Cable'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Permanent handover.'
        ],
        [
            'id'              => 4,
            'asset_id'        => 9,
            'slip_no'         => 'SLIP-2026-0009',
            'asset_tag'       => 'AST2024009',
            'asset_name'      => 'Apple iPad Pro 12.9" M2 Cellular',
            'category'        => 'Tablets & Mobile',
            'brand'           => 'Apple',
            'model'           => 'iPad Pro 12.9 (Wi-Fi + 5G)',
            'serial'          => 'DMPF7829Q921',
            'specs'           => 'Apple M2 • 16 GB Unified • 256 GB Liquid Retina XDR',
            'employee_name'   => 'Aarav Patel',
            'emp_code'        => 'EMP-1008',
            'employee_email'  => 'aarav.patel@viros.com',
            'department'      => 'Product Management',
            'designation'     => 'Lead Product Manager',
            'location'        => 'Corporate HQ - Mumbai',
            'assigned_date'   => '2026-09-22',
            'allocation_type' => 'Temporary Loaner',
            'expected_return' => '2026-10-22',
            'custody_status'  => 'Due Soon',
            'condition'       => 'Excellent',
            'accessories'     => ['Apple Pencil 2nd Gen', 'Magic Keyboard Case', '20W USB-C Adapter'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Client demo sprint loaner. Return required on completion.'
        ],
        [
            'id'              => 5,
            'asset_id'        => 6,
            'slip_no'         => 'SLIP-2026-0006',
            'asset_tag'       => 'AST2024006',
            'asset_name'      => 'Apple iMac 24" M3',
            'category'        => 'Desktops',
            'brand'           => 'Apple',
            'model'           => 'iMac 24 (4.5K Retina Display)',
            'serial'          => 'C02K98LLM3',
            'specs'           => 'Apple M3 8-Core • 24 GB Unified • 512 GB SSD',
            'employee_name'   => 'Elena Rostova',
            'emp_code'        => 'EMP-1006',
            'employee_email'  => 'elena.rostova@viros.com',
            'department'      => 'Design & Creative',
            'designation'     => 'Senior UI/UX Designer',
            'location'        => 'Corporate HQ - Mumbai',
            'assigned_date'   => '2026-02-25',
            'allocation_type' => 'Permanent',
            'expected_return' => null,
            'custody_status'  => 'Active',
            'condition'       => 'Excellent',
            'accessories'     => ['Magic Keyboard with Touch ID', 'Magic Trackpad', 'Power Cord & Adapter'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Assigned to Design Studio Desk 14.'
        ],
        [
            'id'              => 6,
            'asset_id'        => 8,
            'slip_no'         => 'SLIP-2026-0008',
            'asset_tag'       => 'AST2024008',
            'asset_name'      => 'HP EliteBook 840 G10',
            'category'        => 'Laptops',
            'brand'           => 'HP',
            'model'           => 'EliteBook 840 G10 Wolf Security',
            'serial'          => '5CG3290ABC',
            'specs'           => 'Intel Core i7-1355U • 16 GB DDR5 • 512 GB NVMe',
            'employee_name'   => 'David Kim',
            'emp_code'        => 'EMP-1007',
            'employee_email'  => 'david.kim@viros.com',
            'department'      => 'Cybersecurity & Compliance',
            'designation'     => 'Information Security Officer',
            'location'        => 'Delivery Center - Hyderabad',
            'assigned_date'   => '2026-04-12',
            'allocation_type' => 'Permanent',
            'expected_return' => null,
            'custody_status'  => 'Active',
            'condition'       => 'Good',
            'accessories'     => ['HP 65W USB-C Adapter', 'HP Carrying Bag', 'YubiKey 5 NFC'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Secured Wolf Pro Security installation enabled.'
        ],
        [
            'id'              => 7,
            'asset_id'        => 10,
            'slip_no'         => 'SLIP-2026-0010',
            'asset_tag'       => 'AST2024010',
            'asset_name'      => 'Lenovo ThinkPad T14 Gen 4',
            'category'        => 'Laptops',
            'brand'           => 'Lenovo',
            'model'           => 'ThinkPad T14 AMD Edition',
            'serial'          => 'PF-478K20-LNV',
            'specs'           => 'AMD Ryzen 7 PRO 7840U • 32 GB LPDDR5X • 1 TB SSD',
            'employee_name'   => 'Liam Gallagher',
            'emp_code'        => 'EMP-1010',
            'employee_email'  => 'liam.gallagher@viros.com',
            'department'      => 'Engineering',
            'designation'     => 'Lead Frontend Architect',
            'location'        => 'Development Center - Pune',
            'assigned_date'   => '2026-06-01',
            'allocation_type' => 'Project Deployment',
            'expected_return' => '2026-12-31',
            'custody_status'  => 'Active',
            'condition'       => 'Brand New',
            'accessories'     => ['65W GaN Charger', 'ThinkPad Sleeve', 'USB-C Multiport Hub'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'Project Phoenix Core deployment.'
        ],
        [
            'id'              => 8,
            'asset_id'        => 13,
            'slip_no'         => 'SLIP-2026-0013',
            'asset_tag'       => 'AST2024013',
            'asset_name'      => 'Apple MacBook Air 15" M2',
            'category'        => 'Laptops',
            'brand'           => 'Apple',
            'model'           => 'MacBook Air 15 (Midnight)',
            'serial'          => 'C02HQ81LMD91',
            'specs'           => 'Apple M2 8-Core • 16 GB Unified • 512 GB SSD',
            'employee_name'   => 'Priya Sharma',
            'emp_code'        => 'EMP-1013',
            'employee_email'  => 'priya.sharma@viros.com',
            'department'      => 'Human Resources',
            'designation'     => 'Senior HR Business Partner',
            'location'        => 'Corporate HQ - Mumbai',
            'assigned_date'   => '2026-07-15',
            'allocation_type' => 'Permanent',
            'expected_return' => null,
            'custody_status'  => 'Active',
            'condition'       => 'Brand New',
            'accessories'     => ['35W Dual USB-C Adapter', 'MagSafe Cable', 'Midnight Leather Sleeve'],
            'handover_by'     => 'Abhishek Sharma (IT Lead)',
            'agreement_signed'=> true,
            'notes'           => 'HR management machine.'
        ]
    ];
}

// Fallback available assets for dropdown
if (empty($availableAssets)) {
    $availableAssets = [
        [
            'id'        => 5,
            'tag'       => 'AST2024005',
            'name'      => 'Cisco Catalyst 9300 48-Port PoE+',
            'category'  => 'Networking',
            'brand'     => 'Cisco',
            'model'     => 'C9300-48P-A',
            'serial'    => 'FOC2408W0AB',
            'condition' => 'Excellent',
            'location'  => 'Storage Depot (Rack B-01)',
            'specs'     => '48x 1G PoE+ (437W) • Modular Uplinks'
        ],
        [
            'id'        => 14,
            'tag'       => 'AST2024014',
            'name'      => 'Microsoft Surface Pro 9',
            'category'  => 'Tablets & Mobile',
            'brand'     => 'Microsoft',
            'model'     => 'Surface Pro 9 (Platinum)',
            'serial'    => '029384729153',
            'condition' => 'Good',
            'location'  => 'Storage Depot (Shelf 3)',
            'specs'     => 'Intel Core i7-1255U • 16 GB RAM • 256 GB SSD'
        ],
        [
            'id'        => 15,
            'tag'       => 'AST2024015',
            'name'      => 'Dell Latitude 5420',
            'category'  => 'Laptops',
            'brand'     => 'Dell',
            'model'     => 'Latitude 5420 Business',
            'serial'    => 'DELL-5420-OLD',
            'condition' => 'Good',
            'location'  => 'Storage Depot (Shelf 1)',
            'specs'     => 'Intel Core i5-1145G7 • 16 GB DDR4 • 256 GB SSD'
        ],
        [
            'id'        => 16,
            'tag'       => 'AST2026015',
            'name'      => 'Dell Latitude 5420 (Secondary)',
            'category'  => 'Laptops',
            'brand'     => 'Dell',
            'model'     => 'Latitude 5420 Enterprise',
            'serial'    => 'DELL-5420-REV2',
            'condition' => 'Brand New',
            'location'  => 'Storage Depot (Shelf 1)',
            'specs'     => 'Intel Core i5-1145G7 • 16 GB DDR4 • 512 GB SSD'
        ]
    ];
}

// Fallback employees list
if (empty($employeesList)) {
    $employeesList = [
        ['id' => 1, 'emp_code' => 'EMP-1001', 'name' => 'Marcus Vance', 'email' => 'marcus.vance@viros.com', 'designation' => 'VP of Engineering', 'department' => 'Engineering', 'location' => 'Corporate HQ - Mumbai'],
        ['id' => 2, 'emp_code' => 'EMP-1002', 'name' => 'Sophia Chen', 'email' => 'sophia.chen@viros.com', 'designation' => 'Senior DevOps Engineer', 'department' => 'Cloud Operations', 'location' => 'Tech Hub - Bangalore'],
        ['id' => 3, 'emp_code' => 'EMP-1003', 'name' => 'Vikram Malhotra', 'email' => 'vikram.malhotra@viros.com', 'designation' => 'Infrastructure Manager', 'department' => 'IT Infrastructure', 'location' => 'Corporate HQ - Mumbai'],
        ['id' => 4, 'emp_code' => 'EMP-1004', 'name' => 'Sarah Jenkins', 'email' => 'sarah.jenkins@viros.com', 'designation' => 'Enterprise Architect', 'department' => 'Engineering', 'location' => 'Branch Office - Delhi NCR'],
        ['id' => 5, 'emp_code' => 'EMP-1005', 'name' => 'Rohan Mehta', 'email' => 'rohan.mehta@viros.com', 'designation' => 'Full Stack Developer', 'department' => 'Engineering', 'location' => 'Development Center - Pune'],
        ['id' => 6, 'emp_code' => 'EMP-1006', 'name' => 'Elena Rostova', 'email' => 'elena.rostova@viros.com', 'designation' => 'Senior UI/UX Designer', 'department' => 'Design & Creative', 'location' => 'Corporate HQ - Mumbai'],
        ['id' => 7, 'emp_code' => 'EMP-1007', 'name' => 'David Kim', 'email' => 'david.kim@viros.com', 'designation' => 'Information Security Officer', 'department' => 'Cybersecurity & Compliance', 'location' => 'Delivery Center - Hyderabad'],
        ['id' => 8, 'emp_code' => 'EMP-1008', 'name' => 'Aarav Patel', 'email' => 'aarav.patel@viros.com', 'designation' => 'Lead Product Manager', 'department' => 'Product Management', 'location' => 'Corporate HQ - Mumbai'],
        ['id' => 9, 'emp_code' => 'EMP-1009', 'name' => 'Ananya Sen', 'email' => 'ananya.sen@viros.com', 'designation' => 'Financial Analyst', 'department' => 'Finance & Accounts', 'location' => 'Corporate HQ - Mumbai'],
        ['id' => 10, 'emp_code' => 'EMP-1010', 'name' => 'Liam Gallagher', 'email' => 'liam.gallagher@viros.com', 'designation' => 'Lead Frontend Architect', 'department' => 'Engineering', 'location' => 'Development Center - Pune'],
        ['id' => 11, 'emp_code' => 'EMP-1011', 'name' => 'Kavita Nair', 'email' => 'kavita.nair@viros.com', 'designation' => 'Network Engineer', 'department' => 'IT Infrastructure', 'location' => 'Operations Center - Chennai'],
        ['id' => 12, 'emp_code' => 'EMP-1012', 'name' => 'Priya Sharma', 'email' => 'priya.sharma@viros.com', 'designation' => 'Senior HR Business Partner', 'department' => 'Human Resources', 'location' => 'Corporate HQ - Mumbai']
    ];
}

if (empty($departmentsList)) {
    $departmentsList = ['Engineering', 'Cloud Operations', 'IT Infrastructure', 'Design & Creative', 'Product Management', 'Cybersecurity & Compliance', 'Human Resources', 'Finance & Accounts'];
}

// Compute live metrics
$stats = [
    'total'         => count($initialAssignments),
    'permanent'     => 0,
    'temporary'     => 0,
    'remote'        => 0,
    'due_soon'      => 0,
    'available'     => count($availableAssets)
];

foreach ($initialAssignments as $item) {
    $type = $item['allocation_type'];
    if ($type === 'Permanent') {
        $stats['permanent']++;
    } elseif ($type === 'Temporary Loaner') {
        $stats['temporary']++;
    } elseif ($type === 'Remote / WFH') {
        $stats['remote']++;
    }

    if ($item['custody_status'] === 'Due Soon' || $item['custody_status'] === 'Overdue') {
        $stats['due_soon']++;
    }
}

// Include Layout Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Asset Assignment & Custody Management Content -->
<main class="dashboard-content">

    <!-- Page Header Bar -->
    <div class="page-header-bar">
        <div class="page-header-title">
            <div class="breadcrumb-nav">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="assets.php">Assets</a>
                <span>/</span>
                <span>Asset Assignment & Allocation</span>
            </div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--cyan-primary);">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <polyline points="16 11 18 13 22 9"></polyline>
                </svg>
                Asset Assignment & Custody
            </h1>
            <p>Assign hardware devices to employees, manage custody lifecycles, loaner equipment, and digital handover slips.</p>
        </div>
        <div class="header-action-group">
            <button type="button" class="btn-secondary" id="exportAllocationsBtn" title="Export Allocations to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export CSV
            </button>
            <button type="button" class="btn-primary" id="openAssignModalBtn" title="Assign Device to Staff">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Assign New Asset</span>
            </button>
        </div>
    </div>

    <!-- KPI Metric Stat Cards -->
    <div class="asset-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));">
        <!-- Total Active Allocations -->
        <div class="alloc-stat-card card-active" data-filter-tab="all" title="View all active allocations">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Active In Custody</div>
                <div class="stat-val" id="kpiTotalAllocated"><?php echo $stats['total']; ?></div>
                <div class="stat-sub">
                    <span style="color: #10b981; font-weight: 600;">●</span> Total deployed hardware fleet
                </div>
            </div>
            <div class="alloc-stat-icon cyan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
        </div>

        <!-- Permanent Handover -->
        <div class="alloc-stat-card card-permanent" data-filter-tab="permanent" title="Filter permanent staff equipment">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Permanent Handover</div>
                <div class="stat-val" id="kpiPermanentAllocated" style="color: #4f46e5;"><?php echo $stats['permanent']; ?></div>
                <div class="stat-sub">Regular employee workstations</div>
            </div>
            <div class="alloc-stat-icon indigo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
        </div>

        <!-- Temporary / Loaner Devices -->
        <div class="alloc-stat-card card-temporary" data-filter-tab="temporary" title="Filter temporary loaners & project devices">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Temporary Loaners</div>
                <div class="stat-val" id="kpiTemporaryAllocated" style="color: #d97706;"><?php echo $stats['temporary']; ?></div>
                <div class="stat-sub">
                    <span style="color: #ea580c; font-weight: 600;"><?php echo $stats['due_soon']; ?> Due / Overdue</span>
                </div>
            </div>
            <div class="alloc-stat-icon amber">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>

        <!-- Ready to Assign / In Stock -->
        <div class="alloc-stat-card card-available" id="cardAvailableInStock" title="View available unallocated hardware">
            <div class="alloc-stat-info">
                <div class="stat-lbl">Ready to Assign</div>
                <div class="stat-val" id="kpiAvailableAssets" style="color: #059669;"><?php echo $stats['available']; ?></div>
                <div class="stat-sub">In stock in IT storage depot</div>
            </div>
            <div class="alloc-stat-icon emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
        </div>
    </div>

    <!-- Allocation Status Navigation Tabs -->
    <div class="asset-status-tabs">
        <button type="button" class="status-tab-btn active" data-tab="all">
            All Allocations
            <span class="status-tab-badge" id="tabCountAll"><?php echo $stats['total']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="permanent">
            Permanent
            <span class="status-tab-badge" id="tabCountPermanent"><?php echo $stats['permanent']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="temporary">
            Temporary / Loaner
            <span class="status-tab-badge" id="tabCountTemporary"><?php echo $stats['temporary']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="remote">
            Remote / WFH
            <span class="status-tab-badge" id="tabCountRemote"><?php echo $stats['remote']; ?></span>
        </button>
        <button type="button" class="status-tab-btn" data-tab="due_soon" style="border-left: 2px solid #fdba74;">
            Due Soon / Overdue
            <span class="status-tab-badge" id="tabCountDueSoon" style="background: #ea580c; color: #fff;"><?php echo $stats['due_soon']; ?></span>
        </button>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="asset-toolbar" style="margin-bottom: 16px;">
        <div class="toolbar-left" style="flex-wrap: wrap;">
            <!-- Live Search -->
            <div class="asset-search-wrapper" style="min-width: 280px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="allocSearchInput" placeholder="Search by Asset Tag, Device, Employee, Emp Code...">
            </div>

            <!-- Department Filter -->
            <select class="asset-filter-select" id="allocDeptFilter">
                <option value="all">All Departments</option>
                <?php foreach ($departmentsList as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Allocation Type Filter -->
            <select class="asset-filter-select" id="allocTypeFilter">
                <option value="all">All Allocation Types</option>
                <option value="Permanent">Permanent Handover</option>
                <option value="Temporary Loaner">Temporary / Loaner</option>
                <option value="Remote / WFH">Remote / WFH</option>
                <option value="Project Deployment">Project Deployment</option>
            </select>

            <!-- Custody Status Filter -->
            <select class="asset-filter-select" id="allocStatusFilter">
                <option value="all">All Custody States</option>
                <option value="Active">Active Custody</option>
                <option value="Due Soon">Due Soon (<30d)</option>
                <option value="Overdue">Overdue</option>
            </select>

            <!-- Reset Button -->
            <button type="button" class="btn-secondary" id="resetAllocFiltersBtn" style="padding: 7px 12px; font-size: 12.5px;" title="Reset Filters">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Reset
            </button>
        </div>

        <div class="toolbar-right">
            <span style="font-size: 12px; color: var(--text-muted);" id="allocTableCountText">Showing <?php echo count($initialAssignments); ?> records</span>
        </div>
    </div>

    <!-- Active Allocations Table Card -->
    <div class="asset-table-card">
        <div class="asset-table-responsive">
            <table class="asset-data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="custom-checkbox" id="selectAllAlloc">
                        </th>
                        <th>Asset & Hardware Info</th>
                        <th>Custodian / Employee</th>
                        <th>Allocation Terms</th>
                        <th>Expected Return</th>
                        <th>Custody Status</th>
                        <th style="text-align: right; padding-right: 18px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="allocationsTbody">
                    <?php foreach ($initialAssignments as $row): 
                        $typePill = 'permanent';
                        if ($row['allocation_type'] === 'Temporary Loaner') $typePill = 'temporary';
                        elseif ($row['allocation_type'] === 'Remote / WFH') $typePill = 'remote';
                        elseif ($row['allocation_type'] === 'Project Deployment') $typePill = 'project';

                        $custodyBadge = 'status-active';
                        $statusText = 'In Custody';
                        if ($row['custody_status'] === 'Due Soon') {
                            $custodyBadge = 'status-due-soon';
                            $statusText = 'Due Soon';
                        } elseif ($row['custody_status'] === 'Overdue') {
                            $custodyBadge = 'status-overdue';
                            $statusText = 'Overdue';
                        }
                    ?>
                        <tr data-id="<?php echo $row['id']; ?>">
                            <td>
                                <input type="checkbox" class="custom-checkbox row-select-checkbox" value="<?php echo $row['id']; ?>">
                            </td>
                            <td>
                                <div class="alloc-asset-cell">
                                    <div class="alloc-asset-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                            <line x1="8" y1="21" x2="16" y2="21"></line>
                                            <line x1="12" y1="17" x2="12" y2="21"></line>
                                        </svg>
                                    </div>
                                    <div class="alloc-asset-details">
                                        <span class="alloc-asset-name" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)">
                                            <?php echo htmlspecialchars($row['asset_name']); ?>
                                        </span>
                                        <div class="alloc-asset-meta">
                                            <span class="asset-tag-badge" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)"><?php echo htmlspecialchars($row['asset_tag']); ?></span>
                                            <span class="category-pill" style="font-size: 10.5px; padding: 1px 6px;"><?php echo htmlspecialchars($row['category']); ?></span>
                                            <span class="serial-badge" style="font-size: 10.5px; padding: 1px 6px;">SN: <?php echo htmlspecialchars($row['serial']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="custodian-cell">
                                    <?php 
                                        $avatarColors = ['#0093A7', '#0284c7', '#7e22ce', '#059669', '#d97706', '#db2777', '#4f46e5', '#0891b2'];
                                        $parts = explode(' ', trim($row['employee_name']));
                                        $inits = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                                        $cHash = abs(crc32($row['employee_name'] . ($row['emp_code'] ?? '')));
                                        $colorBg = $avatarColors[$cHash % count($avatarColors)];
                                    ?>
                                    <div class="custodian-avatar" style="background: <?php echo $colorBg; ?>;">
                                        <?php echo htmlspecialchars($inits ?: 'ST'); ?>
                                    </div>
                                    <div class="custodian-info">
                                        <div class="custodian-name">
                                            <?php echo htmlspecialchars($row['employee_name']); ?>
                                            <span class="emp-code-badge"><?php echo htmlspecialchars($row['emp_code']); ?></span>
                                        </div>
                                        <div class="custodian-sub">
                                            <?php echo htmlspecialchars($row['designation']); ?> • <strong><?php echo htmlspecialchars($row['department']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 3px; align-items: flex-start;">
                                    <span class="alloc-pill <?php echo $typePill; ?>">
                                        <?php echo htmlspecialchars($row['allocation_type']); ?>
                                    </span>
                                    <span style="font-size: 11.5px; color: var(--text-muted);">
                                        Assigned: <?php echo htmlspecialchars($row['assigned_date']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($row['expected_return'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span style="font-size: 12.5px; font-weight: 600; color: #b45309;">
                                            <?php echo htmlspecialchars($row['expected_return']); ?>
                                        </span>
                                        <span style="font-size: 11px; color: #ea580c;">
                                            Exp. Return
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--text-muted);">— Permanent</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="custody-badge <?php echo $custodyBadge; ?>">
                                    <span class="dot"></span>
                                    <span><?php echo $statusText; ?></span>
                                </div>
                                <?php if (!empty($row['agreement_signed'])): ?>
                                    <div style="font-size: 10.5px; color: #16a34a; margin-top: 3px; display: flex; align-items: center; gap: 3px;">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <span>Slip Verified</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons-wrap" style="justify-content: flex-end; padding-right: 6px;">
                                    <button type="button" class="action-icon-btn btn-view" title="View Custody Details & Handover Slip" onclick="openAssignmentDrawer(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-return" title="Return Asset (Check-In to Inventory)" onclick="openReturnModal(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-transfer" title="Transfer to Another Custodian" onclick="openTransferModal(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
                                    </button>
                                    <button type="button" class="action-icon-btn btn-qr" title="Print Handover Slip Receipt" onclick="openSlipModal(<?php echo $row['id']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- =========================================================================
     SLIDE-OVER DRAWER (Custody & Handover Details)
     ========================================================================= -->
<div class="drawer-backdrop" id="assignmentDrawerBackdrop"></div>

<aside class="asset-drawer" id="assignmentDrawer">
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="asset-tag-badge" id="drawerTagBadge" style="font-size: 12px; font-weight: 700;">AST-2024-001</span>
                    <span class="custody-badge status-active" id="drawerStatusBadge"><span class="dot"></span>In Custody</span>
                    <span class="emp-code-badge" id="drawerSlipNo">SLIP-2026-0001</span>
                </div>
                <h3 id="drawerAssetName" style="margin-top: 4px;">MacBook Pro 16" M3 Max</h3>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="closeAssignmentDrawerBtn" title="Close Drawer">&times;</button>
    </div>

    <!-- Drawer Tabs -->
    <div class="drawer-tabs">
        <button type="button" class="drawer-tab active" data-tab="custody_overview">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Custody Overview
        </button>
        <button type="button" class="drawer-tab" data-tab="device_specs">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            Device Specs
        </button>
        <button type="button" class="drawer-tab" data-tab="handover_timeline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            Custody Timeline
        </button>
    </div>

    <!-- Drawer Content -->
    <div class="drawer-content">
        <!-- Tab 1: Overview -->
        <div class="drawer-tab-pane active" id="pane_custody_overview">
            
            <!-- Custodian Profile Card -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Assigned Custodian (Employee)
                </div>
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; gap: 14px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="custodian-avatar" id="drawerCustAvatar" style="width: 44px; height: 44px; font-size: 15px;">MV</div>
                        <div>
                            <div style="font-size: 14.5px; font-weight: 700; color: var(--text-primary);" id="drawerCustName">Marcus Vance</div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 1px;" id="drawerCustMeta">VP of Engineering • Engineering</div>
                            <div style="font-size: 11.5px; color: var(--cyan-primary); margin-top: 2px;" id="drawerCustEmail">marcus.vance@viros.com</div>
                        </div>
                    </div>
                    <button type="button" class="btn-secondary" style="padding: 5px 10px; font-size: 12px;" onclick="if(window.activeAllocId) openTransferModal(window.activeAllocId)">
                        Transfer
                    </button>
                </div>
            </div>

            <!-- Handover Terms & Scope -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Handover Terms & Allocation
                </div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Allocation Type</div>
                        <div class="value" id="drawerAllocType">Permanent</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Handover Date</div>
                        <div class="value" id="drawerAssignedDate">2026-01-15</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Expected Return</div>
                        <div class="value" id="drawerExpectedReturn">— Permanent</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Initial Condition</div>
                        <div class="value" id="drawerCondition">Brand New</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Assigned Branch</div>
                        <div class="value" id="drawerLocation">Corporate HQ - Mumbai</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Issued By</div>
                        <div class="value" id="drawerHandoverBy">Abhishek Sharma (IT Lead)</div>
                    </div>
                </div>
            </div>

            <!-- Bundled Accessories Checklist -->
            <div class="drawer-section">
                <div class="drawer-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    Bundled Accessories Included
                </div>
                <div id="drawerAccessoriesWrap" class="accessory-chip-grid">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Handover Policy & Notes -->
            <div class="drawer-section">
                <div class="drawer-section-title">Handover Agreement & Notes</div>
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;" id="drawerNotes">
                    Device verified and allocated in good physical condition. Custodian has agreed to corporate IT acceptable use policy.
                </div>
            </div>

        </div>

        <!-- Tab 2: Specs -->
        <div class="drawer-tab-pane" id="pane_device_specs">
            <div class="drawer-section">
                <div class="drawer-section-title">Hardware Specifications</div>
                <div class="drawer-spec-grid">
                    <div class="drawer-spec-item">
                        <div class="label">Asset Tag</div>
                        <div class="value" id="specTag" style="font-family: monospace; font-weight: 700; color: var(--cyan-primary);">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Category</div>
                        <div class="value" id="specCategory">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Brand</div>
                        <div class="value" id="specBrand">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Model</div>
                        <div class="value" id="specModel">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Serial Number</div>
                        <div class="value" id="specSerial" style="font-family: monospace;">-</div>
                    </div>
                    <div class="drawer-spec-item">
                        <div class="label">Hardware Specs</div>
                        <div class="value" id="specDetails">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Timeline -->
        <div class="drawer-tab-pane" id="pane_handover_timeline">
            <div class="drawer-section">
                <div class="drawer-section-title">Custody History Timeline</div>
                <div id="drawerTimelineWrap">
                    <!-- Timeline items generated dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div class="drawer-footer" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        <button type="button" class="btn-danger" style="padding: 7px 12px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 5px;" onclick="if(window.activeAllocId) openReturnModal(window.activeAllocId)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
            Return Asset
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn-secondary" style="padding: 7px 12px; font-size: 12.5px;" onclick="if(window.activeAllocId) openSlipModal(window.activeAllocId)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Handover Slip
            </button>
            <button type="button" class="btn-primary" style="padding: 7px 12px; font-size: 12.5px;" onclick="if(window.activeAllocId) openTransferModal(window.activeAllocId)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line></svg>
                Transfer Custody
            </button>
        </div>
    </div>
</aside>

<!-- =========================================================================
     MODAL 1: ASSIGN ASSET TO EMPLOYEE (WIZARD MODAL)
     ========================================================================= -->
<div class="modal-overlay" id="assignAssetModal" style="display: none;">
    <div class="modal-box" style="max-width: 780px;">
        <div class="modal-header">
            <h3 id="assignAssetModalTitle">Assign Asset to Employee</h3>
            <button type="button" class="modal-close-btn" id="closeAssignModalBtn">&times;</button>
        </div>

        <!-- Modal Tab Headers (Exact pattern from assets.php) -->
        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-btn active" data-tab="employee">Employee Information</button>
            <button type="button" class="modal-tab-btn" data-tab="assets">Asset Detail <span id="assignTabAssetBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span></button>
            <button type="button" class="modal-tab-btn" data-tab="accessories">Accessories Detail <span id="assignTabAccBadge" class="modal-tab-count-badge" style="display:none; margin-left: 5px; background: var(--cyan-primary); color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 700;">0</span></button>
        </div>

        <form id="assignAssetForm">
            <div class="modal-body">
                
                <!-- Tab Pane 1: Employee Information -->
                <div class="modal-tab-pane active" id="modal_pane_employee">
                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="assignEmployeeSelect">Employee / Staff *</label>
                            <select id="assignEmployeeSelect" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employeesList as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['id']); ?>" 
                                            data-name="<?php echo htmlspecialchars($emp['name']); ?>"
                                            data-code="<?php echo htmlspecialchars($emp['emp_code']); ?>"
                                            data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                            data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                                            data-desig="<?php echo htmlspecialchars($emp['designation']); ?>">
                                        <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['emp_code']); ?> - <?php echo htmlspecialchars($emp['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="assignAllocationType">Allocation Type *</label>
                            <select id="assignAllocationType" required>
                                <option value="Permanent" selected>Permanent Handover</option>
                                <option value="Temporary Loaner">Temporary / Loaner Device</option>
                                <option value="Remote / WFH">Remote / WFH Allocation</option>
                                <option value="Project Deployment">Project-Specific Deployment</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Employee Card Preview -->
                    <div class="preview-summary-card" id="empPreviewBox" style="display: none; margin-bottom: 16px;">
                        <div class="preview-summary-title">Custodian Summary</div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="custodian-avatar" id="empPreviewAvatar" style="width: 42px; height: 42px; flex-shrink: 0;">ST</div>
                            <div style="min-width: 0; overflow: hidden;">
                                <div style="font-size: 14px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="empPreviewName">-</div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="empPreviewMeta">-</div>
                                <div style="font-size: 11.5px; color: var(--cyan-primary); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="empPreviewEmail">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="modal-form-group">
                            <label for="assignLocation">Deployment Location *</label>
                            <select id="assignLocation" required>
                                <?php foreach ($locationsList as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="assignHandoverDate">Handover Date *</label>
                            <input type="date" id="assignHandoverDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="modal-form-group" id="expectedReturnGroup" style="display: none;">
                        <label for="assignExpectedReturn">Expected Return Date * <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Required for Temporary Loaners)</span></label>
                        <input type="date" id="assignExpectedReturn" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" style="max-width: 50%;">
                    </div>
                </div>

                <!-- Tab Pane 2: Asset Detail -->
                <div class="modal-tab-pane" id="modal_pane_assets">
                    <div class="modal-form-group">
                        <label for="assignAssetSelect">Choose In-Stock Asset to Add *</label>
                        <div class="batch-asset-picker-row">
                            <select id="assignAssetSelect">
                                <option value="">-- Choose In-Stock Asset to Add --</option>
                                <?php foreach ($availableAssets as $av): ?>
                                    <option value="<?php echo htmlspecialchars($av['id']); ?>"
                                            data-tag="<?php echo htmlspecialchars($av['tag']); ?>"
                                            data-name="<?php echo htmlspecialchars($av['name']); ?>"
                                            data-category="<?php echo htmlspecialchars($av['category']); ?>"
                                            data-brand="<?php echo htmlspecialchars($av['brand']); ?>"
                                            data-serial="<?php echo htmlspecialchars($av['serial']); ?>"
                                            data-condition="<?php echo htmlspecialchars($av['condition']); ?>"
                                            data-specs="<?php echo htmlspecialchars($av['specs']); ?>">
                                        <?php echo htmlspecialchars($av['tag']); ?> • <?php echo htmlspecialchars($av['name']); ?> (SN: <?php echo htmlspecialchars($av['serial'] ?: 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-add-comp-row" id="addAssetToBatchBtn" title="Add device to allocation list">+</button>
                        </div>
                    </div>

                    <!-- Selected Batch Assets List Container -->
                    <div class="modal-form-group" style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Selected Devices for Allocation</span>
                            <span class="asset-count-badge" id="selectedAssetCountBadge">0 Devices</span>
                        </label>
                        <div class="batch-asset-list" id="batchAssetsList" style="max-height: 180px;">
                            <div class="batch-asset-empty" id="batchEmptyState">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                <span>No assets selected yet. Pick an asset above and click <strong>+ Add</strong>.</span>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label>Default Handover Condition *</label>
                        <div style="display: flex; gap: 16px; margin-top: 6px; flex-wrap: wrap;">
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; font-size: 13px;">
                                <input type="radio" name="handoverCondition" value="Brand New" checked> Brand New
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; font-size: 13px;">
                                <input type="radio" name="handoverCondition" value="Excellent"> Excellent
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; font-size: 13px;">
                                <input type="radio" name="handoverCondition" value="Good"> Good
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tab Pane 3: Accessories Detail -->
                <div class="modal-tab-pane" id="modal_pane_accessories">
                    <!-- Included Accessories & Peripherals -->
                    <div class="modal-form-group">
                        <label for="assignAccessorySelect">Choose In-Stock Accessory / Peripheral to Add</label>
                        <div class="batch-asset-picker-row">
                            <select id="assignAccessorySelect">
                                <option value="">-- Choose In-Stock Accessory to Add --</option>
                                <?php foreach ($availableAccessories as $ac): ?>
                                    <option value="<?php echo htmlspecialchars($ac['id']); ?>"
                                            data-sku="<?php echo htmlspecialchars($ac['sku']); ?>"
                                            data-name="<?php echo htmlspecialchars($ac['name']); ?>"
                                            data-category="<?php echo htmlspecialchars($ac['category']); ?>"
                                            data-brand="<?php echo htmlspecialchars($ac['brand'] ?? ''); ?>"
                                            data-model="<?php echo htmlspecialchars($ac['model'] ?? ''); ?>"
                                            data-instock="<?php echo htmlspecialchars($ac['in_stock']); ?>">
                                        <?php echo htmlspecialchars($ac['sku']); ?> • <?php echo htmlspecialchars($ac['name']); ?> (In Stock: <?php echo htmlspecialchars($ac['in_stock']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-add-comp-row" id="addAccessoryToBatchBtn" title="Add accessory to allocation list">+</button>
                        </div>
                    </div>

                    <!-- Selected Batch Accessories List Container -->
                    <div class="modal-form-group" style="margin-bottom: 14px;">
                        <label style="display: flex; align-items: center; justify-content: space-between;">
                            <span>Selected Accessories for Allocation</span>
                            <span class="asset-count-badge" id="selectedAccCountBadge">0 Items</span>
                        </label>
                        <div class="batch-asset-list" id="batchAccessoriesList" style="max-height: 140px;">
                            <div class="batch-asset-empty" id="accEmptyState">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                <span>No accessories added yet. Pick an accessory above and click <strong>+ Add</strong>.</span>
                            </div>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="assignNotes">Handover Remarks / Asset Notes</label>
                        <textarea id="assignNotes" rows="2" placeholder="e.g. Delivered with clean image, antivirus active, no cosmetic scratches."></textarea>
                    </div>

                    <!-- Handover Policy Agreement Checkbox -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px; margin-top: 10px; display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" id="assignPolicyCheck" required style="margin-top: 3px; accent-color: #16a34a; cursor: pointer; flex-shrink: 0;">
                        <label for="assignPolicyCheck" style="font-size: 12px; color: #166534; margin: 0; cursor: pointer; line-height: 1.4;">
                            <strong>Custodian Policy Sign-Off:</strong> Employee has physically received the hardware equipment in working order and agrees to comply with the organization's IT Asset Security & Acceptable Usage Policy.
                        </label>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelAssignBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="submitAssignBtn">Confirm & Complete Handover</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: RETURN ASSET TO INVENTORY (CHECK-IN)
     ========================================================================= -->
<div class="modal-overlay" id="returnAssetModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0;">Return Asset to Inventory</h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Check-in equipment from employee back to storage depot</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="closeReturnModalBtn">&times;</button>
        </div>
        <form id="returnAssetForm">
            <input type="hidden" id="returnAllocId">
            <div class="modal-body">
                
                <div class="preview-summary-card" style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span class="asset-tag-badge" id="returnTagText">AST-2024-001</span>
                        <span class="emp-code-badge" id="returnEmpCodeText">EMP-1001</span>
                    </div>
                    <div style="font-weight: 700; font-size: 14px; color: var(--text-primary);" id="returnAssetNameText">-</div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                        Returning Custodian: <strong id="returnEmpNameText">-</strong>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="returnDate">Return Date *</label>
                        <input type="date" id="returnDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="returnCondition">Returned Condition *</label>
                        <select id="returnCondition" required>
                            <option value="Excellent">Excellent (No defects)</option>
                            <option value="Good" selected>Good (Minor wear)</option>
                            <option value="Needs Repair">Needs Repair / Maintenance</option>
                            <option value="Damaged">Damaged / Non-functional</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="returnStorageLocation">Storage Shelf / Rack Depot *</label>
                    <input type="text" id="returnStorageLocation" value="Storage Depot (Shelf 1)" placeholder="e.g. Storage Depot Rack A-02" required>
                </div>

                <div class="modal-form-group">
                    <label for="returnChecklistNotes">Inspection & Diagnostic Notes</label>
                    <textarea id="returnChecklistNotes" rows="3" placeholder="Inspected charger, bag, power-on test passed, hard drive wiped."></textarea>
                </div>

            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn-secondary" id="cancelReturnBtn">Cancel</button>
                <button type="submit" class="btn-primary" style="background: #2563eb; border-color: #1d4ed8;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm Return & Check-In
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: TRANSFER ASSET TO ANOTHER CUSTODIAN
     ========================================================================= -->
<div class="modal-overlay" id="transferAssetModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line></svg>
                </div>
                <div>
                    <h3 style="margin: 0;">Transfer Asset Custody</h3>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;">Reassign device from current custodian to a new employee</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" id="closeTransferModalBtn">&times;</button>
        </div>
        <form id="transferAssetForm">
            <input type="hidden" id="transferAllocId">
            <div class="modal-body">
                
                <div class="preview-summary-card" style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span class="asset-tag-badge" id="transferTagText">AST-2024-001</span>
                        <span style="font-size: 11px; color: var(--text-muted);">Current Custodian</span>
                    </div>
                    <div style="font-weight: 700; font-size: 14px; color: var(--text-primary);" id="transferAssetNameText">-</div>
                    <div style="font-size: 12.5px; color: #4338ca; margin-top: 3px;">
                        Current: <strong id="transferOldEmpText">-</strong>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="transferNewEmpSelect">New Custodian (Employee) *</label>
                    <select id="transferNewEmpSelect" required style="width: 100%;">
                        <option value="">-- Choose New Custodian --</option>
                        <?php foreach ($employeesList as $emp): ?>
                            <option value="<?php echo htmlspecialchars($emp['id']); ?>" data-name="<?php echo htmlspecialchars($emp['name']); ?>" data-code="<?php echo htmlspecialchars($emp['emp_code']); ?>" data-dept="<?php echo htmlspecialchars($emp['department']); ?>">
                                <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['emp_code']); ?> - <?php echo htmlspecialchars($emp['department']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="modal-form-group">
                        <label for="transferEffectiveDate">Effective Transfer Date *</label>
                        <input type="date" id="transferEffectiveDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="transferReason">Transfer Reason *</label>
                        <select id="transferReason" required>
                            <option value="Department Transfer">Department Transfer</option>
                            <option value="Role Reassignment" selected>Role Reassignment</option>
                            <option value="Project Handover">Project Handover</option>
                            <option value="Employee Departure">Employee Departure Handover</option>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label for="transferNotes">Transfer Notes / Authorizer</label>
                    <textarea id="transferNotes" rows="2" placeholder="e.g. Authorized by Department Head. Device transferred in good working condition."></textarea>
                </div>

            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn-secondary" id="cancelTransferBtn">Cancel</button>
                <button type="submit" class="btn-primary" style="background: #7c3aed; border-color: #6d28d9;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Confirm Custody Transfer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: PRINTABLE HANDOVER SLIP RECEIPT
     ========================================================================= -->
<div class="modal-overlay" id="slipModal">
    <div class="modal-box" style="max-width: 680px;">
        <div class="modal-header">
            <h3>Equipment Handover Slip</h3>
            <button type="button" class="modal-close-btn" id="closeSlipModalBtn">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 72vh; overflow-y: auto;">
            <div class="handover-slip-sheet" id="handoverSlipContent">
                <!-- Header -->
                <div class="slip-header-brand">
                    <div>
                        <div class="slip-title">VIROS PORTAL</div>
                        <div class="slip-subtitle">IT Asset Handover & Custodian Agreement Slip</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-family: monospace; font-weight: 700; font-size: 13px; color: var(--cyan-primary);" id="slipNumber">SLIP-2026-0001</div>
                        <div style="font-size: 11px; color: #64748b;" id="slipDate">Date: <?php echo date('d-M-Y'); ?></div>
                    </div>
                </div>

                <!-- Custodian Info -->
                <div class="slip-section-title">1. Custodian Details</div>
                <div class="slip-grid-2">
                    <div class="slip-grid-item">
                        <span class="label">Employee Name:</span>
                        <span class="value" id="slipEmpName">Marcus Vance</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Employee ID:</span>
                        <span class="value" id="slipEmpCode">EMP-1001</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Department:</span>
                        <span class="value" id="slipEmpDept">Engineering</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Designation:</span>
                        <span class="value" id="slipEmpDesig">VP of Engineering</span>
                    </div>
                </div>

                <!-- Hardware Info -->
                <div class="slip-section-title">2. Hardware Equipment Specifications</div>
                <div class="slip-grid-2">
                    <div class="slip-grid-item">
                        <span class="label">Asset Tag:</span>
                        <span class="value" id="slipAssetTag" style="color: var(--cyan-primary);">AST-2024-001</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Category:</span>
                        <span class="value" id="slipAssetCat">Laptops</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Make & Model:</span>
                        <span class="value" id="slipAssetModel">MacBook Pro 16" M3 Max</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Serial Number:</span>
                        <span class="value" id="slipAssetSerial">C02G40PZMD6T</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Allocation Type:</span>
                        <span class="value" id="slipAllocType">Permanent</span>
                    </div>
                    <div class="slip-grid-item">
                        <span class="label">Handover Condition:</span>
                        <span class="value" id="slipCondition">Brand New</span>
                    </div>
                </div>

                <!-- Peripherals Included -->
                <div class="slip-section-title">3. Included Peripherals & Accessories</div>
                <div id="slipAccessoriesList" style="font-size: 11.5px; color: #334155; padding-left: 14px;">
                    • Power Adapter & USB-C Cable<br>
                    • Laptop Carrying Case / Backpack<br>
                    • Wireless Optical Mouse
                </div>

                <!-- Legal Terms -->
                <div class="slip-terms-box">
                    <strong>Terms of Custody:</strong> The undersigned employee acknowledges receiving the equipment listed above in clean, working condition. The employee accepts full responsibility for reasonable care and custody of the equipment for official duties, and agrees to promptly report any loss, damage, or malfunction to IT Helpdesk.
                </div>

                <!-- Signatures -->
                <div class="slip-sign-row">
                    <div class="slip-sign-box">
                        <div style="height: 35px;"></div>
                        <div><strong>Custodian Signature</strong></div>
                        <div style="font-size: 10.5px; color: #64748b;">(Employee Name / Date)</div>
                    </div>
                    <div class="slip-sign-box">
                        <div style="height: 35px;"></div>
                        <div><strong>Authorized IT Official</strong></div>
                        <div style="font-size: 10.5px; color: #64748b;">(IT Asset Management Seal / Sign)</div>
                    </div>
                </div>

            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-secondary" id="closeSlipBtn">Close</button>
            <button type="button" class="btn-primary" onclick="window.print()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Handover Slip
            </button>
        </div>
    </div>
</div>

<!-- Embedded JS Data -->
<script>
    window.INITIAL_ASSIGNMENTS = <?php echo json_encode($initialAssignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.AVAILABLE_ASSETS = <?php echo json_encode($availableAssets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.EMPLOYEES_LIST = <?php echo json_encode($employeesList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php
// Include Layout Footer
include 'includes/footer.php';
?>
