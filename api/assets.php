<?php
/**
 * Assets Management API (Database Storage & Real MSSQL Persistence)
 * VIROS IT Asset & Service Desk Portal
 * 
 * Supports:
 * - GET: Fetch assets (with search, category, status, department, location filters) + live stats + next tag
 * - POST:
 *     - action = 'create': Register new asset in database
 *     - action = 'edit': Update existing asset in database
 *     - action = 'delete': Delete or retire asset in database
 *     - action = 'reassign': Assign/transfer asset to employee or return to stock
 *     - action = 'bulk_status': Bulk update status for multiple assets
 *     - action = 'bulk_delete': Bulk delete / retire assets
 *     - action = 'import': Bulk import assets into database
 *     - action = 'get_next_tag': Get the next available asset tag
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

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// -----------------------------------------------------------------------------
// Auto-create 'assets' table in SQL Server if not exists & seed initial records
// -----------------------------------------------------------------------------
$tableSetupSql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='assets' AND xtype='U')
BEGIN
    CREATE TABLE assets (
        id INT IDENTITY(1,1) PRIMARY KEY,
        tag NVARCHAR(50) NOT NULL UNIQUE,
        name NVARCHAR(200) NOT NULL,
        category NVARCHAR(100) NOT NULL,
        brand NVARCHAR(100) NOT NULL,
        model NVARCHAR(150) NULL,
        serial NVARCHAR(100) NULL,
        status NVARCHAR(50) NOT NULL DEFAULT 'Available',
        condition NVARCHAR(50) NOT NULL DEFAULT 'Good',
        location NVARCHAR(150) NULL,
        department NVARCHAR(150) NULL,

        assigned_to NVARCHAR(150) NULL,

        -- Hardware Specs
        processor NVARCHAR(150) NULL,
        ram NVARCHAR(100) NULL,
        storage NVARCHAR(150) NULL,
        os NVARCHAR(150) NULL,
        mac_address NVARCHAR(50) NULL,
        ip_address NVARCHAR(50) NULL,

        -- Procurement & Financials
        vendor NVARCHAR(150) NULL,
        po_number NVARCHAR(100) NULL,
        purchase_date NVARCHAR(50) NULL,
        cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        warranty_expiry NVARCHAR(50) NULL,

        -- Structured JSON metadata
        components_json NVARCHAR(MAX) NULL,

        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";

sqlsrv_query($conn, $tableSetupSql);
// Auto-clean legacy columns if they exist and ensure serial column is nullable
if (isset($conn) && $conn !== false) {
    @sqlsrv_query($conn, "ALTER TABLE assets ALTER COLUMN serial NVARCHAR(100) NULL");
    $chkLegacy = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='assets' AND COLUMN_NAME='assigned_name'");
    if ($chkLegacy && sqlsrv_fetch_array($chkLegacy, SQLSRV_FETCH_ASSOC)) {
        sqlsrv_query($conn, "IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='assets' AND COLUMN_NAME='assigned_to') ALTER TABLE assets ADD assigned_to NVARCHAR(150) NULL");
        sqlsrv_query($conn, "UPDATE assets SET assigned_to = assigned_name WHERE assigned_name IS NOT NULL AND (assigned_to IS NULL OR assigned_to = '')");
        foreach (['assigned_name', 'assigned_emp_code', 'assigned_email', 'assigned_dept', 'assigned_role', 'assigned_date', 'history_json', 'tickets_json'] as $col) {
            sqlsrv_query($conn, "ALTER TABLE assets DROP COLUMN $col");
        }
    }
}




function getNextAssetTag($conn) {
    $currentYear = date('Y');
    $prefix = 'AST' . $currentYear;

    $sql = "SELECT tag FROM assets WHERE tag LIKE ? ORDER BY tag DESC";
    $stmt = sqlsrv_query($conn, $sql, [$prefix . '%']);

    $maxNum = 0;
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tag = $row['tag'];
            $serialPart = substr($tag, strlen($prefix));
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
        // Fallback: check any AST tag
        $stmtAll = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM assets");
        if ($stmtAll !== false) {
            $r = sqlsrv_fetch_array($stmtAll, SQLSRV_FETCH_ASSOC);
            $maxNum = intval($r['total'] ?? 0);
            sqlsrv_free_stmt($stmtAll);
        }
    }

    return $prefix . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Synchronize components in the 'components' table with an asset.
 * Sets components.installed_asset = strval($assetId) for attached components,
 * sets status = 'Installed', and detaches any components no longer attached.
 */
