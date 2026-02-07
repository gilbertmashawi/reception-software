<?php
// meetingrooms.php - Meeting Rooms Management
require_once 'config.php';

// Ensure no output before JSON response for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    ob_clean(); // Clear any accidental output
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'book_room':
            $roomName = $_POST['room_name'] ?? '';
            $bookedBy = $_POST['booked_by'] ?? '';
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';
            
            // Validate all fields
            if (empty($roomName) || empty($bookedBy) || empty($startTime) || empty($endTime)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            try {
                $db = getDB();
                
                // Validate booking time
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                
                if ($start >= $end) {
                    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
                    exit;
                }
                
                // Check for overlapping bookings
                $stmt = $db->prepare("
                    SELECT COUNT(*) as overlap_count 
                    FROM meeting_rooms 
                    WHERE room_name = ? 
                    AND (
                        (start_date = ? AND start_time < ? AND end_time > ?) OR
                        (end_date = ? AND start_time < ? AND end_time > ?) OR
                        (start_date < ? AND end_date > ?)
                    )
                ");
                
                $startDate = $start->format('Y-m-d');
                $startTimeFormatted = $start->format('H:i:s');
                $endDate = $end->format('Y-m-d');
                $endTimeFormatted = $end->format('H:i:s');
                
                $stmt->bind_param("sssssssss", 
                    $roomName,
                    $startDate, $endTimeFormatted, $startTimeFormatted,
                    $endDate, $endTimeFormatted, $startTimeFormatted,
                    $endDate, $startDate
                );
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['overlap_count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Room already booked for the selected time']);
                    exit;
                }
                
                // Calculate hours and cost
                $interval = $start->diff($end);
                $hours = $interval->h + ($interval->i / 60);
                // Minimum 1 hour cost is $10, then $5 per additional hour
                $cost = 10 + max(0, ceil($hours - 1) * 5);
                
                // Insert booking
                $stmt = $db->prepare("
                    INSERT INTO meeting_rooms 
                    (room_name, booked_by, start_date, start_time, end_date, end_time, hours, cost) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
                    exit;
                }
                
                $stmt->bind_param("ssssssdd", 
                    $roomName,
                    $bookedBy,
                    $startDate,
                    $startTimeFormatted,
                    $endDate,
                    $endTimeFormatted,
                    $hours,
                    $cost
                );
                
                if ($stmt->execute()) {
                    $bookingId = $stmt->insert_id;
                    
                    // Log activity
                    addActivityLog('Meeting Rooms', "Booked {$roomName} for {$bookedBy}", "Hours: {$hours}, Cost: \${$cost}");
                    
                    // Send notification
                    sendNotification('room_booking', 'New Room Booking', 
                        "{$roomName} booked by {$bookedBy} from {$startDate} {$startTimeFormatted} to {$endDate} {$endTimeFormatted}");
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Room booked successfully!',
                        'booking_id' => $bookingId
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to book room: ' . $stmt->error]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_bookings':
            $date = $_POST['date'] ?? date('Y-m-d');
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM meeting_rooms WHERE start_date = ? ORDER BY start_time");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $bookings = [];
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $bookings]);
            exit;
            
        case 'get_room_status':
            $db = getDB();
            $roomAStatus = getRoomStatus($db, 'Meeting Room A');
            $roomBStatus = getRoomStatus($db, 'Meeting Room B');
            
            echo json_encode([
                'success' => true,
                'rooms' => [
                    'Meeting Room A' => $roomAStatus,
                    'Meeting Room B' => $roomBStatus
                ]
            ]);
            exit;
            
        case 'delete_booking':
            $bookingId = intval($_POST['booking_id']);
            $db = getDB();
            
            // Get booking info first
            $stmt = $db->prepare("SELECT room_name, booked_by FROM meeting_rooms WHERE id = ?");
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
            
            if ($booking) {
                $stmt = $db->prepare("DELETE FROM meeting_rooms WHERE id = ?");
                $stmt->bind_param("i", $bookingId);
                
                if ($stmt->execute()) {
                    addActivityLog('Meeting Rooms', "Deleted booking for {$booking['room_name']} by {$booking['booked_by']}");
                    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete booking']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
            }
            exit;
    }
    exit;
}

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Simple password check
        if ($password === $user['password']) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header('Location: meeting_rooms.php');
            exit();
        }
    }
    
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: meeting_rooms.php');
    exit();
}

