<?php
/**
 * Master Data & Employee Management API (CRUD + Password Updates)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$data = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
$isHod = ($currentUserRole === 'hod');
$isHr = ($currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr'));
$canManageEmp = $isSuperAdmin || $isHr || !empty($currentUserObj['can_manage_employees']);

switch ($action) {
    case 'list':
        $requestedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;

        $whereClauses = ["e.is_active = 1"];
        $params = [];

        if (!$isSuperAdmin && !$isHr) {
            // HOD and employees are restricted to their own department
            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            if ($userDeptId > 0) {
                $whereClauses[] = "e.department_id = ?";
                $params[] = $userDeptId;
            }
        } else if ($requestedDeptId) {
            $whereClauses[] = "e.department_id = ?";
            $params[] = $requestedDeptId;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $stmt = $pdo->prepare("
            SELECT e.*,
                   p.basic_salary, p.hourly_rate, p.expected_hours, p.shift_policy, p.joining_date,
                   p.annual_leave_quota, p.casual_leave_quota, p.sick_leave_quota,
                   d.name as department_name, t.name as team_name 
            FROM employees e 
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            LEFT JOIN departments d ON e.department_id = d.id 
            LEFT JOIN teams t ON e.team_id = t.id 
            WHERE {$whereSql}
            ORDER BY 
                CASE e.role 
                    WHEN 'super_admin' THEN 1 
                    WHEN 'admin' THEN 1 
                    WHEN 'hr' THEN 2 
                    WHEN 'hod' THEN 3 
                    WHEN 'team_lead' THEN 4 
                    WHEN 'coordinator' THEN 5 
                    ELSE 6 
                END, 
                t.id ASC, e.name ASC
        ");
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        // Strip password hashes
        foreach ($employees as &$emp) {
            unset($emp['password_hash']);
        }

        $departments = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
        $teams = $pdo->query("SELECT * FROM teams ORDER BY id ASC")->fetchAll();
        $contentTypes = $pdo->query("SELECT * FROM content_types ORDER BY id ASC")->fetchAll();

        echo json_encode([
            'success' => true,
            'employees' => $employees,
            'departments' => $departments,
            'teams' => $teams,
            'content_types' => $contentTypes
        ]);
        break;

    case 'update_permissions':
        // STRICT SECURITY: Only Super Admin can modify roles and delegate access permissions
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Only Super Admin can modify organizational roles and permissions.']);
            exit;
        }

        $targetId = (int)($data['id'] ?? 0);
        $role = trim($data['role'] ?? 'employee');
        $canAssignTasks = !empty($data['can_assign_tasks']) ? 1 : 0;
        $canEditTasks = !empty($data['can_edit_tasks']) ? 1 : 0;
        $canUnlockSheets = !empty($data['can_unlock_sheets']) ? 1 : 0;
        $canInspectSheets = !empty($data['can_inspect_sheets']) ? 1 : 0;
        $canViewReports = !empty($data['can_view_reports']) ? 1 : 0;
        $canViewAttendance = !empty($data['can_view_attendance']) ? 1 : 0;
        $canManageEmployees = !empty($data['can_manage_employees']) ? 1 : 0;

        if (!$targetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        // Prevent accidental self-demotion lockout of Super Admin
        if ($targetId === $currentUserId && $role !== 'super_admin' && $role !== 'admin') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You cannot remove Super Admin permissions from your own account.']);
            exit;
        }

        // If target is made Super Admin or HR, enable all capabilities
        if ($role === 'super_admin' || $role === 'admin' || $role === 'hr') {
            $canAssignTasks = 1;
            $canEditTasks = 1;
            $canUnlockSheets = 1;
            $canInspectSheets = 1;
            $canViewReports = 1;
            $canViewAttendance = 1;
            $canManageEmployees = 1;
        }

        $stmt = $pdo->prepare("
            UPDATE employees 
            SET role = ?, can_assign_tasks = ?, can_edit_tasks = ?, can_unlock_sheets = ?, 
                can_inspect_sheets = ?, can_view_reports = ?, can_view_attendance = ?, 
                can_manage_employees = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $role, $canAssignTasks, $canEditTasks, $canUnlockSheets,
            $canInspectSheets, $canViewReports, $canViewAttendance,
            $canManageEmployees, $targetId
        ]);

        // If updating self, refresh session role
        if ($targetId === $currentUserId) {
            $_SESSION['role'] = $role;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Role & permissions updated successfully!'
        ]);
        break;

    case 'create_employee':
        if (!$canManageEmp) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
            exit;
        }

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? 'DiscoverPakistan123');
        $role = $data['role'] ?? 'employee';
        $designation = trim($data['designation'] ?? 'Content Creator');
        $deptId = (int)($data['department_id'] ?? 2);
        $teamId = (int)($data['team_id'] ?? 2);
        $avatar = trim($data['avatar'] ?? '');

        $expectedHours = isset($data['expected_hours']) ? (float)$data['expected_hours'] : 8.0;
        $shiftPolicy = trim($data['shift_policy'] ?? ($expectedHours == 0.0 ? 'open_flexible' : 'standard_' . intval($expectedHours) . 'h'));
        $basicSalary = isset($data['basic_salary']) ? (float)$data['basic_salary'] : 0.0;
        $joiningDate = !empty($data['joining_date']) ? $data['joining_date'] : date('Y-m-d');
        $annualQuota = isset($data['annual_leave_quota']) ? (int)$data['annual_leave_quota'] : 14;
        $casualQuota = isset($data['casual_leave_quota']) ? (int)$data['casual_leave_quota'] : 10;
        $sickQuota = isset($data['sick_leave_quota']) ? (int)$data['sick_leave_quota'] : 8;

        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee name and email are required.']);
            exit;
        }

        // Handle Base64 avatar upload
        if (!empty($avatar) && preg_match('/^data:image\/(\w+);base64,/', $avatar, $type)) {
            $dataImg = substr($avatar, strpos($avatar, ',') + 1);
            $dataImg = base64_decode($dataImg);
            if ($dataImg !== false) {
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : strtolower($type[1]);
                $filename = 'avatar_' . uniqid() . '.' . $ext;
                file_put_contents($uploadDir . $filename, $dataImg);
                $avatar = 'uploads/avatars/' . $filename;
            }
        }

        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?)");
        $check->execute([$email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'An employee with this email address already exists.']);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO employees (name, email, password_hash, role, designation, department_id, team_id, avatar, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $email, $passwordHash, $role, $designation, $deptId, $teamId, $avatar]);
        $newEmpId = $pdo->lastInsertId();

        // Create initial HR profile
        $stmtProf = $pdo->prepare("
            INSERT INTO hr_employee_profiles (employee_id, basic_salary, expected_hours, shift_policy, joining_date, annual_leave_quota, casual_leave_quota, sick_leave_quota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtProf->execute([$newEmpId, $basicSalary, $expectedHours, $shiftPolicy, $joiningDate, $annualQuota, $casualQuota, $sickQuota]);

        echo json_encode(['success' => true, 'message' => "Employee {$name} added successfully with shift and salary settings!", 'id' => $newEmpId]);
        break;

    case 'update_profile':
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'You must be logged in to update your profile.']);
            exit;
        }

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $designation = trim($data['designation'] ?? '');
        $avatar = trim($data['avatar'] ?? '');
        $avatarAction = $data['avatar_action'] ?? 'keep'; // 'keep', 'update', 'remove'
        $password = trim($data['password'] ?? '');

        if (empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name and email address are required.']);
            exit;
        }

        // Validate email uniqueness against other accounts
        $check = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?) AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email address is already in use by another employee account.']);
            exit;
        }

        // Fetch current user details
        $stmtCurr = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmtCurr->execute([$userId]);
        $currentUserData = $stmtCurr->fetch();

        if (!$currentUserData) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee account not found.']);
            exit;
        }

        $finalAvatar = $currentUserData['avatar'];

        if ($avatarAction === 'remove') {
            $finalAvatar = null;
        } elseif (!empty($avatar) && preg_match('/^data:image\/(\w+);base64,/', $avatar, $type)) {
            $dataImg = substr($avatar, strpos($avatar, ',') + 1);
            $dataImg = base64_decode($dataImg);
            if ($dataImg !== false) {
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : strtolower($type[1]);
                $filename = 'avatar_' . $userId . '_' . uniqid() . '.' . $ext;
                file_put_contents($uploadDir . $filename, $dataImg);
                $finalAvatar = 'uploads/avatars/' . $filename;
            }
        }

        // Build update query
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET name = ?, email = ?, designation = ?, avatar = ?, password_hash = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $designation, $finalAvatar, $passwordHash, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET name = ?, email = ?, designation = ?, avatar = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $designation, $finalAvatar, $userId]);
        }

        // Update session variables
        $_SESSION['user_name'] = $name;
        $_SESSION['email'] = $email;

        // Return updated user object
        $stmtUpdated = $pdo->prepare("
            SELECT e.id, e.name, e.email, e.role, e.designation, e.department_id, e.team_id, e.avatar, e.is_active,
                   d.name as department_name, t.name as team_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            LEFT JOIN teams t ON e.team_id = t.id 
            WHERE e.id = ?
        ");
        $stmtUpdated->execute([$userId]);
        $updatedUser = $stmtUpdated->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Your profile has been updated successfully!',
            'user' => $updatedUser
        ]);
        break;

    case 'update_employee':
        if (!$canManageEmp) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'employee';
        $designation = trim($data['designation'] ?? 'Content Creator');
        $deptId = (int)($data['department_id'] ?? 2);
        $teamId = (int)($data['team_id'] ?? 2);
        $avatar = trim($data['avatar'] ?? '');

        $expectedHours = isset($data['expected_hours']) ? (float)$data['expected_hours'] : 8.0;
        $shiftPolicy = trim($data['shift_policy'] ?? ($expectedHours == 0.0 ? 'open_flexible' : 'standard_' . intval($expectedHours) . 'h'));
        $basicSalary = isset($data['basic_salary']) ? (float)$data['basic_salary'] : 0.0;
        $joiningDate = !empty($data['joining_date']) ? $data['joining_date'] : null;
        $annualQuota = isset($data['annual_leave_quota']) ? (int)$data['annual_leave_quota'] : 14;
        $casualQuota = isset($data['casual_leave_quota']) ? (int)$data['casual_leave_quota'] : 10;
        $sickQuota = isset($data['sick_leave_quota']) ? (int)$data['sick_leave_quota'] : 8;

        if (!$id || empty($name) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID, name, and email are required.']);
            exit;
        }

        // Handle Base64 avatar upload
        if (!empty($avatar) && preg_match('/^data:image\/(\w+);base64,/', $avatar, $type)) {
            $dataImg = substr($avatar, strpos($avatar, ',') + 1);
            $dataImg = base64_decode($dataImg);
            if ($dataImg !== false) {
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : strtolower($type[1]);
                $filename = 'avatar_' . uniqid() . '.' . $ext;
                file_put_contents($uploadDir . $filename, $dataImg);
                $avatar = 'uploads/avatars/' . $filename;
            }
        }

        // Check email uniqueness for other users
        $check = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?) AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is already in use by another employee.']);
            exit;
        }

        if (!empty($avatar)) {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ?, role = ?, designation = ?, department_id = ?, team_id = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $designation, $deptId, $teamId, $avatar, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ?, role = ?, designation = ?, department_id = ?, team_id = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $designation, $deptId, $teamId, $id]);
        }

        // Update / Insert into hr_employee_profiles
        $stmtProf = $pdo->prepare("
            INSERT INTO hr_employee_profiles (employee_id, basic_salary, expected_hours, shift_policy, joining_date, annual_leave_quota, casual_leave_quota, sick_leave_quota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                basic_salary = VALUES(basic_salary),
                expected_hours = VALUES(expected_hours),
                shift_policy = VALUES(shift_policy),
                joining_date = COALESCE(VALUES(joining_date), joining_date),
                annual_leave_quota = VALUES(annual_leave_quota),
                casual_leave_quota = VALUES(casual_leave_quota),
                sick_leave_quota = VALUES(sick_leave_quota)
        ");
        $stmtProf->execute([$id, $basicSalary, $expectedHours, $shiftPolicy, $joiningDate, $annualQuota, $casualQuota, $sickQuota]);

        // If updated user is current session user, update session name/role
        if (($_SESSION['user_id'] ?? 0) === $id) {
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;
        }

        echo json_encode(['success' => true, 'message' => "Employee {$name} details & shift settings updated successfully!"]);
        break;

    case 'update_password':
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin authorization required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        $newPassword = trim($data['password'] ?? '');

        if (!$id || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID and new password are required.']);
            exit;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE employees SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
        break;

    case 'delete_employee':
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin authorization required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        // Prevent admin from deleting themselves
        if (($_SESSION['user_id'] ?? 0) === $id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own admin account.']);
            exit;
        }

        // Soft delete or remove
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Employee removed successfully.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
