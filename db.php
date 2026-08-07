<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("Location: login.php");
    exit();
}

// Live Database Credentials (MySQL)
$host = 'localhost';
$db_name = 'phone_inventory';
$username = 'root'; // default XAMPP user
$password = ''; // default XAMPP password is empty

try {
    // 1. Connect without database to create it if it doesn't exist
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create the database automatically
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");

    // 3. Select the database
    $pdo->exec("USE `$db_name`;");

    // 4. Create the required tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) DEFAULT 'user'
        );

        CREATE TABLE IF NOT EXISTS `master_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `password` VARCHAR(255) DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS `master_devices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `is_permanent` TINYINT(1) DEFAULT 0,
            `permanent_user` VARCHAR(255) DEFAULT NULL,
            `sim_no` VARCHAR(255) DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS `devices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `device_name` VARCHAR(255) NOT NULL,
            `device_type` VARCHAR(255) NOT NULL,
            `user_name` VARCHAR(255) NOT NULL,
            `sim_no` VARCHAR(255) DEFAULT NULL,
            `assigned_date` VARCHAR(255) NOT NULL,
            `status` VARCHAR(50) DEFAULT 'Issued',
            `system_ip` VARCHAR(255) DEFAULT NULL
        );
    ");

    // 5. Ensure the default admin user exists
    $adminCount = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `username` = 'admin'")->fetchColumn();
    if ($adminCount == 0) {
        $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO `users` (`username`, `password`, `role`) VALUES ('admin', '$defaultPassword', 'admin')");
    }

} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_array = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_array[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function log_daily_history($action, $device_name, $device_type, $user_name, $sim_no, $status = 'Issued') {
    $backup_folder = __DIR__ . '/backups';
    if (!file_exists($backup_folder)) {
        mkdir($backup_folder, 0777, true);
    }

    $today_date_str = date('d-m-Y');
    $timestamp_str = date('Y-m-d H:i');
    $file_path = $backup_folder . '/history_' . $today_date_str . '.csv';
    $file_exists = file_exists($file_path);
    $user_ip = get_client_ip();

    $file = fopen($file_path, 'a');
    if ($file) {
        if (!$file_exists) {
            fputcsv($file, ['Timestamp', 'User IP', 'Action', 'Device Name', 'Type', 'User Name', 'SIM No', 'Status']);
        }
        fputcsv($file, [
            $timestamp_str,
            $user_ip,
            $action,
            $device_name,
            $device_type,
            $user_name,
            $sim_no ?: '',
            $status
        ]);
        fclose($file);
    }
}

function auto_log_permanent_devices($pdo) {
    $today_date = date('d-m-Y');
    $now_time = date('d-m-Y h:i A');

    $stmt = $pdo->query("SELECT name, permanent_user, sim_no FROM master_devices WHERE is_permanent = 1");
    $perm_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($perm_devices as $dev) {
        $dev_name = $dev['name'];
        $perm_user = $dev['permanent_user'];
        $sim = $dev['sim_no'];

        if (empty($perm_user)) {
            continue;
        }

        $check_stmt = $pdo->prepare("SELECT id FROM devices WHERE LOWER(device_name) = LOWER(?) AND assigned_date LIKE ?");
        $check_stmt->execute([$dev_name, $today_date . '%']);
        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

        $dev_name_lower = strtolower($dev_name);
        $dev_type = (strpos($dev_name_lower, 'iphone') !== false || strpos($dev_name_lower, 'iphon') !== false || strpos($dev_name_lower, '7 plus') !== false) ? 'iPhone' : 'Android';

        if ($exists) {
            $update_stmt = $pdo->prepare("UPDATE devices SET user_name = ?, sim_no = ?, status = 'Permanent' WHERE id = ?");
            $update_stmt->execute([$perm_user, $sim ?: 'No SIM', $exists['id']]);
        } else {
            $insert_stmt = $pdo->prepare("INSERT INTO devices (device_name, device_type, user_name, sim_no, assigned_date, status, system_ip) VALUES (?, ?, ?, ?, ?, 'Permanent', 'System Auto')");
            $insert_stmt->execute([$dev_name, $dev_type, $perm_user, $sim ?: 'No SIM', $now_time]);

            log_daily_history(
                'AUTO_PERMANENT',
                $dev_name,
                $dev_type,
                $perm_user,
                $sim ?: '',
                'Permanent'
            );
        }
    }
}
?>
