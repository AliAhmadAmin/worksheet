<?php
/**
 * Hourly Sheet API: Entries CRUD, Lock/Unlock, Print Data
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = $_SESSION['user_id'] ?? ($data['employee_id'] ?? 1);
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$canUnlock = ($currentUserRole === 'admin') || !empty($currentUserObj['can_unlock_sheets']);
$canInspect = ($currentUserRole === 'admin') || !empty($currentUserObj['can_inspect_sheets']);

switch ($action) {
    case 'get_sheet':
        $empId = (int)($_GET['employee_id'] ?? $currentUserId);
        $date = $_GET['date'] ?? date('Y-m-d');

        // Fetch employee details
        $empStmt = $pdo->prepare("
            SELECT e.*, d.name as department_name, t.name as team_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            LEFT JOIN teams t ON e.team_id = t.id 
            WHERE e.id = ?
        ");
        $empStmt->execute([$empId]);
        $employee = $empStmt->fetch();

        if (!$employee) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

        // Fetch or create daily sheet
        $sheetStmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $sheetStmt->execute([$empId, $date]);
        $sheet = $sheetStmt->fetch();

        $entries = [];
        if ($sheet) {
            $entriesStmt = $pdo->prepare("SELECT * FROM sheet_entries WHERE sheet_id = ? ORDER BY id ASC");
            $entriesStmt->execute([$sheet['id']]);
            $entries = $entriesStmt->fetchAll();
        }

        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'sheet' => $sheet,
            'entries' => $entries,
            'is_locked' => $sheet ? (int)$sheet['is_locked'] : 0,
            'can_edit' => ($currentUserRole === 'admin') || (!$sheet || $sheet['is_locked'] == 0)
        ]);
        break;

    case 'save_entries':
        $empId = (int)($data['employee_id'] ?? $currentUserId);
        $date = $data['date'] ?? date('Y-m-d');
        $entries = $data['entries'] ?? [];
        $remarks = $data['remarks'] ?? '';
        $workSummary = $data['work_summary'] ?? '';
        $checkInTime = $data['check_in_time'] ?? null;
        $checkOutTime = $data['check_out_time'] ?? null;

        // Check lock status
        $sheetStmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $sheetStmt->execute([$empId, $date]);
        $sheet = $sheetStmt->fetch();

        // Allow saving entries (especially links) even when locked, without modifying duty times unless admin
        $pdo->beginTransaction();
        try {
            if (!$sheet) {
                $insertSheet = $pdo->prepare("
                    INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, check_out_time, remarks, work_summary, is_locked) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $insertSheet->execute([$empId, $date, $checkInTime, $checkOutTime, $remarks, $workSummary]);
                $sheetId = $pdo->lastInsertId();
            } else {
                $sheetId = $sheet['id'];
                $updateFields = ["remarks = ?", "work_summary = ?", "updated_at = CURRENT_TIMESTAMP"];
                $params = [$remarks, $workSummary];

                if ($checkInTime !== null && ($currentUserRole === 'admin' || !$sheet['is_locked'])) {
                    $updateFields[] = "check_in_time = ?";
                    $params[] = $checkInTime;
                }
                if ($checkOutTime !== null && $currentUserRole === 'admin') {
                    $updateFields[] = "check_out_time = ?";
                    $params[] = $checkOutTime;
                }
                $params[] = $sheetId;

                $updateSheet = $pdo->prepare("UPDATE daily_sheets SET " . implode(', ', $updateFields) . " WHERE id = ?");
                $updateSheet->execute($params);
            }

            // Delete old entries and insert updated set
            $delEntries = $pdo->prepare("DELETE FROM sheet_entries WHERE sheet_id = ?");
            $delEntries->execute([$sheetId]);

            $insEntry = $pdo->prepare("
                INSERT INTO sheet_entries (sheet_id, time_slot, content_type, department, link, title, count_val) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($entries as $row) {
                $title = trim($row['title'] ?? '');
                $rawLink = trim($row['link'] ?? '');
                $link = ($rawLink === 'upload') ? '' : $rawLink;

                // A valid real work unit must have a description or link
                if (empty($title) && empty($link)) {
                    continue; // skip blank rows without work description
                }

                $timeSlot = trim($row['time_slot'] ?? '');
                $contentType = trim($row['content_type'] ?? 'FB Videos');
                $department = trim($row['department'] ?? 'Digital');
                $countVal = 1; // 1 real unit of work per entry row

                $insEntry->execute([$sheetId, $timeSlot, $contentType, $department, $link, $title, $countVal]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Hourly sheet saved successfully', 'sheet_id' => $sheetId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save sheet: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_lock':
        if (!$canUnlock) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to lock or unlock worksheets.']);
            exit;
        }

        $sheetId = (int)($data['sheet_id'] ?? 0);
        $empId = (int)($data['employee_id'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $lockState = (int)($data['is_locked'] ?? 1);

        if ($sheetId) {
            $stmt = $pdo->prepare("UPDATE daily_sheets SET is_locked = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$lockState, $sheetId]);
        } else if ($empId && $date) {
            $stmt = $pdo->prepare("UPDATE daily_sheets SET is_locked = ?, updated_at = CURRENT_TIMESTAMP WHERE employee_id = ? AND sheet_date = ?");
            $stmt->execute([$lockState, $empId, $date]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing sheet identifier']);
            exit;
        }

        echo json_encode([
            'success' => true, 
            'is_locked' => $lockState,
            'message' => $lockState ? 'Sheet locked successfully' : 'Sheet unlocked successfully. Employee can now edit.'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
