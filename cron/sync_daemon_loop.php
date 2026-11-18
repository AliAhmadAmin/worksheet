<?php
/**
 * Standalone 24/7 Background Attendance Sync Loop
 * Runs continuously in the background on the live server.
 * Checks for new punches every 60 seconds without needing an open browser.
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ZKTecoAttendanceService.php';
require_once __DIR__ . '/../includes/ZKTecoMdbService.php';

$pdo = getDbConnection();
$mdbService = new ZKTecoMdbService($pdo);

echo "[" . date('Y-m-d H:i:s') . "] ZKTeco 24/7 Daemon started." . PHP_EOL;

while (true) {
    try {
        // Re-ping PDO in case MySQL connection timed out overnight
        try {
            $pdo->query("SELECT 1");
        } catch (Throwable $e) {
            $pdo = getDbConnection();
            $mdbService = new ZKTecoMdbService($pdo);
        }

        $res = $mdbService->syncIncremental(false);
        $time = date('Y-m-d H:i:s');
        if (!empty($res['success']) && empty($res['skipped'])) {
            echo "[{$time}] Punches synced: " . ($res['message'] ?? 'OK') . PHP_EOL;
        }
    } catch (Throwable $t) {
        echo "[" . date('Y-m-d H:i:s') . "] Sync error: " . $t->getMessage() . PHP_EOL;
    }

    sleep(60);
}
