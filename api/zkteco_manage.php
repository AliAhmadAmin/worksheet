<?php
/**
 * ZKTeco Management API
 * Provides log monitoring, device registration, and interactive biometric punch testing.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_time_limit(300);
ini_set('memory_limit', '512M');
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ZKTecoAttendanceService.php';
require_once __DIR__ . '/../includes/ZKTecoMdbService.php';

$pdo = getDbConnection();
$service = new ZKTecoAttendanceService($pdo);
$mdbService = new ZKTecoMdbService($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = $_SESSION['user_id'] ?? 1;
$stmtUser = $pdo->prepare("SELECT role, can_manage_employees, can_manage_hr FROM employees WHERE id = ?");
$stmtUser->execute([$currentUserId]);
$user = $stmtUser->fetch() ?: [];

$isAdmin = ($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'super_admin' || !empty($user['can_manage_employees']) || !empty($user['can_manage_hr']);

switch ($action) {
    case 'get_devices':
        $stmt = $pdo->query("SELECT * FROM zkteco_devices ORDER BY id ASC");
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-mark offline if no activity in last 5 minutes
        $updatedDevices = [];
        foreach ($devices as $d) {
            $isOnline = false;
            if (!empty($d['last_activity'])) {
                $diff = time() - strtotime($d['last_activity']);
                if ($diff < 300) { // 5 minutes
                    $isOnline = true;
                }
            }
            $d['is_online'] = $isOnline;
            $updatedDevices[] = $d;
        }

        echo json_encode(['success' => true, 'devices' => $updatedDevices]);
        break;

    case 'get_logs':
        // Only run MDB sync if explicitly requested (e.g. sync=1 or force=1)
        if (!empty($_GET['sync']) || !empty($_GET['force'])) {
            try {
                $forceSync = !empty($_GET['force']);
                $mdbService->syncIncremental($forceSync);
            } catch (Throwable $t) {}
        }

        $dateFilter = $_GET['date'] ?? '';
        $empFilter = $_GET['employee_id'] ?? '';
        $statusFilter = $_GET['status'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        $limit = min(300, max(10, (int)($_GET['limit'] ?? 100)));

        $sql = "
            SELECT l.*, 
                   e.name as employee_name, 
                   COALESCE(p.emp_code, e.emp_code, '') as emp_code, 
                   e.designation, 
                   d.name as department_name,
                   COALESCE(p.phone, p.whatsapp_number, e.phone, '') as phone
            FROM zkteco_attendance_logs l
            LEFT JOIN employees e ON l.employee_id = e.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($dateFilter)) {
            $sql .= " AND l.punch_date = ?";
            $params[] = $dateFilter;
        }

        if (!empty($empFilter)) {
            $sql .= " AND l.employee_id = ?";
            $params[] = (int)$empFilter;
        }

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $sql .= " AND l.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($search)) {
            $wild = '%' . $search . '%';
            $sql .= " AND (e.name LIKE ? OR e.emp_code LIKE ? OR l.device_user_id LIKE ? OR l.remarks LIKE ?)";
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
        }

        $sql .= " ORDER BY l.id DESC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats for target date
        $targetDate = !empty($dateFilter) ? $dateFilter : date('Y-m-d');
        $stmtStats = $pdo->prepare("
            SELECT 
                COUNT(*) as total_punches,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied_count,
                SUM(CASE WHEN status = 'unmatched' THEN 1 ELSE 0 END) as unmatched_count,
                SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicate_count
            FROM zkteco_attendance_logs
            WHERE punch_date = ?
        ");
        $stmtStats->execute([$targetDate]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'stats' => $stats
        ]);
        break;

    case 'simulate_punch':
        $pin = trim($data['pin'] ?? ($data['user_id'] ?? ''));
        $verifyType = trim($data['verify_type'] ?? 'fingerprint');
        $punchTime = trim($data['punch_time'] ?? date('Y-m-d H:i:s'));
        $state = trim($data['punch_state'] ?? 'auto');

        if (empty($pin)) {
            echo json_encode(['success' => false, 'message' => 'Please provide an Employee PIN or Employee Code (e.g. 25, DP-025).']);
            exit;
        }

        $ts = strtotime($punchTime) ?: time();
        $punchData = [
            'user_id' => $pin,
            'punch_time' => date('Y-m-d H:i:s', $ts),
            'punch_date' => date('Y-m-d', $ts),
            'formatted_time' => date('h:i A', $ts),
            'punch_state' => $state,
            'verify_type' => $verifyType,
            'timestamp' => $ts
        ];

        $result = $service->recordPunch('SIMULATOR-01', $punchData);
        echo json_encode([
            'success' => true,
            'result' => $result,
            'message' => "Biometric punch simulated for PIN '{$pin}'. Status: {$result['status']}."
        ]);
        break;

    case 'save_device':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Admin privileges required.']);
            exit;
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $name = trim($data['device_name'] ?? 'Main Office Biometric');
        $sn = trim($data['serial_number'] ?? '');
        $ip = trim($data['ip_address'] ?? '192.168.1.201');
        $port = (int)($data['port'] ?? 4370);
        $commKey = (int)($data['comm_key'] ?? 0);

        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE zkteco_devices 
                SET device_name = ?, serial_number = NULLIF(?, ''), ip_address = ?, port = ?, comm_key = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $sn, $ip, $port, $commKey, $id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO zkteco_devices (device_name, serial_number, ip_address, port, comm_key)
                VALUES (?, NULLIF(?, ''), ?, ?, ?)
            ");
            $stmt->execute([$name, $sn, $ip, $port, $commKey]);
        }

        echo json_encode(['success' => true, 'message' => 'ZKTeco device details saved successfully.']);
        break;

    case 'delete_device':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Admin privileges required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM zkteco_devices WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true, 'message' => 'Device removed successfully.']);
        break;

    case 'get_settings':
        $settings = $service->getSettings();
        
        // Compute suggested local ADMS server URLs
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $port = $_SERVER['SERVER_PORT'] ?? '80';

        // Local IPv4 detection
        $localIp = getHostByName(getHostName());

        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'detected_local_ip' => $localIp,
            'detected_port' => $port,
            'server_domain' => $host,
            'adms_cdata_url' => $protocol . $host . '/Worksheet/iclock/cdata.php'
        ]);
        break;

    case 'get_default_mdb_path':
        $stmt = $pdo->query("SELECT mdb_default_path, mdb_auto_sync_enabled, mdb_sync_interval_mins, mdb_last_sync_time, mdb_last_sync_status FROM zkteco_settings WHERE id = 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        $savedPath = $row['mdb_default_path'] ?? '';
        
        $candidates = [
            'C:\\Program Files (x86)\\ZKTeco\\attBackup.mdb',
            'C:\\Program Files (x86)\\ZKTeco\\att2000.mdb',
            'C:\\Program Files\\ZKTeco\\attBackup.mdb',
            'C:\\Program Files\\ZKTeco\\att2000.mdb',
            'C:\\ZKTeco\\attBackup.mdb',
            'C:\\ZKTeco\\att2000.mdb',
            'C:\\attBackup.mdb',
            'C:\\att2000.mdb'
        ];
        $detectedPath = '';
        if (!empty($savedPath) && file_exists($savedPath)) {
            $detectedPath = $savedPath;
        } else {
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $detectedPath = $cand;
                    break;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'default_path' => $detectedPath ?: ($savedPath ?: 'C:\\Program Files (x86)\\ZKTeco\\attBackup.mdb'),
            'saved_path' => $savedPath,
            'file_exists' => !empty($detectedPath) && file_exists($detectedPath),
            'auto_sync_enabled' => isset($row['mdb_auto_sync_enabled']) ? (int)$row['mdb_auto_sync_enabled'] : 1,
            'sync_interval_mins' => isset($row['mdb_sync_interval_mins']) ? (int)$row['mdb_sync_interval_mins'] : 15,
            'last_sync_time' => $row['mdb_last_sync_time'] ?? null,
            'last_sync_status' => $row['mdb_last_sync_status'] ?? null
        ]);
        break;

    case 'test_mdb':
        $mdbPath = trim($data['mdb_path'] ?? ($_GET['mdb_path'] ?? ''));
        if (empty($mdbPath)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid .mdb file path.']);
            break;
        }
        $res = $mdbService->inspectDatabase($mdbPath);
        echo json_encode($res);
        break;

    case 'upload_mdb':
        if (empty($_FILES['mdb_file']) || $_FILES['mdb_file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['mdb_file']['error'] ?? 'no_file';
            $errMsgs = [
                UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds MAX_FILE_SIZE specified in form.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
            ];
            echo json_encode(['success' => false, 'message' => $errMsgs[$errCode] ?? 'Upload error occurred (code ' . $errCode . ').']);
            break;
        }
        $file = $_FILES['mdb_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'mdb' && $ext !== 'accdb') {
            echo json_encode(['success' => false, 'message' => 'Invalid file extension. Only .mdb (Microsoft Access) files are supported.']);
            break;
        }
        $uploadDir = realpath(__DIR__ . '/../uploads/zkteco');
        if (!$uploadDir) {
            $uploadDir = __DIR__ . '/../uploads/zkteco';
            @mkdir($uploadDir, 0777, true);
            $uploadDir = realpath($uploadDir) ?: $uploadDir;
        }
        $safeFileName = 'zkteco_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
        $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file on server.']);
            break;
        }
        $fullPath = realpath($targetPath) ?: $targetPath;
        echo json_encode([
            'success' => true,
            'uploaded_path' => $fullPath,
            'file_name' => $file['name'],
            'file_size' => filesize($fullPath),
            'message' => 'File uploaded successfully.'
        ]);
        break;

    case 'import_mdb':
        $mdbPath = trim($data['mdb_path'] ?? '');
        $startDate = !empty($data['start_date']) ? trim($data['start_date']) : null;
        $endDate = !empty($data['end_date']) ? trim($data['end_date']) : null;
        $limit = isset($data['limit']) ? (int)$data['limit'] : 5000;
        $saveAsDefault = !empty($data['save_as_default']);

        if (empty($mdbPath)) {
            echo json_encode(['success' => false, 'message' => 'Database path is required.']);
            break;
        }

        if ($saveAsDefault) {
            try {
                $stmtSave = $pdo->prepare("UPDATE zkteco_settings SET mdb_default_path = ? WHERE id = 1");
                $stmtSave->execute([$mdbPath]);
            } catch (Exception $e) {}
        }

        $importResult = $mdbService->importPunches($mdbPath, $startDate, $endDate, $limit);
        echo json_encode($importResult);
        break;

    case 'save_mdb_settings':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Admin privileges required.']);
            exit;
        }

        $enabled = !empty($data['auto_sync_enabled']) ? 1 : 0;
        $intervalMins = max(1, (int)($data['sync_interval_mins'] ?? 15));
        $mdbPath = trim($data['mdb_path'] ?? '');

        $stmtSave = $pdo->prepare("
            UPDATE zkteco_settings 
            SET mdb_auto_sync_enabled = ?,
                mdb_sync_interval_mins = ?,
                mdb_default_path = IF(? != '', ?, mdb_default_path)
            WHERE id = 1
        ");
        $stmtSave->execute([$enabled, $intervalMins, $mdbPath, $mdbPath]);

        echo json_encode([
            'success' => true,
            'message' => "Settings saved successfully! Auto-sync is set to every {$intervalMins} minutes."
        ]);
        break;

    case 'check_sync_status':
        $stmt = $pdo->query("SELECT mdb_default_path, mdb_auto_sync_enabled, mdb_sync_interval_mins, mdb_last_sync_time, mdb_last_punch_time, mdb_last_sync_status FROM zkteco_settings WHERE id = 1");
        $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        $mdbPath = trim($settings['mdb_default_path'] ?? '');
        $lastSync = $settings['mdb_last_sync_time'] ?? null;
        
        $elapsedSec = $lastSync ? (time() - strtotime($lastSync)) : 999999;
        $elapsedMins = round($elapsedSec / 60);

        // Check if file is currently accessible
        $fileExists = !empty($mdbPath) && file_exists($mdbPath);

        // Daemon health: considered healthy if synced within last 5 minutes
        $isHealthy = ($elapsedSec < 300);

        echo json_encode([
            'success' => true,
            'last_sync_time' => $lastSync,
            'last_sync_status' => $settings['mdb_last_sync_status'] ?? 'Unknown',
            'elapsed_minutes' => $elapsedMins,
            'is_healthy' => $isHealthy,
            'file_accessible' => $fileExists,
            'mdb_path' => $mdbPath,
            'auto_sync_enabled' => (int)($settings['mdb_auto_sync_enabled'] ?? 1),
            'sync_interval_mins' => (int)($settings['mdb_sync_interval_mins'] ?? 1)
        ]);
        break;

    case 'trigger_auto_sync':
        $force = !empty($data['force']) || !empty($_GET['force']);
        $res = $mdbService->syncIncremental($force);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}
