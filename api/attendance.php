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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = $_SESSION['user_id'] ?? ($data['employee_id'] ?? 1);
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isSuperAdmin = ($currentUserRole === 'super_admin' || $currentUserRole === 'admin');
$isHod = ($currentUserRole === 'hod');
$isHr = ($currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr') || (!empty($currentUserObj['can_manage_hr']) && !in_array($currentUserRole, ['hod', 'employee'])));
$hasGlobalAttendance = ($isSuperAdmin || $isHr);
$userDeptId = (int)($currentUserObj['department_id'] ?? 0);
$managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
if ($isHod) {
    $stmtM = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
    $stmtM->execute([$currentUserId]);
    $extraDepts = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($extraDepts)) {
        $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extraDepts)));
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

        if ($empId !== (int)$currentUserId && !$hasGlobalAttendance) {
            $stmtDeptCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtDeptCheck->execute([$empId]);
            $targetDept = (int)$stmtDeptCheck->fetchColumn();
            if (!in_array($targetDept, $managedDeptIds)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied: You can only view status of employees in your department.']);
                exit;
            }
        }

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
                // Calculate elapsed time from check in datetime to now (handles overnight shifts)
                $inDateTimeStr = $sheet['sheet_date'] . ' ' . $sheet['check_in_time'];
                $inTimestamp = strtotime($inDateTimeStr);
                if ($inTimestamp) {
                    $elapsedSeconds = max(0, time() - $inTimestamp);
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

        if ($empId !== (int)$currentUserId && !$hasGlobalAttendance) {
            $stmtDeptCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtDeptCheck->execute([$empId]);
            $targetDept = (int)$stmtDeptCheck->fetchColumn();
            if (!in_array($targetDept, $managedDeptIds)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied: You can only manage attendance for your own department.']);
                exit;
            }
        }

        // Prevent checking in for future or past dates unless super admin / HR
        if ($date !== $today && !$hasGlobalAttendance) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Live duty check-in is strictly allowed for today (' . $today . ') only. You cannot check in on past or future dates.']);
            exit;
        }

        // Check if sheet exists
        $stmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch();

        if ($sheet && $sheet['is_locked'] && !$hasGlobalAttendance) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'This sheet is locked after check-out. Contact HR or Admin to unlock.']);
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

        if ($empId !== (int)$currentUserId && !$hasGlobalAttendance) {
            $stmtDeptCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtDeptCheck->execute([$empId]);
            $targetDept = (int)$stmtDeptCheck->fetchColumn();
            if (!in_array($targetDept, $managedDeptIds)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied: You can only manage attendance for your own department.']);
                exit;
            }
        }

        $stmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $stmt->execute([$empId, $date]);
        $sheet = $stmt->fetch();

        // Check if this is an overnight shift started yesterday (e.g. 6PM to 2AM)
        $isOvernightShift = false;
        if ($sheet && !empty($sheet['check_in_time']) && empty($sheet['is_locked'])) {
            $inDateTime = strtotime($sheet['sheet_date'] . ' ' . $sheet['check_in_time']);
            // Allow check out if shift was started within the last 36 hours
            if ($inDateTime && (time() - $inDateTime) <= 129600) {
                $isOvernightShift = true;
            }
        }

        if ($date !== $today && !$isOvernightShift && !$hasGlobalAttendance) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Live duty check-out is only permitted for active ongoing shifts.']);
            exit;
        }

        if (!$sheet || empty($sheet['check_in_time'])) {
            if ($hasGlobalAttendance) {
                // Admin or HR can check out directly with default or provided check in time
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

        if ($sheet['is_locked'] && !$hasGlobalAttendance) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sheet is already checked out and locked.']);
            exit;
        }

        // Calculate duty hours accurately using full timestamps
        $inDateTime = strtotime($sheet['sheet_date'] . ' ' . $sheet['check_in_time']);
        $nowTs = time();
        $dutySeconds = 0;

        if ($inDateTime) {
            if ($nowTs >= $inDateTime && ($nowTs - $inDateTime) <= 129600) {
                $dutySeconds = $nowTs - $inDateTime;
            } else {
                $checkInTs = strtotime($sheet['check_in_time']);
                $checkOutTs = strtotime($customTime);
                if ($checkInTs && $checkOutTs) {
                    if ($checkOutTs >= $checkInTs) {
                        $dutySeconds = $checkOutTs - $checkInTs;
                    } else {
                        // Crossed midnight (e.g. 6PM to 2AM)
                        $dutySeconds = ($checkOutTs + 86400) - $checkInTs;
                    }
                }
            }
        }
        $dutyFormatted = formatDutyTime($dutySeconds);

        $update = $pdo->prepare("UPDATE daily_sheets SET check_out_time = ?, total_duty_hours = ?, total_duty_seconds = ?, is_locked = 1, locked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$customTime, $dutyFormatted, $dutySeconds, $sheet['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Checked out successfully at ' . $customTime . ' (Duty: ' . $dutyFormatted . '). Sheet is locked.',
            'check_out_time' => $customTime,
            'total_duty_hours' => $dutyFormatted,
            'is_locked' => 1
        ]);
        break;

    case 'admin_set_shift':
        $empId = (int)($data['employee_id'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $inTime = trim($data['check_in_time'] ?? '');
        $outTime = trim($data['check_out_time'] ?? '');
        $dayType = isset($data['day_type']) && in_array($data['day_type'], ['working', 'swapped_off', 'alt_off', 'holiday'], true)
            ? $data['day_type'] 
            : null;
        $holidayReason = isset($data['holiday_reason']) ? trim($data['holiday_reason']) : null;

        if (!$empId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        // Fetch employee department to check HOD permission scope
        $stmtEmpDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtEmpDept->execute([$empId]);
        $targetDeptId = (int)$stmtEmpDept->fetchColumn();

        $canSetShift = $hasGlobalAttendance || ($isHod && in_array($targetDeptId, $managedDeptIds, true));
        if (!$canSetShift) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: You can only adjust shift attendance for employees in your department.']);
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

        $isLocked = isset($data['is_locked']) ? (int)$data['is_locked'] : ($sheet ? (int)$sheet['is_locked'] : 0);

        // Effective day type logic
        $effectiveDayType = $dayType !== null ? $dayType : ($sheet['day_type'] ?? 'working');
        $effectiveReason = $holidayReason !== null ? $holidayReason : ($sheet['holiday_reason'] ?? null);

        if ($sheet) {
            $update = $pdo->prepare("
                UPDATE daily_sheets 
                SET check_in_time = ?, check_out_time = ?, total_duty_hours = ?, total_duty_seconds = ?, 
                    day_type = ?, holiday_reason = ?, holiday_marked_by = ?, is_locked = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $update->execute([
                !empty($inTime) ? $inTime : null,
                !empty($outTime) ? $outTime : null,
                $dutyFormatted,
                $dutySeconds,
                $effectiveDayType,
                !empty($effectiveReason) ? $effectiveReason : null,
                $currentUserId,
                $isLocked,
                $sheet['id']
            ]);
            $sheetId = $sheet['id'];
        } else {
            $insert = $pdo->prepare("
                INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, check_out_time, total_duty_hours, total_duty_seconds, day_type, holiday_reason, holiday_marked_by, is_locked) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $empId,
                $date,
                !empty($inTime) ? $inTime : null,
                !empty($outTime) ? $outTime : null,
                $dutyFormatted,
                $dutySeconds,
                $effectiveDayType,
                !empty($effectiveReason) ? $effectiveReason : null,
                $currentUserId,
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
            'day_type' => $effectiveDayType,
            'holiday_reason' => $effectiveReason,
            'is_locked' => $isLocked
        ]);
        break;

    case 'live_board':
        $date = $_GET['date'] ?? date('Y-m-d');
        $deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' && $_GET['department_id'] !== 'all' ? (int)$_GET['department_id'] : null;

        $whereClauses = ["e.is_active = 1"];
        $params = [$date];

        if (!$hasGlobalAttendance && !empty($managedDeptIds)) {
            $inDepts = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $whereClauses[] = "e.department_id IN ($inDepts)";
            foreach ($managedDeptIds as $md) {
                $params[] = $md;
            }
        } else if ($deptFilter) {
            $whereClauses[] = "e.department_id = ?";
            $params[] = $deptFilter;
        }

        $whereSql = "WHERE " . implode(' AND ', $whereClauses);

        $sql = "
            SELECT 
                e.id as employee_id,
                e.name,
                e.designation,
                e.role,
                e.avatar,
                e.department_id,
                d.name as department_name,
                d.require_hod_sheet_check,
                t.name as team_name,
                s.id as sheet_id,
                s.check_in_time,
                s.check_out_time,
                s.total_duty_hours,
                s.is_locked,
                s.is_hod_checked,
                s.hod_checked_by,
                s.hod_checked_at,
                s.hod_checked_notes,
                s.day_type,
                s.holiday_reason,
                (SELECT COUNT(*) FROM sheet_entries se WHERE se.sheet_id = s.id) as total_entries
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN daily_sheets s ON e.id = s.employee_id AND s.sheet_date = ?
            {$whereSql}
            ORDER BY 
                CASE e.role 
                    WHEN 'super_admin' THEN 1 
                    WHEN 'admin' THEN 1 
                    WHEN 'hr' THEN 2 
                    WHEN 'hod' THEN 3 
                    WHEN 'deputy_hod' THEN 4 
                    WHEN 'shift_supervisor' THEN 5 
                    WHEN 'team_lead' THEN 6 
                    WHEN 'coordinator' THEN 7 
                    WHEN 'quality_auditor' THEN 8 
                    ELSE 9 
                END,
                d.id ASC, t.id ASC, e.name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $stats = [
            'total_employees' => count($rows),
            'checked_in' => 0,
            'checked_out' => 0,
            'day_off' => 0,
            'not_arrived' => 0,
            'sheets_checked' => 0,
            'sheets_verified' => 0,
            'sheets_pending' => 0,
            'sheets_not_checked' => 0,
            'sheets_empty' => 0
        ];

        foreach ($rows as &$r) {
            if (($r['day_type'] ?? '') === 'holiday') {
                $r['status'] = 'holiday';
                $stats['day_off']++;
            } else if (!empty($r['check_in_time'])) {
                if (!empty($r['check_out_time'])) {
                    $r['status'] = 'checked_out';
                    $stats['checked_out']++;
                } else {
                    $r['status'] = 'working';
                    $stats['checked_in']++;
                }
            } else {
                if ($date === date('Y-m-d')) {
                    $r['status'] = 'not_arrived';
                } else {
                    $r['status'] = 'absent';
                }
                $stats['not_arrived']++;
            }

            $isChecked = !empty($r['is_hod_checked']);
            if ($isChecked) {
                $r['sheet_status'] = 'checked';
                $stats['sheets_checked']++;
                $stats['sheets_verified']++;
            } elseif ($r['status'] === 'checked_out') {
                // When employee checks out, show as pending check
                $r['sheet_status'] = 'pending';
                $stats['sheets_pending']++;
            } elseif ($r['status'] === 'working') {
                $r['sheet_status'] = 'working';
                $stats['sheets_empty']++;
            } else {
                $r['sheet_status'] = 'not_checked';
                $stats['sheets_not_checked']++;
                $stats['sheets_empty']++;
            }
        }

        // Determine if HOD checked system is enabled for this view
        $hasHodCheckEnabled = false;
        if (!empty($deptFilter) && $deptFilter !== 'all') {
            $stmtD = $pdo->prepare("SELECT require_hod_sheet_check FROM departments WHERE id = ?");
            $stmtD->execute([(int)$deptFilter]);
            $deptCheckVal = $stmtD->fetchColumn();
            if ($deptCheckVal !== false) {
                $hasHodCheckEnabled = ((int)$deptCheckVal === 1);
            }
        } elseif ($isHod && !empty($managedDeptIds)) {
            $inD = implode(',', array_map('intval', $managedDeptIds));
            $deptCheckVal = $pdo->query("SELECT MAX(require_hod_sheet_check) FROM departments WHERE id IN ($inD)")->fetchColumn();
            $hasHodCheckEnabled = ((int)$deptCheckVal === 1);
        } else {
            foreach ($rows as $r) {
                if (!empty($r['require_hod_sheet_check'])) {
                    $hasHodCheckEnabled = true;
                    break;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'date' => $date,
            'stats' => $stats,
            'has_hod_check_enabled' => $hasHodCheckEnabled,
            'employees' => $rows
        ]);
        break;

    case 'attendance_report':
        try {
            $month = $_GET['month'] ?? date('Y-m');
            $startDate = $_GET['start_date'] ?? ($month . '-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t', strtotime($startDate));
            $empIdFilter = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' && $_GET['employee_id'] !== 'all' ? (int)$_GET['employee_id'] : null;
            $deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' && $_GET['department_id'] !== 'all' ? (int)$_GET['department_id'] : null;
            $rawStandardParam = $_GET['standard_hours'] ?? 'auto';

            $whereClauses = ["e.is_active = 1"];
            $params = [];

            if (!$hasGlobalAttendance && !empty($managedDeptIds)) {
                $inDepts = implode(',', array_fill(0, count($managedDeptIds), '?'));
                $whereClauses[] = "e.department_id IN ($inDepts)";
                foreach ($managedDeptIds as $md) {
                    $params[] = $md;
                }
            } else if ($deptFilter) {
                $whereClauses[] = "e.department_id = ?";
                $params[] = $deptFilter;
            }

            if ($empIdFilter) {
                $whereClauses[] = "e.id = ?";
                $params[] = $empIdFilter;
            }

            $whereSql = "WHERE " . implode(' AND ', $whereClauses);

            $empStmt = $pdo->prepare("
                SELECT e.id, e.name, e.email, e.designation, e.role, e.avatar, e.department_id, e.weekly_off,
                       COALESCE(p.expected_hours, 8.0) as expected_hours,
                       COALESCE(p.shift_policy, 'standard_8h') as shift_policy,
                       d.name as department_name, t.name as team_name
                FROM employees e
                LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN teams t ON e.team_id = t.id
                {$whereSql}
                ORDER BY 
                    CASE e.role 
                        WHEN 'super_admin' THEN 1 
                        WHEN 'admin' THEN 1 
                        WHEN 'hr' THEN 2 
                        WHEN 'hod' THEN 3 
                        WHEN 'deputy_hod' THEN 4 
                        WHEN 'shift_supervisor' THEN 5 
                        WHEN 'team_lead' THEN 6 
                        WHEN 'coordinator' THEN 7 
                        WHEN 'quality_auditor' THEN 8 
                        ELSE 9 
                    END,
                    d.id ASC, e.name ASC
            ");
            $empStmt->execute($params);
            $employees = $empStmt->fetchAll();

            $includeDetails = isset($_GET['include_details']) && ($_GET['include_details'] === '1' || $_GET['include_details'] === 'true');
            $isSingleEmployee = ($empIdFilter !== null);
            $needDetails = ($isSingleEmployee || $includeDetails);

            // Fetch duty rosters for months in the report period to respect employee scheduled weekly off
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            $periodMonths = array_unique([$startMonth, $endMonth]);
            $inRosterPlaceholders = implode(',', array_fill(0, count($periodMonths), '?'));
            $rosterParams = $periodMonths;
            $rosterSql = "
                SELECT employee_id, roster_month, weekly_off, shift_timing, off_dates_json 
                FROM duty_rosters 
                WHERE roster_month IN ($inRosterPlaceholders)
            ";
            if ($empIdFilter) {
                $rosterSql .= " AND employee_id = ?";
                $rosterParams[] = $empIdFilter;
            }
            $rostersByEmpAndMonth = [];
            try {
                $rosterStmt = $pdo->prepare($rosterSql);
                $rosterStmt->execute($rosterParams);
                while ($rost = $rosterStmt->fetch(PDO::FETCH_ASSOC)) {
                    $rostersByEmpAndMonth[$rost['employee_id']][$rost['roster_month']] = $rost;
                }
            } catch (Exception $e) {
                $rostersByEmpAndMonth = [];
            }

            // Fetch daily sheets for this date range with lean columns (avoid heavy subquery)
            $sheetSql = "
                SELECT ds.id, ds.employee_id, ds.sheet_date, ds.check_in_time, ds.check_out_time, 
                       ds.total_duty_seconds, ds.is_locked, ds.work_summary, ds.remarks,
                       ds.day_type, ds.holiday_reason
                FROM daily_sheets ds
                WHERE ds.sheet_date BETWEEN ? AND ?
            ";
            $sheetParams = [$startDate, $endDate];
            if ($empIdFilter) {
                $sheetSql .= " AND ds.employee_id = ?";
                $sheetParams[] = $empIdFilter;
            }
            $sheetStmt = $pdo->prepare($sheetSql);
            $sheetStmt->execute($sheetParams);
            $allSheets = $sheetStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group sheets by employee_id and date for O(1) lookup
            $sheetsByEmp = [];
            foreach ($allSheets as $sh) {
                $sheetsByEmp[$sh['employee_id']][$sh['sheet_date']] = $sh;
            }

            // Fetch all approved leaves and index by employee_id for O(1) employee lookup
            $leavesByEmp = [];
            try {
                $leaveSql = "
                    SELECT employee_id, start_date, end_date, leave_type, reason 
                    FROM hr_leaves 
                    WHERE status = 'approved' 
                      AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (start_date <= ? AND end_date >= ?))
                ";
                $leaveParams = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
                if ($empIdFilter) {
                    $leaveSql .= " AND employee_id = ?";
                    $leaveParams[] = $empIdFilter;
                }
                $leaveStmt = $pdo->prepare($leaveSql);
                $leaveStmt->execute($leaveParams);
                while ($lv = $leaveStmt->fetch(PDO::FETCH_ASSOC)) {
                    $leavesByEmp[$lv['employee_id']][] = $lv;
                }
            } catch (Exception $e) {
                $leavesByEmp = [];
            }

            // Generate list of days between startDate and endDate
            $periodDates = [];
            $currDate = $startDate;
            $today = date('Y-m-d');
            while (strtotime($currDate) <= strtotime($endDate)) {
                $periodDates[] = [
                    'date' => $currDate,
                    'day' => date('l', strtotime($currDate)),
                    'day_num' => (int)date('d', strtotime($currDate)),
                    'is_future' => ($currDate > $today),
                    'is_today' => ($currDate === $today),
                    'is_past' => ($currDate < $today)
                ];
                $currDate = date('Y-m-d', strtotime($currDate . ' +1 day'));
            }

            $reportData = [];
            $grandTotals = [
                'total_employees' => count($employees),
                'total_days_evaluated' => count($periodDates),
                'total_present_full' => 0,
                'total_short_leaves' => 0,
                'total_half_leaves' => 0,
                'total_approved_leaves' => 0,
                'total_absences' => 0,
                'total_duty_seconds' => 0,
                'total_duty_hours' => 0,
                'total_expected_hours' => 0
            ];

            foreach ($employees as $emp) {
                $empId = (int)$emp['id'];
                $empExpectedHours = isset($emp['expected_hours']) ? (float)$emp['expected_hours'] : 8.0;

                // Determine effective daily shift hours
                $isOpenFlexible = ($rawStandardParam === 'open' || ($rawStandardParam === 'auto' && $empExpectedHours <= 0.0));
                $shiftHours = $isOpenFlexible ? 0.0 : (($rawStandardParam !== 'auto' && is_numeric($rawStandardParam)) ? (float)$rawStandardParam : $empExpectedHours);

                $empRecords = [];
                $empSummary = [
                    'full_days' => 0,
                    'short_leaves' => 0,
                    'half_leaves' => 0,
                    'incomplete' => 0,
                    'approved_leaves' => 0,
                    'absences' => 0,
                    'total_duty_seconds' => 0,
                    'total_duty_hours' => 0,
                    'expected_duty_hours' => 0,
                    'working_days_count' => 0,
                    'is_flexible' => $isOpenFlexible,
                    'shift_hours' => $shiftHours,
                    'weekly_hours' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                    'off_days_worked' => 0,
                    'alt_days_taken' => 0,
                    'comp_off_balance' => 0
                ];

                $empLeaves = $leavesByEmp[$empId] ?? [];

                // Step 1: Pre-analyze all days in period to detect worked weekly off days and candidate rotation days
                $dayMetaMap = [];
                $workedOffDays = [];
                $unclockedDays = [];

                foreach ($periodDates as $d) {
                    $dt = $d['date'];
                    $monthPrefix = substr($dt, 0, 7);
                    $sheet = $sheetsByEmp[$empId][$dt] ?? null;

                    // Resolve employee weekly off from duty roster or employee profile
                    $empRoster = $rostersByEmpAndMonth[$empId][$monthPrefix] ?? null;
                    $scheduledWeeklyOff = !empty($empRoster['weekly_off']) 
                        ? trim($empRoster['weekly_off']) 
                        : (!empty($emp['weekly_off']) ? trim($emp['weekly_off']) : 'Sunday');
                    $empOffDates = !empty($empRoster['off_dates_json']) 
                        ? (json_decode($empRoster['off_dates_json'], true) ?: []) 
                        : [];

                    $dayOfWeek = $d['day'];
                    $isEmpWeeklyOff = false;
                    if (strcasecmp($dayOfWeek, $scheduledWeeklyOff) === 0) {
                        $isEmpWeeklyOff = true;
                    } elseif (stripos($scheduledWeeklyOff, 'Once in Month') !== false && in_array($dt, $empOffDates, true)) {
                        $isEmpWeeklyOff = true;
                    }

                    // Check if on approved leave
                    $isOnLeave = false;
                    $leaveType = '';
                    $leaveReason = '';
                    if (!empty($empLeaves)) {
                        foreach ($empLeaves as $lv) {
                            if ($dt >= $lv['start_date'] && $dt <= $lv['end_date']) {
                                $isOnLeave = true;
                                $leaveType = $lv['leave_type'];
                                $leaveReason = $lv['reason'] ?? '';
                                break;
                            }
                        }
                    }

                    $dutySeconds = $sheet ? (int)$sheet['total_duty_seconds'] : 0;
                    if ($sheet && empty($sheet['check_out_time']) && !empty($sheet['check_in_time']) && $dt === $today) {
                        $inTs = strtotime($dt . ' ' . $sheet['check_in_time']);
                        if ($inTs) $dutySeconds = max(0, time() - $inTs);
                    }
                    $dutyHours = round($dutySeconds / 3600, 2);

                    $explicitDayType = $sheet ? ($sheet['day_type'] ?? '') : '';
                    $holidayReason = $sheet ? ($sheet['holiday_reason'] ?? '') : '';
                    $isExplicitRest = in_array($explicitDayType, ['holiday', 'swapped_off', 'alt_off', 'day_off'], true);

                    if ($d['is_past']) {
                        if ($isEmpWeeklyOff && $dutyHours >= 1.0) {
                            $workedOffDays[] = [
                                'date' => $dt,
                                'duty_hours' => $dutyHours,
                                'day' => $dayOfWeek,
                                'ts' => strtotime($dt)
                            ];
                        } elseif (!$isEmpWeeklyOff && !$isExplicitRest && !$isOnLeave && $dutySeconds == 0 && empty($sheet['check_in_time'])) {
                            $unclockedDays[] = [
                                'date' => $dt,
                                'day' => $dayOfWeek,
                                'ts' => strtotime($dt)
                            ];
                        }
                    } elseif ($d['is_today'] && $isEmpWeeklyOff && $dutyHours >= 1.0) {
                        $workedOffDays[] = [
                            'date' => $dt,
                            'duty_hours' => $dutyHours,
                            'day' => $dayOfWeek,
                            'ts' => strtotime($dt)
                        ];
                    }

                    $dayMetaMap[$dt] = [
                        'sheet' => $sheet,
                        'is_weekly_off' => $isEmpWeeklyOff,
                        'scheduled_weekly_off' => $scheduledWeeklyOff,
                        'is_on_leave' => $isOnLeave,
                        'leave_type' => $leaveType,
                        'leave_reason' => $leaveReason,
                        'duty_seconds' => $dutySeconds,
                        'duty_hours' => $dutyHours,
                        'explicit_day_type' => $explicitDayType,
                        'holiday_reason' => $holidayReason
                    ];
                }

                // Step 2: Auto-pair unclocked days with worked weekly off days within +/- 6 days (same weekly rotation)
                $autoSwappedMap = [];
                $pairedWorkedOffMap = [];

                foreach ($workedOffDays as $wItem) {
                    $wDate = $wItem['date'];
                    $wDay = $wItem['day'];
                    $wTs = $wItem['ts'];

                    $bestUDate = null;
                    $bestUDay = null;
                    $minDiff = 999999;
                    foreach ($unclockedDays as $uItem) {
                        $uDate = $uItem['date'];
                        if (isset($autoSwappedMap[$uDate])) continue;
                        $diff = abs(($uItem['ts'] - $wTs) / 86400);
                        if ($diff <= 6 && $diff < $minDiff) {
                            $minDiff = $diff;
                            $bestUDate = $uDate;
                            $bestUDay = $uItem['day'];
                        }
                    }

                    if ($bestUDate) {
                        $autoSwappedMap[$bestUDate] = [
                            'swapped_with_date' => $wDate,
                            'swapped_with_day' => $wDay,
                            'note' => "Rotated weekly off with {$wDay} ({$wDate})"
                        ];
                        $pairedWorkedOffMap[$wDate] = [
                            'swapped_with_date' => $bestUDate,
                            'swapped_with_day' => $bestUDay,
                            'note' => "Rotated with {$bestUDay} ({$bestUDate})"
                        ];
                    }
                }

                // Step 3: Evaluate each day with awareness of rotated off days and comp off
                foreach ($periodDates as $d) {
                    $dt = $d['date'];
                    $meta = $dayMetaMap[$dt];
                    $sheet = $meta['sheet'];
                    $isEmpWeeklyOff = $meta['is_weekly_off'];
                    $scheduledWeeklyOff = $meta['scheduled_weekly_off'];
                    $isOnLeave = $meta['is_on_leave'];
                    $leaveType = $meta['leave_type'];
                    $leaveReason = $meta['leave_reason'];
                    $dutySeconds = $meta['duty_seconds'];
                    $dutyHours = $meta['duty_hours'];
                    $explicitDayType = $meta['explicit_day_type'];
                    $holidayReason = $meta['holiday_reason'];

                    $isSwappedOff = ($explicitDayType === 'swapped_off') || isset($autoSwappedMap[$dt]);
                    $isAltOff = ($explicitDayType === 'alt_off');
                    $isExplicitHoliday = ($explicitDayType === 'holiday' || $explicitDayType === 'day_off');

                    $isDayOff = ($isExplicitHoliday || $isSwappedOff || $isAltOff);
                    $isRestDay = ($isEmpWeeklyOff || $isDayOff);

                    $isOffDayWorked = ($isEmpWeeklyOff && $dutyHours >= 1.0);
                    if ($isOffDayWorked && !$d['is_future']) {
                        $empSummary['off_days_worked']++;
                    }
                    if (($isSwappedOff || $isAltOff) && !$d['is_future']) {
                        $empSummary['alt_days_taken']++;
                    }

                    $weekNum = min(6, (int)ceil($d['day_num'] / 7));
                    $fullThreshold = max(1.0, $shiftHours - 0.5);
                    $shortThreshold = max(1.0, round($shiftHours * 0.65, 1));
                    $halfThreshold = max(0.5, round($shiftHours * 0.35, 1));

                    $hasCheckIn = !empty($sheet['check_in_time']);
                    $hasCheckOut = !empty($sheet['check_out_time']);

                    if (!$d['is_future']) {
                        $empSummary['total_duty_seconds'] += $dutySeconds;
                        $empSummary['total_duty_hours'] += $dutyHours;
                        $empSummary['weekly_hours'][$weekNum] += $dutyHours;

                        if ($d['is_past']) {
                            $empSummary['working_days_count']++;
                            if (!$isOpenFlexible && !$isRestDay && !$isOnLeave) {
                                $empSummary['expected_duty_hours'] += $shiftHours;
                            }
                        } elseif ($d['is_today']) {
                            if ($hasCheckOut) {
                                $empSummary['working_days_count']++;
                                if (!$isOpenFlexible && !$isRestDay && !$isOnLeave) {
                                    $empSummary['expected_duty_hours'] += $shiftHours;
                                }
                            } elseif ($hasCheckIn) {
                                $empSummary['working_days_count']++;
                                if (!$isOpenFlexible && !$isRestDay && !$isOnLeave && $dutyHours >= $fullThreshold) {
                                    $empSummary['expected_duty_hours'] += $shiftHours;
                                }
                            }
                        }
                    }

                    $swappedNote = '';
                    if ($isSwappedOff) {
                        $swappedNote = $holidayReason ?: (isset($autoSwappedMap[$dt]) ? $autoSwappedMap[$dt]['note'] : 'Rotated Weekly Off');
                    } elseif ($isAltOff) {
                        $swappedNote = $holidayReason ?: 'Alternate Day Off (Comp Off)';
                    }

                    // Dynamic Classification Rules
                    if ($d['is_future']) {
                        $status = 'upcoming';
                        $statusLabel = 'Upcoming';
                        $badgeClass = 'badge-upcoming';
                    } elseif ($isOnLeave) {
                        $status = 'approved_leave';
                        $statusLabel = '🌴 ' . ucfirst($leaveType) . ' Leave';
                        $badgeClass = 'badge-leave';
                        $empSummary['approved_leaves']++;
                        $grandTotals['total_approved_leaves']++;
                    } elseif ($isSwappedOff) {
                        $status = 'swapped_off';
                        $statusLabel = '🔄 Swapped Weekly Off';
                        $badgeClass = 'badge-swapped-off';
                    } elseif ($isAltOff) {
                        $status = 'alt_off';
                        $statusLabel = '⭐ Alternate Day Off';
                        $badgeClass = 'badge-alt-off';
                    } elseif ($isDayOff) {
                        $status = 'day_off';
                        $statusLabel = '🏖️ Day Off';
                        $badgeClass = 'badge-holiday';
                        if (!isset($empSummary['day_offs'])) $empSummary['day_offs'] = 0;
                        $empSummary['day_offs']++;
                        if (!isset($grandTotals['total_day_offs'])) $grandTotals['total_day_offs'] = 0;
                        $grandTotals['total_day_offs']++;
                    } elseif ($isOpenFlexible) {
                        // Open / Flexible Shift (No fixed threshold)
                        if ($dutyHours > 0) {
                            $status = 'full_day';
                            $statusLabel = $isOffDayWorked ? '🟢 Shift Logged (Off Worked ⭐)' : '🟢 Shift Logged';
                            $badgeClass = 'badge-present';
                            $empSummary['full_days']++;
                            $grandTotals['total_present_full']++;
                        } elseif ($isEmpWeeklyOff) {
                            $status = 'weekend';
                            $statusLabel = 'Weekly Off';
                            $badgeClass = 'badge-weekend';
                        } elseif ($d['is_today']) {
                            $status = 'today_pending';
                            $statusLabel = '⏳ Shift Pending';
                            $badgeClass = 'badge-today-pending';
                        } else {
                            $status = 'absent';
                            $statusLabel = '⚪ Off Duty';
                            $badgeClass = 'badge-absent';
                        }
                    } else {
                        // Fixed Shift Evaluation
                        if ($dutyHours >= $fullThreshold) {
                            $status = 'full_day';
                            $statusLabel = '🟢 Full Day';
                            $badgeClass = 'badge-present';
                            $empSummary['full_days']++;
                            $grandTotals['total_present_full']++;
                        } elseif ($dutyHours >= $shortThreshold) {
                            if ($d['is_today'] && !$hasCheckOut) {
                                $status = 'working';
                                $statusLabel = '🟢 Working (' . formatDutyTime($dutySeconds) . ')';
                                $badgeClass = 'badge-present';
                                $empSummary['full_days']++;
                                $grandTotals['total_present_full']++;
                            } else {
                                $status = 'short_leave';
                                $statusLabel = '🟡 Short Leave';
                                $badgeClass = 'badge-short-leave';
                                $empSummary['short_leaves']++;
                                $grandTotals['total_short_leaves']++;
                            }
                        } elseif ($dutyHours >= $halfThreshold) {
                            if ($d['is_today'] && !$hasCheckOut) {
                                $status = 'working';
                                $statusLabel = '🟢 Working (' . formatDutyTime($dutySeconds) . ')';
                                $badgeClass = 'badge-present';
                                $empSummary['full_days']++;
                                $grandTotals['total_present_full']++;
                            } else {
                                $status = 'half_leave';
                                $statusLabel = '🟠 Half Day';
                                $badgeClass = 'badge-half-leave';
                                $empSummary['half_leaves']++;
                                $grandTotals['total_half_leaves']++;
                            }
                        } elseif ($dutyHours > 0) {
                            if ($d['is_today'] && !$hasCheckOut) {
                                $status = 'working';
                                $statusLabel = '🟢 Working (' . formatDutyTime($dutySeconds) . ')';
                                $badgeClass = 'badge-present';
                                $empSummary['full_days']++;
                                $grandTotals['total_present_full']++;
                            } else {
                                $status = 'incomplete';
                                $statusLabel = '🔴 Incomplete';
                                $badgeClass = 'badge-incomplete';
                                $empSummary['incomplete']++;
                                $grandTotals['total_absences']++;
                            }
                        } elseif ($isEmpWeeklyOff) {
                            $status = 'weekend';
                            $statusLabel = 'Weekly Off';
                            $badgeClass = 'badge-weekend';
                        } elseif ($d['is_today']) {
                            $status = 'today_pending';
                            $statusLabel = '⏳ Shift Pending';
                            $badgeClass = 'badge-today-pending';
                        } else {
                            $status = 'absent';
                            $statusLabel = '🔴 Absent';
                            $badgeClass = 'badge-absent';
                            $empSummary['absences']++;
                            $grandTotals['total_absences']++;
                        }
                    }

                    if ($needDetails) {
                        $empRecords[] = [
                            'date' => $dt,
                            'day' => $d['day'],
                            'day_num' => $d['day_num'],
                            'is_weekend' => $isEmpWeeklyOff,
                            'scheduled_weekly_off' => $scheduledWeeklyOff,
                            'is_future' => $d['is_future'],
                            'is_today' => $d['is_today'],
                            'check_in' => $sheet['check_in_time'] ?? null,
                            'check_out' => $sheet['check_out_time'] ?? null,
                            'duty_seconds' => $dutySeconds,
                            'duty_hours' => $dutyHours,
                            'duty_formatted' => $dutySeconds > 0 ? formatDutyTime($dutySeconds) : '-',
                            'status' => $status,
                            'status_label' => $statusLabel,
                            'badge_class' => $badgeClass,
                            'is_locked' => $sheet ? (int)$sheet['is_locked'] : 0,
                            'leave_type' => $leaveType,
                            'leave_reason' => $leaveReason,
                            'day_type' => $explicitDayType,
                            'holiday_reason' => $holidayReason,
                            'is_off_day_worked' => $isOffDayWorked,
                            'is_swapped_off' => $isSwappedOff,
                            'is_alt_off' => $isAltOff,
                            'swapped_note' => $swappedNote,
                            'swapped_with_date' => $autoSwappedMap[$dt]['swapped_with_date'] ?? ($pairedWorkedOffMap[$dt]['swapped_with_date'] ?? null),
                            'work_summary' => $sheet['work_summary'] ?? '',
                            'remarks' => $sheet['remarks'] ?? ''
                        ];
                    }
                }

                // Summary calculations
                $activeWeeks = array_filter($empSummary['weekly_hours']);
                $empSummary['avg_weekly_hours'] = count($activeWeeks) > 0 
                    ? round(array_sum($activeWeeks) / count($activeWeeks), 1) 
                    : 0;
                $empSummary['duty_formatted'] = formatDutyTime($empSummary['total_duty_seconds']);
                $empSummary['hour_balance'] = round($empSummary['total_duty_hours'] - $empSummary['expected_duty_hours'], 1);
                $empSummary['comp_off_balance'] = $empSummary['off_days_worked'] - $empSummary['alt_days_taken'];

                $grandTotals['total_duty_seconds'] += $empSummary['total_duty_seconds'];
                $grandTotals['total_duty_hours'] += $empSummary['total_duty_hours'];
                $grandTotals['total_expected_hours'] += $empSummary['expected_duty_hours'];

                $empItem = [
                    'employee' => $emp,
                    'summary' => $empSummary
                ];
                if ($needDetails) {
                    $empItem['daily_records'] = $empRecords;
                }

                $reportData[] = $empItem;
            }

            $grandTotals['total_duty_formatted'] = formatDutyTime($grandTotals['total_duty_seconds']);

            echo json_encode([
                'success' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'standard_hours' => $rawStandardParam,
                'grand_totals' => $grandTotals,
                'period_dates' => $periodDates,
                'report_data' => $reportData
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Attendance calculation error: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
