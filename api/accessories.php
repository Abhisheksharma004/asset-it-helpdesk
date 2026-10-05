<?php
/**
 * Accessories Management API (Database Storage & CRUD)
 * Asset Management & IT Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch accessories (with search, category, status filters) + live stats + next SKU
 * - POST:
 *     - action = 'create': Add accessory (auto-generating ASO+MMYY+3DIGITSERIAL if needed)
 *     - action = 'edit': Update accessory
 *     - action = 'delete': Delete accessory
 *     - action = 'issue': Issue / checkout accessory to an employee
 *     - action = 'get_next_sku': Get next available SKU
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Auto-create accessories table in SQL Server if not exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='accessories' AND xtype='U')
BEGIN
    CREATE TABLE accessories (
        id INT IDENTITY(1,1) PRIMARY KEY,
        sku NVARCHAR(50) NOT NULL UNIQUE,
        name NVARCHAR(200) NOT NULL,
        category NVARCHAR(100) NOT NULL,
        branch_location NVARCHAR(150) NULL,
        brand NVARCHAR(100) NOT NULL,
        model NVARCHAR(100) NULL,
        total_qty INT NOT NULL DEFAULT 1,
        in_stock INT NOT NULL DEFAULT 1,
        deployed INT NOT NULL DEFAULT 0,
        min_stock INT NOT NULL DEFAULT 5,
        location NVARCHAR(150) NULL DEFAULT 'HQ - New York Depot',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO accessories (sku, name, category, branch_location, brand, model, total_qty, in_stock, deployed, min_stock, location) VALUES
    ('ASO1026001', 'Logitech MX Master 3S Wireless Mouse', 'Keyboards & Mice', 'Corporate HQ - Mumbai', 'Logitech', 'MX Master 3S', 120, 34, 86, 15, 'HQ - New York Depot (Shelf A-02)'),
    ('ASO1026002', 'Dell Pro Wireless Keyboard & Mouse KM5221W', 'Keyboards & Mice', 'Corporate HQ - Mumbai', 'Dell', 'KM5221W', 250, 58, 192, 25, 'HQ - New York Depot (Shelf A-05)'),
    ('ASO1026003', 'Apple Magic Keyboard with Touch ID', 'Keyboards & Mice', 'Delivery Center - Hyderabad', 'Apple', 'Numeric Keypad', 60, 8, 52, 10, 'Austin Hub Depot (Shelf B-01)'),
    ('ASO1026004', 'Dell Thunderbolt 4 Dock WD22TB4', 'Docks & Hubs', 'Corporate HQ - Mumbai', 'Dell', 'WD22TB4 180W', 85, 19, 66, 10, 'HQ - New York Depot (Shelf C-01)'),
    ('ASO1026005', 'Anker 575 USB-C Docking Station (13-in-1)', 'Docks & Hubs', 'Delivery Center - Hyderabad', 'Anker', 'Triple Display 85W', 45, 3, 42, 8, 'Austin Hub Depot (Shelf C-03)'),
    ('ASO1026006', 'CalDigit TS4 Thunderbolt 4 Dock (18 Ports)', 'Docks & Hubs', 'Branch Office - Delhi NCR', 'CalDigit', 'TS4-US 98W', 25, 0, 25, 5, 'London Office Store (Shelf D-01)'),
    ('ASO1026007', 'Jabra Evolve2 65 UC Wireless Headset', 'Headsets & Audio', 'Tech Hub - Bangalore', 'Jabra', 'HSC110W Dual ANC', 110, 22, 88, 15, 'Bangalore DC Depot (Shelf H-01)'),
    ('ASO1026008', 'Poly Voyager Focus 2 UC Headset', 'Headsets & Audio', 'Delivery Center - Hyderabad', 'Poly', 'Focus 2 Bluetooth', 50, 14, 36, 10, 'Austin Hub Depot (Shelf H-02)'),
    ('ASO1026009', 'Sony WH-1000XM5 ANC Headphones', 'Headsets & Audio', 'Corporate HQ - Mumbai', 'Sony', 'WH-1000XM5 Black', 30, 2, 28, 6, 'HQ - New York Depot (Shelf H-04)'),
    ('ASO1026010', 'Logitech Brio 4K Ultra HD Webcam', 'Webcams & Video', 'Corporate HQ - Mumbai', 'Logitech', 'Brio 4K HDR', 95, 28, 67, 12, 'HQ - New York Depot (Shelf V-01)'),
    ('ASO1026011', 'Anker PowerConf C300 HD Webcam', 'Webcams & Video', 'Tech Hub - Bangalore', 'Anker', 'C300 1080p 60fps', 75, 21, 54, 10, 'Bangalore DC Depot (Shelf V-02)'),
    ('ASO1026012', 'Apple 96W USB-C Power Adapter', 'Chargers & Power Adapters', 'Delivery Center - Hyderabad', 'Apple', '96W GaN Fast Charger', 80, 16, 64, 12, 'Austin Hub Depot (Shelf P-01)'),
    ('ASO1026013', 'Lenovo 65W USB-C GaN Travel Charger', 'Chargers & Power Adapters', 'Corporate HQ - Mumbai', 'Lenovo', 'ThinkPad 65W AC', 140, 35, 105, 20, 'HQ - New York Depot (Shelf P-03)'),
    ('ASO1026014', 'Dell 130W USB-C Slim AC Adapter', 'Chargers & Power Adapters', 'Corporate HQ - Mumbai', 'Dell', 'HA130PM170', 90, 0, 90, 10, 'HQ - New York Depot (Shelf P-04)'),
    ('ASO1026015', 'Belkin USB-C to 4K HDMI Adapter', 'Cables & Display Adapters', 'Branch Office - Delhi NCR', 'Belkin', 'AVC002btBK 4K@60Hz', 150, 44, 106, 20, 'London Office Store (Shelf C-02)'),
    ('ASO1026016', 'Anker USB-C to Lightning Braided Cable (6ft)', 'Cables & Display Adapters', 'Tech Hub - Bangalore', 'Anker', 'PowerLine III MFi', 80, 0, 80, 15, 'Bangalore DC Depot (Shelf C-05)'),
    ('ASO1026017', 'YubiKey 5 NFC Hardware Security Key', 'Security Tokens & Smart Keys', 'Corporate HQ - Mumbai', 'Yubico', 'Y-501 FIDO2', 120, 27, 93, 15, 'HQ - New York Depot (Safe Vault 1)'),
    ('ASO1026018', 'Rain Design mStand Aluminum Laptop Stand', 'Laptop Stands & Mounts', 'Delivery Center - Hyderabad', 'Rain Design', 'mStand 10032', 75, 19, 56, 10, 'Austin Hub Depot (Shelf S-01)');
END";

if (isset($conn) && $conn !== false) {
    sqlsrv_query($conn, $tableSetupSql);
    // Ensure column exists for existing tables
    $alterSql = "IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'accessories' AND COLUMN_NAME = 'branch_location')
                 BEGIN
                     ALTER TABLE accessories ADD branch_location NVARCHAR(150) NULL;
                 END";
    sqlsrv_query($conn, $alterSql);
}

if (empty($_SESSION['logged_in']) && !isset($_GET['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

/**
 * Generate Next SKU: ASO + MMYY + 3DIGITSERIAL (e.g. ASO1026019)
 */
