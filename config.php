<?php
// config.php - Database configuration and common functions

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'linkspot_db');
define('SITE_NAME', 'LinkSpot Management System');
define('SITE_THEME_COLOR', '#27ae60'); // Green theme
define('SITE_SECONDARY_COLOR', '#2c3e50'); // Dark blue

// Create database connection
function getDB() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $conn;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role' => $_SESSION['role'] ?? 'reception'
        ];
    }
    return null;
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// Add activity log
function addActivityLog($type, $description, $details = null) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_log (activity_date, activity_time, activity_type, description, details, user_id) VALUES (CURDATE(), CURTIME(), ?, ?, ?, ?)");
    $stmt->bind_param("sssi", $type, $description, $details, $user['id']);
    return $stmt->execute();
}

// Send notification
function sendNotification($type, $title, $message, $recipientType = 'all', $recipientId = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (notification_type, title, message, recipient_type, recipient_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $type, $title, $message, $recipientType, $recipientId);
    return $stmt->execute();
}

// Get unread notifications count
function getUnreadNotificationsCount() {
    $user = getCurrentUser();
    if (!$user) return 0;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE (recipient_type = 'all' OR (recipient_type = 'user' AND recipient_id = ?)) AND is_read = 0");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get current date/time in MySQL format
function now() {
    return date('Y-m-d H:i:s');
}

// Validate and sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Get available station addresses
function getAvailableStationAddresses() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM linkspot_station_addresses WHERE status = 'available' ORDER BY station_code, desk_number");
    $stmt->execute();
    $result = $stmt->get_result();
    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
    return $addresses;
}

// Get available shops
function getAvailableShops() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM summarcity_shops WHERE status = 'available' ORDER BY shop_number");
    $stmt->execute();
    $result = $stmt->get_result();
    $shops = [];
    while ($row = $result->fetch_assoc()) {
        $shops[] = $row;
    }
    return $shops;
}

// Get occupied spaces with remaining time
function getOccupiedSpacesWithRemainingTime() {
    $db = getDB();
    $now = now();
    
    // Get voucher sales with remaining time
    $query = "SELECT vs.*, CONCAT(lsa.station_code, lsa.desk_number) as station, 
                     TIMESTAMPDIFF(MINUTE, CONCAT(vs.sale_date, ' ', vs.sale_time), ?) as minutes_elapsed,
                     vt.name as voucher_type
              FROM voucher_sales vs
              LEFT JOIN linkspot_station_addresses lsa ON vs.station_address_id = lsa.id
              LEFT JOIN voucher_sale_items vsi ON vs.id = vsi.sale_id
              LEFT JOIN voucher_types vt ON vsi.voucher_type_id = vt.id
              WHERE lsa.status = 'occupied' 
              AND vs.sale_date = CURDATE()
              ORDER BY vs.sale_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $occupied = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate remaining time based on voucher type
        $voucherDuration = 60; // Default 1 hour
        if (strpos($row['voucher_type'], 'Hour') !== false) {
            $hours = intval($row['voucher_type']);
            $voucherDuration = $hours * 60;
        } elseif (strpos($row['voucher_type'], 'Day') !== false) {
            $voucherDuration = 24 * 60; // 24 hours
        }
        
        $remaining = $voucherDuration - $row['minutes_elapsed'];
        if ($remaining > 0) {
            $row['remaining_minutes'] = $remaining;
            $row['remaining_time'] = floor($remaining / 60) . 'h ' . ($remaining % 60) . 'm';
            $occupied[] = $row;
        }
    }
    
    return $occupied;
}

// Check for payment reminders
function checkPaymentReminders() {
    $db = getDB();
    $today = date('Y-m-d');
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    
    // Check Linkspot members with due dates approaching
    $reminders = [];
    
    // Linkspot members due in next 7 days
    $stmt = $db->prepare("SELECT * FROM linkspot_members WHERE next_due_date <= ? AND status = 'active'");
    $stmt->bind_param("s", $nextWeek);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $daysLeft = floor((strtotime($row['next_due_date']) - strtotime($today)) / (60 * 60 * 24));
        $reminders[] = [
            'type' => 'linkspot_payment',
            'title' => 'Payment Due: ' . $row['full_name'],
            'message' => $row['full_name'] . ' has payment due on ' . $row['next_due_date'] . ' (' . $daysLeft . ' days left)',
            'target_id' => $row['id'],
            'due_date' => $row['next_due_date']
        ];
    }
    
    // Summarcity members due in next 7 days
    $stmt = $db->prepare("SELECT * FROM summarcity_members WHERE next_due_date <= ? AND status = 'active'");
    $stmt->bind_param("s", $nextWeek);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $daysLeft = floor((strtotime($row['next_due_date']) - strtotime($today)) / (60 * 60 * 24));
        $reminders[] = [
            'type' => 'summarcity_payment',
            'title' => 'Rent Due: ' . $row['full_name'],
            'message' => $row['business_name'] . ' has rent due on ' . $row['next_due_date'] . ' (' . $daysLeft . ' days left)',
            'target_id' => $row['id'],
            'due_date' => $row['next_due_date']
        ];
    }
    
    return $reminders;
}

// Generate member code
function generateMemberCode($prefix = 'LS') {
    $db = getDB();
    $year = date('y');
    $month = date('m');
    
    // Get last member code
    $stmt = $db->prepare("SELECT member_code FROM linkspot_members WHERE member_code LIKE ? ORDER BY id DESC LIMIT 1");
    $likePattern = $prefix . $year . $month . '%';
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNum = intval(substr($row['member_code'], -3));
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNum = '001';
    }
    
    return $prefix . $year . $month . $newNum;
}
?>