<?php
/**
 * Discover Pakistan UHD TV - Matrix Reports Portal
 * Dedicated Page for Department Performance & Timesheet Analytics
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPage = 'reports';

// Page-specific script loading
$pageScripts = ['assets/js/reports.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Matrix Reports Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_reports.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
