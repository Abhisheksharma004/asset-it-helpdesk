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
?>
