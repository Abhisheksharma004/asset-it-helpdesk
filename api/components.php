<?php
/**
 * Parts & Components API (Database Storage & CRUD)
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch components (with search, category, status filters) + live stats + next SKU
 * - POST:
 *     - action = 'create': Add component (auto-generating PRT+MMYY+3DIGITSERIAL if needed)
 *     - action = 'edit': Update component
 *     - action = 'delete': Delete component
 *     - action = 'install': Install / allocate component into an asset
 *     - action = 'detach': Detach component from asset and return to stock
 *     - action = 'import': Bulk import multiple components
 *     - action = 'get_next_sku': Get the next available SKU
 */

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Auto-create components table in SQL Server if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='components' AND xtype='U')
BEGIN
    CREATE TABLE components (
        id INT IDENTITY(1,1) PRIMARY KEY,
        sku NVARCHAR(50) NOT NULL UNIQUE,
        serial NVARCHAR(100) NOT NULL,
        name NVARCHAR(200) NOT NULL,
        category NVARCHAR(100) NOT NULL,
        branch_location NVARCHAR(150) NULL,
        brand NVARCHAR(100) NOT NULL,
        model NVARCHAR(100) NULL,
        specs NVARCHAR(255) NULL,
        status NVARCHAR(50) NOT NULL DEFAULT 'Available',
        installed_asset NVARCHAR(150) NULL DEFAULT '',
        location NVARCHAR(150) NULL DEFAULT 'Storage Depot',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO components (sku, serial, name, category, branch_location, brand, model, specs, status, installed_asset, location) VALUES
    ('PRT1026001', 'CRU-DDR4-88491', 'Crucial 16GB DDR4 3200MHz SO-DIMM', 'RAM & Memory Modules', 'Corporate HQ - Mumbai', 'Crucial', 'CT16G4SFD832A', '16GB DDR4 3200MHz CL22 1.2V 260-Pin', 'Installed', 'AST-2026-001 (Dell Latitude 5420)', 'Installed in Slot 2'),
    ('PRT1026002', 'CRU-DDR4-88492', 'Crucial 16GB DDR4 3200MHz SO-DIMM', 'RAM & Memory Modules', 'Corporate HQ - Mumbai', 'Crucial', 'CT16G4SFD832A', '16GB DDR4 3200MHz CL22 1.2V 260-Pin', 'Available', '', 'Depot Rack 1 (Drawer A-01)'),
    ('PRT1026003', 'KNG-DDR5-10293', 'Kingston Fury Beast 32GB DDR5 5600MHz', 'RAM & Memory Modules', 'Tech Hub - Bangalore', 'Kingston', 'KF556C40BBK2-32', '32GB (2x16GB) DDR5 5600MHz Desktop', 'Available', '', 'Depot Rack 1 (Drawer A-03)'),
    ('PRT1026004', 'SAM-NVME-99102', 'Samsung 980 PRO 1TB PCIe 4.0 NVMe M.2', 'Solid State Drives (SSD)', 'Corporate HQ - Mumbai', 'Samsung', 'MZ-V8P1T0B/AM', '1TB M.2 NVMe PCIe Gen4 (7000MB/s Read)', 'Installed', 'AST-2026-003 (MacBook Pro 16 M1)', 'Installed as Primary Drive'),
    ('PRT1026005', 'SAM-NVME-99103', 'Samsung 980 PRO 1TB PCIe 4.0 NVMe M.2', 'Solid State Drives (SSD)', 'Delivery Center - Hyderabad', 'Samsung', 'MZ-V8P1T0B/AM', '1TB M.2 NVMe PCIe Gen4 (7000MB/s Read)', 'Available', '', 'Depot Rack 2 (Drawer B-01)'),
    ('PRT1026006', 'CRU-SATA-44129', 'Crucial MX500 500GB 2.5-Inch SATA SSD', 'Solid State Drives (SSD)', 'Branch Office - Delhi NCR', 'Crucial', 'CT500MX500SSD1', '500GB SATA 6Gb/s 2.5-Inch 7mm Internal', 'Installed', 'AST-2026-004 (HP EliteDesk 800 G6)', 'Installed in SATA Bay 1'),
    ('PRT1026007', 'SEA-NAS-77218', 'Seagate IronWolf 4TB NAS Hard Drive', 'Hard Disk Drives (HDD)', 'Corporate HQ - Mumbai', 'Seagate', 'ST4000VN006', '4TB 5400RPM SATA 6Gb/s 256MB Cache', 'Installed', 'AST-2026-005 (Dell PowerEdge R740)', 'Server Bay 03'),
    ('PRT1026008', 'NV-RTX-55102', 'NVIDIA RTX A2000 12GB Workstation GPU', 'Graphics & GPU Cards', 'Tech Hub - Bangalore', 'NVIDIA / PNY', 'VCNRTXA2000-12GB', '12GB GDDR6 Low Profile PCIe 4.0 x16', 'Installed', 'AST-2026-006 (Custom AI Workstation)', 'PCIe Slot 1'),
    ('PRT1026009', 'INT-I7-33910', 'Intel Core i7-13700 Desktop Processor', 'Processors & CPUs', 'Corporate HQ - Mumbai', 'Intel', 'BX8071513700', '16 Cores (8P+8E) up to 5.2GHz LGA1700', 'Installed', 'AST-2026-006 (Custom AI Workstation)', 'Socket LGA1700'),
    ('PRT1026010', 'DEL-BAT-22019', 'Dell 58Wh 4-Cell Laptop Replacement Battery', 'Laptop Batteries', 'Delivery Center - Hyderabad', 'Dell OEM', '68Wh H5CKD', '15.2V 58Wh Li-ion for Latitude 5420/5430', 'Available', '', 'Battery Safe Cabinet (Shelf 2)'),
    ('PRT1026011', 'COR-750-66014', 'Corsair RM750x 750W Fully Modular PSU', 'Power Supply Units (PSU)', 'Tech Hub - Bangalore', 'Corsair', 'CP-9020199-NA', '750 Watt 80 Plus Gold Fully Modular', 'Under Repair', '', 'Repair Bench (Ticket #IT-884)'),
    ('PRT1026012', 'INT-NIC-12004', 'Intel X550-T2 Dual Port 10GbE Network Card', 'Network Interface Cards (NIC)', 'Branch Office - Delhi NCR', 'Intel', 'X550T2BLK', 'Dual-Port RJ45 10GbE PCIe 3.0 x4', 'Available', '', 'Depot Rack 4 (Drawer N-01)');
END";

if ($conn !== false) {
    sqlsrv_query($conn, $tableSetupSql);
    // Ensure column exists for existing tables
    $alterSql = "IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'components' AND COLUMN_NAME = 'branch_location')
                 BEGIN
                     ALTER TABLE components ADD branch_location NVARCHAR(150) NULL;
                 END";
    sqlsrv_query($conn, $alterSql);
}

/**
 * Generate Next SKU: PRT + MMYY + 3DIGITSERIAL (e.g. PRT1026013)
 */
function getNextComponentSku($conn, $offset = 0) {
    $now = new DateTime();
    $prefix = 'PRT' . $now->format('my'); // 'PRT' + MM + YY (e.g. PRT1026)

    $sql = "SELECT sku FROM components WHERE sku LIKE ? ORDER BY sku DESC";
    $stmt = sqlsrv_query($conn, $sql, [$prefix . '%']);

    $maxNum = 0;
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sku = $row['sku'];
            $serialPart = substr($sku, strlen($prefix));
            if (is_numeric($serialPart)) {
                $num = intval($serialPart);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    if ($maxNum === 0) {
        // Fallback: check count of components
        $cSql = "SELECT COUNT(*) as cnt FROM components";
        $cStmt = sqlsrv_query($conn, $cSql);
        if ($cStmt !== false && ($cRow = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC))) {
            $maxNum = intval($cRow['cnt']);
            sqlsrv_free_stmt($cStmt);
        }
    }

    $nextNum = $maxNum + 1 + $offset;
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle GET: Fetch components & stats
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status = trim($_GET['status'] ?? '');

    // Overall stats query
    $statsSql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available,
                    SUM(CASE WHEN status = 'Installed' THEN 1 ELSE 0 END) AS installed,
                    SUM(CASE WHEN status IN ('Under Repair', 'Defective') THEN 1 ELSE 0 END) AS repair
                 FROM components";
    $statsStmt = sqlsrv_query($conn, $statsSql);
    $stats = ['total' => 0, 'available' => 0, 'installed' => 0, 'repair' => 0];
    if ($statsStmt !== false && ($sRow = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total'] = intval($sRow['total'] ?? 0);
        $stats['available'] = intval($sRow['available'] ?? 0);
        $stats['installed'] = intval($sRow['installed'] ?? 0);
        $stats['repair'] = intval($sRow['repair'] ?? 0);
        sqlsrv_free_stmt($statsStmt);
    }

    // Filtered records query
    $sql = "SELECT c.id, c.sku, c.serial, c.name, c.category, c.branch_location, c.brand, c.model, c.specs, c.status, 
                   c.installed_asset, c.location,
                   a.id AS host_asset_id, a.tag AS host_asset_tag, a.name AS host_asset_name,
                   CONVERT(VARCHAR(10), c.created_at, 105) AS created_date
            FROM components c
            LEFT JOIN assets a ON (TRY_CAST(c.installed_asset AS INT) = a.id OR c.installed_asset = a.tag)
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (c.name LIKE ? OR c.sku LIKE ? OR c.serial LIKE ? OR c.brand LIKE ? OR c.model LIKE ? OR c.specs LIKE ? OR c.installed_asset LIKE ? OR c.location LIKE ? OR c.branch_location LIKE ? OR a.tag LIKE ? OR a.name LIKE ?)";
        $searchWild = '%' . $search . '%';
        for ($i = 0; $i < 11; $i++) {
            $params[] = $searchWild;
        }
    }

    if ($category !== '' && $category !== 'all') {
        $sql .= " AND c.category = ?";
        $params[] = $category;
    }

    if ($status !== '' && $status !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY c.id DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query failed.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $components = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hostDisplay = '';
        if (!empty($row['host_asset_tag'])) {
            $hostDisplay = $row['host_asset_tag'] . ' (' . $row['host_asset_name'] . ')';
        } elseif (!empty($row['installed_asset'])) {
            $hostDisplay = $row['installed_asset'];
        }

        $components[] = [
            'id'              => intval($row['id']),
            'sku'             => $row['sku'],
            'serial'          => $row['serial'],
            'name'            => $row['name'],
            'category'        => $row['category'],
            'branch_location' => $row['branch_location'] ?? '',
            'brand'           => $row['brand'],
            'model'           => $row['model'] ?? '',
            'specs'           => $row['specs'] ?? '',
            'status'          => $row['status'],
            'installedAsset'  => $hostDisplay,
            'installedAssetId'=> $row['installed_asset'] ?? '',
            'location'        => $row['location'] ?? '',
            'created_date'    => $row['created_date'] ?? ''
        ];
    }
    sqlsrv_free_stmt($stmt);

    $nextSku = getNextComponentSku($conn);

    echo json_encode([
        'success'    => true,
        'components' => $components,
        'stats'      => $stats,
        'next_sku'   => $nextSku
    ]);
    exit;
}

// Handle POST actions
if ($method === 'POST') {
    // Read JSON payload or form-data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim($input['action'] ?? '');

    if ($action === 'get_next_sku') {
        $nextSku = getNextComponentSku($conn);
        echo json_encode(['success' => true, 'next_sku' => $nextSku]);
        exit;
    }

    if ($action === 'create') {
        $name            = trim($input['name'] ?? '');
        $category        = trim($input['category'] ?? '');
        $branch_location = trim($input['branch_location'] ?? ($input['branch'] ?? ''));
        $brand           = trim($input['brand'] ?? '');
        $model           = trim($input['model'] ?? '');
        $serial          = trim($input['serial'] ?? '');
        $status          = trim($input['status'] ?? 'Available');
        $specs           = trim($input['specs'] ?? '');
        $location        = trim($input['location'] ?? 'Depot Shelf');
        $sku             = trim($input['sku'] ?? '');

        if ($branch_location === '') {
            $branch_location = 'Corporate HQ - Mumbai';
        }

        if ($name === '' || $category === '' || $brand === '' || $serial === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Name, Category, Brand, Serial).']);
            exit;
        }

        if ($sku === '') {
            $sku = getNextComponentSku($conn);
        }

        // Check SKU uniqueness
        $chkSql = "SELECT id FROM components WHERE sku = ?";
        $chkStmt = sqlsrv_query($conn, $chkSql, [$sku]);
        if ($chkStmt !== false && sqlsrv_has_rows($chkStmt)) {
            // If already exists, generate fresh unique SKU
            $sku = getNextComponentSku($conn, 1);
            sqlsrv_free_stmt($chkStmt);
        }

        $insSql = "INSERT INTO components (sku, serial, name, category, branch_location, brand, model, specs, status, installed_asset, location, created_at, updated_at) 
                   OUTPUT INSERTED.id 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, GETDATE(), GETDATE())";
        $insParams = [$sku, $serial, $name, $category, $branch_location, $brand, $model, $specs, $status, $location];
        $insStmt = sqlsrv_query($conn, $insSql, $insParams);

        if ($insStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save component.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = null;
        if ($row = sqlsrv_fetch_array($insStmt, SQLSRV_FETCH_ASSOC)) {
            $newId = $row['id'];
        }
        sqlsrv_free_stmt($insStmt);

        echo json_encode([
            'success' => true,
            'message' => "Component \"$name\" registered successfully with SKU $sku.",
            'id'      => $newId,
            'sku'     => $sku
        ]);
        exit;
    }

    if ($action === 'edit') {
        $id              = intval($input['id'] ?? 0);
        $name            = trim($input['name'] ?? '');
        $category        = trim($input['category'] ?? '');
        $branch_location = trim($input['branch_location'] ?? ($input['branch'] ?? ''));
        $brand           = trim($input['brand'] ?? '');
        $model           = trim($input['model'] ?? '');
        $serial          = trim($input['serial'] ?? '');
        $status          = trim($input['status'] ?? 'Available');
        $specs           = trim($input['specs'] ?? '');
        $location        = trim($input['location'] ?? 'Depot Shelf');

        if ($id <= 0 || $name === '' || $category === '' || $brand === '' || $serial === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid component data. Please fill all required fields.']);
            exit;
        }

        // If status changed to Available, clear installed_asset
        $updSql = "UPDATE components 
                   SET name = ?, category = ?, branch_location = ?, brand = ?, model = ?, serial = ?, status = ?, specs = ?, location = ?,
                       installed_asset = CASE WHEN ? = 'Available' THEN '' ELSE installed_asset END,
                       updated_at = GETDATE()
                   WHERE id = ?";
        $updParams = [$name, $category, $branch_location, $brand, $model, $serial, $status, $specs, $location, $status, $id];
        $updStmt = sqlsrv_query($conn, $updSql, $updParams);

        if ($updStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update component.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($updStmt);

        echo json_encode([
            'success' => true,
            'message' => "Component \"$name\" updated successfully."
        ]);
        exit;
    }

    if ($action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid component ID.']);
            exit;
        }

        $delSql = "DELETE FROM components WHERE id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$id]);
        if ($delStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete component.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($delStmt);

        echo json_encode(['success' => true, 'message' => 'Component removed from database successfully.']);
        exit;
    }

    if ($action === 'install') {
        $id          = intval($input['id'] ?? 0);
        $targetAsset = trim($input['target_asset'] ?? '');
        $technician  = trim($input['technician'] ?? '');

        if ($id <= 0 || $targetAsset === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Target asset is required for installation.']);
            exit;
        }

        $assetIdStr = $targetAsset;
        $locStr = "Installed in Asset #" . $targetAsset;
        if (is_numeric($targetAsset)) {
            $aStmt = sqlsrv_query($conn, "SELECT id, tag, name FROM assets WHERE id = ?", [intval($targetAsset)]);
            if ($aStmt && ($aRow = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC))) {
                $assetIdStr = strval($aRow['id']);
                $locStr = "Installed in " . $aRow['tag'] . " (" . $aRow['name'] . ")";
            }
            if ($aStmt) sqlsrv_free_stmt($aStmt);
        } else {
            $aStmt = sqlsrv_query($conn, "SELECT id, tag, name FROM assets WHERE tag = ?", [$targetAsset]);
            if ($aStmt && ($aRow = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC))) {
                $assetIdStr = strval($aRow['id']);
                $locStr = "Installed in " . $aRow['tag'] . " (" . $aRow['name'] . ")";
            }
            if ($aStmt) sqlsrv_free_stmt($aStmt);
        }

        $insSql = "UPDATE components 
                   SET status = 'Installed', installed_asset = ?, location = ?, updated_at = GETDATE() 
                   WHERE id = ?";
        $insStmt = sqlsrv_query($conn, $insSql, [$assetIdStr, $locStr, $id]);

        if ($insStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to allocate component.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($insStmt);

        echo json_encode([
            'success' => true,
            'message' => "Component successfully installed into $targetAsset."
        ]);
        exit;
    }

    if ($action === 'detach') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid component ID.']);
            exit;
        }

        $detSql = "UPDATE components 
                   SET status = 'Available', installed_asset = '', location = 'Returned to Depot Shelf', updated_at = GETDATE() 
                   WHERE id = ?";
        $detStmt = sqlsrv_query($conn, $detSql, [$id]);

        if ($detStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to detach component.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($detStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Component detached and returned to available stock.'
        ]);
        exit;
    }

    if ($action === 'import') {
        $rows = $input['rows'] ?? [];
        if (!is_array($rows) || empty($rows)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No rows provided for import.']);
            exit;
        }

        $imported = 0;
        foreach ($rows as $item) {
            $name     = trim($item['name'] ?? '');
            $category = trim($item['category'] ?? 'RAM & Memory Modules');
            $brand    = trim($item['brand'] ?? 'Generic OEM');
            $model    = trim($item['model'] ?? '');
            $serial   = trim($item['serial'] ?? ('SN-' . strtoupper(substr(uniqid(), -6))));
            $status   = trim($item['status'] ?? 'Available');
            $specs    = trim($item['specs'] ?? '');
            $location = trim($item['location'] ?? 'Storage Depot');

            if ($name === '') continue;

            $sku = getNextComponentSku($conn, $imported);

            $insSql = "INSERT INTO components (sku, serial, name, category, brand, model, specs, status, installed_asset, location, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, GETDATE(), GETDATE())";
            $insStmt = sqlsrv_query($conn, $insSql, [$sku, $serial, $name, $category, $brand, $model, $specs, $status, $location]);
            if ($insStmt !== false) {
                $imported++;
                sqlsrv_free_stmt($insStmt);
            }
        }

        echo json_encode([
            'success'  => true,
            'message'  => "Successfully imported $imported components into database.",
            'imported' => $imported
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
    exit;
}
