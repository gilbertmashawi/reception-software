<?php
// changes.php - Voucher Sales Editing Interface
require_once 'config.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($_POST['action'] === 'get_sales') {
        $date = $_POST['date'] ?? '';
        
        $sql = "SELECT vs.*, 
                CONCAT(lsa.station_code, lsa.desk_number) as station_display,
                GROUP_CONCAT(CONCAT(vsi.quantity, 'x ', vt.name) SEPARATOR ', ') as items,
                GROUP_CONCAT(DISTINCT v.voucher_code ORDER BY v.id SEPARATOR ', ') as voucher_codes
                FROM voucher_sales vs
                LEFT JOIN linkspot_station_addresses lsa ON vs.station_address_id = lsa.id
                LEFT JOIN voucher_sale_items vsi ON vs.id = vsi.sale_id
                LEFT JOIN voucher_types vt ON vsi.voucher_type_id = vt.id
                LEFT JOIN vouchers v ON vs.id = v.sale_id
                WHERE 1=1";
        
        if ($date) {
            $sql .= " AND vs.sale_date = ?";
        }
        
        $sql .= " GROUP BY vs.id ORDER BY vs.id DESC";
        
        $stmt = $db->prepare($sql);
        if ($date) {
            $stmt->bind_param("s", $date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
        
        $response = ['success' => true, 'data' => $sales];
    }
    
    elseif ($_POST['action'] === 'update_sale') {
        $saleId = intval($_POST['sale_id']);
        $data = json_decode($_POST['data'], true);
        
        $db->begin_transaction();
        
        try {
            // Update sale record
            $stmt = $db->prepare("UPDATE voucher_sales 
                                 SET sale_date = ?, 
                                     sale_time = ?,
                                     total_amount = ?,
                                     amount_received = ?,
                                     change_amount = ?,
                                     customer_name = ?,
                                     station_address_id = ?
                                 WHERE id = ?");
            
            $stmt->bind_param(
                "ssdddsii",
                $data['date'],
                $data['time'],
                $data['total'],
                $data['received'],
                $data['change'],
                $data['customer'],
                $data['station'] ?: null,
                $saleId
            );
            $stmt->execute();
            
            // Log activity
            addActivityLog('Voucher Sale Edit', 
                         "Updated sale #{$saleId}", 
                         "Modified by {$user['full_name']}");
            
            $db->commit();
            $response = ['success' => true, 'message' => 'Sale updated successfully'];
            
        } catch (Exception $e) {
            $db->rollback();
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    elseif ($_POST['action'] === 'get_stations') {
        $stmt = $db->prepare("SELECT id, CONCAT(station_code, desk_number) as name 
                             FROM linkspot_station_addresses 
                             ORDER BY station_code, desk_number");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stations = [];
        while ($row = $result->fetch_assoc()) {
            $stations[] = $row;
        }
        
        $response = ['success' => true, 'data' => $stations];
    }
    
    echo json_encode($response);
    exit;
}

// Get sales for initial display
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT vs.*, 
                     CONCAT(lsa.station_code, lsa.desk_number) as station_display
                     FROM voucher_sales vs
                     LEFT JOIN linkspot_station_addresses lsa ON vs.station_address_id = lsa.id
                     WHERE vs.sale_date = ?
                     ORDER BY vs.id DESC");
$stmt->bind_param("s", $today);
$stmt->execute();
$initialSales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Voucher Sales Editor</title>
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
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
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
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .sales-table th {
            background: var(--secondary);
            color: white;
            padding: 12px 8px;
            font-weight: 500;
            font-size: 14px;
            text-align: center;
            position: sticky;
            top: 0;
        }
        
        .sales-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: center;
            font-size: 13px;
        }
        
        .sales-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .sales-table input {
            width: 100px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
        }
        
        .sales-table input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.1);
        }
        
        .sales-table select {
            width: 100px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            background: white;
        }
        
        .change-cell {
            font-weight: 600;
            color: var(--primary);
        }
        
        .negative-change {
            color: var(--danger);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        /* Button Styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
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
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        
        /* Alert Styles */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
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
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
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
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sales-table input,
            .sales-table select {
                width: 80px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip:hover:after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
            <h3><?php echo SITE_NAME; ?></h3>
        </div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="vouchers.php" class="nav-link">
                    <i class="fas fa-wifi"></i> Vouchers
                </a>
            </li>
            <li class="nav-item">
                <a href="changes.php" class="nav-link active">
                    <i class="fas fa-edit"></i> Sales Editor
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-edit"></i> Voucher Sales Editor</h1>
                <p>View and edit voucher sales records</p>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 500;"><?php echo $_SESSION['full_name'] ?? 'User'; ?></div>
                    <div style="font-size: 12px; color: #6c757d;"><?php echo ucfirst($_SESSION['role'] ?? 'staff'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sales Records</h3>
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>Date:</label>
                        <input type="date" id="filterDate" value="<?php echo $today; ?>" onchange="loadSales()">
                    </div>
                    <button class="btn btn-primary" onclick="loadSales()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div id="alertMessage" class="alert"></div>
            
            <div class="table-responsive">
                <table class="sales-table" id="salesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Voucher Codes</th>
                            <th>Station</th>
                            <th>Total</th>
                            <th>Received</th>
                            <th>Change</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody">
                        <?php foreach ($initialSales as $sale): ?>
                        <tr data-id="<?php echo $sale['id']; ?>">
                            <td><?php echo $sale['id']; ?></td>
                            <td><input type="date" class="edit-date" value="<?php echo $sale['sale_date']; ?>"></td>
                            <td><input type="time" class="edit-time" value="<?php echo substr($sale['sale_time'], 0, 8); ?>"></td>
                            <td><input type="text" class="edit-customer" value="<?php echo htmlspecialchars($sale['customer_name'] ?? ''); ?>" placeholder="Customer"></td>
                            <td class="items-cell">-</td>
                            <td class="codes-cell">-</td>
                            <td>
                                <select class="edit-station">
                                    <option value="">No Station</option>
                                    <?php
                                    $stationStmt = $db->prepare("SELECT id, CONCAT(station_code, desk_number) as name FROM linkspot_station_addresses ORDER BY station_code, desk_number");
                                    $stationStmt->execute();
                                    $stationResult = $stationStmt->get_result();
                                    while ($station = $stationResult->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $station['id']; ?>" <?php echo $station['id'] == $sale['station_address_id'] ? 'selected' : ''; ?>>
                                        <?php echo $station['name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            <td><input type="number" class="edit-total" value="<?php echo $sale['total_amount']; ?>" step="0.01" oninput="calculateChange(this)"></td>
                            <td><input type="number" class="edit-received" value="<?php echo $sale['amount_received'] ?? $sale['total_amount']; ?>" step="0.01" oninput="calculateChange(this)"></td>
                            <td class="change-cell <?php echo ($sale['change_amount'] ?? 0) < 0 ? 'negative-change' : ''; ?>"><?php echo number_format($sale['change_amount'] ?? 0, 2); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-success btn-sm" onclick="updateSale(this)">
                                        <i class="fas fa-save"></i>
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="viewDetails(<?php echo $sale['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($initialSales)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; color: #6c757d; padding: 40px;">
                                <i class="fas fa-info-circle"></i> No sales found for today
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal" id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 10px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--secondary);">Sale Details</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div style="padding: 20px;" id="modalContent">
                Loading...
            </div>
        </div>
    </div>

    <script>
        let stations = [];
        
        // Load stations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadStations();
        });
        
        // Load stations for dropdown
        function loadStations() {
            fetch('changes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_stations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    stations = data.data;
                }
            });
        }
        
        // Calculate change
        function calculateChange(element) {
            const row = element.closest('tr');
            const total = parseFloat(row.querySelector('.edit-total').value) || 0;
            const received = parseFloat(row.querySelector('.edit-received').value) || 0;
            const changeCell = row.querySelector('.change-cell');
            
            const change = received - total;
            changeCell.textContent = change.toFixed(2);
            changeCell.classList.toggle('negative-change', change < 0);
        }
        
        // Update sale
        function updateSale(button) {
            const row = button.closest('tr');
            const saleId = row.dataset.id;
            
            const data = {
                date: row.querySelector('.edit-date').value,
                time: row.querySelector('.edit-time').value,
                customer: row.querySelector('.edit-customer').value,
                station: row.querySelector('.edit-station').value,
                total: parseFloat(row.querySelector('.edit-total').value) || 0,
                received: parseFloat(row.querySelector('.edit-received').value) || 0,
                change: parseFloat(row.querySelector('.change-cell').textContent) || 0
            };
            
            // Validate
            if (data.received < (data.total - 0.01)) {
                showAlert('Amount received is less than total amount', 'danger');
                return;
            }
            
            // Show loading state
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            // Send update
            fetch('changes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'ajax': 'true',
                    'action': 'update_sale',
                    'sale_id': saleId,
                    'data': JSON.stringify(data)
                })
            })
            .then(response => response.json())
            .then(data => {
                button.innerHTML = originalHtml;
                button.disabled = false;
                
                if (data.success) {
                    showAlert('Sale updated successfully!', 'success');
                    setTimeout(() => {
                        hideAlert();
                    }, 3000);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                button.innerHTML = originalHtml;
                button.disabled = false;
                showAlert('Error updating sale: ' + error.message, 'danger');
            });
        }
        
        // Load sales with filter
        function loadSales() {
            const date = document.getElementById('filterDate').value;
            
            fetch('changes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'ajax': 'true',
                    'action': 'get_sales',
                    'date': date
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderSalesTable(data.data);
                }
            });
        }
        
        // Render sales table
        function renderSalesTable(sales) {
            const tbody = document.getElementById('salesTableBody');
            
            if (sales.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" style="text-align: center; color: #6c757d; padding: 40px;">
                            <i class="fas fa-info-circle"></i> No sales found for selected date
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            
            sales.forEach(sale => {
                const stationOptions = stations.map(s => 
                    `<option value="${s.id}" ${s.id == sale.station_address_id ? 'selected' : ''}>${s.name}</option>`
                ).join('');
                
                html += `
                    <tr data-id="${sale.id}">
                        <td>${sale.id}</td>
                        <td><input type="date" class="edit-date" value="${sale.sale_date}"></td>
                        <td><input type="time" class="edit-time" value="${sale.sale_time.substring(0, 8)}"></td>
                        <td><input type="text" class="edit-customer" value="${escapeHtml(sale.customer_name || '')}" placeholder="Customer"></td>
                        <td class="items-cell">${escapeHtml(sale.items || '-')}</td>
                        <td class="codes-cell">${escapeHtml(sale.voucher_codes || '-')}</td>
                        <td>
                            <select class="edit-station">
                                <option value="">No Station</option>
                                ${stationOptions}
                            </select>
                        </td>
                        <td><input type="number" class="edit-total" value="${sale.total_amount}" step="0.01" oninput="calculateChange(this)"></td>
                        <td><input type="number" class="edit-received" value="${sale.amount_received || sale.total_amount}" step="0.01" oninput="calculateChange(this)"></td>
                        <td class="change-cell ${(sale.change_amount || 0) < 0 ? 'negative-change' : ''}">${(sale.change_amount || 0).toFixed(2)}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-success btn-sm" onclick="updateSale(this)">
                                    <i class="fas fa-save"></i>
                                </button>
                                <button class="btn btn-info btn-sm" onclick="viewDetails(${sale.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // Helper to escape HTML
        function escapeHtml(text) {
            if (!text) return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // View sale details
        function viewDetails(saleId) {
            // You can implement this to show more details
            alert('Sale details for ID: ' + saleId + '\n\nThis would show items and voucher codes in a modal.');
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Show alert
        function showAlert(message, type) {
            const alert = document.getElementById('alertMessage');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
        }
        
        // Hide alert
        function hideAlert() {
            document.getElementById('alertMessage').style.display = 'none';
        }
        
        // Auto-refresh every 60 seconds
        setInterval(loadSales, 60000);
    </script>
</body>
</html>