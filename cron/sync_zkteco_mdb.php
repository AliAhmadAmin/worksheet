<?php
/**
 * ZKTeco MDB Automated Background Sync Script
 * 
 * Can be run via Windows Task Scheduler / Windows CLI:
 *   php.exe C:\xampp\htdocs\Worksheet\cron\sync_zkteco_mdb.php
 * 
 * Or triggered via HTTP Cron:
 *   http://10.0.0.X/Worksheet/cron/sync_zkteco_mdb.php?secret=zkteco_cron
 */

set_time_limit(300);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ZKTecoAttendanceService.php';
require_once __DIR__ . '/../includes/ZKTecoMdbService.php';

$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));
$force = $isCli ? in_array('--force', $argv ?? []) : (!empty($_GET['force']));

try {
    $pdo = getDbConnection();
    $mdbService = new ZKTecoMdbService($pdo);

    $now = date('Y-m-d H:i:s');
    $result = $mdbService->syncIncremental($force);

    if ($isCli) {
        echo "[{$now}] ZKTeco Auto-Sync: " . ($result['message'] ?? 'Done') . PHP_EOL;
        if (!empty($result['stats'])) {
            echo "  Applied: " . ($result['stats']['applied'] ?? 0) . 
                 " | Duplicates: " . ($result['stats']['duplicate'] ?? 0) . 
                 " | Unmatched: " . ($result['stats']['unmatched'] ?? 0) . PHP_EOL;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'timestamp' => $now,
            'result' => $result
        ]);
    }
} catch (Exception $e) {
    if ($isCli) {
        echo "[ERROR] " . $e->getMessage() . PHP_EOL;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

