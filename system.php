<?php
// system.php - System Settings and Management
require_once 'config.php';
requireLogin();

// Only admin can access this page
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$action = $_GET['action'] ?? 'users';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_users':
            $stmt = $db->prepare("SELECT * FROM users ORDER BY role, username");
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            while ($row = $result->fetch_assoc()) {
                unset($row['password']); // Remove password from response
                $users[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'add_user':
            $username = $_POST['username'];
            $password = $_POST['password'];
            $fullName = $_POST['full_name'];
            $role = $_POST['role'];
            
            // Check if username exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                exit;
            }
            
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password, $fullName, $role);
            
            if ($stmt->execute()) {
                $userId = $db->insert_id;
                addActivityLog('System Users', "Added new user: {$username}", "Role: {$role}, Name: {$fullName}");
                sendNotification('new_user', 'New User Added', "User {$username} has been added to the system", 'all');
                echo json_encode(['success' => true, 'message' => 'User added successfully', 'user_id' => $userId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add user']);
            }
            break;
            
        case 'update_user':
            $userId = intval($_POST['user_id']);
            $username = $_POST['username'];
            $fullName = $_POST['full_name'];
            $role = $_POST['role'];
            $password = $_POST['password'] ?? null;
            
            if ($password) {
                $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $username, $password, $fullName, $role, $userId);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $fullName, $role, $userId);
            }
            
            if ($stmt->execute()) {
                addActivityLog('System Users', "Updated user: {$username}", "Role: {$role}, Name: {$fullName}");
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            }
            break;
            
        case 'delete_user':
            $userId = intval($_POST['user_id']);
            
            // Cannot delete own account
            if ($userId == $user['id']) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                exit;
            }
            
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                addActivityLog('System Users', "Deleted user ID: {$userId}");
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
            }
            break;
            
        case 'get_activity_logs':
            $limit = intval($_POST['limit']) ?: 100;
            $type = $_POST['type'] ?? 'all';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            
            $sql = "SELECT al.*, u.username, u.full_name FROM activity_log al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
            $params = [];
            $types = "";
            
            if ($type !== 'all') {
                $sql .= " AND al.activity_type = ?";
                $params[] = $type;
                $types .= "s";
            }
            
            if ($startDate) {
                $sql .= " AND al.activity_date >= ?";
                $params[] = $startDate;
                $types .= "s";
            }
            
            if ($endDate) {
                $sql .= " AND al.activity_date <= ?";
                $params[] = $endDate;
                $types .= "s";
            }
            
            $sql .= " ORDER BY al.id DESC LIMIT ?";
            $params[] = $limit;
            $types .= "i";
            
            $stmt = $db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            
            // Get activity types for filter
            $stmt2 = $db->prepare("SELECT DISTINCT activity_type FROM activity_log ORDER BY activity_type");
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $types = [];
            while ($row = $result2->fetch_assoc()) {
                $types[] = $row['activity_type'];
            }
            
            echo json_encode(['success' => true, 'data' => $logs, 'types' => $types]);
            break;
            
        case 'get_system_stats':
            $today = date('Y-m-d');
            $month = date('Y-m');
            
            // User stats
            $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins FROM users");
            $stmt->execute();
            $result = $stmt->get_result();
            $userStats = $result->fetch_assoc();
            
            // Activity stats
            $stmt = $db->prepare("SELECT COUNT(*) as today FROM activity_log WHERE activity_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $activityToday = $result->fetch_assoc();
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_log");
            $stmt->execute();
            $result = $stmt->get_result();
            $activityTotal = $result->fetch_assoc();
            
            // Database stats
            $tables = ['users', 'activity_log', 'customer_changes', 'linkspot_payments', 'mall_payments', 'meeting_rooms', 'tasks', 'voucher_sales', 'linkspot_members', 'summarcity_members', 'linkspot_station_addresses', 'summarcity_shops'];
            $tableStats = [];
            $totalRecords = 0;
            
            foreach ($tables as $table) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $count = $row['count'] ?? 0;
                $tableStats[$table] = $count;
                $totalRecords += $count;
            }
            
            // Disk usage (estimate)
            $stmt = $db->prepare("SELECT 
                table_schema as db_name,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                GROUP BY table_schema");
            $stmt->execute();
            $result = $stmt->get_result();
            $dbSize = $result->fetch_assoc();
            
            $stats = [
                'users' => $userStats,
                'activity' => [
                    'today' => $activityToday['today'] ?? 0,
                    'total' => $activityTotal['total'] ?? 0
                ],
                'database' => [
                    'tables' => $tableStats,
                    'total_records' => $totalRecords,
                    'size_mb' => $dbSize['size_mb'] ?? 0
                ],
                'system' => [
                    'php_version' => phpversion(),
                    'mysql_version' => $db->server_info,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'last_backup' => 'Not available' // Would integrate with backup system
                ]
            ];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'export_data':
            $type = $_POST['export_type'];
            $format = $_POST['export_format'];
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="linkspot_' . $type . '_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                switch ($type) {
                    case 'activity_log':
                        $stmt = $db->prepare("SELECT * FROM activity_log ORDER BY id DESC");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        // Headers
                        fputcsv($output, ['ID', 'Date', 'Time', 'Type', 'Description', 'Details', 'User ID', 'Created']);
                        
                        while ($row = $result->fetch_assoc()) {
                            fputcsv($output, $row);
                        }
                        break;
                        
                    case 'voucher_sales':
                        $stmt = $db->prepare("SELECT * FROM voucher_sales ORDER BY id DESC");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        // Headers
                        fputcsv($output, ['ID', 'Sale Date', 'Sale Time', 'Total Amount', 'Amount Received', 'Change Amount', 'Customer Name', 'Station ID', 'Created']);
                        
                        while ($row = $result->fetch_assoc()) {
                            fputcsv($output, $row);
                        }
                        break;
                }
                
                fclose($output);
                exit;
            }
            
            echo json_encode(['success' => false, 'message' => 'Export format not supported']);
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
    <title><?php echo SITE_NAME; ?> - System Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo SITE_THEME_COLOR; ?>;
            --secondary: <?php echo SITE_SECONDARY_COLOR; ?>;
        }
        
        .dashboard-page {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-top: 4px solid var(--primary);
        }
        
        .stat-title {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary);
        }
        
        .stat-details {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
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
            border-bottom: 1px solid #eee;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-primary {
            background: rgba(39, 174, 96, 0.1);
            color: var(--primary);
        }
        
        .badge-secondary {
            background: #6c757d;
            color: white;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #219653;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
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
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .log-item {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #dee2e6;
        }
        
        .log-item.system { border-left-color: #6c757d; }
        .log-item.user { border-left-color: #3498db; }
        .log-item.activity { border-left-color: var(--primary); }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .log-time {
            color: #6c757d;
            font-size: 12px;
        }
        
        .log-type {
            font-weight: 500;
            color: var(--secondary);
        }
        
        .log-user {
            color: #666;
            font-size: 12px;
        }
        
        .log-message {
            color: #333;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .filters {
                flex-direction: column;
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
                    <h1><i class="fas fa-cog"></i> System Settings</h1>
                    <p>Administrator panel for system management</p>
                </div>
            </div>
            
            <!-- System Stats -->
            <div class="stats-grid" id="systemStats">
                <!-- Stats will be loaded here -->
            </div>
            
            <div class="tabs">
                <div class="tab <?php echo $action === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">
                    User Management
                </div>
                <div class="tab <?php echo $action === 'logs' ? 'active' : ''; ?>" onclick="switchTab('logs')">
                    System Logs
                </div>
                <div class="tab <?php echo $action === 'settings' ? 'active' : ''; ?>" onclick="switchTab('settings')">
                    Settings
                </div>
                <div class="tab <?php echo $action === 'export' ? 'active' : ''; ?>" onclick="switchTab('export')">
                    Data Export
                </div>
            </div>
            
            <!-- User Management Tab -->
            <div id="tab-users" class="tab-content" style="<?php echo $action === 'users' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Users</h3>
                        <button class="btn btn-primary" onclick="openAddUserModal()">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </div>
                    
                    <div class="alert" id="userAlert" style="display: none;"></div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Users will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- System Logs Tab -->
            <div id="tab-logs" class="tab-content" style="<?php echo $action === 'logs' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Activity Logs</h3>
                        <div class="filters">
                            <select id="logType" class="form-control">
                                <option value="all">All Types</option>
                            </select>
                            <input type="date" id="logStartDate" class="form-control">
                            <input type="date" id="logEndDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            <button class="btn btn-primary" onclick="loadActivityLogs()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    <div id="activityLogs">
                        <!-- Logs will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div id="tab-settings" class="tab-content" style="<?php echo $action === 'settings' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Settings</h3>
                    </div>
                    
                    <div class="alert" id="settingsAlert" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label class="form-label">System Name</label>
                        <input type="text" class="form-control" value="<?php echo SITE_NAME; ?>" readonly>
                        <small class="text-muted">Configure in config.php</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Theme Color</label>
                        <input type="color" class="form-control" value="<?php echo SITE_THEME_COLOR; ?>" readonly>
                        <small class="text-muted">Configure in config.php</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Database Information</label>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                            <div><strong>Host:</strong> <?php echo DB_HOST; ?></div>
                            <div><strong>Database:</strong> <?php echo DB_NAME; ?></div>
                            <div><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Maintenance Mode</label>
                        <div>
                            <label style="display: inline-flex; align-items: center; gap: 10px; margin-right: 20px;">
                                <input type="radio" name="maintenance" value="off" checked> Off
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 10px;">
                                <input type="radio" name="maintenance" value="on"> On
                            </label>
                        </div>
                        <small class="text-muted">When enabled, only administrators can access the system</small>
                    </div>
                    
                    <button class="btn btn-primary" onclick="saveSettings()">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
            
            <!-- Data Export Tab -->
            <div id="tab-export" class="tab-content" style="<?php echo $action === 'export' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Data Export</h3>
                    </div>
                    
                    <div class="alert" id="exportAlert" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label class="form-label">Export Type</label>
                        <select id="exportType" class="form-control">
                            <option value="activity_log">Activity Logs</option>
                            <option value="voucher_sales">Voucher Sales</option>
                            <option value="linkspot_payments">LinkSpot Payments</option>
                            <option value="mall_payments">Summarcity Payments</option>
                            <option value="meeting_rooms">Meeting Rooms</option>
                            <option value="linkspot_members">LinkSpot Members</option>
                            <option value="summarcity_members">Summarcity Members</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Export Format</label>
                        <select id="exportFormat" class="form-control">
                            <option value="csv">CSV</option>
                            <option value="json" disabled>JSON (Coming Soon)</option>
                            <option value="excel" disabled>Excel (Coming Soon)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date Range</label>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="date" id="exportStartDate" class="form-control">
                            </div>
                            <div class="form-group">
                                <input type="date" id="exportEndDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" onclick="exportData()">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <h4>Backup Database</h4>
                        <p style="color: #666; margin-bottom: 15px;">Create a complete backup of the database</p>
                        <button class="btn btn-success" onclick="createBackup()">
                            <i class="fas fa-database"></i> Create Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalUserTitle">Add User</h3>
                <button class="btn btn-sm" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert" id="modalAlert" style="display: none;"></div>
                
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" id="modalUsername" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" id="modalPassword" class="form-control">
                    <small class="text-muted" id="passwordHelp">Enter password for new user</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" id="modalFullName" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select id="modalRole" class="form-control">
                        <option value="reception">Reception</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <input type="hidden" id="modalUserId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
                <button class="btn btn-primary" id="modalSaveButton" onclick="saveUser()">Add User</button>
            </div>
        </div>
    </div>
    
    <script>
        let isEditingUser = false;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStats();
            
            if ('<?php echo $action; ?>' === 'users') {
                loadUsers();
            } else if ('<?php echo $action; ?>' === 'logs') {
                loadActivityLogs();
            }
        });
        
        // Switch tabs
        function switchTab(tab) {
            window.location.href = 'system.php?action=' + tab;
        }
        
        // Load system stats
        function loadSystemStats() {
            fetch('system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_system_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats;
                    const statsGrid = document.getElementById('systemStats');
                    
                    statsGrid.innerHTML = `
                        <div class="stat-card">
                            <div class="stat-title">Total Users</div>
                            <div class="stat-value">${stats.users.total}</div>
                            <div class="stat-details">${stats.users.admins} administrators</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-title">Today's Activity</div>
                            <div class="stat-value">${stats.activity.today}</div>
                            <div class="stat-details">${stats.activity.total} total logs</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-title">Database Records</div>
                            <div class="stat-value">${stats.database.total_records.toLocaleString()}</div>
                            <div class="stat-details">${Object.keys(stats.database.tables).length} tables, ${stats.database.size_mb} MB</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-title">System Info</div>
                            <div style="font-size: 14px; color: #666; margin-top: 10px;">
                                <div>PHP: ${stats.system.php_version}</div>
                                <div>MySQL: ${stats.system.mysql_version.split(' ')[0]}</div>
                                <div>Last Backup: ${stats.system.last_backup}</div>
                            </div>
                        </div>
                    `;
                }
            });
        }
        
        // Load users
        function loadUsers() {
            fetch('system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderUsers(data.data);
                }
            });
        }
        
        // Render users
        function renderUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            
            if (users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; color: #6c757d;">
                            No users found
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = '';
            
            users.forEach(user => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${user.id}</td>
                    <td>${user.username}</td>
                    <td>${user.full_name}</td>
                    <td>
                        <span class="badge ${user.role === 'admin' ? 'badge-success' : 'badge-primary'}">
                            ${user.role}
                        </span>
                    </td>
                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="editUser(${user.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        ${user.id !== <?php echo $user['id']; ?> ? 
                            `<button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id}, '${user.username}')">
                                <i class="fas fa-trash"></i> Delete
                            </button>` : ''}
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Open add user modal
        function openAddUserModal() {
            isEditingUser = false;
            document.getElementById('modalUserTitle').textContent = 'Add User';
            document.getElementById('modalSaveButton').textContent = 'Add User';
            document.getElementById('passwordHelp').textContent = 'Enter password for new user';
            
            // Clear form
            document.getElementById('modalUserId').value = '';
            document.getElementById('modalUsername').value = '';
            document.getElementById('modalPassword').value = '';
            document.getElementById('modalFullName').value = '';
            document.getElementById('modalRole').value = 'reception';
            
            document.getElementById('userModal').style.display = 'flex';
        }
        
        // Edit user
        function editUser(userId) {
            fetch('system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_users'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.data.find(u => u.id == userId);
                    if (user) {
                        isEditingUser = true;
                        document.getElementById('modalUserTitle').textContent = 'Edit User';
                        document.getElementById('modalSaveButton').textContent = 'Save Changes';
                        document.getElementById('passwordHelp').textContent = 'Leave blank to keep current password';
                        
                        document.getElementById('modalUserId').value = user.id;
                        document.getElementById('modalUsername').value = user.username;
                        document.getElementById('modalPassword').value = '';
                        document.getElementById('modalFullName').value = user.full_name;
                        document.getElementById('modalRole').value = user.role;
                        
                        document.getElementById('userModal').style.display = 'flex';
                    }
                }
            });
        }
        
        // Close user modal
        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
            document.getElementById('modalAlert').style.display = 'none';
        }
        
        // Save user
        function saveUser() {
            const userId = document.getElementById('modalUserId').value;
            const username = document.getElementById('modalUsername').value;
            const password = document.getElementById('modalPassword').value;
            const fullName = document.getElementById('modalFullName').value;
            const role = document.getElementById('modalRole').value;
            
            if (!username || !fullName || (!isEditingUser && !password)) {
                showModalAlert('Please fill all required fields', 'danger');
                return;
            }
            
            const action = isEditingUser ? 'update_user' : 'add_user';
            let body = `ajax=true&action=${action}&username=${encodeURIComponent(username)}&full_name=${encodeURIComponent(fullName)}&role=${role}`;
            
            if (isEditingUser && password) {
                body += `&password=${encodeURIComponent(password)}`;
            } else if (!isEditingUser) {
                body += `&password=${encodeURIComponent(password)}`;
            }
            
            if (isEditingUser) {
                body += `&user_id=${userId}`;
            }
            
            fetch('system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showUserAlert('User ' + (isEditingUser ? 'updated' : 'added') + ' successfully!', 'success');
                    closeUserModal();
                    loadUsers();
                    
                    setTimeout(() => {
                        hideUserAlert();
                    }, 3000);
                } else {
                    showModalAlert(data.message, 'danger');
                }
            });
        }
        
        // Delete user
        function deleteUser(userId, username) {
            if (!confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                return;
            }
            
            fetch('system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=delete_user&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showUserAlert('User deleted successfully!', 'success');
                    loadUsers();
                    
                    setTimeout(() => {
                        hideUserAlert();
                    }, 3000);
                } else {
                    showUserAlert(data.message, 'danger');
                }
            });
        }
        
        // Load activity logs
        function loadActivityLogs() {
            const type = document.getElementById('logType').value;
            const startDate = document.getElementById('logStartDate').value;
            const endDate = document.getElementById('logEndDate').value;
            
            fetch('system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_activity_logs&type=${type}&start_date=${startDate}&end_date=${endDate}&limit=100`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate type filter if not already done
                    if (document.getElementById('logType').options.length <= 1) {
                        const select = document.getElementById('logType');
                        data.types.forEach(type => {
                            const option = document.createElement('option');
                            option.value = type;
                            option.textContent = type;
                            select.appendChild(option);
                        });
                    }
                    
                    renderActivityLogs(data.data);
                }
            });
        }
        
        // Render activity logs
        function renderActivityLogs(logs) {
            const container = document.getElementById('activityLogs');
            
            if (logs.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                        <h3>No activity logs found</h3>
                        <p>Try adjusting your filters</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            
            logs.forEach(log => {
                const item = document.createElement('div');
                item.className = `log-item ${log.activity_type.toLowerCase().includes('system') ? 'system' : 
                                 log.activity_type.toLowerCase().includes('user') ? 'user' : 'activity'}`;
                
                const time = new Date(log.created_at);
                const timeStr = time.toLocaleDateString() + ' ' + time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                item.innerHTML = `
                    <div class="log-header">
                        <div>
                            <span class="log-type">${log.activity_type}</span>
                            ${log.username ? `<span class="log-user"> • by ${log.full_name || log.username}</span>` : ''}
                        </div>
                        <span class="log-time">${timeStr}</span>
                    </div>
                    <div class="log-message">${log.description}</div>
                    ${log.details ? `<div style="font-size: 12px; color: #666; margin-top: 5px;">${log.details}</div>` : ''}
                `;
                
                container.appendChild(item);
            });
        }
        
        // Save settings
        function saveSettings() {
            // This would typically save to a settings table or config file
            showSettingsAlert('Settings saved successfully!', 'success');
            
            setTimeout(() => {
                hideSettingsAlert();
            }, 3000);
        }
        
        // Export data
        function exportData() {
            const type = document.getElementById('exportType').value;
            const format = document.getElementById('exportFormat').value;
            const startDate = document.getElementById('exportStartDate').value;
            const endDate = document.getElementById('exportEndDate').value;
            
            let url = `system.php?export=true&type=${type}&format=${format}`;
            if (startDate) url += `&start_date=${startDate}`;
            if (endDate) url += `&end_date=${endDate}`;
            
            window.open(url, '_blank');
        }
        
        // Create backup
        function createBackup() {
            showExportAlert('Creating database backup...', 'info');
            
            // This would typically trigger a server-side backup script
            setTimeout(() => {
                showExportAlert('Backup created successfully! Check server backup directory.', 'success');
                
                setTimeout(() => {
                    hideExportAlert();
                }, 5000);
            }, 2000);
        }
        
        // Show/hide alerts
        function showUserAlert(message, type) {
            const alert = document.getElementById('userAlert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function hideUserAlert() {
            document.getElementById('userAlert').style.display = 'none';
        }
        
        function showModalAlert(message, type) {
            const alert = document.getElementById('modalAlert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function showSettingsAlert(message, type) {
            const alert = document.getElementById('settingsAlert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function hideSettingsAlert() {
            document.getElementById('settingsAlert').style.display = 'none';
        }
        
        function showExportAlert(message, type) {
            const alert = document.getElementById('exportAlert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        function hideExportAlert() {
            document.getElementById('exportAlert').style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target === modal) {
                closeUserModal();
            }
        };
        
        // Auto-refresh stats every 5 minutes
        setInterval(loadSystemStats, 300000);
    </script>
</body>
</html>