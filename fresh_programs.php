<?php
/**
 * Discover Pakistan UHD TV - Digital Fresh Programs Portal
 * Weekly TV Program Editing, Episode Breakdowns, Clip & Reel Extraction Pipeline
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRolePg = strtolower($authUser['role'] ?? '');
$deptNamePg = strtolower($authUser['department_name'] ?? '');
$isGlobalAdminPg = in_array($userRolePg, ['super_admin', 'admin', 'manager']);
$isHrMemberPg = ($userRolePg === 'hr' || stripos($deptNamePg, 'hr') !== false || !empty($authUser['can_manage_hr']));
$isDigitalMemberPg = (stripos($deptNamePg, 'digital') !== false);
$canAccess = $isGlobalAdminPg || $isHrMemberPg || $isDigitalMemberPg || in_array($userRolePg, ['hod', 'deputy_hod']);

if (!$canAccess) {
    header('Location: index.php');
    exit;
}

$currentPage = 'fresh_programs';
$currentPortal = 'digital';

$pageScripts = ['assets/js/fresh_programs.js'];

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Fresh Programs Application Workspace Container -->
<main class="main-content">
    <?php
    // Modular Feature Views
    require_once __DIR__ . '/views/tab_fresh_programs.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Page Scripts & Dynamic Loaders
require_once __DIR__ . '/includes/footer.php';
?>
