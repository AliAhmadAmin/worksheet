<?php
/**
 * Department Worksheet Columns API
 * Allows HODs and Admins to customize, rename, reorder (left/right), add, and manage worksheet columns
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

require_once __DIR__ . '/../includes/worksheet_columns_helper.php';

switch ($action) {
    case 'get':
    case 'list':
        $deptId = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : $userDeptId;
        if ($deptId <= 0) $deptId = 2; // Default Digital

        $includeHidden = isset($_GET['all']) && $_GET['all'] == '1';
        $columns = getDepartmentWorksheetColumns($pdo, $deptId, $includeHidden);

        $stmtDeptMeta = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
        $stmtDeptMeta->execute([$deptId]);
        $deptInfo = $stmtDeptMeta->fetch(PDO::FETCH_ASSOC) ?: ['id' => $deptId, 'name' => 'Department'];

        $canManage = $isSuperAdmin || ($isHod && in_array($deptId, $managedDeptIds));

        echo json_encode([
            'success' => true,
            'department_id' => $deptId,
            'department_name' => $deptInfo['name'],
            'can_manage' => $canManage,
            'columns' => $columns
        ]);
        break;

    case 'add':
        $deptId = isset($data['department_id']) ? (int)$data['department_id'] : $userDeptId;
        if ($deptId <= 0) $deptId = 2;

        if (!$isSuperAdmin && !($isHod && in_array($deptId, $managedDeptIds))) {
            if (!headers_sent()) http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only customize columns for your own department.']);
            exit;
        }

        $label = trim($data['column_label'] ?? ($data['label'] ?? ''));
        if (empty($label)) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Column label is required.']);
            exit;
        }

        $type = $data['column_type'] ?? 'text';
        $allowedTypes = ['text', 'select', 'link', 'number', 'time'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'text';
        }

        // Generate clean unique key
        $slug = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $label)));
        if (empty($slug)) $slug = 'col';
        $columnKey = 'col_' . $slug . '_' . substr(uniqid(), -4);

        $optionsJson = null;
        if ($type === 'select') {
            $options = $data['options'] ?? [];
            if (is_string($options)) {
                $options = array_values(array_filter(array_map('trim', explode(',', $options))));
            }
            $optionsJson = json_encode($options);
        }

        // Get max sort_order
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM department_worksheet_columns WHERE department_id = ?");
        $stmtMax->execute([$deptId]);
        $nextOrder = (int)$stmtMax->fetchColumn();

        $stmtIns = $pdo->prepare("
            INSERT INTO department_worksheet_columns 
            (department_id, column_key, column_label, column_type, options_json, sort_order, is_visible, is_core)
            VALUES (?, ?, ?, ?, ?, ?, 1, 0)
        ");
        $stmtIns->execute([$deptId, $columnKey, $label, $type, $optionsJson, $nextOrder]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Column added successfully.',
            'column' => [
                'id' => $newId,
                'column_key' => $columnKey,
                'column_label' => $label,
                'column_type' => $type,
                'sort_order' => $nextOrder,
                'is_visible' => 1,
                'is_core' => 0
            ]
        ]);
        break;

    case 'toggle_visibility':
    case 'update':
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Column ID is required.']);
            exit;
        }

        $stmtFind = $pdo->prepare("SELECT * FROM department_worksheet_columns WHERE id = ?");
        $stmtFind->execute([$id]);
        $col = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$col) {
            if (!headers_sent()) http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Column not found.']);
            exit;
        }

        $colDeptId = (int)$col['department_id'];
        if (!$isSuperAdmin && !($isHod && in_array($colDeptId, $managedDeptIds))) {
            if (!headers_sent()) http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only edit columns for your own department.']);
            exit;
        }

        $label = isset($data['column_label']) ? trim($data['column_label']) : $col['column_label'];
        $isVisible = isset($data['is_visible']) ? ((int)$data['is_visible'] ? 1 : 0) : (int)$col['is_visible'];
        $optionsJson = $col['options_json'];

        if (isset($data['options'])) {
            $options = $data['options'];
            if (is_string($options)) {
                $options = array_values(array_filter(array_map('trim', explode(',', $options))));
            }
            $optionsJson = json_encode($options);
        }

        $stmtUp = $pdo->prepare("
            UPDATE department_worksheet_columns 
            SET column_label = ?, is_visible = ?, options_json = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUp->execute([$label, $isVisible, $optionsJson, $id]);

        echo json_encode(['success' => true, 'message' => 'Column updated successfully.']);
        break;

    case 'move':
        $id = (int)($data['id'] ?? 0);
        $direction = strtolower($data['direction'] ?? 'left'); // 'left' or 'right'

        if (!$id) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Column ID is required.']);
            exit;
        }

        $stmtFind = $pdo->prepare("SELECT * FROM department_worksheet_columns WHERE id = ?");
        $stmtFind->execute([$id]);
        $target = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            if (!headers_sent()) http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Column not found.']);
            exit;
        }

        $colDeptId = (int)$target['department_id'];
        if (!$isSuperAdmin && !($isHod && in_array($colDeptId, $managedDeptIds))) {
            if (!headers_sent()) http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }

        // Fetch all columns for this dept ordered
        $stmtAll = $pdo->prepare("SELECT id, sort_order FROM department_worksheet_columns WHERE department_id = ? ORDER BY sort_order ASC, id ASC");
        $stmtAll->execute([$colDeptId]);
        $cols = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $targetIdx = -1;
        for ($i = 0; $i < count($cols); $i++) {
            if ((int)$cols[$i]['id'] === $id) {
                $targetIdx = $i;
                break;
            }
        }

        if ($targetIdx === -1) {
            echo json_encode(['success' => false, 'message' => 'Column not in list.']);
            exit;
        }

        $swapIdx = ($direction === 'left' || $direction === 'up' || $direction === 'prev') ? $targetIdx - 1 : $targetIdx + 1;

        if ($swapIdx >= 0 && $swapIdx < count($cols)) {
            $otherId = (int)$cols[$swapIdx]['id'];
            $otherOrder = (int)$cols[$swapIdx]['sort_order'];
            $myOrder = (int)$cols[$targetIdx]['sort_order'];

            if ($otherOrder === $myOrder) {
                // If duplicates, normalize
                $otherOrder = $swapIdx + 1;
                $myOrder = $targetIdx + 1;
            }

            $pdo->prepare("UPDATE department_worksheet_columns SET sort_order = ? WHERE id = ?")->execute([$otherOrder, $id]);
            $pdo->prepare("UPDATE department_worksheet_columns SET sort_order = ? WHERE id = ?")->execute([$myOrder, $otherId]);
        }

        echo json_encode(['success' => true, 'message' => 'Column order updated.']);
        break;

    case 'reorder':
        $deptId = isset($data['department_id']) ? (int)$data['department_id'] : $userDeptId;
        if ($deptId <= 0) $deptId = 2;

        if (!$isSuperAdmin && !($isHod && in_array($deptId, $managedDeptIds))) {
            if (!headers_sent()) http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }

        $orderIds = $data['order'] ?? [];
        if (!is_array($orderIds)) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Order list required.']);
            exit;
        }

        $upStmt = $pdo->prepare("UPDATE department_worksheet_columns SET sort_order = ? WHERE id = ? AND department_id = ?");
        $idx = 1;
        foreach ($orderIds as $colId) {
            $upStmt->execute([$idx++, (int)$colId, $deptId]);
        }

        echo json_encode(['success' => true, 'message' => 'Columns reordered successfully.']);
        break;

    case 'delete':
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            if (!headers_sent()) http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Column ID is required.']);
            exit;
        }

        $stmtFind = $pdo->prepare("SELECT * FROM department_worksheet_columns WHERE id = ?");
        $stmtFind->execute([$id]);
        $col = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$col) {
            if (!headers_sent()) http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Column not found.']);
            exit;
        }

        $colDeptId = (int)$col['department_id'];
        if (!$isSuperAdmin && !($isHod && in_array($colDeptId, $managedDeptIds))) {
            if (!headers_sent()) http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }

        if ((int)$col['is_core'] === 1) {
            // Core columns can't be deleted, only hidden
            $pdo->prepare("UPDATE department_worksheet_columns SET is_visible = 0 WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Core column hidden.']);
        } else {
            $pdo->prepare("DELETE FROM department_worksheet_columns WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Column deleted successfully.']);
        }
        break;

    case 'reset_defaults':
        $deptId = isset($data['department_id']) ? (int)$data['department_id'] : $userDeptId;
        if ($deptId <= 0) $deptId = 2;

        if (!$isSuperAdmin && !($isHod && in_array($deptId, $managedDeptIds))) {
            if (!headers_sent()) http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }

        ensureDepartmentDefaultColumns($pdo, $deptId, true);
        echo json_encode(['success' => true, 'message' => 'Columns reset to standard defaults.']);
        break;

    default:
        if (!headers_sent()) http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
