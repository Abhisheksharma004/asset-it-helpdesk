<?php
// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
$orgHeaderSettings = function_exists('getOrganizationSettings') ? getOrganizationSettings() : [];
$orgSiteName = !empty($orgHeaderSettings['organization_legal_name']) ? $orgHeaderSettings['organization_legal_name'] : 'VIROS Portal';
$orgFavicon = !empty($orgHeaderSettings['browser_favicon']) ? $orgHeaderSettings['browser_favicon'] : 'assets/images/logo.png';
if (!file_exists(__DIR__ . '/../' . $orgFavicon) && !filter_var($orgFavicon, FILTER_VALIDATE_URL)) {
    $orgFavicon = 'assets/images/logo.png';
}

/**
 * Global Header Component
 * Can be included in any page across the portal.
 *
 * Variables you can define before including:
 *   $page_title (string) - Custom browser tab title
 *   $extra_css (array)   - Optional additional CSS file paths
 */
if (!isset($page_title)) {
    $page_title = $orgSiteName . " - Asset Management & IT Service Desk";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($orgFavicon); ?>" type="image/x-icon">
    <script>
        window.ORG_SETTINGS = <?php echo json_encode($orgHeaderSettings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    
    <!-- Core Stylesheets -->
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo file_exists(__DIR__ . '/../css/dashboard.css') ? filemtime(__DIR__ . '/../css/dashboard.css') : time(); ?>">
    <link rel="stylesheet" href="css/toast.css?v=<?php echo file_exists(__DIR__ . '/../css/toast.css') ? filemtime(__DIR__ . '/../css/toast.css') : time(); ?>">
    <link rel="stylesheet" href="css/searchable-select.css?v=<?php echo file_exists(__DIR__ . '/../css/searchable-select.css') ? filemtime(__DIR__ . '/../css/searchable-select.css') : time(); ?>">
    
    <?php if (isset($extra_css) && is_array($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <?php $css_ver = file_exists(__DIR__ . '/../' . $css_file) ? filemtime(__DIR__ . '/../' . $css_file) : time(); ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file . '?v=' . $css_ver); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
<body <?php echo !empty($body_attributes) ? $body_attributes : ''; ?>>

<div class="app-container">
