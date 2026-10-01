<?php
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
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/toast.css">
    
    <?php if (isset($extra_css) && is_array($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<div class="app-container">
