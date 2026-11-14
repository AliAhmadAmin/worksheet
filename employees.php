<?php
/**
 * Discover Pakistan UHD TV - Staff Directory & Management Portal
 * Dedicated Page for Employee Records, Role Assignments & Department Teams
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRoleEmp = strtolower($authUser['role'] ?? '');
$canAccessEmployees = in_array($userRoleEmp, ['super_admin', 'admin', 'hr']) || !empty($authUser['can_manage_employees']);

if (!$canAccessEmployees) {
    header('Location: index.php');
    exit;
}

$currentPage = 'employees';
$currentPortal = 'digital';

// Page-specific script loading
$pageScripts = ['assets/js/employees.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Staff Directory Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_employees.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
