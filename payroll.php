<?php
/**
 * Discover Pakistan UHD TV - Monthly Payroll & Salaries Portal
 * Dedicated Department Workspace for Monthly Salary Generation, Deductions & Slips
 */
require_once __DIR__ . '/includes/auth_check.php';

// Ensure user has HR, Finance or Admin permissions
$userRole = strtolower($authUser['role'] ?? '');
$deptName = strtolower($authUser['department_name'] ?? '');
$isGlobalAdmin = in_array($userRole, ['super_admin', 'admin']);
$isHrMember = in_array($userRole, ['hr']) || in_array($deptName, ['hr', 'human resources', 'accounts', 'finance']);
$canAccessPayroll = $isGlobalAdmin || $isHrMember || (!empty($authUser['can_manage_hr']) && $userRole !== 'hod') || !empty($authUser['can_manage_payroll']);

if (!$canAccessPayroll) {
    header('Location: index.php');
    exit;
}

$currentPage = 'payroll';
$currentPortal = 'hr';
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['active_portal'] = 'hr';
}

// Page-specific script loading
$pageScripts = ['assets/js/hr.js'];

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Payroll Application Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_hr.php';
    ?>
</main>

<?php
// 3. Interactive Modals (Profile Editor, Payroll Editor, Payslip)
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
