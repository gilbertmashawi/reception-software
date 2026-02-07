<?php
// admin_logs.php - System Logs Viewer
require_once 'config.php';
$db = getDB();

// Handle filters
$filterType = $_GET['type'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterUser = $_GET['user'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$query = "SELECT al.*, u.username, u.full_name FROM activity_log al 
          LEFT JOIN users u ON al.user_id = u.id 
          WHERE 1=1";
$params = [];
$types = '';

if ($filterType) {
    $query .= " AND al.activity_type = ?";
    $params[] = $filterType;
    $types .= 's';
}

if ($filterDate) {
    $query .= " AND al.activity_date = ?";
    $params[] = $filterDate;
    $types .= 's';
}

if ($searchQuery) {
    $query .= " AND (al.description LIKE ? OR al.details LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'ssss';
}

$query .= " ORDER BY al.created_at DESC";

// Prepare statement
$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Get distinct activity types for filter
$typeQuery = "SELECT DISTINCT activity_type FROM activity_log ORDER BY activity_type";
$typeResult = $db->query($typeQuery);
$activityTypes = $typeResult->fetch_all(MYSQLI_ASSOC);

// Get distinct dates for filter
$dateQuery = "SELECT DISTINCT activity_date FROM activity_log ORDER BY activity_date DESC LIMIT 30";
$dateResult = $db->query($dateQuery);
$activityDates = $dateResult->fetch_all(MYSQLI_ASSOC);

// Get distinct users for filter
$userQuery = "SELECT DISTINCT u.id, u.username, u.full_name FROM activity_log al 
              JOIN users u ON al.user_id = u.id 
              ORDER BY u.full_name";
$userResult = $db->query($userQuery);
$users = $userResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - System Logs</title>
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
        
        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        /* Filters */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        /* Card Header */
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
        
        .logs-count {
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
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
        
        /* Log Type Badges */
        .log-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .type-vouchers { background: #d4edda; color: #155724; }
        .type-linkspot { background: #d1ecf1; color: #0c5460; }
        .type-summarcity { background: #f8d7da; color: #721c24; }
        .type-meeting { background: #fff3cd; color: #856404; }
        .type-tasks { background: #e2e3e5; color: #383d41; }
        .type-members { background: #cce5ff; color: #004085; }
        
        /* Details Modal */
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
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            color: var(--secondary);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #dee2e6;
            text-align: right;
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
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                margin: 0 -20px;
                width: calc(100% + 40px);
                border-radius: 0;
                border-left: none;
                border-right: none;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
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
                    <h1><i class="fas fa-history"></i> System Logs</h1>
                    <p>Activity log viewer and management</p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div style="font-weight: 500;">Admin User</div>
                            <div style="font-size: 12px; color: #6c757d;">Administrator</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label class="form-label">Activity Type</label>
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach ($activityTypes as $type): ?>
                                <option value="<?php echo $type['activity_type']; ?>" <?php echo $filterType === $type['activity_type'] ? 'selected' : ''; ?>>
                                    <?php echo $type['activity_type']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <select name="date" class="form-control">
                                <option value="">All Dates</option>
                                <?php foreach ($activityDates as $date): ?>
                                <option value="<?php echo $date['activity_date']; ?>" <?php echo $filterDate === $date['activity_date'] ? 'selected' : ''; ?>>
                                    <?php echo date('M d, Y', strtotime($date['activity_date'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">User</label>
                            <select name="user" class="form-control">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo $user['full_name'] ?: $user['username']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="admin_logs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <button type="button" class="btn" onclick="exportLogs()" style="background: #28a745; color: white;">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Logs Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Activity Logs</h3>
                    <div class="logs-count"><?php echo count($logs); ?> records</div>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>User</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #6c757d;">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; color: #dee2e6;"></i>
                                    No logs found matching your criteria
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): 
                                $typeClass = '';
                                if (stripos($log['activity_type'], 'voucher') !== false) $typeClass = 'type-vouchers';
                                elseif (stripos($log['activity_type'], 'linkspot') !== false) $typeClass = 'type-linkspot';
                                elseif (stripos($log['activity_type'], 'summarcity') !== false) $typeClass = 'type-summarcity';
                                elseif (stripos($log['activity_type'], 'meeting') !== false) $typeClass = 'type-meeting';
                                elseif (stripos($log['activity_type'], 'task') !== false) $typeClass = 'type-tasks';
                                elseif (stripos($log['activity_type'], 'member') !== false) $typeClass = 'type-members';
                                else $typeClass = 'type-tasks';
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($log['activity_date'])); ?></div>
                                    <div style="font-size: 12px; color: #6c757d;"><?php echo $log['activity_time']; ?></div>
                                </td>
                                <td>
                                    <span class="log-type <?php echo $typeClass; ?>">
                                        <?php echo $log['activity_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 500; margin-bottom: 5px;"><?php echo htmlspecialchars($log['description']); ?></div>
                                    <?php if ($log['details']): ?>
                                    <div style="font-size: 12px; color: #6c757d;">
                                        <?php echo htmlspecialchars(substr($log['details'], 0, 100)); ?>
                                        <?php if (strlen($log['details']) > 100): ?>...<?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['full_name']): ?>
                                    <div style="font-weight: 500;"><?php echo $log['full_name']; ?></div>
                                    <div style="font-size: 12px; color: #6c757d;">@<?php echo $log['username']; ?></div>
                                    <?php else: ?>
                                    <span style="color: #6c757d;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn" onclick="viewLogDetails(<?php echo $log['id']; ?>)" 
                                            style="background: var(--primary); color: white; padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal" id="logModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Log Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="logDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // View log details
        function viewLogDetails(logId) {
            fetch(`get_log_details.php?id=${logId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('logDetails').innerHTML = html;
                    document.getElementById('logModal').style.display = 'flex';
                });
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('logModal').style.display = 'none';
        }
        
        // Export logs to CSV
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = `export_logs.php?${params.toString()}`;
        }
        
        // Close modal when clicking outside
        document.getElementById('logModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Add keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>