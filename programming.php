<?php
/**
 * Discover Pakistan UHD TV - Programming & Broadcast Operations Portal
 * Dedicated Handover Pipeline, Broadcast Media Dispatch, and Multi-Channel Digital Publishing
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPortal = 'programming';

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar (Configured for Programming Workspace with Portal Switcher)
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Programming Application Workspace Container -->
<main class="main-content">
    <?php
    // Core Programming Handover Matrix View
    require_once __DIR__ . '/views/tab_programming.php';
    // Supporting Views (Integrated Staff Attendance & Employee Directory)
    require_once __DIR__ . '/views/tab_attendance.php';
    require_once __DIR__ . '/views/tab_employees.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Page Scripts & Dynamic Loaders
require_once __DIR__ . '/includes/footer.php';
?>
