<?php
session_start();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['is_admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$date_ymd = $_GET['date'] ?? date('Y-m-d');
$date_dmy = date('d-m-Y', strtotime($date_ymd));

$stmt = $pdo->prepare("SELECT assigned_date, device_name, user_name, sim_no, status FROM devices WHERE assigned_date LIKE ? ORDER BY id DESC");
$stmt->execute([$date_dmy . '%']);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "Device_Logs_" . $date_ymd . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Add UTF-8 BOM so Excel reads it properly
fputs($output, "\xEF\xBB\xBF");

// Header Row
fputcsv($output, ['Time', 'Date', 'Device Name', 'Assigned User', 'SIM No', 'Status']);

// Data Rows
foreach ($records as $row) {
    fputcsv($output, [
        substr($row['assigned_date'], 11), // Time
        substr($row['assigned_date'], 0, 10), // Date
        $row['device_name'],
        $row['user_name'],
        $row['sim_no'],
        $row['status']
    ]);
}

fclose($output);
exit();
