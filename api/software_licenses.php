<?php
/**
 * Software & Licenses Management API (Database Storage, CRUD & Renewal Logs)
 * Asset Management & IT Service Desk Portal
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// 1. Ensure software_licenses table exists
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='software_licenses' AND xtype='U')
BEGIN
    CREATE TABLE software_licenses (
        id INT IDENTITY(1,1) PRIMARY KEY,
        software_name NVARCHAR(200) NOT NULL,
        publisher NVARCHAR(150) NOT NULL,
        category NVARCHAR(100) NOT NULL,
        version NVARCHAR(100) NULL,
        license_type NVARCHAR(100) NOT NULL,
        license_key NVARCHAR(250) NULL,
        vendor NVARCHAR(150) NULL,
        status NVARCHAR(50) NOT NULL DEFAULT 'Active',
        purchase_date NVARCHAR(50) NULL,
        expiry_date NVARCHAR(50) NULL,
        total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
        notes NVARCHAR(MAX) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";
sqlsrv_query($conn, $tableSetupSql);

// 2. Ensure software_license_renewals log table exists
$renewalLogTableSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='software_license_renewals' AND xtype='U')
BEGIN
    CREATE TABLE software_license_renewals (
        id INT IDENTITY(1,1) PRIMARY KEY,
        license_id INT NOT NULL,
        software_name NVARCHAR(200) NOT NULL,
        previous_expiry NVARCHAR(50) NULL,
        new_expiry NVARCHAR(50) NOT NULL,
        previous_license_key NVARCHAR(250) NULL,
        new_license_key NVARCHAR(250) NULL,
        renewal_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
        renewal_date DATE NOT NULL DEFAULT GETDATE(),
        renewed_by NVARCHAR(150) NULL DEFAULT 'IT Administrator',
        notes NVARCHAR(MAX) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";
sqlsrv_query($conn, $renewalLogTableSql);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Helper function to calculate UI brand logo color
function computeBrandCode($publisher) {
    $p = strtolower($publisher);
    if (strpos($p, 'micro') !== false) return 'msft';
    if (strpos($p, 'adobe') !== false) return 'adobe';
    if (strpos($p, 'jet') !== false) return 'jb';
    if (strpos($p, 'slack') !== false) return 'slack';
    if (strpos($p, 'git') !== false) return 'github';
    if (strpos($p, 'atlass') !== false) return 'atlassian';
    if (strpos($p, 'crowd') !== false) return 'crowdstrike';
    return 'default';
}

// Helper function: Dynamic Expiry Status Calculation from Today's Date
function computeLicenseExpiryStatus($expiryDateStr, $defaultStatus = 'Active') {
    $expStr = trim($expiryDateStr ?? '');
    if ($expStr === '' || strcasecmp($expStr, 'Perpetual') === 0 || strcasecmp($expStr, 'Lifetime') === 0) {
        return [
            'status' => 'Active',
            'days' => null,
            'label' => 'Lifetime Perpetual',
            'badge_class' => 'safe'
        ];
    }

    $expTs = strtotime($expStr);
    if ($expTs === false) {
        return [
            'status' => $defaultStatus ?: 'Active',
            'days' => null,
            'label' => $expStr,
            'badge_class' => 'safe'
        ];
    }

    $todayTs = strtotime(date('Y-m-d'));
    $diffDays = (int) floor(($expTs - $todayTs) / 86400);

    if ($diffDays < 0) {
        $absDays = abs($diffDays);
        return [
            'status' => 'Expired',
            'days' => $diffDays,
            'label' => 'Expired ' . ($absDays === 1 ? '1 day' : "{$absDays} days") . ' ago',
            'badge_class' => 'expired'
        ];
    } elseif ($diffDays <= 30) {
        return [
            'status' => 'Expiring Soon',
            'days' => $diffDays,
            'label' => ($diffDays === 0) ? 'Expires Today!' : "Expires in {$diffDays} day" . ($diffDays === 1 ? '' : 's'),
            'badge_class' => 'warn'
        ];
    } else {
        return [
            'status' => 'Active',
            'days' => $diffDays,
            'label' => "Active ({$diffDays} days left)",
            'badge_class' => 'safe'
        ];
    }
}

// -----------------------------------------------------------------------------
// GET: Fetch software licenses, KPI stats, or Renewal Logs
// -----------------------------------------------------------------------------
if ($method === 'GET') {
    // If fetching renewal logs
    if (isset($_GET['fetch_renewals'])) {
        $licenseId = intval($_GET['license_id'] ?? 0);
        $whereSql = "";
        $params = [];
        if ($licenseId > 0) {
            $whereSql = "WHERE license_id = ?";
            $params = [$licenseId];
        }

        $logSql = "SELECT id, license_id, software_name, previous_expiry, new_expiry, 
                          previous_license_key, new_license_key, renewal_cost, 
                          CONVERT(VARCHAR(10), renewal_date, 120) AS renewal_date, 
                          renewed_by, notes, 
                          CONVERT(VARCHAR(19), created_at, 120) AS created_at
                   FROM software_license_renewals 
                   $whereSql 
                   ORDER BY id DESC";

        $logStmt = sqlsrv_query($conn, $logSql, $params);
        $renewals = [];
        if ($logStmt !== false) {
            while ($r = sqlsrv_fetch_array($logStmt, SQLSRV_FETCH_ASSOC)) {
                $renewals[] = [
                    'id'                   => intval($r['id']),
                    'license_id'           => intval($r['license_id']),
                    'software_name'        => $r['software_name'] ?? '',
                    'previous_expiry'      => $r['previous_expiry'] ?? '—',
                    'new_expiry'           => $r['new_expiry'] ?? '',
                    'previous_license_key' => $r['previous_license_key'] ?? '',
                    'new_license_key'      => $r['new_license_key'] ?? '',
                    'renewal_cost'         => floatval($r['renewal_cost'] ?? 0),
                    'renewal_date'         => $r['renewal_date'] ?? date('Y-m-d'),
                    'renewed_by'           => $r['renewed_by'] ?? 'IT Administrator',
                    'notes'                => $r['notes'] ?? '',
                    'created_at'           => $r['created_at'] ?? ''
                ];
            }
            sqlsrv_free_stmt($logStmt);
        }

        echo json_encode(['success' => true, 'renewals' => $renewals]);
        exit;
    }

    // Default: Fetch Licenses List
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? 'all');
    $status = trim($_GET['status'] ?? 'all');
    $type = trim($_GET['type'] ?? 'all');

    $where = [];
    $params = [];

    if ($category !== '' && $category !== 'all') {
        $where[] = "category = ?";
        $params[] = $category;
    }
    if ($status !== '' && $status !== 'all') {
        $where[] = "status = ?";
        $params[] = $status;
    }
    if ($type !== '' && $type !== 'all') {
        $where[] = "license_type = ?";
        $params[] = $type;
    }
    if ($search !== '') {
        $where[] = "(software_name LIKE ? OR publisher LIKE ? OR license_key LIKE ? OR vendor LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }

    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT * FROM software_licenses $whereClause ORDER BY id DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }

    $licenses = [];
    $activeCount = 0;
    $expiringCount = 0;
    $expiredCount = 0;
    $totalSpend = 0;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $name = $row['software_name'] ?? '';
        $pub = $row['publisher'] ?? '';
        $expDateStr = $row['expiry_date'] ?? '';
        $rawStatus = $row['status'] ?? 'Active';
        $calc = computeLicenseExpiryStatus($expDateStr, $rawStatus);

        $totalSpend += floatval($row['total_cost'] ?? 0);
        if ($calc['status'] === 'Active') $activeCount++;
        elseif ($calc['status'] === 'Expiring Soon') $expiringCount++;
        elseif ($calc['status'] === 'Expired') $expiredCount++;

        $licenses[] = [
            'id'             => intval($row['id']),
            'name'           => $name,
            'software_name'  => $name,
            'publisher'      => $pub,
            'brand_code'     => computeBrandCode($pub),
            'category'       => $row['category'] ?? '',
            'version'        => $row['version'] ?? '',
            'license_type'   => $row['license_type'] ?? '',
            'license_key'    => $row['license_key'] ?? '',
            'vendor'         => $row['vendor'] ?? '',
            'status'         => $calc['status'],
            'raw_status'     => $rawStatus,
            'days_remaining' => $calc['days'],
            'expiry_label'   => $calc['label'],
            'badge_class'    => $calc['badge_class'],
            'purchase_date'  => $row['purchase_date'] ?? '',
            'expiry_date'    => $expDateStr,
            'total_cost'     => floatval($row['total_cost'] ?? 0),
            'notes'          => $row['notes'] ?? ''
        ];
    }
    sqlsrv_free_stmt($stmt);

    $kpiData = [
        'total'    => count($licenses),
        'active'   => $activeCount,
        'expiring' => $expiringCount,
        'expired'  => $expiredCount,
        'spend'    => $totalSpend
    ];

    echo json_encode([
        'success'  => true,
        'licenses' => $licenses,
        'kpi'      => $kpiData
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// POST: Create, Edit, Renew (with Audit Log), Delete
// -----------------------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim($input['action'] ?? '');

    // Action: Renew License (Updates License + Records in Renewal Logs Table)
    if ($action === 'renew') {
        $id = intval($input['id'] ?? 0);
        $newExpiry = trim($input['expiry_date'] ?? '');
        $newCost = floatval($input['total_cost'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        $renewedBy = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'IT Administrator';

        if ($id <= 0 || empty($newExpiry)) {
            echo json_encode(['success' => false, 'error' => 'Valid license ID and new expiry date are required.']);
            exit;
        }

        // 1. Fetch existing license info
        // 1. Fetch existing license info (including previous product key)
        $existStmt = sqlsrv_query($conn, "SELECT software_name, expiry_date, total_cost, license_key, notes FROM software_licenses WHERE id = ?", [$id]);
        if ($existStmt === false || !($existing = sqlsrv_fetch_array($existStmt, SQLSRV_FETCH_ASSOC))) {
            echo json_encode(['success' => false, 'error' => 'License record not found.']);
            exit;
        }
        $swName = $existing['software_name'] ?? 'Software';
        $prevExpiry = $existing['expiry_date'] ?? '—';
        $prevKey = trim($existing['license_key'] ?? '');
        $newKeyInput = trim($input['license_key'] ?? '');
        $finalKey = !empty($newKeyInput) ? $newKeyInput : $prevKey;
        sqlsrv_free_stmt($existStmt);

        // 2. Update software_licenses record
        $updateSql = "UPDATE software_licenses SET 
                        expiry_date = ?, 
                        total_cost = ?, 
                        license_key = ?,
                        status = 'Active', 
                        updated_at = GETDATE() 
                      WHERE id = ?";
        $uStmt = sqlsrv_query($conn, $updateSql, [$newExpiry, $newCost, $finalKey, $id]);
        if ($uStmt === false) {
            echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
            exit;
        }

        // 3. Insert into software_license_renewals audit log table (with old and new key tracking)
        $logInsertSql = "INSERT INTO software_license_renewals 
                        (license_id, software_name, previous_expiry, new_expiry, previous_license_key, new_license_key, renewal_cost, renewal_date, renewed_by, notes)
                        OUTPUT INSERTED.id
                        VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?)";
        $logParams = [$id, $swName, $prevExpiry, $newExpiry, $prevKey, $finalKey, $newCost, $renewedBy, $notes];
        $lStmt = sqlsrv_query($conn, $logInsertSql, $logParams);
        $newLogId = 0;
        if ($lStmt !== false && ($logRow = sqlsrv_fetch_array($lStmt, SQLSRV_FETCH_ASSOC))) {
            $newLogId = intval($logRow['id'] ?? 0);
        }

        echo json_encode([
            'success' => true, 
            'message' => "Software license \"{$swName}\" successfully renewed until {$newExpiry}.",
            'log_id'  => $newLogId,
            'renewal' => [
                'id'                   => $newLogId,
                'license_id'           => $id,
                'software_name'        => $swName,
                'previous_expiry'      => $prevExpiry,
                'new_expiry'           => $newExpiry,
                'previous_license_key' => $prevKey,
                'new_license_key'      => $finalKey,
                'renewal_cost'         => $newCost,
                'renewal_date'         => date('Y-m-d'),
                'renewed_by'           => $renewedBy,
                'notes'                => $notes
            ]
        ]);
        exit;
    }

    // Action: Create License
    if ($action === 'create') {
        $name = trim($input['software_name'] ?? ($input['name'] ?? ''));
        $publisher = trim($input['publisher'] ?? '');
        $category = trim($input['category'] ?? 'Office & Productivity');
        $version = trim($input['version'] ?? '');
        $license_type = trim($input['license_type'] ?? 'SaaS Subscription');
        $license_key = trim($input['license_key'] ?? '');
        $vendor = trim($input['vendor'] ?? '');
        $purchase_date = trim($input['purchase_date'] ?? date('Y-m-d'));
        $expiry_date = trim($input['expiry_date'] ?? 'Perpetual');
        $total_cost = floatval($input['total_cost'] ?? 0);
        $status = trim($input['status'] ?? 'Active');
        $notes = trim($input['notes'] ?? '');

        if (empty($name) || empty($publisher)) {
            echo json_encode(['success' => false, 'error' => 'Software name and publisher are required.']);
            exit;
        }

        $sql = "INSERT INTO software_licenses 
                (software_name, publisher, category, version, license_type, license_key, vendor, status, purchase_date, expiry_date, total_cost, notes)
                OUTPUT INSERTED.id
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$name, $publisher, $category, $version, $license_type, $license_key, $vendor, $status, $purchase_date, $expiry_date, $total_cost, $notes];

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
            exit;
        }
        $newRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $newId = $newRow['id'] ?? 0;

        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Software license created successfully.']);
        exit;
    }

    // Action: Edit License
    if ($action === 'edit') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
            exit;
        }

        $name = trim($input['software_name'] ?? ($input['name'] ?? ''));
        $publisher = trim($input['publisher'] ?? '');
        $category = trim($input['category'] ?? '');
        $version = trim($input['version'] ?? '');
        $license_type = trim($input['license_type'] ?? '');
        $license_key = trim($input['license_key'] ?? '');
        $vendor = trim($input['vendor'] ?? '');
        $purchase_date = trim($input['purchase_date'] ?? '');
        $expiry_date = trim($input['expiry_date'] ?? '');
        $total_cost = floatval($input['total_cost'] ?? 0);
        $status = trim($input['status'] ?? 'Active');
        $notes = trim($input['notes'] ?? '');

        $sql = "UPDATE software_licenses SET 
                software_name = ?, publisher = ?, category = ?, version = ?, license_type = ?, 
                license_key = ?, vendor = ?, status = ?, purchase_date = ?, expiry_date = ?, 
                total_cost = ?, notes = ?, updated_at = GETDATE()
                WHERE id = ?";
        $params = [$name, $publisher, $category, $version, $license_type, $license_key, $vendor, $status, $purchase_date, $expiry_date, $total_cost, $notes, $id];

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Software license updated successfully.']);
        exit;
    }

    // Action: Delete License
    if ($action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
            exit;
        }

        // Delete license and associated renewal logs
        sqlsrv_query($conn, "DELETE FROM software_license_renewals WHERE license_id = ?", [$id]);
        $stmt = sqlsrv_query($conn, "DELETE FROM software_licenses WHERE id = ?", [$id]);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Software license deleted.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}
