<?php
/**
 * Hourly Work Sheet, Task Assigner & Reporting System
 * Main Application Dashboard (Modular View Architecture)
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRoleIdx = strtolower($authUser['role'] ?? '');
$deptLowerIdx = strtolower($authUser['department_name'] ?? '');
$isGlobalAdminIdx = in_array($userRoleIdx, ['super_admin', 'admin']);

$currentPage = 'worksheet';

// Preserve portal context based on employee's department or active admin portal
if ($isGlobalAdminIdx && !empty($_SESSION['active_portal'])) {
    $currentPortal = $_SESSION['active_portal'];
} elseif (stripos($deptLowerIdx, 'news') !== false) {
    $currentPortal = 'newsroom';
} elseif (stripos($deptLowerIdx, 'prog') !== false) {
    $currentPortal = 'programming';
} elseif ($userRoleIdx === 'hr' || stripos($deptLowerIdx, 'hr') !== false) {
    $currentPortal = 'hr';
} else {
    $currentPortal = 'digital';
}
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['active_portal'] = $currentPortal;
}

// Page-specific script loading
$pageScripts = ['assets/js/sheets.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar (Logo, Route Tabs, User Profile, Switcher)
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Main Application Workspace Container -->
<main class="main-content">
    <?php
    // Modular Feature Views
    require_once __DIR__ . '/views/tab_worksheet.php';
    ?>
</main>

<?php
// 3. Interactive Modals (Auth, Task Assigner, Switcher, Employee CRUD, Password Reset)
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
