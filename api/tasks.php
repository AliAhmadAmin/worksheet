<?php
/**
 * Task Assigner API: Admin Assignment & Employee Task Management
 */
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
$canAssign = $isSuperAdmin || $isHod || !empty($currentUserObj['can_assign_tasks']);
$canEdit = $isSuperAdmin || $isHod || !empty($currentUserObj['can_edit_tasks']);
$userDeptId = (int)($currentUserObj['department_id'] ?? 0);

switch ($action) {
    case 'get_tasks':
        $empFilter = $_GET['employee_id'] ?? null;
        $statusFilter = $_GET['status'] ?? null;

        if ($canAssign || $canEdit) {
            $sql = "
                SELECT t.*, 
                       COALESCE(e.name, 'Unassigned') as employee_name, 
                       COALESCE(e.designation, 'Staff') as employee_designation,
                       COALESCE(a.name, 'Admin') as assigner_name
                FROM tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                LEFT JOIN employees a ON t.assigned_by = a.id
                WHERE 1=1
            ";
            $params = [];

            if (!$isSuperAdmin && $userDeptId > 0) {
                $sql .= " AND (e.department_id = ? OR t.assigned_by = ?)";
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            }

            if ($empFilter) {
                $sql .= " AND t.assigned_to = ?";
                $params[] = $empFilter;
            }
            if ($statusFilter) {
                $sql .= " AND t.status = ?";
                $params[] = $statusFilter;
            }
            $sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, t.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
        } else {
            // Employee view: ONLY tasks assigned to this employee
            $sql = "
                SELECT t.*, 
                       COALESCE(e.name, 'Unassigned') as employee_name,
                       COALESCE(a.name, 'Admin') as assigner_name
                FROM tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                LEFT JOIN employees a ON t.assigned_by = a.id
                WHERE t.assigned_to = ?
            ";
            $params = [$currentUserId];
            if ($statusFilter) {
                $sql .= " AND t.status = ?";
                $params[] = $statusFilter;
            }
            $sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, t.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
        }

        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'create_task':
        if (!$canAssign) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to assign tasks.']);
            exit;
        }

        $assignedTo = (int)($data['assigned_to'] ?? 0);
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $rawLink = trim($data['link'] ?? '');
        // Automatically strip surrounding quotes (e.g. from Windows "Copy as path" -> "C:\path")
        $link = trim($rawLink, " \t\n\r\0\x0B\"'");
        $contentType = trim($data['content_type'] ?? 'General');
        $department = trim($data['department'] ?? 'Digital');
        $priority = $data['priority'] ?? 'medium';
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;

        if (!$assignedTo || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee and task title are required.']);
            exit;
        }

        if (!$isSuperAdmin) {
            $checkEmp = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $checkEmp->execute([$assignedTo]);
            $targetDeptId = (int)$checkEmp->fetchColumn();
            if ($userDeptId > 0 && $targetDeptId !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You can only assign tasks to employees within your own department.']);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO tasks (assigned_by, assigned_to, title, description, link, content_type, department, priority, status, due_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$currentUserId, $assignedTo, $title, $description, $link, $contentType, $department, $priority, $dueDate]);

        echo json_encode(['success' => true, 'message' => 'Task assigned successfully.', 'task_id' => $pdo->lastInsertId()]);
        break;

    case 'update_status':
        $taskId = (int)($data['task_id'] ?? 0);
        $newStatus = $data['status'] ?? 'pending';

        if (!in_array($newStatus, ['pending', 'in_progress', 'completed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }

        // Verify task owner if employee
        if (!$isSuperAdmin && !$canEdit) {
            $check = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
            $check->execute([$taskId, $currentUserId]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
                exit;
            }
        }

        $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
        $stmt->execute([$newStatus, $completedAt, $taskId]);

        echo json_encode(['success' => true, 'message' => 'Task status updated to ' . $newStatus]);
        break;

    case 'update_task':
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit tasks.']);
            exit;
        }

        $taskId = (int)($data['task_id'] ?? 0);
        $assignedTo = (int)($data['assigned_to'] ?? 0);
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $rawLink = trim($data['link'] ?? '');
        // Automatically strip surrounding quotes
        $link = trim($rawLink, " \t\n\r\0\x0B\"'");
        $contentType = trim($data['content_type'] ?? 'General');
        $department = trim($data['department'] ?? 'Digital');
        $priority = $data['priority'] ?? 'medium';
        $status = $data['status'] ?? 'pending';
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;

        if (!$taskId || !$assignedTo || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Task ID, assigned employee, and title are required.']);
            exit;
        }

        // Fetch current status to check completed_at
        $checkStmt = $pdo->prepare("SELECT status, completed_at FROM tasks WHERE id = ?");
        $checkStmt->execute([$taskId]);
        $currentTask = $checkStmt->fetch();

        if (!$currentTask) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Task not found.']);
            exit;
        }

        $completedAt = $currentTask['completed_at'];
        if ($status === 'completed' && !$completedAt) {
            $completedAt = date('Y-m-d H:i:s');
        } elseif ($status !== 'completed') {
            $completedAt = null;
        }

        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET assigned_to = ?, title = ?, description = ?, link = ?, content_type = ?, department = ?, priority = ?, status = ?, due_date = ?, completed_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$assignedTo, $title, $description, $link, $contentType, $department, $priority, $status, $dueDate, $completedAt, $taskId]);

        echo json_encode(['success' => true, 'message' => 'Task updated successfully.']);
        break;

    case 'delete_task':
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete tasks.']);
            exit;
        }

        $taskId = (int)($data['task_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);

        echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
