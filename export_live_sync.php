<?php
/**
 * Safe Live Sync Exporter Utility
 * Generates an idempotent SQL sync script that:
 * 1. Upserts departments, teams, content_types
 * 2. Upserts employees, hr_employee_profiles, hr_payroll
 * 3. Does NOT drop daily_sheets, sheet_entries, or tasks (preserves live daily work & attendance)
 * 4. Does NOT disturb active user sessions
 */

require_once __DIR__ . '/config/database.php';
$pdo = getDbConnection();

$syncTables = [
    'departments',
    'teams',
    'content_types',
    'employees',
    'hr_employee_profiles',
    'hr_payroll'
];

$sql = "-- ==========================================================\n";
$sql .= "-- SAFE LIVE DATABASE SYNC SCRIPT\n";
$sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Preserves: daily_sheets, sheet_entries, tasks, active sessions\n";
$sql .= "-- ==========================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sql .= "START TRANSACTION;\n\n";

foreach ($syncTables as $table) {
    $stmt = $pdo->query("SELECT * FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) continue;

    $sql .= "--\n-- Syncing table `{$table}` (" . count($rows) . " records)\n--\n";
    $columns = array_keys($rows[0]);
    $colList = '`' . implode('`, `', $columns) . '`';
    
    // Build ON DUPLICATE KEY UPDATE clause
    $updateParts = [];
    foreach ($columns as $col) {
        if ($col === 'id') continue;
        $updateParts[] = "`{$col}` = VALUES(`{$col}`)";
    }
    $updateClause = implode(', ', $updateParts);

    foreach ($rows as $row) {
        $vals = [];
        foreach ($row as $v) {
            $vals[] = ($v === null) ? 'NULL' : $pdo->quote($v);
        }
        $valList = implode(', ', $vals);
        $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES ({$valList}) ON DUPLICATE KEY UPDATE {$updateClause};\n";
    }
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
$sql .= "COMMIT;\n";

$filename = __DIR__ . '/live_database_sync.sql';
file_put_contents($filename, $sql);
echo "[SUCCESS] Generated safe sync file: {$filename} (" . number_format(filesize($filename)) . " bytes)\n";
