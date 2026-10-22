<?php
/**
 * Hourly Sheet API: Entries CRUD, Lock/Unlock, Print Data
 */
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$data = [];
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
$canUnlock = $isSuperAdmin || $isHod || !empty($currentUserObj['can_unlock_sheets']);
$canInspect = $isSuperAdmin || $isHod || !empty($currentUserObj['can_inspect_sheets']);

if (in_array($action, ['get_sheet', 'get_day_summary', 'get_range_summary', 'print_sheet'])) {
    session_write_close();
}

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

        // HOD permission check: HOD can only access their own sheet or sheets of employees in their department
        $managedDeptIds = [];
        if ($isHod || !$isSuperAdmin) {
            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            if ($userDeptId > 0) $managedDeptIds[] = $userDeptId;
            $stmtManaged = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
            $stmtManaged->execute([$currentUserId]);
            $extra = $stmtManaged->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($extra)) {
                $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extra)));
            }
        }

        if ($isHod && $empId !== (int)$currentUserId && !$isSuperAdmin) {
            $targetDeptId = (int)($employee['department_id'] ?? 0);
            if (!in_array($targetDeptId, $managedDeptIds, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'HODs can only view worksheets for employees in their own department.']);
                exit;
            }
        }

        // Fetch or create daily sheet
        $sheetStmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $sheetStmt->execute([$empId, $date]);
        $sheet = $sheetStmt->fetch();

        $entries = [];
        if ($sheet) {
            $entriesStmt = $pdo->prepare("SELECT * FROM sheet_entries WHERE sheet_id = ? ORDER BY id ASC");
            $entriesStmt->execute([$sheet['id']]);
            $entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($entries as &$entry) {
                if (!empty($entry['custom_data'])) {
                    $entry['custom_data'] = json_decode($entry['custom_data'], true) ?: [];
                } else {
                    $entry['custom_data'] = [];
                }
            }
            unset($entry);
        }

        // Fetch dynamic columns for this employee's department
        $empDeptId = (int)($employee['department_id'] ?? 0);
        if ($empDeptId <= 0) $empDeptId = 2;

        require_once __DIR__ . '/../includes/worksheet_columns_helper.php';
        $columns = getDepartmentWorksheetColumns($pdo, $empDeptId, false);

        // Check if there is an active unclosed overnight shift from recent dates (e.g. checked in last night)
        $activeOvernightSheet = null;
        if ($date === date('Y-m-d') && (!$sheet || empty($sheet['check_in_time']))) {
            $yStmt = $pdo->prepare("
                SELECT * FROM daily_sheets 
                WHERE employee_id = ? 
                  AND check_in_time IS NOT NULL 
                  AND (check_out_time IS NULL OR check_out_time = '')
                  AND (is_locked = 0 OR is_locked IS NULL)
                  AND sheet_date < ?
                ORDER BY sheet_date DESC 
                LIMIT 1
            ");
            $yStmt->execute([$empId, $date]);
            $ySheet = $yStmt->fetch();
            if ($ySheet) {
                $inDateTime = strtotime($ySheet['sheet_date'] . ' ' . $ySheet['check_in_time']);
                if ($inDateTime && (time() - $inDateTime) <= 129600) { // within 36 hours
                    $activeOvernightSheet = $ySheet;
                }
            }
        }

        $isLocked = $sheet ? (int)$sheet['is_locked'] : 0;
        $isAdminOrHod = $isSuperAdmin || ($isHod && in_array((int)$employee['department_id'], $managedDeptIds, true));
        $isSelf = ($empId === (int)$currentUserId);
        $canManageOtherSheets = $isAdminOrHod || !empty($currentUserObj['can_unlock_sheets']);
        $canEditThisSheet = $isSelf ? ($isLocked === 0) : ($canManageOtherSheets && ($isLocked === 0 || $isAdminOrHod));
        $isReadOnlyInspection = (!$isSelf && !$canManageOtherSheets && $canInspect);

        // Calculate monthly off-days taken by this employee in the month of the selected date
        $monthPrefix = date('Y-m', strtotime($date));
        $stmtOffCount = $pdo->prepare("SELECT COUNT(*) FROM daily_sheets WHERE employee_id = ? AND sheet_date LIKE ? AND day_type = 'holiday'");
        $stmtOffCount->execute([$empId, "$monthPrefix%"]);
        $monthlyOffDaysTaken = (int)$stmtOffCount->fetchColumn();

        // Standard monthly off-days quota (default 4 for 24/7 rotational shifts)
        $monthlyOffDaysAllowed = isset($employee['monthly_off_days']) && (int)$employee['monthly_off_days'] > 0 
            ? (int)$employee['monthly_off_days'] 
            : 4;

        // Check if there is an approved HR leave for this date
        $approvedLeave = null;
        try {
            $stmtLeave = $pdo->prepare("
                SELECT id, leave_type, start_date, end_date, days_count, reason 
                FROM hr_leaves 
                WHERE employee_id = ? 
                  AND status = 'approved' 
                  AND ? BETWEEN start_date AND end_date 
                LIMIT 1
            ");
            $stmtLeave->execute([$empId, $date]);
            $approvedLeave = $stmtLeave->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            $approvedLeave = null;
        }

        // Query Duty Roster for this employee and month
        $rosterStmt = $pdo->prepare("
            SELECT * FROM duty_rosters 
            WHERE employee_id = ? AND roster_month = ?
            LIMIT 1
        ");
        $rosterStmt->execute([$empId, $monthPrefix]);
        $rosterEntry = $rosterStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $scheduledShift = $rosterEntry['shift_timing'] ?? null;
        $scheduledWeeklyOff = $rosterEntry['weekly_off'] ?? null;
        $isScheduledOff = false;

        if ($scheduledWeeklyOff) {
            $dayOfWeek = date('l', strtotime($date)); // e.g. 'Sunday', 'Saturday', 'Monday'
            if (strcasecmp($dayOfWeek, $scheduledWeeklyOff) === 0) {
                $isScheduledOff = true;
            } elseif (stripos($scheduledWeeklyOff, 'Once in Month') !== false && !empty($rosterEntry['off_dates_json'])) {
                $offDates = json_decode($rosterEntry['off_dates_json'], true) ?: [];
                if (in_array($date, $offDates, true)) {
                    $isScheduledOff = true;
                }
            }
        }

        // Determine effective day type:
        // If daily_sheets has an explicit day_type: use it.
        // Else if isScheduledOff and no active check-in exists: automatically default to holiday (rest day).
        $effectiveDayType = 'working';
        if ($sheet && !empty($sheet['day_type'])) {
            $effectiveDayType = $sheet['day_type'];
        } elseif ($isScheduledOff && (!$sheet || empty($sheet['check_in_time']))) {
            $effectiveDayType = 'holiday';
        }

        // Look up holiday marker employee name if set
        $holidayMarkerName = null;
        if ($sheet && !empty($sheet['holiday_marked_by'])) {
            try {
                $mStmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
                $mStmt->execute([(int)$sheet['holiday_marked_by']]);
                $holidayMarkerName = $mStmt->fetchColumn() ?: null;
            } catch (Exception $e) {
                $holidayMarkerName = null;
            }
        }

        // Look up HOD checker name if sheet is checked
        $hodCheckedByName = null;
        if (!empty($sheet['hod_checked_by'])) {
            try {
                $stName = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
                $stName->execute([(int)$sheet['hod_checked_by']]);
                $hodCheckedByName = $stName->fetchColumn() ?: null;
            } catch (Exception $e) {
                $hodCheckedByName = null;
            }
        }

        // Fetch department HOD verification & print settings
        $deptRequireCheck = 0;
        $deptBlockPrint = 0;
        try {
            $deptSettingsStmt = $pdo->prepare("
                SELECT require_hod_sheet_check, block_print_before_hod_check 
                FROM departments 
                WHERE id = ?
            ");
            $deptSettingsStmt->execute([$empDeptId]);
            $deptSettings = $deptSettingsStmt->fetch(PDO::FETCH_ASSOC);
            if ($deptSettings) {
                $deptRequireCheck = (int)($deptSettings['require_hod_sheet_check'] ?? 0);
                $deptBlockPrint = (int)($deptSettings['block_print_before_hod_check'] ?? 0);
            }
        } catch (Exception $e) {}

        $isHodChecked = !empty($sheet['is_hod_checked']) ? 1 : 0;
        $canPrint = ($isHodChecked === 1) || ($deptBlockPrint === 0) || $isAdminOrHod;

        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'sheet' => $sheet,
            'entries' => $entries,
            'columns' => $columns,
            'is_locked' => $isLocked,
            'is_hod_checked' => $isHodChecked,
            'hod_checked_by' => $sheet['hod_checked_by'] ?? null,
            'hod_checked_by_name' => $hodCheckedByName,
            'hod_checked_at' => !empty($sheet['hod_checked_at']) ? date('d-M-Y h:i A', strtotime($sheet['hod_checked_at'])) : null,
            'department_settings' => [
                'require_hod_sheet_check' => $deptRequireCheck,
                'block_print_before_hod_check' => $deptBlockPrint
            ],
            'can_print' => $canPrint,
            'active_overnight_sheet' => $activeOvernightSheet,
            'can_edit' => $canEditThisSheet,
            'can_edit_after_lock' => $isSelf ? ($isLocked === 0) : $canManageOtherSheets,
            'is_read_only_inspection' => $isReadOnlyInspection,
            'can_unlock' => $canUnlock,
            'can_mark_check' => ($isSuperAdmin || $isHod),
            'monthly_off_days_taken' => $monthlyOffDaysTaken,
            'monthly_off_days_allowed' => $monthlyOffDaysAllowed,
            'approved_leave' => $approvedLeave,
            'holiday_marker_name' => $holidayMarkerName,
            'roster_entry' => $rosterEntry,
            'scheduled_shift' => $scheduledShift,
            'scheduled_weekly_off' => $scheduledWeeklyOff,
            'is_scheduled_off' => $isScheduledOff,
            'effective_day_type' => $effectiveDayType
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

        // Fetch employee details
        $empCheck = $pdo->prepare("SELECT id, name, department_id FROM employees WHERE id = ?");
        $empCheck->execute([$empId]);
        $targetEmployee = $empCheck->fetch();
        $targetDeptId = $targetEmployee ? (int)$targetEmployee['department_id'] : 2;

        $isSelf = ($empId === (int)$currentUserId);

        // HOD permission check and managed department resolution
        $managedDeptIds = [];
        if ($isHod || !$isSuperAdmin) {
            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            if ($userDeptId > 0) $managedDeptIds[] = $userDeptId;
            $stmtManaged = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
            $stmtManaged->execute([$currentUserId]);
            $extra = $stmtManaged->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($extra)) {
                $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extra)));
            }
        }

        $isAdminOrHod = $isSuperAdmin || ($isHod && in_array($targetDeptId, $managedDeptIds, true));
        $canManageOtherSheets = $isAdminOrHod || !empty($currentUserObj['can_unlock_sheets']);

        // Inspect Other Sheets is view & print only; saving someone else's sheet requires lock/unlock access or HOD/Admin
        if (!$isSelf && !$canManageOtherSheets) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Inspection mode is read-only (view & print only). Permission to Lock & Unlock Sheets is required to modify or save entries.']);
            exit;
        }

        if ($isHod && !$isSelf && !$isSuperAdmin && !in_array($targetDeptId, $managedDeptIds, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'HODs can only save worksheets for employees in their own department.']);
            exit;
        }

        // Check lock status
        $sheetStmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $sheetStmt->execute([$empId, $date]);
        $sheet = $sheetStmt->fetch();

        $isSheetLocked = $sheet && (int)$sheet['is_locked'] === 1;

        // Allow saving entries (especially links / post-checkout allowed fields) even when locked
        $pdo->beginTransaction();
        try {
            if (!$sheet) {
                $insertSheet = $pdo->prepare("
                    INSERT INTO daily_sheets (employee_id, sheet_date, check_in_time, check_out_time, remarks, work_summary, is_locked) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $insertSheet->execute([$empId, $date, $checkInTime, $checkOutTime, $remarks, $workSummary]);
                $sheetId = $pdo->lastInsertId();
                $prevRows = [];
            } else {
                $sheetId = $sheet['id'];

                // Fetch previous entries before any potential modification
                $stmtPrev = $pdo->prepare("SELECT * FROM sheet_entries WHERE sheet_id = ? ORDER BY id ASC");
                $stmtPrev->execute([$sheetId]);
                $prevRows = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);

                $updateFields = ["remarks = ?", "work_summary = ?", "updated_at = CURRENT_TIMESTAMP"];
                $params = [$remarks, $workSummary];

                if ($checkInTime !== null && ($isSuperAdmin || !$sheet['is_locked'])) {
                    $updateFields[] = "check_in_time = ?";
                    $params[] = $checkInTime;
                }
                if ($checkOutTime !== null && $isSuperAdmin) {
                    $updateFields[] = "check_out_time = ?";
                    $params[] = $checkOutTime;
                }
                $params[] = $sheetId;

                $updateSheet = $pdo->prepare("UPDATE daily_sheets SET " . implode(', ', $updateFields) . " WHERE id = ?");
                $updateSheet->execute($params);
            }

            // Anti-Wipe Safeguard: Filter valid rows with content
            $validEntries = [];
            foreach ($entries as $row) {
                $tSlot = trim($row['time_slot'] ?? '');
                $tTitle = trim($row['title'] ?? '');
                $tRawLink = trim($row['link'] ?? '');
                $tLink = ($tRawLink === 'upload') ? '' : $tRawLink;
                $tCustom = $row['custom_data'] ?? [];
                if (!is_array($tCustom)) $tCustom = json_decode($tCustom, true) ?: [];
                $hasCust = false;
                if (!empty($tCustom)) {
                    foreach ($tCustom as $cv) {
                        if (trim((string)$cv) !== '') { $hasCust = true; break; }
                    }
                }
                if (!empty($tTitle) || !empty($tLink) || !empty($tSlot) || $hasCust) {
                    $validEntries[] = $row;
                }
            }

            // If an auto-save sends 0 valid entries while database already has logged rows, DO NOT wipe existing rows!
            $isAuto = !empty($data['is_auto']);
            if ($isAuto && empty($validEntries) && !empty($prevRows)) {
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Active entries preserved against empty auto-save race condition', 
                    'preserved' => true,
                    'sheet_id' => $sheetId
                ]);
                break;
            }

            // If locked and user is NOT admin/HOD, enforce post-checkout column editing rules
            $allowedPostLockKeys = [];
            if ($isSheetLocked && !$isAdminOrHod) {
                require_once __DIR__ . '/../includes/worksheet_columns_helper.php';
                $deptCols = getDepartmentWorksheetColumns($pdo, $targetDeptId, true);
                foreach ($deptCols as $dc) {
                    if (!empty($dc['editable_after_lock'])) {
                        $allowedPostLockKeys[$dc['column_key']] = true;
                    }
                }
            }

            // Delete old entries and insert updated set
            $delEntries = $pdo->prepare("DELETE FROM sheet_entries WHERE sheet_id = ?");
            $delEntries->execute([$sheetId]);

            $insEntry = $pdo->prepare("
                INSERT INTO sheet_entries (sheet_id, time_slot, content_type, department, link, title, custom_data, count_val) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $idx = 0;
            foreach ($entries as $row) {
                // If locked and not admin/HOD, employee cannot create new rows beyond previously logged rows
                if ($isSheetLocked && !$isAdminOrHod) {
                    if ($idx >= count($prevRows)) {
                        break;
                    }
                }

                $prevRow = isset($prevRows[$idx]) ? $prevRows[$idx] : null;

                $timeSlot = trim($row['time_slot'] ?? '');
                $contentType = trim($row['content_type'] ?? 'FB Videos');
                $department = trim($row['department'] ?? 'Digital');
                $title = trim($row['title'] ?? '');
                $rawLink = trim($row['link'] ?? '');
                $link = ($rawLink === 'upload') ? '' : $rawLink;

                // Extract custom column values
                $customData = $row['custom_data'] ?? [];
                if (!is_array($customData)) {
                    $customData = json_decode($customData, true) ?: [];
                }
                foreach ($row as $k => $v) {
                    if (!in_array($k, ['id', 'sheet_id', 'time_slot', 'content_type', 'department', 'link', 'title', 'count_val', 'custom_data', 'created_at'])) {
                        $customData[$k] = $v;
                    }
                }

                // If locked and not admin, preserve previous values for columns that are NOT editable after lock
                if ($isSheetLocked && !$isAdminOrHod && $prevRow) {
                    if (!isset($allowedPostLockKeys['time_slot'])) {
                        $timeSlot = $prevRow['time_slot'];
                    }
                    if (!isset($allowedPostLockKeys['content_type'])) {
                        $contentType = $prevRow['content_type'];
                    }
                    if (!isset($allowedPostLockKeys['department'])) {
                        $department = $prevRow['department'];
                    }
                    if (!isset($allowedPostLockKeys['title'])) {
                        $title = $prevRow['title'];
                    }
                    if (!isset($allowedPostLockKeys['link'])) {
                        $link = $prevRow['link'];
                    }

                    // For custom data, keep previous values for disallowed keys
                    $prevCustom = !empty($prevRow['custom_data']) ? json_decode($prevRow['custom_data'], true) : [];
                    if (is_array($prevCustom)) {
                        foreach ($prevCustom as $pk => $pv) {
                            if (!isset($allowedPostLockKeys[$pk])) {
                                $customData[$pk] = $pv;
                            }
                        }
                    }
                }

                $hasCustomVal = false;
                if (!empty($customData)) {
                    foreach ($customData as $cv) {
                        if (trim((string)$cv) !== '') { $hasCustomVal = true; break; }
                    }
                }
                $customDataJson = !empty($customData) ? json_encode($customData) : null;

                // A valid real work unit must have a description or link or time slot or custom data
                if (empty($title) && empty($link) && empty($timeSlot) && !$hasCustomVal) {
                    $idx++;
                    continue; // skip completely blank rows
                }

                $countVal = 1; // 1 real unit of work per entry row

                $insEntry->execute([$sheetId, $timeSlot, $contentType, $department, $link, $title, $customDataJson, $countVal]);
                $idx++;
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

        if ($isHod && !$isSuperAdmin) {
            $targetEmpId = $empId;
            if (!$targetEmpId && $sheetId) {
                $targetEmpId = (int)$pdo->query("SELECT employee_id FROM daily_sheets WHERE id = {$sheetId}")->fetchColumn();
            }
            if ($targetEmpId && $targetEmpId !== (int)$currentUserId) {
                $empCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
                $empCheck->execute([$targetEmpId]);
                $targetDeptId = (int)$empCheck->fetchColumn();

                $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
                $managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
                $stmtManaged = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
                $stmtManaged->execute([$currentUserId]);
                $extra = $stmtManaged->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($extra)) {
                    $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extra)));
                }

                if (!in_array($targetDeptId, $managedDeptIds, true)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'HODs can only lock or unlock worksheets for employees in their own department.']);
                    exit;
                }
            }
        }

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

    case 'toggle_hod_check':
        if (!$isSuperAdmin && !$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only Department Heads (HOD) and Admins can mark worksheets as checked.']);
            exit;
        }

        $sheetId = (int)($data['sheet_id'] ?? 0);
        $empId = (int)($data['employee_id'] ?? 0);
        $date = $data['date'] ?? date('Y-m-d');
        $checkState = isset($data['is_checked']) ? (int)$data['is_checked'] : 1;
        $notes = trim($data['notes'] ?? '');

        // Verify target employee department for HODs
        $targetEmpId = $empId;
        if (!$targetEmpId && $sheetId) {
            $targetEmpId = (int)$pdo->query("SELECT employee_id FROM daily_sheets WHERE id = {$sheetId}")->fetchColumn();
        }

        if ($isHod && !$isSuperAdmin && $targetEmpId) {
            $empCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $empCheck->execute([$targetEmpId]);
            $targetDeptId = (int)$empCheck->fetchColumn();

            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            $managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
            $stmtManaged = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
            $stmtManaged->execute([$currentUserId]);
            $extra = $stmtManaged->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($extra)) {
                $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extra)));
            }

            if (!in_array($targetDeptId, $managedDeptIds, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'HODs can only verify worksheets for employees in their own department.']);
                exit;
            }
        }

        // Find or create sheet if it doesn't exist yet
        if (!$sheetId && $empId && $date) {
            $sCheck = $pdo->prepare("SELECT id FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
            $sCheck->execute([$empId, $date]);
            $sheetId = (int)$sCheck->fetchColumn();

            if (!$sheetId) {
                $sCreate = $pdo->prepare("INSERT INTO daily_sheets (employee_id, sheet_date, created_at) VALUES (?, ?, NOW())");
                $sCreate->execute([$empId, $date]);
                $sheetId = (int)$pdo->lastInsertId();
            }
        }

        if (!$sheetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing sheet identifier']);
            exit;
        }

        $checkerName = $currentUserObj['name'] ?? 'HOD';

        if ($checkState === 1) {
            $stmt = $pdo->prepare("
                UPDATE daily_sheets 
                SET is_hod_checked = 1, hod_checked_by = ?, hod_checked_at = NOW(), hod_checked_notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $notes, $sheetId]);
            $msg = 'Worksheet successfully marked as Checked and Verified by HOD.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE daily_sheets 
                SET is_hod_checked = 0, hod_checked_by = NULL, hod_checked_at = NULL, hod_checked_notes = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$sheetId]);
            $msg = 'Worksheet verification removed / reopened.';
        }

        echo json_encode([
            'success' => true,
            'is_hod_checked' => $checkState,
            'hod_checked_by' => ($checkState ? (int)$currentUserId : null),
            'hod_checked_by_name' => ($checkState ? $checkerName : null),
            'hod_checked_at' => ($checkState ? date('d-M-Y h:i A') : null),
            'message' => $msg
        ]);
        break;

    case 'toggle_day_type':
        $empId = (int)($data['employee_id'] ?? $currentUserId);
        $date = $data['date'] ?? date('Y-m-d');
        $targetType = ($data['day_type'] ?? '') === 'holiday' ? 'holiday' : 'working';
        $reason = trim($data['reason'] ?? '');

        // Permission: User can mark their own sheet, or Super Admin, or HOD for their department
        $empCheck = $pdo->prepare("SELECT id, name, department_id FROM employees WHERE id = ?");
        $empCheck->execute([$empId]);
        $targetEmp = $empCheck->fetch();
        if (!$targetEmp) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

        $canToggle = ($empId === (int)$currentUserId) || $isSuperAdmin;
        if (!$canToggle && $isHod) {
            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            $managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
            $stmtManaged = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
            $stmtManaged->execute([$currentUserId]);
            $extra = $stmtManaged->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($extra)) {
                $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extra)));
            }
            if (in_array((int)$targetEmp['department_id'], $managedDeptIds, true)) {
                $canToggle = true;
            }
        }

        if (!$canToggle) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized to update rest day status for this employee.']);
            exit;
        }

        // Fetch or create sheet
        $sheetStmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
        $sheetStmt->execute([$empId, $date]);
        $sheet = $sheetStmt->fetch();

        $defaultHolidayReason = 'Scheduled Rotational Day Off';

        if (!$sheet) {
            $ins = $pdo->prepare("
                INSERT INTO daily_sheets 
                    (employee_id, sheet_date, day_type, holiday_reason, holiday_marked_by, remarks, work_summary, is_locked)
                VALUES 
                    (?, ?, ?, ?, ?, '', '', 0)
            ");
            $ins->execute([
                $empId, 
                $date, 
                $targetType, 
                ($targetType === 'holiday' ? ($reason ?: $defaultHolidayReason) : null), 
                $currentUserId
            ]);
            $sheetId = $pdo->lastInsertId();
        } else {
            $sheetId = $sheet['id'];
            $upd = $pdo->prepare("
                UPDATE daily_sheets 
                SET day_type = ?, 
                    holiday_reason = ?, 
                    holiday_marked_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upd->execute([
                $targetType,
                ($targetType === 'holiday' ? ($reason ?: ($sheet['holiday_reason'] ?: $defaultHolidayReason)) : null),
                $currentUserId,
                $sheetId
            ]);
        }

        // Return updated off-day count for the month
        $monthPrefix = date('Y-m', strtotime($date));
        $stmtOffCount = $pdo->prepare("SELECT COUNT(*) FROM daily_sheets WHERE employee_id = ? AND sheet_date LIKE ? AND day_type = 'holiday'");
        $stmtOffCount->execute([$empId, "$monthPrefix%"]);
        $monthlyOffDaysTaken = (int)$stmtOffCount->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => $targetType === 'holiday' 
                ? 'Date marked as Scheduled Day Off / Holiday.' 
                : 'Date reverted to regular working sheet.',
            'day_type' => $targetType,
            'monthly_off_days_taken' => $monthlyOffDaysTaken
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
