<?php
/**
 * Server-Side Authentication Gatekeeper
 * Protects all portal pages and ensures only authenticated users with active sessions can access the dashboard.
 */
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['user_id'])) {
    // If not logged in, redirect immediately to login page
    header('Location: login.php');
    exit;
}

// Fetch current authenticated user details for navbar and permissions
$currentUserId = (int)$_SESSION['user_id'];
$pdo = getDbConnection();
$authStmt = $pdo->prepare("
    SELECT e.*, d.name as department_name, d.parent_id, p.name as parent_dept_name, t.name as team_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN departments p ON d.parent_id = p.id
    LEFT JOIN teams t ON e.team_id = t.id 
    WHERE e.id = ? AND e.is_active = 1
");
$authStmt->execute([$currentUserId]);
$authUser = $authStmt->fetch();

if (!$authUser) {
    // Account deactivated or removed: destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_deactivated');
    exit;
}

// Ensure non-admin users (especially HODs) cannot be hijacked by an old HR active_portal session
$uRoleCheck = strtolower($authUser['role'] ?? '');
$uDeptCheck = strtolower($authUser['department_name'] ?? '');
$isGlobalAdminCheck = in_array($uRoleCheck, ['super_admin', 'admin']);

if (!$isGlobalAdminCheck) {
    // If not global admin, active portal must match user's actual department
    if ($uRoleCheck === 'hr' || (stripos($uDeptCheck, 'hr') !== false && $uRoleCheck !== 'hod') || ($uRoleCheck === 'hod' && stripos($uDeptCheck, 'hr') !== false)) {
        $_SESSION['active_portal'] = 'hr';
    } elseif (stripos($uDeptCheck, 'news') !== false || stripos(strtolower($authUser['parent_dept_name'] ?? ''), 'news') !== false) {
        $_SESSION['active_portal'] = 'newsroom';
    } elseif (stripos($uDeptCheck, 'prog') !== false || stripos(strtolower($authUser['parent_dept_name'] ?? ''), 'prog') !== false) {
        $_SESSION['active_portal'] = 'programming';
    } else {
        $_SESSION['active_portal'] = 'digital';
    }
}
