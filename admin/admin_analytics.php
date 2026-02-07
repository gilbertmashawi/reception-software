<?php
// admin_analytics.php - Admin Analytics Dashboard
require_once 'config.php';
// requireLogin() removed - no login required

// Check if user is admin (commented out since login not required)
// if ($_SESSION['role'] !== 'admin') {
//     header('Location: admin_analytics.php');
//     exit();
// }

$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_dashboard_stats':
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
            
            // Occupancy stats
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied
                FROM linkspot_station_addresses");
            $stmt->execute();
            $result = $stmt->get_result();
            $stationData = $result->fetch_assoc();
            $occupancyRate = $stationData['total'] > 0 ? round(($stationData['occupied'] / $stationData['total']) * 100, 1) : 0;
            
            // Total users
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
            $stmt->execute();
            $result = $stmt->get_result();
            $totalUsers = $result->fetch_assoc()['total'] ?? 0;
            
            // Today's tasks
            $stmt = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as completed
                FROM tasks WHERE task_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $taskData = $result->fetch_assoc();
            $taskCompletion = $taskData['total'] > 0 ? round(($taskData['completed'] / $taskData['total']) * 100, 1) : 100;
            
            // Available vouchers
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM vouchers WHERE status = 'available'");
            $stmt->execute();
            $result = $stmt->get_result();
            $availableVouchers = $result->fetch_assoc()['total'] ?? 0;
            
            // Recent activity count
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_log WHERE DATE(created_at) = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $todayActivity = $result->fetch_assoc()['total'] ?? 0;
            
            $metrics = [
                'revenue_today' => $revenueToday,
                'revenue_month' => $revenueMonth,
                'active_members' => $activeMembers,
                'occupancy_rate' => $occupancyRate,
                'total_users' => $totalUsers,
                'task_completion' => $taskCompletion,
                'available_vouchers' => $availableVouchers,
                'today_activity' => $todayActivity
            ];
            
            echo json_encode(['success' => true, 'metrics' => $metrics]);
            break;
            
        case 'get_revenue_breakdown':
            $month = $_POST['month'] ?? date('Y-m');
            
            $data = [
                'vouchers' => 0,
                'linkspot' => 0,
                'summarcity' => 0,
                'meeting_rooms' => 0,
                'total' => 0
            ];
            
            // Voucher sales
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM voucher_sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $data['vouchers'] = floatval($row['total']);
            
            // Linkspot payments
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM linkspot_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $data['linkspot'] = floatval($row['total']);
            
            // Summarcity payments
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM mall_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $data['summarcity'] = floatval($row['total']);
            
            // Meeting rooms
            $stmt = $db->prepare("SELECT COALESCE(SUM(cost), 0) as total FROM meeting_rooms WHERE DATE_FORMAT(start_date, '%Y-%m') = ?");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $data['meeting_rooms'] = floatval($row['total']);
            
            $data['total'] = $data['vouchers'] + $data['linkspot'] + $data['summarcity'] + $data['meeting_rooms'];
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_daily_revenue':
            $startDate = $_POST['start_date'] ?? date('Y-m-01');
            $endDate = $_POST['end_date'] ?? date('Y-m-t');
            
            $stmt = $db->prepare("
                SELECT 
                    DATE(sale_date) as date,
                    COALESCE(SUM(total_amount), 0) as vouchers,
                    0 as linkspot,
                    0 as summarcity,
                    0 as meeting_rooms
                FROM voucher_sales 
                WHERE sale_date BETWEEN ? AND ?
                GROUP BY DATE(sale_date)
                
                UNION ALL
                
                SELECT 
                    DATE(payment_date) as date,
                    0 as vouchers,
                    COALESCE(SUM(amount), 0) as linkspot,
                    0 as summarcity,
                    0 as meeting_rooms
                FROM linkspot_payments 
                WHERE payment_date BETWEEN ? AND ?
                GROUP BY DATE(payment_date)
                
                UNION ALL
                
                SELECT 
                    DATE(payment_date) as date,
                    0 as vouchers,
                    0 as linkspot,
                    COALESCE(SUM(amount), 0) as summarcity,
                    0 as meeting_rooms
                FROM mall_payments 
                WHERE payment_date BETWEEN ? AND ?
                GROUP BY DATE(payment_date)
                
                UNION ALL
                
                SELECT 
                    DATE(start_date) as date,
                    0 as vouchers,
                    0 as linkspot,
                    0 as summarcity,
                    COALESCE(SUM(cost), 0) as meeting_rooms
                FROM meeting_rooms 
                WHERE start_date BETWEEN ? AND ?
                GROUP BY DATE(start_date)
                
                ORDER BY date
            ");
            
            $stmt->bind_param("ssssssss", $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $dailyData = [];
            while ($row = $result->fetch_assoc()) {
                $date = $row['date'];
                if (!isset($dailyData[$date])) {
                    $dailyData[$date] = [
                        'date' => $date,
                        'vouchers' => 0,
                        'linkspot' => 0,
                        'summarcity' => 0,
                        'meeting_rooms' => 0,
                        'total' => 0
                    ];
                }
                
                $dailyData[$date]['vouchers'] += $row['vouchers'];
                $dailyData[$date]['linkspot'] += $row['linkspot'];
                $dailyData[$date]['summarcity'] += $row['summarcity'];
                $dailyData[$date]['meeting_rooms'] += $row['meeting_rooms'];
                $dailyData[$date]['total'] = $dailyData[$date]['vouchers'] + $dailyData[$date]['linkspot'] + 
                                           $dailyData[$date]['summarcity'] + $dailyData[$date]['meeting_rooms'];
            }
            
            $dailyData = array_values($dailyData);
            echo json_encode(['success' => true, 'data' => $dailyData]);
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
    <title><?php echo SITE_NAME; ?> - Admin Analytics</title>
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
            --linkspot-color: #3498db;
            --summarcity-color: #9b59b6;
            --voucher-color: #e74c3c;
            --meeting-color: #f39c12;
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-card.info {
            border-left-color: var(--info);
        }
        
        .stat-card.success {
            border-left-color: var(--success);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger);
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
        
        .stat-card.warning .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .stat-card.info .stat-icon {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .stat-card.success .stat-icon {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .stat-card.danger .stat-icon {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
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
        
        /* Form Controls */
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
        
        /* Filters */
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
        }
        
        /* Revenue Breakdown */
        .revenue-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .revenue-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .revenue-item.vouchers {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .revenue-item.linkspot {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }
        
        .revenue-item.summarcity {
            background: rgba(155, 89, 182, 0.1);
            border: 1px solid rgba(155, 89, 182, 0.2);
        }
        
        .revenue-item.meeting {
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid rgba(243, 156, 18, 0.2);
        }
        
        .revenue-value {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .revenue-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-page">
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-chart-line"></i> Admin Analytics Dashboard</h1>
                    <p>Comprehensive overview of system performance and metrics</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-avatar">
                            <!-- Removed session-based user display -->
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <div style="font-weight: 500;">Public Analytics Dashboard</div>
                            <div style="font-size: 12px; color: #6c757d;">View Only Access</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Stats -->
            <div class="stats-grid" id="dashboardStats">
                <!-- Stats will be loaded here -->
            </div>
            
            <!-- Revenue Analysis -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Revenue Analysis</h3>
                    <div class="filters">
                        <input type="month" id="revenueMonth" class="form-control" value="<?php echo date('Y-m'); ?>" onchange="loadRevenueBreakdown()">
                    </div>
                </div>
                
                <div id="revenueBreakdown">
                    <!-- Revenue breakdown will be loaded here -->
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
                </div>
            </div>
            
            <!-- Daily Revenue Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daily Revenue Trend</h3>
                    <div class="filters">
                        <input type="date" id="startDate" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        <input type="date" id="endDate" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                        <button class="btn btn-primary" onclick="loadDailyRevenue()">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="dailyRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let revenueChart = null;
        let dailyRevenueChart = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
            loadRevenueBreakdown();
            loadDailyRevenue();
        });
        
        // Load dashboard stats
        function loadDashboardStats() {
            fetch('admin_analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_dashboard_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const metrics = data.metrics;
                    const statsGrid = document.getElementById('dashboardStats');
                    
                    statsGrid.innerHTML = `
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
                        
                        <div class="stat-card success">
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
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="stat-value">${metrics.total_users}</div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="stat-value">${metrics.available_vouchers}</div>
                            <div class="stat-label">Available Vouchers</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-value">${metrics.task_completion}%</div>
                            <div class="stat-label">Task Completion</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="stat-value">${metrics.today_activity}</div>
                            <div class="stat-label">Today's Activities</div>
                        </div>
                    `;
                }
            });
        }
        
        // Load revenue breakdown
        function loadRevenueBreakdown() {
            const month = document.getElementById('revenueMonth').value;
            
            fetch('admin_analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_revenue_breakdown&month=${month}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const revenueData = data.data;
                    const breakdown = document.getElementById('revenueBreakdown');
                    
                    breakdown.innerHTML = `
                        <div class="revenue-breakdown">
                            <div class="revenue-item vouchers">
                                <div class="revenue-value">$${revenueData.vouchers.toFixed(2)}</div>
                                <div class="revenue-label">Internet Vouchers</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    ${revenueData.total > 0 ? ((revenueData.vouchers / revenueData.total) * 100).toFixed(1) : 0}%
                                </div>
                            </div>
                            
                            <div class="revenue-item linkspot">
                                <div class="revenue-value">$${revenueData.linkspot.toFixed(2)}</div>
                                <div class="revenue-label">LinkSpot Spaces</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    ${revenueData.total > 0 ? ((revenueData.linkspot / revenueData.total) * 100).toFixed(1) : 0}%
                                </div>
                            </div>
                            
                            <div class="revenue-item summarcity">
                                <div class="revenue-value">$${revenueData.summarcity.toFixed(2)}</div>
                                <div class="revenue-label">Summarcity Mall</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    ${revenueData.total > 0 ? ((revenueData.summarcity / revenueData.total) * 100).toFixed(1) : 0}%
                                </div>
                            </div>
                            
                            <div class="revenue-item meeting">
                                <div class="revenue-value">$${revenueData.meeting_rooms.toFixed(2)}</div>
                                <div class="revenue-label">Meeting Rooms</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    ${revenueData.total > 0 ? ((revenueData.meeting_rooms / revenueData.total) * 100).toFixed(1) : 0}%
                                </div>
                            </div>
                            
                            <div class="revenue-item" style="background: rgba(39, 174, 96, 0.1); border: 1px solid rgba(39, 174, 96, 0.2);">
                                <div class="revenue-value" style="color: var(--primary);">$${revenueData.total.toFixed(2)}</div>
                                <div class="revenue-label">Total Revenue</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">100%</div>
                            </div>
                        </div>
                    `;
                    
                    // Update pie chart
                    updateRevenueChart(revenueData);
                }
            });
        }
        
        // Update revenue pie chart
        function updateRevenueChart(data) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            if (revenueChart) {
                revenueChart.destroy();
            }
            
            revenueChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Internet Vouchers', 'LinkSpot Spaces', 'Summarcity Mall', 'Meeting Rooms'],
                    datasets: [{
                        data: [data.vouchers, data.linkspot, data.summarcity, data.meeting_rooms],
                        backgroundColor: [
                            'var(--voucher-color)',
                            'var(--linkspot-color)',
                            'var(--summarcity-color)',
                            'var(--meeting-color)'
                        ],
                        borderColor: [
                            'rgba(231, 76, 60, 1)',
                            'rgba(52, 152, 219, 1)',
                            'rgba(155, 89, 182, 1)',
                            'rgba(243, 156, 18, 1)'
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
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: $${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Load daily revenue data
        function loadDailyRevenue() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            fetch('admin_analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_daily_revenue&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDailyRevenueChart(data.data);
                }
            });
        }
        
        // Update daily revenue chart
        function updateDailyRevenueChart(data) {
            const ctx = document.getElementById('dailyRevenueChart').getContext('2d');
            
            const labels = data.map(item => new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            const totals = data.map(item => item.total);
            const vouchers = data.map(item => item.vouchers);
            const linkspot = data.map(item => item.linkspot);
            const summarcity = data.map(item => item.summarcity);
            const meeting = data.map(item => item.meeting_rooms);
            
            if (dailyRevenueChart) {
                dailyRevenueChart.destroy();
            }
            
            dailyRevenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Revenue',
                            data: totals,
                            borderColor: 'var(--primary)',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.1,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'Internet Vouchers',
                            data: vouchers,
                            borderColor: 'var(--voucher-color)',
                            backgroundColor: 'transparent',
                            tension: 0.1,
                            borderWidth: 1
                        },
                        {
                            label: 'LinkSpot Spaces',
                            data: linkspot,
                            borderColor: 'var(--linkspot-color)',
                            backgroundColor: 'transparent',
                            tension: 0.1,
                            borderWidth: 1
                        },
                        {
                            label: 'Summarcity Mall',
                            data: summarcity,
                            borderColor: 'var(--summarcity-color)',
                            backgroundColor: 'transparent',
                            tension: 0.1,
                            borderWidth: 1
                        },
                        {
                            label: 'Meeting Rooms',
                            data: meeting,
                            borderColor: 'var(--meeting-color)',
                            backgroundColor: 'transparent',
                            tension: 0.1,
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
        
        // Auto-refresh every 5 minutes
        setInterval(loadDashboardStats, 300000);
    </script>
</body>
</html>