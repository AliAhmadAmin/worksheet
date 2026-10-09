<?php
/**
 * ZKTeco ADMS Endpoint: /iclock/getrequest.php
 * Handles device command query & polling.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ZKTecoAttendanceService.php';

$service = new ZKTecoAttendanceService();
$params = array_merge($_GET, $_POST);

header('Content-Type: text/plain');
echo $service->handleAdmsGetRequest($params);
exit;
