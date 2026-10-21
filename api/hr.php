<?php
/**
 * HR Management API
 * Handles Leave Applications, Approvals, Staff HR Profiles, and Automated Monthly Payroll
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
$stmtUserCheck = $pdo->prepare("
    SELECT e.*, d.name as department_name, t.name as team_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN teams t ON e.team_id = t.id
    WHERE e.id = ?
");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isHod = ($currentUserRole === 'hod' || (stripos($currentUserObj['designation'] ?? '', 'HOD') !== false && !in_array($currentUserRole, ['super_admin', 'admin'])));
$isAdmin = ($currentUserRole === 'admin' || $currentUserRole === 'super_admin' || $currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr'));
$canManageHr = ($isAdmin || (!empty($currentUserObj['can_manage_hr']) && !$isHod));
$userDeptId = (int)($currentUserObj['department_id'] ?? 0);

switch ($action) {
    case 'get_overview':
        $selectedMonth = $_GET['month'] ?? date('Y-m');
        $todayDate = date('Y-m-d');

        // Total Pending Leaves (Awaiting HOD or HR review)
        if ($canManageHr) {
            $stmtPending = $pdo->query("SELECT COUNT(*) FROM hr_leaves WHERE status IN ('pending', 'approved_by_hod')");
            $pendingLeavesCount = (int)$stmtPending->fetchColumn();
        } elseif ($isHod) {
            $stmtPending = $pdo->prepare("
                SELECT COUNT(*) 
                FROM hr_leaves l 
                JOIN employees e ON l.employee_id = e.id 
                WHERE l.status = 'pending' AND e.department_id = ? AND l.employee_id != ?
            ");
            $stmtPending->execute([$userDeptId, $currentUserId]);
            $pendingLeavesCount = (int)$stmtPending->fetchColumn();
        } else {
            $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM hr_leaves WHERE status IN ('pending', 'approved_by_hod') AND employee_id = ?");
            $stmtPending->execute([$currentUserId]);
            $pendingLeavesCount = (int)$stmtPending->fetchColumn();
        }

        // Approved Leaves this month
        $stmtApprovedMonth = $pdo->prepare("SELECT COUNT(*) FROM hr_leaves WHERE status = 'approved' AND (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)");
        $stmtApprovedMonth->execute([$selectedMonth, $selectedMonth]);
        $approvedThisMonthCount = (int)$stmtApprovedMonth->fetchColumn();

        // Active Staff Count
        $stmtActive = $pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1");
        $activeStaffCount = (int)$stmtActive->fetchColumn();

        // Today's Checked-In Count
        $stmtTodayPresent = $pdo->prepare("SELECT COUNT(*) FROM daily_sheets WHERE sheet_date = ? AND (check_in_time IS NOT NULL OR total_duty_seconds > 0)");
        $stmtTodayPresent->execute([$todayDate]);
        $todayPresentCount = (int)$stmtTodayPresent->fetchColumn();

        // Today's Staff On Leave
        $stmtOnLeave = $pdo->prepare("
            SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name
            FROM hr_leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE l.status = 'approved' AND ? BETWEEN l.start_date AND l.end_date
            ORDER BY e.name ASC
        ");
        $stmtOnLeave->execute([$todayDate]);
        $todayOnLeaveStaff = $stmtOnLeave->fetchAll();

        // Pending Leave Applications Queue (Top 6)
        if ($canManageHr) {
            $stmtPendingList = $pdo->query("
                SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name, h.name as hod_name
                FROM hr_leaves l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employees h ON l.hod_id = h.id
                WHERE l.status IN ('pending', 'approved_by_hod')
                ORDER BY CASE l.status WHEN 'approved_by_hod' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END, l.created_at ASC
                LIMIT 6
            ");
            $pendingLeavesList = $stmtPendingList->fetchAll();
        } elseif ($isHod) {
            $stmtPendingList = $pdo->prepare("
                SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name, h.name as hod_name
                FROM hr_leaves l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employees h ON l.hod_id = h.id
                WHERE l.status IN ('pending', 'approved_by_hod') AND e.department_id = ?
                ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved_by_hod' THEN 2 ELSE 3 END, l.created_at ASC
                LIMIT 6
            ");
            $stmtPendingList->execute([$userDeptId]);
            $pendingLeavesList = $stmtPendingList->fetchAll();
        } else {
            $stmtPendingList = $pdo->prepare("
                SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name, h.name as hod_name
                FROM hr_leaves l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employees h ON l.hod_id = h.id
                WHERE l.status IN ('pending', 'approved_by_hod') AND l.employee_id = ?
                ORDER BY l.created_at ASC
                LIMIT 6
            ");
            $stmtPendingList->execute([$currentUserId]);
            $pendingLeavesList = $stmtPendingList->fetchAll();
        }

        // Department Headcount Breakdown
        $stmtDeptBreakdown = $pdo->query("
            SELECT COALESCE(d.name, 'Others') as department_name, COUNT(e.id) as staff_count
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.is_active = 1
            GROUP BY d.name
            ORDER BY staff_count DESC
        ");
        $deptBreakdown = $stmtDeptBreakdown->fetchAll();

        // User's own leave balance
        $empId = $currentUserId;
        $stmtProfile = $pdo->prepare("SELECT * FROM hr_employee_profiles WHERE employee_id = ?");
        $stmtProfile->execute([$empId]);
        $profile = $stmtProfile->fetch();

        $annualQuota = $profile['annual_leave_quota'] ?? 14;
        $casualQuota = $profile['casual_leave_quota'] ?? 10;
        $sickQuota = $profile['sick_leave_quota'] ?? 8;

        // Count approved leaves taken this year
        $currentYear = date('Y');
        $stmtTaken = $pdo->prepare("
            SELECT leave_type, SUM(days_count) as total_days 
            FROM hr_leaves 
            WHERE employee_id = ? AND status = 'approved' AND YEAR(start_date) = ?
            GROUP BY leave_type
        ");
        $stmtTaken->execute([$empId, $currentYear]);
        $takenRows = $stmtTaken->fetchAll();
        $takenMap = ['annual' => 0, 'casual' => 0, 'sick' => 0, 'unpaid' => 0, 'other' => 0];
        foreach ($takenRows as $row) {
            $takenMap[$row['leave_type']] = (float)$row['total_days'];
        }

        // Pending Loan Requests
        if ($canManageHr) {
            $stmtPendingLoans = $pdo->query("SELECT COUNT(*) FROM hr_loans WHERE status IN ('pending', 'approved_by_hod')");
            $pendingLoansCount = (int)$stmtPendingLoans->fetchColumn();
        } elseif ($isHod) {
            $stmtPendingLoans = $pdo->prepare("
                SELECT COUNT(*) 
                FROM hr_loans l 
                JOIN employees e ON l.employee_id = e.id 
                WHERE l.status = 'pending' AND e.department_id = ? AND l.employee_id != ?
            ");
            $stmtPendingLoans->execute([$userDeptId, $currentUserId]);
            $pendingLoansCount = (int)$stmtPendingLoans->fetchColumn();
        } else {
            $stmtPendingLoans = $pdo->prepare("SELECT COUNT(*) FROM hr_loans WHERE status IN ('pending', 'approved_by_hod') AND employee_id = ?");
            $stmtPendingLoans->execute([$currentUserId]);
            $pendingLoansCount = (int)$stmtPendingLoans->fetchColumn();
        }

        // Active Approved Loans Total
        $stmtActiveLoans = $pdo->query("SELECT COUNT(*), COALESCE(SUM(amount - paid_amount), 0) as total_balance FROM hr_loans WHERE status = 'approved' AND paid_amount < amount");
        $activeLoanRow = $stmtActiveLoans->fetch() ?: ['COUNT(*)' => 0, 'total_balance' => 0];

        // Active Notices (Top 5)
        $stmtNotices = $pdo->query("
            SELECT n.*, e.name as posted_by_name, e.avatar as posted_by_avatar, d.name as target_department_name
            FROM hr_notices n
            JOIN employees e ON n.posted_by = e.id
            LEFT JOIN departments d ON n.target_department_id = d.id
            WHERE n.is_active = 1
            ORDER BY CASE n.priority WHEN 'urgent' THEN 1 WHEN 'holiday' THEN 2 ELSE 3 END, n.created_at DESC
            LIMIT 5
        ");
        $activeNotices = $stmtNotices->fetchAll();

        // Pending Loan Applications Queue (Top 6)
        if ($canManageHr) {
            $stmtPendingLoansList = $pdo->query("
                SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name, h.name as hod_name
                FROM hr_loans l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employees h ON l.hod_id = h.id
                WHERE l.status IN ('pending', 'approved_by_hod')
                ORDER BY CASE l.status WHEN 'approved_by_hod' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END, l.created_at ASC
                LIMIT 6
            ");
            $pendingLoansList = $stmtPendingLoansList->fetchAll();
        } elseif ($isHod) {
            $stmtPendingLoansList = $pdo->prepare("
                SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name, h.name as hod_name
                FROM hr_loans l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employees h ON l.hod_id = h.id
                WHERE l.status IN ('pending', 'approved_by_hod') AND e.department_id = ?
                ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved_by_hod' THEN 2 ELSE 3 END, l.created_at ASC
                LIMIT 6
            ");
            $stmtPendingLoansList->execute([$userDeptId]);
            $pendingLoansList = $stmtPendingLoansList->fetchAll();
        } else {
            $stmtPendingLoansList = $pdo->prepare("
                SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name, h.name as hod_name
                FROM hr_loans l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employees h ON l.hod_id = h.id
                WHERE l.status IN ('pending', 'approved_by_hod') AND l.employee_id = ?
                ORDER BY l.created_at ASC
                LIMIT 6
            ");
            $stmtPendingLoansList->execute([$currentUserId]);
            $pendingLoansList = $stmtPendingLoansList->fetchAll();
        }

        // Department Attendance Breakdown for Today
        $stmtDeptAtt = $pdo->prepare("
            SELECT 
                COALESCE(d.name, 'General') as department_name,
                COUNT(DISTINCT e.id) as total_staff,
                COUNT(DISTINCT ds.employee_id) as present_staff
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN daily_sheets ds ON e.id = ds.employee_id AND ds.sheet_date = ? AND (ds.check_in_time IS NOT NULL OR ds.total_duty_seconds > 0)
            WHERE e.is_active = 1
            GROUP BY d.name
            ORDER BY total_staff DESC
        ");
        $stmtDeptAtt->execute([$todayDate]);
        $deptAttendanceStats = $stmtDeptAtt->fetchAll();

        // Company-wide Approved Leaves taken this month
        $currentMonth = date('Y-m');
        $stmtCompanyLeavesMonth = $pdo->prepare("
            SELECT leave_type, SUM(days_count) as total_days
            FROM hr_leaves
            WHERE status = 'approved' AND (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)
            GROUP BY leave_type
        ");
        $stmtCompanyLeavesMonth->execute([$currentMonth, $currentMonth]);
        $companyMonthLeavesRows = $stmtCompanyLeavesMonth->fetchAll();
        $companyLeaveStats = ['annual' => 0, 'casual' => 0, 'sick' => 0, 'unpaid' => 0, 'other' => 0, 'total_days' => 0];
        foreach ($companyMonthLeavesRows as $row) {
            $companyLeaveStats[$row['leave_type']] = (float)$row['total_days'];
            $companyLeaveStats['total_days'] += (float)$row['total_days'];
        }

        echo json_encode([
            'success' => true,
            'overview' => [
                'pending_leaves' => $pendingLeavesCount,
                'approved_this_month' => $approvedThisMonthCount,
                'pending_loans' => $pendingLoansCount,
                'active_loans_count' => (int)$activeLoanRow['COUNT(*)'],
                'active_loans_balance' => (float)$activeLoanRow['total_balance'],
                'active_staff' => $activeStaffCount,
                'today_present_count' => $todayPresentCount,
                'today_on_leave_count' => count($todayOnLeaveStaff),
                'today_on_leave_staff' => $todayOnLeaveStaff,
                'pending_leaves_list' => $pendingLeavesList,
                'pending_loans_list' => $pendingLoansList,
                'active_notices' => $activeNotices,
                'department_breakdown' => $deptBreakdown,
                'department_attendance_stats' => $deptAttendanceStats,
                'company_leave_stats' => $companyLeaveStats,
                'current_month_name' => date('F Y'),
                'user_leave_balance' => [
                    'annual_total' => $annualQuota,
                    'annual_taken' => $takenMap['annual'],
                    'annual_remaining' => max(0, $annualQuota - $takenMap['annual']),
                    'casual_total' => $casualQuota,
                    'casual_taken' => $takenMap['casual'],
                    'casual_remaining' => max(0, $casualQuota - $takenMap['casual']),
                    'sick_total' => $sickQuota,
                    'sick_taken' => $takenMap['sick'],
                    'sick_remaining' => max(0, $sickQuota - $takenMap['sick']),
                    'unpaid_taken' => $takenMap['unpaid']
                ]
            ]
        ]);
        break;

    case 'get_leaves':
        $empFilter = $_GET['employee_id'] ?? null;
        $statusFilter = $_GET['status'] ?? null;
        $typeFilter = $_GET['leave_type'] ?? null;
        $dateFrom = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
        $dateTo = !empty($_GET['date_to']) ? $_GET['date_to'] : null;

        $sql = "
            SELECT l.*, 
                   e.name as employee_name, 
                   e.designation as employee_designation,
                   e.avatar as employee_avatar,
                   d.name as department_name,
                   d.id as department_id,
                   a.name as action_by_name,
                   h.name as hod_name
            FROM hr_leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees a ON l.action_by = a.id
            LEFT JOIN employees h ON l.hod_id = h.id
            WHERE 1=1
        ";
        $params = [];

        if ($canManageHr) {
            // HR can see all or filter by specific employee
            if ($empFilter) {
                $sql .= " AND l.employee_id = ?";
                $params[] = $empFilter;
            }
        } elseif ($isHod) {
            // HOD can see leaves from their department OR their own leaves
            if ($empFilter) {
                $sql .= " AND l.employee_id = ? AND (e.department_id = ? OR l.employee_id = ?)";
                $params[] = $empFilter;
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            } else {
                $sql .= " AND (e.department_id = ? OR l.employee_id = ?)";
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            }
        } else {
            // Regular employee can only see their own leaves
            $sql .= " AND l.employee_id = ?";
            $params[] = $currentUserId;
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $sql .= " AND l.status = ?";
            $params[] = $statusFilter;
        }

        if ($typeFilter && $typeFilter !== 'all') {
            $sql .= " AND l.leave_type = ?";
            $params[] = $typeFilter;
        }

        if ($dateFrom) {
            $sql .= " AND (l.end_date >= ? OR l.start_date >= ?)";
            $params[] = $dateFrom;
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND (l.start_date <= ? OR l.end_date <= ?)";
            $params[] = $dateTo;
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved_by_hod' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END, l.start_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leaves = $stmt->fetchAll();

        echo json_encode([
            'success' => true, 
            'leaves' => $leaves,
            'current_user' => [
                'id' => $currentUserId,
                'role' => $currentUserRole,
                'is_hod' => $isHod,
                'can_manage_hr' => $canManageHr,
                'department_id' => $userDeptId
            ]
        ]);
        break;

    case 'apply_leave':
        $empId = $canManageHr ? (int)($data['employee_id'] ?? $currentUserId) : $currentUserId;
        $leaveType = $data['leave_type'] ?? 'casual';
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? $startDate;
        $reason = trim($data['reason'] ?? '');

        if (!$startDate) {
            echo json_encode(['success' => false, 'message' => 'Start date is required.']);
            exit;
        }
        if (!$reason) {
            echo json_encode(['success' => false, 'message' => 'Reason for leave is required.']);
            exit;
        }

        // Calculate days
        $startDt = new DateTime($startDate);
        $endDt = new DateTime($endDate);
        if ($endDt < $startDt) {
            echo json_encode(['success' => false, 'message' => 'End date cannot be before start date.']);
            exit;
        }

        $interval = $startDt->diff($endDt);
        $daysCount = (float)($interval->days + 1);

        $initialStatus = ($canManageHr && isset($data['auto_approve']) && $data['auto_approve']) ? 'approved' : 'pending';
        $actionBy = ($initialStatus === 'approved') ? $currentUserId : null;
        $actionAt = ($initialStatus === 'approved') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO hr_leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status, action_by, action_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$empId, $leaveType, $startDate, $endDate, $daysCount, $reason, $initialStatus, $actionBy, $actionAt]);

        echo json_encode([
            'success' => true,
            'message' => ($initialStatus === 'approved') ? 'Leave recorded and approved successfully.' : 'Leave application submitted successfully. Forwarded to HOD for initial approval.'
        ]);
        break;

    case 'update_leave_status':
        $leaveId = (int)($data['leave_id'] ?? 0);
        $targetStatus = $data['status'] ?? '';
        $notes = trim($data['admin_notes'] ?? ($data['hod_notes'] ?? ''));

        if (!$leaveId) {
            echo json_encode(['success' => false, 'message' => 'Leave application ID is required.']);
            exit;
        }

        $stmtCheck = $pdo->prepare("
            SELECT l.*, e.department_id, e.name as employee_name 
            FROM hr_leaves l 
            JOIN employees e ON l.employee_id = e.id 
            WHERE l.id = ?
        ");
        $stmtCheck->execute([$leaveId]);
        $leave = $stmtCheck->fetch();

        if (!$leave) {
            echo json_encode(['success' => false, 'message' => 'Leave record not found.']);
            exit;
        }

        $isDeptHod = ($isHod && (int)$leave['department_id'] === $userDeptId);

        if (!$canManageHr && !$isDeptHod) {
            echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to approve/reject this leave.']);
            exit;
        }

        // HOD Approval Workflow
        if ($targetStatus === 'approved_by_hod') {
            if (!$isDeptHod && !$canManageHr) {
                echo json_encode(['success' => false, 'message' => 'Only Department HOD can endorse this initial approval.']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE hr_leaves 
                SET status = 'approved_by_hod', hod_id = ?, hod_action_at = NOW(), hod_notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $notes, $leaveId]);

            echo json_encode([
                'success' => true, 
                'message' => "Leave approved by HOD. Forwarded to HR for final approval.",
                'status' => 'approved_by_hod'
            ]);
            break;
        }

        // HR Final Approval
        if ($targetStatus === 'approved') {
            if (!$canManageHr) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only HR Department can grant final leave approval.']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE hr_leaves 
                SET status = 'approved', action_by = ?, action_at = NOW(), admin_notes = COALESCE(NULLIF(?, ''), admin_notes) 
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $notes, $leaveId]);

            echo json_encode([
                'success' => true, 
                'message' => "Leave application granted final approval by HR.",
                'status' => 'approved'
            ]);
            break;
        }

        // Rejection by HOD or HR
        if ($targetStatus === 'rejected') {
            if ($isDeptHod && !$canManageHr) {
                // HOD rejection
                $stmt = $pdo->prepare("
                    UPDATE hr_leaves 
                    SET status = 'rejected', hod_id = ?, hod_action_at = NOW(), hod_notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$currentUserId, $notes, $leaveId]);
                $msg = "Leave application rejected by HOD.";
            } else {
                // HR rejection
                $stmt = $pdo->prepare("
                    UPDATE hr_leaves 
                    SET status = 'rejected', action_by = ?, action_at = NOW(), admin_notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$currentUserId, $notes, $leaveId]);
                $msg = "Leave application rejected by HR.";
            }

            echo json_encode(['success' => true, 'message' => $msg, 'status' => 'rejected']);
            break;
        }

        // Fallback for resetting / cancelling
        if (in_array($targetStatus, ['pending', 'cancelled'])) {
            if (!$canManageHr && $leave['employee_id'] != $currentUserId) {
                echo json_encode(['success' => false, 'message' => 'Access denied.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE hr_leaves SET status = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$targetStatus, $notes, $leaveId]);
            echo json_encode(['success' => true, 'message' => "Leave marked as {$targetStatus}."]);
            break;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
        break;

    case 'delete_leave':
        $leaveId = (int)($data['leave_id'] ?? 0);
        
        $stmtCheck = $pdo->prepare("SELECT * FROM hr_leaves WHERE id = ?");
        $stmtCheck->execute([$leaveId]);
        $leave = $stmtCheck->fetch();

        if (!$leave) {
            echo json_encode(['success' => false, 'message' => 'Leave record not found.']);
            exit;
        }

        if (!$canManageHr && $leave['employee_id'] != $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }

        if (!$canManageHr && !in_array($leave['status'], ['pending', 'approved_by_hod'])) {
            echo json_encode(['success' => false, 'message' => 'Only pending leave applications can be cancelled.']);
            exit;
        }

        $stmtDel = $pdo->prepare("DELETE FROM hr_leaves WHERE id = ?");
        $stmtDel->execute([$leaveId]);

        echo json_encode(['success' => true, 'message' => 'Leave application removed successfully.']);
        break;

    case 'get_profiles':
        $empWhere = (!$canManageHr && $userDeptId > 0) ? "WHERE e.is_active = 1 AND e.department_id = {$userDeptId}" : "WHERE e.is_active = 1";
        $sql = "
            SELECT e.id, e.name, e.email, e.role, e.designation, e.avatar, e.is_active,
                   d.name as department_name, t.name as team_name,
                   p.phone, p.cnic, p.joining_date, p.employment_type, p.basic_salary, p.hourly_rate,
                   p.emergency_contact, p.emergency_phone, p.address,
                   p.annual_leave_quota, p.casual_leave_quota, p.sick_leave_quota, p.notes
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            {$empWhere}
            ORDER BY e.name ASC
        ";
        $stmt = $pdo->query($sql);
        $profiles = $stmt->fetchAll();

        echo json_encode(['success' => true, 'profiles' => $profiles]);
        break;

    case 'get_profile':
        $empId = (int)($_GET['employee_id'] ?? $currentUserId);
        if (!$canManageHr && $empId !== (int)$currentUserId) {
            $empId = $currentUserId;
        }

        $stmt = $pdo->prepare("
            SELECT e.id, e.name, e.email, e.role, e.designation, e.avatar, e.is_active,
                   d.name as department_name, t.name as team_name,
                   p.phone, p.cnic, p.joining_date, p.employment_type, p.basic_salary, p.hourly_rate,
                   p.emergency_contact, p.emergency_phone, p.address,
                   p.annual_leave_quota, p.casual_leave_quota, p.sick_leave_quota, p.notes
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            WHERE e.id = ?
        ");
        $stmt->execute([$empId]);
        $profile = $stmt->fetch();

        echo json_encode(['success' => true, 'profile' => $profile]);
        break;

    case 'save_profile':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        if (!$empId) {
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit;
        }

        $phone = trim($data['phone'] ?? '');
        $cnic = trim($data['cnic'] ?? '');
        $joiningDate = !empty($data['joining_date']) ? $data['joining_date'] : null;
        $employmentType = $data['employment_type'] ?? 'full_time';
        $basicSalary = (float)($data['basic_salary'] ?? 0.00);
        $hourlyRate = (float)($data['hourly_rate'] ?? 0.00);
        $emergencyContact = trim($data['emergency_contact'] ?? '');
        $emergencyPhone = trim($data['emergency_phone'] ?? '');
        $address = trim($data['address'] ?? '');
        $annualQuota = (int)($data['annual_leave_quota'] ?? 14);
        $casualQuota = (int)($data['casual_leave_quota'] ?? 10);
        $sickQuota = (int)($data['sick_leave_quota'] ?? 8);
        $notes = trim($data['notes'] ?? '');

        // Check if profile exists
        $stmtCheck = $pdo->prepare("SELECT id FROM hr_employee_profiles WHERE employee_id = ?");
        $stmtCheck->execute([$empId]);
        $exists = $stmtCheck->fetch();

        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE hr_employee_profiles 
                SET phone = ?, cnic = ?, joining_date = ?, employment_type = ?, basic_salary = ?, 
                    hourly_rate = ?, emergency_contact = ?, emergency_phone = ?, address = ?, 
                    annual_leave_quota = ?, casual_leave_quota = ?, sick_leave_quota = ?, notes = ?
                WHERE employee_id = ?
            ");
            $stmt->execute([
                $phone, $cnic, $joiningDate, $employmentType, $basicSalary, 
                $hourlyRate, $emergencyContact, $emergencyPhone, $address, 
                $annualQuota, $casualQuota, $sickQuota, $notes, $empId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO hr_employee_profiles 
                (employee_id, phone, cnic, joining_date, employment_type, basic_salary, 
                 hourly_rate, emergency_contact, emergency_phone, address, 
                 annual_leave_quota, casual_leave_quota, sick_leave_quota, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $empId, $phone, $cnic, $joiningDate, $employmentType, $basicSalary, 
                $hourlyRate, $emergencyContact, $emergencyPhone, $address, 
                $annualQuota, $casualQuota, $sickQuota, $notes
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'HR profile saved successfully.']);
        break;



    case 'get_loans':
        $empFilter = $_GET['employee_id'] ?? null;
        $statusFilter = $_GET['status'] ?? null;
        $typeFilter = $_GET['request_type'] ?? null;
        $dateFrom = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
        $dateTo = !empty($_GET['date_to']) ? $_GET['date_to'] : null;

        $sql = "
            SELECT l.*, 
                   CASE WHEN l.status = 'repaid' THEN 0.00 ELSE GREATEST(0.00, (l.amount - l.paid_amount)) END as remaining_amount,
                   e.name as employee_name, 
                   e.designation as employee_designation,
                   e.avatar as employee_avatar,
                   d.name as department_name,
                   d.id as department_id,
                   a.name as action_by_name,
                   h.name as hod_name
            FROM hr_loans l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees a ON l.action_by = a.id
            LEFT JOIN employees h ON l.hod_id = h.id
            WHERE 1=1
        ";
        $params = [];

        if ($canManageHr) {
            if ($empFilter) {
                $sql .= " AND l.employee_id = ?";
                $params[] = $empFilter;
            }
        } elseif ($isHod) {
            // HOD can see loans from their department members OR their own loans
            if ($empFilter) {
                $sql .= " AND l.employee_id = ? AND (e.department_id = ? OR l.employee_id = ?)";
                $params[] = $empFilter;
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            } else {
                $sql .= " AND (e.department_id = ? OR l.employee_id = ?)";
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            }
        } else {
            $sql .= " AND l.employee_id = ?";
            $params[] = $currentUserId;
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $sql .= " AND l.status = ?";
            $params[] = $statusFilter;
        }

        if ($typeFilter && $typeFilter !== 'all') {
            $sql .= " AND l.request_type = ?";
            $params[] = $typeFilter;
        }

        if ($dateFrom) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved_by_hod' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END, l.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $loans = $stmt->fetchAll();

        echo json_encode([
            'success' => true, 
            'loans' => $loans,
            'current_user' => [
                'id' => $currentUserId,
                'role' => $currentUserRole,
                'is_hod' => $isHod,
                'can_manage_hr' => $canManageHr,
                'department_id' => $userDeptId
            ]
        ]);
        break;

    case 'apply_loan':
        $empId = ($canManageHr && $currentUserRole !== 'hod') ? (int)($data['employee_id'] ?? $currentUserId) : $currentUserId;
        $requestType = $data['request_type'] ?? 'advance_salary';
        $amount = (float)($data['amount'] ?? 0);
        $repaymentMonths = max(1, (int)($data['repayment_months'] ?? 1));
        $deductionStartMonth = !empty($data['deduction_start_month']) ? $data['deduction_start_month'] : date('Y-m');
        $reason = trim($data['reason'] ?? '');

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Requested amount must be greater than zero.']);
            exit;
        }
        if (!$reason) {
            echo json_encode(['success' => false, 'message' => 'Please provide a reason or purpose for this request.']);
            exit;
        }

        $monthlyDeduction = round($amount / $repaymentMonths, 2);
        $isMainHrAdmin = ($canManageHr && $currentUserRole !== 'hod');
        $initialStatus = ($isMainHrAdmin && !empty($data['auto_approve'])) ? 'approved' : 'pending';
        $actionBy = ($initialStatus === 'approved') ? $currentUserId : null;
        $actionAt = ($initialStatus === 'approved') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO hr_loans 
                (employee_id, request_type, amount, repayment_months, monthly_deduction, deduction_start_month, paid_amount, reason, status, action_by, action_at)
            VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?)
        ");
        $stmt->execute([$empId, $requestType, $amount, $repaymentMonths, $monthlyDeduction, $deductionStartMonth, $reason, $initialStatus, $actionBy, $actionAt]);

        echo json_encode([
            'success' => true,
            'message' => ($initialStatus === 'approved') ? 'Loan/Advance recorded and approved successfully.' : 'Loan/Advance request submitted successfully. Forwarded to HOD for initial endorsement.'
        ]);
        break;

    case 'update_loan_status':
        $loanId = (int)($data['loan_id'] ?? 0);
        $status = $data['status'] ?? '';
        $notes = trim($data['admin_notes'] ?? ($data['hod_notes'] ?? ''));
        $paidAmount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : null;

        if (!$loanId) {
            echo json_encode(['success' => false, 'message' => 'Loan application ID is required.']);
            exit;
        }

        $stmtCheck = $pdo->prepare("
            SELECT l.*, e.department_id, e.name as employee_name 
            FROM hr_loans l 
            JOIN employees e ON l.employee_id = e.id 
            WHERE l.id = ?
        ");
        $stmtCheck->execute([$loanId]);
        $loan = $stmtCheck->fetch();

        if (!$loan) {
            echo json_encode(['success' => false, 'message' => 'Loan record not found.']);
            exit;
        }

        $isDeptHod = ($isHod && (int)$loan['department_id'] === $userDeptId);

        if (!$canManageHr && !$isDeptHod) {
            echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to approve/reject this loan request.']);
            exit;
        }

        // HOD Approval Workflow
        if ($status === 'approved_by_hod') {
            if (!$isDeptHod && !$canManageHr) {
                echo json_encode(['success' => false, 'message' => 'Only Department HOD can endorse this initial approval.']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = 'approved_by_hod', hod_id = ?, hod_action_at = NOW(), hod_notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $notes, $loanId]);

            echo json_encode([
                'success' => true, 
                'message' => "Advance/Loan request approved by HOD. Forwarded to HR for final approval.",
                'status' => 'approved_by_hod'
            ]);
            break;
        }

        // HOD Rejection Workflow
        if ($status === 'rejected' && $isDeptHod && !$canManageHr) {
            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = 'rejected', hod_id = ?, hod_action_at = NOW(), hod_notes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $notes, $loanId]);

            echo json_encode([
                'success' => true, 
                'message' => "Advance/Loan request rejected by HOD.",
                'status' => 'rejected'
            ]);
            break;
        }

        // HR Final Approval & Management
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: HR Manager / Admin permission required for final loan approval.']);
            exit;
        }

        if (!in_array($status, ['approved', 'rejected', 'repaid', 'pending', 'cancelled'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
            exit;
        }

        if ($status === 'repaid') {
            $newPaid = ($paidAmount !== null && $paidAmount > 0) ? $paidAmount : (float)$loan['amount'];
            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = 'repaid', paid_amount = ?, admin_notes = COALESCE(NULLIF(?, ''), admin_notes), action_by = ?, action_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newPaid, $notes, $currentUserId, $loanId]);
        } elseif ($paidAmount !== null) {
            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = ?, admin_notes = COALESCE(NULLIF(?, ''), admin_notes), paid_amount = ?, action_by = ?, action_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $paidAmount, $currentUserId, $loanId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = ?, admin_notes = COALESCE(NULLIF(?, ''), admin_notes), action_by = ?, action_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $currentUserId, $loanId]);
        }

        echo json_encode(['success' => true, 'message' => "Request marked as {$status}."]);
        break;

    case 'delete_loan':
        $loanId = (int)($data['loan_id'] ?? 0);
        $stmtCheck = $pdo->prepare("SELECT * FROM hr_loans WHERE id = ?");
        $stmtCheck->execute([$loanId]);
        $loan = $stmtCheck->fetch();

        if (!$loan) {
            echo json_encode(['success' => false, 'message' => 'Record not found.']);
            exit;
        }

        if (!$canManageHr && $loan['employee_id'] != $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }

        if (!$canManageHr && $loan['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Only pending requests can be cancelled.']);
            exit;
        }

        $stmtDel = $pdo->prepare("DELETE FROM hr_loans WHERE id = ?");
        $stmtDel->execute([$loanId]);

        echo json_encode(['success' => true, 'message' => 'Request removed successfully.']);
        break;

    case 'get_notices':
        $sql = "
            SELECT n.*, 
                   e.name as posted_by_name, 
                   e.avatar as posted_by_avatar, 
                   d.name as target_department_name
            FROM hr_notices n
            JOIN employees e ON n.posted_by = e.id
            LEFT JOIN departments d ON n.target_department_id = d.id
            WHERE n.is_active = 1
            ORDER BY CASE n.priority WHEN 'urgent' THEN 1 WHEN 'holiday' THEN 2 ELSE 3 END, n.created_at DESC
        ";
        $stmt = $pdo->query($sql);
        $notices = $stmt->fetchAll();

        echo json_encode(['success' => true, 'notices' => $notices]);
        break;

    case 'save_notice':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: HR Manager / Admin permission required.']);
            exit;
        }

        $title = trim($data['title'] ?? ($_POST['title'] ?? ''));
        $message = trim($data['message'] ?? ($_POST['message'] ?? ''));
        $priority = $data['priority'] ?? ($_POST['priority'] ?? 'normal');
        $targetDeptId = !empty($data['target_department_id'] ?? ($_POST['target_department_id'] ?? null)) ? (int)($data['target_department_id'] ?? $_POST['target_department_id']) : null;

        if (!$title || !$message) {
            echo json_encode(['success' => false, 'message' => 'Title and announcement message are required.']);
            exit;
        }

        $attachmentPath = null;
        $attachmentName = null;

        // 1. Multipart file upload ($_FILES)
        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $origName = basename($file['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'zip'];

            if (!in_array($ext, $allowedExts)) {
                echo json_encode(['success' => false, 'message' => "Invalid attachment format .$ext. Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, WEBP, ZIP."]);
                exit;
            }

            if ($file['size'] > 25 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => "Attachment is too large. Maximum size is 25MB."]);
                exit;
            }

            $uploadDir = __DIR__ . '/../uploads/notices/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $safeFilename = 'notice_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $safeFilename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $attachmentPath = 'uploads/notices/' . $safeFilename;
                $attachmentName = $origName;
            }
        }
        // 2. Base64 attachment payload fallback
        elseif (!empty($data['attachment_base64']) && !empty($data['attachment_name'])) {
            $base64Data = $data['attachment_base64'];
            $attachmentName = basename($data['attachment_name']);
            $ext = strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'zip'];

            if (in_array($ext, $allowedExts)) {
                if (strpos($base64Data, ',') !== false) {
                    $base64Data = explode(',', $base64Data)[1];
                }
                $decoded = base64_decode($base64Data);
                if ($decoded !== false) {
                    $uploadDir = __DIR__ . '/../uploads/notices/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $safeFilename = 'notice_' . uniqid() . '.' . $ext;
                    file_put_contents($uploadDir . $safeFilename, $decoded);
                    $attachmentPath = 'uploads/notices/' . $safeFilename;
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO hr_notices (title, message, attachment_path, attachment_name, priority, target_department_id, posted_by, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$title, $message, $attachmentPath, $attachmentName, $priority, $targetDeptId, $currentUserId]);

        echo json_encode(['success' => true, 'message' => 'Announcement posted successfully.']);
        break;

    case 'delete_notice':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: HR Manager / Admin permission required.']);
            exit;
        }

        $noticeId = (int)($data['notice_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE hr_notices SET is_active = 0 WHERE id = ?");
        $stmt->execute([$noticeId]);

        echo json_encode(['success' => true, 'message' => 'Notice archived successfully.']);
        break;

    case 'get_fines':
        $monthFilter = $_GET['month'] ?? '';
        $empFilter = $_GET['employee_id'] ?? null;
        $statusFilter = $_GET['status'] ?? 'all';
        $catFilter = $_GET['fine_category'] ?? 'all';

        $sql = "
            SELECT f.*, 
                   e.name as employee_name, 
                   e.designation as employee_designation,
                   e.avatar as employee_avatar,
                   d.name as department_name,
                   i.name as issued_by_name,
                   a.name as action_by_name,
                   sc.title as show_cause_title
            FROM hr_fines f
            JOIN employees e ON f.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees i ON f.issued_by = i.id
            LEFT JOIN employees a ON f.action_by = a.id
            LEFT JOIN hr_show_cause_notices sc ON f.show_cause_id = sc.id
            WHERE 1=1
        ";
        $params = [];

        if (!$canManageHr && !$isHod) {
            $sql .= " AND f.employee_id = ?";
            $params[] = $currentUserId;
        } elseif ($isHod && !$canManageHr) {
            if ($empFilter) {
                $sql .= " AND f.employee_id = ? AND (e.department_id = ? OR f.employee_id = ?)";
                $params[] = $empFilter;
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            } else {
                $sql .= " AND (e.department_id = ? OR f.employee_id = ?)";
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            }
        } elseif ($empFilter) {
            $sql .= " AND f.employee_id = ?";
            $params[] = $empFilter;
        }

        if (!empty($monthFilter)) {
            $sql .= " AND f.salary_month = ?";
            $params[] = $monthFilter;
        }

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $sql .= " AND f.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($catFilter) && $catFilter !== 'all') {
            $sql .= " AND f.fine_category = ?";
            $params[] = $catFilter;
        }

        $sql .= " ORDER BY f.fine_date DESC, f.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $fines = $stmt->fetchAll();

        // Calculate summary stats
        $totalApplied = 0.00;
        $totalWaived = 0.00;
        $appliedCount = 0;
        $waivedCount = 0;

        foreach ($fines as $f) {
            if ($f['status'] === 'applied') {
                $totalApplied += (float)$f['amount'];
                $appliedCount++;
            } elseif ($f['status'] === 'waived') {
                $totalWaived += (float)$f['amount'];
                $waivedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'fines' => $fines,
            'stats' => [
                'total_fines' => count($fines),
                'applied_count' => $appliedCount,
                'waived_count' => $waivedCount,
                'total_applied_amount' => $totalApplied,
                'total_waived_amount' => $totalWaived
            ]
        ]);
        break;

    case 'get_show_cause_notices':
        $monthFilter = $_GET['month'] ?? '';
        $empFilter = $_GET['employee_id'] ?? null;
        $statusFilter = $_GET['status'] ?? 'all';
        $catFilter = $_GET['violation_category'] ?? 'all';

        $sql = "
            SELECT sc.*,
                   e.name as employee_name,
                   e.designation as employee_designation,
                   e.avatar as employee_avatar,
                   d.name as department_name,
                   ib.name as issued_by_name,
                   ab.name as action_by_name,
                   f.amount as fine_amount,
                   f.salary_month as fine_salary_month,
                   f.status as fine_status
            FROM hr_show_cause_notices sc
            JOIN employees e ON sc.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees ib ON sc.issued_by = ib.id
            LEFT JOIN employees ab ON sc.action_by = ab.id
            LEFT JOIN hr_fines f ON sc.fine_id = f.id
            WHERE 1=1
        ";
        $params = [];

        if (!$canManageHr && !$isHod) {
            $sql .= " AND sc.employee_id = ?";
            $params[] = $currentUserId;
        } elseif ($isHod && !$canManageHr) {
            if ($empFilter) {
                $sql .= " AND sc.employee_id = ? AND (e.department_id = ? OR sc.employee_id = ?)";
                $params[] = $empFilter;
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            } else {
                $sql .= " AND (e.department_id = ? OR sc.employee_id = ?)";
                $params[] = $userDeptId;
                $params[] = $currentUserId;
            }
        } elseif ($empFilter) {
            $sql .= " AND sc.employee_id = ?";
            $params[] = $empFilter;
        }

        if (!empty($monthFilter)) {
            $sql .= " AND (DATE_FORMAT(sc.notice_date, '%Y-%m') = ? OR DATE_FORMAT(sc.incident_date, '%Y-%m') = ?)";
            $params[] = $monthFilter;
            $params[] = $monthFilter;
        }

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $sql .= " AND sc.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($catFilter) && $catFilter !== 'all') {
            $sql .= " AND sc.violation_category = ?";
            $params[] = $catFilter;
        }

        $sql .= " ORDER BY sc.notice_date DESC, sc.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pendingReplyCount = 0;
        $underReviewCount = 0;
        $satisfiedCount = 0;
        $fineIssuedCount = 0;

        foreach ($notices as $n) {
            if ($n['status'] === 'pending_reply') $pendingReplyCount++;
            elseif ($n['status'] === 'under_review') $underReviewCount++;
            elseif ($n['status'] === 'satisfied_closed') $satisfiedCount++;
            elseif ($n['status'] === 'fine_issued') $fineIssuedCount++;
        }

        echo json_encode([
            'success' => true,
            'notices' => $notices,
            'stats' => [
                'total_notices' => count($notices),
                'pending_reply_count' => $pendingReplyCount,
                'under_review_count' => $underReviewCount,
                'satisfied_count' => $satisfiedCount,
                'fine_issued_count' => $fineIssuedCount
            ]
        ]);
        break;

    case 'issue_show_cause':
        if (!$canManageHr && !$isHod) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Only Admins, HR and HODs can issue show cause notices.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        $incidentDate = !empty($data['incident_date']) ? $data['incident_date'] : date('Y-m-d');
        $noticeDate = !empty($data['notice_date']) ? $data['notice_date'] : date('Y-m-d');
        $deadlineDate = !empty($data['deadline_date']) ? $data['deadline_date'] : null;
        $violationCategory = $data['violation_category'] ?? 'sop_violation';
        $title = trim($data['title'] ?? '');
        $allegationDetails = trim($data['allegation_details'] ?? '');

        if ($empId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select an employee.']);
            exit;
        }
        if (!$title) {
            echo json_encode(['success' => false, 'message' => 'Please enter a notice subject / title.']);
            exit;
        }
        if (!$allegationDetails) {
            echo json_encode(['success' => false, 'message' => 'Please describe the allegation / incident details.']);
            exit;
        }

        // HOD validation: can only issue for departmental staff
        if ($isHod && !$canManageHr) {
            $stmtEmpCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtEmpCheck->execute([$empId]);
            $targetDeptId = (int)$stmtEmpCheck->fetchColumn();
            if ($targetDeptId !== $userDeptId) {
                echo json_encode(['success' => false, 'message' => 'HODs can only issue notices for staff in their own department.']);
                exit;
            }
        }

        $validCategories = ['late_arrival', 'unauthorized_absence', 'sop_violation', 'negligence', 'misconduct', 'other'];
        if (!in_array($violationCategory, $validCategories)) {
            $violationCategory = 'sop_violation';
        }

        $stmt = $pdo->prepare("
            INSERT INTO hr_show_cause_notices 
                (employee_id, issued_by, notice_date, incident_date, violation_category, title, allegation_details, deadline_date, status)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, 'pending_reply')
        ");
        $stmt->execute([$empId, $currentUserId, $noticeDate, $incidentDate, $violationCategory, $title, $allegationDetails, $deadlineDate]);

        echo json_encode(['success' => true, 'message' => 'Show Cause Notice issued to employee successfully.']);
        break;

    case 'submit_show_cause_reply':
        $noticeId = (int)($data['notice_id'] ?? ($_POST['notice_id'] ?? 0));
        $replyText = trim($data['employee_reply'] ?? ($_POST['employee_reply'] ?? ''));

        if ($noticeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notice ID.']);
            exit;
        }
        if (!$replyText) {
            echo json_encode(['success' => false, 'message' => 'Please provide your explanation / response.']);
            exit;
        }

        $stmtCheck = $pdo->prepare("SELECT * FROM hr_show_cause_notices WHERE id = ?");
        $stmtCheck->execute([$noticeId]);
        $notice = $stmtCheck->fetch();

        if (!$notice) {
            echo json_encode(['success' => false, 'message' => 'Show Cause Notice not found.']);
            exit;
        }

        // Only the assigned employee or HR/Admin can submit reply
        if ($notice['employee_id'] != $currentUserId && !$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only reply to your own show cause notice.']);
            exit;
        }

        $attachmentPath = null;
        $attachmentName = null;

        // Check file upload from $_FILES
        if (!empty($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['reply_attachment'];
            $origName = basename($file['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'txt'];

            if (!in_array($ext, $allowedExts)) {
                echo json_encode(['success' => false, 'message' => "Invalid attachment format .$ext. Allowed: PDF, DOC, DOCX, XLS, JPG, PNG, WEBP."]);
                exit;
            }

            if ($file['size'] > 20 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Attachment exceeds 20MB limit.']);
                exit;
            }

            $uploadDir = __DIR__ . '/../uploads/showcause/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $safeFilename = 'reply_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $safeFilename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $attachmentPath = 'uploads/showcause/' . $safeFilename;
                $attachmentName = $origName;
            }
        } elseif (!empty($data['reply_attachment_base64']) && !empty($data['reply_attachment_name'])) {
            $base64Data = $data['reply_attachment_base64'];
            $attachmentName = basename($data['reply_attachment_name']);
            $ext = strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'txt'];

            if (in_array($ext, $allowedExts)) {
                if (strpos($base64Data, ',') !== false) {
                    $base64Data = explode(',', $base64Data)[1];
                }
                $decoded = base64_decode($base64Data);
                if ($decoded !== false) {
                    $uploadDir = __DIR__ . '/../uploads/showcause/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $safeFilename = 'reply_' . uniqid() . '.' . $ext;
                    if (file_put_contents($uploadDir . $safeFilename, $decoded)) {
                        $attachmentPath = 'uploads/showcause/' . $safeFilename;
                    }
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE hr_show_cause_notices 
            SET employee_reply = ?,
                reply_submitted_at = NOW(),
                reply_attachment_path = COALESCE(?, reply_attachment_path),
                reply_attachment_name = COALESCE(?, reply_attachment_name),
                status = 'under_review'
            WHERE id = ?
        ");
        $stmt->execute([$replyText, $attachmentPath, $attachmentName, $noticeId]);

        echo json_encode(['success' => true, 'message' => 'Response submitted successfully. Awaiting HR review.']);
        break;

    case 'decide_show_cause':
        if (!$canManageHr && !$isHod) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Only Admins, HR and HODs can decide show cause inquiries.']);
            exit;
        }

        $noticeId = (int)($data['notice_id'] ?? 0);
        $decision = $data['decision'] ?? ''; // 'satisfied' or 'fine_issued'
        $remarks = trim($data['decision_remarks'] ?? '');

        if ($noticeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notice ID.']);
            exit;
        }

        $stmtCheck = $pdo->prepare("SELECT * FROM hr_show_cause_notices WHERE id = ?");
        $stmtCheck->execute([$noticeId]);
        $notice = $stmtCheck->fetch();

        if (!$notice) {
            echo json_encode(['success' => false, 'message' => 'Show Cause Notice not found.']);
            exit;
        }

        if ($decision === 'satisfied') {
            // Satisfied with explanation -> close without penalty
            $stmt = $pdo->prepare("
                UPDATE hr_show_cause_notices 
                SET status = 'satisfied_closed',
                    decision_remarks = ?,
                    action_by = ?,
                    action_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$remarks ?: 'Explanation accepted. Case closed with no disciplinary deduction.', $currentUserId, $noticeId]);

            echo json_encode(['success' => true, 'message' => 'Explanation accepted. Show Cause Notice closed successfully.']);
            break;
        } elseif ($decision === 'fine_issued') {
            // Not satisfied -> issue fine
            $amount = (float)($data['fine_amount'] ?? 0);
            $salaryMonth = !empty($data['salary_month']) ? $data['salary_month'] : date('Y-m');
            $fineCategory = !empty($data['fine_category']) ? $data['fine_category'] : $notice['violation_category'];

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid fine amount to impose.']);
                exit;
            }

            $fineReason = 'Show Cause Notice #' . $noticeId . ' (' . $notice['title'] . '): ' . ($remarks ?: 'Explanation not satisfactory.');

            // Insert into hr_fines
            $stmtFine = $pdo->prepare("
                INSERT INTO hr_fines 
                    (employee_id, fine_date, amount, fine_category, reason, salary_month, status, issued_by, show_cause_id)
                VALUES 
                    (?, CURDATE(), ?, ?, ?, ?, 'applied', ?, ?)
            ");
            $stmtFine->execute([
                $notice['employee_id'],
                $amount,
                $fineCategory,
                $fineReason,
                $salaryMonth,
                $currentUserId,
                $noticeId
            ]);
            $newFineId = (int)$pdo->lastInsertId();

            // Update show cause notice
            $stmt = $pdo->prepare("
                UPDATE hr_show_cause_notices 
                SET status = 'fine_issued',
                    fine_id = ?,
                    decision_remarks = ?,
                    action_by = ?,
                    action_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newFineId, $remarks ?: 'Explanation rejected; fine penalty imposed.', $currentUserId, $noticeId]);

            echo json_encode(['success' => true, 'message' => 'Disciplinary fine issued and applied to payroll successfully.']);
            break;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid decision option.']);
            exit;
        }
        break;

    case 'delete_show_cause':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $noticeId = (int)($data['notice_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM hr_show_cause_notices WHERE id = ?");
        $stmt->execute([$noticeId]);

        echo json_encode(['success' => true, 'message' => 'Show cause notice deleted successfully.']);
        break;

    case 'add_fine':
        if (!$canManageHr && !$isHod) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Only Admins, HR and HODs can issue fines.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        $fineDate = !empty($data['fine_date']) ? $data['fine_date'] : date('Y-m-d');
        $salaryMonth = !empty($data['salary_month']) ? $data['salary_month'] : date('Y-m', strtotime($fineDate));
        $fineCategory = $data['fine_category'] ?? 'sop_violation';
        $reason = trim($data['reason'] ?? '');
        $showCauseId = !empty($data['show_cause_id']) ? (int)$data['show_cause_id'] : null;

        if ($empId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select an employee.']);
            exit;
        }
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Fine amount must be greater than zero.']);
            exit;
        }
        if (!$reason) {
            echo json_encode(['success' => false, 'message' => 'Please describe the reason/incident for this penalty.']);
            exit;
        }

        // HOD validation: can only fine departmental staff
        if ($isHod && !$canManageHr) {
            $stmtEmpCheck = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtEmpCheck->execute([$empId]);
            $targetDeptId = (int)$stmtEmpCheck->fetchColumn();
            if ($targetDeptId !== $userDeptId) {
                echo json_encode(['success' => false, 'message' => 'HODs can only issue disciplinary fines for staff in their own department.']);
                exit;
            }
        }

        $validCategories = ['late_arrival', 'unauthorized_absence', 'sop_violation', 'negligence', 'misconduct', 'other'];
        if (!in_array($fineCategory, $validCategories)) {
            $fineCategory = 'sop_violation';
        }

        $stmt = $pdo->prepare("
            INSERT INTO hr_fines 
                (employee_id, fine_date, amount, fine_category, reason, salary_month, status, issued_by, show_cause_id)
            VALUES 
                (?, ?, ?, ?, ?, ?, 'applied', ?, ?)
        ");
        $stmt->execute([$empId, $fineDate, $amount, $fineCategory, $reason, $salaryMonth, $currentUserId, $showCauseId]);
        $newFineId = (int)$pdo->lastInsertId();

        if ($showCauseId) {
            try {
                $stmtScUpdate = $pdo->prepare("
                    UPDATE hr_show_cause_notices 
                    SET status = 'fine_issued', fine_id = ?, decision_remarks = ?, action_by = ?, action_at = NOW()
                    WHERE id = ?
                ");
                $stmtScUpdate->execute([$newFineId, $reason, $currentUserId, $showCauseId]);
            } catch (Exception $e) {}
        }

        echo json_encode(['success' => true, 'message' => 'Disciplinary fine issued successfully.']);
        break;

    case 'update_fine_status':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $fineId = (int)($data['fine_id'] ?? 0);
        $status = $data['status'] ?? 'applied';
        $waivedReason = trim($data['waived_reason'] ?? '');

        if (!in_array($status, ['applied', 'waived'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid fine status.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE hr_fines 
            SET status = ?, waived_reason = ?, action_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $waivedReason, $currentUserId, $fineId]);

        $actionWord = ($status === 'waived') ? 'waived (forgiven)' : 're-applied';
        echo json_encode(['success' => true, 'message' => "Fine successfully {$actionWord}."]);
        break;

    case 'delete_fine':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $fineId = (int)($data['fine_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM hr_fines WHERE id = ?");
        $stmt->execute([$fineId]);

        echo json_encode(['success' => true, 'message' => 'Fine record removed successfully.']);
        break;

    case 'get_payroll':
        $month = $_GET['month'] ?? date('Y-m');
        $firstDay = $month . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay));

        $whereClauses = ["e.is_active = 1"];
        $params = [
            'month' => $month,
            'advMonth' => $month,
            'loanMonth' => $month,
            'firstDay1' => $firstDay,
            'lastDay1' => $lastDay,
            'firstDay2' => $firstDay,
            'lastDay2' => $lastDay,
            'fineMonth' => $month,
            'claimsMonth' => $month
        ];

        if (!$canManageHr && !$isHod) {
            $whereClauses[] = "e.id = :curUser";
            $params['curUser'] = $currentUserId;
        } elseif ($isHod && !$canManageHr && $userDeptId > 0) {
            $whereClauses[] = "e.department_id = :deptId";
            $params['deptId'] = $userDeptId;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "
            SELECT 
                e.id as employee_id,
                e.name as employee_name,
                e.department_id,
                e.designation,
                e.avatar,
                d.name as department_name,
                COALESCE(p.emp_code, CONCAT('DP-', LPAD(e.id, 3, '0'))) as emp_code,
                p.father_husband_name,
                p.cnic_no,
                p.bank_name,
                p.bank_account_no,
                p.fixed_allowance,
                p.joining_date,
                p.basic_salary as profile_salary,
                COALESCE(pay.basic_salary, p.basic_salary, 0.00) as basic_salary,
                COALESCE(pay.fuel_allowance, clm.fuel_claims, p.fixed_allowance, 0.00) as fuel_allowance,
                COALESCE(pay.incentive, clm.incentive_claims, 0.00) as incentive,
                COALESCE(pay.food_bills, clm.food_claims, 0.00) as food_bills,
                COALESCE(pay.bonus, clm.bonus_claims, 0.00) as bonus,
                pay.bonus_reason,
                COALESCE(pay.advance_salary, adv_ded.advance_salary, 0.00) as advance_salary,
                COALESCE(pay.loan_deduction, l_ded.loan_deduction, 0.00) as loan_deduction,
                COALESCE(pay.fines, f_ded.fines, 0.00) as fines,
                COALESCE(pay.fine_reason, f_ded.fine_reason) as fine_reason,
                COALESCE(pay.wht_amount, 0.00) as wht_amount,
                COALESCE(pay.deductions, 0.00) as deductions,
                pay.deduction_reason,
                COALESCE(pay.paid_amount, 0.00) as paid_amount,
                COALESCE(pay.payable_amount, 0.00) as payable_amount,
                pay.form_no,
                pay.increment_remarks,
                COALESCE(pay.payment_status, 'draft') as payment_status,
                pay.payment_method,
                pay.payment_date,
                COALESCE(pay.working_days, 30) as working_days,
                COALESCE(att.present_days, pay.present_days, 0) as present_days,
                COALESCE(lv.paid_leaves, pay.approved_leaves, 0) as approved_leaves,
                COALESCE(lv.unpaid_leaves, pay.unpaid_leaves, 0) as unpaid_leaves,
                COALESCE(lv.total_leaves, (COALESCE(lv.paid_leaves, pay.approved_leaves, 0) + COALESCE(lv.unpaid_leaves, pay.unpaid_leaves, 0))) as total_leaves,
                pay.unpaid_leave_deduction,
                COALESCE(pay.total_duty_hours, ROUND(att.total_duty_hours, 1), 0.0) as total_duty_hours
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            LEFT JOIN hr_payroll pay ON e.id = pay.employee_id AND pay.salary_month = :month
            LEFT JOIN (
                SELECT 
                    employee_id,
                    SUM(CASE WHEN claim_type IN ('fuel', 'travel', 'mobile') THEN amount ELSE 0 END) as fuel_claims,
                    SUM(CASE WHEN claim_type = 'incentive' THEN amount ELSE 0 END) as incentive_claims,
                    SUM(CASE WHEN claim_type = 'food_bills' THEN amount ELSE 0 END) as food_claims,
                    SUM(CASE WHEN claim_type = 'bonus' THEN amount ELSE 0 END) as bonus_claims
                FROM hr_claims
                WHERE status IN ('approved', 'paid') AND salary_month = :claimsMonth
                GROUP BY employee_id
            ) clm ON e.id = clm.employee_id
            LEFT JOIN (
                SELECT employee_id, COUNT(DISTINCT sheet_date) as present_days, SUM(COALESCE(total_duty_seconds, 0) / 3600.0) as total_duty_hours
                FROM daily_sheets
                WHERE sheet_date >= :firstDay1 AND sheet_date <= :lastDay1 AND (check_in_time IS NOT NULL OR total_duty_seconds > 0)
                GROUP BY employee_id
            ) att ON e.id = att.employee_id
            LEFT JOIN (
                SELECT 
                    employee_id,
                    SUM(CASE WHEN leave_type IN ('annual', 'casual', 'sick') THEN days_count ELSE 0 END) as paid_leaves,
                    SUM(CASE WHEN leave_type = 'unpaid' THEN days_count ELSE 0 END) as unpaid_leaves,
                    SUM(days_count) as total_leaves
                FROM hr_leaves
                WHERE status = 'approved' AND start_date <= :lastDay2 AND end_date >= :firstDay2
                GROUP BY employee_id
            ) lv ON e.id = lv.employee_id
            LEFT JOIN (
                SELECT employee_id, SUM(monthly_deduction) as advance_salary
                FROM hr_loans
                WHERE status IN ('approved', 'active') 
                  AND request_type = 'advance_salary'
                  AND deduction_start_month <= :advMonth
                GROUP BY employee_id
            ) adv_ded ON e.id = adv_ded.employee_id
            LEFT JOIN (
                SELECT employee_id, SUM(monthly_deduction) as loan_deduction
                FROM hr_loans
                WHERE status IN ('approved', 'active') 
                  AND request_type != 'advance_salary'
                  AND deduction_start_month <= :loanMonth
                GROUP BY employee_id
            ) l_ded ON e.id = l_ded.employee_id
            LEFT JOIN (
                SELECT employee_id, SUM(amount) as fines, GROUP_CONCAT(reason SEPARATOR '; ') as fine_reason
                FROM hr_fines
                WHERE status = 'applied' AND salary_month = :fineMonth
                GROUP BY employee_id
            ) f_ded ON e.id = f_ded.employee_id
            WHERE {$whereSql}
            ORDER BY e.name ASC
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $payroll = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($payroll as &$p) {
                $basic = (float)$p['basic_salary'];
                $fuel = (float)$p['fuel_allowance'];
                $incentive = (float)$p['incentive'];
                $foodBills = (float)$p['food_bills'];
                $bonus = (float)$p['bonus'];
                $grossAdditions = $fuel + $incentive + $bonus;

                $advance = (float)$p['advance_salary'];
                $loan = (float)$p['loan_deduction'];
                $fines = (float)$p['fines'];
                $wht = (float)$p['wht_amount'];
                $deductions = (float)$p['deductions'];

                // Unpaid leave deduction computation
                $unpaidLeavesCount = (float)($p['unpaid_leaves'] ?? 0);
                if ($p['unpaid_leave_deduction'] !== null && $p['unpaid_leave_deduction'] !== '') {
                    $unpaidLeaveDeduction = (float)$p['unpaid_leave_deduction'];
                } else if ($unpaidLeavesCount > 0 && $basic > 0) {
                    $dailyRate = $basic / 30.0;
                    $unpaidLeaveDeduction = round($unpaidLeavesCount * $dailyRate, 2);
                } else {
                    $unpaidLeaveDeduction = 0.00;
                }
                $p['unpaid_leave_deduction'] = number_format($unpaidLeaveDeduction, 2, '.', '');

                $grossDeductions = $advance + $loan + $foodBills + $fines + $wht + $deductions + $unpaidLeaveDeduction;

                $netSalary = max(0, $basic + $grossAdditions - $grossDeductions);
                $p['net_salary'] = number_format($netSalary, 2, '.', '');
                
                $paidAmount = (float)$p['paid_amount'];
                $p['payable_amount'] = number_format(max(0, $netSalary - $paidAmount), 2, '.', '');
                $p['salary_month'] = $month;
            }

            echo json_encode(['success' => true, 'payroll' => $payroll, 'month' => $month]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error loading payroll: ' . $e->getMessage()]);
        }
        break;

    case 'save_payroll_item':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: HR/Admin permission required.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        $salaryMonth = $data['salary_month'] ?? date('Y-m');
        $workingDays = (int)($data['working_days'] ?? 30);
        $presentDays = (int)($data['present_days'] ?? 0);
        $approvedLeaves = (int)($data['approved_leaves'] ?? 0);
        $unpaidLeaves = (int)($data['unpaid_leaves'] ?? 0);
        $dutyHours = (float)($data['total_duty_hours'] ?? 0.0);

        $basicSalary = (float)($data['basic_salary'] ?? 0.0);
        $fuelAllowance = (float)($data['fuel_allowance'] ?? 0.0);
        $incentive = (float)($data['incentive'] ?? 0.0);
        $foodBills = (float)($data['food_bills'] ?? 0.0);
        $bonus = (float)($data['bonus'] ?? 0.0);
        $bonusReason = trim($data['bonus_reason'] ?? '');

        $advanceSalary = (float)($data['advance_salary'] ?? 0.0);
        $loanDeduction = (float)($data['loan_deduction'] ?? 0.0);
        $fines = (float)($data['fines'] ?? 0.0);
        $fineReason = trim($data['fine_reason'] ?? '');
        $whtAmount = (float)($data['wht_amount'] ?? 0.0);
        $deductions = (float)($data['deductions'] ?? 0.0);
        $deductionReason = trim($data['deduction_reason'] ?? '');

        $unpaidLeaveDeduction = (isset($data['unpaid_leave_deduction']) && $data['unpaid_leave_deduction'] !== '') ? (float)$data['unpaid_leave_deduction'] : 0.00;

        $grossAdditions = $fuelAllowance + $incentive + $bonus;
        $grossDeductions = $advanceSalary + $loanDeduction + $foodBills + $fines + $whtAmount + $deductions + $unpaidLeaveDeduction;
        $netSalary = max(0, $basicSalary + $grossAdditions - $grossDeductions);

        $paidAmount = (float)($data['paid_amount'] ?? 0.0);
        $payableAmount = max(0, $netSalary - $paidAmount);
        $formNo = trim($data['form_no'] ?? '');
        $incrementRemarks = trim($data['increment_remarks'] ?? '');
        $paymentStatus = in_array($data['payment_status'] ?? '', ['draft', 'approved', 'paid']) ? $data['payment_status'] : 'draft';
        $paymentMethod = trim($data['payment_method'] ?? 'Bank Transfer');
        $paymentDate = !empty($data['payment_date']) ? $data['payment_date'] : null;

        if ($empId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID.']);
            exit;
        }

        try {
            // Check if record exists
            $stmtCheck = $pdo->prepare("SELECT id FROM hr_payroll WHERE employee_id = ? AND salary_month = ?");
            $stmtCheck->execute([$empId, $salaryMonth]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE hr_payroll SET
                        basic_salary = ?,
                        working_days = ?,
                        present_days = ?,
                        approved_leaves = ?,
                        unpaid_leaves = ?,
                        unpaid_leave_deduction = ?,
                        total_duty_hours = ?,
                        fuel_allowance = ?,
                        incentive = ?,
                        food_bills = ?,
                        bonus = ?,
                        bonus_reason = ?,
                        advance_salary = ?,
                        loan_deduction = ?,
                        fines = ?,
                        fine_reason = ?,
                        wht_amount = ?,
                        deductions = ?,
                        deduction_reason = ?,
                        net_salary = ?,
                        paid_amount = ?,
                        payable_amount = ?,
                        form_no = ?,
                        increment_remarks = ?,
                        payment_status = ?,
                        payment_method = ?,
                        payment_date = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $basicSalary, $workingDays, $presentDays, $approvedLeaves, $unpaidLeaves, $unpaidLeaveDeduction, $dutyHours,
                    $fuelAllowance, $incentive, $foodBills, $bonus, $bonusReason,
                    $advanceSalary, $loanDeduction, $fines, $fineReason, $whtAmount, $deductions, $deductionReason,
                    $netSalary, $paidAmount, $payableAmount, $formNo, $incrementRemarks,
                    $paymentStatus, $paymentMethod, $paymentDate, $existingId
                ]);
            } else {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO hr_payroll (
                        employee_id, salary_month, basic_salary, working_days, present_days,
                        approved_leaves, unpaid_leaves, unpaid_leave_deduction, total_duty_hours,
                        fuel_allowance, incentive, food_bills, bonus, bonus_reason,
                        advance_salary, loan_deduction, fines, fine_reason, wht_amount,
                        deductions, deduction_reason, net_salary, paid_amount, payable_amount,
                        form_no, increment_remarks, payment_status, payment_method, payment_date,
                        generated_by, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, NOW(), NOW()
                    )
                ");
                $stmtInsert->execute([
                    $empId, $salaryMonth, $basicSalary, $workingDays, $presentDays,
                    $approvedLeaves, $unpaidLeaves, $unpaidLeaveDeduction, $dutyHours,
                    $fuelAllowance, $incentive, $foodBills, $bonus, $bonusReason,
                    $advanceSalary, $loanDeduction, $fines, $fineReason, $whtAmount,
                    $deductions, $deductionReason, $netSalary, $paidAmount, $payableAmount,
                    $formNo, $incrementRemarks, $paymentStatus, $paymentMethod, $paymentDate,
                    $currentUserId
                ]);
            }

            // When salary is disbursed / marked as paid, automatically settle advance salary and loan deductions
            if ($paymentStatus === 'paid') {
                // 1. Settle Advance Salary
                if ($advanceSalary > 0) {
                    $stmtAdv = $pdo->prepare("
                        SELECT id, amount, paid_amount 
                        FROM hr_loans 
                        WHERE employee_id = ? AND request_type = 'advance_salary' AND status IN ('approved', 'active')
                        ORDER BY id ASC
                    ");
                    $stmtAdv->execute([$empId]);
                    $advLoans = $stmtAdv->fetchAll();

                    $remainingToDeduct = $advanceSalary;
                    foreach ($advLoans as $al) {
                        if ($remainingToDeduct <= 0) break;
                        $loanBal = (float)$al['amount'] - (float)$al['paid_amount'];
                        if ($loanBal <= 0) continue;

                        $deductFromThis = min($loanBal, $remainingToDeduct);
                        $newPaidAmount = (float)$al['paid_amount'] + $deductFromThis;
                        $newStatus = ($newPaidAmount >= (float)$al['amount']) ? 'repaid' : 'approved';

                        $stmtUpd = $pdo->prepare("
                            UPDATE hr_loans 
                            SET paid_amount = ?, status = ?, action_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmtUpd->execute([$newPaidAmount, $newStatus, $al['id']]);
                        $remainingToDeduct -= $deductFromThis;
                    }
                }

                // 2. Settle Monthly Loan Installment
                if ($loanDeduction > 0) {
                    $stmtLn = $pdo->prepare("
                        SELECT id, amount, paid_amount, monthly_deduction 
                        FROM hr_loans 
                        WHERE employee_id = ? AND request_type != 'advance_salary' AND status IN ('approved', 'active')
                        ORDER BY id ASC
                    ");
                    $stmtLn->execute([$empId]);
                    $regLoans = $stmtLn->fetchAll();

                    $remainingToDeduct = $loanDeduction;
                    foreach ($regLoans as $rl) {
                        if ($remainingToDeduct <= 0) break;
                        $loanBal = (float)$rl['amount'] - (float)$rl['paid_amount'];
                        if ($loanBal <= 0) continue;

                        $deductFromThis = min($loanBal, $remainingToDeduct);
                        $newPaidAmount = (float)$rl['paid_amount'] + $deductFromThis;
                        $newStatus = ($newPaidAmount >= (float)$rl['amount']) ? 'repaid' : 'approved';

                        $stmtUpd = $pdo->prepare("
                            UPDATE hr_loans 
                            SET paid_amount = ?, status = ?, action_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmtUpd->execute([$newPaidAmount, $newStatus, $rl['id']]);
                        $remainingToDeduct -= $deductFromThis;
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => 'Payroll adjustments saved successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error saving payroll: ' . $e->getMessage()]);
        }
        break;

    // ================= 7. FUEL, TRAVEL, FOOD & INCENTIVE CLAIMS =================
    case 'get_claims':
        $statusFilter = $_GET['status'] ?? 'all';
        $typeFilter = $_GET['claim_type'] ?? 'all';
        $monthFilter = $_GET['month'] ?? '';
        $empFilter = (int)($_GET['employee_id'] ?? 0);

        $whereClauses = ["1=1"];
        $params = [];

        if (!$canManageHr && !$isHod) {
            $whereClauses[] = "c.employee_id = ?";
            $params[] = $currentUserId;
        } elseif ($isHod && !$canManageHr) {
            $whereClauses[] = "(c.employee_id = ? OR e.department_id = ?)";
            $params[] = $currentUserId;
            $params[] = $userDeptId;
        }

        if ($statusFilter !== 'all') {
            $whereClauses[] = "c.status = ?";
            $params[] = $statusFilter;
        }

        if ($typeFilter !== 'all') {
            $whereClauses[] = "c.claim_type = ?";
            $params[] = $typeFilter;
        }

        if ($monthFilter) {
            $whereClauses[] = "c.salary_month = ?";
            $params[] = $monthFilter;
        }

        if ($empFilter > 0 && ($canManageHr || $isHod)) {
            $whereClauses[] = "c.employee_id = ?";
            $params[] = $empFilter;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "
            SELECT 
                c.*,
                e.name as employee_name,
                e.designation,
                e.avatar,
                d.name as department_name,
                COALESCE(p.emp_code, CONCAT('DP-', LPAD(e.id, 3, '0'))) as emp_code,
                act.name as action_by_name
            FROM hr_claims c
            JOIN employees e ON c.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            LEFT JOIN employees act ON c.action_by = act.id
            WHERE {$whereSql}
            ORDER BY c.claim_date DESC, c.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary calculations
        $totalFuel = 0.00;
        $totalFood = 0.00;
        $totalIncentive = 0.00;
        $pendingCount = 0;

        foreach ($claims as $clm) {
            $amt = (float)$clm['amount'];
            if ($clm['status'] === 'pending') {
                $pendingCount++;
            }
            if ($clm['status'] === 'approved' || $clm['status'] === 'paid') {
                if (in_array($clm['claim_type'], ['fuel', 'travel', 'mobile'])) {
                    $totalFuel += $amt;
                } elseif ($clm['claim_type'] === 'food_bills') {
                    $totalFood += $amt;
                } elseif (in_array($clm['claim_type'], ['incentive', 'bonus'])) {
                    $totalIncentive += $amt;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'claims' => $claims,
            'stats' => [
                'total_claims' => count($claims),
                'pending_count' => $pendingCount,
                'total_fuel_amount' => $totalFuel,
                'total_food_amount' => $totalFood,
                'total_incentive_amount' => $totalIncentive
            ]
        ]);
        break;

    case 'add_claim':
        $empId = (int)($data['employee_id'] ?? $currentUserId);
        $claimType = $data['claim_type'] ?? 'fuel';
        $amount = (float)($data['amount'] ?? 0);
        $claimDate = !empty($data['claim_date']) ? $data['claim_date'] : date('Y-m-d');
        $salaryMonth = !empty($data['salary_month']) ? $data['salary_month'] : date('Y-m', strtotime($claimDate));
        $receiptNo = trim($data['receipt_no'] ?? '');
        $reason = trim($data['reason'] ?? '');
        $autoApprove = !empty($data['auto_approve']);

        if ($empId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select an employee.']);
            exit;
        }
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
            exit;
        }
        if (!$reason) {
            echo json_encode(['success' => false, 'message' => 'Please provide the purpose, route, or reason for this claim.']);
            exit;
        }

        $validTypes = ['fuel', 'travel', 'mobile', 'incentive', 'food_bills', 'bonus', 'other'];
        if (!in_array($claimType, $validTypes)) {
            $claimType = 'fuel';
        }

        // Determine status
        $status = 'pending';
        $actionBy = null;
        $actionAt = null;

        if ($canManageHr || ($isHod && $autoApprove)) {
            $status = 'approved';
            $actionBy = $currentUserId;
            $actionAt = date('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare("
            INSERT INTO hr_claims 
                (employee_id, claim_type, amount, claim_date, salary_month, receipt_no, reason, status, action_by, action_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $empId, $claimType, $amount, $claimDate, $salaryMonth, $receiptNo, $reason, $status, $actionBy, $actionAt
        ]);

        $statusMsg = ($status === 'approved') ? 'approved & recorded' : 'submitted for review';
        echo json_encode(['success' => true, 'message' => "Claim successfully {$statusMsg}."]);
        break;

    case 'update_claim_status':
        if (!$canManageHr && !$isHod) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $claimId = (int)($data['claim_id'] ?? 0);
        $status = $data['status'] ?? 'approved';
        $adminNotes = trim($data['admin_notes'] ?? '');

        if (!in_array($status, ['pending', 'approved', 'rejected', 'paid'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid claim status.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE hr_claims 
            SET status = ?, admin_notes = ?, action_by = ?, action_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $adminNotes, $currentUserId, $claimId]);

        echo json_encode(['success' => true, 'message' => "Claim status updated to {$status}."]);
        break;

    case 'delete_claim':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $claimId = (int)($data['claim_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM hr_claims WHERE id = ?");
        $stmt->execute([$claimId]);

        echo json_encode(['success' => true, 'message' => 'Claim record deleted successfully.']);
        break;

    default:
        echo json_encode(['error' => 'Invalid or missing action in HR API']);
        break;
}

