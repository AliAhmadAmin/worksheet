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
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isAdmin = ($currentUserRole === 'admin' || $currentUserRole === 'super_admin' || $currentUserRole === 'hr' || (isset($currentUserObj['department_name']) && strtolower($currentUserObj['department_name']) === 'hr'));
$canManageHr = $isAdmin || !empty($currentUserObj['can_manage_hr']);

switch ($action) {
    case 'get_overview':
        $selectedMonth = $_GET['month'] ?? date('Y-m');
        $todayDate = date('Y-m-d');

        // Total Pending Leaves
        $stmtPending = $pdo->query("SELECT COUNT(*) FROM hr_leaves WHERE status = 'pending'");
        $pendingLeavesCount = (int)$stmtPending->fetchColumn();

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
        $stmtPendingList = $pdo->query("
            SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name
            FROM hr_leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE l.status = 'pending'
            ORDER BY l.created_at ASC
            LIMIT 6
        ");
        $pendingLeavesList = $stmtPendingList->fetchAll();

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
        $stmtPendingLoans = $pdo->query("SELECT COUNT(*) FROM hr_loans WHERE status = 'pending'");
        $pendingLoansCount = (int)$stmtPendingLoans->fetchColumn();

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
        $stmtPendingLoansList = $pdo->query("
            SELECT l.*, e.name as employee_name, e.designation, e.avatar, d.name as department_name
            FROM hr_loans l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE l.status = 'pending'
            ORDER BY l.created_at ASC
            LIMIT 6
        ");
        $pendingLoansList = $stmtPendingLoansList->fetchAll();

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

        $sql = "
            SELECT l.*, 
                   e.name as employee_name, 
                   e.designation as employee_designation,
                   e.avatar as employee_avatar,
                   d.name as department_name,
                   a.name as action_by_name
            FROM hr_leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees a ON l.action_by = a.id
            WHERE 1=1
        ";
        $params = [];

        // If not HR admin and not inspecting someone else, employee can only see their own leaves
        if (!$canManageHr) {
            $sql .= " AND l.employee_id = ?";
            $params[] = $currentUserId;
        } elseif ($empFilter) {
            $sql .= " AND l.employee_id = ?";
            $params[] = $empFilter;
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $sql .= " AND l.status = ?";
            $params[] = $statusFilter;
        }

        if ($typeFilter && $typeFilter !== 'all') {
            $sql .= " AND l.leave_type = ?";
            $params[] = $typeFilter;
        }

        $sql .= " ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END, l.start_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leaves = $stmt->fetchAll();

        echo json_encode(['success' => true, 'leaves' => $leaves]);
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

        $initialStatus = $canManageHr && isset($data['auto_approve']) && $data['auto_approve'] ? 'approved' : 'pending';
        $actionBy = ($initialStatus === 'approved') ? $currentUserId : null;
        $actionAt = ($initialStatus === 'approved') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO hr_leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status, action_by, action_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$empId, $leaveType, $startDate, $endDate, $daysCount, $reason, $initialStatus, $actionBy, $actionAt]);

        echo json_encode([
            'success' => true,
            'message' => ($initialStatus === 'approved') ? 'Leave recorded and approved successfully.' : 'Leave application submitted successfully for approval.'
        ]);
        break;

    case 'update_leave_status':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: HR Manager / Admin permission required.']);
            exit;
        }

        $leaveId = (int)($data['leave_id'] ?? 0);
        $status = $data['status'] ?? '';
        $adminNotes = trim($data['admin_notes'] ?? '');

        if (!in_array($status, ['approved', 'rejected', 'pending', 'cancelled'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE hr_leaves 
            SET status = ?, admin_notes = ?, action_by = ?, action_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$status, $adminNotes, $currentUserId, $leaveId]);

        echo json_encode(['success' => true, 'message' => "Leave application marked as {$status}."]);
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

        if (!$canManageHr && $leave['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Only pending leave applications can be cancelled.']);
            exit;
        }

        $stmtDel = $pdo->prepare("DELETE FROM hr_leaves WHERE id = ?");
        $stmtDel->execute([$leaveId]);

        echo json_encode(['success' => true, 'message' => 'Leave application removed successfully.']);
        break;

    case 'get_profiles':
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
            WHERE e.is_active = 1
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

    case 'get_payroll':
        $salaryMonth = $_GET['month'] ?? date('Y-m');
        $empFilter = $_GET['employee_id'] ?? null;

        // Parse month
        $parts = explode('-', $salaryMonth);
        $year = (int)($parts[0] ?? date('Y'));
        $month = (int)($parts[1] ?? date('m'));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // Fetch active employees
        $empSql = "
            SELECT e.id, e.name, e.designation, e.role, e.avatar,
                   d.name as department_name, t.name as team_name,
                   COALESCE(p.basic_salary, 0.00) as default_basic_salary,
                   COALESCE(p.employment_type, 'full_time') as employment_type,
                   p.joining_date
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
            WHERE e.is_active = 1
        ";
        $empParams = [];
        if (!$canManageHr) {
            $empSql .= " AND e.id = ?";
            $empParams[] = $currentUserId;
        } elseif ($empFilter) {
            $empSql .= " AND e.id = ?";
            $empParams[] = $empFilter;
        }
        $empSql .= " ORDER BY e.name ASC";

        $stmtEmp = $pdo->prepare($empSql);
        $stmtEmp->execute($empParams);
        $employees = $stmtEmp->fetchAll();

        // Fetch existing payroll records for month
        $stmtPayroll = $pdo->prepare("SELECT * FROM hr_payroll WHERE salary_month = ?");
        $stmtPayroll->execute([$salaryMonth]);
        $existingPayroll = [];
        foreach ($stmtPayroll->fetchAll() as $pr) {
            $existingPayroll[$pr['employee_id']] = $pr;
        }

        $payrollItems = [];

        foreach ($employees as $emp) {
            $eId = $emp['id'];

            // 1. Calculate attended days and duty seconds from daily_sheets
            $stmtAtt = $pdo->prepare("
                SELECT COUNT(*) as present_count, 
                       COALESCE(SUM(total_duty_seconds), 0) as total_seconds
                FROM daily_sheets 
                WHERE employee_id = ? 
                  AND DATE_FORMAT(sheet_date, '%Y-%m') = ?
                  AND (check_in_time IS NOT NULL OR total_duty_seconds > 0)
            ");
            $stmtAtt->execute([$eId, $salaryMonth]);
            $attRow = $stmtAtt->fetch();
            $presentDays = (int)($attRow['present_count'] ?? 0);
            $totalDutyHours = round(((int)$attRow['total_seconds']) / 3600, 1);

            // 2. Calculate approved leaves in this month
            $stmtLeaves = $pdo->prepare("
                SELECT leave_type, SUM(days_count) as days
                FROM hr_leaves
                WHERE employee_id = ? 
                  AND status = 'approved'
                  AND (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)
                GROUP BY leave_type
            ");
            $stmtLeaves->execute([$eId, $salaryMonth, $salaryMonth]);
            $leavesRows = $stmtLeaves->fetchAll();
            $approvedLeaves = 0;
            $unpaidLeaves = 0;
            foreach ($leavesRows as $lr) {
                if ($lr['leave_type'] === 'unpaid') {
                    $unpaidLeaves += (float)$lr['days'];
                } else {
                    $approvedLeaves += (float)$lr['days'];
                }
            }

            // Existing or Computed Record
            $record = $existingPayroll[$eId] ?? null;
            $basicSalary = $record ? (float)$record['basic_salary'] : (float)$emp['default_basic_salary'];
            $bonus = $record ? (float)$record['bonus'] : 0.00;
            $deductions = $record ? (float)$record['deductions'] : 0.00;
            $deductionReason = $record ? ($record['deduction_reason'] ?? '') : '';
            $bonusReason = $record ? ($record['bonus_reason'] ?? '') : '';
            $paymentStatus = $record ? ($record['payment_status'] ?? 'draft') : 'draft';
            $paymentDate = $record ? ($record['payment_date'] ?? null) : null;
            $paymentMethod = $record ? ($record['payment_method'] ?? 'Bank Transfer') : 'Bank Transfer';

            // Auto Loan / Advance Installment calculation if no manual deduction already set
            $stmtActiveLoans = $pdo->prepare("
                SELECT * FROM hr_loans 
                WHERE employee_id = ? 
                  AND status = 'approved' 
                  AND deduction_start_month <= ? 
                  AND paid_amount < amount
            ");
            $stmtActiveLoans->execute([$eId, $salaryMonth]);
            $activeLoans = $stmtActiveLoans->fetchAll();
            $autoLoanDeduction = 0;
            $loanNotes = [];
            foreach ($activeLoans as $al) {
                $rem = (float)$al['amount'] - (float)$al['paid_amount'];
                $monthly = min($rem, (float)$al['monthly_deduction']);
                $autoLoanDeduction += $monthly;
                $loanNotes[] = ucfirst(str_replace('_', ' ', $al['request_type'])) . " (" . number_format($monthly) . ")";
            }

            if (!$record && $autoLoanDeduction > 0) {
                $deductions = $autoLoanDeduction;
                $deductionReason = "Loan Deduction: " . implode(', ', $loanNotes);
            }

            // Auto Net Salary calculation
            $netSalary = max(0, $basicSalary + $bonus - $deductions);

            $payrollItems[] = [
                'employee_id' => $eId,
                'employee_name' => $emp['name'],
                'designation' => $emp['designation'],
                'department_name' => $emp['department_name'],
                'avatar' => $emp['avatar'],
                'salary_month' => $salaryMonth,
                'working_days' => $daysInMonth,
                'present_days' => $presentDays,
                'approved_leaves' => $approvedLeaves,
                'unpaid_leaves' => $unpaidLeaves,
                'total_duty_hours' => $totalDutyHours,
                'basic_salary' => $basicSalary,
                'bonus' => $bonus,
                'bonus_reason' => $bonusReason,
                'deductions' => $deductions,
                'deduction_reason' => $deductionReason,
                'net_salary' => $netSalary,
                'active_loans_count' => count($activeLoans),
                'auto_loan_deduction' => $autoLoanDeduction,
                'payment_status' => $paymentStatus,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod
            ];
        }

        echo json_encode([
            'success' => true,
            'salary_month' => $salaryMonth,
            'payroll' => $payrollItems
        ]);
        break;

    case 'save_payroll_item':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: Admin/HR permission required.']);
            exit;
        }

        $empId = (int)($data['employee_id'] ?? 0);
        $salaryMonth = $data['salary_month'] ?? date('Y-m');
        $basicSalary = (float)($data['basic_salary'] ?? 0.00);
        $workingDays = (int)($data['working_days'] ?? 30);
        $presentDays = (int)($data['present_days'] ?? 0);
        $approvedLeaves = (int)($data['approved_leaves'] ?? 0);
        $unpaidLeaves = (int)($data['unpaid_leaves'] ?? 0);
        $totalDutyHours = (float)($data['total_duty_hours'] ?? 0.00);
        $bonus = (float)($data['bonus'] ?? 0.00);
        $deductions = (float)($data['deductions'] ?? 0.00);
        $bonusReason = trim($data['bonus_reason'] ?? '');
        $deductionReason = trim($data['deduction_reason'] ?? '');
        $paymentStatus = $data['payment_status'] ?? 'draft';
        $paymentDate = !empty($data['payment_date']) ? $data['payment_date'] : null;
        $paymentMethod = $data['payment_method'] ?? 'Bank Transfer';

        $netSalary = max(0, $basicSalary + $bonus - $deductions);

        $stmt = $pdo->prepare("
            INSERT INTO hr_payroll 
                (employee_id, salary_month, basic_salary, working_days, present_days, approved_leaves, unpaid_leaves, 
                 total_duty_hours, bonus, deductions, bonus_reason, deduction_reason, net_salary, payment_status, payment_date, payment_method, generated_by)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                basic_salary = VALUES(basic_salary),
                working_days = VALUES(working_days),
                present_days = VALUES(present_days),
                approved_leaves = VALUES(approved_leaves),
                unpaid_leaves = VALUES(unpaid_leaves),
                total_duty_hours = VALUES(total_duty_hours),
                bonus = VALUES(bonus),
                deductions = VALUES(deductions),
                bonus_reason = VALUES(bonus_reason),
                deduction_reason = VALUES(deduction_reason),
                net_salary = VALUES(net_salary),
                payment_status = VALUES(payment_status),
                payment_date = VALUES(payment_date),
                payment_method = VALUES(payment_method),
                generated_by = VALUES(generated_by)
        ");

        $stmt->execute([
            $empId, $salaryMonth, $basicSalary, $workingDays, $presentDays, $approvedLeaves, $unpaidLeaves,
            $totalDutyHours, $bonus, $deductions, $bonusReason, $deductionReason, $netSalary, $paymentStatus, $paymentDate, $paymentMethod, $currentUserId
        ]);

        echo json_encode(['success' => true, 'message' => 'Payroll record updated successfully.']);
        break;

    case 'get_loans':
        $empFilter = $_GET['employee_id'] ?? null;
        $statusFilter = $_GET['status'] ?? null;
        $typeFilter = $_GET['request_type'] ?? null;

        $sql = "
            SELECT l.*, 
                   (l.amount - l.paid_amount) as remaining_amount,
                   e.name as employee_name, 
                   e.designation as employee_designation,
                   e.avatar as employee_avatar,
                   d.name as department_name,
                   a.name as action_by_name
            FROM hr_loans l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN employees a ON l.action_by = a.id
            WHERE 1=1
        ";
        $params = [];

        if (!$canManageHr) {
            $sql .= " AND l.employee_id = ?";
            $params[] = $currentUserId;
        } elseif ($empFilter) {
            $sql .= " AND l.employee_id = ?";
            $params[] = $empFilter;
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $sql .= " AND l.status = ?";
            $params[] = $statusFilter;
        }

        if ($typeFilter && $typeFilter !== 'all') {
            $sql .= " AND l.request_type = ?";
            $params[] = $typeFilter;
        }

        $sql .= " ORDER BY CASE l.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END, l.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $loans = $stmt->fetchAll();

        echo json_encode(['success' => true, 'loans' => $loans]);
        break;

    case 'apply_loan':
        $empId = $canManageHr ? (int)($data['employee_id'] ?? $currentUserId) : $currentUserId;
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
        $initialStatus = $canManageHr && isset($data['auto_approve']) && $data['auto_approve'] ? 'approved' : 'pending';
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
            'message' => ($initialStatus === 'approved') ? 'Loan/Advance recorded and approved successfully.' : 'Loan/Advance request submitted successfully for approval.'
        ]);
        break;

    case 'update_loan_status':
        if (!$canManageHr) {
            echo json_encode(['success' => false, 'message' => 'Access denied: HR Manager / Admin permission required.']);
            exit;
        }

        $loanId = (int)($data['loan_id'] ?? 0);
        $status = $data['status'] ?? '';
        $adminNotes = trim($data['admin_notes'] ?? '');
        $paidAmount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : null;

        if (!in_array($status, ['approved', 'rejected', 'repaid', 'pending', 'cancelled'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
            exit;
        }

        if ($paidAmount !== null) {
            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = ?, admin_notes = ?, paid_amount = ?, action_by = ?, action_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $adminNotes, $paidAmount, $currentUserId, $loanId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE hr_loans 
                SET status = ?, admin_notes = ?, action_by = ?, action_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $adminNotes, $currentUserId, $loanId]);
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

        $title = trim($data['title'] ?? '');
        $message = trim($data['message'] ?? '');
        $priority = $data['priority'] ?? 'normal';
        $targetDeptId = !empty($data['target_department_id']) ? (int)$data['target_department_id'] : null;

        if (!$title || !$message) {
            echo json_encode(['success' => false, 'message' => 'Title and announcement message are required.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO hr_notices (title, message, priority, target_department_id, posted_by, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$title, $message, $priority, $targetDeptId, $currentUserId]);

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

    default:
        echo json_encode(['error' => 'Invalid or missing action in HR API']);
        break;
}
