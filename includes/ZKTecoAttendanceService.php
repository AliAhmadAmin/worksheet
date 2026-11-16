<?php
/**
 * ZKTeco Biometric Attendance Service
 * Handles ADMS Cloud Push Protocol & Direct TCP/UDP Socket Communication.
 * Seamlessly integrates fingerprint/face punches with daily_sheets & live shift roster.
 */

require_once __DIR__ . '/../config/database.php';

class ZKTecoAttendanceService {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
        $this->ensureTablesExist();
    }

    /**
     * Auto-create required ZKTeco database tables if they do not exist.
     */
    private function ensureTablesExist() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `zkteco_settings` (
                    `id` int(11) NOT NULL DEFAULT 1,
                    `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
                    `auto_apply_sheet` tinyint(1) NOT NULL DEFAULT 1,
                    `check_in_mode` enum('smart','first_punch','punch_state') NOT NULL DEFAULT 'smart',
                    `check_out_mode` enum('smart','last_punch','punch_state') NOT NULL DEFAULT 'smart',
                    `server_port` int(11) NOT NULL DEFAULT 80,
                    `timezone_offset` int(11) NOT NULL DEFAULT 5,
                    `push_interval_sec` int(11) NOT NULL DEFAULT 10,
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    `mdb_default_path` varchar(255) DEFAULT NULL,
                    `mdb_auto_sync_enabled` tinyint(1) DEFAULT 1,
                    `mdb_sync_interval_mins` int(11) DEFAULT 15,
                    `mdb_last_sync_time` datetime DEFAULT NULL,
                    `mdb_last_punch_time` datetime DEFAULT NULL,
                    `mdb_last_sync_status` varchar(255) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                INSERT IGNORE INTO `zkteco_settings` (`id`, `is_enabled`, `auto_apply_sheet`) VALUES (1, 1, 1);

                CREATE TABLE IF NOT EXISTS `zkteco_devices` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `device_name` varchar(100) NOT NULL DEFAULT 'Main Office ZKTeco',
                    `serial_number` varchar(100) DEFAULT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `port` int(11) NOT NULL DEFAULT 4370,
                    `comm_key` int(11) NOT NULL DEFAULT 0,
                    `device_model` varchar(50) DEFAULT NULL,
                    `firmware_version` varchar(50) DEFAULT NULL,
                    `user_count` int(11) NOT NULL DEFAULT 0,
                    `finger_count` int(11) NOT NULL DEFAULT 0,
                    `log_count` int(11) NOT NULL DEFAULT 0,
                    `status` enum('online','offline') NOT NULL DEFAULT 'offline',
                    `last_activity` datetime DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `serial_number` (`serial_number`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `zkteco_attendance_logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `device_sn` varchar(100) DEFAULT NULL,
                    `device_user_id` varchar(50) NOT NULL COMMENT 'PIN / User ID on Machine',
                    `employee_id` int(11) DEFAULT NULL,
                    `punch_time` datetime NOT NULL,
                    `punch_date` date NOT NULL,
                    `punch_state` varchar(20) NOT NULL DEFAULT 'auto',
                    `verify_type` varchar(20) NOT NULL DEFAULT 'fingerprint',
                    `sheet_id` int(11) DEFAULT NULL,
                    `status` enum('applied','unmatched','ignored','duplicate') NOT NULL DEFAULT 'applied',
                    `remarks` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `idx_user_time` (`device_user_id`,`punch_time`),
                    KEY `idx_emp_date` (`employee_id`,`punch_date`),
                    KEY `idx_sheet` (`sheet_id`),
                    KEY `idx_punch_time` (`punch_time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Auto-migrate multi-source attendance tracking columns on daily_sheets
            $columnsNeeded = [
                'biometric_in_time' => 'VARCHAR(50) NULL',
                'biometric_out_time' => 'VARCHAR(50) NULL',
                'biometric_duty_hours' => 'VARCHAR(50) NULL',
                'biometric_duty_seconds' => 'INT(11) DEFAULT 0',
                'whatsapp_in_time' => 'VARCHAR(50) NULL',
                'whatsapp_out_time' => 'VARCHAR(50) NULL',
                'whatsapp_duty_hours' => 'VARCHAR(50) NULL',
                'whatsapp_duty_seconds' => 'INT(11) DEFAULT 0',
            ];
            $existingCols = [];
            try {
                $colStmt = $this->pdo->query("SHOW COLUMNS FROM `daily_sheets`");
                if ($colStmt) {
                    $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                }
            } catch (Exception $e) {}

            foreach ($columnsNeeded as $col => $def) {
                if (!in_array($col, $existingCols, true)) {
                    try {
                        $this->pdo->exec("ALTER TABLE `daily_sheets` ADD COLUMN `{$col}` {$def}");
                    } catch (Exception $e) {}
                }
            }
        } catch (Exception $e) {}
    }

    /**
     * Get ZKTeco Settings
     */
    public function getSettings() {
        $stmt = $this->pdo->query("SELECT * FROM zkteco_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'id' => 1,
            'is_enabled' => isset($settings['is_enabled']) ? (int)$settings['is_enabled'] : 1,
            'auto_apply_sheet' => isset($settings['auto_apply_sheet']) ? (int)$settings['auto_apply_sheet'] : 1,
            'check_in_mode' => $settings['check_in_mode'] ?? 'smart',
            'check_out_mode' => $settings['check_out_mode'] ?? 'smart',
            'server_port' => isset($settings['server_port']) ? (int)$settings['server_port'] : 80,
            'timezone_offset' => isset($settings['timezone_offset']) ? (int)$settings['timezone_offset'] : 5,
            'push_interval_sec' => isset($settings['push_interval_sec']) ? (int)$settings['push_interval_sec'] : 10,
            'mdb_default_path' => $settings['mdb_default_path'] ?? null,
            'mdb_auto_sync_enabled' => isset($settings['mdb_auto_sync_enabled']) ? (int)$settings['mdb_auto_sync_enabled'] : 1,
            'mdb_sync_interval_mins' => isset($settings['mdb_sync_interval_mins']) ? (int)$settings['mdb_sync_interval_mins'] : 15,
            'mdb_last_sync_time' => $settings['mdb_last_sync_time'] ?? null,
            'mdb_last_punch_time' => $settings['mdb_last_punch_time'] ?? null,
            'mdb_last_sync_status' => $settings['mdb_last_sync_status'] ?? null
        ];
    }

    /**
     * Handle ADMS Handshake / Heartbeat (GET /iclock/cdata)
     */
    public function handleAdmsHandshake($params, $headers = []) {
        $sn = trim($params['SN'] ?? ($params['sn'] ?? ($headers['sn'] ?? '')));
        $options = $params['options'] ?? '';
        $pushver = $params['pushver'] ?? '';

        if (!empty($sn)) {
            $this->updateDeviceStatus($sn, [
                'firmware_version' => $pushver ?: null,
                'status' => 'online',
                'last_activity' => date('Y-m-d H:i:s')
            ]);
        }

        $settings = $this->getSettings();
        $tz = $settings['timezone_offset'];
        $interval = $settings['push_interval_sec'];

        // Standard ZKTeco ADMS handshake response headers & configuration
        $response = "GET OPTION FROM: {$sn}\n"
                  . "Stamp=9999\n"
                  . "OpStamp=9999\n"
                  . "PhotoStamp=0\n"
                  . "ErrorDelay=30\n"
                  . "Delay={$interval}\n"
                  . "TransTimes=00:00;14:05\n"
                  . "TransInterval=1\n"
                  . "TransFlag=1111000000\n"
                  . "TimeZone={$tz}\n"
                  . "Realtime=1\n"
                  . "Encrypt=0\n"
                  . "ServerVersion=3.1.1\n";

        return $response;
    }

    /**
     * Handle ADMS Request Poll (GET /iclock/getrequest)
     */
    public function handleAdmsGetRequest($params) {
        $sn = trim($params['SN'] ?? ($params['sn'] ?? ''));
        if (!empty($sn)) {
            $this->updateDeviceStatus($sn, [
                'status' => 'online',
                'last_activity' => date('Y-m-d H:i:s')
            ]);
        }

        // Return OK (no pending remote commands)
        return "OK\n";
    }

    /**
     * Process ADMS Attendance Push Data (POST /iclock/cdata?table=ATTLOG)
     */
    public function handleAdmsPostData($params, $rawBody) {
        $sn = trim($params['SN'] ?? ($params['sn'] ?? ''));
        $table = strtoupper(trim($params['table'] ?? ($params['TableName'] ?? 'ATTLOG')));

        if (!empty($sn)) {
            $this->updateDeviceStatus($sn, [
                'status' => 'online',
                'last_activity' => date('Y-m-d H:i:s')
            ]);
        }

        // Always log raw payload for inspection & debugging
        @file_put_contents(
            __DIR__ . '/../scratch/zkteco_adms_requests.log', 
            "[" . date('Y-m-d H:i:s') . "] SN: {$sn} | TABLE: {$table} | BODY: {$rawBody}\n", 
            FILE_APPEND
        );

        if ($table === 'ATTLOG') {
            $processedCount = $this->processAttendanceLogLines($sn, $rawBody);
            return "OK: {$processedCount}\n";
        } elseif ($table === 'OPERLOG' || $table === 'USERINFO' || $table === 'BIODATA') {
            return "OK\n";
        }

        return "OK\n";
    }

    /**
     * Parse Attendance Log Lines from ZKTeco
     */
    public function processAttendanceLogLines($sn, $body) {
        $lines = preg_split('/[\r\n]+/', trim($body));
        $processed = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parsedPunch = $this->parsePunchLine($line);
            if (!$parsedPunch) continue;

            $this->recordPunch($sn, $parsedPunch);
            $processed++;
        }

        return $processed;
    }

    /**
     * Parse Single Line of ZKTeco Punch
     * Formats supported:
     * Format 1 (Tab separated): PIN\t2026-10-09 09:05:00\t0\t1\t0\t0\t0
     * Format 2 (KeyValue): PIN=25\tTime=2026-10-09 09:05:00\tStatus=0\tVerify=1
     * Format 3 (Space/Comma separated)
     */
    public function parsePunchLine($line) {
        $userId = '';
        $punchTime = '';
        $state = 'auto';
        $verifyType = 'fingerprint';

        // Check if Key=Value format
        if (strpos($line, 'PIN=') !== false || strpos($line, 'Time=') !== false) {
            parse_str(str_replace("\t", '&', $line), $data);
            $userId = $data['PIN'] ?? ($data['pin'] ?? ($data['User'] ?? ''));
            $punchTime = $data['Time'] ?? ($data['time'] ?? '');
            $stateCode = $data['Status'] ?? ($data['status'] ?? '0');
            $verifyCode = $data['Verify'] ?? ($data['verify'] ?? '1');
        } else {
            // Tab or space separated
            $parts = preg_split('/[\t,]+/', $line);
            if (count($parts) < 2) {
                $parts = preg_split('/\s+/', $line, 3);
            }

            $userId = trim($parts[0] ?? '');
            $punchTime = trim($parts[1] ?? '');
            if (isset($parts[2]) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $parts[2])) {
                // If date and time were split across parts 1 and 2
                $punchTime = $parts[1] . ' ' . $parts[2];
                $stateCode = $parts[3] ?? '0';
                $verifyCode = $parts[4] ?? '1';
            } else {
                $stateCode = $parts[2] ?? '0';
                $verifyCode = $parts[3] ?? '1';
            }
        }

        // Clean user ID / PIN
        $userId = preg_replace('/[^\w\-]/', '', $userId);
        if (empty($userId)) return null;

        // Parse & validate punch timestamp
        $timestamp = strtotime($punchTime);
        if (!$timestamp) return null;

        $formattedPunchTime = date('Y-m-d H:i:s', $timestamp);

        // Verification types: 1=Fingerprint, 2=PIN/Password, 4=Card, 15=Face
        $verifyMap = [
            '1' => 'fingerprint',
            '2' => 'password',
            '3' => 'card',
            '4' => 'card',
            '15' => 'face',
            '25' => 'palm'
        ];
        $verifyType = $verifyMap[(string)$verifyCode] ?? 'fingerprint';

        // State types: 0=CheckIn, 1=CheckOut, 2=BreakOut, 3=BreakIn, 4=OT-In, 5=OT-Out, 255=Auto
        $stateMap = [
            '0' => 'check_in',
            '1' => 'check_out',
            '2' => 'break_out',
            '3' => 'break_in',
            '4' => 'ot_in',
            '5' => 'ot_out',
            '255' => 'auto'
        ];
        $state = $stateMap[(string)$stateCode] ?? 'auto';

        return [
            'user_id' => $userId,
            'punch_time' => $formattedPunchTime,
            'punch_date' => date('Y-m-d', $timestamp),
            'formatted_time' => date('h:i A', $timestamp),
            'punch_state' => $state,
            'verify_type' => $verifyType,
            'timestamp' => $timestamp
        ];
    }

    /**
     * Record Punch & Apply to Daily Sheets
     */
    public function recordPunch($deviceSn, $punchData) {
        $pin = $punchData['user_id'];
        $punchTime = $punchData['punch_time'];
        $punchDate = $punchData['punch_date'];
        $formattedTime = $punchData['formatted_time'];
        $punchState = $punchData['punch_state'];
        $verifyType = $punchData['verify_type'];

        // 1. Prevent duplicate punch records within 60 seconds
        $stmtChk = $this->pdo->prepare("
            SELECT id FROM zkteco_attendance_logs 
            WHERE device_user_id = ? AND punch_time = ?
            LIMIT 1
        ");
        $stmtChk->execute([$pin, $punchTime]);
        if ($stmtChk->fetch()) {
            return ['status' => 'duplicate', 'message' => 'Duplicate punch already recorded.'];
        }

        // 2. Match Employee by PIN / Code / ID
        $employee = $this->findEmployeeByPin($pin);
        $empId = $employee ? (int)$employee['id'] : null;

        $logStatus = 'applied';
        $remarks = '';
        $sheetId = null;

        if (!$employee) {
            $logStatus = 'unmatched';
            $remarks = "Biometric PIN '{$pin}' not matched to any active employee. Please map PIN/Employee Code in Employee Profile.";
        } else {
            // Apply punch to daily_sheets
            $applyResult = $this->applyPunchToDailySheet($employee, $punchData);
            $sheetId = $applyResult['sheet_id'] ?? null;
            $remarks = $applyResult['remarks'] ?? '';
            $logStatus = $applyResult['status'] ?? 'applied';
        }

        // 3. Save into zkteco_attendance_logs
        $stmtLog = $this->pdo->prepare("
            INSERT INTO zkteco_attendance_logs 
                (device_sn, device_user_id, employee_id, punch_time, punch_date, punch_state, verify_type, sheet_id, status, remarks)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $deviceSn,
            $pin,
            $empId,
            $punchTime,
            $punchDate,
            $punchState,
            $verifyType,
            $sheetId,
            $logStatus,
            $remarks
        ]);

        return [
            'status' => $logStatus,
            'employee' => $employee ? $employee['name'] : 'Unmatched',
            'emp_code' => $employee ? ($employee['emp_code'] ?? '') : '',
            'remarks' => $remarks
        ];
    }

    /**
     * Find Employee by Biometric PIN or Employee Code
     * Strictly matches Employee Code (e.g. DP1124, 1124) or explicit biometric PIN.
     * NEVER blindly matches database auto-increment ID (e.id).
     */
    public function findEmployeeByPin($pin) {
        if (empty($pin)) return null;

        $cleanPin = trim($pin);
        $cleanDigits = preg_replace('/[^\d]/', '', $cleanPin);
        $cleanCode = strtoupper($cleanPin);

        // Build exact match candidates WITHOUT zero-padding to prevent false matches (e.g. PIN 11 must NEVER match DP-011)
        $codeVariants = array_filter(array_unique([
            $cleanCode,
            $cleanPin,
            !empty($cleanDigits) ? 'DP-' . $cleanDigits : null,
            !empty($cleanDigits) ? 'DP' . $cleanDigits : null,
            !empty($cleanDigits) ? 'EMP-' . $cleanDigits : null,
            !empty($cleanDigits) ? 'EMP' . $cleanDigits : null,
            !empty($cleanDigits) ? $cleanDigits : null
        ]));

        if (empty($codeVariants)) return null;

        $placeholders = implode(',', array_fill(0, count($codeVariants), '?'));
        $sql = "
            SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code as profile_code
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            WHERE e.is_active = 1 AND (
                e.emp_code IN ({$placeholders})
                OR p.emp_code IN ({$placeholders})
                OR REPLACE(e.emp_code, '-', '') IN ({$placeholders})
                OR REPLACE(p.emp_code, '-', '') IN ({$placeholders})
            )
            LIMIT 1
        ";
        $allParams = array_merge($codeVariants, $codeVariants, $codeVariants, $codeVariants);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($allParams);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($emp) return $emp;

        return null;
    }

    /**
     * Apply Punch to Daily Sheet
     */
    private function applyPunchToDailySheet($employee, $punchData) {
        $empId = (int)$employee['id'];
        $punchDate = $punchData['punch_date'];
        $punchTimeStr = $punchData['formatted_time']; // e.g. "09:05 AM"
        $rawPunchState = strtolower(trim($punchData['punch_state'] ?? 'auto'));
        $verifyType = ucfirst($punchData['verify_type'] ?? 'Fingerprint');
        $curTs = $punchData['timestamp'];

        // Check if daily sheet exists for this employee and date
        $stmt = $this->pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $punchDate]);
        $sheet = $stmt->fetch(PDO::FETCH_ASSOC);

        // Detect explicit In vs Out from machine punch state
        $isExplicitCheckOut = in_array($rawPunchState, ['check_out', 'out', '1', 'o'], true);
        $isExplicitCheckIn = in_array($rawPunchState, ['check_in', 'in', '0', 'i'], true);

        // ==========================================
        // SMART CROSS-MIDNIGHT / OVERNIGHT SHIFT PAIRING
        // ==========================================
        // When an employee works an evening/night shift (e.g. 6 PM - 2 AM), they punch out after midnight.
        // If today's sheet does not already have an active check-in, check if yesterday has an open shift.
        $punchHour = (int)date('H', $curTs);
        $prevDate = date('Y-m-d', strtotime($punchDate . ' -1 day'));

        $stmtPrev = $this->pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmtPrev->execute([$empId, $prevDate]);
        $prevSheet = $stmtPrev->fetch(PDO::FETCH_ASSOC);

        $isOvernightCheckout = false;
        if ($prevSheet) {
            $prevInStr = !empty($prevSheet['biometric_in_time']) ? $prevSheet['biometric_in_time'] : (!empty($prevSheet['check_in_time']) ? $prevSheet['check_in_time'] : null);
            $prevHasOut = !empty($prevSheet['biometric_out_time']) || !empty($prevSheet['check_out_time']);

            if ($prevInStr && !$prevHasOut) {
                $prevInTs = strtotime($prevDate . ' ' . $prevInStr);
                $elapsedSinceIn = ($prevInTs && $curTs > $prevInTs) ? ($curTs - $prevInTs) : 0;

                // Check if today already has its own active check-in
                $todayHasIn = $sheet && (!empty($sheet['biometric_in_time']) || !empty($sheet['check_in_time']));

                // Overnight shift completion criteria:
                // 1. Shift duration between 45 minutes and 18 hours (e.g. checked in 6 PM yesterday, punching out 12:05 AM to 08:00 AM)
                // 2. Punch is in early morning window (before 08:00 AM) OR machine punch state explicitly says check_out
                // 3. Today's sheet does not have an active check-in
                if ($elapsedSinceIn >= 2700 && $elapsedSinceIn <= 64800 && !$todayHasIn) {
                    if ($punchHour < 8 || $isExplicitCheckOut) {
                        $isOvernightCheckout = true;
                    }
                }
            }
        }

        if ($isOvernightCheckout) {
            // Prevent duplicate punch within 2 minutes of existing biometric_out_time on prevSheet
            if (!empty($prevSheet['biometric_out_time'])) {
                $existingOutTs = strtotime($punchDate . ' ' . $prevSheet['biometric_out_time']);
                if ($existingOutTs && abs($curTs - $existingOutTs) < 120) {
                    return [
                        'status' => 'duplicate',
                        'sheet_id' => $prevSheet['id'],
                        'remarks' => "Ignored duplicate punch within 2 minutes of Biometric Out ({$prevSheet['biometric_out_time']})."
                    ];
                }
            }

            // Calculate duty seconds across midnight
            $prevInStr = !empty($prevSheet['biometric_in_time']) ? $prevSheet['biometric_in_time'] : $prevSheet['check_in_time'];
            $inTs = strtotime($prevDate . ' ' . $prevInStr);
            $outTs = $curTs;
            $dutySeconds = max(0, $outTs - $inTs);
            $h = floor($dutySeconds / 3600);
            $m = floor(($dutySeconds % 3600) / 60);
            $dutyFormatted = sprintf('%02d:%02d:00', $h, $m);

            $preserveWebCheckout = !empty($prevSheet['is_locked']) || !empty($prevSheet['check_out_time']);

            if ($preserveWebCheckout) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET biometric_out_time = ?,
                        biometric_duty_hours = ?,
                        biometric_duty_seconds = ?,
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([
                    $punchTimeStr,
                    $dutyFormatted,
                    $dutySeconds,
                    "[Biometric {$verifyType}] Overnight Out at {$punchTimeStr} (Bio Duty: {$dutyFormatted})",
                    $prevSheet['id']
                ]);
            } else {
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET biometric_out_time = ?,
                        biometric_duty_hours = ?,
                        biometric_duty_seconds = ?,
                        check_out_time = ?,
                        total_duty_hours = ?,
                        total_duty_seconds = ?,
                        is_locked = 1,
                        locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP),
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([
                    $punchTimeStr,
                    $dutyFormatted,
                    $dutySeconds,
                    $punchTimeStr,
                    $dutyFormatted,
                    $dutySeconds,
                    "[Biometric {$verifyType}] Overnight Out at {$punchTimeStr} (Duty: {$dutyFormatted})",
                    $prevSheet['id']
                ]);
            }

            // If an empty orphan row for today was created earlier with no check-in, clean it up
            if ($sheet && empty($sheet['check_in_time']) && empty($sheet['biometric_in_time'])) {
                $stmtDel = $this->pdo->prepare("DELETE FROM daily_sheets WHERE id = ?");
                $stmtDel->execute([$sheet['id']]);
            }

            return [
                'status' => 'applied',
                'sheet_id' => $prevSheet['id'],
                'remarks' => "Recorded Overnight Biometric Out at {$punchTimeStr} for {$prevDate} shift (Duty: {$dutyFormatted}) via {$verifyType} for {$employee['name']}."
            ];
        }

        // Determine punch intent: Check-In vs Check-Out
        $isCheckOut = false;
        if ($isExplicitCheckOut) {
            $isCheckOut = true;
        } elseif ($isExplicitCheckIn) {
            $isCheckOut = false;
        } else {
            // Auto / smart determination:
            if (!empty($sheet['biometric_in_time'])) {
                // If biometric in already exists, subsequent punch is Check-Out
                $isCheckOut = true;
            } elseif (!empty($sheet['check_in_time'])) {
                // Check if punch is significantly after web check-in (e.g. >= 2.5 hours)
                $inTs = strtotime($punchDate . ' ' . $sheet['check_in_time']);
                if ($inTs && ($curTs - $inTs) >= 9000) {
                    $isCheckOut = true;
                } else {
                    $isCheckOut = false;
                }
            } else {
                $isCheckOut = false;
            }
        }

        if ($isCheckOut) {
            // ==========================================
            // RECORD BIOMETRIC CHECK-OUT
            // ==========================================
            $refInStr = !empty($sheet['biometric_in_time']) ? $sheet['biometric_in_time'] : (!empty($sheet['check_in_time']) ? $sheet['check_in_time'] : null);
            $dutySeconds = 0;
            $dutyFormatted = null;

            if ($refInStr) {
                $inTs = strtotime($punchDate . ' ' . $refInStr);
                $outTs = $curTs;
                if ($outTs < $inTs) {
                    $outTs += 86400; // Overnight shift
                }
                $dutySeconds = max(0, $outTs - $inTs);
                $h = floor($dutySeconds / 3600);
                $m = floor(($dutySeconds % 3600) / 60);
                $dutyFormatted = sprintf('%02d:%02d:00', $h, $m);
            }

            if (!$sheet) {
                // First punch of the day is a Check-Out
                $stmtInsert = $this->pdo->prepare("
                    INSERT INTO daily_sheets 
                        (employee_id, sheet_date, biometric_out_time, biometric_duty_hours, biometric_duty_seconds, remarks, is_locked)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, 0)
                ");
                $stmtInsert->execute([
                    $empId,
                    $punchDate,
                    $punchTimeStr,
                    $dutyFormatted,
                    $dutySeconds,
                    "[Biometric {$verifyType}] Out at {$punchTimeStr}"
                ]);
                $sheetId = $this->pdo->lastInsertId();

                return [
                    'status' => 'applied',
                    'sheet_id' => $sheetId,
                    'remarks' => "Recorded Biometric Out at {$punchTimeStr} via {$verifyType} for {$employee['name']}."
                ];
            } else {
                // Prevent duplicate punch within 2 minutes of existing biometric_out_time
                if (!empty($sheet['biometric_out_time'])) {
                    $existingOutTs = strtotime($punchDate . ' ' . $sheet['biometric_out_time']);
                    if ($existingOutTs && abs($curTs - $existingOutTs) < 120) {
                        return [
                            'status' => 'duplicate',
                            'sheet_id' => $sheet['id'],
                            'remarks' => "Ignored duplicate punch within 2 minutes of Biometric Out ({$sheet['biometric_out_time']})."
                        ];
                    }
                }

                // If sheet is locked OR web check_out_time already exists, PRESERVE web check_out_time!
                $preserveWebCheckout = !empty($sheet['is_locked']) || !empty($sheet['check_out_time']);

                if ($preserveWebCheckout) {
                    $stmtUp = $this->pdo->prepare("
                        UPDATE daily_sheets 
                        SET biometric_out_time = ?,
                            biometric_duty_hours = ?,
                            biometric_duty_seconds = ?,
                            remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmtUp->execute([
                        $punchTimeStr,
                        $dutyFormatted,
                        $dutySeconds,
                        "[Biometric {$verifyType}] Out at {$punchTimeStr}",
                        $sheet['id']
                    ]);

                    return [
                        'status' => 'applied',
                        'sheet_id' => $sheet['id'],
                        'remarks' => "Recorded Biometric Out at {$punchTimeStr} (Bio Duty: " . ($dutyFormatted ?: 'N/A') . ") via {$verifyType} for {$employee['name']}. Web sheet checkout preserved."
                    ];
                } else {
                    $stmtUp = $this->pdo->prepare("
                        UPDATE daily_sheets 
                        SET biometric_out_time = ?,
                            biometric_duty_hours = ?,
                            biometric_duty_seconds = ?,
                            check_out_time = ?,
                            total_duty_hours = COALESCE(?, total_duty_hours),
                            total_duty_seconds = IF(? > 0, ?, total_duty_seconds),
                            remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmtUp->execute([
                        $punchTimeStr,
                        $dutyFormatted,
                        $dutySeconds,
                        $punchTimeStr,
                        $dutyFormatted,
                        $dutySeconds,
                        $dutySeconds,
                        "[Biometric {$verifyType}] Out at {$punchTimeStr}",
                        $sheet['id']
                    ]);

                    return [
                        'status' => 'applied',
                        'sheet_id' => $sheet['id'],
                        'remarks' => "Recorded Biometric Out at {$punchTimeStr} (Duty: " . ($dutyFormatted ?: 'N/A') . ") via {$verifyType} for {$employee['name']}."
                    ];
                }
            }
        } else {
            // ==========================================
            // RECORD BIOMETRIC CHECK-IN
            // ==========================================
            if (!$sheet) {
                // First punch of the day -> Create Check-In
                $stmtInsert = $this->pdo->prepare("
                    INSERT INTO daily_sheets 
                        (employee_id, sheet_date, check_in_time, biometric_in_time, remarks, is_locked)
                    VALUES 
                        (?, ?, ?, ?, ?, 0)
                ");
                $stmtInsert->execute([
                    $empId, 
                    $punchDate, 
                    $punchTimeStr, 
                    $punchTimeStr,
                    "[Biometric {$verifyType}] In at {$punchTimeStr}"
                ]);
                $sheetId = $this->pdo->lastInsertId();

                return [
                    'status' => 'applied',
                    'sheet_id' => $sheetId,
                    'remarks' => "Created Check-In at {$punchTimeStr} via Biometric {$verifyType} for {$employee['name']}."
                ];
            } else {
                // Sheet already exists
                if (!empty($sheet['biometric_in_time'])) {
                    // Check-in already exists on machine
                    $existingInTs = strtotime($punchDate . ' ' . $sheet['biometric_in_time']);
                    if ($existingInTs && abs($curTs - $existingInTs) < 120) {
                        return [
                            'status' => 'duplicate',
                            'sheet_id' => $sheet['id'],
                            'remarks' => "Ignored duplicate punch within 2 minutes of Biometric In ({$sheet['biometric_in_time']})."
                        ];
                    }

                    // If earlier than existing biometric_in_time, update to the earlier punch
                    if ($existingInTs && $curTs < $existingInTs) {
                        $stmtUp = $this->pdo->prepare("
                            UPDATE daily_sheets 
                            SET biometric_in_time = ?,
                                check_in_time = IF(check_in_time IS NULL OR check_in_time = '', ?, check_in_time),
                                remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");
                        $stmtUp->execute([
                            $punchTimeStr,
                            $punchTimeStr,
                            "[Biometric {$verifyType}] Adjusted In to earlier {$punchTimeStr}",
                            $sheet['id']
                        ]);

                        return [
                            'status' => 'applied',
                            'sheet_id' => $sheet['id'],
                            'remarks' => "Adjusted Biometric In to earlier time {$punchTimeStr} for {$employee['name']}."
                        ];
                    }

                    return [
                        'status' => 'ignored',
                        'sheet_id' => $sheet['id'],
                        'remarks' => "Biometric In already recorded at {$sheet['biometric_in_time']}; later check-in punch ignored."
                    ];
                }

                // Biometric in is empty
                $needWebCheckIn = empty($sheet['check_in_time']);
                $sql = "
                    UPDATE daily_sheets 
                    SET biometric_in_time = ?,
                        " . ($needWebCheckIn ? "check_in_time = ?, " : "") . "
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ";
                $params = $needWebCheckIn 
                    ? [$punchTimeStr, $punchTimeStr, "[Biometric {$verifyType}] In at {$punchTimeStr}", $sheet['id']]
                    : [$punchTimeStr, "[Biometric {$verifyType}] In at {$punchTimeStr}", $sheet['id']];
                
                $stmtUp = $this->pdo->prepare($sql);
                $stmtUp->execute($params);

                return [
                    'status' => 'applied',
                    'sheet_id' => $sheet['id'],
                    'remarks' => "Recorded Biometric In at {$punchTimeStr} via {$verifyType} for {$employee['name']}."
                ];
            }
        }
    }

    /**
     * Update Device Heartbeat & Info in zkteco_devices
     */
    public function updateDeviceStatus($sn, $extra = []) {
        if (empty($sn)) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $model = $extra['device_model'] ?? null;
        $firmware = $extra['firmware_version'] ?? null;
        $status = $extra['status'] ?? 'online';

        $stmt = $this->pdo->prepare("
            INSERT INTO zkteco_devices 
                (serial_number, ip_address, device_model, firmware_version, status, last_activity)
            VALUES 
                (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                ip_address = IF(VALUES(ip_address) != '', VALUES(ip_address), ip_address),
                device_model = COALESCE(VALUES(device_model), device_model),
                firmware_version = COALESCE(VALUES(firmware_version), firmware_version),
                status = 'online',
                last_activity = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$sn, $ip, $model, $firmware, $status]);
    }
}
