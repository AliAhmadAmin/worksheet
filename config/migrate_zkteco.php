<?php
/**
 * ZKTeco Biometric Database Migration
 */
require_once __DIR__ . '/database.php';

try {
    $pdo = getDbConnection();

    // 1. Devices Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zkteco_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_name VARCHAR(100) NOT NULL DEFAULT 'Main Office ZKTeco',
            serial_number VARCHAR(100) UNIQUE NULL,
            ip_address VARCHAR(45) NULL,
            port INT NOT NULL DEFAULT 4370,
            comm_key INT NOT NULL DEFAULT 0,
            device_model VARCHAR(50) NULL,
            firmware_version VARCHAR(50) NULL,
            user_count INT NOT NULL DEFAULT 0,
            finger_count INT NOT NULL DEFAULT 0,
            log_count INT NOT NULL DEFAULT 0,
            status ENUM('online', 'offline') NOT NULL DEFAULT 'offline',
            last_activity DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sn (serial_number),
            INDEX idx_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Attendance Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zkteco_attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_sn VARCHAR(100) NULL,
            device_user_id VARCHAR(50) NOT NULL COMMENT 'PIN / User ID on Machine',
            employee_id INT NULL,
            punch_time DATETIME NOT NULL,
            punch_date DATE NOT NULL,
            punch_state VARCHAR(20) NOT NULL DEFAULT 'auto' COMMENT '0: CheckIn, 1: CheckOut, 2: BreakOut, 3: BreakIn, 4: OT-In, 5: OT-Out, auto',
            verify_type VARCHAR(20) NOT NULL DEFAULT 'fingerprint' COMMENT 'fingerprint, face, card, password, manual',
            sheet_id INT NULL,
            status ENUM('applied', 'unmatched', 'ignored', 'duplicate') NOT NULL DEFAULT 'applied',
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (device_user_id, punch_time),
            INDEX idx_emp_date (employee_id, punch_date),
            INDEX idx_sheet (sheet_id),
            INDEX idx_sn (device_sn),
            INDEX idx_punch_time (punch_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. ZKTeco Settings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zkteco_settings (
            id INT PRIMARY KEY DEFAULT 1,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            auto_apply_sheet TINYINT(1) NOT NULL DEFAULT 1,
            check_in_mode ENUM('smart', 'first_punch', 'punch_state') NOT NULL DEFAULT 'smart',
            check_out_mode ENUM('smart', 'last_punch', 'punch_state') NOT NULL DEFAULT 'smart',
            server_port INT NOT NULL DEFAULT 80,
            timezone_offset INT NOT NULL DEFAULT 5,
            push_interval_sec INT NOT NULL DEFAULT 10,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert default settings if not exists
    $pdo->exec("
        INSERT IGNORE INTO zkteco_settings (id, is_enabled, auto_apply_sheet, check_in_mode, check_out_mode, server_port, timezone_offset, push_interval_sec)
        VALUES (1, 1, 1, 'smart', 'smart', 80, 5, 10);
    ");

    // Insert default device if empty
    $chk = $pdo->query("SELECT COUNT(*) FROM zkteco_devices")->fetchColumn();
    if ($chk == 0) {
        $pdo->exec("
            INSERT INTO zkteco_devices (device_name, ip_address, port, comm_key, status)
            VALUES ('Main Office Biometric', '192.168.1.201', 4370, 0, 'offline')
        ");
    }

    echo "ZKTeco database schema migrated successfully.\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
