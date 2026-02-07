<?php
// printers.php - Printer Connections Management
require_once 'config.php';
// requireAdmin(); // Only admins can access

$action = $_GET['action'] ?? 'view';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_printers':
            $stmt = $db->prepare("
                SELECT p.*, 
                l.name as location_name,
                u.username as admin_username,
                u.full_name as admin_name,
                (SELECT COUNT(*) FROM printer_jobs WHERE printer_id = p.id AND status = 'pending') as pending_jobs,
                (SELECT COUNT(*) FROM printer_logs WHERE printer_id = p.id AND log_date = CURDATE()) as today_logs
                FROM printers p
                LEFT JOIN locations l ON p.location_id = l.id
                LEFT JOIN users u ON p.admin_id = u.id
                ORDER BY p.location_id, p.name
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $printers = [];
            while ($row = $result->fetch_assoc()) {
                $printers[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $printers]);
            break;
            
        case 'test_printer':
            $printerId = intval($_POST['printer_id']);
            
            // In real implementation, this would test actual printer connection
            // For demo, we'll simulate with random success/failure
            
            $success = rand(0, 1) == 1; // 50% chance of success
            
            if ($success) {
                // Log the test
                $stmt = $db->prepare("INSERT INTO printer_logs (printer_id, log_type, message, user_id) VALUES (?, 'test', 'Printer test successful', ?)");
                $userId = $_SESSION['user_id'];
                $stmt->bind_param("ii", $printerId, $userId);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Printer test successful']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Printer test failed - Connection timeout']);
            }
            break;
            
        case 'add_printer':
            $name = $_POST['name'];
            $model = $_POST['model'];
            $ipAddress = $_POST['ip_address'];
            $port = $_POST['port'];
            $locationId = $_POST['location_id'];
            $adminId = $_POST['admin_id'];
            $status = $_POST['status'];
            
            $stmt = $db->prepare("INSERT INTO printers (name, model, ip_address, port, location_id, admin_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssiis", $name, $model, $ipAddress, $port, $locationId, $adminId, $status);
            
            if ($stmt->execute()) {
                $printerId = $db->insert_id;
                addActivityLog('Printers', "Added new printer: {$name}", "Model: {$model}, IP: {$ipAddress}");
                echo json_encode(['success' => true, 'message' => 'Printer added successfully', 'id' => $printerId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add printer']);
            }
            break;
            
        case 'update_printer':
            $printerId = intval($_POST['id']);
            $name = $_POST['name'];
            $model = $_POST['model'];
            $ipAddress = $_POST['ip_address'];
            $port = $_POST['port'];
            $locationId = $_POST['location_id'];
            $adminId = $_POST['admin_id'];
            $status = $_POST['status'];
            
            $stmt = $db->prepare("UPDATE printers SET name = ?, model = ?, ip_address = ?, port = ?, location_id = ?, admin_id = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssiisi", $name, $model, $ipAddress, $port, $locationId, $adminId, $status, $printerId);
            
            if ($stmt->execute()) {
                addActivityLog('Printers', "Updated printer: {$name}", "ID: {$printerId}, New status: {$status}");
                echo json_encode(['success' => true, 'message' => 'Printer updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update printer']);
            }
            break;
            
        case 'delete_printer':
            $printerId = intval($_POST['id']);
            
            // Check if printer has active jobs
            $stmt = $db->prepare("SELECT COUNT(*) as job_count FROM printer_jobs WHERE printer_id = ? AND status IN ('pending', 'processing')");
            $stmt->bind_param("i", $printerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['job_count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete printer with active jobs']);
                exit;
            }
            
            $stmt = $db->prepare("DELETE FROM printers WHERE id = ?");
            $stmt->bind_param("i", $printerId);
            
            if ($stmt->execute()) {
                addActivityLog('Printers', "Deleted printer ID: {$printerId}", "Deleted by admin");
                echo json_encode(['success' => true, 'message' => 'Printer deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete printer']);
            }
            break;
            
        case 'get_printer_logs':
            $printerId = intval($_POST['printer_id']);
            $limit = intval($_POST['limit'] ?? 50);
            
            $stmt = $db->prepare("
                SELECT pl.*, u.username, u.full_name 
                FROM printer_logs pl
                LEFT JOIN users u ON pl.user_id = u.id
                WHERE pl.printer_id = ?
                ORDER BY pl.created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param("ii", $printerId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $logs]);
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
    <title><?php echo SITE_NAME; ?> - Printer Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* (Same CSS styles as vouchers.php, just adding printer-specific styles) */
        
        .printer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .printer-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #ddd;
            transition: all 0.3s;
        }
        
        .printer-card.online {
            border-left-color: #28a745;
        }
        
        .printer-card.offline {
            border-left-color: #dc3545;
        }
        
        .printer-card.maintenance {
            border-left-color: #ffc107;
        }
        
        .printer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .printer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .printer-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .printer-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-online {
            background: #d4edda;
            color: #155724;
        }
        
        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        
        .printer-info {
            margin: 15px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #333;
            font-family: monospace;
        }
        
        .printer-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard-page">
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-print"></i> Printer Management</h1>
                    <p>Manage printer connections and status</p>
                </div>
                <div class="user-menu">
                    <!-- Same as vouchers.php -->
                </div>
            </div>
            
            <div class="tabs">
                <div class="tab <?php echo $action === 'view' ? 'active' : ''; ?>" onclick="switchTab('view')">
                    View Printers
                </div>
                <div class="tab <?php echo $action === 'add' ? 'active' : ''; ?>" onclick="switchTab('add')">
                    Add Printer
                </div>
                <div class="tab <?php echo $action === 'logs' ? 'active' : ''; ?>" onclick="switchTab('logs')">
                    System Logs
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid" id="printerStats" style="display: none;">
                <!-- Stats will be loaded here -->
            </div>
            
            <div id="tab-view" class="tab-content" style="<?php echo $action === 'view' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Printers</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadPrinters()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="printer-grid" id="printerGrid">
                        <!-- Printers will be loaded here -->
                    </div>
                </div>
            </div>
            
            <div id="tab-add" class="tab-content" style="<?php echo $action === 'add' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Printer</h3>
                    </div>
                    
                    <form id="addPrinterForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Printer Name *</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., Reception Printer" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-control" placeholder="e.g., Epson TM-T88V" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">IP Address *</label>
                                <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.100" required pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Port *</label>
                                <input type="number" name="port" class="form-control" placeholder="9100" value="9100" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Location *</label>
                                <select name="location_id" class="form-control" required>
                                    <option value="">-- Select Location --</option>
                                    <?php
                                    $stmt = $db->prepare("SELECT * FROM locations ORDER BY name");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while ($row = $result->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>">
                                            <?php echo $row['name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Admin Responsible</label>
                                <select name="admin_id" class="form-control">
                                    <option value="">-- Select Admin --</option>
                                    <?php
                                    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'admin' OR role = 'superadmin' ORDER BY full_name");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while ($row = $result->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>">
                                            <?php echo $row['full_name']; ?> (<?php echo $row['username']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" style="margin-top: 20px; width: 100%;">
                            <i class="fas fa-plus"></i> Add Printer
                        </button>
                    </form>
                </div>
            </div>
            
            <div id="tab-logs" class="tab-content" style="<?php echo $action === 'logs' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Printer System Logs</h3>
                        <div>
                            <select id="logPrinterFilter" class="form-control" style="width: auto; display: inline-block;" onchange="loadPrinterLogs()">
                                <option value="0">All Printers</option>
                                <!-- Printer options will be loaded here -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table" id="logsTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Printer</th>
                                    <th>Type</th>
                                    <th>Message</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <!-- Logs will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Modal for Printer Details -->
            <div id="printerModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
                <div class="modal-content" style="background: white; margin: 50px auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 id="modalTitle">Printer Details</h3>
                        <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                    </div>
                    <div id="modalBody">
                        <!-- Modal content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let printers = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            loadPrinters();
            if ('<?php echo $action; ?>' === 'logs') {
                loadPrinterOptions();
                loadPrinterLogs();
            }
            
            // Form submission
            document.getElementById('addPrinterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                addPrinter();
            });
        });
        
        function switchTab(tab) {
            window.location.href = 'printers.php?action=' + tab;
        }
        
        function loadPrinters() {
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_printers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    printers = data.data;
                    renderPrinters(data.data);
                    updateStats(data.data);
                }
            });
        }
        
        function renderPrinters(printersData) {
            const grid = document.getElementById('printerGrid');
            grid.innerHTML = '';
            
            if (printersData.length === 0) {
                grid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-print" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                        <h3>No printers configured</h3>
                        <p>Add your first printer to get started</p>
                    </div>
                `;
                return;
            }
            
            printersData.forEach(printer => {
                const card = document.createElement('div');
                card.className = `printer-card ${printer.status}`;
                card.dataset.id = printer.id;
                
                let statusClass = 'status-online';
                if (printer.status === 'offline') statusClass = 'status-offline';
                if (printer.status === 'maintenance') statusClass = 'status-maintenance';
                
                card.innerHTML = `
                    <div class="printer-header">
                        <div class="printer-name">${printer.name}</div>
                        <div class="printer-status ${statusClass}">${printer.status}</div>
                    </div>
                    
                    <div class="printer-info">
                        <div class="info-row">
                            <span class="info-label">Model:</span>
                            <span class="info-value">${printer.model}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">IP Address:</span>
                            <span class="info-value">${printer.ip_address}:${printer.port}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Location:</span>
                            <span class="info-value">${printer.location_name || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Admin:</span>
                            <span class="info-value">${printer.admin_name || 'N/A'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pending Jobs:</span>
                            <span class="info-value ${printer.pending_jobs > 0 ? 'badge badge-warning' : ''}">${printer.pending_jobs}</span>
                        </div>
                    </div>
                    
                    <div class="printer-actions">
                        <button class="btn btn-sm btn-info" onclick="testPrinter(${printer.id})">
                            <i class="fas fa-bolt"></i> Test
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="viewPrinterDetails(${printer.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="editPrinter(${printer.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deletePrinter(${printer.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }
        
        function updateStats(printersData) {
            const statsContainer = document.getElementById('printerStats');
            
            const onlineCount = printersData.filter(p => p.status === 'online').length;
            const offlineCount = printersData.filter(p => p.status === 'offline').length;
            const maintenanceCount = printersData.filter(p => p.status === 'maintenance').length;
            const totalJobs = printersData.reduce((sum, p) => sum + parseInt(p.pending_jobs), 0);
            
            statsContainer.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${printersData.length}</div>
                    <div class="stat-label">Total Printers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #28a745;">${onlineCount}</div>
                    <div class="stat-label">Online</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #dc3545;">${offlineCount}</div>
                    <div class="stat-label">Offline</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #ffc107;">${maintenanceCount}</div>
                    <div class="stat-label">Maintenance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #17a2b8;">${totalJobs}</div>
                    <div class="stat-label">Pending Jobs</div>
                </div>
            `;
            
            statsContainer.style.display = 'grid';
        }
        
        function testPrinter(printerId) {
            if (!confirm('Test this printer connection?')) return;
            
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=test_printer&printer_id=${printerId}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                loadPrinters();
            });
        }
        
        function viewPrinterDetails(printerId) {
            const printer = printers.find(p => p.id == printerId);
            if (!printer) return;
            
            document.getElementById('modalTitle').textContent = printer.name + ' Details';
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="printer-info">
                    <div class="info-row">
                        <span class="info-label">ID:</span>
                        <span class="info-value">${printer.id}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Model:</span>
                        <span class="info-value">${printer.model}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Connection:</span>
                        <span class="info-value">${printer.ip_address}:${printer.port}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location:</span>
                        <span class="info-value">${printer.location_name || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admin Responsible:</span>
                        <span class="info-value">${printer.admin_name || 'N/A'} (${printer.admin_username || 'N/A'})</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="badge ${printer.status === 'online' ? 'badge-success' : printer.status === 'offline' ? 'badge-danger' : 'badge-warning'}">
                                ${printer.status}
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created:</span>
                        <span class="info-value">${printer.created_at || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Updated:</span>
                        <span class="info-value">${printer.updated_at || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Pending Jobs:</span>
                        <span class="info-value">${printer.pending_jobs}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Today's Logs:</span>
                        <span class="info-value">${printer.today_logs}</span>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <h4>Recent Activity</h4>
                    <div id="printerLogsContent">
                        Loading logs...
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="editPrinter(${printer.id})">
                        <i class="fas fa-edit"></i> Edit Printer
                    </button>
                    <button class="btn btn-info" onclick="loadPrinterLogsModal(${printer.id})">
                        <i class="fas fa-history"></i> View Full Logs
                    </button>
                    <button class="btn btn-success" onclick="testPrinter(${printer.id})">
                        <i class="fas fa-bolt"></i> Test Connection
                    </button>
                </div>
            `;
            
            // Load recent logs for this printer
            loadPrinterLogsModal(printerId);
            
            document.getElementById('printerModal').style.display = 'block';
        }
        
        function loadPrinterLogsModal(printerId) {
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_printer_logs&printer_id=${printerId}&limit=10`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('printerLogsContent');
                    if (data.data.length === 0) {
                        container.innerHTML = '<p style="color: #666; text-align: center;">No recent logs</p>';
                        return;
                    }
                    
                    let logsHtml = '<div style="max-height: 200px; overflow-y: auto;">';
                    data.data.forEach(log => {
                        const time = new Date(log.created_at).toLocaleTimeString();
                        logsHtml += `
                            <div style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 12px;">
                                <span style="color: #666;">${time}</span>
                                <span style="margin-left: 10px; color: #333;">${log.message}</span>
                                <span style="margin-left: 10px; color: #888;">- ${log.full_name || log.username || 'System'}</span>
                            </div>
                        `;
                    });
                    logsHtml += '</div>';
                    container.innerHTML = logsHtml;
                }
            });
        }
        
        function editPrinter(printerId) {
            const printer = printers.find(p => p.id == printerId);
            if (!printer) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Printer';
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <form id="editPrinterForm">
                    <input type="hidden" name="id" value="${printer.id}">
                    
                    <div class="form-group">
                        <label class="form-label">Printer Name *</label>
                        <input type="text" name="name" class="form-control" value="${printer.name}" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Model *</label>
                        <input type="text" name="model" class="form-control" value="${printer.model}" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">IP Address *</label>
                            <input type="text" name="ip_address" class="form-control" value="${printer.ip_address}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Port *</label>
                            <input type="number" name="port" class="form-control" value="${printer.port}" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Location *</label>
                            <select name="location_id" class="form-control" required>
                                <option value="">-- Select Location --</option>
                                <?php
                                $stmt = $db->prepare("SELECT * FROM locations ORDER BY name");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>" ${printer.location_id == <?php echo $row['id']; ?> ? 'selected' : ''}>
                                        <?php echo $row['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Admin Responsible</label>
                            <select name="admin_id" class="form-control">
                                <option value="">-- Select Admin --</option>
                                <?php
                                $stmt = $db->prepare("SELECT * FROM users WHERE role = 'admin' OR role = 'superadmin' ORDER BY full_name");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>" ${printer.admin_id == <?php echo $row['id']; ?> ? 'selected' : ''}>
                                        <?php echo $row['full_name']; ?> (<?php echo $row['username']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="online" ${printer.status === 'online' ? 'selected' : ''}>Online</option>
                            <option value="offline" ${printer.status === 'offline' ? 'selected' : ''}>Offline</option>
                            <option value="maintenance" ${printer.status === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                        </select>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn btn-danger" onclick="deletePrinter(${printer.id})">
                            <i class="fas fa-trash"></i> Delete Printer
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            Cancel
                        </button>
                    </div>
                </form>
            `;
            
            // Add form submission handler
            document.getElementById('editPrinterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updatePrinter();
            });
            
            document.getElementById('printerModal').style.display = 'block';
        }
        
        function addPrinter() {
            const form = document.getElementById('addPrinterForm');
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => data[key] = value);
            
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=add_printer&${new URLSearchParams(data)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    form.reset();
                    loadPrinters();
                    switchTab('view');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function updatePrinter() {
            const form = document.getElementById('editPrinterForm');
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => data[key] = value);
            
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=update_printer&${new URLSearchParams(data)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal();
                    loadPrinters();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function deletePrinter(printerId) {
            if (!confirm('Are you sure you want to delete this printer? This action cannot be undone.')) {
                return;
            }
            
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=delete_printer&id=${printerId}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    closeModal();
                    loadPrinters();
                }
            });
        }
        
        function loadPrinterOptions() {
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_printers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('logPrinterFilter');
                    // Clear existing options except the first one
                    while (select.options.length > 1) {
                        select.remove(1);
                    }
                    
                    data.data.forEach(printer => {
                        const option = document.createElement('option');
                        option.value = printer.id;
                        option.textContent = printer.name;
                        select.appendChild(option);
                    });
                }
            });
        }
        
        function loadPrinterLogs() {
            const printerId = document.getElementById('logPrinterFilter').value;
            
            fetch('printers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_printer_logs&printer_id=${printerId}&limit=100`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('logsTableBody');
                    tbody.innerHTML = '';
                    
                    if (data.data.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666;">
                                    No logs found
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    data.data.forEach(log => {
                        const date = new Date(log.created_at);
                        const timeString = date.toLocaleString();
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${timeString}</td>
                            <td>${getPrinterName(log.printer_id)}</td>
                            <td>
                                <span class="badge ${log.log_type === 'error' ? 'badge-danger' : log.log_type === 'warning' ? 'badge-warning' : 'badge-info'}">
                                    ${log.log_type}
                                </span>
                            </td>
                            <td>${log.message}</td>
                            <td>${log.full_name || log.username || 'System'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            });
        }
        
        function getPrinterName(printerId) {
            const printer = printers.find(p => p.id == printerId);
            return printer ? printer.name : 'Unknown';
        }
        
        function closeModal() {
            document.getElementById('printerModal').style.display = 'none';
        }
        
        // Auto-refresh printers every 30 seconds
        if ('<?php echo $action; ?>' === 'view') {
            setInterval(loadPrinters, 30000);
        }
    </script>
</body>
</html>