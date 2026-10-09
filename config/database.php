<?php
/**
 * Database Configuration and Auto-Bootstrapper for MySQL
 * Uses environment variables (.env) with secure fallbacks.
 */

// Load .env if present
if (file_exists(__DIR__ . '/../.env')) {
    $envLines = @file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($envKey, $envVal) = explode('=', $line, 2);
            $envKey = trim($envKey);
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($envKey, $_SERVER) && !array_key_exists($envKey, $_ENV)) {
                putenv("{$envKey}={$envVal}");
                $_ENV[$envKey] = $envVal;
                $_SERVER[$envKey] = $envVal;
            }
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'worksheet');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
date_default_timezone_set('Asia/Karachi');

// Configure persistent session lifetime with reverse-proxy & ngrok support
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 2592000); // 30 days
    ini_set('session.cookie_lifetime', 2592000); // 30 days
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 2592000,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => $isHttps ? 'None' : 'Lax'
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
                migratePermissionsSchema($pdo);
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
    static $migrated = false;
    if ($migrated) return;
    $migrated = true;

    try {
        // Change role column to VARCHAR(50) if needed to support custom roles
        $pdo->exec("ALTER TABLE employees MODIFY COLUMN role VARCHAR(50) DEFAULT 'employee'");
        try { $pdo->exec("ALTER TABLE employees MODIFY COLUMN email VARCHAR(191) NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE employees MODIFY COLUMN password_hash VARCHAR(255) NULL"); } catch (Exception $e) {}

        // Add granular permission flags and active status if not exists
        $columns = [
            'is_active' => "TINYINT DEFAULT 1",
            'can_login' => "TINYINT DEFAULT 1",
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

        try {
            $pdo->exec("UPDATE employees SET is_active = 1 WHERE is_active IS NULL");
            $pdo->exec("UPDATE employees SET can_login = 1 WHERE can_login IS NULL");
        } catch (Exception $e) {}

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

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS hr_claims (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            claim_type VARCHAR(50) DEFAULT 'fuel',
            amount DECIMAL(10,2) NOT NULL,
            claim_date DATE NOT NULL,
            salary_month VARCHAR(7) NOT NULL,
            receipt_no VARCHAR(100) NULL,
            reason TEXT NULL,
            description TEXT NULL,
            route_details TEXT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            admin_notes TEXT NULL,
            action_by INT NULL,
            action_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (action_by) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $pdo->exec("ALTER TABLE hr_payroll ADD COLUMN fines DECIMAL(10,2) DEFAULT 0.00");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE hr_payroll ADD COLUMN fine_reason TEXT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE hr_payroll ADD COLUMN unpaid_leave_deduction DECIMAL(10,2) DEFAULT 0.00");
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

        // Auto-seed default hr_employee_profiles for any existing employees missing a profile without altering data
        $pdo->exec("
            INSERT IGNORE INTO hr_employee_profiles (employee_id, joining_date, employment_type, basic_salary, expected_hours, shift_policy, annual_leave_quota, casual_leave_quota, sick_leave_quota)
            SELECT id, CURDATE(), 'full_time', 0.00, 8.0, 'standard_8h', 14, 10, 8
            FROM employees
            WHERE id NOT IN (SELECT employee_id FROM hr_employee_profiles)
        ");

        // WhatsApp Attendance Integration Schema
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_attendance_settings (
            id INT PRIMARY KEY,
            api_url VARCHAR(255) DEFAULT 'https://app.orbitsend.com/api/send/whatsapp',
            api_secret VARCHAR(255) NULL,
            unique_id VARCHAR(255) NULL,
            group_jid VARCHAR(255) NULL,
            webhook_token VARCHAR(255) NULL,
            ai_provider VARCHAR(50) DEFAULT 'auto',
            ai_api_key VARCHAR(255) NULL,
            is_enabled TINYINT DEFAULT 1,
            auto_reply TINYINT DEFAULT 0,
            default_shift_start VARCHAR(50) DEFAULT '09:00 AM',
            default_shift_end VARCHAR(50) DEFAULT '06:00 PM',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try { $pdo->exec("ALTER TABLE whatsapp_attendance_settings ADD COLUMN api_url VARCHAR(255) DEFAULT 'https://app.orbitsend.com/api/send/whatsapp'"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE whatsapp_attendance_settings ADD COLUMN ai_provider VARCHAR(50) DEFAULT 'auto'"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE whatsapp_attendance_settings ADD COLUMN ai_api_key VARCHAR(255) NULL"); } catch (Exception $e) {}

        // Seed default settings row if not present
        $defaultApiUrl = getenv('ORBITSEND_API_URL') ?: 'https://app.orbitsend.com/api/send/whatsapp';
        $defaultSecret = getenv('ORBITSEND_SECRET') ?: '';
        $defaultWebhook = getenv('ORBITSEND_WEBHOOK_SECRET') ?: '';
        $defaultUnique = getenv('ORBITSEND_ACCOUNT') ?: '';

        $stmtSeed = $pdo->prepare("
            INSERT INTO whatsapp_attendance_settings 
                (id, api_url, api_secret, webhook_token, unique_id, is_enabled, auto_reply) 
            VALUES 
                (1, ?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                api_url = COALESCE(NULLIF(api_url, ''), VALUES(api_url)),
                api_secret = COALESCE(NULLIF(api_secret, ''), VALUES(api_secret)),
                webhook_token = COALESCE(NULLIF(webhook_token, ''), VALUES(webhook_token)),
                unique_id = COALESCE(NULLIF(unique_id, ''), VALUES(unique_id))
        ");
        $stmtSeed->execute([$defaultApiUrl, $defaultSecret, $defaultWebhook, $defaultUnique]);

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(255) NULL,
            sender_phone VARCHAR(100) NULL,
            sender_name VARCHAR(150) NULL,
            group_jid VARCHAR(255) NULL,
            employee_id INT NULL,
            raw_message TEXT NULL,
            parsed_action ENUM('check_in', 'check_out', 'leaving_early', 'short_leave', 'unknown') DEFAULT 'unknown',
            extracted_time VARCHAR(50) NULL,
            action_date DATE NOT NULL,
            sheet_id INT NULL,
            status ENUM('applied', 'ignored', 'unmatched', 'duplicate') DEFAULT 'applied',
            remarks TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
            FOREIGN KEY (sheet_id) REFERENCES daily_sheets(id) ON DELETE SET NULL,
            INDEX idx_phone (sender_phone),
            INDEX idx_date (action_date),
            INDEX idx_emp (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try { $pdo->exec("ALTER TABLE hr_employee_profiles ADD COLUMN whatsapp_number VARCHAR(50) NULL AFTER phone"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE employees ADD COLUMN whatsapp_number VARCHAR(50) NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE employees ADD COLUMN phone VARCHAR(50) NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE employees ADD COLUMN emp_code VARCHAR(50) NULL"); } catch (Exception $e) {}

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

function initDatabaseSchema($pdo) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        description TEXT NULL,
        hod_id INT NULL,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        department_id INT NULL,
        description TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        email VARCHAR(191) UNIQUE NULL,
        password_hash VARCHAR(255) NULL,
        role VARCHAR(50) DEFAULT 'employee',
        designation VARCHAR(150) DEFAULT 'Staff',
        emp_code VARCHAR(50) NULL,
        phone VARCHAR(50) NULL,
        whatsapp_number VARCHAR(50) NULL,
        department_id INT NULL,
        team_id INT NULL,
        avatar VARCHAR(255) NULL,
        can_login TINYINT DEFAULT 1,
        can_assign_tasks TINYINT DEFAULT 0,
        can_edit_tasks TINYINT DEFAULT 0,
        can_unlock_sheets TINYINT DEFAULT 0,
        can_inspect_sheets TINYINT DEFAULT 0,
        can_view_reports TINYINT DEFAULT 0,
        can_view_attendance TINYINT DEFAULT 0,
        can_manage_employees TINYINT DEFAULT 0,
        can_manage_hr TINYINT DEFAULT 0,
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
    // 1. Default Departments
    $departments = ['News Room', 'Digital', 'Programming', 'Archive', 'HR', 'Management', 'Others'];
    $stmtDept = $pdo->prepare("INSERT IGNORE INTO departments (name) VALUES (?)");
    foreach ($departments as $dept) {
        $stmtDept->execute([$dept]);
    }

    // 2. Default Content Types
    $contentTypes = ['Reel', 'YT Videos', 'Post Card', 'FB Videos', 'Podcast', 'Documentary', 'News Story'];
    $stmtContent = $pdo->prepare("INSERT IGNORE INTO content_types (name) VALUES (?)");
    foreach ($contentTypes as $type) {
        $stmtContent->execute([$type]);
    }

    // 3. Default Super Admin account if no employees exist in DB
    $empCount = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    if ($empCount === 0) {
        $adminPassHash = password_hash('Admin@12345', PASSWORD_DEFAULT);
        $stmtAdmin = $pdo->prepare("
            INSERT INTO employees (name, email, password_hash, role, designation, can_login, can_assign_tasks, can_edit_tasks, can_unlock_sheets, can_inspect_sheets, can_view_reports, can_view_attendance, can_manage_employees, can_manage_hr, is_active)
            VALUES (?, ?, ?, 'super_admin', 'Administrator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)
        ");
        $stmtAdmin->execute(['System Administrator', 'admin@discoverpakistan.tv', $adminPassHash]);
    }
}
