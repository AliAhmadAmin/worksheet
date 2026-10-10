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
$isHr = ($currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr') || (!empty($currentUserObj['can_manage_hr']) && $currentUserRole !== 'hod'));
$canManageEmp = $isSuperAdmin || $isHr || !empty($currentUserObj['can_manage_employees']);

$userDeptId = (int)($currentUserObj['department_id'] ?? 0);
$managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
if ($isHod) {
    $stmtManagedDepts = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
    $stmtManagedDepts->execute([$currentUserId]);
    $extraDepts = $stmtManagedDepts->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($extraDepts)) {
        $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extraDepts)));
    }
}

switch ($action) {
    case 'list':
        $requestedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
        $statusFilter = $_GET['status'] ?? null;
        $includeInactive = isset($_GET['include_inactive']) && ($_GET['include_inactive'] === '1' || $_GET['include_inactive'] === 'true');

        $whereClauses = [];
        $params = [];

        if (!$includeInactive && $statusFilter !== 'all') {
            if ($statusFilter === 'inactive') {
                $whereClauses[] = "e.is_active = 0";
            } else {
                $whereClauses[] = "e.is_active = 1";
            }
        }

        if ($isHod) {
            // HODs are strictly restricted to their own department (and any department they manage)
            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            $managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
            $stmtManagedDepts = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
            $stmtManagedDepts->execute([$currentUserId]);
            $extraDepts = $stmtManagedDepts->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($extraDepts)) {
                $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extraDepts)));
            }

            if (!empty($managedDeptIds)) {
                $placeholders = implode(',', array_fill(0, count($managedDeptIds), '?'));
                $whereClauses[] = "(e.department_id IN ($placeholders) OR e.id = ?)";
                foreach ($managedDeptIds as $dId) {
                    $params[] = $dId;
                }
                $params[] = $currentUserId;
            } else {
                $whereClauses[] = "e.id = ?";
                $params[] = $currentUserId;
            }
        } else if (!$isSuperAdmin && !$isHr) {
            // Regular employees are restricted to their own department
            $userDeptId = (int)($currentUserObj['department_id'] ?? 0);
            if ($userDeptId > 0) {
                $whereClauses[] = "(e.department_id = ? OR e.id = ?)";
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            }
        } else if ($requestedDeptId) {
            $whereClauses[] = "e.department_id = ?";
            $params[] = $requestedDeptId;
        }

        $whereSql = !empty($whereClauses) ? implode(' AND ', $whereClauses) : '1=1';

        $stmt = $pdo->prepare("
            SELECT e.*,
                   p.emp_code, p.father_husband_name, p.cnic_no, p.bank_name, p.bank_account_no, p.fixed_allowance,
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

        $deptWhere = "WHERE d.is_active = 1";
        $deptParams = [];
        if ($isHod) {
            if (!empty($managedDeptIds)) {
                $deptPlaceholders = implode(',', array_fill(0, count($managedDeptIds), '?'));
                $deptWhere .= " AND d.id IN ($deptPlaceholders)";
                $deptParams = $managedDeptIds;
            } else {
                $deptWhere .= " AND 1=0";
            }
        }

        $stmtDept = $pdo->prepare("
            SELECT d.*, 
                   h.name as hod_name, h.email as hod_email, h.avatar as hod_avatar,
                   (SELECT COUNT(*) FROM teams t WHERE t.department_id = d.id) as team_count,
                   (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.is_active = 1) as member_count,
                   (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND (e.team_id IS NULL OR e.team_id = 0) AND e.is_active = 1) as unassigned_count
            FROM departments d
            LEFT JOIN employees h ON d.hod_id = h.id
            {$deptWhere}
            ORDER BY d.id ASC
        ");
        $stmtDept->execute($deptParams);
        $departments = $stmtDept->fetchAll();

        $teamsWhere = "";
        $teamsParams = [];
        if ($isHod) {
            if (!empty($managedDeptIds)) {
                $teamPlaceholders = implode(',', array_fill(0, count($managedDeptIds), '?'));
                $teamsWhere = "WHERE t.department_id IN ($teamPlaceholders)";
                $teamsParams = $managedDeptIds;
            } else {
                $teamsWhere = "WHERE 1=0";
            }
        }

        $stmtTeams = $pdo->prepare("
            SELECT t.*,
                   d.name as department_name,
                   (SELECT COUNT(*) FROM employees e WHERE e.team_id = t.id AND e.is_active = 1) as member_count
            FROM teams t
            LEFT JOIN departments d ON t.department_id = d.id
            {$teamsWhere}
            ORDER BY t.department_id ASC, t.name ASC
        ");
        $stmtTeams->execute($teamsParams);
        $teams = $stmtTeams->fetchAll();

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
        $canLogin = !empty($data['can_login']) ? 1 : 0;
        $email = !empty(trim($data['email'] ?? '')) ? trim($data['email']) : null;
        $password = trim($data['password'] ?? 'DiscoverPakistan123');
        $role = $canLogin ? ($data['role'] ?? 'employee') : 'employee';
        $designation = trim($data['designation'] ?? '');
        $deptId = (!empty($data['department_id']) && (int)$data['department_id'] > 0) ? (int)$data['department_id'] : null;
        $teamId = (!empty($data['team_id']) && (int)$data['team_id'] > 0) ? (int)$data['team_id'] : null;
        $avatar = trim($data['avatar'] ?? '');

        if ($isHod) {
            // HOD can add staff to their own department, but role is strictly employee (Staff Member)
            $role = 'employee';
            if (empty($deptId) || !in_array($deptId, $managedDeptIds, true)) {
                $deptId = !empty($managedDeptIds) ? $managedDeptIds[0] : $userDeptId;
            }
        }

        $empCode = trim($data['emp_code'] ?? '');
        $phone = trim($data['phone'] ?? ($data['whatsapp_number'] ?? ''));
        $fatherHusbandName = trim($data['father_husband_name'] ?? '');
        $cnicNo = trim($data['cnic_no'] ?? '');
        $bankName = trim($data['bank_name'] ?? 'UBL');
        $bankAccountNo = trim($data['bank_account_no'] ?? '');
        $fixedAllowance = isset($data['fixed_allowance']) ? (float)$data['fixed_allowance'] : 0.0;

        $expectedHours = isset($data['expected_hours']) ? (float)$data['expected_hours'] : 8.0;
        $shiftPolicy = trim($data['shift_policy'] ?? ($expectedHours == 0.0 ? 'open_flexible' : 'standard_' . intval($expectedHours) . 'h'));
        $basicSalary = isset($data['basic_salary']) ? (float)$data['basic_salary'] : 0.0;
        $joiningDate = !empty($data['joining_date']) ? $data['joining_date'] : date('Y-m-d');
        $annualQuota = isset($data['annual_leave_quota']) ? (int)$data['annual_leave_quota'] : 14;
        $casualQuota = isset($data['casual_leave_quota']) ? (int)$data['casual_leave_quota'] : 10;
        $sickQuota = isset($data['sick_leave_quota']) ? (int)$data['sick_leave_quota'] : 8;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee name is required.']);
            exit;
        }

        if ($canLogin && empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Official email address is required when portal login access is enabled.']);
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
        if (!empty($email)) {
            $check = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?)");
            $check->execute([$email]);
            if ($check->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'An employee with this email address already exists.']);
                exit;
            }
        }

        $passwordHash = $canLogin ? password_hash($password, PASSWORD_DEFAULT) : null;
        $stmt = $pdo->prepare("INSERT INTO employees (name, email, password_hash, role, designation, department_id, team_id, avatar, can_login, is_active, phone, whatsapp_number, emp_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
        $stmt->execute([$name, $email, $passwordHash, $role, $designation, $deptId, $teamId, $avatar, $canLogin, $phone, $phone, $empCode]);
        $newEmpId = $pdo->lastInsertId();

        // Default employee code if empty
        if (empty($empCode)) {
            $empCode = 'DP-' . str_pad($newEmpId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE employees SET emp_code = ? WHERE id = ?")->execute([$empCode, $newEmpId]);
        }

        // Create initial HR profile
        $stmtProf = $pdo->prepare("
            INSERT INTO hr_employee_profiles (employee_id, emp_code, phone, whatsapp_number, father_husband_name, cnic_no, bank_name, bank_account_no, fixed_allowance, basic_salary, expected_hours, shift_policy, joining_date, annual_leave_quota, casual_leave_quota, sick_leave_quota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtProf->execute([$newEmpId, $empCode, $phone, $phone, $fatherHusbandName, $cnicNo, $bankName, $bankAccountNo, $fixedAllowance, $basicSalary, $expectedHours, $shiftPolicy, $joiningDate, $annualQuota, $casualQuota, $sickQuota]);

        $loginMsg = $canLogin ? "with portal login enabled" : "as profile/roster only (no login)";
        echo json_encode(['success' => true, 'message' => "Employee {$name} ({$empCode}) added successfully {$loginMsg}!", 'id' => $newEmpId]);
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
        $canLogin = !empty($data['can_login']) ? 1 : 0;
        $email = !empty(trim($data['email'] ?? '')) ? trim($data['email']) : null;
        $role = $canLogin ? ($data['role'] ?? 'employee') : 'employee';
        $designation = trim($data['designation'] ?? '');
        $deptId = (!empty($data['department_id']) && (int)$data['department_id'] > 0) ? (int)$data['department_id'] : null;
        $teamId = (!empty($data['team_id']) && (int)$data['team_id'] > 0) ? (int)$data['team_id'] : null;
        $avatar = trim($data['avatar'] ?? '');

        if ($isHod) {
            // HOD can only edit employees within their managed departments
            $stmtTarget = $pdo->prepare("SELECT department_id, role FROM employees WHERE id = ?");
            $stmtTarget->execute([$id]);
            $targetEmp = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$targetEmp || !in_array((int)$targetEmp['department_id'], $managedDeptIds, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied: You can only edit employees in your department.']);
                exit;
            }

            // HOD cannot reassign or change organizational role! Strictly preserve existing role
            $role = $targetEmp['role'] ?: 'employee';

            // HOD cannot transfer employee out of department
            $deptId = (int)$targetEmp['department_id'];
        }

        $empCode = trim($data['emp_code'] ?? '');
        $phone = trim($data['phone'] ?? ($data['whatsapp_number'] ?? ''));
        $fatherHusbandName = trim($data['father_husband_name'] ?? '');
        $cnicNo = trim($data['cnic_no'] ?? '');
        $bankName = trim($data['bank_name'] ?? 'UBL');
        $bankAccountNo = trim($data['bank_account_no'] ?? '');
        $fixedAllowance = isset($data['fixed_allowance']) ? (float)$data['fixed_allowance'] : 0.0;

        $expectedHours = isset($data['expected_hours']) ? (float)$data['expected_hours'] : 8.0;
        $shiftPolicy = trim($data['shift_policy'] ?? ($expectedHours == 0.0 ? 'open_flexible' : 'standard_' . intval($expectedHours) . 'h'));
        $basicSalary = isset($data['basic_salary']) ? (float)$data['basic_salary'] : 0.0;
        $joiningDate = !empty($data['joining_date']) ? $data['joining_date'] : null;
        $annualQuota = isset($data['annual_leave_quota']) ? (int)$data['annual_leave_quota'] : 14;
        $casualQuota = isset($data['casual_leave_quota']) ? (int)$data['casual_leave_quota'] : 10;
        $sickQuota = isset($data['sick_leave_quota']) ? (int)$data['sick_leave_quota'] : 8;

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID and name are required.']);
            exit;
        }

        if ($canLogin && empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Official email address is required when portal login access is enabled.']);
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
        if (!empty($email)) {
            $check = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?) AND id != ?");
            $check->execute([$email, $id]);
            if ($check->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'This email is already in use by another employee.']);
                exit;
            }
        }

        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        if (($_SESSION['user_id'] ?? 0) === $id && $isActive === 0) {
            $isActive = 1;
        }

        if ($avatar === '__REMOVE__') {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ?, role = ?, designation = ?, department_id = ?, team_id = ?, avatar = NULL, can_login = ?, is_active = ?, phone = ?, whatsapp_number = ?, emp_code = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $designation, $deptId, $teamId, $canLogin, $isActive, $phone, $phone, $empCode, $id]);
        } elseif (!empty($avatar)) {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ?, role = ?, designation = ?, department_id = ?, team_id = ?, avatar = ?, can_login = ?, is_active = ?, phone = ?, whatsapp_number = ?, emp_code = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $designation, $deptId, $teamId, $avatar, $canLogin, $isActive, $phone, $phone, $empCode, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ?, role = ?, designation = ?, department_id = ?, team_id = ?, can_login = ?, is_active = ?, phone = ?, whatsapp_number = ?, emp_code = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $designation, $deptId, $teamId, $canLogin, $isActive, $phone, $phone, $empCode, $id]);
        }

        // Update / Insert into hr_employee_profiles
        $stmtProf = $pdo->prepare("
            INSERT INTO hr_employee_profiles (employee_id, emp_code, phone, whatsapp_number, father_husband_name, cnic_no, bank_name, bank_account_no, fixed_allowance, basic_salary, expected_hours, shift_policy, joining_date, annual_leave_quota, casual_leave_quota, sick_leave_quota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                emp_code = VALUES(emp_code),
                phone = VALUES(phone),
                whatsapp_number = VALUES(whatsapp_number),
                father_husband_name = VALUES(father_husband_name),
                cnic_no = VALUES(cnic_no),
                bank_name = VALUES(bank_name),
                bank_account_no = VALUES(bank_account_no),
                fixed_allowance = VALUES(fixed_allowance),
                basic_salary = VALUES(basic_salary),
                expected_hours = VALUES(expected_hours),
                shift_policy = VALUES(shift_policy),
                joining_date = COALESCE(VALUES(joining_date), joining_date),
                annual_leave_quota = VALUES(annual_leave_quota),
                casual_leave_quota = VALUES(casual_leave_quota),
                sick_leave_quota = VALUES(sick_leave_quota)
        ");
        $stmtProf->execute([$id, $empCode, $phone, $phone, $fatherHusbandName, $cnicNo, $bankName, $bankAccountNo, $fixedAllowance, $basicSalary, $expectedHours, $shiftPolicy, $joiningDate, $annualQuota, $casualQuota, $sickQuota]);

        // If updated user is current session user, update session name/role
        if (($_SESSION['user_id'] ?? 0) === $id) {
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;
        }

        echo json_encode(['success' => true, 'message' => "Employee {$name} details & shift settings updated successfully!"]);
        break;

    case 'create_department':
        if (!$canManageEmp && !$isHr && !$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
            exit;
        }

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $hodId = (!empty($data['hod_id']) && (int)$data['hod_id'] > 0) ? (int)$data['hod_id'] : null;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department name is required.']);
            exit;
        }

        // Check duplicate name
        $stmtCheck = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = LOWER(?) AND is_active = 1");
        $stmtCheck->execute([$name]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "A department named '{$name}' already exists."]);
            exit;
        }

        $stmtInsert = $pdo->prepare("INSERT INTO departments (name, description, hod_id, is_active) VALUES (?, ?, ?, 1)");
        $stmtInsert->execute([$name, $description, $hodId]);
        $newDeptId = (int)$pdo->lastInsertId();

        if ($hodId) {
            $stmtUpdateHod = $pdo->prepare("UPDATE employees SET role = 'hod', department_id = ? WHERE id = ?");
            $stmtUpdateHod->execute([$newDeptId, $hodId]);
        }

        echo json_encode([
            'success' => true,
            'message' => "Department '{$name}' created successfully!",
            'id' => $newDeptId
        ]);
        break;

    case 'update_department':
        if (!$canManageEmp && !$isHr && !$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $hodId = (!empty($data['hod_id']) && (int)$data['hod_id'] > 0) ? (int)$data['hod_id'] : null;

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department ID and name are required.']);
            exit;
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = LOWER(?) AND id != ? AND is_active = 1");
        $stmtCheck->execute([$name, $id]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Another department is already using the name '{$name}'."]);
            exit;
        }

        $stmtUpdate = $pdo->prepare("UPDATE departments SET name = ?, description = ?, hod_id = ? WHERE id = ?");
        $stmtUpdate->execute([$name, $description, $hodId, $id]);

        if ($hodId) {
            $stmtUpdateHod = $pdo->prepare("UPDATE employees SET role = 'hod', department_id = ? WHERE id = ?");
            $stmtUpdateHod->execute([$id, $hodId]);
        }

        echo json_encode([
            'success' => true,
            'message' => "Department '{$name}' updated successfully!"
        ]);
        break;

    case 'delete_department':
        if (!$isSuperAdmin && !$isHr) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department ID is required.']);
            exit;
        }

        // Soft delete department
        $stmt = $pdo->prepare("UPDATE departments SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Department removed successfully.'
        ]);
        break;

    case 'create_team':
        $deptId = (int)($data['department_id'] ?? 0);
        $userDeptId = (int)($currentUserObj['department_id'] ?? 0);

        // HOD can create teams for their own department; Super Admin/HR can create for any department
        if (!$isSuperAdmin && !$isHr && !$canManageEmp) {
            if (!$isHod || $deptId !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: HODs can only create teams within their own department.']);
                exit;
            }
        }

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($name) || !$deptId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team name and department are required.']);
            exit;
        }

        // Check duplicate team name in this department
        $stmtCheck = $pdo->prepare("SELECT id FROM teams WHERE LOWER(name) = LOWER(?) AND department_id = ?");
        $stmtCheck->execute([$name, $deptId]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "A team named '{$name}' already exists in this department."]);
            exit;
        }

        $stmtInsert = $pdo->prepare("INSERT INTO teams (name, department_id, description) VALUES (?, ?, ?)");
        $stmtInsert->execute([$name, $deptId, $description]);
        $newTeamId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => "Team '{$name}' created successfully!",
            'id' => $newTeamId
        ]);
        break;

    case 'update_team':
        $teamId = (int)($data['id'] ?? 0);
        $deptId = (int)($data['department_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $userDeptId = (int)($currentUserObj['department_id'] ?? 0);

        if (!$teamId || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID and name are required.']);
            exit;
        }

        // Fetch existing team
        $stmtFetch = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
        $stmtFetch->execute([$teamId]);
        $existingTeam = $stmtFetch->fetch();
        if (!$existingTeam) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Team not found.']);
            exit;
        }

        if (!$isSuperAdmin && !$isHr && !$canManageEmp) {
            if (!$isHod || (int)$existingTeam['department_id'] !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: You can only edit teams in your assigned department.']);
                exit;
            }
        }

        $targetDeptId = $deptId > 0 ? $deptId : (int)$existingTeam['department_id'];

        $stmtUpdate = $pdo->prepare("UPDATE teams SET name = ?, department_id = ?, description = ? WHERE id = ?");
        $stmtUpdate->execute([$name, $targetDeptId, $description, $teamId]);

        echo json_encode([
            'success' => true,
            'message' => "Team '{$name}' updated successfully!"
        ]);
        break;

    case 'delete_team':
        $teamId = (int)($data['id'] ?? 0);
        $userDeptId = (int)($currentUserObj['department_id'] ?? 0);

        if (!$teamId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Team ID is required.']);
            exit;
        }

        $stmtFetch = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
        $stmtFetch->execute([$teamId]);
        $existingTeam = $stmtFetch->fetch();
        if (!$existingTeam) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Team not found.']);
            exit;
        }

        if (!$isSuperAdmin && !$isHr && !$canManageEmp) {
            if (!$isHod || (int)$existingTeam['department_id'] !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: You can only delete teams in your assigned department.']);
                exit;
            }
        }

        // Set team_id to NULL for all employees in this team
        $pdo->prepare("UPDATE employees SET team_id = NULL WHERE team_id = ?")->execute([$teamId]);

        // Delete team
        $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$teamId]);

        echo json_encode([
            'success' => true,
            'message' => "Team deleted successfully. Member assignments have been reset to unassigned."
        ]);
        break;

    case 'assign_team':
        $empId = (int)($data['employee_id'] ?? 0);
        $teamId = (!empty($data['team_id']) && (int)$data['team_id'] > 0) ? (int)$data['team_id'] : null;
        $userDeptId = (int)($currentUserObj['department_id'] ?? 0);

        if (!$empId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        // Fetch target employee
        $stmtTarget = $pdo->prepare("SELECT id, name, department_id, team_id FROM employees WHERE id = ?");
        $stmtTarget->execute([$empId]);
        $targetEmp = $stmtTarget->fetch();
        if (!$targetEmp) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found.']);
            exit;
        }

        // Check HOD department boundary
        if (!$isSuperAdmin && !$isHr && !$canManageEmp) {
            if (!$isHod || (int)$targetEmp['department_id'] !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: HOD can only assign team members within their own department.']);
                exit;
            }
        }

        // If assigning to a team, verify team exists and matches department
        if ($teamId) {
            $stmtTeam = $pdo->prepare("SELECT id, name, department_id FROM teams WHERE id = ?");
            $stmtTeam->execute([$teamId]);
            $targetTeam = $stmtTeam->fetch();
            if (!$targetTeam) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Team not found.']);
                exit;
            }
            // If HOD, team must belong to their dept
            if ($isHod && !$isSuperAdmin && !$isHr && (int)$targetTeam['department_id'] !== $userDeptId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Cannot assign employee to a team outside your department.']);
                exit;
            }
        }

        $stmtAssign = $pdo->prepare("UPDATE employees SET team_id = ? WHERE id = ?");
        $stmtAssign->execute([$teamId, $empId]);

        $teamLabel = $teamId ? "assigned to team successfully!" : "marked as Unassigned (General).";
        echo json_encode([
            'success' => true,
            'message' => "{$targetEmp['name']} {$teamLabel}"
        ]);
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

    case 'toggle_status':
    case 'deactivate_employee':
        if (!$canManageEmp && !$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        $newStatus = isset($data['is_active']) ? (int)$data['is_active'] : 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        // Prevent admin from deactivating themselves
        if (($_SESSION['user_id'] ?? 0) === $id && $newStatus === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        $msg = $newStatus === 1 ? 'Employee account reactivated successfully.' : 'Employee account deactivated successfully.';
        echo json_encode(['success' => true, 'message' => $msg]);
        break;

    case 'delete_employee':
    case 'delete_employee_permanent':
        if (!$canManageEmp && !$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Super Admin or HR authorization required.']);
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
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own logged-in account.']);
            exit;
        }

        // Fetch employee info first to verify
        $stmtCheck = $pdo->prepare("SELECT id, name, role FROM employees WHERE id = ?");
        $stmtCheck->execute([$id]);
        $empToDelete = $stmtCheck->fetch();

        if (!$empToDelete) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Employee not found or already deleted.']);
            exit;
        }

        if ($empToDelete['role'] === 'super_admin' && !$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only Super Admin can delete Super Admin accounts.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Disable foreign key checks for clean cascading deletion of duplicate/unwanted records
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); } catch (Exception $e) {}

            // 1. Clean up hierarchy references
            try { $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE hod_id = ?")->execute([$id]); } catch (Exception $e) {}

            // 2. Clean up task references
            try { $pdo->prepare("UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("UPDATE tasks SET created_by = NULL WHERE created_by = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("UPDATE tasks SET approved_by = NULL WHERE approved_by = ?")->execute([$id]); } catch (Exception $e) {}

            // 3. Clean up sheet entries and daily sheets
            try {
                $sheetStmt = $pdo->prepare("SELECT id FROM daily_sheets WHERE employee_id = ?");
                $sheetStmt->execute([$id]);
                $sIds = $sheetStmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($sIds)) {
                    $placeholders = implode(',', array_fill(0, count($sIds), '?'));
                    $pdo->prepare("DELETE FROM sheet_entries WHERE sheet_id IN ($placeholders)")->execute($sIds);
                }
                $pdo->prepare("DELETE FROM daily_sheets WHERE employee_id = ?")->execute([$id]);
            } catch (Exception $e) {}

            // 4. Clean up HR records
            try { $pdo->prepare("DELETE FROM hr_claims WHERE employee_id = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM hr_fines WHERE employee_id = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM hr_loans WHERE employee_id = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM hr_leaves WHERE employee_id = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM hr_payroll WHERE employee_id = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM hr_employee_profiles WHERE employee_id = ?")->execute([$id]); } catch (Exception $e) {}

            // 5. Clean up Dispatches
            try { $pdo->prepare("DELETE FROM programming_dispatches WHERE submitted_by = ?")->execute([$id]); } catch (Exception $e) {}
            try { $pdo->prepare("DELETE FROM newsroom_dispatches WHERE submitted_by = ?")->execute([$id]); } catch (Exception $e) {}

            // 6. Delete Employee
            $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);

            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $e) {}

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Employee "' . $empToDelete['name'] . '" deleted permanently.'
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $e2) {}
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete employee: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
