<?php
/**
 * ZKTeco ADMS Endpoint: /iclock/cdata.php
 * Handles device handshake (GET) and attendance punch pushes (POST).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ZKTecoAttendanceService.php';

$service = new ZKTecoAttendanceService();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$params = array_merge($_GET, $_POST);

if ($method === 'GET') {
    // Handshake & Heartbeat
    header('Content-Type: text/plain');
    $response = $service->handleAdmsHandshake($params, getallheaders() ?: []);
    echo $response;
    exit;
} elseif ($method === 'POST') {
    // Attendance Punch / Event Push
    header('Content-Type: text/plain');
    $rawBody = file_get_contents('php://input');
    $response = $service->handleAdmsPostData($params, $rawBody);
    echo $response;
    exit;
}
