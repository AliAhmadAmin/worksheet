<?php
/**
 * Safe Live Database Sync Runner
 * Usage CLI: php import_live_sync.php
 * Usage Browser: http://<domain-or-localhost>/Worksheet/import_live_sync.php
 */

require_once __DIR__ . '/config/database.php';

$sqlFile = __DIR__ . '/live_database_sync.sql';

if (!file_exists($sqlFile)) {
    die("<h2 style='color:red;'>Error: live_database_sync.sql file not found in " . htmlspecialchars(__DIR__) . "</h2>");
}

try {
    $pdo = getDbConnection();
    $sql = file_get_contents($sqlFile);

    echo "<pre style='font-family: monospace; background:#1e1e2e; color:#a6e3a1; padding:20px; border-radius:8px;'>";
    echo "====================================================\n";
    echo "  Discover Pakistan - Live Database Sync Execution  \n";
    echo "====================================================\n\n";
    echo "Executing SQL statements from live_database_sync.sql...\n";

    $pdo->exec($sql);

    echo "\n[SUCCESS] All records synchronized successfully!\n\n";

    // Show summary counts
    $empCount = $pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
    $loginCount = $pdo->query("SELECT COUNT(*) FROM employees WHERE can_login = 1")->fetchColumn();
    $digitalCount = $pdo->query("SELECT COUNT(*) FROM employees WHERE department_id = 2")->fetchColumn();
    $payrollCount = $pdo->query("SELECT COUNT(*) FROM hr_payroll WHERE salary_month = '2026-09'")->fetchColumn();

    echo "Status Summary:\n";
    echo " - Total Active Employees: {$empCount}\n";
    echo " - Staff with Login Enabled: {$loginCount}\n";
    echo " - Digital Team Members: {$digitalCount}\n";
    echo " - September Payroll Records: {$payrollCount}\n\n";
    echo "Database sync is complete! You can now access your application.\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "<pre style='font-family: monospace; background:#1e1e2e; color:#f38ba8; padding:20px; border-radius:8px;'>";
    echo "[ERROR] Database sync failed: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "</pre>";
}
