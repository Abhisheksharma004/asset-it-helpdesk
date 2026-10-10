<?php
// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    $page_title = "VIROS Portal - Asset Management & IT Service Desk";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
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
