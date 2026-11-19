<?php
/**
 * Discover Pakistan UHD TV - Programming & Broadcast Operations Portal
 * Dedicated Handover Pipeline, Broadcast Media Dispatch, and Multi-Channel Digital Publishing
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRolePg = strtolower($authUser['role'] ?? '');
$deptNamePg = strtolower($authUser['department_name'] ?? '');
$isGlobalAdminPg = in_array($userRolePg, ['super_admin', 'admin']);
$isHrMemberPg = ($userRolePg === 'hr' || stripos($deptNamePg, 'hr') !== false || !empty($authUser['can_manage_hr']));
$isProgMemberPg = (stripos($deptNamePg, 'prog') !== false);
$isDigitalMemberPg = (stripos($deptNamePg, 'digital') !== false);
$canAccessProgramming = $isGlobalAdminPg || $isHrMemberPg || $isProgMemberPg || $isDigitalMemberPg;

if (!$canAccessProgramming) {
    header('Location: index.php');
    exit;
}

$currentPage = 'programming';
$currentPortal = 'programming';
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['active_portal'] = 'programming';
}

// Page-specific script loading
$pageScripts = ['assets/js/programming.js'];

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar (Configured for Programming Workspace with Portal Switcher)
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Programming Application Workspace Container -->
<main class="main-content">
    <?php
    // Modular Feature Views
    require_once __DIR__ . '/views/tab_programming.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Page Scripts & Dynamic Loaders
require_once __DIR__ . '/includes/footer.php';
?>
