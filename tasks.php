<?php
// tasks.php - Daily Tasker Management
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
            header('Location: tasks.php');
            exit();
        }
    }
    
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: tasks.php');
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_task':
            $taskDate = $_POST['task_date'];
            $taskTime = $_POST['task_time'];
            $title = $_POST['title'];
            $description = $_POST['description'] ?? '';
            $priority = $_POST['priority'] ?? 'medium';
            
            $stmt = $db->prepare("INSERT INTO tasks (task_date, task_time, title, description, status, priority) VALUES (?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("sssss", $taskDate, $taskTime, $title, $description, $priority);
            
            if ($stmt->execute()) {
                $taskId = $db->insert_id;
                addActivityLog('Tasks', "Added task: {$title}", "Priority: {$priority}, Date: {$taskDate}");
                sendNotification('new_task', 'New Task Added', "Task '{$title}' added for {$taskDate}", 'user', $user['id']);
                echo json_encode(['success' => true, 'message' => 'Task added successfully', 'task_id' => $taskId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add task']);
            }
            break;
            
        case 'get_tasks':
            $date = $_POST['date'] ?? date('Y-m-d');
            $status = $_POST['status'] ?? 'all';
            
            $sql = "SELECT * FROM tasks WHERE task_date = ?";
            if ($status !== 'all') {
                $sql .= " AND status = ?";
            }
            $sql .= " ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                task_time";
            
            $stmt = $db->prepare($sql);
            if ($status !== 'all') {
                $stmt->bind_param("ss", $date, $status);
            } else {
                $stmt->bind_param("s", $date);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $tasks = [];
            $stats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'halt' => 0];
            
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
                $stats['total']++;
                $stats[$row['status']]++;
            }
            
            echo json_encode(['success' => true, 'data' => $tasks, 'stats' => $stats]);
            break;
            
        case 'update_task_status':
            $taskId = intval($_POST['task_id']);
            $status = $_POST['status'];
            
            $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $taskId);
            
            if ($stmt->execute()) {
                // Get task info for log
                $stmt2 = $db->prepare("SELECT title FROM tasks WHERE id = ?");
                $stmt2->bind_param("i", $taskId);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $task = $result->fetch_assoc();
                
                addActivityLog('Tasks', "Updated task '{$task['title']}' to {$status}");
                echo json_encode(['success' => true, 'message' => 'Task status updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update task']);
            }
            break;
            
        case 'delete_task':
            $taskId = intval($_POST['task_id']);
            
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->bind_param("i", $taskId);
            
            if ($stmt->execute()) {
                addActivityLog('Tasks', "Deleted task ID {$taskId}");
                echo json_encode(['success' => true, 'message' => 'Task deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
            }
            break;
            
        case 'update_task':
            $taskId = intval($_POST['task_id']);
            $title = $_POST['title'];
            $description = $_POST['description'] ?? '';
            $priority = $_POST['priority'] ?? 'medium';
            $taskDate = $_POST['task_date'];
            $taskTime = $_POST['task_time'];
            
            $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, priority = ?, task_date = ?, task_time = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $title, $description, $priority, $taskDate, $taskTime, $taskId);
            
            if ($stmt->execute()) {
                addActivityLog('Tasks', "Updated task ID {$taskId}", "New title: {$title}");
                echo json_encode(['success' => true, 'message' => 'Task updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update task']);
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
    <title><?php echo SITE_NAME; ?> - Daily Tasker</title>
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
        
        .btn-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
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
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.total { border-left-color: #6c757d; }
        .stat-card.completed { border-left-color: var(--success); }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.halt { border-left-color: var(--danger); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--secondary);
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
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
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        /* Task Cards */
        .tasks-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .task-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #dee2e6;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .task-card.high-priority { border-left-color: var(--danger); background: #f8d7da; }
        .task-card.medium-priority { border-left-color: var(--warning); background: #fff3cd; }
        .task-card.low-priority { border-left-color: var(--success); background: #d4edda; }
        
        .task-card.complete { opacity: 0.7; background: #e9ecef; }
        .task-card.halt { background: #f8f9fa; border-left-color: #6c757d; }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .task-title {
            font-weight: 600;
            color: var(--secondary);
            margin: 0;
        }
        
        .task-meta {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 12px;
            color: #6c757d;
        }
        
        .priority-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-high { background: var(--danger); color: white; }
        .priority-medium { background: var(--warning); color: #856404; }
        .priority-low { background: var(--success); color: white; }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .status-complete { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-halt { background: #f8d7da; color: #721c24; }
        
        .task-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .task-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-top: 10px;
        }
        
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
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            text-align: right;
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
            
            .form-row {
                flex-direction: column;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .task-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .task-actions {
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
                    <h1><i class="fas fa-tasks"></i> Daily Tasker</h1>
                    <p>Manage your daily tasks and priorities</p>
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
            
            <!-- Stats Cards -->
            <div class="stats-grid" id="statsCards">
                <div class="stat-card total">
                    <div class="stat-value" id="totalTasks">0</div>
                    <div class="stat-label">Total Tasks</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-value" id="completedTasks">0</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value" id="pendingTasks">0</div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card halt">
                    <div class="stat-value" id="haltTasks">0</div>
                    <div class="stat-label">On Hold</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Add New Task</h3>
                </div>
                
                <div class="alert" id="alertMessage" style="display: none;"></div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Task Title *</label>
                        <input type="text" id="taskTitle" class="form-control" placeholder="Enter task title">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="taskPriority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" id="taskDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time *</label>
                        <input type="time" id="taskTime" class="form-control" value="<?php echo date('H:i'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="taskDescription" class="form-control" rows="3" placeholder="Enter task description"></textarea>
                </div>
                
                <button class="btn btn-primary" onclick="addTask()">
                    <i class="fas fa-plus"></i> Add Task
                </button>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tasks</h3>
                    <div class="filters">
                        <input type="date" id="filterDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        <select id="filterStatus" class="form-control">
                            <option value="all">All Status</option>
                            <option value="complete">Complete</option>
                            <option value="pending">Pending</option>
                            <option value="halt">On Hold</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadTasks()">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="btn btn-secondary" onclick="resetFilters()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
                
                <div class="tasks-container" id="tasksContainer">
                    <!-- Tasks will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Task Modal -->
    <div class="modal" id="editTaskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Task</h3>
                <button class="btn btn-sm" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="editTaskTitle" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="editTaskPriority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="editTaskStatus" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="complete">Complete</option>
                            <option value="halt">On Hold</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" id="editTaskDate" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time *</label>
                        <input type="time" id="editTaskTime" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="editTaskDescription" class="form-control" rows="3"></textarea>
                </div>
                <input type="hidden" id="editTaskId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="updateTask()">Save Changes</button>
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadTasks();
            
            // Auto-refresh every 30 seconds
            setInterval(loadTasks, 30000);
        });
        
        // Load tasks
        function loadTasks() {
            const date = document.getElementById('filterDate').value;
            const status = document.getElementById('filterStatus').value;
            
            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_tasks&date=${date}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStats(data.stats);
                    renderTasks(data.data);
                }
            });
        }
        
        // Update stats
        function updateStats(stats) {
            document.getElementById('totalTasks').textContent = stats.total;
            document.getElementById('completedTasks').textContent = stats.completed;
            document.getElementById('pendingTasks').textContent = stats.pending;
            document.getElementById('haltTasks').textContent = stats.halt;
        }
        
        // Render tasks
        function renderTasks(tasks) {
            const container = document.getElementById('tasksContainer');
            
            if (tasks.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No tasks found</h3>
                        <p>Add a new task using the form above</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            
            tasks.forEach(task => {
                const taskCard = document.createElement('div');
                taskCard.className = `task-card ${task.priority}-priority ${task.status}`;
                taskCard.innerHTML = `
                    <div class="task-header">
                        <div>
                            <h4 class="task-title">${task.title}</h4>
                            <div class="task-meta">
                                <span class="priority-badge priority-${task.priority}">${task.priority}</span>
                                <span class="status-badge status-${task.status}">${task.status}</span>
                                <span><i class="far fa-calendar"></i> ${task.task_date}</span>
                                <span><i class="far fa-clock"></i> ${task.task_time}</span>
                            </div>
                        </div>
                    </div>
                    ${task.description ? `<div class="task-description">${task.description}</div>` : ''}
                    <div class="task-actions">
                        ${task.status !== 'complete' ? 
                            `<button class="btn btn-success btn-sm" onclick="updateTaskStatus(${task.id}, 'complete')">
                                <i class="fas fa-check"></i> Complete
                            </button>` : ''}
                        ${task.status !== 'halt' ? 
                            `<button class="btn btn-danger btn-sm" onclick="updateTaskStatus(${task.id}, 'halt')">
                                <i class="fas fa-pause"></i> Hold
                            </button>` : ''}
                        ${task.status !== 'pending' ? 
                            `<button class="btn btn-warning btn-sm" onclick="updateTaskStatus(${task.id}, 'pending')">
                                <i class="fas fa-redo"></i> Reopen
                            </button>` : ''}
                        <button class="btn btn-primary btn-sm" onclick="openEditModal(${task.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="deleteTask(${task.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                container.appendChild(taskCard);
            });
        }
        
        // Add new task
        function addTask() {
            const title = document.getElementById('taskTitle').value;
            const description = document.getElementById('taskDescription').value;
            const priority = document.getElementById('taskPriority').value;
            const date = document.getElementById('taskDate').value;
            const time = document.getElementById('taskTime').value;
            
            if (!title || !date || !time) {
                showAlert('Please fill all required fields', 'danger');
                return;
            }
            
            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=add_task&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&priority=${priority}&task_date=${date}&task_time=${time}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Task added successfully!', 'success');
                    
                    // Reset form
                    document.getElementById('taskTitle').value = '';
                    document.getElementById('taskDescription').value = '';
                    document.getElementById('taskPriority').value = 'medium';
                    
                    // Reload tasks
                    loadTasks();
                    
                    setTimeout(() => {
                        hideAlert();
                    }, 3000);
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Update task status
        function updateTaskStatus(taskId, status) {
            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=update_task_status&task_id=${taskId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTasks();
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Delete task
        function deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }
            
            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=delete_task&task_id=${taskId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTasks();
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Open edit modal
        function openEditModal(taskId) {
            // Load task details
            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_tasks&date=all`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const task = data.data.find(t => t.id == taskId);
                    if (task) {
                        document.getElementById('editTaskId').value = task.id;
                        document.getElementById('editTaskTitle').value = task.title;
                        document.getElementById('editTaskPriority').value = task.priority;
                        document.getElementById('editTaskStatus').value = task.status;
                        document.getElementById('editTaskDate').value = task.task_date;
                        document.getElementById('editTaskTime').value = task.task_time;
                        document.getElementById('editTaskDescription').value = task.description || '';
                        
                        document.getElementById('editTaskModal').style.display = 'flex';
                    }
                }
            });
        }
        
        // Close edit modal
        function closeEditModal() {
            document.getElementById('editTaskModal').style.display = 'none';
        }
        
        // Update task
        function updateTask() {
            const taskId = document.getElementById('editTaskId').value;
            const title = document.getElementById('editTaskTitle').value;
            const priority = document.getElementById('editTaskPriority').value;
            const status = document.getElementById('editTaskStatus').value;
            const date = document.getElementById('editTaskDate').value;
            const time = document.getElementById('editTaskTime').value;
            const description = document.getElementById('editTaskDescription').value;
            
            if (!title || !date || !time) {
                showAlert('Please fill all required fields', 'danger');
                return;
            }
            
            fetch('tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=update_task&task_id=${taskId}&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&priority=${priority}&task_date=${date}&task_time=${time}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Task updated successfully!', 'success');
                    closeEditModal();
                    loadTasks();
                    
                    setTimeout(() => {
                        hideAlert();
                    }, 3000);
                } else {
                    showAlert(data.message, 'danger');
                }
            });
        }
        
        // Reset filters
        function resetFilters() {
            document.getElementById('filterDate').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('filterStatus').value = 'all';
            loadTasks();
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
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('editTaskModal');
            if (event.target === modal) {
                closeEditModal();
            }
        };
    </script>
</body>
</html>