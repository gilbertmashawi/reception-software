<?php
// analytics.php - Analytics and Reports
require_once 'config.php';
requireLogin();

$action = $_GET['type'] ?? 'revenue';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_revenue_data':
            $period = $_POST['period'] ?? 'month';
            $startDate = $_POST['start_date'] ?? date('Y-m-01');
            $endDate = $_POST['end_date'] ?? date('Y-m-t');
            
            $data = [
                'labels' => [],
                'vouchers' => [],
                'linkspot' => [],
                'summarcity' => [],
                'meeting_rooms' => [],
                'total' => []
            ];
            
            if ($period === 'day') {
                // Daily data for last 30 days
                for ($i = 29; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $data['labels'][] = date('M d', strtotime($date));
                    
                    // Voucher sales
                    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE sale_date = ?");
                    $stmt->bind_param("s", $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['vouchers'][] = floatval($row['total']);
                    
                    // Linkspot payments
                    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM linkspot_payments WHERE payment_date = ?");
                    $stmt->bind_param("s", $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['linkspot'][] = floatval($row['total']);
                    
                    // Summarcity payments
                    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM mall_payments WHERE payment_date = ?");
                    $stmt->bind_param("s", $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['summarcity'][] = floatval($row['total']);
                    
                    // Meeting rooms
                    $stmt = $db->prepare("SELECT COALESCE(SUM(cost), 0) as total FROM meeting_rooms WHERE start_date = ?");
                    $stmt->bind_param("s", $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['meeting_rooms'][] = floatval($row['total']);
                    
                    // Total
                    $total = $data['vouchers'][$i] + $data['linkspot'][$i] + $data['summarcity'][$i] + $data['meeting_rooms'][$i];
                    $data['total'][] = $total;
                }
            } elseif ($period === 'month') {
                // Monthly data for last 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $month = date('Y-m', strtotime("-$i months"));
                    $data['labels'][] = date('M Y', strtotime($month . '-01'));
                    
                    // Voucher sales
                    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
                    $stmt->bind_param("s", $month);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['vouchers'][] = floatval($row['total']);
                    
                    // Linkspot payments
                    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM linkspot_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
                    $stmt->bind_param("s", $month);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['linkspot'][] = floatval($row['total']);
                    
                    // Summarcity payments
                    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM mall_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
                    $stmt->bind_param("s", $month);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['summarcity'][] = floatval($row['total']);
                    
                    // Meeting rooms
                    $stmt = $db->prepare("SELECT COALESCE(SUM(cost), 0) as total FROM meeting_rooms WHERE DATE_FORMAT(start_date, '%Y-%m') = ?");
                    $stmt->bind_param("s", $month);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $data['meeting_rooms'][] = floatval($row['total']);
                    
                    // Total
                    $total = $data['vouchers'][$i] + $data['linkspot'][$i] + $data['summarcity'][$i] + $data['meeting_rooms'][$i];
                    $data['total'][] = $total;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_occupancy_data':
            $date = $_POST['date'] ?? date('Y-m-d');
            
            // Get station occupancy
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available
                FROM linkspot_station_addresses");
            $stmt->execute();
            $result = $stmt->get_result();
            $stationData = $result->fetch_assoc();
            
            // Get shop occupancy
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available
                FROM summarcity_shops");
            $stmt->execute();
            $result = $stmt->get_result();
            $shopData = $result->fetch_assoc();
            
            // Get meeting room bookings for today
            $stmt = $db->prepare("SELECT COUNT(*) as bookings FROM meeting_rooms WHERE start_date = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $meetingData = $result->fetch_assoc();
            
            // Get active voucher sessions
            $occupied = getOccupiedSpacesWithRemainingTime();
            $activeSessions = count($occupied);
            
            $data = [
                'stations' => [
                    'total' => $stationData['total'] ?? 0,
                    'occupied' => $stationData['occupied'] ?? 0,
                    'available' => $stationData['available'] ?? 0,
                    'occupancy_rate' => $stationData['total'] > 0 ? round(($stationData['occupied'] / $stationData['total']) * 100, 1) : 0
                ],
                'shops' => [
                    'total' => $shopData['total'] ?? 0,
                    'occupied' => $shopData['occupied'] ?? 0,
                    'available' => $shopData['available'] ?? 0,
                    'occupancy_rate' => $shopData['total'] > 0 ? round(($shopData['occupied'] / $shopData['total']) * 100, 1) : 0
                ],
                'meeting_rooms' => [
                    'bookings' => $meetingData['bookings'] ?? 0
                ],
                'active_sessions' => $activeSessions
            ];
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_member_analytics':
            // Linkspot member stats
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN is_new = 1 THEN 1 ELSE 0 END) as new,
                COALESCE(SUM(balance), 0) as total_balance,
                COALESCE(AVG(monthly_rate), 0) as avg_monthly_rate
                FROM linkspot_members");
            $stmt->execute();
            $result = $stmt->get_result();
            $linkspotData = $result->fetch_assoc();
            
            // Summarcity member stats
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN is_new = 1 THEN 1 ELSE 0 END) as new,
                COALESCE(SUM(balance), 0) as total_balance,
                COALESCE(AVG(rent_amount), 0) as avg_rent
                FROM summarcity_members");
            $stmt->execute();
            $result = $stmt->get_result();
            $summarcityData = $result->fetch_assoc();
            
            // Member growth (last 6 months)
            $growthData = ['labels' => [], 'linkspot' => [], 'summarcity' => []];
            for ($i = 5; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $growthData['labels'][] = date('M', strtotime($month . '-01'));
                
                // Linkspot growth
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM linkspot_members WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
                $stmt->bind_param("s", $month);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $growthData['linkspot'][] = $row['count'] ?? 0;
                
                // Summarcity growth
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM summarcity_members WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
                $stmt->bind_param("s", $month);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $growthData['summarcity'][] = $row['count'] ?? 0;
            }
            
            $data = [
                'linkspot' => $linkspotData,
                'summarcity' => $summarcityData,
                'growth' => $growthData,
                'total_members' => ($linkspotData['total'] ?? 0) + ($summarcityData['total'] ?? 0),
                'total_balance' => ($linkspotData['total_balance'] ?? 0) + ($summarcityData['total_balance'] ?? 0)
            ];
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_top_metrics':
            $today = date('Y-m-d');
            $month = date('Y-m');
            
            // Today's revenue
            $revenueToday = 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE sale_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueToday += $row['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM linkspot_payments WHERE payment_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueToday += $row['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM mall_payments WHERE payment_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueToday += $row['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(cost), 0) as total FROM meeting_rooms WHERE start_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueToday += $row['total'] ?? 0;
            
            // Monthly revenue
            $revenueMonth = 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueMonth += $row['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM linkspot_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueMonth += $row['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM mall_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueMonth += $row['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(cost), 0) as total FROM meeting_rooms WHERE DATE_FORMAT(start_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $revenueMonth += $row['total'] ?? 0;
            
            // Active members
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM linkspot_members WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->get_result();
            $activeLinkspot = $result->fetch_assoc()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM summarcity_members WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->get_result();
            $activeSummarcity = $result->fetch_assoc()['total'] ?? 0;
            $activeMembers = $activeLinkspot + $activeSummarcity;
            
            // Occupancy rate
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied
                FROM linkspot_station_addresses");
            $stmt->execute();
            $result = $stmt->get_result();
            $stationData = $result->fetch_assoc();
            $occupancyRate = $stationData['total'] > 0 ? round(($stationData['occupied'] / $stationData['total']) * 100, 1) : 0;
            
            // Today's tasks completion
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as completed
                FROM tasks WHERE task_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $taskData = $result->fetch_assoc();
            $taskCompletion = $taskData['total'] > 0 ? round(($taskData['completed'] / $taskData['total']) * 100, 1) : 100;
            
            $metrics = [
                'revenue_today' => $revenueToday,
                'revenue_month' => $revenueMonth,
                'active_members' => $activeMembers,
                'occupancy_rate' => $occupancyRate,
                'task_completion' => $taskCompletion
            ];
            
            echo json_encode(['success' => true, 'metrics' => $metrics]);
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Card Styles */
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
        }
        
        .tab:hover {
            color: var(--primary);
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
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
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-lg {
            padding: 12px 25px;
            font-size: 16px;
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
        
        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: block;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Tables */
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
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
        }
        
        /* Analytics Color Variables */
        :root {
            --linkspot-color: #3498db;
            --summarcity-color: #9b59b6;
            --voucher-color: #e74c3c;
            --meeting-color: #f39c12;
        }
        
        /* Filters */
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        /* Analytics Stats Grid */
        .analytics-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .analytics-stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
        }
        
        .analytics-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .analytics-stat-title {
            font-weight: 600;
            color: var(--secondary);
            margin: 0;
        }
        
        .analytics-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Details Rows */
        .stat-details {
            margin-top: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        /* Legend */
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        
        .color-vouchers { background: var(--voucher-color); }
        .color-linkspot { background: var(--linkspot-color); }
        .color-summarcity { background: var(--summarcity-color); }
        .color-meeting { background: var(--meeting-color); }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
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
            
            .analytics-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .tab {
                white-space: nowrap;
            }
            
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-page">
        <!-- Include the header/sidebar from header.php -->
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
                    <p>Track performance, revenue, and occupancy metrics</p>
                </div>
                <div class="user-menu">
                    <div class="notification-badge">
                        <i class="fas fa-bell"></i>
                        <?php 
                        $unreadCount = getUnreadNotificationsCount();
                        if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
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
            
            <!-- Top Metrics -->
            <div class="stats-grid" id="topMetrics">
                <!-- Metrics will be loaded here -->
            </div>
            
            <div class="tabs">
                <div class="tab <?php echo $action === 'revenue' ? 'active' : ''; ?>" onclick="switchTab('revenue')">
                    Revenue Reports
                </div>
                <div class="tab <?php echo $action === 'occupancy' ? 'active' : ''; ?>" onclick="switchTab('occupancy')">
                    Occupancy Reports
                </div>
                <div class="tab <?php echo $action === 'members' ? 'active' : ''; ?>" onclick="switchTab('members')">
                    Member Analytics
                </div>
            </div>
            
            <!-- Revenue Reports Tab -->
            <div id="tab-revenue" class="tab-content <?php echo $action === 'revenue' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Revenue Analysis</h3>
                        <div class="filters">
                            <select id="revenuePeriod" class="form-control" onchange="loadRevenueData()">
                                <option value="day">Last 30 Days</option>
                                <option value="month">Last 12 Months</option>
                            </select>
                            <input type="date" id="revenueStartDate" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                            <input type="date" id="revenueEndDate" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                            <button class="btn btn-primary" onclick="loadRevenueData()">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                    
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color color-vouchers"></div>
                            <span>Internet Vouchers</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color color-linkspot"></div>
                            <span>LinkSpot Spaces</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color color-summarcity"></div>
                            <span>Summarcity Mall</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color color-meeting"></div>
                            <span>Meeting Rooms</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--primary);"></div>
                            <span>Total Revenue</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Occupancy Reports Tab -->
            <div id="tab-occupancy" class="tab-content <?php echo $action === 'occupancy' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Occupancy Analysis</h3>
                        <div class="filters">
                            <input type="date" id="occupancyDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            <button class="btn btn-primary" onclick="loadOccupancyData()">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                        </div>
                    </div>
                    
                    <div class="analytics-stats-grid">
                        <div class="analytics-stat-card">
                            <div class="analytics-stat-header">
                                <h4 class="analytics-stat-title">LinkSpot Stations</h4>
                                <div class="analytics-stat-value" id="stationOccupancyRate">0%</div>
                            </div>
                            <div class="stat-details">
                                <div class="detail-row">
                                    <span class="detail-label">Total Stations</span>
                                    <span class="detail-value" id="totalStations">0</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Occupied</span>
                                    <span class="detail-value" id="occupiedStations">0</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Available</span>
                                    <span class="detail-value" id="availableStations">0</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="analytics-stat-card">
                            <div class="analytics-stat-header">
                                <h4 class="analytics-stat-title">Summarcity Shops</h4>
                                <div class="analytics-stat-value" id="shopOccupancyRate">0%</div>
                            </div>
                            <div class="stat-details">
                                <div class="detail-row">
                                    <span class="detail-label">Total Shops</span>
                                    <span class="detail-value" id="totalShops">0</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Occupied</span>
                                    <span class="detail-value" id="occupiedShops">0</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Available</span>
                                    <span class="detail-value" id="availableShops">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="occupancyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Member Analytics Tab -->
            <div id="tab-members" class="tab-content <?php echo $action === 'members' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Member Analytics</h3>
                    </div>
                    
                    <div class="analytics-stats-grid" style="margin-bottom: 30px;">
                        <div class="analytics-stat-card">
                            <div class="analytics-stat-header">
                                <h4 class="analytics-stat-title">LinkSpot Members</h4>
                            </div>
                            <div class="stat-details" id="linkspotStats">
                                <!-- Stats will be loaded here -->
                            </div>
                        </div>
                        
                        <div class="analytics-stat-card">
                            <div class="analytics-stat-header">
                                <h4 class="analytics-stat-title">Summarcity Members</h4>
                            </div>
                            <div class="stat-details" id="summarcityStats">
                                <!-- Stats will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="memberGrowthChart"></canvas>
                    </div>
                    
                    <div class="legend" style="margin-top: 20px;">
                        <div class="legend-item">
                            <div class="legend-color color-linkspot"></div>
                            <span>LinkSpot Members</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color color-summarcity"></div>
                            <span>Summarcity Members</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let revenueChart = null;
        let occupancyChart = null;
        let memberGrowthChart = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadTopMetrics();
            
            if ('<?php echo $action; ?>' === 'revenue') {
                loadRevenueData();
            } else if ('<?php echo $action; ?>' === 'occupancy') {
                loadOccupancyData();
            } else if ('<?php echo $action; ?>' === 'members') {
                loadMemberAnalytics();
            }
        });
        
        // Switch tabs
        function switchTab(tab) {
            // Update active tab visually
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector(`.tab[onclick*="${tab}"]`).classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');
            
            // Load data for the selected tab
            if (tab === 'revenue') {
                loadRevenueData();
            } else if (tab === 'occupancy') {
                loadOccupancyData();
            } else if (tab === 'members') {
                loadMemberAnalytics();
            }
            
            // Update URL without reloading
            history.pushState(null, null, `analytics.php?type=${tab}`);
        }
        
        // Load top metrics
        function loadTopMetrics() {
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_top_metrics'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const metrics = data.metrics;
                    const metricsGrid = document.getElementById('topMetrics');
                    
                    metricsGrid.innerHTML = `
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-value">$${metrics.revenue_today.toFixed(2)}</div>
                            <div class="stat-label">Today's Revenue</div>
                        </div>
                        
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="stat-value">$${metrics.revenue_month.toFixed(2)}</div>
                            <div class="stat-label">Monthly Revenue</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value">${metrics.active_members}</div>
                            <div class="stat-label">Active Members</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <div class="stat-value">${metrics.occupancy_rate}%</div>
                            <div class="stat-label">Occupancy Rate</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-value">${metrics.task_completion}%</div>
                            <div class="stat-label">Task Completion</div>
                        </div>
                    `;
                }
            });
        }
        
        // Load revenue data
        function loadRevenueData() {
            const period = document.getElementById('revenuePeriod').value;
            
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_revenue_data&period=${period}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderRevenueChart(data.data);
                }
            });
        }
        
        // Render revenue chart
        function renderRevenueChart(chartData) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (revenueChart) {
                revenueChart.destroy();
            }
            
            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Internet Vouchers',
                            data: chartData.vouchers,
                            borderColor: 'var(--voucher-color)',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'LinkSpot Spaces',
                            data: chartData.linkspot,
                            borderColor: 'var(--linkspot-color)',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Summarcity Mall',
                            data: chartData.summarcity,
                            borderColor: 'var(--summarcity-color)',
                            backgroundColor: 'rgba(155, 89, 182, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Meeting Rooms',
                            data: chartData.meeting_rooms,
                            borderColor: 'var(--meeting-color)',
                            backgroundColor: 'rgba(243, 156, 18, 0.1)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Total Revenue',
                            data: chartData.total,
                            borderColor: 'var(--primary)',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.1,
                            fill: true,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'nearest'
                    }
                }
            });
        }
        
        // Load occupancy data
        function loadOccupancyData() {
            const date = document.getElementById('occupancyDate').value;
            
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_occupancy_data&date=${date}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateOccupancyStats(data.data);
                    renderOccupancyChart(data.data);
                }
            });
        }
        
        // Update occupancy stats
        function updateOccupancyStats(data) {
            document.getElementById('stationOccupancyRate').textContent = data.stations.occupancy_rate + '%';
            document.getElementById('totalStations').textContent = data.stations.total;
            document.getElementById('occupiedStations').textContent = data.stations.occupied;
            document.getElementById('availableStations').textContent = data.stations.available;
            
            document.getElementById('shopOccupancyRate').textContent = data.shops.occupancy_rate + '%';
            document.getElementById('totalShops').textContent = data.shops.total;
            document.getElementById('occupiedShops').textContent = data.shops.occupied;
            document.getElementById('availableShops').textContent = data.shops.available;
        }
        
        // Render occupancy chart
        function renderOccupancyChart(data) {
            const ctx = document.getElementById('occupancyChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (occupancyChart) {
                occupancyChart.destroy();
            }
            
            occupancyChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Occupied Stations', 'Available Stations', 'Occupied Shops', 'Available Shops'],
                    datasets: [{
                        data: [
                            data.stations.occupied,
                            data.stations.available,
                            data.shops.occupied,
                            data.shops.available
                        ],
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(52, 152, 219, 0.3)',
                            'rgba(155, 89, 182, 0.8)',
                            'rgba(155, 89, 182, 0.3)'
                        ],
                        borderColor: [
                            'rgba(52, 152, 219, 1)',
                            'rgba(52, 152, 219, 1)',
                            'rgba(155, 89, 182, 1)',
                            'rgba(155, 89, 182, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Load member analytics
        function loadMemberAnalytics() {
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_member_analytics'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMemberStats(data.data);
                    renderMemberGrowthChart(data.data.growth);
                }
            });
        }
        
        // Update member stats
        function updateMemberStats(data) {
            const linkspotStats = document.getElementById('linkspotStats');
            const summarcityStats = document.getElementById('summarcityStats');
            
            linkspotStats.innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">Total Members</span>
                    <span class="detail-value">${data.linkspot.total}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Active</span>
                    <span class="detail-value">${data.linkspot.active}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Inactive</span>
                    <span class="detail-value">${data.linkspot.inactive}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">New Members</span>
                    <span class="detail-value">${data.linkspot.new}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Avg Monthly Rate</span>
                    <span class="detail-value">$${data.linkspot.avg_monthly_rate.toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Balance</span>
                    <span class="detail-value">$${data.linkspot.total_balance.toFixed(2)}</span>
                </div>
            `;
            
            summarcityStats.innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">Total Members</span>
                    <span class="detail-value">${data.summarcity.total}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Active</span>
                    <span class="detail-value">${data.summarcity.active}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Inactive</span>
                    <span class="detail-value">${data.summarcity.inactive}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">New Members</span>
                    <span class="detail-value">${data.summarcity.new}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Avg Rent</span>
                    <span class="detail-value">$${data.summarcity.avg_rent.toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Balance</span>
                    <span class="detail-value">$${data.summarcity.total_balance.toFixed(2)}</span>
                </div>
            `;
        }
        
        // Render member growth chart
        function renderMemberGrowthChart(growthData) {
            const ctx = document.getElementById('memberGrowthChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (memberGrowthChart) {
                memberGrowthChart.destroy();
            }
            
            memberGrowthChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: growthData.labels,
                    datasets: [
                        {
                            label: 'LinkSpot Members',
                            data: growthData.linkspot,
                            backgroundColor: 'rgba(52, 152, 219, 0.8)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Summarcity Members',
                            data: growthData.summarcity,
                            backgroundColor: 'rgba(155, 89, 182, 0.8)',
                            borderColor: 'rgba(155, 89, 182, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Auto-refresh metrics every 5 minutes
        setInterval(loadTopMetrics, 300000);
    </script>
</body>
</html>