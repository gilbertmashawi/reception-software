<?php
// notifications.php - Notification Management
require_once 'config.php';

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
            header('Location: notifications.php');
            exit();
        }
    }
    
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: notifications.php');
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
$action = $_GET['action'] ?? 'view';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_notifications':
            $limit = intval($_POST['limit']) ?: 50;
            $unreadOnly = $_POST['unread_only'] === 'true';
            
            $sql = "SELECT * FROM notifications WHERE recipient_type = 'all' OR (recipient_type = 'user' AND recipient_id = ?)";
            
            if ($unreadOnly) {
                $sql .= " AND is_read = 0";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("ii", $user['id'], $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            $unreadCount = 0;
            
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
                if (!$row['is_read']) $unreadCount++;
            }
            
            echo json_encode(['success' => true, 'data' => $notifications, 'unread_count' => $unreadCount]);
            break;
            
        case 'mark_as_read':
            $notificationId = intval($_POST['notification_id']);
            $markAll = $_POST['mark_all'] === 'true';
            
            if ($markAll) {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE (recipient_type = 'all' OR (recipient_type = 'user' AND recipient_id = ?)) AND is_read = 0");
                $stmt->bind_param("i", $user['id']);
            } else {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                $stmt->bind_param("i", $notificationId);
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
            }
            break;
            
        case 'delete_notification':
            $notificationId = intval($_POST['notification_id']);
            $deleteAll = $_POST['delete_all'] === 'true';
            
            if ($deleteAll) {
                $stmt = $db->prepare("DELETE FROM notifications WHERE (recipient_type = 'all' OR (recipient_type = 'user' AND recipient_id = ?))");
                $stmt->bind_param("i", $user['id']);
            } else {
                $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
                $stmt->bind_param("i", $notificationId);
            }
            
            if ($stmt->execute()) {
                addActivityLog('Notifications', $deleteAll ? "Deleted all notifications" : "Deleted notification ID {$notificationId}");
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
            }
            break;
            
        case 'send_notification':
            if ($user['role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Only admins can send notifications']);
                exit;
            }
            
            $title = $_POST['title'];
            $message = $_POST['message'];
            $recipientType = $_POST['recipient_type'];
            $recipientId = $_POST['recipient_id'] ?? null;
            $notificationType = $_POST['notification_type'] ?? 'general';
            
            if (sendNotification($notificationType, $title, $message, $recipientType, $recipientId)) {
                addActivityLog('Notifications', "Sent notification: {$title}", "Recipient: {$recipientType}");
                echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
            }
            break;
            
        case 'get_reminders':
            $today = date('Y-m-d');
            $stmt = $db->prepare("SELECT r.*, u.username FROM reminders r LEFT JOIN users u ON r.created_by = u.id WHERE r.reminder_time >= ? AND r.status = 'pending' ORDER BY r.reminder_time");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reminders = [];
            while ($row = $result->fetch_assoc()) {
                $reminders[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $reminders]);
            break;
            
        case 'update_reminder_status':
            $reminderId = intval($_POST['reminder_id']);
            $status = $_POST['status'];
            
            $stmt = $db->prepare("UPDATE reminders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $reminderId);
            
            if ($stmt->execute()) {
                addActivityLog('Reminders', "Updated reminder status to {$status}", "Reminder ID: {$reminderId}");
                echo json_encode(['success' => true, 'message' => 'Reminder status updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update reminder']);
            }
            break;
    }
    exit;
}

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
    <title><?php echo SITE_NAME; ?> - Notifications</title>
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
            --notification-unread: #e3f2fd;
            --notification-general: #f5f5f5;
            --notification-alert: #fff3cd;
            --notification-warning: #f8d7da;
            --notification-success: #d4edda;
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
        
        .btn-primary:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-block {
            width: 100%;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
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
        
        /* Notification Badge */
        .notification-badge-inline {
            background: var(--primary);
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        /* Notifications List */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .notification-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-item.unread {
            background: var(--notification-unread);
            border-left-color: var(--primary);
        }
        
        .notification-item.general { border-left-color: #6c757d; }
        .notification-item.alert { border-left-color: #ffc107; background: var(--notification-alert); }
        .notification-item.warning { border-left-color: #dc3545; background: var(--notification-warning); }
        .notification-item.success { border-left-color: #28a745; background: var(--notification-success); }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--secondary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-time {
            color: #6c757d;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .notification-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .type-badge {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .type-general { background: #6c757d; color: white; }
        .type-alert { background: #ffc107; color: #856404; }
        .type-warning { background: #dc3545; color: white; }
        .type-success { background: #28a745; color: white; }
        
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        /* Reminders */
        .reminder-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #ffc107;
            transition: all 0.3s;
        }
        
        .reminder-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .reminder-item.urgent {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .reminder-time {
            color: #6c757d;
            font-size: 12px;
            margin-top: 5px;
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
            
            .notification-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .notification-actions {
                flex-wrap: wrap;
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
                    <h1><i class="fas fa-bell"></i> Notifications & Reminders</h1>
                    <p>Stay updated with system alerts and reminders</p>
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
                <div class="tab <?php echo $action === 'view' ? 'active' : ''; ?>" onclick="switchTab('view')">
                    All Notifications
                </div>
                <div class="tab <?php echo $action === 'reminders' ? 'active' : ''; ?>" onclick="switchTab('reminders')">
                    Reminders
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <div class="tab <?php echo $action === 'send' ? 'active' : ''; ?>" onclick="switchTab('send')">
                    Send Notification
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Notifications Tab -->
            <div id="tab-view" class="tab-content" style="<?php echo $action === 'view' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Notifications</h3>
                        <div>
                            <button class="btn btn-primary btn-sm" onclick="markAllAsRead()">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteAllNotifications()">
                                <i class="fas fa-trash"></i> Clear All
                            </button>
                        </div>
                    </div>
                    
                    <div class="notifications-list" id="notificationsList">
                        <!-- Notifications will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Reminders Tab -->
            <div id="tab-reminders" class="tab-content" style="<?php echo $action === 'reminders' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Reminders</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadReminders()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div id="remindersList">
                        <!-- Reminders will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Send Notification Tab (Admin Only) -->
            <?php if ($user['role'] === 'admin'): ?>
            <div id="tab-send" class="tab-content" style="<?php echo $action === 'send' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Send Notification</h3>
                    </div>
                    
                    <div class="alert" id="alertMessage" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" id="notificationTitle" class="form-control" placeholder="Enter notification title">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message *</label>
                        <textarea id="notificationMessage" class="form-control" rows="4" placeholder="Enter notification message"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Recipient Type *</label>
                            <select id="recipientType" class="form-control" onchange="toggleRecipientId()">
                                <option value="all">All Users</option>
                                <option value="user">Specific User</option>
                            </select>
                        </div>
                        <div class="form-group" id="recipientIdGroup" style="display: none;">
                            <label class="form-label">User ID</label>
                            <input type="number" id="recipientId" class="form-control" placeholder="Enter user ID">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notification Type</label>
                        <select id="notificationType" class="form-control">
                            <option value="general">General</option>
                            <option value="alert">Alert</option>
                            <option value="warning">Warning</option>
                            <option value="success">Success</option>
                        </select>
                    </div>
                    
                    <button class="btn btn-primary" onclick="sendNewNotification()">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </div>
            </div>
            <?php endif; ?>
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            
            if ('<?php echo $action; ?>' === 'reminders') {
                loadReminders();
            }
            
            // Auto-refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
        
        // Switch tabs
        function switchTab(tab) {
            window.location.href = 'notifications.php?action=' + tab;
        }
        
        // Load notifications
        function loadNotifications() {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_notifications&limit=50&unread_only=false'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUnreadCount(data.unread_count);
                    renderNotifications(data.data);
                }
            });
        }
        
        // Update unread count
        function updateUnreadCount(count) {
            const badge = document.getElementById('unreadCount');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            }
        }
        
        // Render notifications
        function renderNotifications(notifications) {
            const list = document.getElementById('notificationsList');
            
            if (notifications.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <i class="far fa-bell-slash"></i>
                        <h3>No notifications</h3>
                        <p>You're all caught up!</p>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = '';
            
            notifications.forEach(notification => {
                const item = document.createElement('div');
                item.className = `notification-item ${notification.notification_type} ${notification.is_read ? '' : 'unread'}`;
                
                // Format time
                const time = new Date(notification.created_at);
                const timeStr = time.toLocaleDateString() + ' ' + time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                item.innerHTML = `
                    <div class="notification-header">
                        <h4 class="notification-title">
                            <span class="type-badge type-${notification.notification_type}">${notification.notification_type}</span>
                            ${notification.title}
                        </h4>
                        <span class="notification-time">${timeStr}</span>
                    </div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-actions">
                        ${!notification.is_read ? `
                            <button class="btn btn-primary btn-sm" onclick="markAsRead(${notification.id})">
                                <i class="fas fa-check"></i> Mark as Read
                            </button>
                        ` : ''}
                        <button class="btn btn-danger btn-sm" onclick="deleteNotification(${notification.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                list.appendChild(item);
            });
        }
        
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=mark_as_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        // Mark all as read
        function markAllAsRead() {
            if (!confirm('Mark all notifications as read?')) return;
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=mark_as_read&mark_all=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        // Delete notification
        function deleteNotification(notificationId) {
            if (!confirm('Delete this notification?')) return;
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=delete_notification&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        // Delete all notifications
        function deleteAllNotifications() {
            if (!confirm('Delete all notifications? This action cannot be undone.')) return;
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=delete_notification&delete_all=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        // Load reminders
        function loadReminders() {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_reminders'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderReminders(data.data);
                }
            });
        }
        
        // Render reminders
        function renderReminders(reminders) {
            const list = document.getElementById('remindersList');
            
            if (reminders.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <i class="far fa-clock"></i>
                        <h3>No reminders</h3>
                        <p>No upcoming reminders scheduled</p>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = '';
            
            reminders.forEach(reminder => {
                const time = new Date(reminder.reminder_time);
                const now = new Date();
                const hoursDiff = (time - now) / (1000 * 60 * 60);
                
                const item = document.createElement('div');
                item.className = `reminder-item ${hoursDiff <= 1 ? 'urgent' : ''}`;
                
                item.innerHTML = `
                    <div><strong>${reminder.title}</strong></div>
                    <div style="font-size: 14px; color: #666; margin-top: 5px;">${reminder.message}</div>
                    <div class="reminder-time">
                        <i class="far fa-clock"></i> ${time.toLocaleString()}
                        ${reminder.username ? ` • Created by: ${reminder.username}` : ''}
                    </div>
                    <div style="margin-top: 10px;">
                        <button class="btn btn-success btn-sm" onclick="updateReminderStatus(${reminder.id}, 'sent')">
                            <i class="fas fa-check"></i> Mark as Sent
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="updateReminderStatus(${reminder.id}, 'dismissed')">
                            <i class="fas fa-times"></i> Dismiss
                        </button>
                    </div>
                `;
                
                list.appendChild(item);
            });
        }
        
        // Update reminder status
        function updateReminderStatus(reminderId, status) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=update_reminder_status&reminder_id=${reminderId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadReminders();
                }
            });
        }
        
        // Toggle recipient ID field
        function toggleRecipientId() {
            const type = document.getElementById('recipientType').value;
            const group = document.getElementById('recipientIdGroup');
            group.style.display = type === 'user' ? 'block' : 'none';
        }
        
        // Send new notification (admin only)
        function sendNewNotification() {
            const title = document.getElementById('notificationTitle').value;
            const message = document.getElementById('notificationMessage').value;
            const recipientType = document.getElementById('recipientType').value;
            const recipientId = document.getElementById('recipientId').value;
            const notificationType = document.getElementById('notificationType').value;
            
            if (!title || !message) {
                showAlert('Please fill all required fields', 'danger');
                return;
            }
            
            if (recipientType === 'user' && !recipientId) {
                showAlert('Please enter a user ID', 'danger');
                return;
            }
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=send_notification&title=${encodeURIComponent(title)}&message=${encodeURIComponent(message)}&recipient_type=${recipientType}&recipient_id=${recipientId}&notification_type=${notificationType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Notification sent successfully!', 'success');
                    // Clear form
                    document.getElementById('notificationTitle').value = '';
                    document.getElementById('notificationMessage').value = '';
                    document.getElementById('recipientId').value = '';
                    
                    setTimeout(() => {
                        hideAlert();
                    }, 3000);
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Show/hide alert
        function showAlert(message, type) {
            const alert = document.getElementById('alertMessage');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function hideAlert() {
            document.getElementById('alertMessage').style.display = 'none';
        }
        
        // Auto-refresh reminders every minute
        if ('<?php echo $action; ?>' === 'reminders') {
            setInterval(loadReminders, 60000);
        }
    </script>
</body>
</html>