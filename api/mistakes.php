<?php
/**
 * Employee Quality & Mistakes Management API
 * Discover Pakistan UHD TV Timesheet & Operations Portal
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = $_SESSION['user_id'] ?? 0;
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isSuperAdmin = ($currentUserRole === 'super_admin' || $currentUserRole === 'admin');
$isHod = ($currentUserRole === 'hod' || $currentUserRole === 'deputy_hod');
$isHr = ($currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr') || (!empty($currentUserObj['can_manage_hr']) && $currentUserRole !== 'hod'));
$canInspect = $isSuperAdmin || $isHod || $isHr || !empty($currentUserObj['can_inspect_sheets']) || !empty($currentUserObj['can_log_mistakes']);
$canLogMistakes = $isSuperAdmin || $isHod || $isHr || !empty($currentUserObj['can_log_mistakes']) || !empty($currentUserObj['can_inspect_sheets']);

$userDeptId = (int)($currentUserObj['department_id'] ?? 0);
$managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
if ($isHod) {
    $managedDeptIds = getHodManagedDepartmentIds($pdo, $currentUserId, $userDeptId);
}

switch ($action) {
    case 'list':
        $empId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        $sheetId = isset($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : 0;
        $entryId = isset($_GET['entry_id']) ? (int)$_GET['entry_id'] : 0;
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        $month = $_GET['month'] ?? ''; // e.g. '2026-11'
        $year = $_GET['year'] ?? '';   // e.g. '2026'

        $where = ["1=1"];
        $params = [];

        // Role-based visibility
        if (!$isSuperAdmin && !$isHr) {
            if ($isHod) {
                if (!empty($managedDeptIds)) {
                    $inDepts = implode(',', array_fill(0, count($managedDeptIds), '?'));
                    $where[] = "(e.department_id IN ($inDepts) OR em.employee_id = ?)";
                    foreach ($managedDeptIds as $d) { $params[] = $d; }
                    $params[] = $currentUserId;
                } else {
                    $where[] = "em.employee_id = ?";
                    $params[] = $currentUserId;
                }
            } else {
                // Regular staff see only their own mistakes
                $where[] = "em.employee_id = ?";
                $params[] = $currentUserId;
            }
        }

        if ($empId > 0) {
            $where[] = "em.employee_id = ?";
            $params[] = $empId;
        }
        if ($sheetId > 0) {
            $where[] = "em.sheet_id = ?";
            $params[] = $sheetId;
        }
        if ($entryId > 0) {
            $where[] = "em.entry_id = ?";
            $params[] = $entryId;
        }
        if ($deptId > 0) {
            $where[] = "e.department_id = ?";
            $params[] = $deptId;
        }
        if (!empty($startDate) && !empty($endDate)) {
            $where[] = "em.mistake_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } elseif (!empty($month)) {
            $where[] = "DATE_FORMAT(em.mistake_date, '%Y-%m') = ?";
            $params[] = $month;
        } elseif (!empty($year)) {
            $where[] = "YEAR(em.mistake_date) = ?";
            $params[] = $year;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                em.*,
                e.name as employee_name,
                e.designation as employee_designation,
                d.name as department_name,
                t.name as team_name,
                u.name as logged_by_name,
                u.role as logged_by_role,
                se.time_slot as entry_time_slot,
                se.content_type as entry_content_type,
                se.title as entry_title,
                se.link as entry_link
            FROM employee_mistakes em
            JOIN employees e ON em.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN employees u ON em.logged_by = u.id
            LEFT JOIN sheet_entries se ON em.entry_id = se.id
            WHERE {$whereSql}
            ORDER BY em.mistake_date DESC, em.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $mistakes = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'count' => count($mistakes),
            'mistakes' => $mistakes
        ]);
        break;

    case 'summary':
        $empId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $targetMonth = $_GET['month'] ?? date('Y-m'); // e.g. 2026-11
        $targetYear = $_GET['year'] ?? date('Y');     // e.g. 2026

        // 1. Employee-specific month & year summary
        if ($empId > 0) {
            $stmtMonth = $pdo->prepare("SELECT COUNT(*) FROM employee_mistakes WHERE employee_id = ? AND DATE_FORMAT(mistake_date, '%Y-%m') = ?");
            $stmtMonth->execute([$empId, $targetMonth]);
            $countMonth = (int)$stmtMonth->fetchColumn();

            $stmtYear = $pdo->prepare("SELECT COUNT(*) FROM employee_mistakes WHERE employee_id = ? AND YEAR(mistake_date) = ?");
            $stmtYear->execute([$empId, $targetYear]);
            $countYear = (int)$stmtYear->fetchColumn();

            // Severity breakdown in year
            $stmtSev = $pdo->prepare("
                SELECT severity, COUNT(*) as cnt 
                FROM employee_mistakes 
                WHERE employee_id = ? AND YEAR(mistake_date) = ? 
                GROUP BY severity
            ");
            $stmtSev->execute([$empId, $targetYear]);
            $bySeverity = $stmtSev->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            // Category breakdown in year
            $stmtCat = $pdo->prepare("
                SELECT category, COUNT(*) as cnt 
                FROM employee_mistakes 
                WHERE employee_id = ? AND YEAR(mistake_date) = ? 
                GROUP BY category 
                ORDER BY cnt DESC
            ");
            $stmtCat->execute([$empId, $targetYear]);
            $byCategory = $stmtCat->fetchAll() ?: [];

            // Mistakes grouped by day for target month
            $stmtDays = $pdo->prepare("
                SELECT mistake_date, COUNT(*) as count 
                FROM employee_mistakes 
                WHERE employee_id = ? AND DATE_FORMAT(mistake_date, '%Y-%m') = ? 
                GROUP BY mistake_date
            ");
            $stmtDays->execute([$empId, $targetMonth]);
            $byDays = $stmtDays->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            echo json_encode([
                'success' => true,
                'employee_id' => $empId,
                'month' => $targetMonth,
                'year' => $targetYear,
                'count_month' => $countMonth,
                'count_year' => $countYear,
                'by_severity' => $bySeverity,
                'by_category' => $byCategory,
                'by_days' => $byDays
            ]);
            break;
        }

        // 2. Department or company-wide summary
        $where = ["1=1"];
        $params = [];
        if ($deptId > 0) {
            $where[] = "e.department_id = ?";
            $params[] = $deptId;
        } elseif (!$isSuperAdmin && !empty($managedDeptIds)) {
            $inDepts = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $where[] = "e.department_id IN ($inDepts)";
            foreach ($managedDeptIds as $d) { $params[] = $d; }
        }

        $paramsMonth = array_merge($params, [$targetMonth]);
        $whereMonth = implode(' AND ', array_merge($where, ["DATE_FORMAT(em.mistake_date, '%Y-%m') = ?"]));

        $stmtEmpCounts = $pdo->prepare("
            SELECT em.employee_id, COUNT(*) as total_mistakes
            FROM employee_mistakes em
            JOIN employees e ON em.employee_id = e.id
            WHERE {$whereMonth}
            GROUP BY em.employee_id
        ");
        $stmtEmpCounts->execute($paramsMonth);
        $empCounts = $stmtEmpCounts->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        echo json_encode([
            'success' => true,
            'month' => $targetMonth,
            'year' => $targetYear,
            'employee_counts' => $empCounts
        ]);
        break;

    case 'create':
    case 'add':
        if (!$canLogMistakes) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Only Supervisors, HODs, or staff with Log Mistake access can log mistakes.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);

        // Department scope validation for delegated users and HODs
        if (!$isSuperAdmin && !$isHr && $empId > 0) {
            $stmtTargetDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtTargetDept->execute([$empId]);
            $targetDeptId = (int)$stmtTargetDept->fetchColumn();
            if ($targetDeptId && !in_array($targetDeptId, $managedDeptIds, true) && $targetDeptId !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: You can only log mistakes for employees in your department.']);
                exit;
            }
        }
        $mistakeDate = trim($data['mistake_date'] ?? date('Y-m-d'));
        $category = trim($data['category'] ?? 'General / Other');
        $severity = trim($data['severity'] ?? 'minor');
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $sheetId = !empty($data['sheet_id']) ? (int)$data['sheet_id'] : null;
        $entryId = !empty($data['entry_id']) ? (int)$data['entry_id'] : null;
        $penaltyPoints = isset($data['penalty_points']) ? max(1, (int)$data['penalty_points']) : 1;

        if ($empId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee is required.']);
            exit;
        }

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Mistake title or summary is required.']);
            exit;
        }

        if (!in_array($severity, ['minor', 'moderate', 'critical'], true)) {
            $severity = 'minor';
        }

        // If sheetId is not provided, try to find daily_sheets record for this employee & date
        if (!$sheetId && !empty($mistakeDate)) {
            $stmtSheetFind = $pdo->prepare("SELECT id FROM daily_sheets WHERE employee_id = ? AND sheet_date = ? LIMIT 1");
            $stmtSheetFind->execute([$empId, $mistakeDate]);
            $sheetId = $stmtSheetFind->fetchColumn() ?: null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO employee_mistakes 
                (employee_id, sheet_id, entry_id, mistake_date, category, severity, title, description, logged_by, penalty_points, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $empId,
            $sheetId,
            $entryId,
            $mistakeDate,
            $category,
            $severity,
            $title,
            $description,
            $currentUserId,
            $penaltyPoints
        ]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Mistake successfully logged.',
            'mistake_id' => $newId
        ]);
        break;

    case 'delete':
        if (!$canInspect) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid mistake ID.']);
            exit;
        }

        // Security check: Only Admin, or the HOD of that department, or the user who logged it can delete
        $checkStmt = $pdo->prepare("
            SELECT em.*, e.department_id 
            FROM employee_mistakes em 
            JOIN employees e ON em.employee_id = e.id 
            WHERE em.id = ?
        ");
        $checkStmt->execute([$id]);
        $mistake = $checkStmt->fetch();

        if (!$mistake) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Mistake record not found.']);
            exit;
        }

        $canDelete = $isSuperAdmin 
            || ($mistake['logged_by'] == $currentUserId) 
            || ($isHod && in_array((int)$mistake['department_id'], $managedDeptIds, true));

        if (!$canDelete) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this mistake record.']);
            exit;
        }

        $pdo->prepare("DELETE FROM employee_mistakes WHERE id = ?")->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Mistake record successfully removed.'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
