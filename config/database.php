<?php
/**
 * Database Configuration and Auto-Bootstrapper for MySQL
 * Database: worksheet
 * Host: 127.0.0.1
 * User: root
 * Password: Ahmad@@**786Ali
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'worksheet');
define('DB_USER', 'root');
define('DB_PASS', '');
date_default_timezone_set('Asia/Karachi');

// Configure 30-day persistent session lifetime so user sessions never expire unexpectedly
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 2592000); // 30 days
    ini_set('session.cookie_lifetime', 2592000); // 30 days
    session_set_cookie_params([
        'lifetime' => 2592000,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true
    ];

    $passwordsToTry = array_unique([DB_PASS, '', 'root']);
    $lastException = null;

    // Fast path: Try connecting directly to existing database
    foreach ($passwordsToTry as $pass) {
        try {
            $pdo = new PDO($dsn, DB_USER, $pass, $options);
            if ($pdo) {
                return $pdo;
            }
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    // Fallback path: Database might not exist yet or needs bootstrap
    foreach ($passwordsToTry as $pass) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            if ($pdo) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `" . DB_NAME . "`");
                $check = $pdo->query("SHOW TABLES LIKE 'employees'")->fetch();
                if (!$check) {
                    initDatabaseSchema($pdo);
                    seedInitialData($pdo);
                } else {
                    migratePermissionsSchema($pdo);
                }
                return $pdo;
            }
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'MySQL Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')]);
    exit;
}

function migratePermissionsSchema($pdo) {
    try {
        // Change role column to VARCHAR(50) if needed to support custom roles
        $pdo->exec("ALTER TABLE employees MODIFY COLUMN role VARCHAR(50) DEFAULT 'employee'");

        // Add granular permission flags if not exists
        $columns = [
            'can_assign_tasks' => "TINYINT DEFAULT 0",
            'can_edit_tasks' => "TINYINT DEFAULT 0",
            'can_unlock_sheets' => "TINYINT DEFAULT 0",
            'can_inspect_sheets' => "TINYINT DEFAULT 0",
            'can_view_reports' => "TINYINT DEFAULT 0",
            'can_view_attendance' => "TINYINT DEFAULT 0",
            'can_manage_employees' => "TINYINT DEFAULT 0",
            'can_manage_hr' => "TINYINT DEFAULT 0"
        ];

        foreach ($columns as $col => $def) {
            $colCheck = $pdo->query("SHOW COLUMNS FROM employees LIKE '$col'")->fetch();
            if (!$colCheck) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN $col $def");
            }
        }

        // Check and add link column to tasks table if missing
        $taskLinkCheck = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'link'")->fetch();
        if (!$taskLinkCheck) {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN link TEXT AFTER description");
        }

        // Ensure all admin accounts have full permissions enabled (1)
        $pdo->exec("
            UPDATE employees 
            SET can_assign_tasks = 1, can_edit_tasks = 1, can_unlock_sheets = 1, 
                can_inspect_sheets = 1, can_view_reports = 1, can_view_attendance = 1, 
                can_manage_employees = 1, can_manage_hr = 1 
            WHERE role = 'admin'
        ");

        // Create HR Management tables if they don't exist
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_leaves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type ENUM('annual', 'casual', 'sick', 'unpaid', 'other') DEFAULT 'casual',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days_count DECIMAL(4,1) DEFAULT 1.0,
            reason TEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            hod_id INT NULL,
            hod_action_at DATETIME NULL,
            hod_notes TEXT,
            admin_notes TEXT,
            action_by INT NULL,
            action_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (action_by) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $pdo->exec("ALTER TABLE hr_leaves MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
            $pdo->exec("ALTER TABLE hr_leaves ADD COLUMN hod_id INT NULL");
            $pdo->exec("ALTER TABLE hr_leaves ADD COLUMN hod_action_at DATETIME NULL");
            $pdo->exec("ALTER TABLE hr_leaves ADD COLUMN hod_notes TEXT NULL");
        } catch (Exception $e) {}

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_employee_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL UNIQUE,
            phone VARCHAR(50),
            cnic VARCHAR(50),
            joining_date DATE,
            employment_type ENUM('full_time', 'part_time', 'contract', 'internship', 'probation') DEFAULT 'full_time',
            basic_salary DECIMAL(10,2) DEFAULT 0.00,
            hourly_rate DECIMAL(8,2) DEFAULT 0.00,
            emergency_contact VARCHAR(100),
            emergency_phone VARCHAR(50),
            address TEXT,
            annual_leave_quota INT DEFAULT 14,
            casual_leave_quota INT DEFAULT 10,
            sick_leave_quota INT DEFAULT 8,
            expected_hours DECIMAL(4,1) DEFAULT 8.0,
            shift_policy VARCHAR(50) DEFAULT 'standard_8h',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $pdo->exec("ALTER TABLE hr_employee_profiles ADD COLUMN expected_hours DECIMAL(4,1) DEFAULT 8.0");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE hr_employee_profiles ADD COLUMN shift_policy VARCHAR(50) DEFAULT 'standard_8h'");
        } catch (Exception $e) {}

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_payroll (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            salary_month VARCHAR(7) NOT NULL,
            basic_salary DECIMAL(10,2) DEFAULT 0.00,
            working_days INT DEFAULT 30,
            present_days INT DEFAULT 0,
            approved_leaves INT DEFAULT 0,
            unpaid_leaves INT DEFAULT 0,
            late_days INT DEFAULT 0,
            total_duty_hours DECIMAL(6,2) DEFAULT 0.00,
            bonus DECIMAL(10,2) DEFAULT 0.00,
            deductions DECIMAL(10,2) DEFAULT 0.00,
            deduction_reason TEXT,
            bonus_reason TEXT,
            net_salary DECIMAL(10,2) NOT NULL,
            payment_status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
            payment_date DATE NULL,
            payment_method VARCHAR(50) DEFAULT 'Bank Transfer',
            generated_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_emp_month (employee_id, salary_month),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            request_type ENUM('advance_salary', 'emergency_loan', 'medical_aid') DEFAULT 'advance_salary',
            amount DECIMAL(10,2) NOT NULL,
            repayment_months INT DEFAULT 1,
            monthly_deduction DECIMAL(10,2) NOT NULL,
            deduction_start_month VARCHAR(7) NOT NULL,
            paid_amount DECIMAL(10,2) DEFAULT 0.00,
            reason TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'repaid', 'cancelled') DEFAULT 'pending',
            admin_notes TEXT,
            action_by INT NULL,
            action_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (action_by) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_notices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            priority ENUM('normal', 'urgent', 'holiday', 'event') DEFAULT 'normal',
            target_department_id INT NULL,
            posted_by INT NOT NULL,
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (posted_by) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_fines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            fine_date DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            fine_category ENUM('late_arrival', 'unauthorized_absence', 'sop_violation', 'negligence', 'misconduct', 'other') DEFAULT 'sop_violation',
            reason TEXT NOT NULL,
            salary_month VARCHAR(7) NOT NULL,
            status ENUM('applied', 'waived') DEFAULT 'applied',
            waived_reason TEXT NULL,
            issued_by INT NULL,
            action_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (issued_by) REFERENCES employees(id) ON DELETE SET NULL,
            FOREIGN KEY (action_by) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $pdo->exec("ALTER TABLE hr_payroll ADD COLUMN fines DECIMAL(10,2) DEFAULT 0.00");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE hr_payroll ADD COLUMN fine_reason TEXT NULL");
        } catch (Exception $e) {}

        ensureDepartmentAndTeamsSchema($pdo);
        ensureDetailedPayrollSchema($pdo);

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS programming_dispatches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dispatch_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            path_whatsapp TEXT,
            channel VARCHAR(100) DEFAULT 'Discover Pakistan',
            sender_id INT NULL,
            sender_name VARCHAR(150),
            dispatch_time VARCHAR(50),
            link TEXT,
            dp_status VARCHAR(50) DEFAULT '',
            dp_time VARCHAR(50),
            pt_status VARCHAR(50) DEFAULT '',
            pt_time VARCHAR(50),
            publisher_id INT NULL,
            publisher_name VARCHAR(150),
            remarks TEXT,
            rating VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES employees(id) ON DELETE SET NULL,
            FOREIGN KEY (publisher_id) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS newsroom_dispatches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dispatch_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            path_whatsapp TEXT,
            channel VARCHAR(100) DEFAULT 'Discover Pakistan',
            sender_id INT NULL,
            sender_name VARCHAR(150),
            dispatch_time VARCHAR(50),
            link TEXT,
            dp_status VARCHAR(50) DEFAULT '',
            dp_time VARCHAR(50),
            pt_status VARCHAR(50) DEFAULT '',
            pt_time VARCHAR(50),
            publisher_id INT NULL,
            publisher_name VARCHAR(150),
            remarks TEXT,
            rating VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES employees(id) ON DELETE SET NULL,
            FOREIGN KEY (publisher_id) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed Programming & News Room Departments & Teams if missing
        $pdo->exec("INSERT IGNORE INTO departments (name) VALUES ('Programming')");
        $pdo->exec("INSERT IGNORE INTO departments (name) VALUES ('Archive')");
        $pdo->exec("INSERT IGNORE INTO departments (name) VALUES ('News Room')");
        $pdo->exec("INSERT IGNORE INTO teams (name) VALUES ('Programming Team')");
        $pdo->exec("INSERT IGNORE INTO teams (name) VALUES ('Archive Team')");
        $pdo->exec("INSERT IGNORE INTO teams (name) VALUES ('News Team')");

        // Auto-sync status for items published on PT where DP status was pending
        $pdo->exec("
            UPDATE programming_dispatches 
            SET dp_status = 'Published on PT' 
            WHERE (pt_status = 'Published' OR pt_status = 'Published on PT') 
              AND (dp_status = '' OR dp_status IS NULL OR dp_status = 'Pending')
        ");
        $pdo->exec("
            UPDATE newsroom_dispatches 
            SET dp_status = 'Published on PT' 
            WHERE (pt_status = 'Published' OR pt_status = 'Published on PT') 
              AND (dp_status = '' OR dp_status IS NULL OR dp_status = 'Pending')
        ");

        // Seed Programming & Newsroom Staff Rosters non-destructively
        seedProgrammingStaff($pdo);
        seedNewsroomStaff($pdo);

        // Auto-seed default hr_employee_profiles for any existing employees missing a profile without altering data
        $pdo->exec("
            INSERT IGNORE INTO hr_employee_profiles (employee_id, joining_date, employment_type, basic_salary, expected_hours, shift_policy, annual_leave_quota, casual_leave_quota, sick_leave_quota)
            SELECT id, CURDATE(), 'full_time', 0.00, 8.0, 'standard_8h', 14, 10, 8
            FROM employees
            WHERE id NOT IN (SELECT employee_id FROM hr_employee_profiles)
        ");

    } catch (Exception $e) {
        // Ignore if already migrated
    }
}

function ensureDepartmentAndTeamsSchema($pdo) {
    try {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            description TEXT NULL,
            hod_id INT NULL,
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try { $pdo->exec("ALTER TABLE departments ADD COLUMN description TEXT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE departments ADD COLUMN hod_id INT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE departments ADD COLUMN is_active TINYINT DEFAULT 1"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE departments ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            department_id INT NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try { $pdo->exec("ALTER TABLE teams ADD COLUMN department_id INT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE teams ADD COLUMN description TEXT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE teams ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}

        // Allow staff without login accounts
        try { $pdo->exec("ALTER TABLE employees ADD COLUMN can_login TINYINT DEFAULT 1"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE employees MODIFY COLUMN email VARCHAR(191) NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE employees MODIFY COLUMN password_hash VARCHAR(255) NULL"); } catch (Exception $e) {}

        // Link default teams to departments if not linked yet
        $deptMap = $pdo->query("SELECT name, id FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($deptMap)) {
            $digitalId = $deptMap['Digital'] ?? null;
            $newsId = $deptMap['News Room'] ?? null;
            $progId = $deptMap['Programming'] ?? null;
            $othersId = $deptMap['Others'] ?? null;

            if ($digitalId) {
                $pdo->exec("UPDATE teams SET department_id = $digitalId WHERE department_id IS NULL AND name IN ('Admin', 'Facebook Team', 'Image Posting Team', 'YouTube Team', 'Digital NLEs', 'Team YouTube Shorts', 'Internees')");
            }
            if ($newsId) {
                $pdo->exec("UPDATE teams SET department_id = $newsId WHERE department_id IS NULL AND name IN ('Pakistan Today News', 'News Team')");
            }
            if ($progId) {
                $pdo->exec("UPDATE teams SET department_id = $progId WHERE department_id IS NULL AND name IN ('Programming Team', 'Archive Team')");
            }
            if ($othersId) {
                $pdo->exec("UPDATE teams SET department_id = $othersId WHERE department_id IS NULL AND name IN ('Web Developer')");
            }

            // Sync HODs into departments if hod_id is currently NULL
            $hods = $pdo->query("SELECT id, name, department_id FROM employees WHERE role = 'hod' AND department_id IS NOT NULL")->fetchAll();
            foreach ($hods as $h) {
                $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ? AND hod_id IS NULL")->execute([$h['id'], $h['department_id']]);
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
}

function ensureDetailedPayrollSchema($pdo) {
    try {
        // 1. Extend hr_employee_profiles with legal/bank master columns
        $profileCols = [
            "emp_code VARCHAR(50) NULL",
            "father_husband_name VARCHAR(191) NULL",
            "cnic_no VARCHAR(50) NULL",
            "bank_name VARCHAR(100) DEFAULT 'UBL'",
            "bank_account_no VARCHAR(100) NULL",
            "fixed_allowance DECIMAL(10,2) DEFAULT 0.00"
        ];
        foreach ($profileCols as $col) {
            try { $pdo->exec("ALTER TABLE hr_employee_profiles ADD COLUMN {$col}"); } catch (Exception $e) {}
        }

        // 2. Extend hr_payroll with comprehensive calculation & disbursement columns
        $payrollCols = [
            "fuel_allowance DECIMAL(10,2) DEFAULT 0.00",
            "incentive DECIMAL(10,2) DEFAULT 0.00",
            "food_bills DECIMAL(10,2) DEFAULT 0.00",
            "advance_salary DECIMAL(10,2) DEFAULT 0.00",
            "loan_deduction DECIMAL(10,2) DEFAULT 0.00",
            "wht_amount DECIMAL(10,2) DEFAULT 0.00",
            "form_no VARCHAR(50) NULL",
            "increment_remarks TEXT NULL",
            "paid_amount DECIMAL(10,2) DEFAULT 0.00",
            "payable_amount DECIMAL(10,2) DEFAULT 0.00"
        ];
        foreach ($payrollCols as $col) {
            try { $pdo->exec("ALTER TABLE hr_payroll ADD COLUMN {$col}"); } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        // Ignore
    }
}

function seedNewsroomStaff($pdo) {
    try {
        $deptMap = $pdo->query("SELECT name, id FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
        $teamMap = $pdo->query("SELECT name, id FROM teams")->fetchAll(PDO::FETCH_KEY_PAIR);

        $newsDeptId = $deptMap['News Room'] ?? ($deptMap['Digital'] ?? 1);
        $newsTeamId = $teamMap['News Team'] ?? ($teamMap['Admin'] ?? 1);
        $defaultPasswordHash = password_hash('DiscoverPakistan123', PASSWORD_DEFAULT);

        $newsroomRoster = [
            ['Naveed Qaiser', 'hnqrana@gmail.com', 'hod', 'Director News', 'News Room', 'News Team'],
            ['Rashid Yazdani', 'rashid.yazdani@discoverpakistan.tv', 'employee', 'Content Writer', 'News Room', 'News Team'],
            ['Kazim Jaffri', 'kazim.jaffari@gmail.com', 'employee', 'Content Writer', 'News Room', 'News Team'],
            ['Sammad Khan', 'sammadk96@gmail.com', 'team_lead', 'Sr.Producer', 'News Room', 'News Team'],
            ['Hafiz Shahzad Ahmed', 'Shahzadnazir470@gmail.com', 'team_lead', 'Assignment Editor', 'News Room', 'News Team'],
            ['Khurram Khalid', 'kkizhere@gmail.com', 'team_lead', 'Assignment Editor', 'News Room', 'News Team'],
            ['Hussain Naqvi', 'hussaintransmission@gmail.com', 'team_lead', 'Assignment Editor', 'News Room', 'News Team'],
            ['Khurram Shahzad', 'Idealkhuram@gmail.com', 'employee', 'Content Producer', 'News Room', 'News Team'],
            ['Sheraz Khalid', 'az9766599@gmail.com', 'employee', 'Content Producer', 'News Room', 'News Team'],
            ['Asim Mumtaz', 'sherwani.asim@gmail.com', 'employee', 'Associate Producer', 'News Room', 'News Team'],
            ['Ali Zain', 'alizainzafar4@gmail.com', 'employee', 'News Producer', 'News Room', 'News Team'],
            ['Shehrbano', 'Shehrbano29@gmail.com', 'employee', 'News Producer', 'News Room', 'News Team'],
            ['Warisha Abbas', 'warishahamza35@gmail.com', 'employee', 'Anchor', 'News Room', 'News Team'],
            ['Eza Romail', 'ezaromail@gmail.com', 'employee', 'Anchor', 'News Room', 'News Team'],
            ['Talha Amir', 'talhasulehri28@gmail.com', 'employee', 'Anchor', 'News Room', 'News Team'],
            ['Ateeq Malik', 'Ateeqmajeed@gmail.com', 'employee', 'Reporter', 'News Room', 'News Team'],
            ['Hafsa Ali', 'ranahafsa26@gmail.com', 'employee', 'Reporter', 'News Room', 'News Team'],
            ['Shuja Butt', 'alib4711@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Talha Arif', 'talhaarif0@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Muhammad Abbas', 'muhammadabbasjaffery72@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Abdur Rehman', 'peaksrehman@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Faizan Joya', 'faizanjoyia05@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Hassan Raza', 'Hassanraza0032uon@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Umar Muzamil', 'umarmuzammil205@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Rashid Ali', 'Rashidhussain12349@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Rizwan Waseem Khan', 'zeerizwan2020@gmail.com', 'employee', 'Associate Producer', 'News Room', 'News Team'],
            ['Muhammad Khaqan Blaggan', 'khaqan143jutt@gmail.com', 'employee', 'Associate Producer', 'News Room', 'News Team'],
            ['Zulqanain Haider', 'syedzulqarnain05@gmail.com', 'employee', 'Audio Engineer', 'News Room', 'News Team'],
            ['Sued Moeed Ali', 'immoeed7@gmail.com', 'employee', 'Audio Engineer', 'News Room', 'News Team'],
            ['Jabeer Sajid', 'jabeershah14@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Kashif Ali', 'Kashiftahirtv@gmail.com', 'employee', 'NLE (Non-Linear Editor)', 'News Room', 'News Team'],
            ['Maria Bukhari', 'maria4bukhari21@gmail.com', 'employee', 'Content Analyst', 'News Room', 'News Team']
        ];

        $stmtInsert = $pdo->prepare("
            INSERT IGNORE INTO employees (name, email, password_hash, role, designation, department_id, team_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($newsroomRoster as $emp) {
            $name = $emp[0];
            $email = strtolower(trim($emp[1]));
            $role = $emp[2];
            $designation = $emp[3];
            $deptId = $deptMap[$emp[4]] ?? $newsDeptId;
            $teamId = $teamMap[$emp[5]] ?? $newsTeamId;

            $stmtInsert->execute([$name, $email, $defaultPasswordHash, $role, $designation, $deptId, $teamId]);
        }
    } catch (Exception $e) {
        // Ignore errors if already seeded
    }
}

function seedProgrammingStaff($pdo) {
    try {
        $deptMap = $pdo->query("SELECT name, id FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
        $teamMap = $pdo->query("SELECT name, id FROM teams")->fetchAll(PDO::FETCH_KEY_PAIR);

        $progDeptId = $deptMap['Programming'] ?? ($deptMap['Digital'] ?? 1);
        $progTeamId = $teamMap['Programming Team'] ?? ($teamMap['Admin'] ?? 1);
        $defaultPasswordHash = password_hash('DiscoverPakistan123', PASSWORD_DEFAULT);

        $programmingRoster = [
            ['Ghulam Abbas', 'hodshahdp@gmail.com', 'hod', 'HOD Programming', 'Programming', 'Programming Team'],
            ['Zeeshan Butt', 'Meri.emailz@gmail.com', 'team_lead', 'Sr.Producer', 'Programming', 'Programming Team'],
            ['Tabish Noor Khan', 'tabish.discoverpk@gmail.com', 'team_lead', 'Sr.Producer', 'Programming', 'Programming Team'],
            ['Fakhar Zaman', 'fakhar.zaman@discoverpakistan.tv', 'team_lead', 'Sr.Producer', 'Programming', 'Programming Team'],
            ['Iqra Maqsood', 'iqramaqsood009@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Umair Manzoor', 'umairmanzoor@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Usman Mehar', 'Ieospunch@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Khursand Hashim', 'Khursand.hashim62@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Maisha Aslam', 'Maishaaslam91@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Syed Fahad Kamran', 'Fahadkamran1999@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Mueez', 'Ra7361309@gmail.com', 'employee', 'Asst. Producer', 'Programming', 'Programming Team'],
            ['Fahad feroz', 'fahad.ferose@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Waqas Chaudhary', 'waqas.chaudhary@discoverpakistan.tv', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Sameera Latif', 'sameera.latif@discoverpakistan.tv', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Zaheer Sheikh', 'Zhr.sheikh78@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Saba Pasha', 'Sabapasha851@gmail.com', 'employee', 'Program Anchor', 'Programming', 'Programming Team'],
            ['Rameen Mehmood', 'Rameenmehmood5@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Khadija Bhalli', 'khadija.bhalli@discoverpakistan.tv', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Shehroz Anjum', 'Shehroz.anjum1994@gmail.com', 'employee', 'Anchor + Content Writer', 'Programming', 'Programming Team'],
            ['Hafsa Ali', 'hafsa.ali@discoverpakistan.tv', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Warisha Abbass', 'warisha.abbass@discoverpakistan.tv', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Shahan Karachi', 'Shahan.shahid.k@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Naseer Abbasi', 'abbasinaseer056@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Adeel Mirza', 'mirza.adeel.45@gmail.com', 'employee', 'Producer', 'Programming', 'Programming Team'],
            ['Ali Kashif', 'alikashifsultani@gmail.com', 'hod', 'HOD Archive', 'Programming', 'Archive Team'],
            ['Rana Arslan', 'ranaarslan421@gmail.com', 'employee', 'Archive Officer', 'Programming', 'Archive Team'],
            ['Babar Rasool', 'Babarwattoo777@gmail.com', 'employee', 'Archive Officer', 'Programming', 'Archive Team']
        ];

        $stmtInsert = $pdo->prepare("
            INSERT IGNORE INTO employees (name, email, password_hash, role, designation, department_id, team_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($programmingRoster as $emp) {
            $name = $emp[0];
            $email = strtolower(trim($emp[1]));
            $role = $emp[2];
            $designation = $emp[3];
            $deptId = $deptMap[$emp[4]] ?? $progDeptId;
            $teamId = $teamMap[$emp[5]] ?? $progTeamId;

            $stmtInsert->execute([$name, $email, $defaultPasswordHash, $role, $designation, $deptId, $teamId]);
        }
    } catch (Exception $e) {
        // Ignore errors if already seeded
    }
}

function initDatabaseSchema($pdo) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        is_active TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS content_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        icon VARCHAR(50) DEFAULT 'file-text'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(191) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'employee',
        designation VARCHAR(150) DEFAULT 'Member',
        department_id INT,
        team_id INT,
        avatar VARCHAR(255),
        can_assign_tasks TINYINT DEFAULT 0,
        can_edit_tasks TINYINT DEFAULT 0,
        can_unlock_sheets TINYINT DEFAULT 0,
        can_inspect_sheets TINYINT DEFAULT 0,
        can_view_reports TINYINT DEFAULT 0,
        can_view_attendance TINYINT DEFAULT 0,
        can_manage_employees TINYINT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS daily_sheets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        sheet_date DATE NOT NULL,
        check_in_time VARCHAR(50),
        check_out_time VARCHAR(50),
        total_duty_hours VARCHAR(50),
        total_duty_seconds INT DEFAULT 0,
        remarks TEXT,
        work_summary TEXT,
        is_locked TINYINT DEFAULT 0,
        locked_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_emp_date (employee_id, sheet_date),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS sheet_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sheet_id INT NOT NULL,
        time_slot VARCHAR(100) NOT NULL,
        content_type VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        link VARCHAR(255),
        title TEXT NOT NULL,
        count_val INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sheet_id) REFERENCES daily_sheets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assigned_by INT NOT NULL,
        assigned_to INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        link TEXT,
        content_type VARCHAR(100),
        department VARCHAR(100),
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        due_date DATE,
        completed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_by) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function seedInitialData($pdo) {
    // 1. Departments
    $departments = ['News Room', 'Digital', 'Programming', 'Documentary', 'Others'];
    $stmtDept = $pdo->prepare("INSERT IGNORE INTO departments (name) VALUES (?)");
    foreach ($departments as $dept) {
        $stmtDept->execute([$dept]);
    }

    // 2. Teams
    $teams = [
        'Admin',
        'Facebook Team',
        'Image Posting Team',
        'YouTube Team',
        'Digital NLEs',
        'Team YouTube Shorts',
        'Pakistan Today News',
        'Internees',
        'Web Developer'
    ];
    $stmtTeam = $pdo->prepare("INSERT IGNORE INTO teams (name) VALUES (?)");
    foreach ($teams as $team) {
        $stmtTeam->execute([$team]);
    }

    // 3. Content Types
    $contentTypes = ['Reel', 'YT Videos', 'Post Card', 'FB Videos', 'Podcast'];
    $stmtContent = $pdo->prepare("INSERT IGNORE INTO content_types (name) VALUES (?)");
    foreach ($contentTypes as $type) {
        $stmtContent->execute([$type]);
    }

    // Lookup maps
    $deptMap = $pdo->query("SELECT name, id FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
    $teamMap = $pdo->query("SELECT name, id FROM teams")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Default password hash for DiscoverPakistan123
    $defaultPasswordHash = password_hash('DiscoverPakistan123', PASSWORD_DEFAULT);

    // 4. Exact 23 Team Members with real Emails from user prompt
    $roster = [
        ['Muhammad Zeeshan', 'zeeeguest@gmail.com', 'admin', 'Admin Manager', 'Digital', 'Admin', [0, 0, 0, 0]],
        ['Zahra Kazmi', 'kazmizahra96@gmail.com', 'employee', 'Social Lead', 'Digital', 'Facebook Team', [71, 0, 0, 38]],
        ['Aroosa Tayyab', 'aroosatayyab2004@gmail.com', 'employee', 'Content Creator', 'Digital', 'Facebook Team', [5, 1, 0, 38]],
        ['Zara Farooqi', 'zk1596032@gmail.com', 'employee', 'Video Editor', 'Digital', 'Facebook Team', [42, 0, 0, 63]],
        ['Malik Sultan', 'maliksultanawan018@gmail.com', 'employee', 'Senior SME', 'Digital', 'Facebook Team', [24, 0, 123, 6]],
        ['Syed Shahid', 'shahidsyed991@gmail.com', 'employee', 'Content Strategist', 'Digital', 'Facebook Team', [73, 0, 4, 50]],
        ['M. Sajid Khan', 'muhammadsajidk574@gmail.com', 'employee', 'Graphic Specialist', 'Digital', 'Image Posting Team', [0, 0, 138, 0]],
        ['Jawad Malik', 'malikprince28293@gmail.com', 'employee', 'Visual Designer', 'Digital', 'Image Posting Team', [0, 0, 138, 0]],
        ['Ali Haider', 'canvadiscover@gmail.com', 'employee', 'Image Editor', 'Digital', 'Image Posting Team', [0, 0, 74, 0]],
        ['Afrasyab Rashid', 'afrasyabrashid@gmail.com', 'employee', 'YouTube Lead', 'Digital', 'YouTube Team', [2, 18, 0, 22]],
        ['Ragheeba Ahsan', 'areebasaif08@gmail.com', 'employee', 'YouTube Editor', 'Digital', 'YouTube Team', [0, 19, 0, 14]],
        ['Muhammad Adnan', 'adnanansari2307@gmail.com', 'employee', 'Video Specialist', 'Digital', 'YouTube Team', [0, 24, 0, 1]],
        ['Babar Shahzad', 'babarshahzad9052@gmail.com', 'employee', 'Digital NLE Lead', 'Digital', 'Digital NLEs', [69, 0, 0, 2]],
        ['Waqas Anjum', 'vickyvickyjutt88@gmail.com', 'employee', 'Video Editor', 'Digital', 'Digital NLEs', [0, 0, 0, 0]],
        ['Muhammad Waqas', 'wakas.mughal91@gmail.com', 'employee', 'Video Editor', 'Digital', 'Digital NLEs', [14, 15, 1, 0]],
        ['Asif Nazir', 'asifnazir247@gmail.com', 'employee', 'Shorts Creator', 'Digital', 'Team YouTube Shorts', [16, 0, 0, 47]],
        ['Abaid', 'abaid@discoverpakistan.tv', 'employee', 'SME', 'Digital', 'Team YouTube Shorts', [0, 0, 25, 0]],
        ['Umer Saddiq', 'umarsadiq121560@gmail.com', 'employee', 'News Sub-Editor', 'News Room', 'Pakistan Today News', [0, 3, 19, 66]],
        ['Ali Arshad', 'ali029749@gmail.com', 'employee', 'News Associate', 'News Room', 'Pakistan Today News', [0, 0, 46, 69]],
        ['Umar Zaheer', 'abdulshaffayumer@gmail.com', 'employee', 'News Desk', 'News Room', 'Pakistan Today News', [0, 0, 18, 72]],
        ['Hamid Alvi', 'allnewsofficial009@gmail.com', 'employee', 'Internee Designer', 'Digital', 'Internees', [0, 0, 65, 0]],
        ['Armaan', 'maniali1096@gmail.com', 'employee', 'Internee Creator', 'Digital', 'Internees', [32, 0, 0, 0]],
        ['Ali Ahmad', 'aliahmad.as1182@gmail.com', 'employee', 'Web Developer', 'Others', 'Web Developer', [0, 0, 0, 0]]
    ];

    $stmtEmp = $pdo->prepare("INSERT IGNORE INTO employees (id, name, email, password_hash, role, designation, department_id, team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $today = date('Y-m-d');
    $empId = 1;

    foreach ($roster as $emp) {
        $deptId = $deptMap[$emp[4]] ?? 2;
        $teamId = $teamMap[$emp[5]] ?? 1;

        $stmtEmp->execute([$empId, $emp[0], $emp[1], $defaultPasswordHash, $emp[2], $emp[3], $deptId, $teamId]);
        $empId++;
    }

    // Seed initial operational tasks
    $stmtTask = $pdo->prepare("INSERT INTO tasks (assigned_by, assigned_to, title, description, content_type, department, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtTask->execute([1, 2, 'Schedule 15 News Reels for Evening', 'Target prime-time distribution 6 PM - 10 PM', 'Reel', 'Digital', 'urgent', 'pending', $today]);
    $stmtTask->execute([1, 5, 'Create 25 Infographic Post Cards', 'Daily target for social cards with statistics', 'Post Card', 'Digital', 'high', 'in_progress', $today]);
    $stmtTask->execute([1, 17, 'Review Afternoon Shorts Queue', 'Verify audio sync and tags on TikTok clips', 'FB Videos', 'Digital', 'medium', 'pending', $today]);
    $stmtTask->execute([1, 10, 'Edit Prime-Time YouTube Episode', 'Final color grade and audio mix for 8 PM premiere', 'YT Videos', 'Digital', 'urgent', 'in_progress', $today]);
}
