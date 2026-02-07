<?php
// admin_spaces.php - Space Management
require_once 'config.php';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_all_stations':
            $stmt = $db->prepare("SELECT 
                lsa.*, 
                lm.full_name as occupant_name,
                lm.member_code,
                lm.balance,
                lm.next_due_date,
                lm.package_type,
                lm.monthly_rate
                FROM linkspot_station_addresses lsa
                LEFT JOIN linkspot_members lm ON lsa.current_user_id = lm.id
                ORDER BY 
                    CASE 
                        WHEN lsa.station_code REGEXP '^[A-Z]$' THEN ASCII(lsa.station_code)
                        ELSE 999
                    END,
                    CAST(lsa.desk_number AS UNSIGNED)");
            $stmt->execute();
            $result = $stmt->get_result();
            $stations = [];
            while ($row = $result->fetch_assoc()) {
                $stations[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $stations]);
            break;
            
        case 'get_station_details':
            $stationId = intval($_POST['station_id']);
            
            $stmt = $db->prepare("SELECT 
                lsa.*, 
                lm.full_name as occupant_name,
                lm.member_code,
                lm.balance,
                lm.next_due_date,
                lm.package_type,
                lm.monthly_rate,
                lm.email,
                lm.phone,
                lm.id_number,
                lm.created_at as member_since
                FROM linkspot_station_addresses lsa
                LEFT JOIN linkspot_members lm ON lsa.current_user_id = lm.id
                WHERE lsa.id = ?");
            $stmt->bind_param("i", $stationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $station = $result->fetch_assoc();
            
            echo json_encode(['success' => true, 'data' => $station]);
            break;
            
        case 'release_station':
            $stationId = intval($_POST['station_id']);
            
            $db->begin_transaction();
            try {
                // Get station details
                $stmt = $db->prepare("SELECT current_user_id FROM linkspot_station_addresses WHERE id = ?");
                $stmt->bind_param("i", $stationId);
                $stmt->execute();
                $result = $stmt->get_result();
                $station = $result->fetch_assoc();
                
                if ($station['current_user_id']) {
                    // Update member station assignment
                    $stmt = $db->prepare("UPDATE linkspot_members SET station_address_id = NULL, station_code = NULL, desk_number = NULL WHERE id = ?");
                    $stmt->bind_param("i", $station['current_user_id']);
                    $stmt->execute();
                }
                
                // Release station
                $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'available', current_user_id = NULL, current_user_name = NULL, occupation_start = NULL, occupation_end = NULL WHERE id = ?");
                $stmt->bind_param("i", $stationId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Linkspot Spaces', "Released station ID {$stationId}", "Admin action");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Station released successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'assign_station':
            $stationId = intval($_POST['station_id']);
            $memberId = intval($_POST['member_id']);
            
            $db->begin_transaction();
            try {
                // Get station and member details
                $stmt = $db->prepare("SELECT station_code, desk_number FROM linkspot_station_addresses WHERE id = ?");
                $stmt->bind_param("i", $stationId);
                $stmt->execute();
                $result = $stmt->get_result();
                $station = $result->fetch_assoc();
                
                $stmt = $db->prepare("SELECT full_name FROM linkspot_members WHERE id = ?");
                $stmt->bind_param("i", $memberId);
                $stmt->execute();
                $result = $stmt->get_result();
                $member = $result->fetch_assoc();
                
                if (!$station || !$member) {
                    throw new Exception('Station or member not found');
                }
                
                // Update station
                $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'occupied', current_user_id = ?, current_user_name = ?, occupation_start = NOW() WHERE id = ?");
                $stmt->bind_param("isi", $memberId, $member['full_name'], $stationId);
                $stmt->execute();
                
                // Update member
                $stmt = $db->prepare("UPDATE linkspot_members SET station_address_id = ?, station_code = ?, desk_number = ? WHERE id = ?");
                $stmt->bind_param("issi", $stationId, $station['station_code'], $station['desk_number'], $memberId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Linkspot Spaces', "Assigned station {$station['station_code']}{$station['desk_number']} to {$member['full_name']}", "Admin action");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Station assigned successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_available_members':
            $stmt = $db->prepare("SELECT id, full_name, member_code, balance FROM linkspot_members WHERE status = 'active' AND (station_address_id IS NULL OR station_address_id = 0) ORDER BY full_name");
            $stmt->execute();
            $result = $stmt->get_result();
            $members = [];
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $members]);
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
    <title><?php echo SITE_NAME; ?> - Space Management</title>
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
        
        /* Station Grid Container */
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
        }
        
        /* Station Grid */
        .station-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .station-card {
            padding: 15px 10px;
            text-align: center;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
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
        }
        
        .station-card.maintenance {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .station-card.reserved {
            background: #cce5ff;
            color: #004085;
            border-color: #b8daff;
        }
        
        .station-code {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .station-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .available .station-status {
            background: #28a745;
            color: white;
        }
        
        .occupied .station-status {
            background: #dc3545;
            color: white;
        }
        
        .station-occupant {
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin: 5px 0;
            font-weight: 500;
        }
        
        .station-balance {
            font-size: 11px;
            font-weight: 500;
        }
        
        .station-balance.positive {
            color: #28a745;
        }
        
        .station-balance.negative {
            color: #dc3545;
        }
        
        .station-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .station-card:hover .station-actions {
            opacity: 1;
        }
        
        .station-action-btn {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .view-btn {
            background: var(--primary);
            color: white;
        }
        
        .view-btn:hover {
            background: #219653;
        }
        
        .release-btn {
            background: var(--danger);
            color: white;
        }
        
        .release-btn:hover {
            background: #c82333;
        }
        
        /* Station Groups */
        .station-group {
            margin-bottom: 30px;
        }
        
        .group-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
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
        
        /* Form */
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
        
        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-weight: 500;
            font-size: 14px;
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
            
            .station-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .station-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    <h1><i class="fas fa-map-marker-alt"></i> Space Management</h1>
                    <p>Manage LinkSpot stations and assignments</p>
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
            
            <!-- Stats -->
            <div class="stats-grid" id="statsGrid">
                <!-- Stats will be loaded here -->
            </div>
            
            <!-- Station Grid -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Stations</h3>
                    <button class="btn btn-primary" onclick="loadStations()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                
                <div id="stationGroups">
                    <!-- Station groups will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Station Details Modal -->
    <div class="modal" id="stationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Station Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="stationDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Assign Station Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Station</h3>
                <button class="modal-close" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Member</label>
                    <select id="memberSelect" class="form-control">
                        <option value="">Select a member...</option>
                    </select>
                </div>
                <div id="assignStationInfo"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                <button class="btn btn-primary" onclick="assignStation()">Assign</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentStationId = null;
        let stations = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadStations();
        });
        
        // Load stations
        function loadStations() {
            fetch('admin_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_all_stations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    stations = data.data;
                    updateStats(data.data);
                    displayStations(data.data);
                }
            });
        }
        
        // Update stats
        function updateStats(stations) {
            const total = stations.length;
            const occupied = stations.filter(s => s.status === 'occupied').length;
            const available = stations.filter(s => s.status === 'available').length;
            const occupiedRate = total > 0 ? Math.round((occupied / total) * 100) : 0;
            
            const statsGrid = document.getElementById('statsGrid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <div class="stat-value">${total}</div>
                    <div class="stat-label">Total Stations</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value">${available}</div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value">${occupied}</div>
                    <div class="stat-label">Occupied</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">${occupiedRate}%</div>
                    <div class="stat-label">Occupancy Rate</div>
                </div>
            `;
        }
        
        // Display stations in groups
        function displayStations(stations) {
            // Group stations by station_code
            const grouped = {};
            stations.forEach(station => {
                if (!grouped[station.station_code]) {
                    grouped[station.station_code] = [];
                }
                grouped[station.station_code].push(station);
            });
            
            const stationGroups = document.getElementById('stationGroups');
            stationGroups.innerHTML = '';
            
            // Sort groups alphabetically
            const sortedGroups = Object.keys(grouped).sort();
            
            sortedGroups.forEach(groupCode => {
                const groupStations = grouped[groupCode];
                
                const groupDiv = document.createElement('div');
                groupDiv.className = 'station-group';
                
                const groupTitle = document.createElement('div');
                groupTitle.className = 'group-title';
                groupTitle.textContent = `Station ${groupCode}`;
                groupDiv.appendChild(groupTitle);
                
                const gridDiv = document.createElement('div');
                gridDiv.className = 'station-grid';
                
                // Sort stations by desk number numerically
                groupStations.sort((a, b) => {
                    const numA = parseInt(a.desk_number) || 0;
                    const numB = parseInt(b.desk_number) || 0;
                    return numA - numB;
                });
                
                groupStations.forEach(station => {
                    const card = createStationCard(station);
                    gridDiv.appendChild(card);
                });
                
                groupDiv.appendChild(gridDiv);
                stationGroups.appendChild(groupDiv);
            });
        }
        
        // Create station card
        function createStationCard(station) {
            const card = document.createElement('div');
            card.className = `station-card ${station.status}`;
            card.dataset.id = station.id;
            
            const balance = parseFloat(station.balance || 0);
            const balanceClass = balance >= 0 ? 'positive' : 'negative';
            const balanceText = balance >= 0 ? `$${balance.toFixed(2)}` : `-$${Math.abs(balance).toFixed(2)}`;
            
            card.innerHTML = `
                <div class="station-actions">
                    <button class="station-action-btn view-btn" onclick="viewStation(${station.id}, event)">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${station.status === 'occupied' ? `
                    <button class="station-action-btn release-btn" onclick="releaseStation(${station.id}, event)">
                        <i class="fas fa-times"></i>
                    </button>
                    ` : ''}
                </div>
                <div class="station-code">${station.station_code}${station.desk_number}</div>
                <div class="station-status">${station.status}</div>
                ${station.occupant_name ? `
                <div class="station-occupant" title="${station.occupant_name}">
                    ${station.occupant_name}
                </div>
                <div class="station-balance ${balanceClass}">
                    ${balanceText}
                </div>
                ` : ''}
            `;
            
            // Add click handler for the entire card
            card.addEventListener('click', function(e) {
                if (!e.target.closest('.station-actions')) {
                    viewStation(station.id);
                }
            });
            
            return card;
        }
        
        // View station details
        function viewStation(stationId, event = null) {
            if (event) event.stopPropagation();
            
            currentStationId = stationId;
            
            fetch('admin_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_station_details&station_id=${stationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const station = data.data;
                    const modal = document.getElementById('stationModal');
                    const details = document.getElementById('stationDetails');
                    
                    let html = `
                        <div class="details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Station Code</div>
                                <div class="detail-value">${station.station_code}${station.desk_number}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span style="
                                        padding: 3px 8px;
                                        border-radius: 4px;
                                        font-size: 12px;
                                        font-weight: 500;
                                        ${station.status === 'available' ? 'background: #d4edda; color: #155724;' : 
                                          station.status === 'occupied' ? 'background: #f8d7da; color: #721c24;' : 
                                          station.status === 'maintenance' ? 'background: #fff3cd; color: #856404;' : 
                                          'background: #cce5ff; color: #004085;'}
                                    ">
                                        ${station.status}
                                    </span>
                                </div>
                            </div>
                    `;
                    
                    if (station.occupant_name) {
                        html += `
                            <div class="detail-item">
                                <div class="detail-label">Occupant</div>
                                <div class="detail-value">${station.occupant_name}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Member Code</div>
                                <div class="detail-value">${station.member_code || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Package Type</div>
                                <div class="detail-value">${station.package_type || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Monthly Rate</div>
                                <div class="detail-value">$${parseFloat(station.monthly_rate || 0).toFixed(2)}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Balance</div>
                                <div class="detail-value" style="${parseFloat(station.balance || 0) >= 0 ? 'color: #28a745;' : 'color: #dc3545;'}">
                                    $${Math.abs(parseFloat(station.balance || 0)).toFixed(2)} ${parseFloat(station.balance || 0) >= 0 ? '' : '(Overdue)'}
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Next Due Date</div>
                                <div class="detail-value">${station.next_due_date || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Member Since</div>
                                <div class="detail-value">${station.member_since ? new Date(station.member_since).toLocaleDateString() : 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value">${station.email || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value">${station.phone || 'N/A'}</div>
                            </div>
                        `;
                    }
                    
                    html += `
                        </div>
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            ${station.status === 'available' ? `
                            <button class="btn btn-primary" onclick="showAssignModal(${stationId})">
                                <i class="fas fa-user-plus"></i> Assign Member
                            </button>
                            ` : ''}
                            ${station.status === 'occupied' ? `
                            <button class="btn btn-danger" onclick="confirmRelease(${stationId})">
                                <i class="fas fa-times"></i> Release Station
                            </button>
                            ` : ''}
                        </div>
                    `;
                    
                    details.innerHTML = html;
                    modal.style.display = 'flex';
                }
            });
        }
        
        // Release station
        function releaseStation(stationId, event = null) {
            if (event) event.stopPropagation();
            
            if (confirm('Are you sure you want to release this station?')) {
                fetch('admin_spaces.php', {
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
                        loadStations();
                        closeModal();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        
        function confirmRelease(stationId) {
            releaseStation(stationId);
        }
        
        // Show assign modal
        function showAssignModal(stationId) {
            currentStationId = stationId;
            
            // Load available members
            fetch('admin_spaces.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_available_members'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('memberSelect');
                    select.innerHTML = '<option value="">Select a member...</option>';
                    
                    data.data.forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = `${member.full_name} (${member.member_code}) - Balance: $${parseFloat(member.balance).toFixed(2)}`;
                        select.appendChild(option);
                    });
                    
                    // Get station info
                    const station = stations.find(s => s.id == stationId);
                    if (station) {
                        document.getElementById('assignStationInfo').innerHTML = `
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-top: 10px;">
                                <div style="font-size: 14px; color: #6c757d;">Assigning Station:</div>
                                <div style="font-size: 16px; font-weight: 500;">${station.station_code}${station.desk_number}</div>
                            </div>
                        `;
                    }
                    
                    document.getElementById('assignModal').style.display = 'flex';
                }
            });
        }
        
        // Assign station to member
        function assignStation() {
            const memberId = document.getElementById('memberSelect').value;
            
            if (!memberId) {
                alert('Please select a member');
                return;
            }
            
            if (confirm('Assign this station to the selected member?')) {
                fetch('admin_spaces.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=assign_station&station_id=${currentStationId}&member_id=${memberId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        loadStations();
                        closeAssignModal();
                        closeModal();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        
        // Close modals
        function closeModal() {
            document.getElementById('stationModal').style.display = 'none';
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (modal.id === 'stationModal') closeModal();
                    if (modal.id === 'assignModal') closeAssignModal();
                }
            });
        });
        
        // Add keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeAssignModal();
            }
        });
    </script>
</body>
</html>