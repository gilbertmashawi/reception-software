<?php
require_once 'config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    exit('Access denied');
}

$db = getDB();

// Get filter parameters
$filterType = $_GET['type'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterUser = $_GET['user'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$query = "SELECT al.*, u.username, u.full_name FROM activity_log al 
          LEFT JOIN users u ON al.user_id = u.id 
          WHERE 1=1";
$params = [];
$types = '';

if ($filterType) {
    $query .= " AND al.activity_type = ?";
    $params[] = $filterType;
    $types .= 's';
}

if ($filterDate) {
    $query .= " AND al.activity_date = ?";
    $params[] = $filterDate;
    $types .= 's';
}

if ($filterUser) {
    $query .= " AND al.user_id = ?";
    $params[] = $filterUser;
    $types .= 'i';
}

if ($searchQuery) {
    $query .= " AND (al.description LIKE ? OR al.details LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'ssss';
}

$query .= " ORDER BY al.created_at DESC";

// Prepare statement
$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=activity_logs_' . date('Y-m-d_H-i-s') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Write headers
fputcsv($output, ['ID', 'Date', 'Time', 'Activity Type', 'Description', 'Details', 'User', 'Username', 'Created At']);

// Write data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['activity_date'],
        $row['activity_time'],
        $row['activity_type'],
        $row['description'],
        $row['details'] ?? '',
        $row['full_name'] ?? 'System',
        $row['username'] ?? '',
        $row['created_at']
    ]);
}

fclose($output);
exit;