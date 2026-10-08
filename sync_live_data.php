<?php
/**
 * Universal Intelligent Database Sync for Live & Localhost
 * 
 * Synchronizes:
 * 1. Departments & Teams
 * 2. Employee Roster (matches by name/email to handle ID discrepancies gracefully)
 * 3. HR Profiles (salaries, bank details, allowances)
 * 4. September 2026 Payroll records (mapped to exact matching employee ID)
 * 5. Canteen Food Bill deductions & Allowances
 * 6. Preserves all daily sheets, tasks, attendance, and active sessions
 */

require_once __DIR__ . '/config/database.php';
$pdo = getDbConnection();

echo "<pre style='font-family: monospace; background:#1e1e2e; color:#a6e3a1; padding:20px; border-radius:8px;'>";
echo "====================================================\n";
echo "   Discover Pakistan - Smart Live Data Synchronizer \n";
echo "====================================================\n\n";

// Ensure schema is fully migrated
migratePermissionsSchema($pdo);
ensureDepartmentAndTeamsSchema($pdo);
ensureDetailedPayrollSchema($pdo);

// Delete temporary/typo departments if they exist
$pdo->exec("UPDATE employees SET department_id = 2 WHERE department_id IN (SELECT id FROM departments WHERE name LIKE '%SOCIAL%')");
$pdo->exec("UPDATE employees SET department_id = 1 WHERE department_id IN (SELECT id FROM departments WHERE name LIKE '%News Roon%')");
$pdo->exec("DELETE FROM departments WHERE name LIKE '%SOCIAL%' OR name LIKE '%News Roon%'");

