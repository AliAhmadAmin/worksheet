<?php
/**
 * HOD WhatsApp & AI Task Assigner API
 * Manages per-HOD OrbitSend credentials, AI multimodal image reading,
 * and automated task dispatching.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/HodWhatsAppService.php';

$pdo = getDbConnection();
$service = new HodWhatsAppService($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = (int)($_SESSION['user_id'] ?? ($data['user_id'] ?? 1));
$stmtUser = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUser->execute([$currentUserId]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

$role = $currentUser['role'] ?? ($_SESSION['role'] ?? 'employee');
$isHodOrAdmin = in_array($role, ['super_admin', 'admin', 'hod']) || !empty($currentUser['can_assign_tasks']);

// Handle Incoming Webhook (public access authenticated by token or hod_id)
if ($action === 'webhook' || !empty($_GET['webhook'])) {
    $hodId = (int)($_GET['hod_id'] ?? ($data['hod_id'] ?? 1));
    $token = trim($_GET['token'] ?? ($data['token'] ?? ''));

    $settings = $service->getSettings($hodId);
    if (!empty($settings['webhook_token']) && $settings['webhook_token'] !== $token) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid webhook token.']);
        exit;
    }

    // Process incoming webhook payload
    $payload = !empty($data) ? $data : (json_decode(file_get_contents('php://input'), true) ?: []);
    // Future expansion for 2-way WhatsApp task conversation
    echo json_encode(['success' => true, 'message' => 'Webhook received']);
    exit;
}

// Protected actions require HOD or Admin permissions
if (!$isHodOrAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only HOD or Admins can access WhatsApp & AI task settings.']);
    exit;
}

switch ($action) {
    case 'get_settings':
        $targetHodId = $currentUserId;
        if (!empty($_GET['hod_id']) && in_array($role, ['super_admin', 'admin'])) {
            $targetHodId = (int)$_GET['hod_id'];
        }

        $settings = $service->getSettings($targetHodId);

        // Generate dynamic webhook URL for this HOD
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/Worksheet/api'));
        $webhookUrl = "{$protocol}{$host}{$scriptDir}/hod_tasks_whatsapp.php?action=webhook&hod_id={$targetHodId}";

        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'webhook_url' => $webhookUrl
        ]);
        break;

    case 'save_settings':
        $targetHodId = $currentUserId;
        if (!empty($data['hod_id']) && in_array($role, ['super_admin', 'admin'])) {
            $targetHodId = (int)$data['hod_id'];
        }

        $res = $service->saveSettings($targetHodId, $data);
        echo json_encode($res);
        break;

    case 'test_connection':
        $targetHodId = $currentUserId;
        $testPhone = trim($data['test_phone'] ?? '');

        // If phone not provided, try HOD's own phone
        if (empty($testPhone)) {
            $testPhone = !empty($currentUser['whatsapp_number']) ? $currentUser['whatsapp_number'] : ($currentUser['phone'] ?? '');
        }

        $cleanPhone = $service->cleanPhoneNumber($testPhone);
        if (empty($cleanPhone)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid test phone number (e.g. 03001234567).']);
            exit;
        }

        $settings = $service->getSettings($targetHodId);
        $apiUrl = $settings['api_url'] ?: 'https://app.orbitsend.com/api/send/whatsapp';
        $secret = !empty($data['api_secret']) && strpos($data['api_secret'], '•') === false ? $data['api_secret'] : $settings['api_secret'];
        $unique = !empty($data['unique_id']) ? $data['unique_id'] : $settings['unique_id'];

        if (empty($secret) || empty($unique)) {
            echo json_encode(['success' => false, 'message' => 'OrbitSend Secret Key and Unique ID are required to test connection.']);
            exit;
        }

        $testMsg = "✅ *OrbitSend Connection Test Successful!*\n\n👑 *HOD:* {$currentUser['name']}\n🏢 *System:* Discover Pakistan TV Worksheet\n⏰ *Time:* " . date('d-M-Y h:i A') . "\n\nYour WhatsApp gateway is active and ready to dispatch task assignments!";

        $postData = [
            'secret' => $secret,
            'unique' => $unique,
            'recipient' => $cleanPhone,
            'type' => 'text',
            'message' => $testMsg
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $json = json_decode($res, true);
        if ($res && ($httpCode === 200 || $httpCode === 201)) {
            echo json_encode([
                'success' => true,
                'message' => "Test message sent successfully to {$cleanPhone} via OrbitSend!",
                'response' => $json
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "OrbitSend test failed: " . ($json['message'] ?? ($err ?: "HTTP {$httpCode}")),
                'response' => $json
            ]);
        }
        break;

    case 'ai_read_task_image':
        $imageBase64 = $data['image_base64'] ?? '';
        $contextText = trim($data['context_text'] ?? '');

        if (empty($imageBase64)) {
            echo json_encode(['success' => false, 'message' => 'No image data received for AI reading.']);
            exit;
        }

        $analysis = $service->analyzeTaskImageWithAi($imageBase64, $currentUserId, $contextText);
        echo json_encode($analysis);
        break;

    case 'send_task_whatsapp':
        $taskId = (int)($data['task_id'] ?? 0);
        if (!$taskId) {
            echo json_encode(['success' => false, 'message' => 'Task ID is required.']);
            exit;
        }

        $res = $service->sendTaskWhatsAppNotification($taskId, $currentUserId);
        echo json_encode($res);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Unknown action: {$action}"]);
        break;
}
