<?php
/**
 * Discover Pakistan UHD TV - Duty Roster Portal
 * Dedicated Page for Shift Scheduling, Monthly Rosters & Staff Assignments
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPage = 'roster';

// Page-specific script loading
$pageScripts = ['assets/js/roster.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Duty Roster Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_roster.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
