<?php
/**
 * Department Tracking Options API
 * Allows HODs and Admins to customize Content Types and Tracking Departments/Desks for their department
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

$data = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = $_SESSION['user_id'] ?? ($data['employee_id'] ?? 0);
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isSuperAdmin = ($currentUserRole === 'super_admin' || $currentUserRole === 'admin');
$isHod = ($currentUserRole === 'hod');
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

function ensureDepartmentDefaults($pdo, $departmentId, $force = false) {
    if ($departmentId <= 0) return;

    if (!$force) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM department_tracking_options WHERE department_id = ? AND is_active = 1");
        $check->execute([$departmentId]);
        if ((int)$check->fetchColumn() > 0) {
            return;
        }
    }

    $defaultContentTypes = ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];
    $defaultTrackingDepts = ['Digital', 'News Room', 'Programming', 'Documentary', 'Others'];

    $insert = $pdo->prepare("
        INSERT INTO department_tracking_options (department_id, option_type, option_name, sort_order, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");

    $order = 1;
    foreach ($defaultContentTypes as $ct) {
        $insert->execute([$departmentId, 'content_type', $ct, $order++]);
    }

    $order = 1;
    foreach ($defaultTrackingDepts as $td) {
        $insert->execute([$departmentId, 'tracking_dept', $td, $order++]);
    }
}

switch ($action) {
    case 'get':
    case 'list':
        $deptId = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : $userDeptId;
        if ($deptId <= 0) {
            $deptId = 2; // Default to Digital Media department if none assigned
        }

        ensureDepartmentDefaults($pdo, $deptId);

        $stmtCT = $pdo->prepare("
            SELECT id, option_name as name, sort_order 
            FROM department_tracking_options 
            WHERE department_id = ? AND option_type = 'content_type' AND is_active = 1 
            ORDER BY sort_order ASC, id ASC
        ");
        $stmtCT->execute([$deptId]);
        $contentTypes = $stmtCT->fetchAll(PDO::FETCH_ASSOC);

        $stmtTD = $pdo->prepare("
            SELECT id, option_name as name, sort_order 
            FROM department_tracking_options 
            WHERE department_id = ? AND option_type = 'tracking_dept' AND is_active = 1 
            ORDER BY sort_order ASC, id ASC
        ");
        $stmtTD->execute([$deptId]);
        $trackingDepts = $stmtTD->fetchAll(PDO::FETCH_ASSOC);

        // Fetch department metadata
        $stmtDeptMeta = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
        $stmtDeptMeta->execute([$deptId]);
        $deptInfo = $stmtDeptMeta->fetch(PDO::FETCH_ASSOC) ?: ['id' => $deptId, 'name' => 'Department'];

        $canManage = $isSuperAdmin || ($isHod && in_array($deptId, $managedDeptIds));

        echo json_encode([
            'success' => true,
            'department_id' => $deptId,
            'department_name' => $deptInfo['name'],
            'can_manage' => $canManage,
            'content_types' => $contentTypes,
            'tracking_departments' => $trackingDepts
        ]);
        break;

    case 'add':
        $deptId = isset($data['department_id']) ? (int)$data['department_id'] : $userDeptId;
        if ($deptId <= 0) $deptId = 2;

        if (!$isSuperAdmin && !($isHod && in_array($deptId, $managedDeptIds))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only customize options for your own department.']);
            exit;
        }

        $type = ($data['option_type'] ?? '') === 'tracking_dept' ? 'tracking_dept' : 'content_type';
        $name = trim($data['option_name'] ?? ($data['name'] ?? ''));

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Option name cannot be empty.']);
            exit;
        }

        // Check if duplicate exists for this department and type
        $stmtCheck = $pdo->prepare("
            SELECT id FROM department_tracking_options 
            WHERE department_id = ? AND option_type = ? AND LOWER(option_name) = LOWER(?) AND is_active = 1
        ");
        $stmtCheck->execute([$deptId, $type, $name]);
        if ($stmtCheck->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'An option with this name already exists in your department.']);
            exit;
        }

        // Determine sort order
        $stmtOrder = $pdo->prepare("
            SELECT COALESCE(MAX(sort_order), 0) + 1 
            FROM department_tracking_options 
            WHERE department_id = ? AND option_type = ? AND is_active = 1
        ");
        $stmtOrder->execute([$deptId, $type]);
        $sortOrder = (int)$stmtOrder->fetchColumn();

        $stmtInsert = $pdo->prepare("
            INSERT INTO department_tracking_options (department_id, option_type, option_name, sort_order, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtInsert->execute([$deptId, $type, $name, $sortOrder]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Tracking option added successfully.',
            'item' => [
                'id' => $newId,
                'name' => $name,
                'sort_order' => $sortOrder,
                'option_type' => $type
            ]
        ]);
        break;

    case 'update':
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['option_name'] ?? ($data['name'] ?? ''));

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID and option name are required.']);
            exit;
        }

        $stmtFind = $pdo->prepare("SELECT * FROM department_tracking_options WHERE id = ?");
        $stmtFind->execute([$id]);
        $opt = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$opt) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Option not found.']);
            exit;
        }

        $optDeptId = (int)$opt['department_id'];
        if (!$isSuperAdmin && !($isHod && in_array($optDeptId, $managedDeptIds))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only edit options for your own department.']);
            exit;
        }

        $oldName = $opt['option_name'];

        // Update option name
        $stmtUpdate = $pdo->prepare("UPDATE department_tracking_options SET option_name = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$name, $id]);

        // If requested, update historical worksheet entries for this department to preserve data consistency
        $updateEntries = isset($data['update_entries']) ? (bool)$data['update_entries'] : true;
        $updatedRows = 0;

        if ($updateEntries && strcasecmp($oldName, $name) !== 0) {
            try {
                if ($opt['option_type'] === 'content_type') {
                    $stmtHist = $pdo->prepare("
                        UPDATE sheet_entries se
                        JOIN daily_sheets ds ON se.sheet_id = ds.id
                        JOIN employees e ON ds.employee_id = e.id
                        SET se.content_type = ?
                        WHERE LOWER(se.content_type) = LOWER(?) AND e.department_id = ?
                    ");
                    $stmtHist->execute([$name, $oldName, $optDeptId]);
                    $updatedRows = $stmtHist->rowCount();
                } else {
                    $stmtHist = $pdo->prepare("
                        UPDATE sheet_entries se
                        JOIN daily_sheets ds ON se.sheet_id = ds.id
                        JOIN employees e ON ds.employee_id = e.id
                        SET se.department = ?
                        WHERE LOWER(se.department) = LOWER(?) AND e.department_id = ?
                    ");
                    $stmtHist->execute([$name, $oldName, $optDeptId]);
                    $updatedRows = $stmtHist->rowCount();
                }
            } catch (Exception $e) {}
        }

        echo json_encode([
            'success' => true,
            'message' => 'Option renamed successfully.',
            'history_updated_rows' => $updatedRows
        ]);
        break;

    case 'delete':
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Option ID is required.']);
            exit;
        }

        $stmtFind = $pdo->prepare("SELECT * FROM department_tracking_options WHERE id = ?");
        $stmtFind->execute([$id]);
        $opt = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$opt) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Option not found.']);
            exit;
        }

        $optDeptId = (int)$opt['department_id'];
        if (!$isSuperAdmin && !($isHod && in_array($optDeptId, $managedDeptIds))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only delete options for your own department.']);
            exit;
        }

        $stmtDel = $pdo->prepare("UPDATE department_tracking_options SET is_active = 0, updated_at = NOW() WHERE id = ?");
        $stmtDel->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Tracking option removed successfully.']);
        break;

    case 'reset_defaults':
        $deptId = isset($data['department_id']) ? (int)$data['department_id'] : $userDeptId;
        if ($deptId <= 0) $deptId = 2;

        if (!$isSuperAdmin && !($isHod && in_array($deptId, $managedDeptIds))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only reset options for your own department.']);
            exit;
        }

        // Soft-delete current options and seed defaults
        $pdo->prepare("UPDATE department_tracking_options SET is_active = 0 WHERE department_id = ?")->execute([$deptId]);
        ensureDepartmentDefaults($pdo, $deptId, true);

        echo json_encode(['success' => true, 'message' => 'Reset to default tracking options successfully.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
