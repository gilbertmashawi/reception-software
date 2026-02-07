<?php
// index.php - Main Dashboard
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
            header('Location: index.php');
            exit();
        }
    }
    
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get dashboard statistics
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
    
    // // Today's revenue
    // $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE sale_date = ?";
    // $stmt = $db->prepare($query);
    // $stmt->bind_param("s", $today);
    // $stmt->execute();
    // $result = $stmt->get_result();
    // $row = $result->fetch_assoc();
    // $stats['total_revenue_today'] += $row['total'] ?? 0;
$query = "
    SELECT 
        (
            SELECT COALESCE(SUM(total_amount), 0)
            FROM voucher_sales
            WHERE sale_date = ?
        ) +
        (
            SELECT COALESCE(SUM(cost), 0)
            FROM meeting_rooms
            WHERE start_date = ?
        ) +
        (
            SELECT COALESCE(SUM(amount), 0)
            FROM linkspot_payments
            WHERE payment_date = ?
        ) +
        (
            SELECT COALESCE(SUM(amount), 0)
            FROM mall_payments
            WHERE payment_date = ?
        ) AS total_revenue
";

$stmt = $db->prepare($query);
$stmt->bind_param("ssss", $today, $today, $today, $today);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$stats['total_revenue_today'] = $row['total_revenue'] ?? 0;


    
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

$stats = isLoggedIn() ? getDashboardStats() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Dashboard</title>
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
        
        /* Tables */
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: var(--secondary);
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
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
        }
    </style>
</head>
<body>
    <?php if (!isLoggedIn()): ?>
    <!-- Login Page -->
    <div class="login-page">
        <div class="login-container">
            <div class="login-header">
                <img src="https://tse4.mm.bing.net/th/id/OIP.G4ovr1INXV-IZq5y8PJshgHaHa?rs=1&pid=ImgDetMain&o=7&rm=3" 
                     alt="LinkSpot Logo">
                <h1>LinkSpot Reception</h1>
                <p>Login to continue</p>
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
<!--                     <p>Use: gilbert, teddy, walter, tafadzwa, or admin</p>
                    <p>Password: same as username (except admin: admin123)</p> -->
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
                    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                    <p>Welcome back, <?php echo $_SESSION['full_name']; ?>! Here's what's happening today.</p>
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
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value"><?php echo formatCurrency($stats['total_revenue_today']); ?></div>
                    <div class="stat-label">Today's Revenue</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['active_members']; ?></div>
                    <div class="stat-label">Active Members</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['pending_tasks']; ?></div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?php echo count($stats['payment_reminders']); ?></div>
                    <div class="stat-label">Payment Reminders</div>
                </div>
            </div>
            
            <!-- Occupied Spaces -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-desktop"></i> Currently Occupied Spaces
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Station</th>
                                <th>Customer</th>
                                <th>Voucher Type</th>
                                <th>Remaining Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stats['occupied_spaces'])): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #6c757d;">
                                    No occupied spaces at the moment
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($stats['occupied_spaces'] as $space): ?>
                            <tr>
                                <td><?php echo $space['station'] ?? 'N/A'; ?></td>
                                <td><?php echo $space['customer_name'] ?? 'Walk-in'; ?></td>
                                <td><?php echo $space['voucher_type'] ?? 'N/A'; ?></td>
                                <td>
                                    <?php if (isset($space['remaining_minutes']) && $space['remaining_minutes'] <= 5): ?>
                                    <span class="badge badge-danger"><?php echo $space['remaining_time']; ?> remaining</span>
                                    <?php elseif (isset($space['remaining_minutes'])): ?>
                                    <span class="badge badge-warning"><?php echo $space['remaining_time']; ?> remaining</span>
                                    <?php else: ?>
                                    <span class="badge badge-info">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($space['remaining_minutes']) && $space['remaining_minutes'] <= 5): ?>
                                    <span class="badge badge-danger">Almost Expired</span>
                                    <?php else: ?>
                                    <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i> Recent Activity
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stats['recent_activity'])): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #6c757d;">
                                    No recent activity
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($activity['created_at'])); ?></td>
                                <td><span class="badge badge-info"><?php echo $activity['activity_type']; ?></span></td>
                                <td><?php echo $activity['description']; ?></td>
                                <td><?php echo $activity['username'] ?? 'System'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Payment Reminders -->
            <?php if (!empty($stats['payment_reminders'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-circle"></i> Payment Reminders
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Type</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['payment_reminders'] as $reminder): ?>
                            <tr>
                                <td><?php echo $reminder['title']; ?></td>
                                <td>
                                    <?php if ($reminder['type'] === 'linkspot_payment'): ?>
                                    <span class="badge badge-info">LinkSpot</span>
                                    <?php else: ?>
                                    <span class="badge badge-warning">Summarcity</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $reminder['due_date']; ?></td>
                                <td>
                                    <?php 
                                    $daysLeft = floor((strtotime($reminder['due_date']) - time()) / (60 * 60 * 24));
                                    if ($daysLeft <= 0) {
                                        echo '<span class="badge badge-danger">Overdue</span>';
                                    } elseif ($daysLeft <= 3) {
                                        echo '<span class="badge badge-warning">' . $daysLeft . ' days</span>';
                                    } else {
                                        echo '<span class="badge badge-success">' . $daysLeft . ' days</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
        
        // Auto-refresh dashboard every 30 seconds
        setInterval(() => {
            if (window.location.pathname.endsWith('index.php')) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>