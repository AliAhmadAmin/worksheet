<?php
/**
 * ZKTeco Access Database (.MDB) Service
 * Supports direct PDO ODBC, native ODBC, and Windows 32-bit ADODB fallback bridge.
 * Reads CHECKINOUT and USERINFO tables from ZKTeco desktop software (e.g. attBackup.mdb).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ZKTecoAttendanceService.php';

class ZKTecoMdbService {
    private $pdo;
    private $attService;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
        $this->attService = new ZKTecoAttendanceService($this->pdo);
        $this->ensureDatabaseSchema();
    }

    /**
     * Auto-migrate schema columns on live servers seamlessly without manual SQL intervention.
     */
    private function ensureDatabaseSchema() {
        try {
            $this->pdo->exec("
                ALTER TABLE zkteco_settings 
                ADD COLUMN IF NOT EXISTS mdb_default_path VARCHAR(255) NULL,
                ADD COLUMN IF NOT EXISTS mdb_auto_sync_enabled TINYINT(1) DEFAULT 1,
                ADD COLUMN IF NOT EXISTS mdb_sync_interval_mins INT DEFAULT 1,
                ADD COLUMN IF NOT EXISTS mdb_last_sync_time DATETIME NULL,
                ADD COLUMN IF NOT EXISTS mdb_last_punch_time DATETIME NULL,
                ADD COLUMN IF NOT EXISTS mdb_last_sync_status VARCHAR(255) NULL,
                ADD COLUMN IF NOT EXISTS mdb_last_file_mtime BIGINT NULL
            ");
        } catch (Exception $e) {
            // Non-blocking: table might already have columns or running older MySQL without IF NOT EXISTS
            try {
                $cols = [
                    'mdb_default_path' => 'VARCHAR(255) NULL',
                    'mdb_auto_sync_enabled' => 'TINYINT(1) DEFAULT 1',
                    'mdb_sync_interval_mins' => 'INT DEFAULT 1',
                    'mdb_last_sync_time' => 'DATETIME NULL',
                    'mdb_last_punch_time' => 'DATETIME NULL',
                    'mdb_last_sync_status' => 'VARCHAR(255) NULL',
                    'mdb_last_file_mtime' => 'BIGINT NULL'
                ];
                foreach ($cols as $col => $type) {
                    try {
                        $this->pdo->exec("ALTER TABLE zkteco_settings ADD COLUMN {$col} {$type}");
                    } catch (Exception $ex) {}
                }
            } catch (Exception $ex2) {}
        }
    }

    /**
     * Test connection to .mdb file and detect connection driver method.
     */
    public function testConnection($mdbPath) {
        $mdbPath = trim($mdbPath);
        $real = realpath($mdbPath);
        if ($real) {
            $mdbPath = $real;
        }

        if (!file_exists($mdbPath)) {
            return [
                'success' => false,
                'message' => 'File not found. Please provide a valid full path or network path (e.g. \\\\10.0.0.x\\share\\attBackup.mdb).'
            ];
        }

        // 1. Try PDO ODBC
        try {
            if (extension_loaded('pdo_odbc')) {
                $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$mdbPath};Uid=Admin;Pwd=;";
                $db = new PDO($dsn);
                $stmt = $db->query("SELECT COUNT(*) FROM CHECKINOUT");
                $count = $stmt->fetchColumn();
                return [
                    'success' => true,
                    'method' => 'pdo_odbc',
                    'path' => $mdbPath,
                    'file_size' => filesize($mdbPath),
                    'total_punches' => (int)$count,
                    'message' => "Successfully connected via PDO ODBC! Found {$count} total punch records."
                ];
            }
        } catch (Exception $e) {
            // Fall through to other drivers
        }

        // 2. Try native odbc_connect
        try {
            if (function_exists('odbc_connect')) {
                $connStr = "Driver={Microsoft Access Driver (*.mdb)};Dbq={$mdbPath};";
                $conn = @odbc_connect($connStr, '', '');
                if ($conn) {
                    $res = odbc_exec($conn, "SELECT COUNT(*) FROM CHECKINOUT");
                    $count = 0;
                    if ($res && odbc_fetch_row($res)) {
                        $count = (int)odbc_result($res, 1);
                    }
                    odbc_close($conn);
                    return [
                        'success' => true,
                        'method' => 'native_odbc',
                        'path' => $mdbPath,
                        'file_size' => filesize($mdbPath),
                        'total_punches' => $count,
                        'message' => "Successfully connected via Native ODBC! Found {$count} total punch records."
                    ];
                }
            }
        } catch (Exception $e) {
            // Fall through to fallback
        }

        // 3. Built-in Windows 32-bit ADODB Bridge (Works on every Windows OS without extra drivers)
        $bridgeTest = $this->queryViaWindowsBridge($mdbPath, "SELECT COUNT(*) as cnt FROM CHECKINOUT");
        if ($bridgeTest['success']) {
            $count = (int)($bridgeTest['data'][0]['cnt'] ?? 0);
            return [
                'success' => true,
                'method' => 'windows_adodb_bridge',
                'path' => $mdbPath,
                'file_size' => filesize($mdbPath),
                'total_punches' => $count,
                'message' => "Successfully connected via Windows ADODB Jet Engine! Found {$count} total punch records."
            ];
        }

        return [
            'success' => false,
            'message' => 'Unable to read .mdb file: ' . ($bridgeTest['message'] ?? 'Unknown driver error.')
        ];
    }

    /**
     * Inspect .MDB Database: Get Punch Counts, Date Range, and User Summary
     */
    public function inspectDatabase($mdbPath) {
        $test = $this->testConnection($mdbPath);
        if (!$test['success']) {
            return $test;
        }

        $mdbPath = trim($mdbPath);
        $real = realpath($mdbPath);
        if ($real) {
            $mdbPath = $real;
        }
        $method = $test['method'];

        // Query date range and punch totals
        $sqlStats = "SELECT MIN(CHECKTIME) as min_date, MAX(CHECKTIME) as max_date, COUNT(*) as total_punches FROM CHECKINOUT";
        $sqlUsers = "SELECT COUNT(*) as total_users FROM USERINFO";

        $stats = $this->runQuery($mdbPath, $method, $sqlStats);
        $users = $this->runQuery($mdbPath, $method, $sqlUsers);

        $minDate = $stats['data'][0]['min_date'] ?? null;
        $maxDate = $stats['data'][0]['max_date'] ?? null;
        $totalPunches = (int)($stats['data'][0]['total_punches'] ?? 0);
        $totalUsers = (int)($users['data'][0]['total_users'] ?? 0);

        return [
            'success' => true,
            'method' => $method,
            'path' => $mdbPath,
            'file_size_formatted' => $this->formatBytes(filesize($mdbPath)),
            'total_punches' => $totalPunches,
            'total_users' => $totalUsers,
            'min_date' => $minDate ? substr($minDate, 0, 10) : 'N/A',
            'max_date' => $maxDate ? substr($maxDate, 0, 10) : 'N/A',
            'min_time' => $minDate,
            'max_time' => $maxDate
        ];
    }

    /**
     * Import Punches from .MDB into Worksheet Database & Daily Sheets
     */
    public function importPunches($mdbPath, $startDate = null, $endDate = null, $limit = 5000) {
        $mdbPath = trim($mdbPath);
        $real = realpath($mdbPath);
        if ($real) {
            $mdbPath = $real;
        }
        if (!$mdbPath || !file_exists($mdbPath)) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        $test = $this->testConnection($mdbPath);
        if (!$test['success']) {
            return $test;
        }
        $method = $test['method'];

        // 1. Fetch Users mapping from USERINFO (USERID -> Badgenumber)
        $userMap = [];
        $usersRes = $this->runQuery($mdbPath, $method, "SELECT USERID, Badgenumber, Name FROM USERINFO");
        if ($usersRes['success'] && !empty($usersRes['data'])) {
            foreach ($usersRes['data'] as $u) {
                $uid = (string)($u['USERID'] ?? '');
                $badge = trim((string)($u['Badgenumber'] ?? ''));
                $userMap[$uid] = !empty($badge) ? $badge : $uid;
            }
        }

        // 2. Build Query for CHECKINOUT
        $whereClauses = [];
        if (!empty($startDate)) {
            $whereClauses[] = "CHECKTIME >= #" . date('Y-m-d 00:00:00', strtotime($startDate)) . "#";
        }
        if (!empty($endDate)) {
            $whereClauses[] = "CHECKTIME <= #" . date('Y-m-d 23:59:59', strtotime($endDate)) . "#";
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";
        $topClause = $limit > 0 ? "TOP {$limit}" : "";
        $sql = "SELECT {$topClause} USERID, CHECKTIME, CHECKTYPE, VERIFYCODE FROM CHECKINOUT {$whereSql} ORDER BY CHECKTIME ASC";

        $punchesRes = $this->runQuery($mdbPath, $method, $sql);
        if (!$punchesRes['success']) {
            return ['success' => false, 'message' => 'Failed to query CHECKINOUT table: ' . $punchesRes['message']];
        }

        $rawPunches = $punchesRes['data'] ?? [];
        $appliedCount = 0;
        $duplicateCount = 0;
        $unmatchedCount = 0;
        $totalProcessed = count($rawPunches);

        foreach ($rawPunches as $row) {
            $uId = (string)($row['USERID'] ?? '');
            $pin = $userMap[$uId] ?? $uId;
            $checkTime = trim((string)($row['CHECKTIME'] ?? ''));
            $ts = strtotime($checkTime);
            if (!$ts) continue;

            $stateCode = (string)($row['CHECKTYPE'] ?? '0');
            $verifyCode = (string)($row['VERIFYCODE'] ?? '1');

            // Format check
            $verifyMap = ['1' => 'fingerprint', '2' => 'password', '3' => 'card', '15' => 'face', '25' => 'palm'];
            $stateMap = ['I' => 'check_in', '0' => 'check_in', 'O' => 'check_out', '1' => 'check_out', '2' => 'break_out', '3' => 'break_in', '4' => 'ot_in', '5' => 'ot_out'];

            $punchData = [
                'user_id' => $pin,
                'punch_time' => date('Y-m-d H:i:s', $ts),
                'punch_date' => date('Y-m-d', $ts),
                'formatted_time' => date('h:i A', $ts),
                'punch_state' => $stateMap[strtoupper($stateCode)] ?? 'auto',
                'verify_type' => $verifyMap[$verifyCode] ?? 'fingerprint',
                'timestamp' => $ts
            ];

            $res = $this->attService->recordPunch('MDB-FILE', $punchData);
            if ($res['status'] === 'applied') {
                $appliedCount++;
            } elseif ($res['status'] === 'duplicate') {
                $duplicateCount++;
            } elseif ($res['status'] === 'unmatched') {
                $unmatchedCount++;
            }
        }

        return [
            'success' => true,
            'message' => "Import completed: {$appliedCount} punches applied to timesheets, {$duplicateCount} duplicates skipped, {$unmatchedCount} unmatched PINs.",
            'stats' => [
                'total_processed' => $totalProcessed,
                'applied' => $appliedCount,
                'duplicate' => $duplicateCount,
                'unmatched' => $unmatchedCount
            ]
        ];
    }

    /**
     * Unified Query Runner
     */
    private function runQuery($mdbPath, $method, $sql) {
        if ($method === 'pdo_odbc') {
            try {
                $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$mdbPath};Uid=Admin;Pwd=;";
                $db = new PDO($dsn);
                $stmt = $db->query($sql);
                return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } catch (Exception $e) {
                // Try fallback bridge
                return $this->queryViaWindowsBridge($mdbPath, $sql);
            }
        } elseif ($method === 'native_odbc') {
            try {
                $connStr = "Driver={Microsoft Access Driver (*.mdb)};Dbq={$mdbPath};";
                $conn = @odbc_connect($connStr, '', '');
                if ($conn) {
                    $res = @odbc_exec($conn, $sql);
                    $rows = [];
                    while ($res && ($r = odbc_fetch_array($res))) {
                        $rows[] = $r;
                    }
                    odbc_close($conn);
                    return ['success' => true, 'data' => $rows];
                }
            } catch (Exception $e) {}
            return $this->queryViaWindowsBridge($mdbPath, $sql);
        }

        return $this->queryViaWindowsBridge($mdbPath, $sql);
    }

    /**
     * Windows 32-bit ADODB PowerShell Bridge.
     * Guaranteed to work on all Windows operating systems without requiring 64-bit Access drivers.
     */
    private function queryViaWindowsBridge($mdbPath, $sql) {
        $cleanPath = str_replace("'", "''", $mdbPath);
        $cleanSql = str_replace("'", "''", $sql);

        $ps32 = 'C:\\Windows\\SysWOW64\\WindowsPowerShell\\v1.0\\powershell.exe';
        if (!file_exists($ps32)) {
            $ps32 = 'powershell.exe';
        }

        $script = <<<PS1
\$mdb = '{$cleanPath}'
\$sql = '{$cleanSql}'
try {
    \$conn = New-Object -ComObject ADODB.Connection
    \$conn.Open("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=\$mdb")
    \$rs = \$conn.Execute(\$sql)
    \$rows = @()
    while (-not \$rs.EOF) {
        \$obj = @{}
        for (\$i = 0; \$i -lt \$rs.Fields.Count; \$i++) {
            \$f = \$rs.Fields.Item(\$i)
            \$val = \$f.Value
            if (\$val -is [System.DateTime]) {
                \$val = \$val.ToString("yyyy-MM-dd HH:mm:ss")
            }
            \$obj[\$f.Name] = \$val
        }
        \$rows += \$obj
        \$rs.MoveNext()
    }
    \$conn.Close()
    \$out = @{ success = \$true; data = \$rows }
    ConvertTo-Json \$out -Depth 5 -Compress
} catch {
    \$out = @{ success = \$false; message = \$_.Exception.Message }
    ConvertTo-Json \$out -Compress
}
PS1;

        $tempScript = sys_get_temp_dir() . '/mdb_bridge_' . uniqid() . '.ps1';
        file_put_contents($tempScript, $script);

        $cmd = "\"{$ps32}\" -NoProfile -ExecutionPolicy Bypass -File \"{$tempScript}\"";
        $output = shell_exec($cmd);
        @unlink($tempScript);

        if (empty($output)) {
            return ['success' => false, 'message' => 'Empty output from Windows ADODB bridge.'];
        }

        $decoded = json_decode(trim($output), true);
        if (!$decoded) {
            return ['success' => false, 'message' => 'Invalid JSON response from bridge: ' . substr($output, 0, 100)];
        }

        return $decoded;
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Incremental Automated Sync
     * Safe, lightweight, and configurable (e.g. every 15 mins).
     * Checks filemtime first and only fetches punches newer than last sync.
     */
    public function syncIncremental($forced = false) {
        $stmt = $this->pdo->query("SELECT * FROM zkteco_settings WHERE id = 1");
        $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        if (!$settings) {
            return ['success' => false, 'message' => 'Settings not found.'];
        }

        $enabled = isset($settings['mdb_auto_sync_enabled']) ? (int)$settings['mdb_auto_sync_enabled'] : 1;
        $intervalMins = isset($settings['mdb_sync_interval_mins']) && (int)$settings['mdb_sync_interval_mins'] > 0 ? (int)$settings['mdb_sync_interval_mins'] : 1;
        $mdbPath = trim($settings['mdb_default_path'] ?? '');
        $lastSync = $settings['mdb_last_sync_time'] ?? null;
        $lastPunch = $settings['mdb_last_punch_time'] ?? null;

        if (!$enabled && !$forced) {
            return ['success' => true, 'skipped' => true, 'message' => 'Auto-sync is currently disabled.'];
        }

        // Interval elapsed check
        if (!$forced && !empty($lastSync)) {
            $elapsedSec = time() - strtotime($lastSync);
            if ($elapsedSec < ($intervalMins * 60)) {
                $waitMins = ceil((($intervalMins * 60) - $elapsedSec) / 60);
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => "Next sync in {$waitMins} min(s) (Interval: {$intervalMins}m).",
                    'last_sync' => $lastSync
                ];
            }
        }

        // Concurrency lock using MySQL GET_LOCK (non-blocking, auto-released on disconnect)
        try {
            $lockStmt = $this->pdo->query("SELECT GET_LOCK('zkteco_sync_lock', 0)");
            $hasLock = $lockStmt ? (int)$lockStmt->fetchColumn() : 1;
            if ($hasLock !== 1) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Sync is already running in another process.'
                ];
            }
        } catch (Throwable $t) {}

        if (empty($mdbPath) || !file_exists($mdbPath)) {
            $this->releaseSyncLock();
            $err = "Database file not accessible at: " . ($mdbPath ?: 'empty path');
            $this->updateSyncStatus('error', $err);
            return ['success' => false, 'message' => $err];
        }

        // Fast file modification time check with stat cache cleared
        @clearstatcache(true, $mdbPath);
        $mtime = @filemtime($mdbPath);
        $lastFileMtime = isset($settings['mdb_last_file_mtime']) ? (int)$settings['mdb_last_file_mtime'] : null;

        // If file timestamp is identical to our last imported check, and not forced, skip
        if ($mtime !== false && !empty($lastFileMtime) && $mtime === $lastFileMtime && !$forced) {
            $this->releaseSyncLock();
            $this->updateSyncStatus('success', 'File unchanged, no new punches.');
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Database file has not changed since last sync.',
                'last_sync' => date('Y-m-d H:i:s')
            ];
        }

        // Incremental starting point
        $startWindow = null;
        if (!empty($lastPunch)) {
            $startWindow = date('Y-m-d H:i:s', strtotime($lastPunch) - 300);
        } else {
            $startWindow = date('Y-m-d 00:00:00');
        }

        $result = $this->importPunches($mdbPath, $startWindow, null, 2000);
        $this->releaseSyncLock();
        if ($result['success']) {
            try {
                $this->attService->healOrphanMorningCheckouts();
            } catch (Throwable $t) {}

            $stmtMax = $this->pdo->query("SELECT MAX(punch_time) FROM zkteco_attendance_logs");
            $newestPunch = $stmtMax ? $stmtMax->fetchColumn() : null;
            $newestPunch = $newestPunch ?: ($lastPunch ?: date('Y-m-d H:i:s'));

            $statusMsg = "Synced at " . date('h:i A') . ": " . ($result['stats']['applied'] ?? 0) . " applied, " . ($result['stats']['duplicate'] ?? 0) . " dupes.";
            $stmtUp = $this->pdo->prepare("
                UPDATE zkteco_settings 
                SET mdb_last_sync_time = NOW(),
                    mdb_last_punch_time = ?,
                    mdb_last_sync_status = ?,
                    mdb_last_file_mtime = ?
                WHERE id = 1
            ");
            $stmtUp->execute([$newestPunch, $statusMsg, $mtime ?: time()]);

            $result['last_sync'] = date('Y-m-d H:i:s');
            $result['status_msg'] = $statusMsg;
        } else {
            $this->updateSyncStatus('error', $result['message'] ?? 'Import failed');
        }

        return $result;
    }

    private function releaseSyncLock() {
        try {
            $this->pdo->query("SELECT RELEASE_LOCK('zkteco_sync_lock')");
        } catch (Throwable $t) {}
    }

    private function updateSyncStatus($type, $msg) {
        try {
            $statusText = ($type === 'error' ? '❌ ' : '✔ ') . $msg;
            $stmt = $this->pdo->prepare("UPDATE zkteco_settings SET mdb_last_sync_time = NOW(), mdb_last_sync_status = ? WHERE id = 1");
            $stmt->execute([$statusText]);
        } catch (Exception $e) {}
    }
}

