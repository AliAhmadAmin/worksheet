<?php
/**
 * Discover Pakistan UHD TV - News Room & Broadcast Operations Portal
 * Dedicated Story Handover Pipeline, Ticker & Bulletin Dispatch, and Multi-Channel Digital Publishing
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRoleNr = strtolower($authUser['role'] ?? '');
$deptNameNr = strtolower($authUser['department_name'] ?? '');
$isGlobalAdminNr = in_array($userRoleNr, ['super_admin', 'admin']);
$isNewsMemberNr = (stripos($deptNameNr, 'news') !== false);

if (!$isGlobalAdminNr && !$isNewsMemberNr) {
    header('Location: index.php');
    exit;
}

$currentPortal = 'newsroom';

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar (Configured for News Room Workspace with Portal Switcher)
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated News Room Application Workspace Container -->
<main class="main-content">
    <?php
    // Modular Feature Views
    require_once __DIR__ . '/views/tab_newsroom.php';
    require_once __DIR__ . '/views/tab_worksheet.php';
    require_once __DIR__ . '/views/tab_roster.php';
    require_once __DIR__ . '/views/tab_tasks.php';
    require_once __DIR__ . '/views/tab_programming.php';
    require_once __DIR__ . '/views/tab_hr.php';
    require_once __DIR__ . '/views/tab_reports.php';
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
