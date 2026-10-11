<?php
/**
 * Database Configuration (MSSQL Server)
 * Asset Management & IT Service Desk Portal
 * 
 * Uses native Microsoft SQL Server Driver (sqlsrv) - No PDO.
 */

// SQL Server connection settings
$serverName = "MSI\\SQLEXPRESS";
$port = 1433;
$connectionOptions = [
    "Database"               => "asset_helpdesk",
    "Uid"                    => "",
    "PWD"                    => "",
    "TrustServerCertificate" => true, // SSL / Self-signed certificate bypass
    "CharacterSet"           => "UTF-8",
    "LoginTimeout"           => 30,
    "ConnectRetryCount"      => 3
];

// Make connection variables globally accessible
global $serverName, $connectionOptions, $port, $conn;

// Create connection using native sqlsrv functions
// For named instances like SQLEXPRESS, try without port first
$conn = sqlsrv_connect($serverName, $connectionOptions);

// If initial connection fails, try with port appended
if ($conn === false) {
    $serverWithPort = $serverName . "," . $port;
    $conn = sqlsrv_connect($serverWithPort, $connectionOptions);
}

// Check connection status
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

/**
 * Get the native SQL Server connection resource.
 *
 * @return resource|false
 */
function getDB() {
    global $conn;
    return $conn;
}

/**
 * Alternative function name for getting the database connection.
 *
 * @return resource|false
 */
function getSQLSrvConnection() {
    global $conn;
    return $conn;
}

/**
 * Helper function for SQL Server specific date formatting.
 *
 * @param DateTime|string $date
 * @return string
 */
function formatDateForSQLServer($date) {
    if ($date instanceof DateTime) {
        return $date->format('Y-m-d H:i:s');
    }
    return $date;
}

/**
 * Helper function to retrieve the last inserted IDENTITY ID in SQL Server.
 *
 * @param resource|null $connection Optional sqlsrv connection resource
 * @return int|string|false
 */
function getLastInsertId($connection = null) {
    if ($connection === null) {
        global $conn;
        $connection = $conn;
    }

    $stmt = sqlsrv_query($connection, "SELECT @@IDENTITY AS last_id");
    if ($stmt === false) {
        return false;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return $row['last_id'] ?? false;
}

/**
 * Helper function to retrieve Organization Settings (Legal name, branding logo, favicon, etc.).
 * Cached per request for optimum performance across components.
 *
 * @param resource|null $connection Optional sqlsrv connection resource
 * @return array
 */
function getOrganizationSettings($connection = null) {
    static $cachedSettings = null;
    if ($cachedSettings !== null) {
        return $cachedSettings;
    }

    if ($connection === null) {
        global $conn;
        $connection = $conn;
    }

    $defaultSettings = [
        'organization_legal_name' => 'VIROS PORTAL',
        'support_desk_email'      => 'it@virosentrepreneurs.com',
        'it_helpdesk_helpline'    => '',
        'primary_brand_logo'      => 'assets/images/logo.png',
        'browser_favicon'         => 'assets/images/logo.png'
    ];

    if (!$connection) {
        $cachedSettings = $defaultSettings;
        return $cachedSettings;
    }

    $stmt = @sqlsrv_query($connection, "SELECT TOP 1 organization_legal_name, support_desk_email, it_helpdesk_helpline, primary_brand_logo, browser_favicon FROM organization_settings ORDER BY id ASC");
    if ($stmt !== false && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        foreach ($defaultSettings as $k => $v) {
            if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
                $defaultSettings[$k] = trim((string)$row[$k]);
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    $cachedSettings = $defaultSettings;
    return $cachedSettings;
}
?>
