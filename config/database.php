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
define('DB_PASS', 'Ahmad@@**786Ali');

date_default_timezone_set('Asia/Karachi');

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $passwordsToTry = [DB_PASS, '', 'root'];
    // Deduplicate passwords to avoid redundant attempts
    $passwordsToTry = array_unique($passwordsToTry);
    $lastException = null;

    foreach ($passwordsToTry as $pass) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            if ($pdo) {
                break;
            }
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    if (!$pdo) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'MySQL Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')]);
        exit;
    }

    try {
        // Ensure database exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Check if tables exist
        $check = $pdo->query("SHOW TABLES LIKE 'employees'")->fetch();
        if (!$check) {
            initDatabaseSchema($pdo);
            seedInitialData($pdo);
        } else {
            migratePermissionsSchema($pdo);
        }

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'MySQL Database initialization failed: ' . $e->getMessage()]);
        exit;
    }
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
            'can_manage_employees' => "TINYINT DEFAULT 0"
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
                can_manage_employees = 1 
            WHERE role = 'admin'
        ");
    } catch (Exception $e) {
        // Ignore if already migrated
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
