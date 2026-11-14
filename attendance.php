<?php
/**
 * Discover Pakistan UHD TV - Staff Attendance & Biometric Portal
 * Dedicated Page for Live Shift Roster, Attendance Reports, WhatsApp & ZKTeco Streams
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPage = 'attendance';
$currentPortal = 'digital';

// Page-specific script loading
$pageScripts = [
    'assets/js/reports.js',
    'assets/js/whatsapp_attendance.js',
    'assets/js/zkteco_attendance.js'
];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Attendance & Biometric Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_attendance.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
