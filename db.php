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
    try {
        // Try to connect to the specific database directly (Best for shared hosting/cPanel)
        $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // If database doesn't exist, connect without dbname and create it (Best for local XAMPP/VPS)
        if ($e->getCode() == 1049) { // 1049 is "Unknown database"
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
            $pdo->exec("USE `$db_name`;");
        } else {
            throw $e; // Re-throw if it's an authentication or other error
        }
    }

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

        CREATE TABLE IF NOT EXISTS `master_sims` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sim_number` VARCHAR(255) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS `master_devices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(50) DEFAULT 'Phone',
            `is_permanent` TINYINT(1) DEFAULT 0,
            `permanent_user` VARCHAR(255) DEFAULT NULL,
            `sim_no` VARCHAR(255) DEFAULT NULL,
            `last_assigned_to` VARCHAR(255) DEFAULT NULL,
            `last_assigned_date` VARCHAR(255) DEFAULT NULL
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

    // 5. SMTP Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        smtp_host VARCHAR(255) NOT NULL DEFAULT '',
        smtp_port INT NOT NULL DEFAULT 587,
        smtp_user VARCHAR(255) NOT NULL DEFAULT '',
        smtp_pass VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default SMTP row if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM smtp_settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO smtp_settings (smtp_host, smtp_port, smtp_user, smtp_pass) VALUES ('', 587, '', '')");
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

    $stmt = $pdo->query("SELECT name, permanent_user, sim_no, category FROM master_devices WHERE is_permanent = 1");
    $perm_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($perm_devices as $dev) {
        $dev_name = $dev['name'];
        $perm_user = $dev['permanent_user'];
        $sim = $dev['sim_no'];
        $dev_type = $dev['category'];

        if (empty($perm_user)) {
            continue;
        }

        $check_stmt = $pdo->prepare("SELECT id FROM devices WHERE LOWER(device_name) = LOWER(?) AND assigned_date LIKE ?");
        $check_stmt->execute([$dev_name, $today_date . '%']);
        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

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

function sendWelcomeEmail($toEmail, $name, $username, $password) {
    if (empty($toEmail)) return false;
    
    // Auto-detect login URL based on current server setup
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $loginUrl = $protocol . "://" . $host . $uri . "/login.php";

    $subject = "Welcome to TechVault - Your Account Details";
    
    // Modern HTML Email Template
    $message = "
    <html>
    <head>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background-color: #1e293b; padding: 30px; border-radius: 10px; border: 1px solid #334155; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #334155; padding-bottom: 20px; }
            .header h1 { color: #0ea5e9; margin: 0; }
            .content { line-height: 1.6; }
            .details { background-color: #0f172a; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #334155; }
            .details p { margin: 10px 0; font-size: 1.1em; }
            .btn { display: inline-block; padding: 12px 25px; background-color: #0ea5e9; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }
            .footer { margin-top: 30px; text-align: center; font-size: 0.9em; color: #94a3b8; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to TechVault</h1>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>An account has been created for you in the TechVault system. Below are your login credentials:</p>
                
                <div class='details'>
                    <p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                    <p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>
                </div>
                
                <p>Please log in and change your password as soon as possible.</p>
                
                <div style='text-align: center;'>
                    <a href='" . htmlspecialchars($loginUrl) . "' class='btn'>Log In Now</a>
                </div>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Fetch SMTP settings
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM smtp_settings LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtp && !empty($smtp['smtp_host']) && !empty($smtp['smtp_user']) && !empty($smtp['smtp_pass'])) {
        require_once __DIR__ . '/libs/SimpleSMTP.php';
        $mailer = new SimpleSMTP($smtp['smtp_host'], $smtp['smtp_port'], $smtp['smtp_user'], $smtp['smtp_pass']);
        return $mailer->send($toEmail, $subject, $message, $smtp['smtp_user'], "TechVault Admin");
    }

    // Fallback to native mail if SMTP is not configured
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: TechVault Admin <admin@localhost>" . "\r\n";
    return @mail($toEmail, $subject, $message, $headers);
}
?>
