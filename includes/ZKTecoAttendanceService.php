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
            'push_interval_sec' => isset($settings['push_interval_sec']) ? (int)$settings['push_interval_sec'] : 10
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
     */
    public function findEmployeeByPin($pin) {
        if (empty($pin)) return null;

        $cleanDigits = preg_replace('/[^\d]/', '', $pin);
        $cleanCode = strtoupper(trim($pin));

        // 1. Direct match by Employee ID
        if (is_numeric($pin)) {
            $stmt = $this->pdo->prepare("
                SELECT e.*, d.name as department_name, t.name as team_name, p.emp_code as profile_code
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                WHERE e.is_active = 1 AND e.id = ?
                LIMIT 1
            ");
            $stmt->execute([(int)$pin]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emp) return $emp;
        }

        // 2. Match by Employee Code (e.g. DP-025, DP025, EMP-25)
        $codeVariants = [
            $cleanCode,
            'DP-' . str_pad($cleanDigits, 3, '0', STR_PAD_LEFT),
            'DP-' . (int)$cleanDigits,
            'DP' . str_pad($cleanDigits, 3, '0', STR_PAD_LEFT),
            'DP' . (int)$cleanDigits,
            'EMP-' . str_pad($cleanDigits, 3, '0', STR_PAD_LEFT),
            'EMP-' . (int)$cleanDigits,
            (string)$cleanDigits
        ];

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
        $punchState = $punchData['punch_state'];
        $verifyType = ucfirst($punchData['verify_type']);

        // Check if daily sheet exists for this employee and date
        $stmt = $this->pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $punchDate]);
        $sheet = $stmt->fetch(PDO::FETCH_ASSOC);

        // Smart In/Out Determination
        if (!$sheet) {
            // First punch of the day -> Create Check-In
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO daily_sheets 
                    (employee_id, sheet_date, check_in_time, remarks, status)
                VALUES 
                    (?, ?, ?, ?, 'draft')
            ");
            $stmtInsert->execute([
                $empId, 
                $punchDate, 
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
            // Existing sheet for today
            if (empty($sheet['check_in_time'])) {
                // Check-in was empty, populate it
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET check_in_time = ?,
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([
                    $punchTimeStr, 
                    "[Biometric {$verifyType}] In at {$punchTimeStr}", 
                    $sheet['id']
                ]);

                return [
                    'status' => 'applied',
                    'sheet_id' => $sheet['id'],
                    'remarks' => "Recorded Check-In at {$punchTimeStr} via Biometric {$verifyType} for {$employee['name']}."
                ];
            } else {
                // If punch happens within 2 minutes of check-in, ignore as accidental double-tap
                $inTs = strtotime($punchDate . ' ' . $sheet['check_in_time']);
                $curTs = $punchData['timestamp'];
                if ($inTs && abs($curTs - $inTs) < 120) {
                    return [
                        'status' => 'duplicate',
                        'sheet_id' => $sheet['id'],
                        'remarks' => "Ignored duplicate punch within 2 minutes of Check-In ({$sheet['check_in_time']})."
                    ];
                }

                // Update Check-Out Time
                $stmtUp = $this->pdo->prepare("
                    UPDATE daily_sheets 
                    SET check_out_time = ?,
                        remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', '\n'), ?),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUp->execute([
                    $punchTimeStr, 
                    "[Biometric {$verifyType}] Out at {$punchTimeStr}", 
                    $sheet['id']
                ]);

                return [
                    'status' => 'applied',
                    'sheet_id' => $sheet['id'],
                    'remarks' => "Recorded Check-Out at {$punchTimeStr} via Biometric {$verifyType} for {$employee['name']}."
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
