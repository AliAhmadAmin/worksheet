<?php
/**
 * Attendance API: Check-in, Check-out, Live Duty Tracking & Status
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
$currentUserRole = $_SESSION['role'] ?? 'employee';

// Double check role from database if user_id in session
if (!empty($_SESSION['user_id'])) {
    $roleCheck = $pdo->prepare("SELECT role FROM employees WHERE id = ?");
    $roleCheck->execute([$_SESSION['user_id']]);
    $dbRole = $roleCheck->fetchColumn();
    if ($dbRole) {
        $currentUserRole = $dbRole;
    }
}

function formatDutyTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
}

switch ($action) {
    case 'status':
        $empId = (int)($_GET['employee_id'] ?? $currentUserId);
        $date = $_GET['date'] ?? date('Y-m-d');

        $stmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch();

        $isCheckedIn = false;
        $isCheckedOut = false;
        $elapsedSeconds = 0;

        if ($sheet && $sheet['check_in_time']) {
            $isCheckedIn = true;
            if ($sheet['check_out_time']) {
                $isCheckedOut = true;
                $elapsedSeconds = (int)$sheet['total_duty_seconds'];
            } else {
                // Calculate elapsed time from check in time to now if today
                if ($date === date('Y-m-d')) {
                    $inTimestamp = strtotime($sheet['check_in_time']);
                    if ($inTimestamp) {
                        $elapsedSeconds = max(0, time() - $inTimestamp);
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'sheet' => $sheet,
            'is_checked_in' => $isCheckedIn,
            'is_checked_out' => $isCheckedOut,
            'is_locked' => $sheet ? (int)$sheet['is_locked'] : 0,
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_formatted' => formatDutyTime($elapsedSeconds)
        ]);
        break;

    case 'check_in':
        $empId = (int)($data['employee_id'] ?? $currentUserId);
        $date = $data['date'] ?? date('Y-m-d');
        $today = date('Y-m-d');
        $customTime = $data['time'] ?? date('h:i A');

        // Prevent checking in for future or past dates unless admin
        if ($date !== $today && $currentUserRole !== 'admin') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Live duty check-in is strictly allowed for today (' . $today . ') only. You cannot check in on past or future dates.']);
            exit;
        }

        // Check if sheet exists
        $stmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch();

        if ($sheet && $sheet['is_locked'] && $currentUserRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'This sheet is locked after check-out. Contact Admin to unlock.']);
            exit;
        }

        if ($sheet) {
            $update = $pdo->prepare("UPDATE daily_sheets SET check_in_time = ?, check_out_time = NULL, total_duty_hours = NULL, total_duty_seconds = 0, is_locked = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$customTime, $sheet['id']]);
            $sheetId = $sheet['id'];
        } else {
            $insert = $pdo->prepare("INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, is_locked) VALUES (?, ?, ?, 0)");
            $insert->execute([$empId, $date, $customTime]);
            $sheetId = $pdo->lastInsertId();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Checked in successfully at ' . $customTime,
            'sheet_id' => $sheetId,
            'check_in_time' => $customTime
        ]);
        break;

    case 'check_out':
        $empId = (int)($data['employee_id'] ?? $currentUserId);
        $date = $data['date'] ?? date('Y-m-d');
        $today = date('Y-m-d');
        $customTime = $data['time'] ?? date('h:i A');

        if ($date !== $today && $currentUserRole !== 'admin') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Live duty check-out is only permitted for today (' . $today . ').']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch();

        if (!$sheet || empty($sheet['check_in_time'])) {
            if ($currentUserRole === 'admin') {
                // Admin can check out directly with default or provided check in time
                $defaultIn = $data['check_in_time'] ?? '09:00 AM';
                $checkInTs = strtotime($defaultIn);
                $checkOutTs = strtotime($customTime);
                $dutySeconds = ($checkInTs && $checkOutTs && $checkOutTs >= $checkInTs) ? ($checkOutTs - $checkInTs) : 0;
                $dutyFormatted = formatDutyTime($dutySeconds);

                if ($sheet) {
                    $update = $pdo->prepare("UPDATE daily_sheets SET check_in_time = ?, check_out_time = ?, total_duty_hours = ?, total_duty_seconds = ?, is_locked = 1, locked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $update->execute([$defaultIn, $customTime, $dutyFormatted, $dutySeconds, $sheet['id']]);
                } else {
                    $insert = $pdo->prepare("INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, check_out_time, total_duty_hours, total_duty_seconds, is_locked, locked_at) VALUES (?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)");
                    $insert->execute([$empId, $date, $defaultIn, $customTime, $dutyFormatted, $dutySeconds]);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Checked in at ' . $defaultIn . ' and checked out at ' . $customTime . '. Duty: ' . $dutyFormatted,
                    'check_in_time' => $defaultIn,
                    'check_out_time' => $customTime,
                    'total_duty_hours' => $dutyFormatted,
                    'is_locked' => 1
                ]);
                exit;
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot check out before checking in!']);
                exit;
            }
        }

        if ($sheet['is_locked'] && $currentUserRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sheet is already checked out and locked.']);
            exit;
        }

        // Calculate duty hours
        $checkInTs = strtotime($sheet['check_in_time']);
        $checkOutTs = strtotime($customTime);
        $dutySeconds = 0;

        if ($checkInTs && $checkOutTs) {
            if ($checkOutTs >= $checkInTs) {
                $dutySeconds = $checkOutTs - $checkInTs;
            } else {
                // If crossed midnight
                $dutySeconds = ($checkOutTs + 86400) - $checkInTs;
            }
        }
        $dutyFormatted = formatDutyTime($dutySeconds);

        $update = $pdo->prepare("UPDATE daily_sheets SET check_out_time = ?, total_duty_hours = ?, total_duty_seconds = ?, is_locked = 1, locked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$customTime, $dutyFormatted, $dutySeconds, $sheet['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Checked out successfully at ' . $customTime . '. Sheet is now locked.',
            'check_out_time' => $customTime,
            'total_duty_hours' => $dutyFormatted,
            'is_locked' => 1
        ]);
        break;

    case 'admin_set_shift':
        if ($currentUserRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $inTime = trim($data['check_in_time'] ?? '');
        $outTime = trim($data['check_out_time'] ?? '');
        $isLocked = isset($data['is_locked']) ? (int)$data['is_locked'] : (!empty($outTime) ? 1 : 0);

        if (!$empId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        // Calculate duty hours if both provided
        $dutySeconds = 0;
        $dutyFormatted = null;

        if (!empty($inTime) && !empty($outTime)) {
            $inTs = strtotime($inTime);
            $outTs = strtotime($outTime);
            if ($inTs && $outTs) {
                if ($outTs >= $inTs) {
                    $dutySeconds = $outTs - $inTs;
                } else {
                    $dutySeconds = ($outTs + 86400) - $inTs;
                }
                $dutyFormatted = formatDutyTime($dutySeconds);
            }
        }

        $stmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch();

        if ($sheet) {
            $update = $pdo->prepare("
                UPDATE daily_sheets 
                SET check_in_time = ?, check_out_time = ?, total_duty_hours = ?, total_duty_seconds = ?, is_locked = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $update->execute([
                !empty($inTime) ? $inTime : null,
                !empty($outTime) ? $outTime : null,
                $dutyFormatted,
                $dutySeconds,
                $isLocked,
                $sheet['id']
            ]);
            $sheetId = $sheet['id'];
        } else {
            $insert = $pdo->prepare("
                INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, check_out_time, total_duty_hours, total_duty_seconds, is_locked) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $empId,
                $date,
                !empty($inTime) ? $inTime : null,
                !empty($outTime) ? $outTime : null,
                $dutyFormatted,
                $dutySeconds,
                $isLocked
            ]);
            $sheetId = $pdo->lastInsertId();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Shift attendance updated successfully.',
            'sheet_id' => $sheetId,
            'check_in_time' => $inTime,
            'check_out_time' => $outTime,
            'total_duty_hours' => $dutyFormatted,
            'is_locked' => $isLocked
        ]);
        break;

    case 'live_board':
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $sql = "
            SELECT 
                e.id as employee_id,
                e.name,
                e.designation,
                e.role,
                e.avatar,
                d.name as department_name,
                t.name as team_name,
                s.id as sheet_id,
                s.check_in_time,
                s.check_out_time,
                s.total_duty_hours,
                s.total_duty_seconds,
                s.is_locked,
                (SELECT COUNT(*) FROM sheet_entries se WHERE se.sheet_id = s.id) as total_entries
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN daily_sheets s ON e.id = s.employee_id AND s.sheet_date = ?
            WHERE e.is_active = 1
            ORDER BY t.id ASC, e.name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll();

        $stats = [
            'total_employees' => count($rows),
            'checked_in' => 0,
            'checked_out' => 0,
            'not_arrived' => 0
        ];

        foreach ($rows as &$r) {
            if (!empty($r['check_in_time'])) {
                if (!empty($r['check_out_time'])) {
                    $r['status'] = 'checked_out';
                    $stats['checked_out']++;
                } else {
                    $r['status'] = 'working';
                    $stats['checked_in']++;
                }
            } else {
                $r['status'] = 'absent';
                $stats['not_arrived']++;
            }
        }

        echo json_encode([
            'success' => true,
            'date' => $date,
            'stats' => $stats,
            'employees' => $rows
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
