<?php
/**
 * OrbitSend WhatsApp Incoming Webhook Endpoint (ngrok & live compatible)
 * 
 * Configure this URL in your OrbitSend Dashboard Webhook settings:
 * URL: https://your-ngrok-or-domain.app/Worksheet/api/whatsapp_webhook.php
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Webhook-Secret, ngrok-skip-browser-warning');
header('ngrok-skip-browser-warning: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/WhatsAppAttendanceService.php';

// Handle browser GET check (or ngrok / ping test)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $service = new WhatsAppAttendanceService();
    $settings = $service->getSettings();
    echo json_encode([
        'status' => 'active',
        'service' => 'Discover Pakistan WhatsApp Attendance Webhook',
        'ngrok_compatible' => true,
        'is_enabled' => (bool)$settings['is_enabled'],
        'auto_reply' => (bool)$settings['auto_reply'],
        'timestamp' => date('Y-m-d H:i:s'),
        'instructions' => 'Set this URL as Webhook in OrbitSend dashboard. Send POST requests with WhatsApp message payloads.'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);

    if (empty($payload)) {
        $payload = $_POST;
    }

    if (empty($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Empty webhook payload received.']);
        exit;
    }

    $service = new WhatsAppAttendanceService();
    $result = $service->processIncomingMessage($payload);

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
