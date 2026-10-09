<?php
/**
 * WhatsApp Attendance Management API
 * Provides log viewing, settings configuration, and interactive testing simulator.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/WhatsAppAttendanceService.php';

$pdo = getDbConnection();
$service = new WhatsAppAttendanceService($pdo);

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
    case 'get_settings':
        $settings = $service->getSettings();
        // Mask secret for display
        $maskedSecret = '';
        if (!empty($settings['api_secret'])) {
            $len = strlen($settings['api_secret']);
            $maskedSecret = substr($settings['api_secret'], 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($settings['api_secret'], -4);
        }

        // Webhook URL suggestion (Supports localhost, live domains, and ngrok tunnels)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] !== 'off');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $appBase = rtrim(str_replace('/api', '', $scriptDir), '/\\');
        $webhookUrl = $protocol . $host . ($appBase ? $appBase : '') . '/api/whatsapp_webhook.php';

        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'masked_secret' => $maskedSecret,
            'suggested_webhook_url' => $webhookUrl
        ]);
        break;

    case 'save_settings':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Administrator privileges required.']);
            exit;
        }

        $apiUrl = trim($data['api_url'] ?? 'https://app.orbitsend.com/api/send/whatsapp');
        $apiSecret = trim($data['api_secret'] ?? '');
        $uniqueId = trim($data['unique_id'] ?? ($data['account'] ?? ''));
        $webhookToken = trim($data['webhook_token'] ?? '');
        $groupJid = trim($data['group_jid'] ?? '');
        $aiProvider = trim($data['ai_provider'] ?? 'auto');
        $aiApiKey = trim($data['ai_api_key'] ?? '');
        $isEnabled = isset($data['is_enabled']) ? (int)$data['is_enabled'] : 1;
        $autoReply = isset($data['auto_reply']) ? (int)$data['auto_reply'] : 0;
        $defaultStart = trim($data['default_shift_start'] ?? '09:00 AM');
        $defaultEnd = trim($data['default_shift_end'] ?? '06:00 PM');

        // If secret contains asterisks and wasn't changed, keep previous
        if (strpos($apiSecret, '****') !== false || empty($apiSecret)) {
            $prev = $service->getSettings();
            $apiSecret = $prev['api_secret'] ?? '';
        }

        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_attendance_settings 
                (id, api_url, api_secret, unique_id, webhook_token, group_jid, ai_provider, ai_api_key, is_enabled, auto_reply, default_shift_start, default_shift_end)
            VALUES 
                (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                api_url = VALUES(api_url),
                api_secret = VALUES(api_secret),
                unique_id = VALUES(unique_id),
                webhook_token = VALUES(webhook_token),
                group_jid = VALUES(group_jid),
                ai_provider = VALUES(ai_provider),
                ai_api_key = VALUES(ai_api_key),
                is_enabled = VALUES(is_enabled),
                auto_reply = VALUES(auto_reply),
                default_shift_start = VALUES(default_shift_start),
                default_shift_end = VALUES(default_shift_end)
        ");
        $stmt->execute([$apiUrl, $apiSecret, $uniqueId, $webhookToken, $groupJid, $aiProvider, $aiApiKey, $isEnabled, $autoReply, $defaultStart, $defaultEnd]);

        echo json_encode(['success' => true, 'message' => 'OrbitSend WhatsApp & AI settings updated successfully.']);
        break;

    case 'get_logs':
        $dateFilter = $_GET['date'] ?? '';
        $empFilter = $_GET['employee_id'] ?? '';
        $statusFilter = $_GET['status'] ?? 'all';
        $actionFilter = $_GET['parsed_action'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        $limit = min(300, max(10, (int)($_GET['limit'] ?? 100)));

        $sql = "
            SELECT l.*, 
                   e.name as employee_name, 
                   COALESCE(p.emp_code, e.emp_code, '') as emp_code, 
                   e.designation, 
                   d.name as department_name,
                   COALESCE(NULLIF(l.sender_phone, ''), p.phone, p.whatsapp_number, e.phone, e.whatsapp_number, '') as display_phone
            FROM whatsapp_attendance_logs l
            LEFT JOIN employees e ON l.employee_id = e.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($dateFilter)) {
            $sql .= " AND l.action_date = ?";
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

        if (!empty($actionFilter) && $actionFilter !== 'all') {
            $sql .= " AND l.parsed_action = ?";
            $params[] = $actionFilter;
        }

        if (!empty($search)) {
            $searchWild = '%' . $search . '%';
            $sql .= " AND (e.name LIKE ? OR e.emp_code LIKE ? OR l.sender_phone LIKE ? OR l.sender_name LIKE ? OR l.raw_message LIKE ? OR l.remarks LIKE ?)";
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
        }

        $sql .= " ORDER BY l.id DESC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats for target date (or today if none selected)
        $targetDateForStats = !empty($dateFilter) ? $dateFilter : date('Y-m-d');
        $stmtStats = $pdo->prepare("
            SELECT 
                COUNT(*) as total_today,
                SUM(CASE WHEN parsed_action = 'check_in' THEN 1 ELSE 0 END) as in_today,
                SUM(CASE WHEN parsed_action = 'check_out' THEN 1 ELSE 0 END) as out_today,
                SUM(CASE WHEN parsed_action = 'leaving_early' THEN 1 ELSE 0 END) as early_today,
                SUM(CASE WHEN status = 'unmatched' THEN 1 ELSE 0 END) as unmatched_today
            FROM whatsapp_attendance_logs
            WHERE action_date = ?
        ");
        $stmtStats->execute([$targetDateForStats]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'stats' => $stats,
            'stats_date' => $targetDateForStats
        ]);
        break;

    case 'simulate_message':
        $rawMessage = trim($data['message'] ?? '');
        $senderPhone = trim($data['phone'] ?? '');
        $senderName = trim($data['sender_name'] ?? '');
        $simulatedDate = !empty($data['date']) ? $data['date'] : date('Y-m-d');

        if (!$rawMessage) {
            echo json_encode(['success' => false, 'message' => 'Please enter message text to simulate.']);
            exit;
        }

        $payload = [
            'message' => $rawMessage,
            'from' => $senderPhone ?: '03001234567',
            'pushName' => $senderName ?: 'Staff Member',
            'timestamp' => strtotime($simulatedDate . ' ' . date('H:i:s')),
            'message_id' => 'sim_' . uniqid()
        ];

        $result = $service->processIncomingMessage($payload);
        echo json_encode([
            'success' => true,
            'result' => $result
        ]);
        break;

    case 'fetch_groups':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $res = $service->fetchWhatsAppGroups();
        echo json_encode($res);
        break;

    case 'fetch_accounts':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $res = $service->fetchWhatsAppAccounts();
        echo json_encode($res);
        break;

    case 'clear_logs':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $pdo->exec("DELETE FROM whatsapp_attendance_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        echo json_encode(['success' => true, 'message' => 'Archived logs older than 60 days cleared.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
