# TechVault - Project Documentation

## 1. Project Overview
TechVault is a localized inventory management web application tailored for IT departments, QA labs, and testing floors. It bridges the gap between hardware tracking and employee allocations, minimizing device loss and maximizing resource availability.

## 2. System Architecture
The application is built on a standard LAMP/WAMP stack:
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla), Bootstrap 5, FontAwesome 6.
- **Backend:** PHP 8+ (Procedural and OOP mixed for utility classes).
- **Database:** MySQL / MariaDB via PDO.
- **Mail Engine:** Native `SimpleSMTP.php` class utilizing PHP sockets (no `PHPMailer` or OS `sendmail` dependency).

## 3. Database Schema
TechVault utilizes an auto-migrating database structure. The schema is defined in `db.php` and contains the following primary entities:

### `users`
System administrators and standard users.
- `id` (INT, PK)
- `username` (VARCHAR)
- `password` (VARCHAR, Hashed)
- `role` (VARCHAR)

### `master_users`
Registered employees eligible to receive devices.
- `id` (INT, PK)
- `name` (VARCHAR)
- `email` (VARCHAR)
- `password` (VARCHAR)

### `master_devices`
The catalog of physical hardware.
- `id` (INT, PK)
- `name` (VARCHAR)
- `category` (VARCHAR)
- `is_permanent` (TINYINT)
- `permanent_user` (VARCHAR)

### `devices`
The allocation transaction history.
- `id` (INT, PK)
- `device_name` (VARCHAR)
- `device_type` (VARCHAR)
- `user_name` (VARCHAR)
- `assigned_date` (VARCHAR)
- `status` (VARCHAR: Issued, Returned, Permanent)

### `smtp_settings`
Encrypted/Stored email configuration for the system.
- `id` (INT, PK)
- `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass` (VARCHAR)

## 4. Security Measures
- **SQL Injection Prevention:** All queries use PHP Data Objects (PDO) with prepared statements.
- **Authentication:** Sessions are strictly validated on every secure page.
- **Password Hashing:** `password_hash()` (Bcrypt) is used for all stored passwords.

## 5. Future Roadmap
- Implementation of Barcode/QR Code scanning for quick assignments.
- Real-time websocket updates for the Live Dashboard.
- Export to PDF functionality for monthly audits.