// Only proceed if logged in
if (!isLoggedIn()) {
    // Show login page
    include 'header.php';
    exit();
}

$db = getDB();
$user = getCurrentUser();
$action = $_GET['action'] ?? 'bookings';

// Function to check room availability
function getRoomStatus($db, $roomName, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $now = new DateTime();
    $currentTime = $now->format('H:i:s');
    $currentDate = $now->format('Y-m-d');
    
    // Check if room is currently occupied
    $stmt = $db->prepare("
        SELECT * FROM meeting_rooms 
        WHERE room_name = ? 
        AND start_date <= ? 
        AND end_date >= ?
        AND TIME(start_time) <= ?
        AND TIME(end_time) >= ?
        ORDER BY start_time DESC 
        LIMIT 1
    ");
    
    $stmt->bind_param("sssss", $roomName, $currentDate, $currentDate, $currentTime, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        return [
            'status' => 'occupied',
            'booking' => $booking,
            'booked_by' => $booking['booked_by'],
            'end_time' => $booking['end_time']
        ];
    }
    
    // Check if room has any bookings for today
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_bookings 
        FROM meeting_rooms 
        WHERE room_name = ? 
        AND start_date = ?
    ");
    $stmt->bind_param("ss", $roomName, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['total_bookings'] > 0) {
        // Room has bookings but not currently occupied
        return [
            'status' => 'reserved',
            'bookings_count' => $row['total_bookings']
        ];
    }
    
    return [
        'status' => 'available'
    ];
}

// Get room status for display
$roomAStatus = getRoomStatus($db, 'Meeting Room A');
$roomBStatus = getRoomStatus($db, 'Meeting Room B');

// Get dashboard statistics for sidebar
function getDashboardStats() {
    $db = getDB();
    $today = date('Y-m-d');
    
    $stats = [
        'total_revenue_today' => 0,
        'active_members' => 0,
        'pending_tasks' => 0,
        'available_spaces' => 0,
        'unread_notifications' => getUnreadNotificationsCount(),
        'payment_reminders' => [],
        'new_members_today' => 0,
        'occupied_spaces' => []
    ];
    
    // Today's revenue
    $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE sale_date = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['total_revenue_today'] += $row['total'] ?? 0;
    
    // Active members
    $query = "SELECT COUNT(*) as count FROM linkspot_members WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['active_members'] += $row['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM summarcity_members WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['active_members'] += $row['count'] ?? 0;
    
    // Pending tasks
    $query = "SELECT COUNT(*) as count FROM tasks WHERE status = 'pending' AND task_date = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['pending_tasks'] = $row['count'] ?? 0;
    
    // Available spaces
    $query = "SELECT COUNT(*) as count FROM linkspot_station_addresses WHERE status = 'available'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['available_spaces'] = $row['count'] ?? 0;
    
    // New members today
    $query = "SELECT COUNT(*) as count FROM linkspot_members WHERE DATE(created_at) = ? AND is_new = 1";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['new_members_today'] += $row['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM summarcity_members WHERE DATE(created_at) = ? AND is_new = 1";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['new_members_today'] += $row['count'] ?? 0;
    
    // Payment reminders
    $stats['payment_reminders'] = checkPaymentReminders();
    
    // Occupied spaces with remaining time
    $stats['occupied_spaces'] = getOccupiedSpacesWithRemainingTime();
    
    // Recent activity
    $query = "SELECT al.*, u.username FROM activity_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              ORDER BY al.created_at DESC LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['recent_activity'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['recent_activity'][] = $row;
    }
    
    return $stats;
}

$stats = getDashboardStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Meeting Rooms</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo SITE_THEME_COLOR; ?>;
            --secondary: <?php echo SITE_SECONDARY_COLOR; ?>;
            --accent: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --sidebar-width: 260px;
            --header-height: 70px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .login-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 3px solid white;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-primary:disabled {
            background: #7fbf8f;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .dashboard-page {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--secondary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .sidebar-header h3 {
            font-size: 18px;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            list-style: none;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .nav-dropdown .nav-link {
            position: relative;
        }
        
        .nav-dropdown .nav-link:after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: auto;
            transition: transform 0.3s;
        }
        
        .nav-dropdown.active .nav-link:after {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            padding-left: 20px;
            background: rgba(0,0,0,0.2);
            display: none;
        }
        
        .nav-dropdown.active .dropdown-menu {
            display: block;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title h1 {
            font-size: 24px;
            color: var(--secondary);
            margin: 0;
        }
        
        .page-title p {
            color: #6c757d;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-badge {
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-card.info {
            border-left-color: var(--info);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: rgba(39, 174, 96, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .stat-card.danger .stat-icon {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .stat-card.warning .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .stat-card.info .stat-icon {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 18px;
            color: var(--secondary);
            margin: 0;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab:hover {
            color: var(--primary);
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Form */
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        /* Collapsible Form Toggle */
        .form-toggle {
            background: var(--primary);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .form-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }
        
        .form-toggle.expanded {
            background: var(--danger);
        }
        
        .collapsible-form {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.5s ease-out;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 0 25px;
        }
        
        .collapsible-form.expanded {
            max-height: 2000px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        
        /* Room Grid */
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .room-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #dee2e6;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .room-card.occupied {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .room-card.available {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .room-card.reserved {
            border-color: #ffc107;
            background: #fff3cd;
        }
        
        .room-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .status-available {
            background: #28a745;
            color: white;
        }
        
        .status-occupied {
            background: #dc3545;
            color: white;
        }
        
        .status-reserved {
            background: #ffc107;
            color: #856404;
        }
        
        /* Room Status Cards */
        .room-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .room-status-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .room-status-card:hover {
            transform: translateY(-5px);
        }
        
        .room-status-card.occupied {
            border-left: 4px solid var(--danger);
        }
        
        .room-status-card.available {
            border-left: 4px solid var(--success);
        }
        
        .room-status-card.reserved {
            border-left: 4px solid var(--warning);
        }
        
        .room-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .room-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .room-current-booking {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .room-current-booking h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6c757d;
        }
        
        /* Badges */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 5px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .room-grid {
                grid-template-columns: 1fr;
            }
            
            .room-status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!isLoggedIn()): ?>
    <!-- Login Page -->
    <div class="login-page">
        <div class="login-container">
            <div class="login-header">
                <img src="https://scontent.fhre1-2.fna.fbcdn.net/v/t39.30808-6/358463300_669326775209841_5422243091594236605_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=xvYFlRpuCysQ7kNvwE1kVf7&_nc_oc=AdkIzCWNuer7eKsitiYPxWIEh6EeFJiR3OzMoX521DlWvneItUpdDQFAWg3h-EMy8-s&_nc_zt=23&_nc_ht=scontent.fhre1-2.fna&_nc_gid=igic6zzb6eWSf_Zi3oMxRw&oh=00_AfqSWFzSgi_3m_PNmez8FRZVwa7RrMtw_TkV8n8fIrbSrg&oe=697A3BAF" 
                     alt="LinkSpot Logo">
                <h1>LinkSpot Management</h1>
                <p>Welcome to the Management System</p>
            </div>
            <div class="login-body">
                <?php if (isset($login_error)): ?>
                <div class="alert alert-danger">
                    <?php echo $login_error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div style="margin-top: 20px; text-align: center; color: #666; font-size: 12px;">
                    <p>Use: gilbert, teddy, walter, tafadzwa, or admin</p>
                    <p>Password: same as username (except admin: admin123)</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Main Dashboard -->
    <div class="dashboard-page">
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-door-closed"></i> Meeting Rooms</h1>
                    <p>Book and manage meeting rooms</p>
                </div>
                <div class="user-menu">
                    <div class="notification-badge">
                        <i class="fas fa-bell"></i>
                        <?php if ($stats['unread_notifications'] > 0): ?>
                        <span class="badge"><?php echo $stats['unread_notifications']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 500;"><?php echo $_SESSION['full_name']; ?></div>
                            <div style="font-size: 12px; color: #6c757d;"><?php echo ucfirst($_SESSION['role']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tabs">
                <div class="tab <?php echo $action === 'bookings' ? 'active' : ''; ?>" onclick="switchTab('bookings')">
                    Book Room
                </div>
                <div class="tab <?php echo $action === 'view' ? 'active' : ''; ?>" onclick="switchTab('view')">
                    View Bookings
                </div>
                <div class="tab <?php echo $action === 'status' ? 'active' : ''; ?>" onclick="switchTab('status')">
                    Room Status
                </div>
            </div>
            
            <!-- Book Room Tab -->
            <div id="tab-bookings" class="tab-content" style="<?php echo $action === 'bookings' ? 'display: block;' : 'display: none;'; ?>">
                <div class="form-toggle" onclick="toggleBookingForm()" id="bookingToggle">
                    <i class="fas fa-plus"></i>
                </div>
                
                <div class="collapsible-form" id="bookingForm">
                    <div class="alert" id="alertMessage"></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Room Name *</label>
                            <select id="roomName" class="form-control">
                                <option value="Meeting Room A">Meeting Room A</option>
                                <option value="Meeting Room B">Meeting Room B</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Booked By *</label>
                            <input type="text" id="bookedBy" class="form-control" placeholder="Enter name" value="<?php echo $_SESSION['full_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Time *</label>
                            <input type="datetime-local" id="startTime" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time *</label>
                            <input type="datetime-local" id="endTime" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Booking Summary</label>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px;">
                                <div>
                                    <div style="font-size: 12px; color: #6c757d;">Room</div>
                                    <div id="summaryRoom">Meeting Room A</div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #6c757d;">Booked By</div>
                                    <div id="summaryBookedBy"><?php echo $_SESSION['full_name']; ?></div>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <div style="font-size: 12px; color: #6c757d;">Start Time</div>
                                    <div id="summaryStartTime">--:--</div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #6c757d;">End Time</div>
                                    <div id="summaryEndTime">--:--</div>
                                </div>
                            </div>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                <div style="font-size: 12px; color: #6c757d;">Estimated Cost</div>
                                <div id="summaryCost" style="font-size: 18px; font-weight: 600; color: var(--primary);">$0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" onclick="bookRoom()" id="bookButton">
                        <i class="fas fa-calendar-plus"></i> Book Room
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Room Status</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadRoomStatus()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="room-status-grid" id="roomStatusGrid">
                        <!-- Room status will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- View Bookings Tab -->
            <div id="tab-view" class="tab-content" style="<?php echo $action === 'view' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Bookings</h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="date" id="filterDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" style="width: 150px;">
                            <button class="btn btn-primary btn-sm" onclick="loadBookings()">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    <div class="room-grid" id="bookingsGrid">
                        <!-- Bookings will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Room Status Tab -->
            <div id="tab-status" class="tab-content" style="<?php echo $action === 'status' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Current Room Status</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadAllRoomStatus()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="room-status-grid" id="allRoomStatusGrid">
                        <!-- All room status will be loaded here -->
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Room Information</h3>
                    </div>
                    <div style="padding: 20px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px 0; color: var(--secondary);">Meeting Room A</h4>
                                <p style="margin: 5px 0; color: #6c757d;">Capacity: 10 people</p>
                                <p style="margin: 5px 0; color: #6c757d;">Equipment: Projector, Whiteboard</p>
                                <p style="margin: 5px 0; color: #6c757d;">Hourly Rate: $10 first hour, $5 per additional hour</p>
                            </div>
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px 0; color: var(--secondary);">Meeting Room B</h4>
                                <p style="margin: 5px 0; color: #6c757d;">Capacity: 8 people</p>
                                <p style="margin: 5px 0; color: #6c757d;">Equipment: TV Screen, Conference Phone</p>
                                <p style="margin: 5px 0; color: #6c757d;">Hourly Rate: $10 first hour, $5 per additional hour</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Mobile menu toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Dropdown toggle
        document.querySelectorAll('.nav-dropdown .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const dropdown = this.parentElement;
                    dropdown.classList.toggle('active');
                }
            });
        });
        
        // Helper function to round time to nearest 15 minutes
        function roundToNearest15Minutes(date) {
            const minutes = date.getMinutes();
            const roundedMinutes = Math.round(minutes / 15) * 15;
            const newDate = new Date(date);
            
            if (roundedMinutes === 60) {
                newDate.setHours(newDate.getHours() + 1);
                newDate.setMinutes(0);
            } else {
                newDate.setMinutes(roundedMinutes);
            }
            
            return newDate;
        }
        
        // Helper function for formatting datetime-local input
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }
        
        // Helper function to format date for display
        function formatDateForDisplay(dateString) {
            const date = new Date(dateString);
            const options = { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            };
            return date.toLocaleDateString('en-US', options);
        }
        
        // Switch tabs
        function switchTab(tab) {
            window.location.href = 'meeting_rooms.php?action=' + tab;
        }
        
        // Toggle booking form
        function toggleBookingForm() {
            const toggle = document.getElementById('bookingToggle');
            const form = document.getElementById('bookingForm');
            
            if (form.classList.contains('expanded')) {
                form.classList.remove('expanded');
                toggle.innerHTML = '<i class="fas fa-plus"></i>';
                toggle.classList.remove('expanded');
            } else {
                form.classList.add('expanded');
                toggle.innerHTML = '<i class="fas fa-minus"></i>';
                toggle.classList.add('expanded');
                
                // Set default times if not already set
                if (!document.getElementById('startTime').value) {
                    const now = new Date();
                    const start = roundToNearest15Minutes(now);
                    const end = new Date(start);
                    end.setHours(end.getHours() + 1);
                    
                    document.getElementById('startTime').value = formatDateTimeLocal(start);
                    document.getElementById('endTime').value = formatDateTimeLocal(end);
                    updateBookingSummary();
                }
                
                // Load room status
                if (document.getElementById('roomStatusGrid').children.length === 0) {
                    loadRoomStatus();
                }
            }
        }
        
        // Update booking summary
        function updateBookingSummary() {
            const roomName = document.getElementById('roomName').value;
            const bookedBy = document.getElementById('bookedBy').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            
            document.getElementById('summaryRoom').textContent = roomName;
            document.getElementById('summaryBookedBy').textContent = bookedBy || 'Not set';
            
            if (startTime) {
                document.getElementById('summaryStartTime').textContent = formatDateForDisplay(startTime);
            }
            
            if (endTime) {
                document.getElementById('summaryEndTime').textContent = formatDateForDisplay(endTime);
            }
            
            // Calculate estimated cost
            if (startTime && endTime) {
                const start = new Date(startTime);
                const end = new Date(endTime);
                const diffInHours = (end - start) / (1000 * 60 * 60);
                
                if (diffInHours > 0) {
                    // Minimum 1 hour cost is $10, then $5 per additional hour
                    const cost = 10 + Math.max(0, Math.ceil(diffInHours - 1) * 5);
                    document.getElementById('summaryCost').textContent = '$' + cost.toFixed(2);
                }
            }
        }
        
        // Setup form listeners
        function setupFormListeners() {
            const inputs = ['roomName', 'bookedBy', 'startTime', 'endTime'];
            inputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('change', updateBookingSummary);
                    input.addEventListener('input', updateBookingSummary);
                }
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set default times
            const now = new Date();
            const start = roundToNearest15Minutes(now);
            const end = new Date(start);
            end.setHours(end.getHours() + 1);
            
            document.getElementById('startTime').value = formatDateTimeLocal(start);
            document.getElementById('endTime').value = formatDateTimeLocal(end);
            
            // Initial update of booking summary
            updateBookingSummary();
            
            // Setup form listeners
            setupFormListeners();
            
            // Load appropriate content based on active tab
            if ('<?php echo $action; ?>' === 'bookings') {
                loadRoomStatus();
            } else if ('<?php echo $action; ?>' === 'view') {
                loadBookings();
            } else if ('<?php echo $action; ?>' === 'status') {
                loadAllRoomStatus();
            }
            
            // Add event listener for date filter
            const filterDate = document.getElementById('filterDate');
            if (filterDate) {
                filterDate.addEventListener('change', loadBookings);
            }
        });
        
        // Load room status for bookings tab
        function loadRoomStatus() {
            fetch('meeting_rooms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_room_status`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('roomStatusGrid');
                    if (grid) {
                        grid.innerHTML = '';
                        
                        Object.entries(data.rooms).forEach(([roomName, roomStatus]) => {
                            const card = document.createElement('div');
                            card.className = `room-status-card ${roomStatus.status}`;
                            
                            let statusContent = '';
                            if (roomStatus.status === 'occupied') {
                                const endTime = new Date(roomStatus.booking.end_date + 'T' + roomStatus.booking.end_time);
                                const now = new Date();
                                const timeRemaining = Math.max(0, Math.floor((endTime - now) / 60000)); // minutes
                                
                                statusContent = `
                                    <div class="room-current-booking">
                                        <h4>Currently Occupied By:</h4>
                                        <p style="margin: 5px 0;"><strong>${roomStatus.booked_by}</strong></p>
                                        <p style="margin: 5px 0;"><strong>Ends:</strong> ${roomStatus.booking.end_time}</p>
                                        <p style="margin: 5px 0;"><strong>Time remaining:</strong> ${Math.floor(timeRemaining / 60)}h ${timeRemaining % 60}m</p>
                                    </div>
                                `;
                            } else if (roomStatus.status === 'reserved') {
                                statusContent = `
                                    <div class="room-current-booking">
                                        <h4>Room Information:</h4>
                                        <p style="margin: 5px 0;">Has ${roomStatus.bookings_count} booking(s) today</p>
                                        <p style="margin: 5px 0;">Check bookings tab for details</p>
                                    </div>
                                `;
                            } else {
                                statusContent = `
                                    <div class="room-current-booking">
                                        <h4>Room Information:</h4>
                                        <p style="margin: 5px 0;">Available for booking</p>
                                        <p style="margin: 5px 0;">No current bookings</p>
                                    </div>
                                `;
                            }
                            
                            card.innerHTML = `
                                <div class="room-status-header">
                                    <div class="room-name">${roomName}</div>
                                    <span class="room-status status-${roomStatus.status}">
                                        ${roomStatus.status.charAt(0).toUpperCase() + roomStatus.status.slice(1)}
                                    </span>
                                </div>
                                ${statusContent}
                            `;
                            grid.appendChild(card);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading room status:', error);
                showAlert('Failed to load room status. Please refresh the page.', 'danger');
            });
        }
        
        // Load all room status for status tab
        function loadAllRoomStatus() {
            fetch('meeting_rooms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_room_status`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('allRoomStatusGrid');
                    if (grid) {
                        grid.innerHTML = '';
                        
                        Object.entries(data.rooms).forEach(([roomName, roomStatus]) => {
                            const card = document.createElement('div');
                            card.className = `room-status-card ${roomStatus.status}`;
                            
                            let statusContent = '';
                            if (roomStatus.status === 'occupied') {
                                const endTime = new Date(roomStatus.booking.end_date + 'T' + roomStatus.booking.end_time);
                                const now = new Date();
                                const timeRemaining = Math.max(0, Math.floor((endTime - now) / 60000)); // minutes
                                
                                statusContent = `
                                    <div class="room-current-booking">
                                        <h4>Currently Occupied By:</h4>
                                        <p style="margin: 5px 0;"><strong>${roomStatus.booked_by}</strong></p>
                                        <p style="margin: 5px 0;"><strong>Start:</strong> ${roomStatus.booking.start_time}</p>
                                        <p style="margin: 5px 0;"><strong>End:</strong> ${roomStatus.booking.end_time}</p>
                                        <p style="margin: 5px 0;"><strong>Time remaining:</strong> ${Math.floor(timeRemaining / 60)}h ${timeRemaining % 60}m</p>
                                        <p style="margin: 5px 0;"><strong>Cost:</strong> $${parseFloat(roomStatus.booking.cost).toFixed(2)}</p>
                                    </div>
                                `;
                            } else if (roomStatus.status === 'reserved') {
                                statusContent = `
                                    <div class="room-current-booking">
                                        <h4>Room Information:</h4>
                                        <p style="margin: 5px 0;">Has ${roomStatus.bookings_count} booking(s) today</p>
                                        <p style="margin: 5px 0;">Status: Reserved for future bookings</p>
                                    </div>
                                `;
                            } else {
                                statusContent = `
                                    <div class="room-current-booking">
                                        <h4>Room Information:</h4>
                                        <p style="margin: 5px 0;">Available for booking</p>
                                        <p style="margin: 5px 0;">No current bookings</p>
                                    </div>
                                `;
                            }
                            
                            card.innerHTML = `
                                <div class="room-status-header">
                                    <div class="room-name">${roomName}</div>
                                    <span class="room-status status-${roomStatus.status}">
                                        ${roomStatus.status.charAt(0).toUpperCase() + roomStatus.status.slice(1)}
                                    </span>
                                </div>
                                ${statusContent}
                            `;
                            grid.appendChild(card);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading room status:', error);
                showAlert('Failed to load room status. Please refresh the page.', 'danger');
            });
        }
        
        // Book room
        function bookRoom() {
            const roomName = document.getElementById('roomName').value;
            const bookedBy = document.getElementById('bookedBy').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            
            if (!roomName || !bookedBy || !startTime || !endTime) {
                showAlert('Please fill in all fields', 'danger');
                return;
            }
            
            const start = new Date(startTime);
            const end = new Date(endTime);
            
            if (start >= end) {
                showAlert('End time must be after start time', 'danger');
                return;
            }
            
            // Validate booking duration (minimum 15 minutes)
            const diffInMinutes = (end - start) / (1000 * 60);
            if (diffInMinutes < 15) {
                showAlert('Minimum booking duration is 15 minutes', 'danger');
                return;
            }
            
            // Disable button and show loading
            const bookButton = document.getElementById('bookButton');
            const originalText = bookButton.innerHTML;
            
            bookButton.disabled = true;
            bookButton.innerHTML = '<span class="spinner"></span> Booking...';
            
            // Create FormData for better handling
            const formData = new FormData();
            formData.append('ajax', 'true');
            formData.append('action', 'book_room');
            formData.append('room_name', roomName);
            formData.append('booked_by', bookedBy);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);
            
            fetch('meeting_rooms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Booking response:', data);
                
                // Re-enable button
                bookButton.disabled = false;
                bookButton.innerHTML = originalText;
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    
                    // Keep user's name for convenience
                    document.getElementById('bookedBy').value = bookedBy;
                    
                    // Set default times for next booking
                    const now = new Date();
                    const start = roundToNearest15Minutes(now);
                    const end = new Date(start);
                    end.setHours(end.getHours() + 1);
                    
                    document.getElementById('startTime').value = formatDateTimeLocal(start);
                    document.getElementById('endTime').value = formatDateTimeLocal(end);
                    
                    // Update booking summary
                    updateBookingSummary();
                    
                    // Refresh room status
                    loadRoomStatus();
                    
                    setTimeout(() => {
                        hideAlert();
                    }, 3000);
                } else {
                    showAlert(data.message || 'Booking failed', 'danger');
                }
            })
            .catch(error => {
                console.error('Booking error:', error);
                bookButton.disabled = false;
                bookButton.innerHTML = originalText;
                showAlert('Error: ' + error.message, 'danger');
            });
        }
        
        // Load bookings
        function loadBookings() {
            const date = document.getElementById('filterDate').value;
            
            fetch('meeting_rooms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_bookings&date=${date}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('bookingsGrid');
                    if (grid) {
                        grid.innerHTML = '';
                        
                        if (data.data.length === 0) {
                            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #6c757d; padding: 40px;">No bookings for selected date</div>';
                            return;
                        }
                        
                        data.data.forEach(booking => {
                            const card = document.createElement('div');
                            card.className = 'room-card occupied';
                            card.innerHTML = `
                                <div class="room-status status-occupied">Occupied</div>
                                <h4 style="margin: 0 0 10px 0;">${booking.room_name}</h4>
                                <p style="margin: 5px 0;"><strong>Booked by:</strong> ${booking.booked_by}</p>
                                <p style="margin: 5px 0;"><strong>Start:</strong> ${booking.start_date} ${booking.start_time}</p>
                                <p style="margin: 5px 0;"><strong>End:</strong> ${booking.end_date} ${booking.end_time}</p>
                                <p style="margin: 5px 0;"><strong>Hours:</strong> ${parseFloat(booking.hours).toFixed(2)}</p>
                                <p style="margin: 5px 0 15px 0;"><strong>Cost:</strong> $${parseFloat(booking.cost).toFixed(2)}</p>
                                <button class="btn btn-danger btn-sm" onclick="deleteBooking(${booking.id})">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            `;
                            grid.appendChild(card);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading bookings:', error);
                const grid = document.getElementById('bookingsGrid');
                if (grid) {
                    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #dc3545; padding: 40px;">Error loading bookings</div>';
                }
            });
        }
        
        // Delete booking
        function deleteBooking(bookingId) {
            if (!confirm('Are you sure you want to delete this booking?')) {
                return;
            }
            
            fetch('meeting_rooms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=delete_booking&booking_id=${bookingId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Booking deleted successfully!');
                    loadBookings();
                    loadRoomStatus();
                    loadAllRoomStatus();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting booking:', error);
                alert('Error deleting booking: ' + error.message);
            });
        }
        
        // Show alert
        function showAlert(message, type) {
            const alert = document.getElementById('alertMessage');
            if (alert) {
                alert.textContent = message;
                alert.className = `alert alert-${type}`;
                alert.style.display = 'block';
            }
        }
        
        // Hide alert
        function hideAlert() {
            const alert = document.getElementById('alertMessage');
            if (alert) {
                alert.style.display = 'none';
            }
        }
        
        // Auto-refresh room status every minute for bookings tab
        if ('<?php echo $action; ?>' === 'bookings') {
            setInterval(loadRoomStatus, 60000);
        }
        
        // Auto-refresh all room status every minute for status tab
        if ('<?php echo $action; ?>' === 'status') {
            setInterval(loadAllRoomStatus, 60000);
        }
    </script>
</body>
</html>