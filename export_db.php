<?php
/**
 * Database Exporter Utility
 * Usage CLI: php export_db.php
 * Usage Browser: http://localhost/worksheet/export_db.php
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();
$dbName = DB_NAME;

$isCli = (php_sapi_name() === 'cli');

// Generate SQL Dump
$sqlDump = "-- =============================================\n";
$sqlDump .= "-- Database Export: `{$dbName}`\n";
$sqlDump .= "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
$sqlDump .= "-- =============================================\n\n";
$sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sqlDump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sqlDump .= "START TRANSACTION;\n";
$sqlDump .= "SET time_zone = '+00:00';\n\n";

$sqlDump .= "CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
$sqlDump .= "USE `{$dbName}`;\n\n";

// Fetch all tables
$tables = [];
$stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    // 1. Structure
    $sqlDump .= "--\n-- Table structure for table `{$table}`\n--\n";
    $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
    $sqlDump .= $createTableStmt[1] . ";\n\n";

    // 2. Data
    $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 0) {
        $sqlDump .= "--\n-- Dumping data for table `{$table}`\n--\n";
        $columns = array_keys($rows[0]);
        $columnsList = implode('`, `', $columns);

        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = $pdo->quote($val);
                }
            }
            $sqlDump .= "INSERT INTO `{$table}` (`{$columnsList}`) VALUES (" . implode(', ', $values) . ");\n";
        }
        $sqlDump .= "\n";
    }
}

$sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
$sqlDump .= "COMMIT;\n";

$filename = "worksheet_export_" . date('Y-m-d_His') . ".sql";

if ($isCli) {
    $savePath = __DIR__ . '/' . $filename;
    file_put_contents($savePath, $sqlDump);
    echo "\n[SUCCESS] Database exported successfully to:\n" . $savePath . "\n\n";
} else {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sqlDump));
    echo $sqlDump;
    exit;
}
