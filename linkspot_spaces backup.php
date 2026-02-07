<?php
// linkspot_spaces.php - LinkSpot Spaces Management
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
            header('Location: linkspot_spaces.php');
            exit();
        }
    }
    
    $login_error = "Invalid username or password";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: linkspot_spaces.php');
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
$action = $_GET['action'] ?? 'payments';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_available_stations':
            $stations = getAvailableStationAddresses();
            echo json_encode(['success' => true, 'data' => $stations]);
            break;
            
        case 'get_occupied_stations':
            $stmt = $db->prepare("SELECT lsa.*, lm.full_name, lm.next_due_date FROM linkspot_station_addresses lsa LEFT JOIN linkspot_members lm ON lsa.current_user_id = lm.id WHERE lsa.status = 'occupied' ORDER BY lsa.station_code, lsa.desk_number");
            $stmt->execute();
            $result = $stmt->get_result();
            $occupied = [];
            while ($row = $result->fetch_assoc()) {
                $occupied[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $occupied]);
            break;
            
        case 'record_payment':
            $memberId = intval($_POST['member_id']);
            $memberName = $_POST['member_name'];
            $amount = floatval($_POST['amount']);
            $paymentMethod = $_POST['payment_method'];
            $monthPaid = $_POST['month_paid'];
            $stationId = intval($_POST['station_id']);
            $description = $_POST['description'] ?? '';
            
            $db->begin_transaction();
            
            try {
                // Record payment
                $stmt = $db->prepare("INSERT INTO linkspot_payments (payment_date, month_paid, payer_name, amount, payment_method, description, station_address_id) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sddsdi", $monthPaid, $memberName, $amount, $paymentMethod, $description, $stationId);
                $stmt->execute();
                $paymentId = $db->insert_id;
                
                // Update member balance if member exists
                if ($memberId > 0) {
                    $stmt = $db->prepare("UPDATE linkspot_members SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $memberId);
                    $stmt->execute();
                }
                
                // Update station if assigned
                if ($stationId > 0) {
                    $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'occupied', current_user_id = ?, current_user_name = ?, occupation_start = NOW() WHERE id = ?");
                    $memberId = $memberId ?: 0;
                    $stmt->bind_param("isi", $memberId, $memberName, $stationId);
                    $stmt->execute();
                }
                
                // Add activity log
                addActivityLog('Linkspot Spaces', "Recorded payment from {$memberName} for {$monthPaid}", "Amount: \${$amount}, Station ID: {$stationId}");
                
                // Send notification
                sendNotification('linkspot_payment', 'New Payment Recorded', "Payment of \${$amount} from {$memberName} for {$monthPaid}");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully', 'payment_id' => $paymentId]);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_members':
            $stmt = $db->prepare("SELECT * FROM linkspot_members WHERE status = 'active' ORDER BY full_name");
            $stmt->execute();
            $result = $stmt->get_result();
            $members = [];
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $members]);
            break;
            
        case 'add_member':
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $idNumber = $_POST['id_number'] ?? '';
            $packageType = $_POST['package_type'];
            $monthlyRate = floatval($_POST['monthly_rate']);
            $stationId = intval($_POST['station_id']);
            $notes = $_POST['notes'] ?? '';
            $isNew = isset($_POST['is_new']) ? 1 : 0;
            
            $db->begin_transaction();
            
            try {
                // Generate member code
                $memberCode = generateMemberCode('LS');
                
                // Calculate next due date (1 month from now)
                $nextDueDate = date('Y-m-d', strtotime('+1 month'));
                
                // Get station details if assigned
                $stationCode = '';
                $deskNumber = '';
                if ($stationId > 0) {
                    $stmt = $db->prepare("SELECT station_code, desk_number FROM linkspot_station_addresses WHERE id = ?");
                    $stmt->bind_param("i", $stationId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($station = $result->fetch_assoc()) {
                        $stationCode = $station['station_code'];
                        $deskNumber = $station['desk_number'];
                        
                        // Update station status
                        $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'occupied', current_user_id = LAST_INSERT_ID(), current_user_name = ?, occupation_start = NOW() WHERE id = ?");
                        $stmt->bind_param("si", $fullName, $stationId);
                        $stmt->execute();
                    }
                }
                
                // Insert member
                $stmt = $db->prepare("INSERT INTO linkspot_members (member_code, full_name, email, phone, id_number, package_type, monthly_rate, station_address_id, station_code, desk_number, next_due_date, is_new, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssdissisi", $memberCode, $fullName, $email, $phone, $idNumber, $packageType, $monthlyRate, $stationId, $stationCode, $deskNumber, $nextDueDate, $isNew, $notes);
                $stmt->execute();
                $memberId = $db->insert_id;
                
                // Add activity log
                addActivityLog('Linkspot Members', "Added new member: {$fullName}", "Code: {$memberCode}, Package: {$packageType}, Rate: \${$monthlyRate}");
                
                // Send notification for new member
                if ($isNew) {
                    sendNotification('new_member', 'New Member Added', "New member {$fullName} ({$memberCode}) has been added to LinkSpot Spaces");
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Member added successfully', 'member_id' => $memberId, 'member_code' => $memberCode]);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'release_station':
            $stationId = intval($_POST['station_id']);
            
            $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'available', current_user_id = NULL, current_user_name = NULL, occupation_start = NULL, occupation_end = NULL WHERE id = ?");
            $stmt->bind_param("i", $stationId);
            
            if ($stmt->execute()) {
                addActivityLog('Linkspot Spaces', "Released station ID {$stationId}");
                echo json_encode(['success' => true, 'message' => 'Station released successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to release station']);
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
    <title><?php echo SITE_NAME; ?> - LinkSpot Spaces</title>
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
        
        /* Station Grid */
        .station-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        
        .station-card {
            padding: 12px 5px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 14px;
            border: 2px solid transparent;
        }
        
        .station-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .station-card.available {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .station-card.occupied {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            cursor: not-allowed;
        }
        
        .station-card.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        /* Member Grid */
        .member-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .member-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #dee2e6;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .member-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .member-card.new-member {
            border-color: var(--primary);
            background: rgba(39, 174, 96, 0.05);
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
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .new-badge {
            background: #ffc107;
            color: #856404;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
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
            
            .station-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .member-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                margin: 0 -20px;
                width: calc(100% + 40px);
                border-radius: 0;
                border-left: none;
                border-right: none;
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
                    <h1><i class="fas fa-link"></i> LinkSpot Spaces</h1>
                    <p>Manage members, spaces, and payments</p>
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
                <div class="tab <?php echo $action === 'payments' ? 'active' : ''; ?>" onclick="switchTab('payments')">
                    Record Payments
                </div>
                <div class="tab <?php echo $action === 'members' ? 'active' : ''; ?>" onclick="switchTab('members')">
                    Members
                </div>
                <div class="tab <?php echo $action === 'spaces' ? 'active' : ''; ?>" onclick="switchTab('spaces')">
                    Space Management
                </div>
            </div>
            
            <!-- Record Payments Tab -->
            <div id="tab-payments" class="tab-content" style="<?php echo $action === 'payments' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Record Payment</h3>
                    </div>
                    
                    <div class="alert" id="alertMessage"></div>
                    
                    <div class="form-group">
                        <label class="form-label">Select Member</label>
                        <select id="memberSelect" class="form-control" onchange="loadMemberDetails()">
                            <option value="">-- Select Member or Enter New Name --</option>
                        </select>
                        <small class="text-muted">Or enter a new name below</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" id="memberName" class="form-control" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount *</label>
                            <input type="number" id="paymentAmount" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Month Paid *</label>
                            <input type="text" id="monthPaid" class="form-control" placeholder="e.g. January 2024" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Method *</label>
                            <select id="paymentMethod" class="form-control">
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assign Station (Optional)</label>
                        <div class="station-grid" id="stationGrid">
                            <!-- Stations will be loaded here -->
                        </div>
                        <small class="text-muted">Select an available station to assign</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description (Optional)</label>
                        <input type="text" id="paymentDescription" class="form-control" placeholder="Enter description">
                    </div>
                    
                    <button class="btn btn-primary" onclick="recordPayment()">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                </div>
            </div>
            
            <!-- Members Tab -->
            <div id="tab-members" class="tab-content" style="<?php echo $action === 'members' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Member</h3>
                    </div>
                    
                    <div class="alert" id="memberAlert"></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" id="newMemberName" class="form-control" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" id="newMemberEmail" class="form-control" placeholder="Enter email">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" id="newMemberPhone" class="form-control" placeholder="Enter phone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ID Number</label>
                            <input type="text" id="newMemberId" class="form-control" placeholder="Enter ID number">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Package Type *</label>
                            <select id="packageType" class="form-control">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monthly Rate *</label>
                            <input type="number" id="monthlyRate" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assign Station (Optional)</label>
                        <div class="station-grid" id="newMemberStations">
                            <!-- Stations will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea id="memberNotes" class="form-control" rows="3" placeholder="Enter notes"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="isNewMember"> Mark as New Member (Send Notification)
                        </label>
                    </div>
                    
                    <button class="btn btn-success" onclick="addMember()">
                        <i class="fas fa-user-plus"></i> Add Member
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Active Members</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadMembers()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="member-grid" id="membersGrid">
                        <!-- Members will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Space Management Tab -->
            <div id="tab-spaces" class="tab-content" style="<?php echo $action === 'spaces' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Occupied Spaces</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadOccupiedStations()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th>Occupant</th>
                                    <th>Status</th>
                                    <th>Since</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="occupiedStationsBody">
                                <!-- Occupied stations will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Stations</h3>
                    </div>
                    
                    <div class="station-grid" id="allStationsGrid">
                        <!-- All stations will be loaded here -->
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
        
        let selectedStation = null;
        let selectedStationForMember = null;
        let members = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadStations();
            loadMembersList();
            
            if ('<?php echo $action; ?>' === 'members') {
                loadMembers();
                loadStationsForMember();
            }
            
            if ('<?php echo $action; ?>' === 'spaces') {
                loadOccupiedStations();
                loadAllStations();
            }
        });
        
        // Switch tabs
        function switchTab(tab) {
            window.location.href = 'linkspot_spaces.php?action=' + tab;
        }
        
        // Load stations for payment tab
        function loadStations() {
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_available_stations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('stationGrid');
                    if (grid) {
                        grid.innerHTML = '';
                        displayStations(data.data, grid, 'payment');
                    }
                }
            });
        }
        
        // Load stations for member tab
        function loadStationsForMember() {
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_available_stations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('newMemberStations');
                    if (grid) {
                        grid.innerHTML = '';
                        displayStations(data.data, grid, 'member');
                    }
                }
            });
        }
        
        // Display stations in grid
        function displayStations(stations, container, type) {
            // Group stations by code
            const grouped = {};
            stations.forEach(station => {
                if (!grouped[station.station_code]) {
                    grouped[station.station_code] = [];
                }
                grouped[station.station_code].push(station);
            });
            
            // Display stations
            for (const [code, stationList] of Object.entries(grouped)) {
                stationList.forEach(station => {
                    const card = document.createElement('div');
                    card.className = 'station-card available';
                    card.dataset.id = station.id;
                    card.innerHTML = `${code}${station.desk_number}`;
                    
                    if (type === 'payment') {
                        card.onclick = () => selectStationForPayment(station.id);
                    } else if (type === 'member') {
                        card.onclick = () => selectStationForMember(station.id);
                    }
                    
                    container.appendChild(card);
                });
            }
        }
        
        // Load members for dropdown
        function loadMembersList() {
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_members'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    members = data.data;
                    const select = document.getElementById('memberSelect');
                    select.innerHTML = '<option value="">-- Select Member or Enter New Name --</option>';
                    
                    data.data.forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = `${member.full_name} (${member.member_code})`;
                        select.appendChild(option);
                    });
                }
            });
        }
        
        // Load all members for display
        function loadMembers() {
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_members'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('membersGrid');
                    grid.innerHTML = '';
                    
                    data.data.forEach(member => {
                        const card = document.createElement('div');
                        card.className = `member-card ${member.is_new ? 'new-member' : ''}`;
                        card.innerHTML = `
                            <div class="member-header">
                                <div class="member-code">${member.member_code}</div>
                                ${member.is_new ? '<div class="new-badge">NEW</div>' : ''}
                            </div>
                            <h4 style="margin: 0 0 10px 0;">${member.full_name}</h4>
                            <p style="margin: 5px 0;"><strong>Phone:</strong> ${member.phone || 'N/A'}</p>
                            <p style="margin: 5px 0;"><strong>Email:</strong> ${member.email || 'N/A'}</p>
                            <p style="margin: 5px 0;"><strong>Package:</strong> ${member.package_type}</p>
                            <p style="margin: 5px 0;"><strong>Rate:</strong> $${parseFloat(member.monthly_rate).toFixed(2)}/month</p>
                            <p style="margin: 5px 0;"><strong>Station:</strong> ${member.station_code || 'N/A'}${member.desk_number || ''}</p>
                            <p style="margin: 5px 0;"><strong>Due Date:</strong> ${member.next_due_date}</p>
                            <p style="margin: 5px 0;"><strong>Balance:</strong> $${parseFloat(member.balance).toFixed(2)}</p>
                        `;
                        grid.appendChild(card);
                    });
                }
            });
        }
        
        // Load occupied stations
        function loadOccupiedStations() {
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_occupied_stations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('occupiedStationsBody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(station => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${station.station_code}${station.desk_number}</td>
                            <td>${station.current_user_name || 'N/A'}</td>
                            <td><span class="badge badge-danger">Occupied</span></td>
                            <td>${station.occupation_start ? new Date(station.occupation_start).toLocaleDateString() : 'N/A'}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="releaseStation(${station.id})">
                                    <i class="fas fa-times"></i> Release
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            });
        }
        
        // Load all stations
        function loadAllStations() {
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_available_stations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const grid = document.getElementById('allStationsGrid');
                    grid.innerHTML = '';
                    
                    // Add occupied stations too
                    const allStations = [...data.data];
                    
                    // Display all stations
                    allStations.forEach(station => {
                        const card = document.createElement('div');
                        card.className = 'station-card available';
                        card.innerHTML = `${station.station_code}${station.desk_number}`;
                        grid.appendChild(card);
                    });
                }
            });
        }
        
        // Select station for payment
        function selectStationForPayment(stationId) {
            if (selectedStation === stationId) {
                selectedStation = null;
            } else {
                selectedStation = stationId;
            }
            updateStationSelection('stationGrid');
        }
        
        // Select station for member
        function selectStationForMember(stationId) {
            if (selectedStationForMember === stationId) {
                selectedStationForMember = null;
            } else {
                selectedStationForMember = stationId;
            }
            updateStationSelection('newMemberStations');
        }
        
        // Update station selection UI
        function updateStationSelection(gridId) {
            const cards = document.querySelectorAll(`#${gridId} .station-card`);
            cards.forEach(card => {
                card.classList.remove('selected');
                const stationId = parseInt(card.dataset.id);
                if (stationId === selectedStation || stationId === selectedStationForMember) {
                    card.classList.add('selected');
                }
            });
        }
        
        // Load member details when selected
        function loadMemberDetails() {
            const select = document.getElementById('memberSelect');
            const memberId = select.value;
            const nameInput = document.getElementById('memberName');
            
            if (memberId) {
                const member = members.find(m => m.id == memberId);
                if (member) {
                    nameInput.value = member.full_name;
                }
            } else {
                nameInput.value = '';
            }
        }
        
        // Record payment
        function recordPayment() {
            const memberId = document.getElementById('memberSelect').value;
            const memberName = document.getElementById('memberName').value;
            const amount = document.getElementById('paymentAmount').value;
            const monthPaid = document.getElementById('monthPaid').value;
            const paymentMethod = document.getElementById('paymentMethod').value;
            const description = document.getElementById('paymentDescription').value;
            
            if (!memberName || !amount || !monthPaid) {
                showAlert('Please fill all required fields', 'danger');
                return;
            }
            
            if (confirm('Record this payment?')) {
                fetch('linkspot_spaces.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=record_payment&member_id=${memberId}&member_name=${encodeURIComponent(memberName)}&amount=${amount}&month_paid=${encodeURIComponent(monthPaid)}&payment_method=${encodeURIComponent(paymentMethod)}&description=${encodeURIComponent(description)}&station_id=${selectedStation || 0}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Payment recorded successfully!', 'success');
                        // Reset form
                        document.getElementById('memberSelect').value = '';
                        document.getElementById('memberName').value = '';
                        document.getElementById('paymentAmount').value = '';
                        document.getElementById('monthPaid').value = '';
                        document.getElementById('paymentDescription').value = '';
                        selectedStation = null;
                        updateStationSelection('stationGrid');
                        loadStations();
                        
                        setTimeout(() => {
                            hideAlert();
                        }, 3000);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                });
            }
        }
        
        // Add new member
        function addMember() {
            const fullName = document.getElementById('newMemberName').value;
            const email = document.getElementById('newMemberEmail').value;
            const phone = document.getElementById('newMemberPhone').value;
            const idNumber = document.getElementById('newMemberId').value;
            const packageType = document.getElementById('packageType').value;
            const monthlyRate = document.getElementById('monthlyRate').value;
            const notes = document.getElementById('memberNotes').value;
            const isNew = document.getElementById('isNewMember').checked;
            
            if (!fullName || !monthlyRate) {
                showMemberAlert('Please fill all required fields', 'danger');
                return;
            }
            
            if (confirm('Add this member?')) {
                fetch('linkspot_spaces.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=add_member&full_name=${encodeURIComponent(fullName)}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&id_number=${encodeURIComponent(idNumber)}&package_type=${packageType}&monthly_rate=${monthlyRate}&station_id=${selectedStationForMember || 0}&notes=${encodeURIComponent(notes)}&is_new=${isNew ? 1 : 0}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMemberAlert(`Member added successfully! Member Code: ${data.member_code}`, 'success');
                        // Reset form
                        document.getElementById('newMemberName').value = '';
                        document.getElementById('newMemberEmail').value = '';
                        document.getElementById('newMemberPhone').value = '';
                        document.getElementById('newMemberId').value = '';
                        document.getElementById('monthlyRate').value = '';
                        document.getElementById('memberNotes').value = '';
                        document.getElementById('isNewMember').checked = false;
                        selectedStationForMember = null;
                        updateStationSelection('newMemberStations');
                        
                        // Refresh lists
                        loadMembersList();
                        loadMembers();
                        loadStationsForMember();
                        
                        setTimeout(() => {
                            hideMemberAlert();
                        }, 5000);
                    } else {
                        showMemberAlert(data.message, 'danger');
                    }
                });
            }
        }
        
        // Release station
        function releaseStation(stationId) {
            if (!confirm('Release this station?')) {
                return;
            }
            
            fetch('linkspot_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=release_station&station_id=${stationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadOccupiedStations();
                    loadStations();
                    loadStationsForMember();
                } else {
                    alert(data.message);
                }
            });
        }
        
        // Show/hide alerts
        function showAlert(message, type) {
            const alert = document.getElementById('alertMessage');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function hideAlert() {
            document.getElementById('alertMessage').style.display = 'none';
        }
        
        function showMemberAlert(message, type) {
            const alert = document.getElementById('memberAlert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function hideMemberAlert() {
            document.getElementById('memberAlert').style.display = 'none';
        }
    </script>
</body>
</html>