function syncAssetComponents($conn, $assetId, $assetTag = '', $assetName = '', $componentsList = []) {
    if (!isset($conn) || $conn === false || empty($assetId)) {
        return;
    }

    $assetIdStr = strval($assetId);
    $locationStr = 'Installed in ' . ($assetTag ?: ('Asset #' . $assetIdStr)) . ($assetName ? ' (' . $assetName . ')' : '');

    // 1. Identify which component IDs should be linked
    $matchedComponentIds = [];

    if (is_array($componentsList)) {
        foreach ($componentsList as $c) {
            $matchedId = null;

            // Priority 1: Match by explicit component_id / id provided
            $cIdToCheck = !empty($c['component_id']) ? $c['component_id'] : (!empty($c['id']) && is_numeric($c['id']) && intval($c['id']) < 100000000 ? $c['id'] : null);
            if (!empty($cIdToCheck)) {
                $cid = intval($cIdToCheck);
                $chk = sqlsrv_query($conn, "SELECT id FROM components WHERE id = ?", [$cid]);
                if ($chk && ($cRow = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) {
                    $matchedId = intval($cRow['id']);
                }
                if ($chk) sqlsrv_free_stmt($chk);
            }

            // Priority 2: Match by unique serial number
            if (!$matchedId && !empty($c['serial'])) {
                $sn = trim($c['serial']);
                $chk = sqlsrv_query($conn, "SELECT id FROM components WHERE serial = ?", [$sn]);
                if ($chk && ($cRow = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) {
                    $matchedId = intval($cRow['id']);
                }
                if ($chk) sqlsrv_free_stmt($chk);
            }

            // Priority 3: Match by SKU / tag
            $tagOrSku = trim($c['tag'] ?? ($c['sku'] ?? ''));
            if (!$matchedId && !empty($tagOrSku)) {
                $chk = sqlsrv_query($conn, "SELECT id FROM components WHERE sku = ?", [$tagOrSku]);
                if ($chk && ($cRow = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) {
                    $matchedId = intval($cRow['id']);
                }
                if ($chk) sqlsrv_free_stmt($chk);
            }

            // Priority 4: Match by component name if Available or already installed on this asset
            if (!$matchedId && !empty($c['name'])) {
                $cname = trim($c['name']);
                $chk = sqlsrv_query($conn, "SELECT TOP 1 id FROM components WHERE name = ? AND (installed_asset IS NULL OR installed_asset = '' OR installed_asset = ? OR installed_asset = ?) ORDER BY id ASC", [$cname, $assetIdStr, $assetTag]);
                if ($chk && ($cRow = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC))) {
                    $matchedId = intval($cRow['id']);
                }
                if ($chk) sqlsrv_free_stmt($chk);
            }

            if ($matchedId && !in_array($matchedId, $matchedComponentIds, true)) {
                $matchedComponentIds[] = $matchedId;
            }
        }
    }

    // 2. Detach components previously linked to this asset that are not in $matchedComponentIds
    $detachParams = [$assetIdStr];
    $detachSql = "UPDATE components 
                  SET installed_asset = '', 
                      status = 'Available', 
                      location = 'Depot Shelf (Unassigned)', 
                      updated_at = GETDATE() 
                  WHERE (installed_asset = ?";
    if (!empty($assetTag)) {
        $detachSql .= " OR installed_asset = ?";
        $detachParams[] = $assetTag;
    }
    $detachSql .= ")";

    if (!empty($matchedComponentIds)) {
        $placeholders = implode(',', array_fill(0, count($matchedComponentIds), '?'));
        $detachSql .= " AND id NOT IN ($placeholders)";
        $detachParams = array_merge($detachParams, $matchedComponentIds);
    }

    sqlsrv_query($conn, $detachSql, $detachParams);

    // 3. Link all matched components to this asset ID in installed_asset column
    if (!empty($matchedComponentIds)) {
        $linkPlaceholders = implode(',', array_fill(0, count($matchedComponentIds), '?'));
        $linkSql = "UPDATE components 
                    SET installed_asset = ?, 
                        status = 'Installed', 
                        location = ?, 
                        updated_at = GETDATE() 
                    WHERE id IN ($linkPlaceholders)";
        $linkParams = array_merge([$assetIdStr, $locationStr], $matchedComponentIds);
        sqlsrv_query($conn, $linkSql, $linkParams);
    }
}

/**
 * Format a DB row to the rich frontend Asset object
 */
function getEmployeeLookup() {
    global $conn;
    static $lookup = null;
    if ($lookup !== null) return $lookup;
    $lookup = [];
    if (isset($conn) && $conn !== false) {
        $stmt = sqlsrv_query($conn, "SELECT e.emp_code, e.first_name, e.last_name, e.email, e.designation, d.name AS department_name
                                      FROM employees e
                                      LEFT JOIN departments d ON e.department_id = d.id");
        if ($stmt !== false) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $fullName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                $data = [
                    'name'        => $fullName,
                    'empCode'     => $r['emp_code'] ?? '',
                    'email'       => $r['email'] ?? '',
                    'role'        => $r['designation'] ?? 'Team Member',
                    'department'  => $r['department_name'] ?? ''
                ];
                if (!empty($fullName)) $lookup[strtolower($fullName)] = $data;
                if (!empty($r['emp_code'])) $lookup[strtolower($r['emp_code'])] = $data;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
    return $lookup;
}

function formatAssetRow($row) {
    $assignedTo = null;
    $assignedVal = !empty($row['assigned_to']) ? trim($row['assigned_to']) : '';
    if (!empty($assignedVal)) {
        $empLookup = getEmployeeLookup();
        $key = strtolower($assignedVal);
        if (!empty($empLookup[$key])) {
            $e = $empLookup[$key];
            $assignedTo = [
                'name'         => $e['name'],
                'empCode'      => $e['empCode'],
                'email'        => $e['email'],
                'department'   => $row['department'] ?: ($e['department'] ?? ''),
                'role'         => $e['role'] ?: 'Team Member',
                'assignedDate' => !empty($row['updated_at']) && $row['updated_at'] instanceof DateTime ? $row['updated_at']->format('d M Y') : 'Active'
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

    $components = [];
    $assetIdStr = strval($row['id'] ?? '');
    $assetTagStr = strval($row['tag'] ?? '');

    // Fetch linked components directly from components table
    global $conn;
    if (!empty($assetIdStr) && isset($conn) && $conn !== false) {
        $cQuery = sqlsrv_query($conn, "SELECT id, sku, serial, name, category, brand, model, specs, status FROM components WHERE installed_asset = ? OR installed_asset = ?", [$assetIdStr, $assetTagStr]);
        if ($cQuery !== false) {
            while ($cr = sqlsrv_fetch_array($cQuery, SQLSRV_FETCH_ASSOC)) {
                $components[] = [
                    'component_id' => intval($cr['id']),
                    'id'           => intval($cr['id']),
                    'name'         => $cr['name'] ?? '',
                    'serial'       => $cr['serial'] ?? '',
                    'tag'          => $cr['sku'] ?? '',
                    'sku'          => $cr['sku'] ?? '',
                    'category'     => $cr['category'] ?? '',
                    'brand'        => $cr['brand'] ?? '',
                    'model'        => $cr['model'] ?? '',
                    'specs'        => $cr['specs'] ?? '',
                    'status'       => $cr['status'] ?? 'Installed'
                ];
            }
            sqlsrv_free_stmt($cQuery);
        }
    }

    // Merge any non-duplicate components from components_json
    if (!empty($row['components_json'])) {
        $decoded = json_decode($row['components_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $alreadyExists = false;
                foreach ($components as $cExisting) {
                    if ((!empty($item['serial']) && $cExisting['serial'] === $item['serial']) ||
                        (!empty($item['component_id']) && $cExisting['component_id'] === $item['component_id'])) {
                        $alreadyExists = true;
                        break;
                    }
                }
                if (!$alreadyExists) {
                    $components[] = $item;
                }
            }
        }
    }

    $history = [];
    $tickets = [];

    return [
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

/**
 * Send raw ZPL content directly to a named Windows printer queue via winspool.drv
 */
function sendZplToPrinter($printerName, $zplContent, $docName = 'Asset Label') {
    if (empty($printerName)) {
        return ['success' => false, 'message' => 'Printer name is required.'];
    }

    if (empty($zplContent)) {
        return ['success' => false, 'message' => 'ZPL print data is empty.'];
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'viros_zpl_') . '.prn';
    file_put_contents($tempFile, $zplContent);

    $psScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'send_to_printer.ps1';
    if (!file_exists($psScript)) {
        @unlink($tempFile);
        return ['success' => false, 'message' => 'Printer communication script (send_to_printer.ps1) missing.'];
    }

    $escapedPrinter = str_replace('"', '`"', $printerName);
    $escapedDocName = str_replace('"', '', $docName);
    $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File "' . $psScript . '" -PrinterName "' . $escapedPrinter . '" -ZplFilePath "' . $tempFile . '" -DocName "' . $escapedDocName . '"';

    $output = [];
    $ret = 0;
    @exec($cmd, $output, $ret);

    @unlink($tempFile);

    $outText = trim(implode("\n", $output));
    if ($ret === 0 && strpos($outText, 'SUCCESS') !== false) {
        return [
            'success' => true,
            'message' => "Label sent successfully to {$printerName}."
        ];
    }

    return [
        'success' => false,
        'message' => "Could not send to printer '{$printerName}': " . ($outText ?: 'Check if printer is powered on and connected.')
    ];
}

/**
 * Fetch installed Windows printers on the host system (pure printer names only)
 */
function getSystemPrintersList($forceRefresh = false) {
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'viros_system_printers.json';
    $printers = null;

    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached) {
            $printers = json_decode($cached, true);
        }
    }

    if (!is_array($printers) || empty($printers)) {
        $printers = [];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Printer | Select-Object Name | ConvertTo-Json"';
            $output = [];
            @exec($cmd, $output);
            $json = implode('', $output);
            $data = json_decode($json, true);
            if ($data) {
                if (isset($data['Name'])) {
                    $data = [$data];
                }
                foreach ($data as $p) {
                    if (!empty($p['Name'])) {
                        $pName = trim($p['Name']);
                        $printers[] = [
                            'name' => $pName
                        ];
                    }
                }
            }
        }
        if (!empty($printers)) {
            @file_put_contents($cacheFile, json_encode($printers));
        }
    }

    return is_array($printers) ? $printers : [];
}

// =============================================================================
// ROUTE: GET (Fetch assets + stats or system printers)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'get_system_printers') {
        $refresh = !empty($_GET['refresh']);
        echo json_encode([
            'success'  => true,
            'printers' => getSystemPrintersList($refresh)
        ]);
        exit;
    }

    $where = [];
    $params = [];

    if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
        $where[] = "category = ?";
        $params[] = $_GET['category'];
    }

    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $statusMap = [
            'in-use'      => 'In Use',
            'available'   => 'Available',
            'maintenance' => 'Under Maintenance',
            'reserved'    => 'Reserved',
            'retired'     => 'Retired'
        ];
        $st = $statusMap[strtolower($_GET['status'])] ?? $_GET['status'];
        $where[] = "status = ?";
        $params[] = $st;
    }

    if (!empty($_GET['department']) && $_GET['department'] !== 'all') {
        $where[] = "department = ?";
        $params[] = $_GET['department'];
    }

    if (!empty($_GET['location']) && $_GET['location'] !== 'all') {
        $where[] = "location = ?";
        $params[] = $_GET['location'];
    }

    if (!empty($_GET['condition']) && $_GET['condition'] !== 'all') {
        $where[] = "condition = ?";
        $params[] = $_GET['condition'];
    }

    if (!empty($_GET['search'])) {
        $q = '%' . trim($_GET['search']) . '%';
        $where[] = "(tag LIKE ? OR name LIKE ? OR serial LIKE ? OR brand LIKE ? OR model LIKE ? OR assigned_to LIKE ? OR department LIKE ?)";
        array_push($params, $q, $q, $q, $q, $q, $q, $q);
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT * FROM assets $whereSql ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch assets.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $assets = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $assets[] = formatAssetRow($row);
    }
    sqlsrv_free_stmt($stmt);

    // Compute live global KPI statistics
    $statSql = "SELECT 
        COUNT(*) AS total_assets,
        SUM(CASE WHEN status = 'In Use' THEN 1 ELSE 0 END) AS in_use,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) AS maintenance,
        SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) AS reserved,
        SUM(CASE WHEN status = 'Retired' THEN 1 ELSE 0 END) AS retired,
        SUM(ISNULL(cost, 0)) AS total_value
    FROM assets";

    $statStmt = sqlsrv_query($conn, $statSql);
    $stats = [
        'total'          => count($assets),
        'in_use'         => 0,
        'available'      => 0,
        'maintenance'    => 0,
        'reserved'       => 0,
        'retired'        => 0,
        'expiring_soon'  => 0,
        'total_value'    => 0
    ];

    if ($statStmt !== false) {
        if ($sRow = sqlsrv_fetch_array($statStmt, SQLSRV_FETCH_ASSOC)) {
            $stats['total']       = intval($sRow['total_assets'] ?? 0);
            $stats['in_use']      = intval($sRow['in_use'] ?? 0);
            $stats['available']   = intval($sRow['available'] ?? 0);
            $stats['maintenance'] = intval($sRow['maintenance'] ?? 0);
            $stats['reserved']    = intval($sRow['reserved'] ?? 0);
            $stats['retired']     = intval($sRow['retired'] ?? 0);
            $stats['total_value'] = floatval($sRow['total_value'] ?? 0);
        }
        sqlsrv_free_stmt($statStmt);
    }

    // Expiring within 30 days calculation
    $expStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS expiring FROM assets WHERE warranty_expiry IS NOT NULL AND warranty_expiry <> '' AND TRY_CAST(warranty_expiry AS DATE) >= CAST(GETDATE() AS DATE) AND TRY_CAST(warranty_expiry AS DATE) <= DATEADD(day, 30, CAST(GETDATE() AS DATE))");
    if ($expStmt !== false) {
        if ($eRow = sqlsrv_fetch_array($expStmt, SQLSRV_FETCH_ASSOC)) {
            $stats['expiring_soon'] = intval($eRow['expiring'] ?? 0);
        }
        sqlsrv_free_stmt($expStmt);
    }

    echo json_encode([
        'success'  => true,
        'assets'   => $assets,
        'stats'    => $stats,
        'next_tag' => getNextAssetTag($conn)
    ]);
    exit;
}

// =============================================================================
// ROUTE: POST (Create, Edit, Delete, Reassign, Bulk Operations)
// =============================================================================
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------------------------
    // ACTION: get_next_tag
    // -------------------------------------------------------------------------
    case 'get_next_tag':
        echo json_encode([
            'success'  => true,
            'next_tag' => getNextAssetTag($conn)
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: get_system_printers
    // -------------------------------------------------------------------------
    case 'get_system_printers':
        $refresh = !empty($input['refresh']);
        echo json_encode([
            'success'  => true,
            'printers' => getSystemPrintersList($refresh)
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: generate_zpl
    // -------------------------------------------------------------------------
    case 'generate_zpl':
        $tag = trim($input['tag'] ?? '');
        $serial = trim($input['serial'] ?? '');
        $name = trim($input['name'] ?? '');
        $copies = intval($input['copies'] ?? 1);
        if ($copies < 1) $copies = 1;

        $templateFile = __DIR__ . '/../lablel/assetlable.txt';
        $zpl = "";
        if (file_exists($templateFile)) {
            $zpl = file_get_contents($templateFile);
            $zpl = str_replace('#Fortune Marketing Private Limited#', 'Fortune Marketing Private Limited', $zpl);
            $zpl = str_replace('#Asset Code#', $tag, $zpl);
            $zpl = str_replace('#Serial#', $serial, $zpl);
            $zpl = str_replace('#Asset Name#', $name, $zpl);
            $zpl = str_replace('#Code#', $tag, $zpl);
            $zpl = str_replace('^PQ1,0,1,Y', '^PQ' . $copies . ',0,1,Y', $zpl);
        }
        echo json_encode([
            'success' => true,
            'zpl'     => $zpl
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: print_label (Send formatted assetlable.txt directly to Windows printer)
    // -------------------------------------------------------------------------
    case 'print_label':
        $printerName = trim($input['printer_name'] ?? '');
        $tag = trim($input['tag'] ?? '');
        $serial = trim($input['serial'] ?? '');
        $name = trim($input['name'] ?? '');
        $copies = intval($input['copies'] ?? 1);
        if ($copies < 1) $copies = 1;

        if (empty($printerName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a printer to print the label.']);
            exit;
        }

        // If user explicitly chooses browser default / virtual print
        if ($printerName === 'system_default') {
            echo json_encode(['success' => false, 'fallback_browser' => true, 'message' => 'Use browser print for Default System Printer.']);
            exit;
        }

        $templateFile = __DIR__ . '/../lablel/assetlable.txt';
        if (!file_exists($templateFile)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Label template file (lablel/assetlable.txt) not found.']);
            exit;
        }

        $zpl = file_get_contents($templateFile);
        $zpl = str_replace('#Fortune Marketing Private Limited#', 'Fortune Marketing Private Limited', $zpl);
        $zpl = str_replace('#Asset Code#', $tag, $zpl);
        $zpl = str_replace('#Serial#', $serial, $zpl);
        $zpl = str_replace('#Asset Name#', $name, $zpl);
        $zpl = str_replace('#Code#', $tag, $zpl);
        $zpl = str_replace('^PQ1,0,1,Y', '^PQ' . $copies . ',0,1,Y', $zpl);

        $res = sendZplToPrinter($printerName, $zpl, "Asset Label " . $tag);
        echo json_encode($res);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: create
    // -------------------------------------------------------------------------
    case 'create':
        $tag = trim($input['tag'] ?? '');
        if (empty($tag)) {
            $tag = getNextAssetTag($conn);
        }

        $name = trim($input['name'] ?? '');
        $serial = trim($input['serial'] ?? '');
        $category = trim($input['category'] ?? 'General');
        $brand = trim($input['brand'] ?? '');
        $model = trim($input['model'] ?? '');
        $status = trim($input['status'] ?? 'Available');
        $condition = trim($input['condition'] ?? 'Good');
        $location = trim($input['location'] ?? '');
        $department = trim($input['department'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Asset Name is required.']);
            exit;
        }

        // Specs
        $specs = $input['specs'] ?? [];
        $processor = trim($specs['processor'] ?? ($input['processor'] ?? ''));
        $ram = trim($specs['ram'] ?? ($input['ram'] ?? ''));
        $storage = trim($specs['storage'] ?? ($input['storage'] ?? ''));
        $os = trim($specs['os'] ?? ($input['os'] ?? ''));
        $mac = trim($specs['macAddress'] ?? ($input['mac_address'] ?? ''));
        $ip = trim($specs['ipAddress'] ?? ($input['ip_address'] ?? ''));

        // Financials
        $fin = $input['financials'] ?? [];
        $vendor = trim($fin['vendor'] ?? ($input['vendor'] ?? ''));
        $poNumber = trim($fin['poNumber'] ?? ($input['po_number'] ?? ''));
        $purchaseDate = trim($fin['purchaseDate'] ?? ($input['purchase_date'] ?? ''));
        $cost = floatval($fin['cost'] ?? ($input['cost'] ?? 0));
        $warrantyExpiry = trim($fin['warrantyExpiry'] ?? ($input['warranty_expiry'] ?? ''));

        // Components & History JSON
        $components = $input['components'] ?? [];
        $componentsJson = !empty($components) ? json_encode($components) : null;

        $insertSql = "INSERT INTO assets (
            tag, name, category, brand, model, serial, status, condition, location, department,
            processor, ram, storage, os, mac_address, ip_address,
            vendor, po_number, purchase_date, cost, warranty_expiry,
            components_json, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

        $params = [
            $tag, $name, $category, $brand, $model, $serial, $status, $condition, $location, $department,
            $processor, $ram, $storage, $os, $mac, $ip,
            $vendor, $poNumber, $purchaseDate, $cost, $warrantyExpiry,
            $componentsJson
        ];

        $stmt = sqlsrv_query($conn, $insertSql, $params);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save asset into database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        $newId = getLastInsertId($conn);

        // Link installed components to this asset ID in components table
        syncAssetComponents($conn, $newId, $tag, $name, $components);

        // Fetch inserted row to return
        $fetchStmt = sqlsrv_query($conn, "SELECT * FROM assets WHERE id = ?", [$newId]);
        $createdAsset = null;
        if ($fetchStmt !== false) {
            if ($row = sqlsrv_fetch_array($fetchStmt, SQLSRV_FETCH_ASSOC)) {
                $createdAsset = formatAssetRow($row);
            }
            sqlsrv_free_stmt($fetchStmt);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Asset registered successfully in database.',
            'asset'   => $createdAsset
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: edit
    // -------------------------------------------------------------------------
    case 'edit':
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid asset ID.']);
            exit;
        }

        $tag = trim($input['tag'] ?? '');
        $name = trim($input['name'] ?? '');
        $serial = trim($input['serial'] ?? '');
        $category = trim($input['category'] ?? '');
        $brand = trim($input['brand'] ?? '');
        $model = trim($input['model'] ?? '');
        $status = trim($input['status'] ?? 'Available');
        $condition = trim($input['condition'] ?? 'Good');
        $location = trim($input['location'] ?? '');
        $department = trim($input['department'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Asset Name is required.']);
            exit;
        }

        // Specs
        $specs = $input['specs'] ?? [];
        $processor = trim($specs['processor'] ?? ($input['processor'] ?? ''));
        $ram = trim($specs['ram'] ?? ($input['ram'] ?? ''));
        $storage = trim($specs['storage'] ?? ($input['storage'] ?? ''));
        $os = trim($specs['os'] ?? ($input['os'] ?? ''));
        $mac = trim($specs['macAddress'] ?? ($input['mac_address'] ?? ''));
        $ip = trim($specs['ipAddress'] ?? ($input['ip_address'] ?? ''));

        // Financials
        $fin = $input['financials'] ?? [];
        $vendor = trim($fin['vendor'] ?? ($input['vendor'] ?? ''));
        $poNumber = trim($fin['poNumber'] ?? ($input['po_number'] ?? ''));
        $purchaseDate = trim($fin['purchaseDate'] ?? ($input['purchase_date'] ?? ''));
        $cost = floatval($fin['cost'] ?? ($input['cost'] ?? 0));
        $warrantyExpiry = trim($fin['warrantyExpiry'] ?? ($input['warranty_expiry'] ?? ''));

        $components = $input['components'] ?? [];
        $componentsJson = !empty($components) ? json_encode($components) : null;

        $updateSql = "UPDATE assets SET 
            tag = ?, name = ?, category = ?, brand = ?, model = ?, serial = ?,
            status = ?, condition = ?, location = ?, department = ?,
            processor = ?, ram = ?, storage = ?, os = ?, mac_address = ?, ip_address = ?,
            vendor = ?, po_number = ?, purchase_date = ?, cost = ?, warranty_expiry = ?,
            components_json = ?, updated_at = GETDATE()
        WHERE id = ?";

        $params = [
            $tag, $name, $category, $brand, $model, $serial,
            $status, $condition, $location, $department,
            $processor, $ram, $storage, $os, $mac, $ip,
            $vendor, $poNumber, $purchaseDate, $cost, $warrantyExpiry,
            $componentsJson, $id
        ];

        $stmt = sqlsrv_query($conn, $updateSql, $params);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update asset in database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        // Link/sync installed components to this asset ID in components table
        syncAssetComponents($conn, $id, $tag, $name, $components);

        $fetchStmt = sqlsrv_query($conn, "SELECT * FROM assets WHERE id = ?", [$id]);
        $updatedAsset = null;
        if ($fetchStmt !== false && ($row = sqlsrv_fetch_array($fetchStmt, SQLSRV_FETCH_ASSOC))) {
            $updatedAsset = formatAssetRow($row);
            sqlsrv_free_stmt($fetchStmt);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Asset updated successfully.',
            'asset'   => $updatedAsset
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: reassign (Assign / Transfer Custody / Return to Stock)
    // -------------------------------------------------------------------------
    case 'reassign':
        $id = intval($input['id'] ?? 0);
        $actionType = $input['actionType'] ?? 'assign_employee';

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid asset ID.']);
            exit;
        }

        if ($actionType === 'return_to_stock') {
            $sql = "UPDATE assets SET 
                status = 'Available',
                assigned_to = NULL,
                updated_at = GETDATE()
            WHERE id = ?";
            sqlsrv_query($conn, $sql, [$id]);
        } else {
            $empName = trim($input['employeeName'] ?? '');
            $empDept = trim($input['department'] ?? '');

            if (empty($empName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Recipient employee name is required.']);
                exit;
            }

            $sql = "UPDATE assets SET 
                status = 'In Use',
                department = CASE WHEN ? != '' THEN ? ELSE department END,
                assigned_to = ?,
                updated_at = GETDATE()
            WHERE id = ?";

            $params = [
                $empDept, $empDept,
                $empName,
                $id
            ];
            sqlsrv_query($conn, $sql, $params);
        }

        $fetchStmt = sqlsrv_query($conn, "SELECT * FROM assets WHERE id = ?", [$id]);
        $updatedAsset = null;
        if ($fetchStmt !== false && ($row = sqlsrv_fetch_array($fetchStmt, SQLSRV_FETCH_ASSOC))) {
            $updatedAsset = formatAssetRow($row);
            sqlsrv_free_stmt($fetchStmt);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Asset assignment updated successfully.',
            'asset'   => $updatedAsset
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: delete (or retire)
    // -------------------------------------------------------------------------
    case 'delete':
        $id = intval($input['id'] ?? 0);
        $hardDelete = !isset($input['hard']) || !empty($input['hard']); // Default to complete delete

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid asset ID.']);
            exit;
        }

        // Fetch asset tag, name and components_json
        $aStmt = sqlsrv_query($conn, "SELECT id, tag, name, components_json FROM assets WHERE id = ?", [$id]);
        $assetTag = '';
        $assetName = '';
        $componentsJson = '';
        if ($aStmt && ($aRow = sqlsrv_fetch_array($aStmt, SQLSRV_FETCH_ASSOC))) {
            $assetTag = $aRow['tag'] ?? '';
            $assetName = $aRow['name'] ?? '';
            $componentsJson = $aRow['components_json'] ?? '';
            sqlsrv_free_stmt($aStmt);
        }

        // 1. Release all attached components in components table to UNUSED / AVAILABLE
        $updCompSql = "UPDATE components 
                       SET installed_asset = '', 
                           status = 'Available', 
                           location = 'Storage Depot (Unassigned)', 
                           updated_at = GETDATE() 
                       WHERE installed_asset = ?";
        $updCompParams = [strval($id)];
        if (!empty($assetTag)) {
            $updCompSql .= " OR installed_asset = ?";
            $updCompParams[] = $assetTag;
        }
        sqlsrv_query($conn, $updCompSql, $updCompParams);

        // Also release by IDs/serials from components_json if any
        if (!empty($componentsJson)) {
            $cList = json_decode($componentsJson, true);
            if (is_array($cList)) {
                foreach ($cList as $ci) {
                    $cId = !empty($ci['component_id']) ? intval($ci['component_id']) : (!empty($ci['id']) && is_numeric($ci['id']) && intval($ci['id']) < 100000000 ? intval($ci['id']) : 0);
                    if ($cId > 0) {
                        sqlsrv_query($conn, "UPDATE components SET installed_asset = '', status = 'Available', location = 'Storage Depot (Unassigned)', updated_at = GETDATE() WHERE id = ?", [$cId]);
                    } else if (!empty($ci['serial'])) {
                        sqlsrv_query($conn, "UPDATE components SET installed_asset = '', status = 'Available', location = 'Storage Depot (Unassigned)', updated_at = GETDATE() WHERE serial = ?", [trim($ci['serial'])]);
                    }
                }
            }
        }

        if ($hardDelete) {
            $stmt = sqlsrv_query($conn, "DELETE FROM assets WHERE id = ?", [$id]);
        } else {
            // Soft delete: mark Retired
            $stmt = sqlsrv_query($conn, "UPDATE assets SET status = 'Retired', assigned_to = NULL, updated_at = GETDATE() WHERE id = ?", [$id]);
        }

        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete asset from database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Asset completely deleted and all attached components released to available inventory.'
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: bulk_status
    // -------------------------------------------------------------------------
    case 'bulk_status':
        $ids = $input['ids'] ?? [];
        $newStatus = trim($input['status'] ?? '');

        if (!is_array($ids) || empty($ids) || empty($newStatus)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid IDs or status for bulk update.']);
            exit;
        }

        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        if ($newStatus === 'Available' || $newStatus === 'Retired') {
            $sql = "UPDATE assets SET status = ?, assigned_to = NULL, updated_at = GETDATE() WHERE id IN ($idPlaceholders)";
        } else {
            $sql = "UPDATE assets SET status = ?, updated_at = GETDATE() WHERE id IN ($idPlaceholders)";
        }

        $params = array_merge([$newStatus], array_map('intval', $ids));
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update bulk status.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Updated ' . count($ids) . ' assets to "' . $newStatus . '"'
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: bulk_delete
    // -------------------------------------------------------------------------
    case 'bulk_delete':
        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid asset IDs for bulk delete.']);
            exit;
        }

        $idInts = array_map('intval', $ids);
        $idStrs = array_map('strval', $idInts);
        $placeholders = implode(',', array_fill(0, count($idInts), '?'));

        // Get tags and components_json
        $tags = [];
        $tStmt = sqlsrv_query($conn, "SELECT tag, components_json FROM assets WHERE id IN ($placeholders)", $idInts);
        if ($tStmt) {
            while ($tr = sqlsrv_fetch_array($tStmt, SQLSRV_FETCH_ASSOC)) {
                if (!empty($tr['tag'])) $tags[] = $tr['tag'];
                if (!empty($tr['components_json'])) {
                    $cList = json_decode($tr['components_json'], true);
                    if (is_array($cList)) {
                        foreach ($cList as $ci) {
                            $cId = !empty($ci['component_id']) ? intval($ci['component_id']) : (!empty($ci['id']) && is_numeric($ci['id']) && intval($ci['id']) < 100000000 ? intval($ci['id']) : 0);
                            if ($cId > 0) {
                                sqlsrv_query($conn, "UPDATE components SET installed_asset = '', status = 'Available', location = 'Storage Depot (Unassigned)', updated_at = GETDATE() WHERE id = ?", [$cId]);
                            } else if (!empty($ci['serial'])) {
                                sqlsrv_query($conn, "UPDATE components SET installed_asset = '', status = 'Available', location = 'Storage Depot (Unassigned)', updated_at = GETDATE() WHERE serial = ?", [trim($ci['serial'])]);
                            }
                        }
                    }
                }
            }
            sqlsrv_free_stmt($tStmt);
        }

        // Release all attached components to Available
        $allRefStrs = array_unique(array_merge($idStrs, $tags));
        if (!empty($allRefStrs)) {
            $cPlaceholders = implode(',', array_fill(0, count($allRefStrs), '?'));
            sqlsrv_query($conn, "UPDATE components SET installed_asset = '', status = 'Available', location = 'Storage Depot (Unassigned)', updated_at = GETDATE() WHERE installed_asset IN ($cPlaceholders)", $allRefStrs);
        }

        // Delete assets completely
        $delStmt = sqlsrv_query($conn, "DELETE FROM assets WHERE id IN ($placeholders)", $idInts);
        if ($delStmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete assets from database.', 'errors' => sqlsrv_errors()]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Successfully deleted ' . count($idInts) . ' asset(s) and released all attached components.'
        ]);
        exit;

    // -------------------------------------------------------------------------
    // ACTION: import (Bulk Insert from CSV)
    // -------------------------------------------------------------------------
    case 'import':
        $items = $input['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No asset records provided for import.']);
            exit;
        }

        $insertedCount = 0;
        foreach ($items as $item) {
            $tag = trim($item['tag'] ?? '');
            if (empty($tag)) {
                $tag = getNextAssetTag($conn);
            }
            $name = trim($item['name'] ?? '');
            $serial = trim($item['serial'] ?? '');
            if (empty($name) || empty($serial)) continue;

            $category = trim($item['category'] ?? 'General');
            $brand = trim($item['brand'] ?? '');
            $model = trim($item['model'] ?? '');
            $status = trim($item['status'] ?? 'Available');
            $condition = trim($item['condition'] ?? 'Good');
            $location = trim($item['location'] ?? '');
            $department = trim($item['department'] ?? '');
            $cost = floatval($item['cost'] ?? 0);
            $warranty = trim($item['warrantyExpiry'] ?? '');

            $insertSql = "INSERT INTO assets (
                tag, name, category, brand, model, serial, status, condition, location, department,
                cost, warranty_expiry, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

            $res = sqlsrv_query($conn, $insertSql, [
                $tag, $name, $category, $brand, $model, $serial, $status, $condition, $location, $department,
                $cost, $warranty
            ]);

            if ($res !== false) {
                $insertedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully imported $insertedCount asset(s) into database.",
            'count'   => $insertedCount
        ]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        exit;
}
