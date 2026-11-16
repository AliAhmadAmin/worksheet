<?php
/**
 * Discover Pakistan UHD TV - HR & People Operations Portal
 * Dedicated Department Workspace for Leave Management, Staff Profiles, and Monthly Payroll
 */
require_once __DIR__ . '/includes/auth_check.php';

// Ensure user has HR or Admin permissions
$userRole = strtolower($authUser['role'] ?? '');
$deptName = strtolower($authUser['department_name'] ?? '');
$isGlobalAdmin = in_array($userRole, ['super_admin', 'admin']);
$isHrMember = in_array($userRole, ['hr']) || in_array($deptName, ['hr', 'human resources']);
$canAccessHr = $isGlobalAdmin || $isHrMember || (!empty($authUser['can_manage_hr']) && $userRole !== 'hod');

if (!$canAccessHr) {
    header('Location: index.php');
    exit;
}

$currentPage = 'hr';
$currentPortal = 'hr';
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['active_portal'] = 'hr';
}

// Page-specific script loading
$pageScripts = ['assets/js/hr.js'];

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
    ?>
</main>

<?php
// 3. Interactive Modals (Auth, HR Leave Form, Profile Editor, Payroll Editor)
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
