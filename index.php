<?php
/**
 * Hourly Work Sheet, Task Assigner & Reporting System
 * Main Application Dashboard (Modular View Architecture)
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRoleIdx = strtolower($authUser['role'] ?? '');
$deptLowerIdx = strtolower($authUser['department_name'] ?? '');
$parentDeptLowerIdx = strtolower($authUser['parent_dept_name'] ?? '');
$isGlobalAdminIdx = in_array($userRoleIdx, ['super_admin', 'admin']);
$canSwitchPortals = $isGlobalAdminIdx;

$currentPage = 'worksheet';

// When HOD lands on the root index page without explicit tab/date/emp inspection query parameters,
// redirect directly to their Team Dashboard (attendance.php)
if (in_array($userRoleIdx, ['hod', 'deputy_hod']) && empty($_GET)) {
    header('Location: attendance.php');
    exit;
}

// Handle explicit portal switch via ?portal= (Global Admins only)
if (!empty($_GET['portal']) && $canSwitchPortals) {
    $req = strtolower(trim($_GET['portal']));
    if (in_array($req, ['digital', 'newsroom', 'programming', 'hr'], true)) {
        $_SESSION['active_portal'] = $req;
    }
}

// Determine active workspace portal based on admin selection or user department
if ($canSwitchPortals && !empty($_SESSION['active_portal'])) {
    $currentPortal = $_SESSION['active_portal'];
} elseif ($userRoleIdx === 'hr' || (stripos($deptLowerIdx, 'hr') !== false && $userRoleIdx !== 'hod') || ($userRoleIdx === 'hod' && stripos($deptLowerIdx, 'hr') !== false)) {
    $currentPortal = 'hr';
} elseif (stripos($deptLowerIdx, 'news') !== false || stripos($parentDeptLowerIdx, 'news') !== false) {
    $currentPortal = 'newsroom';
} elseif (stripos($deptLowerIdx, 'prog') !== false || stripos($parentDeptLowerIdx, 'prog') !== false) {
    $currentPortal = 'programming';
} else {
    $currentPortal = 'digital';
}
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['active_portal'] = $currentPortal;
}

// Page-specific script loading
$pageScripts = ['assets/js/sheets.js', 'assets/js/reports.js'];

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