// Department Map
$deptMap = $pdo->query("SELECT LOWER(TRIM(name)), id FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
$teamMap = $pdo->query("SELECT LOWER(TRIM(name)), id FROM teams")->fetchAll(PDO::FETCH_KEY_PAIR);

$digitalDeptId = $deptMap['digital'] ?? 2;
$newsDeptId = $deptMap['news room'] ?? 1;
$progDeptId = $deptMap['programming'] ?? 3;

$defaultPasswordHash = password_hash('DiscoverPakistan123', PASSWORD_DEFAULT);

// Load the master employee & payroll dataset from database directory
$datasetFile = __DIR__ . '/database/master_dataset.json';
if (!file_exists($datasetFile)) {
    // Generate dataset from current clean state
    $employeesData = $pdo->query("
        SELECT e.*, d.name as dept_name, t.name as team_name,
               p.emp_code, p.father_husband_name, p.cnic_no, p.bank_name, p.bank_account_no, p.fixed_allowance, p.basic_salary as profile_salary, p.employment_type,
               pay.basic_salary as pay_basic, pay.fuel_allowance, pay.incentive, pay.food_bills, pay.bonus, pay.bonus_reason, pay.advance_salary, pay.loan_deduction, pay.fines, pay.fine_reason, pay.wht_amount, pay.deductions, pay.deduction_reason, pay.net_salary, pay.paid_amount, pay.payable_amount, pay.form_no, pay.increment_remarks, pay.working_days
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN teams t ON e.team_id = t.id
        LEFT JOIN hr_employee_profiles p ON e.id = p.employee_id
        LEFT JOIN hr_payroll pay ON e.id = pay.employee_id AND pay.salary_month = '2026-09'
        WHERE e.is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    file_put_contents($datasetFile, json_encode($employeesData, JSON_PRETTY_PRINT));
}

$dataset = json_decode(file_get_contents($datasetFile), true);
echo "Processing " . count($dataset) . " master employee records...\n";

$syncedCount = 0;
$createdCount = 0;

foreach ($dataset as $row) {
    $name = trim($row['name']);
    $email = !empty($row['email']) ? strtolower(trim($row['email'])) : null;
    $deptName = strtolower(trim($row['dept_name'] ?? ''));
    $teamName = strtolower(trim($row['team_name'] ?? ''));
    
    // Resolve department ID
    $targetDeptId = $deptMap[$deptName] ?? $digitalDeptId;
    if ($deptName === 'digital' || stripos($deptName, 'social') !== false) {
        $targetDeptId = $digitalDeptId;
    }
    
    // Resolve team ID
    $targetTeamId = $teamMap[$teamName] ?? null;

    // 1. Find matching employee on current database (by email first, then by name)
    $matchedEmp = null;
    if ($email && $email !== 'no login email') {
        $stmtFind = $pdo->prepare("SELECT * FROM employees WHERE email = ? LIMIT 1");
        $stmtFind->execute([$email]);
        $matchedEmp = $stmtFind->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$matchedEmp) {
        $stmtFind = $pdo->prepare("SELECT * FROM employees WHERE LOWER(TRIM(name)) = ? LIMIT 1");
        $stmtFind->execute([strtolower($name)]);
        $matchedEmp = $stmtFind->fetch(PDO::FETCH_ASSOC);
    }

    $empId = null;
    if ($matchedEmp) {
        $empId = (int)$matchedEmp['id'];
        // Update employee details
        $stmtUp = $pdo->prepare("
            UPDATE employees 
            SET name = ?,
                email = COALESCE(?, email),
                designation = ?,
                role = ?,
                department_id = ?,
                team_id = COALESCE(?, team_id),
                can_login = ?,
                is_active = 1,
                password_hash = COALESCE(NULLIF(password_hash, ''), ?)
            WHERE id = ?
        ");
        $stmtUp->execute([
            $name,
            $email,
            $row['designation'],
            $row['role'] ?? 'employee',
            $targetDeptId,
            $targetTeamId,
            $row['can_login'] ?? 1,
            $defaultPasswordHash,
            $empId
        ]);
        $syncedCount++;
    } else {
        // Insert new employee
        $stmtIn = $pdo->prepare("
            INSERT INTO employees (name, email, password_hash, role, designation, department_id, team_id, can_login, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmtIn->execute([
            $name,
            $email,
            $defaultPasswordHash,
            $row['role'] ?? 'employee',
            $row['designation'],
            $targetDeptId,
            $targetTeamId,
            $row['can_login'] ?? 1
        ]);
        $empId = (int)$pdo->lastInsertId();
        $createdCount++;
    }

    if (!$empId) continue;

    // 2. Upsert hr_employee_profiles
    $profileSalary = (float)($row['profile_salary'] ?? $row['salary'] ?? $row['pay_basic'] ?? 0.00);
    $stmtProf = $pdo->prepare("
        INSERT INTO hr_employee_profiles 
            (employee_id, emp_code, father_husband_name, cnic_no, bank_name, bank_account_no, fixed_allowance, basic_salary, employment_type)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            emp_code = VALUES(emp_code),
            father_husband_name = VALUES(father_husband_name),
            cnic_no = VALUES(cnic_no),
            bank_name = VALUES(bank_name),
            bank_account_no = VALUES(bank_account_no),
            fixed_allowance = VALUES(fixed_allowance),
            basic_salary = VALUES(basic_salary),
            employment_type = VALUES(employment_type)
    ");
    $stmtProf->execute([
        $empId,
        $row['emp_code'] ?? null,
        $row['father_husband_name'] ?? null,
        $row['cnic_no'] ?? null,
        $row['bank_name'] ?? 'UBL',
        $row['bank_account_no'] ?? null,
        (float)($row['fixed_allowance'] ?? 0.00),
        $profileSalary,
        $row['employment_type'] ?? 'full_time'
    ]);

    // 3. Upsert hr_payroll for September 2026 if data exists
    if (isset($row['pay_basic']) && $row['pay_basic'] !== null) {
        $payBasic = (float)$row['pay_basic'];
        $fuel = (float)($row['fuel_allowance'] ?? 0.00);
        $incentive = (float)($row['incentive'] ?? 0.00);
        $foodBills = (float)($row['food_bills'] ?? 0.00);
        $bonus = (float)($row['bonus'] ?? 0.00);
        $advance = (float)($row['advance_salary'] ?? 0.00);
        $loan = (float)($row['loan_deduction'] ?? 0.00);
        $fines = (float)($row['fines'] ?? 0.00);
        $wht = (float)($row['wht_amount'] ?? 0.00);
        $deductions = (float)($row['deductions'] ?? 0.00);
        $net = (float)($row['net_salary'] ?? ($payBasic + $fuel + $incentive + $bonus - $foodBills - $advance - $loan - $fines - $wht - $deductions));
        $payable = (float)($row['payable_amount'] ?? $net);

        $stmtPay = $pdo->prepare("
            INSERT INTO hr_payroll 
                (employee_id, salary_month, basic_salary, fuel_allowance, incentive, food_bills, bonus, bonus_reason, advance_salary, loan_deduction, fines, fine_reason, wht_amount, deductions, deduction_reason, net_salary, payable_amount, paid_amount, form_no, increment_remarks, working_days, payment_status)
            VALUES
                (?, '2026-09', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, 'draft')
            ON DUPLICATE KEY UPDATE
                basic_salary = VALUES(basic_salary),
                fuel_allowance = VALUES(fuel_allowance),
                incentive = VALUES(incentive),
                food_bills = VALUES(food_bills),
                bonus = VALUES(bonus),
                bonus_reason = VALUES(bonus_reason),
                advance_salary = VALUES(advance_salary),
                loan_deduction = VALUES(loan_deduction),
                fines = VALUES(fines),
                fine_reason = VALUES(fine_reason),
                wht_amount = VALUES(wht_amount),
                deductions = VALUES(deductions),
                deduction_reason = VALUES(deduction_reason),
                net_salary = VALUES(net_salary),
                payable_amount = VALUES(payable_amount),
                form_no = VALUES(form_no),
                increment_remarks = VALUES(increment_remarks),
                working_days = VALUES(working_days)
        ");
        $stmtPay->execute([
            $empId,
            $payBasic,
            $fuel,
            $incentive,
            $foodBills,
            $bonus,
            $row['bonus_reason'] ?? null,
            $advance,
            $loan,
            $fines,
            $row['fine_reason'] ?? null,
            $wht,
            $deductions,
            $row['deduction_reason'] ?? null,
            $net,
            $payable,
            $row['form_no'] ?? null,
            $row['increment_remarks'] ?? null,
            (int)($row['working_days'] ?? 30)
        ]);
    }
}

echo "\n[SUCCESS] Synchronization Completed!\n";
echo " - Updated Existing Staff Records: {$syncedCount}\n";
echo " - Added Missing Staff Records: {$createdCount}\n";
echo " - Digital Team Staff in Database: " . $pdo->query("SELECT COUNT(*) FROM employees WHERE department_id = {$digitalDeptId} AND is_active = 1")->fetchColumn() . "\n";
echo " - Total Payroll Records for Sep-2026: " . $pdo->query("SELECT COUNT(*) FROM hr_payroll WHERE salary_month = '2026-09'")->fetchColumn() . "\n\n";
echo "You can now view the Staff Directory and HR Payroll on live without any missing records!\n";
echo "</pre>";
