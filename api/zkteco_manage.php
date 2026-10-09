<?php
/**
 * ZKTeco Management API
 * Provides log monitoring, device registration, and interactive biometric punch testing.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ZKTecoAttendanceService.php';

$pdo = getDbConnection();
$service = new ZKTecoAttendanceService($pdo);

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

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}
