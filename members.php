<?php
// members.php - Combined Members Directory
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
            header('Location: members.php');
            exit();
        }
    }
    
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: members.php');
    exit();
}

// Only proceed if logged in
if (!isLoggedIn()) {
    // Show login page
    include 'header.php';
    exit();
}

$db = getDB();
$action = $_GET['action'] ?? 'linkspot';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_linkspot_members':
            $status = $_POST['status'] ?? 'all';
            $search = $_POST['search'] ?? '';
            
            $sql = "SELECT * FROM linkspot_members WHERE 1=1";
            $params = [];
            $types = "";
            
            if ($status !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($search)) {
                $sql .= " AND (full_name LIKE ? OR member_code LIKE ? OR email LIKE ? OR phone LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= "ssss";
            }
            
            $sql .= " ORDER BY full_name";
            
            $stmt = $db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $members = [];
            $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'new' => 0];
            
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
                $stats['total']++;
                if ($row['status'] === 'active') $stats['active']++;
                if ($row['status'] === 'inactive') $stats['inactive']++;
                if ($row['is_new']) $stats['new']++;
            }
            
            echo json_encode(['success' => true, 'data' => $members, 'stats' => $stats]);
            break;
            
        case 'get_summarcity_members':
            $status = $_POST['status'] ?? 'all';
            $search = $_POST['search'] ?? '';
            
            $sql = "SELECT * FROM summarcity_members WHERE 1=1";
            $params = [];
            $types = "";
            
            if ($status !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($search)) {
                $sql .= " AND (full_name LIKE ? OR business_name LIKE ? OR member_code LIKE ? OR email LIKE ? OR phone LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= "sssss";
            }
            
            $sql .= " ORDER BY full_name";
            
            $stmt = $db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $members = [];
            $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'new' => 0];
            
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
                $stats['total']++;
                if ($row['status'] === 'active') $stats['active']++;
                if ($row['status'] === 'inactive') $stats['inactive']++;
                if ($row['is_new']) $stats['new']++;
            }
            
            echo json_encode(['success' => true, 'data' => $members, 'stats' => $stats]);
            break;
            
        case 'update_member_status':
            $type = $_POST['member_type'];
            $memberId = intval($_POST['member_id']);
            $status = $_POST['status'];
            
            $table = $type === 'linkspot' ? 'linkspot_members' : 'summarcity_members';
            $stmt = $db->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $memberId);
            
            if ($stmt->execute()) {
                addActivityLog('Members', "Updated {$type} member status to {$status}", "Member ID: {$memberId}");
                echo json_encode(['success' => true, 'message' => 'Member status updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            break;
            
        case 'mark_as_read':
            $type = $_POST['member_type'];
            $memberId = intval($_POST['member_id']);
            
            $table = $type === 'linkspot' ? 'linkspot_members' : 'summarcity_members';
            $stmt = $db->prepare("UPDATE {$table} SET is_new = 0 WHERE id = ?");
            $stmt->bind_param("i", $memberId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Marked as read']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update']);
            }
            break;
            
        case 'get_member_details':
            $type = $_POST['member_type'];
            $memberId = intval($_POST['member_id']);
            
            $table = $type === 'linkspot' ? 'linkspot_members' : 'summarcity_members';
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result->fetch_assoc();
            
            if ($member) {
                // Get payment history
                $paymentTable = $type === 'linkspot' ? 'linkspot_payments' : 'mall_payments';
                $nameField = $type === 'linkspot' ? 'payer_name' : 'payer_name';
                
                $stmt2 = $db->prepare("SELECT * FROM {$paymentTable} WHERE {$nameField} = ? ORDER BY payment_date DESC LIMIT 10");
                $stmt2->bind_param("s", $member['full_name']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $payments = [];
                while ($row = $result2->fetch_assoc()) {
                    $payments[] = $row;
                }
                
                echo json_encode(['success' => true, 'member' => $member, 'payments' => $payments]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Member not found']);
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
    <title><?php echo SITE_NAME; ?> - Members Directory</title>
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
            --linkspot-color: #3498db;
            --summarcity-color: #9b59b6;
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
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .btn-linkspot {
            background: var(--linkspot-color);
            color: white;
        }
        
        .btn-linkspot:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-summarcity {
            background: var(--summarcity-color);
            color: white;
        }
        
        .btn-summarcity:hover {
            background: #8e44ad;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(155, 89, 182, 0.3);
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
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.linkspot { border-left-color: var(--linkspot-color); }
        .stat-card.summarcity { border-left-color: var(--summarcity-color); }
        
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
        
        /* Filters */
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        
        /* Members Grid */
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .member-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 2px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .member-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .member-card.linkspot {
            border-left: 4px solid var(--linkspot-color);
        }
        
        .member-card.summarcity {
            border-left: 4px solid var(--summarcity-color);
        }
        
        .member-card.new-member {
            background: rgba(255, 193, 7, 0.1);
        }
        
        .member-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .member-code {
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .member-type {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .type-linkspot {
            background: var(--linkspot-color);
            color: white;
        }
        
        .type-summarcity {
            background: var(--summarcity-color);
            color: white;
        }
        
        .new-badge {
            background: #ffc107;
            color: #856404;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .member-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary);
            margin: 0 0 10px 0;
        }
        
        .member-info {
            margin: 10px 0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: 500;
            color: #555;
            min-width: 120px;
        }
        
        .info-value {
            color: #666;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .member-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
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
            max-width: 600px;
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
        
        .payment-history {
            margin-top: 20px;
        }
        
        .payment-item {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
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
            
            .members-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .member-actions {
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
                    <h1><i class="fas fa-users"></i> Members Directory</h1>
                    <p>Manage all members from LinkSpot Spaces and Summarcity Mall</p>
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
            <div class="stats-grid">
                <div class="stat-card linkspot">
                    <div class="stat-value" id="linkspotTotal">0</div>
                    <div class="stat-label">LinkSpot Members</div>
                </div>
                <div class="stat-card summarcity">
                    <div class="stat-value" id="summarcityTotal">0</div>
                    <div class="stat-label">Summarcity Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalNew">0</div>
                    <div class="stat-label">New Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalActive">0</div>
                    <div class="stat-label">Active Members</div>
                </div>
            </div>
            
            <div class="tabs">
                <div class="tab <?php echo $action === 'linkspot' ? 'active' : ''; ?>" onclick="switchTab('linkspot')">
                    LinkSpot Members
                </div>
                <div class="tab <?php echo $action === 'summarcity' ? 'active' : ''; ?>" onclick="switchTab('summarcity')">
                    Summarcity Members
                </div>
            </div>
            
            <!-- LinkSpot Members Tab -->
            <div id="tab-linkspot" class="tab-content" style="<?php echo $action === 'linkspot' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">LinkSpot Spaces Members</h3>
                        <div class="filters">
                            <input type="text" id="linkspotSearch" class="form-control search-box" placeholder="Search members...">
                            <select id="linkspotStatus" class="form-control">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <button class="btn btn-primary" onclick="loadLinkspotMembers()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="btn btn-secondary" onclick="resetLinkspotFilters()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </div>
                    
                    <div class="members-grid" id="linkspotMembersGrid">
                        <!-- LinkSpot members will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Summarcity Members Tab -->
            <div id="tab-summarcity" class="tab-content" style="<?php echo $action === 'summarcity' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Summarcity Mall Members</h3>
                        <div class="filters">
                            <input type="text" id="summarcitySearch" class="form-control search-box" placeholder="Search members...">
                            <select id="summarcityStatus" class="form-control">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <button class="btn btn-primary" onclick="loadSummarcityMembers()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="btn btn-secondary" onclick="resetSummarcityFilters()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </div>
                    
                    <div class="members-grid" id="summarcityMembersGrid">
                        <!-- Summarcity members will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Member Details Modal -->
    <div class="modal" id="memberModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Member Details</h3>
                <button class="btn btn-sm" onclick="closeMemberModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="memberDetails">
                    <!-- Member details will be loaded here -->
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
        
        let currentMemberType = '<?php echo $action; ?>';
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if (currentMemberType === 'linkspot') {
                loadLinkspotMembers();
            } else {
                loadSummarcityMembers();
            }
        });
        
        // Switch tabs
        function switchTab(tab) {
            currentMemberType = tab;
            window.location.href = 'members.php?action=' + tab;
        }
        
        // Load LinkSpot members
        function loadLinkspotMembers() {
            const search = document.getElementById('linkspotSearch').value;
            const status = document.getElementById('linkspotStatus').value;
            
            fetch('members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_linkspot_members&search=${encodeURIComponent(search)}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStats(data.stats, 'linkspot');
                    renderLinkspotMembers(data.data);
                }
            });
        }
        
        // Load Summarcity members
        function loadSummarcityMembers() {
            const search = document.getElementById('summarcitySearch').value;
            const status = document.getElementById('summarcityStatus').value;
            
            fetch('members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_summarcity_members&search=${encodeURIComponent(search)}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStats(data.stats, 'summarcity');
                    renderSummarcityMembers(data.data);
                }
            });
        }
        
        // Update stats
        function updateStats(stats, type) {
            if (type === 'linkspot') {
                document.getElementById('linkspotTotal').textContent = stats.total;
                document.getElementById('totalActive').textContent = stats.active;
                document.getElementById('totalNew').textContent = stats.new;
            } else {
                document.getElementById('summarcityTotal').textContent = stats.total;
                document.getElementById('totalActive').textContent = stats.active;
                document.getElementById('totalNew').textContent = stats.new;
            }
        }
        
        // Render LinkSpot members
        function renderLinkspotMembers(members) {
            const grid = document.getElementById('linkspotMembersGrid');
            
            if (members.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-users"></i>
                        <h3>No members found</h3>
                        <p>Try adjusting your search filters</p>
                    </div>
                `;
                return;
            }
            
            grid.innerHTML = '';
            
            members.forEach(member => {
                const card = document.createElement('div');
                card.className = `member-card linkspot ${member.is_new ? 'new-member' : ''}`;
                card.innerHTML = `
                    <div class="member-header">
                        <span class="member-code">${member.member_code}</span>
                        <div>
                            <span class="member-type type-linkspot">LinkSpot</span>
                            ${member.is_new ? '<span class="new-badge">NEW</span>' : ''}
                        </div>
                    </div>
                    
                    <h3 class="member-name">${member.full_name}</h3>
                    
                    <div class="member-info">
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-${member.status}">${member.status}</span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value">${member.phone || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">${member.email || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Station:</span>
                            <span class="info-value">${member.station_code || 'N/A'}${member.desk_number || ''}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Package:</span>
                            <span class="info-value">${member.package_type}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Monthly Rate:</span>
                            <span class="info-value">$${parseFloat(member.monthly_rate).toFixed(2)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Next Due:</span>
                            <span class="info-value">${member.next_due_date || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Balance:</span>
                            <span class="info-value">$${parseFloat(member.balance).toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div class="member-actions">
                        <button class="btn btn-primary btn-sm" onclick="viewMemberDetails('linkspot', ${member.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${member.is_new ? 
                            `<button class="btn btn-success btn-sm" onclick="markAsRead('linkspot', ${member.id})">
                                <i class="fas fa-check"></i> Mark Read
                            </button>` : ''}
                        ${member.status === 'active' ?
                            `<button class="btn btn-danger btn-sm" onclick="updateMemberStatus('linkspot', ${member.id}, 'inactive')">
                                <i class="fas fa-ban"></i> Deactivate
                            </button>` :
                            `<button class="btn btn-success btn-sm" onclick="updateMemberStatus('linkspot', ${member.id}, 'active')">
                                <i class="fas fa-check"></i> Activate
                            </button>`}
                    </div>
                `;
                grid.appendChild(card);
            });
        }
        
        // Render Summarcity members
        function renderSummarcityMembers(members) {
            const grid = document.getElementById('summarcityMembersGrid');
            
            if (members.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-users"></i>
                        <h3>No members found</h3>
                        <p>Try adjusting your search filters</p>
                    </div>
                `;
                return;
            }
            
            grid.innerHTML = '';
            
            members.forEach(member => {
                const card = document.createElement('div');
                card.className = `member-card summarcity ${member.is_new ? 'new-member' : ''}`;
                card.innerHTML = `
                    <div class="member-header">
                        <span class="member-code">${member.member_code}</span>
                        <div>
                            <span class="member-type type-summarcity">Summarcity</span>
                            ${member.is_new ? '<span class="new-badge">NEW</span>' : ''}
                        </div>
                    </div>
                    
                    <h3 class="member-name">${member.full_name}</h3>
                    ${member.business_name ? `<h4 style="color: #666; margin: 0 0 10px 0;">${member.business_name}</h4>` : ''}
                    
                    <div class="member-info">
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-${member.status}">${member.status}</span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value">${member.phone || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">${member.email || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Shop:</span>
                            <span class="info-value">${member.shop_number || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Business Type:</span>
                            <span class="info-value">${member.business_type || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Rent Amount:</span>
                            <span class="info-value">$${parseFloat(member.rent_amount).toFixed(2)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Next Due:</span>
                            <span class="info-value">${member.next_due_date || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Balance:</span>
                            <span class="info-value">$${parseFloat(member.balance).toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div class="member-actions">
                        <button class="btn btn-primary btn-sm" onclick="viewMemberDetails('summarcity', ${member.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${member.is_new ? 
                            `<button class="btn btn-success btn-sm" onclick="markAsRead('summarcity', ${member.id})">
                                <i class="fas fa-check"></i> Mark Read
                            </button>` : ''}
                        ${member.status === 'active' ?
                            `<button class="btn btn-danger btn-sm" onclick="updateMemberStatus('summarcity', ${member.id}, 'inactive')">
                                <i class="fas fa-ban"></i> Deactivate
                            </button>` :
                            `<button class="btn btn-success btn-sm" onclick="updateMemberStatus('summarcity', ${member.id}, 'active')">
                                <i class="fas fa-check"></i> Activate
                            </button>`}
                    </div>
                `;
                grid.appendChild(card);
            });
        }
        
        // View member details
        function viewMemberDetails(type, memberId) {
            fetch('members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_member_details&member_type=${type}&member_id=${memberId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const member = data.member;
                    let detailsHtml = `
                        <div style="margin-bottom: 20px;">
                            <h4>${member.full_name}</h4>
                            ${member.business_name ? `<p><strong>Business:</strong> ${member.business_name}</p>` : ''}
                            <p><strong>Member Code:</strong> ${member.member_code}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${member.status}">${member.status}</span></p>
                            <p><strong>Phone:</strong> ${member.phone || 'N/A'}</p>
                            <p><strong>Email:</strong> ${member.email || 'N/A'}</p>
                            <p><strong>${type === 'linkspot' ? 'Station' : 'Shop'}:</strong> ${member.station_code || member.shop_number || 'N/A'}${member.desk_number || ''}</p>
                            <p><strong>${type === 'linkspot' ? 'Monthly Rate' : 'Rent Amount'}:</strong> $${parseFloat(type === 'linkspot' ? member.monthly_rate : member.rent_amount).toFixed(2)}</p>
                            <p><strong>Next Due Date:</strong> ${member.next_due_date || 'N/A'}</p>
                            <p><strong>Current Balance:</strong> $${parseFloat(member.balance).toFixed(2)}</p>
                            <p><strong>Member Since:</strong> ${new Date(member.created_at).toLocaleDateString()}</p>
                            ${member.notes ? `<p><strong>Notes:</strong> ${member.notes}</p>` : ''}
                        </div>
                    `;
                    
                    if (data.payments.length > 0) {
                        detailsHtml += `
                            <div class="payment-history">
                                <h5>Recent Payments</h5>
                                ${data.payments.map(payment => `
                                    <div class="payment-item">
                                        <div><strong>Date:</strong> ${payment.payment_date}</div>
                                        <div><strong>Amount:</strong> $${parseFloat(payment.amount).toFixed(2)}</div>
                                        <div><strong>Month Paid:</strong> ${payment.month_paid}</div>
                                        <div><strong>Method:</strong> ${payment.payment_method}</div>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                    }
                    
                    document.getElementById('modalTitle').textContent = `${type === 'linkspot' ? 'LinkSpot' : 'Summarcity'} Member Details`;
                    document.getElementById('memberDetails').innerHTML = detailsHtml;
                    document.getElementById('memberModal').style.display = 'flex';
                }
            });
        }
        
        // Update member status
        function updateMemberStatus(type, memberId, status) {
            if (!confirm(`Are you sure you want to ${status === 'active' ? 'activate' : 'deactivate'} this member?`)) {
                return;
            }
            
            fetch('members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=update_member_status&member_type=${type}&member_id=${memberId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Member status updated successfully');
                    if (type === 'linkspot') {
                        loadLinkspotMembers();
                    } else {
                        loadSummarcityMembers();
                    }
                } else {
                    alert('Failed to update member status');
                }
            });
        }
        
        // Mark as read
        function markAsRead(type, memberId) {
            fetch('members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=mark_as_read&member_type=${type}&member_id=${memberId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (type === 'linkspot') {
                        loadLinkspotMembers();
                    } else {
                        loadSummarcityMembers();
                    }
                }
            });
        }
        
        // Close member modal
        function closeMemberModal() {
            document.getElementById('memberModal').style.display = 'none';
        }
        
        // Reset filters
        function resetLinkspotFilters() {
            document.getElementById('linkspotSearch').value = '';
            document.getElementById('linkspotStatus').value = 'all';
            loadLinkspotMembers();
        }
        
        function resetSummarcityFilters() {
            document.getElementById('summarcitySearch').value = '';
            document.getElementById('summarcityStatus').value = 'all';
            loadSummarcityMembers();
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('memberModal');
            if (event.target === modal) {
                closeMemberModal();
            }
        };
    </script>
</body>
</html>