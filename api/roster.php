<?php
/**
 * Duty Roster API: Monthly Rotational Schedules, Assigned Shift Timings & Weekly Offs
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

$currentUserRole = strtolower($currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee'));
$isSuperAdmin = in_array($currentUserRole, ['super_admin', 'admin']);
$isHod = ($currentUserRole === 'hod');
$isHr = in_array($currentUserRole, ['hr']) || (!empty($currentUserObj['can_manage_hr']) && !$isHod);

// Dept permissions
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

switch ($action) {
    case 'get_roster':
        $month = $_GET['month'] ?? date('Y-m');
        $deptId = (int)($_GET['department_id'] ?? ($userDeptId ?: 2));

        // Dept permission check
        if (!$isSuperAdmin && !$isHr && !in_array($deptId, $managedDeptIds, true) && $deptId !== $userDeptId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized to view roster for this department.']);
            exit;
        }

        $deptStmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $deptStmt->execute([$deptId]);
        $department = $deptStmt->fetch();

        // Fetch teams for this department
        $teamsStmt = $pdo->prepare("SELECT * FROM teams WHERE department_id = ? ORDER BY id ASC");
        $teamsStmt->execute([$deptId]);
        $teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch active employees for this department
        $empStmt = $pdo->prepare("
            SELECT e.id, e.name, e.designation, e.role, e.emp_code, e.avatar, e.team_id, t.name as team_name
            FROM employees e
            LEFT JOIN teams t ON e.team_id = t.id
            WHERE e.department_id = ? AND e.is_active = 1
            ORDER BY 
                CASE e.role WHEN 'hod' THEN 1 WHEN 'admin' THEN 2 WHEN 'team_lead' THEN 3 ELSE 4 END,
                t.id ASC, e.name ASC
        ");
        $empStmt->execute([$deptId]);
        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch existing roster rows for this month & department
        $rosterStmt = $pdo->prepare("
            SELECT * FROM duty_rosters 
            WHERE department_id = ? AND roster_month = ?
        ");
        $rosterStmt->execute([$deptId, $month]);
        $existingRoster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);

        $rosterMap = [];
        foreach ($existingRoster as $r) {
            if (!empty($r['off_dates_json'])) {
                $r['off_dates'] = json_decode($r['off_dates_json'], true) ?: [];
            } else {
                $r['off_dates'] = [];
            }
            $rosterMap[$r['employee_id']] = $r;
        }

        // Check if previous month has records available to copy
        $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
        $prevCountStmt = $pdo->prepare("SELECT COUNT(*) FROM duty_rosters WHERE department_id = ? AND roster_month = ?");
        $prevCountStmt->execute([$deptId, $prevMonth]);
        $hasPrevMonth = ((int)$prevCountStmt->fetchColumn() > 0);

        // Build composite list
        $items = [];
        foreach ($employees as $emp) {
            $eId = (int)$emp['id'];
            $rost = $rosterMap[$eId] ?? null;

            $items[] = [
                'employee_id' => $eId,
                'name' => $emp['name'],
                'designation' => $rost ? ($rost['designation'] ?: $emp['designation']) : ($emp['designation'] ?: 'Staff'),
                'team_id' => $emp['team_id'],
                'team_name' => $emp['team_name'] ?: 'General',
                'avatar' => $emp['avatar'],
                'shift_timing' => $rost ? $rost['shift_timing'] : (!empty($emp['shift_timing']) ? $emp['shift_timing'] : '10:00 AM TO 6:00 PM'),
                'weekly_off' => $rost ? $rost['weekly_off'] : (!empty($emp['weekly_off']) ? $emp['weekly_off'] : 'Sunday'),
                'off_dates' => $rost ? $rost['off_dates'] : [],
                'notes' => $rost ? ($rost['notes'] ?? '') : ''
            ];
        }

        $canEdit = $isSuperAdmin || $isHr || ($isHod && in_array($deptId, $managedDeptIds, true));

        echo json_encode([
            'success' => true,
            'month' => $month,
            'department_id' => $deptId,
            'department_name' => $department['name'] ?? 'Department',
            'teams' => $teams,
            'items' => $items,
            'has_roster' => !empty($existingRoster),
            'has_prev_month' => $hasPrevMonth,
            'prev_month' => $prevMonth,
            'can_edit' => $canEdit
        ]);
        break;

    case 'save_roster':
        $month = $data['month'] ?? date('Y-m');
        $deptId = (int)($data['department_id'] ?? ($userDeptId ?: 2));
        $items = $data['items'] ?? [];

        $canEdit = $isSuperAdmin || $isHr || ($isHod && in_array($deptId, $managedDeptIds, true));
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized to modify duty roster for this department.']);
            exit;
        }

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'No roster entries provided.']);
            exit;
        }

        $stmtUpsert = $pdo->prepare("
            INSERT INTO duty_rosters 
                (department_id, roster_month, employee_id, designation, shift_timing, weekly_off, off_dates_json, notes, created_by)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                designation = VALUES(designation),
                shift_timing = VALUES(shift_timing),
                weekly_off = VALUES(weekly_off),
                off_dates_json = VALUES(off_dates_json),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmtSyncEmp = $pdo->prepare("
            UPDATE employees 
            SET shift_timing = ?, weekly_off = ?, designation = COALESCE(NULLIF(?, ''), designation)
            WHERE id = ?
        ");

        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $eId = (int)($item['employee_id'] ?? 0);
                if ($eId <= 0) continue;

                $desig = trim($item['designation'] ?? '');
                $timing = trim($item['shift_timing'] ?? '10:00 AM TO 6:00 PM');
                $weeklyOff = trim($item['weekly_off'] ?? 'Sunday');
                $offDates = !empty($item['off_dates']) ? json_encode($item['off_dates']) : null;
                $notes = trim($item['notes'] ?? '');

                $stmtUpsert->execute([
                    $deptId,
                    $month,
                    $eId,
                    $desig,
                    $timing,
                    $weeklyOff,
                    $offDates,
                    $notes,
                    $currentUserId
                ]);

                // Two-way sync: Update employee master record so both sides always stay identical
                $stmtSyncEmp->execute([$timing, $weeklyOff, $desig, $eId]);
            }
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Duty Roster for {$month} saved successfully (" . count($items) . " employee schedules updated)."
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error saving roster: ' . $e->getMessage()]);
        }
        break;

    case 'copy_previous':
        $targetMonth = $data['target_month'] ?? date('Y-m');
        $deptId = (int)($data['department_id'] ?? ($userDeptId ?: 2));

        $canEdit = $isSuperAdmin || $isHr || ($isHod && in_array($deptId, $managedDeptIds, true));
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        $prevMonth = date('Y-m', strtotime($targetMonth . '-01 -1 month'));

        $stmtPrev = $pdo->prepare("SELECT * FROM duty_rosters WHERE department_id = ? AND roster_month = ?");
        $stmtPrev->execute([$deptId, $prevMonth]);
        $prevRows = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);

        if (empty($prevRows)) {
            echo json_encode(['success' => false, 'message' => "No duty roster found for previous month ({$prevMonth}) to copy."]);
            exit;
        }

        $stmtCopy = $pdo->prepare("
            INSERT INTO duty_rosters 
                (department_id, roster_month, employee_id, designation, shift_timing, weekly_off, off_dates_json, notes, created_by)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                designation = VALUES(designation),
                shift_timing = VALUES(shift_timing),
                weekly_off = VALUES(weekly_off),
                off_dates_json = VALUES(off_dates_json),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");

        $pdo->beginTransaction();
        try {
            $copied = 0;
            foreach ($prevRows as $row) {
                $stmtCopy->execute([
                    $deptId,
                    $targetMonth,
                    $row['employee_id'],
                    $row['designation'],
                    $row['shift_timing'],
                    $row['weekly_off'],
                    $row['off_dates_json'],
                    $row['notes'],
                    $currentUserId
                ]);
                $copied++;
            }
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Successfully copied {$copied} employee schedules from {$prevMonth} to {$targetMonth}."
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error copying roster: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
