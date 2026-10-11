<?php
/**
 * Organization Settings API
 * Asset Management & IT Service Desk Portal
 *
 * Supports:
 * - GET: Retrieve current organization legal details and branding assets
 * - POST: Update organization legal name, support email, helpline phone, logo, and favicon
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication (allow preview parameter for testing/staging)
if (empty($_SESSION['logged_in']) && !isset($_GET['preview']) && !isset($_POST['preview'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Auto-create table if not exists with the exact fields
$tableSetupSql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='organization_settings' AND xtype='U')
BEGIN
    CREATE TABLE organization_settings (
        id INT IDENTITY(1,1) PRIMARY KEY,
        organization_legal_name NVARCHAR(255) NULL,
        support_desk_email NVARCHAR(255) NULL,
        it_helpdesk_helpline NVARCHAR(100) NULL,
        primary_brand_logo NVARCHAR(MAX) NULL,
        browser_favicon NVARCHAR(MAX) NULL
    );

    INSERT INTO organization_settings (
        organization_legal_name,
        support_desk_email,
        it_helpdesk_helpline,
        primary_brand_logo,
        browser_favicon
    ) VALUES (
        'Viros Technologies Pvt Ltd',
        'it-desk@viros.in',
        '+91 (020) 4123-8899',
        'assets/images/logo.png',
        'assets/images/logo.png'
    );
END

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='organization_setting' AND (xtype='U' OR xtype='V'))
BEGIN
    EXEC('CREATE VIEW organization_setting AS SELECT * FROM organization_settings');
END
";
sqlsrv_query($conn, $tableSetupSql);

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: Retrieve organization settings
if ($method === 'GET') {
    $sql = "SELECT TOP 1 id, organization_legal_name, support_desk_email, it_helpdesk_helpline, primary_brand_logo, browser_favicon FROM organization_settings ORDER BY id ASC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch settings from database.', 'errors' => sqlsrv_errors()]);
        exit;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    if (!$row) {
        $row = [
            'id' => 1,
            'organization_legal_name' => 'Viros Technologies Pvt Ltd',
            'support_desk_email' => 'it-desk@viros.in',
            'it_helpdesk_helpline' => '+91 (020) 4123-8899',
            'primary_brand_logo' => 'assets/images/logo.png',
            'browser_favicon' => 'assets/images/logo.png'
        ];
    }

    echo json_encode(['success' => true, 'data' => $row]);
    exit;
}

// Handle POST: Update organization settings
if ($method === 'POST') {
    // 1. Fetch current settings first
    $currentSql = "SELECT TOP 1 id, organization_legal_name, support_desk_email, it_helpdesk_helpline, primary_brand_logo, browser_favicon FROM organization_settings ORDER BY id ASC";
    $currStmt = sqlsrv_query($conn, $currentSql);
    $currentData = ($currStmt && ($cRow = sqlsrv_fetch_array($currStmt, SQLSRV_FETCH_ASSOC))) ? $cRow : null;
    if ($currStmt) sqlsrv_free_stmt($currStmt);

    // Read input fields
    $orgName = isset($_POST['organization_legal_name']) ? trim($_POST['organization_legal_name']) : ($currentData['organization_legal_name'] ?? 'Viros Technologies Pvt Ltd');
    $orgEmail = isset($_POST['support_desk_email']) ? trim($_POST['support_desk_email']) : ($currentData['support_desk_email'] ?? 'it-desk@viros.in');
    $orgPhone = isset($_POST['it_helpdesk_helpline']) ? trim($_POST['it_helpdesk_helpline']) : ($currentData['it_helpdesk_helpline'] ?? '+91 (020) 4123-8899');
    
    // Existing logos
    $logoPath = $currentData['primary_brand_logo'] ?? 'assets/images/logo.png';
    $faviconPath = $currentData['browser_favicon'] ?? 'assets/images/logo.png';

    // Check if reset requested
    if (!empty($_POST['reset_logo'])) {
        $logoPath = 'assets/images/logo.png';
    }
    if (!empty($_POST['reset_favicon'])) {
        $faviconPath = 'assets/images/logo.png';
    }

    // Direct path or base64 passed as text
    if (!empty($_POST['primary_brand_logo'])) {
        $logoPath = trim($_POST['primary_brand_logo']);
    }
    if (!empty($_POST['browser_favicon'])) {
        $faviconPath = trim($_POST['browser_favicon']);
    }

    // Prepare upload directory if files are uploaded
    $uploadDir = __DIR__ . '/../uploads/branding/';
    if (!file_exists($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $allowedExtensions = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'ico'];

    // Handle uploaded Primary Brand Logo file
    if (isset($_FILES['logo_file']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            $newFileName = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $destPath)) {
                $logoPath = 'uploads/branding/' . $newFileName;
            }
        }
    }

    // Handle uploaded Browser Favicon file
    if (isset($_FILES['favicon_file']) && is_uploaded_file($_FILES['favicon_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['favicon_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            $newFileName = 'favicon_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['favicon_file']['tmp_name'], $destPath)) {
                $faviconPath = 'uploads/branding/' . $newFileName;
            }
        }
    }

    // Update in database
    if ($currentData && !empty($currentData['id'])) {
        $updateSql = "UPDATE organization_settings SET 
            organization_legal_name = ?, 
            support_desk_email = ?, 
            it_helpdesk_helpline = ?, 
            primary_brand_logo = ?, 
            browser_favicon = ? 
            WHERE id = ?";
        $params = [$orgName, $orgEmail, $orgPhone, $logoPath, $faviconPath, $currentData['id']];
        $stmt = sqlsrv_query($conn, $updateSql, $params);
    } else {
        $insertSql = "INSERT INTO organization_settings (
            organization_legal_name, 
            support_desk_email, 
            it_helpdesk_helpline, 
            primary_brand_logo, 
            browser_favicon
        ) VALUES (?, ?, ?, ?, ?)";
        $params = [$orgName, $orgEmail, $orgPhone, $logoPath, $faviconPath];
        $stmt = sqlsrv_query($conn, $insertSql, $params);
    }

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to save organization settings in database.', 
            'errors' => sqlsrv_errors()
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Organization settings saved successfully.',
        'data' => [
            'organization_legal_name' => $orgName,
            'support_desk_email' => $orgEmail,
            'it_helpdesk_helpline' => $orgPhone,
            'primary_brand_logo' => $logoPath,
            'browser_favicon' => $faviconPath
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
