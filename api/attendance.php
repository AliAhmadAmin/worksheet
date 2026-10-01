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
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isSuperAdmin = ($currentUserRole === 'super_admin' || $currentUserRole === 'admin');
$isHod = ($currentUserRole === 'hod');
$isHr = ($currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr') || !empty($currentUserObj['can_manage_hr']));
$hasGlobalAttendance = ($isSuperAdmin || $isHr);
$userDeptId = (int)($currentUserObj['department_id'] ?? 0);

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
        if (!$hasGlobalAttendance) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Super Admin or HR access required.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $inTime = trim($data['check_in_time'] ?? '');
        $outTime = trim($data['check_out_time'] ?? '');

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

        $isLocked = isset($data['is_locked']) ? (int)$data['is_locked'] : ($sheet ? (int)$sheet['is_locked'] : 0);

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
        $deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' && $_GET['department_id'] !== 'all' ? (int)$_GET['department_id'] : null;

        $whereClauses = ["e.is_active = 1"];
        $params = [$date];

        if (!$hasGlobalAttendance && $userDeptId > 0) {
            $whereClauses[] = "e.department_id = ?";
            $params[] = $userDeptId;
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
            {$whereSql}
            ORDER BY 
                CASE e.role 
                    WHEN 'super_admin' THEN 1 
                    WHEN 'admin' THEN 1 
                    WHEN 'hr' THEN 2 
                    WHEN 'hod' THEN 3 
                    WHEN 'team_lead' THEN 4 
                    ELSE 5 
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

            if (!$hasGlobalAttendance && $userDeptId > 0) {
                $whereClauses[] = "e.department_id = ?";
                $params[] = $userDeptId;
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
                SELECT e.id, e.name, e.email, e.designation, e.role, e.avatar, e.department_id,
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
                        WHEN 'team_lead' THEN 4 
                        ELSE 5 
                    END,
                    d.id ASC, e.name ASC
            ");
            $empStmt->execute($params);
            $employees = $empStmt->fetchAll();

            // Fetch all daily sheets for this date range
            $sheetStmt = $pdo->prepare("
                SELECT ds.*, 
                       (SELECT COUNT(*) FROM sheet_entries se WHERE se.sheet_id = ds.id) as total_entries
                FROM daily_sheets ds
                WHERE ds.sheet_date BETWEEN ? AND ?
            ");
            $sheetStmt->execute([$startDate, $endDate]);
            $allSheets = $sheetStmt->fetchAll();
            
            // Group sheets by employee_id and date
            $sheetsByEmp = [];
            foreach ($allSheets as $sh) {
                $sheetsByEmp[$sh['employee_id']][$sh['sheet_date']] = $sh;
            }

            // Fetch all approved leaves for this date range
            $approvedLeaves = [];
            try {
                $leaveStmt = $pdo->prepare("
                    SELECT * FROM hr_leaves 
                    WHERE status = 'approved' 
                      AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (start_date <= ? AND end_date >= ?))
                ");
                $leaveStmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
                $approvedLeaves = $leaveStmt->fetchAll();
            } catch (Exception $e) {
                $approvedLeaves = [];
            }

            // Generate list of days between startDate and endDate
            $periodDates = [];
            $currDate = $startDate;
            $today = date('Y-m-d');
            while (strtotime($currDate) <= strtotime($endDate)) {
                $periodDates[] = [
                    'date' => $currDate,
                    'day' => date('D', strtotime($currDate)),
                    'day_num' => (int)date('d', strtotime($currDate)),
                    'is_weekend' => (date('N', strtotime($currDate)) >= 7),
                    'is_future' => ($currDate > $today)
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
                $empId = $emp['id'];
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
                    'weekly_hours' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]
                ];

                foreach ($periodDates as $d) {
                    $dt = $d['date'];
                    $sheet = $sheetsByEmp[$empId][$dt] ?? null;
                    
                    // Check if on approved leave
                    $isOnLeave = false;
                    $leaveType = '';
                    $leaveReason = '';
                    foreach ($approvedLeaves as $lv) {
                        if ($lv['employee_id'] == $empId && $dt >= $lv['start_date'] && $dt <= $lv['end_date']) {
                            $isOnLeave = true;
                            $leaveType = $lv['leave_type'];
                            $leaveReason = $lv['reason'] ?? '';
                            break;
                        }
                    }

                    $dutySeconds = $sheet ? (int)$sheet['total_duty_seconds'] : 0;
                    if ($sheet && empty($sheet['check_out_time']) && !empty($sheet['check_in_time']) && $dt === $today) {
                        $inTs = strtotime($dt . ' ' . $sheet['check_in_time']);
                        if ($inTs) $dutySeconds = max(0, time() - $inTs);
                    }

                    $dutyHours = round($dutySeconds / 3600, 2);
                    $weekNum = min(6, (int)ceil($d['day_num'] / 7));

                    if (!$d['is_future']) {
                        $empSummary['working_days_count']++;
                        $empSummary['total_duty_seconds'] += $dutySeconds;
                        $empSummary['total_duty_hours'] += $dutyHours;
                        $empSummary['weekly_hours'][$weekNum] += $dutyHours;
                        if (!$isOpenFlexible && !$d['is_weekend']) {
                            $empSummary['expected_duty_hours'] += $shiftHours;
                        }
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
                    } elseif ($isOpenFlexible) {
                        // Open / Flexible Shift (No fixed threshold)
                        if ($dutyHours > 0) {
                            $status = 'full_day';
                            $statusLabel = '🟢 Shift Logged (' . formatDutyTime($dutySeconds) . ')';
                            $badgeClass = 'badge-present';
                            $empSummary['full_days']++;
                            $grandTotals['total_present_full']++;
                        } elseif ($d['is_weekend']) {
                            $status = 'weekend';
                            $statusLabel = 'Weekend';
                            $badgeClass = 'badge-weekend';
                        } else {
                            $status = 'absent';
                            $statusLabel = '⚪ Off Duty';
                            $badgeClass = 'badge-absent';
                        }
                    } else {
                        // Fixed Shift Evaluation
                        $fullThreshold = max(1.0, $shiftHours - 0.5);
                        $shortThreshold = max(1.0, round($shiftHours * 0.65, 1));
                        $halfThreshold = max(0.5, round($shiftHours * 0.35, 1));

                        if ($dutyHours >= $fullThreshold) {
                            $status = 'full_day';
                            $statusLabel = '🟢 Full Day (' . formatDutyTime($dutySeconds) . ')';
                            $badgeClass = 'badge-present';
                            $empSummary['full_days']++;
                            $grandTotals['total_present_full']++;
                        } elseif ($dutyHours >= $shortThreshold) {
                            $status = 'short_leave';
                            $statusLabel = '🟡 Short Leave (~' . round($shiftHours * 0.75, 1) . 'h)';
                            $badgeClass = 'badge-short-leave';
                            $empSummary['short_leaves']++;
                            $grandTotals['total_short_leaves']++;
                        } elseif ($dutyHours >= $halfThreshold) {
                            $status = 'half_leave';
                            $statusLabel = '🟠 Half Day (~' . round($shiftHours * 0.5, 1) . 'h)';
                            $badgeClass = 'badge-half-leave';
                            $empSummary['half_leaves']++;
                            $grandTotals['total_half_leaves']++;
                        } elseif ($dutyHours > 0) {
                            $status = 'incomplete';
                            $statusLabel = '🔴 Incomplete';
                            $badgeClass = 'badge-incomplete';
                            $empSummary['incomplete']++;
                            $grandTotals['total_absences']++;
                        } elseif ($d['is_weekend']) {
                            $status = 'weekend';
                            $statusLabel = 'Weekend';
                            $badgeClass = 'badge-weekend';
                        } else {
                            $status = 'absent';
                            $statusLabel = '🔴 Absent';
                            $badgeClass = 'badge-absent';
                            $empSummary['absences']++;
                            $grandTotals['total_absences']++;
                        }
                    }

                    $empRecords[] = [
                        'date' => $dt,
                        'day' => $d['day'],
                        'day_num' => $d['day_num'],
                        'is_weekend' => $d['is_weekend'],
                        'is_future' => $d['is_future'],
                        'check_in' => $sheet['check_in_time'] ?? null,
                        'check_out' => $sheet['check_out_time'] ?? null,
                        'duty_seconds' => $dutySeconds,
                        'duty_hours' => $dutyHours,
                        'duty_formatted' => $dutySeconds > 0 ? formatDutyTime($dutySeconds) : '-',
                        'status' => $status,
                        'status_label' => $statusLabel,
                        'badge_class' => $badgeClass,
                        'is_locked' => $sheet ? (int)$sheet['is_locked'] : 0,
                        'entries_count' => $sheet ? (int)$sheet['total_entries'] : 0,
                        'leave_type' => $leaveType,
                        'leave_reason' => $leaveReason,
                        'work_summary' => $sheet['work_summary'] ?? '',
                        'remarks' => $sheet['remarks'] ?? ''
                    ];
                }

                // Summary calculations
                $activeWeeks = array_filter($empSummary['weekly_hours']);
                $empSummary['avg_weekly_hours'] = count($activeWeeks) > 0 
                    ? round(array_sum($activeWeeks) / count($activeWeeks), 1) 
                    : 0;
                $empSummary['duty_formatted'] = formatDutyTime($empSummary['total_duty_seconds']);
                $empSummary['hour_balance'] = round($empSummary['total_duty_hours'] - $empSummary['expected_duty_hours'], 1);

                $grandTotals['total_duty_seconds'] += $empSummary['total_duty_seconds'];
                $grandTotals['total_duty_hours'] += $empSummary['total_duty_hours'];
                $grandTotals['total_expected_hours'] += $empSummary['expected_duty_hours'];

                $reportData[] = [
                    'employee' => $emp,
                    'summary' => $empSummary,
                    'daily_records' => $empRecords
                ];
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
