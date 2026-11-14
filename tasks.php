<?php
/**
 * Discover Pakistan UHD TV - Task Command Center
 * Dedicated Page for Task Assignment, Monitoring & Completion Tracking
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPage = 'tasks';
$currentPortal = 'digital';

// Page-specific script loading
$pageScripts = ['assets/js/tasks.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Task Command Center Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_tasks.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
