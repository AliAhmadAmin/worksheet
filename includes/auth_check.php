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
    SELECT e.*, d.name as department_name, t.name as team_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
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