function getNextAccessorySku($conn, $offset = 0) {
    $now = new DateTime();
    $prefix = 'ASO' . $now->format('my');

    $sql = "SELECT sku FROM accessories WHERE sku LIKE ? ORDER BY sku DESC";
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
        $cSql = "SELECT COUNT(*) as cnt FROM accessories";
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

if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status = trim($_GET['status'] ?? '');

    // Overall stats
    $statsSql = "SELECT 
                    COUNT(*) AS total,
                    ISNULL(SUM(in_stock), 0) AS in_stock,
                    ISNULL(SUM(deployed), 0) AS deployed,
                    ISNULL(SUM(CASE WHEN in_stock > 0 AND in_stock <= min_stock THEN 1 ELSE 0 END), 0) AS low_stock
                 FROM accessories";
    $statsStmt = sqlsrv_query($conn, $statsSql);
    $stats = ['total' => 0, 'in_stock' => 0, 'deployed' => 0, 'low_stock' => 0];
    if ($statsStmt !== false && ($sRow = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total'] = intval($sRow['total'] ?? 0);
        $stats['in_stock'] = intval($sRow['in_stock'] ?? 0);
        $stats['deployed'] = intval($sRow['deployed'] ?? 0);
        $stats['low_stock'] = intval($sRow['low_stock'] ?? 0);
        sqlsrv_free_stmt($statsStmt);
    }

    // Query records
    $sql = "SELECT id, sku, name, category, branch_location, brand, model, total_qty, in_stock, deployed, min_stock, location, 
                   CONVERT(VARCHAR(19), created_at, 120) as created_date
            FROM accessories 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (name LIKE ? OR sku LIKE ? OR brand LIKE ? OR model LIKE ? OR location LIKE ? OR category LIKE ? OR branch_location LIKE ?)";
        $searchWild = '%' . $search . '%';
        for ($i = 0; $i < 7; $i++) {
            $params[] = $searchWild;
        }
    }

    if ($category !== '' && $category !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    if ($status === 'In Stock') {
        $sql .= " AND in_stock > min_stock";
    } elseif ($status === 'Low Stock') {
        $sql .= " AND in_stock > 0 AND in_stock <= min_stock";
    } elseif ($status === 'Out of Stock') {
        $sql .= " AND in_stock = 0";
    }

    $sql .= " ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query failed.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $accessories = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $accessories[] = [
            'id'              => intval($row['id']),
            'sku'             => $row['sku'],
            'name'            => $row['name'],
            'category'        => $row['category'],
            'branch_location' => $row['branch_location'] ?? '',
            'brand'           => $row['brand'],
            'model'           => $row['model'] ?? '',
            'totalQty'        => intval($row['total_qty']),
            'inStock'         => intval($row['in_stock']),
            'deployed'        => intval($row['deployed']),
            'minStock'        => intval($row['min_stock']),
            'location'        => $row['location'] ?? '',
            'created_date'    => $row['created_date'] ?? ''
        ];
    }
    sqlsrv_free_stmt($stmt);

    $nextSku = getNextAccessorySku($conn);

    echo json_encode([
        'success'     => true,
        'accessories' => $accessories,
        'stats'       => $stats,
        'next_sku'    => $nextSku
    ]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim($input['action'] ?? '');

    if ($action === 'get_next_sku') {
        $nextSku = getNextAccessorySku($conn);
        echo json_encode(['success' => true, 'next_sku' => $nextSku]);
        exit;
    }

    if ($action === 'create') {
        $name            = trim($input['name'] ?? '');
        $category        = trim($input['category'] ?? '');
        $branch_location = trim($input['branch_location'] ?? ($input['branch'] ?? ''));
        $brand           = trim($input['brand'] ?? '');
        $model           = trim($input['model'] ?? '');
        $totalQty        = intval($input['totalQty'] ?? ($input['total_qty'] ?? 1));
        $minStock        = intval($input['minStock'] ?? ($input['min_stock'] ?? 5));
        $location        = trim($input['location'] ?? 'HQ - New York Depot');
        $sku             = trim($input['sku'] ?? '');

        if ($branch_location === '') {
            $branch_location = 'Corporate HQ - Mumbai';
        }

        if ($name === '' || $category === '' || $brand === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Name, Category, Brand).']);
            exit;
        }

        if ($totalQty <= 0) $totalQty = 1;
        if ($minStock < 0) $minStock = 5;

        if ($sku === '') {
            $sku = getNextAccessorySku($conn);
        }

        // Check SKU uniqueness
        $chkSql = "SELECT id FROM accessories WHERE sku = ?";
        $chkStmt = sqlsrv_query($conn, $chkSql, [$sku]);
        if ($chkStmt !== false && sqlsrv_has_rows($chkStmt)) {
            $sku = getNextAccessorySku($conn, 1);
            sqlsrv_free_stmt($chkStmt);
        }

        $insSql = "INSERT INTO accessories (sku, name, category, branch_location, brand, model, total_qty, in_stock, deployed, min_stock, location, created_at, updated_at) 
                   OUTPUT INSERTED.id 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, GETDATE(), GETDATE())";
        $insParams = [$sku, $name, $category, $branch_location, $brand, $model, $totalQty, $totalQty, $minStock, $location];
        $insStmt = sqlsrv_query($conn, $insSql, $insParams);

        if ($insStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save accessory.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = null;
        if ($row = sqlsrv_fetch_array($insStmt, SQLSRV_FETCH_ASSOC)) {
            $newId = $row['id'];
        }
        sqlsrv_free_stmt($insStmt);

        echo json_encode([
            'success' => true,
            'message' => "Accessory \"$name\" registered successfully with SKU $sku.",
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
        $totalQty        = intval($input['totalQty'] ?? ($input['total_qty'] ?? 1));
        $minStock        = intval($input['minStock'] ?? ($input['min_stock'] ?? 5));
        $location        = trim($input['location'] ?? 'HQ - New York Depot');

        if ($id <= 0 || $name === '' || $category === '' || $brand === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid accessory data. Please fill all required fields.']);
            exit;
        }

        if ($totalQty <= 0) $totalQty = 1;
        if ($minStock < 0) $minStock = 5;

        // Fetch current quantities to calculate in_stock adjustment
        $curSql = "SELECT total_qty, in_stock, deployed FROM accessories WHERE id = ?";
        $curStmt = sqlsrv_query($conn, $curSql, [$id]);
        $curRow = ($curStmt !== false) ? sqlsrv_fetch_array($curStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($curStmt !== false) sqlsrv_free_stmt($curStmt);

        $curTotal = intval($curRow['total_qty'] ?? $totalQty);
        $curInStock = intval($curRow['in_stock'] ?? $totalQty);
        $curDeployed = intval($curRow['deployed'] ?? 0);

        $diff = $totalQty - $curTotal;
        $newInStock = max(0, $curInStock + $diff);

        $updSql = "UPDATE accessories 
                   SET name = ?, category = ?, branch_location = ?, brand = ?, model = ?, total_qty = ?, in_stock = ?, min_stock = ?, location = ?, updated_at = GETDATE()
                   WHERE id = ?";
        $updParams = [$name, $category, $branch_location, $brand, $model, $totalQty, $newInStock, $minStock, $location, $id];
        $updStmt = sqlsrv_query($conn, $updSql, $updParams);

        if ($updStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update accessory.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($updStmt);

        echo json_encode([
            'success' => true,
            'message' => "Accessory \"$name\" updated successfully."
        ]);
        exit;
    }

    if ($action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid accessory ID.']);
            exit;
        }

        $delSql = "DELETE FROM accessories WHERE id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, [$id]);
        if ($delStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete accessory.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($delStmt);

        echo json_encode(['success' => true, 'message' => 'Accessory removed from database successfully.']);
        exit;
    }

    if ($action === 'issue') {
        $id       = intval($input['id'] ?? 0);
        $qty      = intval($input['qty'] ?? 1);
        $employee = trim($input['employee'] ?? '');

        if ($id <= 0 || $qty <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid accessory or quantity.']);
            exit;
        }

        // Check current stock
        $chkSql = "SELECT name, in_stock, deployed FROM accessories WHERE id = ?";
        $chkStmt = sqlsrv_query($conn, $chkSql, [$id]);
        $row = ($chkStmt !== false) ? sqlsrv_fetch_array($chkStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($chkStmt !== false) sqlsrv_free_stmt($chkStmt);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Accessory not found.']);
            exit;
        }

        if (intval($row['in_stock']) < $qty) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Requested quantity ($qty) exceeds available stock ({$row['in_stock']})."]);
            exit;
        }

        $updSql = "UPDATE accessories 
                   SET in_stock = in_stock - ?, deployed = deployed + ?, updated_at = GETDATE() 
                   WHERE id = ?";
        $updStmt = sqlsrv_query($conn, $updSql, [$qty, $qty, $id]);
        if ($updStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to issue accessory.', 'errors' => sqlsrv_errors()]);
            exit;
        }
        sqlsrv_free_stmt($updStmt);

        $accName = $row['name'];
        echo json_encode([
            'success' => true,
            'message' => "Issued $qty unit(s) of \"$accName\" to $employee successfully."
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}
