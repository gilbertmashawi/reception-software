<?php
// admin_mall.php - Summacity Mall Management
require_once 'config.php';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_all_shops':
            $stmt = $db->prepare("SELECT 
                ss.*, 
                sm.full_name as tenant_name,
                sm.member_code,
                sm.balance,
                sm.next_due_date,
                sm.business_name,
                sm.rent_amount,
                sm.business_type,
                sm.email,
                sm.phone
                FROM summarcity_shops ss
                LEFT JOIN summarcity_members sm ON ss.current_tenant_id = sm.id
                ORDER BY 
                    CAST(SUBSTRING(ss.shop_number FROM 2) AS UNSIGNED)");
            $stmt->execute();
            $result = $stmt->get_result();
            $shops = [];
            while ($row = $result->fetch_assoc()) {
                $shops[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $shops]);
            break;
            
        case 'get_shop_details':
            $shopId = intval($_POST['shop_id']);
            
            $stmt = $db->prepare("SELECT 
                ss.*, 
                sm.full_name as tenant_name,
                sm.member_code,
                sm.balance,
                sm.next_due_date,
                sm.business_name,
                sm.business_type,
                sm.rent_amount,
                sm.email,
                sm.phone,
                sm.id_number,
                sm.created_at as member_since,
                sm.notes
                FROM summarcity_shops ss
                LEFT JOIN summarcity_members sm ON ss.current_tenant_id = sm.id
                WHERE ss.id = ?");
            $stmt->bind_param("i", $shopId);
            $stmt->execute();
            $result = $stmt->get_result();
            $shop = $result->fetch_assoc();
            
            echo json_encode(['success' => true, 'data' => $shop]);
            break;
            
        case 'release_shop':
            $shopId = intval($_POST['shop_id']);
            
            $db->begin_transaction();
            try {
                // Get shop details
                $stmt = $db->prepare("SELECT current_tenant_id FROM summarcity_shops WHERE id = ?");
                $stmt->bind_param("i", $shopId);
                $stmt->execute();
                $result = $stmt->get_result();
                $shop = $result->fetch_assoc();
                
                if ($shop['current_tenant_id']) {
                    // Update member shop assignment
                    $stmt = $db->prepare("UPDATE summarcity_members SET shop_id = NULL, shop_number = NULL WHERE id = ?");
                    $stmt->bind_param("i", $shop['current_tenant_id']);
                    $stmt->execute();
                }
                
                // Release shop
                $stmt = $db->prepare("UPDATE summarcity_shops SET status = 'available', current_tenant_id = NULL, current_tenant_name = NULL, rent_amount = NULL, next_due_date = NULL WHERE id = ?");
                $stmt->bind_param("i", $shopId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Summarcity Mall', "Released shop ID {$shopId}", "Admin action");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Shop released successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'assign_shop':
            $shopId = intval($_POST['shop_id']);
            $memberId = intval($_POST['member_id']);
            $rentAmount = floatval($_POST['rent_amount']);
            $nextDueDate = $_POST['next_due_date'];
            
            $db->begin_transaction();
            try {
                // Get shop and member details
                $stmt = $db->prepare("SELECT shop_number FROM summarcity_shops WHERE id = ?");
                $stmt->bind_param("i", $shopId);
                $stmt->execute();
                $result = $stmt->get_result();
                $shop = $result->fetch_assoc();
                
                $stmt = $db->prepare("SELECT full_name, business_name FROM summarcity_members WHERE id = ?");
                $stmt->bind_param("i", $memberId);
                $stmt->execute();
                $result = $stmt->get_result();
                $member = $result->fetch_assoc();
                
                if (!$shop || !$member) {
                    throw new Exception('Shop or member not found');
                }
                
                // Update shop
                $stmt = $db->prepare("UPDATE summarcity_shops SET 
                    status = 'occupied', 
                    current_tenant_id = ?, 
                    current_tenant_name = ?, 
                    rent_amount = ?, 
                    next_due_date = ? 
                    WHERE id = ?");
                $stmt->bind_param("issdi", $memberId, $member['full_name'], $rentAmount, $nextDueDate, $shopId);
                $stmt->execute();
                
                // Update member
                $stmt = $db->prepare("UPDATE summarcity_members SET shop_id = ?, shop_number = ?, rent_amount = ?, next_due_date = ? WHERE id = ?");
                $stmt->bind_param("issdi", $shopId, $shop['shop_number'], $rentAmount, $nextDueDate, $memberId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Summarcity Mall', "Assigned shop {$shop['shop_number']} to {$member['full_name']} ({$member['business_name']})", "Admin action");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Shop assigned successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_available_members':
            $stmt = $db->prepare("SELECT id, full_name, member_code, business_name, balance, rent_amount FROM summarcity_members WHERE status = 'active' AND (shop_id IS NULL OR shop_id = 0) ORDER BY full_name");
            $stmt->execute();
            $result = $stmt->get_result();
            $members = [];
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $members]);
            break;
            
        case 'record_payment':
            $shopId = intval($_POST['shop_id']);
            $payerName = $_POST['payer_name'];
            $monthPaid = $_POST['month_paid'];
            $amount = floatval($_POST['amount']);
            $paymentMethod = $_POST['payment_method'];
            $description = $_POST['description'];
            
            $db->begin_transaction();
            try {
                // Insert payment record
                $stmt = $db->prepare("INSERT INTO mall_payments (payment_date, month_paid, payer_name, amount, payment_method, description, shop_id) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdssi", $monthPaid, $payerName, $amount, $paymentMethod, $description, $shopId);
                $stmt->execute();
                
                // Update tenant balance
                $stmt = $db->prepare("UPDATE summarcity_members SET balance = balance - ? WHERE shop_id = ?");
                $stmt->bind_param("di", $amount, $shopId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Summarcity Mall', "Recorded payment from {$payerName} for {$monthPaid}", "Amount: \${$amount}, Shop ID: {$shopId}");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
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
    <title><?php echo SITE_NAME; ?> - Summacity Mall Management</title>
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
        
        /* Shop Grid Container */
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
        
        .btn-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        
        /* Shop Grid */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }
        
        .shop-card {
            padding: 15px 10px;
            text-align: center;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        .shop-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .shop-card.available {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .shop-card.occupied {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .shop-card.maintenance {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .shop-card.reserved {
            background: #cce5ff;
            color: #004085;
            border-color: #b8daff;
        }
        
        .shop-code {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .shop-name {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
            min-height: 20px;
        }
        
        .shop-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .available .shop-status {
            background: #28a745;
            color: white;
        }
        
        .occupied .shop-status {
            background: #dc3545;
            color: white;
        }
        
        .shop-tenant {
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin: 5px 0;
            font-weight: 500;
        }
        
        .shop-business {
            font-size: 11px;
            color: #555;
            margin: 3px 0;
        }
        
        .shop-balance {
            font-size: 12px;
            font-weight: 500;
        }
        
        .shop-balance.positive {
            color: #28a745;
        }
        
        .shop-balance.negative {
            color: #dc3545;
        }
        
        .shop-rent {
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .shop-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .shop-card:hover .shop-actions {
            opacity: 1;
        }
        
        .shop-action-btn {
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
        
        .payment-btn {
            background: var(--warning);
            color: #212529;
        }
        
        .payment-btn:hover {
            background: #e0a800;
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
            
            .shop-grid {
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
            
            .shop-grid {
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
                    <h1><i class="fas fa-store"></i> Summacity Mall Management</h1>
                    <p>Manage mall shops and tenant assignments</p>
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
            
            <!-- Shop Grid -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Shops</h3>
                    <button class="btn btn-primary" onclick="loadShops()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                
                <div id="shopGridContainer">
                    <!-- Shop grid will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Shop Details Modal -->
    <div class="modal" id="shopModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Shop Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="shopDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Assign Shop Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Shop</h3>
                <button class="modal-close" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Tenant</label>
                    <select id="memberSelect" class="form-control">
                        <option value="">Select a tenant...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Monthly Rent ($)</label>
                    <input type="number" id="rentAmount" class="form-control" step="0.01" min="0" placeholder="Enter rent amount">
                </div>
                <div class="form-group">
                    <label class="form-label">Next Due Date</label>
                    <input type="date" id="nextDueDate" class="form-control">
                </div>
                <div id="assignShopInfo"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                <button class="btn btn-primary" onclick="assignShop()">Assign</button>
            </div>
        </div>
    </div>
    
    <!-- Record Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Record Payment</h3>
                <button class="modal-close" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="paymentShopInfo"></div>
                <div class="form-group">
                    <label class="form-label">Payer Name</label>
                    <input type="text" id="payerName" class="form-control" placeholder="Enter payer name">
                </div>
                <div class="form-group">
                    <label class="form-label">Month Paid For</label>
                    <input type="text" id="monthPaid" class="form-control" placeholder="e.g., January 2026">
                </div>
                <div class="form-group">
                    <label class="form-label">Amount ($)</label>
                    <input type="number" id="paymentAmount" class="form-control" step="0.01" min="0" placeholder="Enter amount">
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select id="paymentMethod" class="form-control">
                        <option value="Cash">Cash</option>
                        <option value="Ecocash">Ecocash</option>
                        <option value="Swipe">Swipe</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea id="paymentDescription" class="form-control" rows="2" placeholder="Any additional notes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <button class="btn btn-primary" onclick="recordPayment()">Record Payment</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentShopId = null;
        let shops = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadShops();
        });
        
        // Load shops
        function loadShops() {
            fetch('admin_mall.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_all_shops'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    shops = data.data;
                    updateStats(data.data);
                    displayShops(data.data);
                }
            });
        }
        
        // Update stats
        function updateStats(shops) {
            const total = shops.length;
            const occupied = shops.filter(s => s.status === 'occupied').length;
            const available = shops.filter(s => s.status === 'available').length;
            const totalRent = shops.filter(s => s.status === 'occupied').reduce((sum, shop) => sum + (parseFloat(shop.rent_amount) || 0), 0);
            const occupancyRate = total > 0 ? Math.round((occupied / total) * 100) : 0;
            
            const statsGrid = document.getElementById('statsGrid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-value">${total}</div>
                    <div class="stat-label">Total Shops</div>
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
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">$${totalRent.toFixed(2)}</div>
                    <div class="stat-label">Monthly Rent Income</div>
                </div>
            `;
        }
        
        // Display shops
        function displayShops(shops) {
            const shopGridContainer = document.getElementById('shopGridContainer');
            
            // Sort shops by shop number
            const sortedShops = [...shops].sort((a, b) => {
                const numA = parseInt(a.shop_number.replace(/[^0-9]/g, '')) || 0;
                const numB = parseInt(b.shop_number.replace(/[^0-9]/g, '')) || 0;
                return numA - numB;
            });
            
            const gridDiv = document.createElement('div');
            gridDiv.className = 'shop-grid';
            
            sortedShops.forEach(shop => {
                const card = createShopCard(shop);
                gridDiv.appendChild(card);
            });
            
            shopGridContainer.innerHTML = '';
            shopGridContainer.appendChild(gridDiv);
        }
        
        // Create shop card
        function createShopCard(shop) {
            const card = document.createElement('div');
            card.className = `shop-card ${shop.status}`;
            card.dataset.id = shop.id;
            
            const balance = parseFloat(shop.balance || 0);
            const rent = parseFloat(shop.rent_amount || 0);
            const balanceClass = balance <= 0 ? 'positive' : 'negative';
            const balanceText = balance >= 0 ? `$${balance.toFixed(2)}` : `-$${Math.abs(balance).toFixed(2)}`;
            
            card.innerHTML = `
                <div class="shop-actions">
                    <button class="shop-action-btn view-btn" onclick="viewShop(${shop.id}, event)">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${shop.status === 'occupied' ? `
                    <button class="shop-action-btn payment-btn" onclick="showPaymentModal(${shop.id}, event)">
                        <i class="fas fa-money-bill"></i>
                    </button>
                    <button class="shop-action-btn release-btn" onclick="releaseShop(${shop.id}, event)">
                        <i class="fas fa-times"></i>
                    </button>
                    ` : ''}
                </div>
                <div class="shop-code">${shop.shop_number}</div>
                <div class="shop-name">${shop.shop_name || ''}</div>
                <div class="shop-status">${shop.status}</div>
                ${shop.tenant_name ? `
                <div class="shop-tenant" title="${shop.tenant_name}">
                    ${shop.tenant_name}
                </div>
                <div class="shop-business">${shop.business_name || ''}</div>
                <div class="shop-balance ${balanceClass}">
                    Balance: ${balanceText}
                </div>
                <div class="shop-rent">
                    Rent: $${rent.toFixed(2)}
                </div>
                ` : ''}
            `;
            
            // Add click handler for the entire card
            card.addEventListener('click', function(e) {
                if (!e.target.closest('.shop-actions')) {
                    viewShop(shop.id);
                }
            });
            
            return card;
        }
        
        // View shop details
        function viewShop(shopId, event = null) {
            if (event) event.stopPropagation();
            
            currentShopId = shopId;
            
            fetch('admin_mall.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=get_shop_details&shop_id=${shopId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const shop = data.data;
                    const modal = document.getElementById('shopModal');
                    const details = document.getElementById('shopDetails');
                    
                    let html = `
                        <div class="details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Shop Number</div>
                                <div class="detail-value">${shop.shop_number}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Shop Name</div>
                                <div class="detail-value">${shop.shop_name || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span style="
                                        padding: 3px 8px;
                                        border-radius: 4px;
                                        font-size: 12px;
                                        font-weight: 500;
                                        ${shop.status === 'available' ? 'background: #d4edda; color: #155724;' : 
                                          shop.status === 'occupied' ? 'background: #f8d7da; color: #721c24;' : 
                                          shop.status === 'maintenance' ? 'background: #fff3cd; color: #856404;' : 
                                          'background: #cce5ff; color: #004085;'}
                                    ">
                                        ${shop.status}
                                    </span>
                                </div>
                            </div>
                    `;
                    
                    if (shop.tenant_name) {
                        html += `
                            <div class="detail-item">
                                <div class="detail-label">Tenant</div>
                                <div class="detail-value">${shop.tenant_name}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Member Code</div>
                                <div class="detail-value">${shop.member_code || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Business Name</div>
                                <div class="detail-value">${shop.business_name || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Business Type</div>
                                <div class="detail-value">${shop.business_type || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Monthly Rent</div>
                                <div class="detail-value">$${parseFloat(shop.rent_amount || 0).toFixed(2)}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Balance</div>
                                <div class="detail-value" style="${parseFloat(shop.balance || 0) <= 0 ? 'color: #28a745;' : 'color: #dc3545;'}">
                                    $${Math.abs(parseFloat(shop.balance || 0)).toFixed(2)} ${parseFloat(shop.balance || 0) > 0 ? '(Overdue)' : ''}
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Next Due Date</div>
                                <div class="detail-value">${shop.next_due_date || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Tenant Since</div>
                                <div class="detail-value">${shop.member_since ? new Date(shop.member_since).toLocaleDateString() : 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value">${shop.email || 'N/A'}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value">${shop.phone || 'N/A'}</div>
                            </div>
                        `;
                    }
                    
                    html += `
                        </div>
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            ${shop.status === 'available' ? `
                            <button class="btn btn-primary" onclick="showAssignModal(${shopId})">
                                <i class="fas fa-user-plus"></i> Assign Tenant
                            </button>
                            ` : ''}
                            ${shop.status === 'occupied' ? `
                            <button class="btn btn-warning" onclick="showPaymentModal(${shopId})">
                                <i class="fas fa-money-bill"></i> Record Payment
                            </button>
                            <button class="btn btn-danger" onclick="confirmRelease(${shopId})">
                                <i class="fas fa-times"></i> Release Shop
                            </button>
                            ` : ''}
                        </div>
                    `;
                    
                    details.innerHTML = html;
                    modal.style.display = 'flex';
                }
            });
        }
        
        // Release shop
        function releaseShop(shopId, event = null) {
            if (event) event.stopPropagation();
            
            if (confirm('Are you sure you want to release this shop?')) {
                fetch('admin_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=release_shop&shop_id=${shopId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        loadShops();
                        closeModal();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        
        function confirmRelease(shopId) {
            releaseShop(shopId);
        }
        
        // Show assign modal
        function showAssignModal(shopId) {
            currentShopId = shopId;
            
            // Load available members
            fetch('admin_mall.php', {
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
                    select.innerHTML = '<option value="">Select a tenant...</option>';
                    
                    data.data.forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = `${member.full_name} (${member.member_code}) - ${member.business_name || 'No Business'} - Balance: $${parseFloat(member.balance).toFixed(2)}`;
                        select.appendChild(option);
                    });
                    
                    // Get shop info
                    const shop = shops.find(s => s.id == shopId);
                    if (shop) {
                        document.getElementById('assignShopInfo').innerHTML = `
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-top: 10px;">
                                <div style="font-size: 14px; color: #6c757d;">Assigning Shop:</div>
                                <div style="font-size: 16px; font-weight: 500;">${shop.shop_number} - ${shop.shop_name || ''}</div>
                            </div>
                        `;
                    }
                    
                    // Set default next due date to next month
                    const today = new Date();
                    const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, today.getDate());
                    document.getElementById('nextDueDate').value = nextMonth.toISOString().split('T')[0];
                    
                    document.getElementById('assignModal').style.display = 'flex';
                }
            });
        }
        
        // Assign shop to member
        function assignShop() {
            const memberId = document.getElementById('memberSelect').value;
            const rentAmount = document.getElementById('rentAmount').value;
            const nextDueDate = document.getElementById('nextDueDate').value;
            
            if (!memberId) {
                alert('Please select a tenant');
                return;
            }
            
            if (!rentAmount || parseFloat(rentAmount) <= 0) {
                alert('Please enter a valid rent amount');
                return;
            }
            
            if (!nextDueDate) {
                alert('Please select a due date');
                return;
            }
            
            if (confirm('Assign this shop to the selected tenant?')) {
                fetch('admin_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=assign_shop&shop_id=${currentShopId}&member_id=${memberId}&rent_amount=${rentAmount}&next_due_date=${nextDueDate}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        loadShops();
                        closeAssignModal();
                        closeModal();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        
        // Show payment modal
        function showPaymentModal(shopId, event = null) {
            if (event) event.stopPropagation();
            
            currentShopId = shopId;
            
            const shop = shops.find(s => s.id == shopId);
            if (shop) {
                document.getElementById('paymentShopInfo').innerHTML = `
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                        <div style="font-size: 14px; color: #6c757d;">Recording Payment for:</div>
                        <div style="font-size: 16px; font-weight: 500;">${shop.shop_number} - ${shop.shop_name || ''}</div>
                        <div style="font-size: 14px; margin-top: 5px;">Tenant: ${shop.tenant_name}</div>
                        <div style="font-size: 14px;">Current Balance: $${parseFloat(shop.balance || 0).toFixed(2)}</div>
                    </div>
                `;
            }
            
            document.getElementById('paymentModal').style.display = 'flex';
        }
        
        // Record payment
        function recordPayment() {
            const payerName = document.getElementById('payerName').value;
            const monthPaid = document.getElementById('monthPaid').value;
            const paymentAmount = document.getElementById('paymentAmount').value;
            const paymentMethod = document.getElementById('paymentMethod').value;
            const paymentDescription = document.getElementById('paymentDescription').value;
            
            if (!payerName || !monthPaid || !paymentAmount) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (parseFloat(paymentAmount) <= 0) {
                alert('Please enter a valid payment amount');
                return;
            }
            
            if (confirm(`Record payment of $${paymentAmount} for ${monthPaid}?`)) {
                fetch('admin_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=record_payment&shop_id=${currentShopId}&payer_name=${encodeURIComponent(payerName)}&month_paid=${encodeURIComponent(monthPaid)}&amount=${paymentAmount}&payment_method=${encodeURIComponent(paymentMethod)}&description=${encodeURIComponent(paymentDescription)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        loadShops();
                        closePaymentModal();
                        closeModal();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        
        // Close modals
        function closeModal() {
            document.getElementById('shopModal').style.display = 'none';
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('memberSelect').innerHTML = '<option value="">Select a tenant...</option>';
            document.getElementById('rentAmount').value = '';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('payerName').value = '';
            document.getElementById('monthPaid').value = '';
            document.getElementById('paymentAmount').value = '';
            document.getElementById('paymentMethod').value = 'Cash';
            document.getElementById('paymentDescription').value = '';
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (modal.id === 'shopModal') closeModal();
                    if (modal.id === 'assignModal') closeAssignModal();
                    if (modal.id === 'paymentModal') closePaymentModal();
                }
            });
        });
        
        // Add keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeAssignModal();
                closePaymentModal();
            }
        });
    </script>
</body>
</html>