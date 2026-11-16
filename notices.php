<?php
/**
 * Discover Pakistan UHD TV - Notice Board & Announcements Portal
 * Dedicated Workspace for Official Circulars, Policy Updates & Notices
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPage = 'notices';

// Page-specific script loading
$pageScripts = ['assets/js/hr.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Notice Board Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_hr.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
