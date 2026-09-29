<?php
/**
 * Database Seed / Reset Utility
 * Usage CLI: php seed_db.php
 * Usage Browser: http://localhost/worksheet/seed_db.php
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family: monospace; background: #0f172a; color: #10b981; padding: 24px; border-radius: 8px; font-size: 14px;">';
}

echo "==============================================\n";
echo "       WORKSHEET DATABASE SEEDER\n";
echo "==============================================\n\n";

try {
    echo "[1/4] Checking and creating database tables...\n";
    initDatabaseSchema($pdo);
    echo "      -> Tables created / verified successfully.\n\n";

    echo "[2/4] Applying column & permission migrations...\n";
    migratePermissionsSchema($pdo);
    echo "      -> Migrations verified successfully.\n\n";

    echo "[3/4] Seeding default departments, teams & content types...\n";
    seedInitialData($pdo);
    echo "      -> Reference data seeded successfully.\n\n";

    echo "[4/4] Verifying employees in database...\n";
    $empCount = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $teamCount = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();

    echo "      -> Total Employees: {$empCount}\n";
    echo "      -> Total Departments: {$deptCount}\n";
    echo "      -> Total Teams: {$teamCount}\n\n";

    echo "==============================================\n";
    echo " [SUCCESS] Database seeding completed successfully!\n";
    echo "==============================================\n";
} catch (Exception $e) {
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo '</pre>';
}
