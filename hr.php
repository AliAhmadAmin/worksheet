<?php
/**
 * Discover Pakistan UHD TV - HR & People Operations Portal
 * Dedicated Department Workspace for Leave Management, Staff Profiles, and Monthly Payroll
 */
require_once __DIR__ . '/includes/auth_check.php';

// Ensure user has HR or Admin permissions
if ($authUser['role'] !== 'admin' && empty($authUser['can_manage_hr']) && strtolower($authUser['department_name'] ?? '') !== 'hr') {
    header('Location: index.php');
    exit;
}

$currentPortal = 'hr';

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar (Configured for HR Workspace with Portal Switcher)
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated HR Application Workspace Container -->
<main class="main-content">
    <?php
    // Core HR Management View (HR Dashboard, Leave Center, Monthly Payroll)
    require_once __DIR__ . '/views/tab_hr.php';
    // Supporting Views (Integrated Staff Attendance & Employee Directory)
    require_once __DIR__ . '/views/tab_attendance.php';
    require_once __DIR__ . '/views/tab_employees.php';
    ?>
</main>

<?php
// 3. Interactive Modals (Auth, HR Leave Form, Profile Editor, Payroll Editor)
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
