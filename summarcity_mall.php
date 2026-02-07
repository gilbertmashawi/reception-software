<?php
// summarcity_mall.php - Summarcity Mall Management
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'payments';
$db = getDB();
$user = getCurrentUser();

// Generate member code function
function generateSummerCityMemberCode($prefix = 'SM') {
    $db = getDB();
    $year = date('y');
    $month = date('m');
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM summarcity_members WHERE DATE_FORMAT(created_at, '%y%m') = ?");
    $monthYear = $year . $month;
    $stmt->bind_param("s", $monthYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $sequence = str_pad(($row['count'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    return $prefix . $year . $month . $sequence;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_available_shops':
            $stmt = $db->prepare("SELECT * FROM summarcity_shops WHERE status IN ('available', 'occupied', 'maintenance') ORDER BY shop_number");
            $stmt->execute();
            $result = $stmt->get_result();
            $shops = [];
            while ($row = $result->fetch_assoc()) {
                $shops[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $shops]);
            break;
            
        case 'record_payment':
            $tenantId = intval($_POST['tenant_id']);
            $tenantName = $_POST['tenant_name'];
            $amount = floatval($_POST['amount']);
            $paymentMethod = $_POST['payment_method'];
            $monthPaid = $_POST['month_paid'];
            $shopId = intval($_POST['shop_id']);
            $description = $_POST['description'] ?? '';
            
            $db->begin_transaction();
            
            try {
                // Record payment
                $stmt = $db->prepare("INSERT INTO mall_payments (payment_date, month_paid, payer_name, amount, payment_method, description, shop_id) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sddsi", $monthPaid, $tenantName, $amount, $paymentMethod, $description, $shopId);
                $stmt->execute();
                $paymentId = $db->insert_id;
                
                // Update tenant balance if tenant exists
                if ($tenantId > 0) {
                    $stmt = $db->prepare("UPDATE summarcity_members SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $tenantId);
                    $stmt->execute();
                    
                    // Update next due date if balance is cleared
                    $stmt = $db->prepare("SELECT balance FROM summarcity_members WHERE id = ?");
                    $stmt->bind_param("i", $tenantId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($tenant = $result->fetch_assoc()) {
                        if ($tenant['balance'] <= 0) {
                            $nextDueDate = date('Y-m-d', strtotime('+1 month'));
                            $stmt = $db->prepare("UPDATE summarcity_members SET next_due_date = ? WHERE id = ?");
                            $stmt->bind_param("si", $nextDueDate, $tenantId);
                            $stmt->execute();
                        }
                    }
                }
                
                // Add activity log
                addActivityLog('Summarcity Mall', "Recorded payment from {$tenantName} for {$monthPaid}", "Amount: \${$amount}, Method: {$paymentMethod}, Shop ID: {$shopId}");
                
                // Send notification
                sendNotification('mall_payment', 'New Mall Payment', "Payment of \${$amount} from {$tenantName} for {$monthPaid}");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully', 'payment_id' => $paymentId]);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_tenants':
            $search = $_POST['search'] ?? '';
            $stmt = $db->prepare("SELECT sm.*, ss.shop_name, ss.shop_number 
                                 FROM summarcity_members sm 
                                 LEFT JOIN summarcity_shops ss ON sm.shop_id = ss.id 
                                 WHERE sm.full_name LIKE ? OR sm.member_code LIKE ? OR ss.shop_number LIKE ?
                                 ORDER BY sm.full_name");
            $searchTerm = "%{$search}%";
            $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenants = [];
            while ($row = $result->fetch_assoc()) {
                $tenants[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $tenants]);
            break;
            
        case 'get_tenant_details':
            $tenantId = intval($_POST['tenant_id']);
            $stmt = $db->prepare("SELECT sm.*, ss.shop_name, ss.shop_number 
                                 FROM summarcity_members sm 
                                 LEFT JOIN summarcity_shops ss ON sm.shop_id = ss.id 
                                 WHERE sm.id = ?");
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenant = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $tenant]);
            break;
            
        case 'add_tenant':
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $idNumber = $_POST['id_number'] ?? '';
            $businessName = $_POST['business_name'] ?? '';
            $businessType = $_POST['business_type'] ?? '';
            $shopId = intval($_POST['shop_id']);
            $rentAmount = floatval($_POST['rent_amount']);
            $notes = $_POST['notes'] ?? '';
            $isNew = isset($_POST['is_new']) ? 1 : 0;
            
            $db->begin_transaction();
            
            try {
                // Check if shop is available
                if ($shopId > 0) {
                    $stmt = $db->prepare("SELECT status FROM summarcity_shops WHERE id = ?");
                    $stmt->bind_param("i", $shopId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $shop = $result->fetch_assoc();
                    
                    if ($shop && $shop['status'] !== 'available') {
                        throw new Exception("Selected shop is not available");
                    }
                }
                
                // Generate member code
                $memberCode = generateSummerCityMemberCode('SM');
                
                // Calculate next due date (1 month from now)
                $nextDueDate = date('Y-m-d', strtotime('+1 month'));
                
                // Get shop details if assigned
                $shopNumber = '';
                if ($shopId > 0) {
                    $stmt = $db->prepare("SELECT shop_number, shop_name FROM summarcity_shops WHERE id = ?");
                    $stmt->bind_param("i", $shopId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($shop = $result->fetch_assoc()) {
                        $shopNumber = $shop['shop_number'];
                        
                        // Update shop status
                        $stmt = $db->prepare("UPDATE summarcity_shops SET status = 'occupied', current_tenant_id = LAST_INSERT_ID(), current_tenant_name = ?, rent_amount = ?, next_due_date = ? WHERE id = ?");
                        $businessDisplayName = $businessName ?: $fullName;
                        $stmt->bind_param("sdsi", $businessDisplayName, $rentAmount, $nextDueDate, $shopId);
                        $stmt->execute();
                    }
                }
                
                // Insert tenant
                $stmt = $db->prepare("INSERT INTO summarcity_members (member_code, full_name, email, phone, id_number, business_name, business_type, shop_id, shop_number, rent_amount, balance, next_due_date, is_new, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $balance = $rentAmount; // Initial balance equals rent amount
                $stmt->bind_param("sssssssisidsis", $memberCode, $fullName, $email, $phone, $idNumber, $businessName, $businessType, $shopId, $shopNumber, $rentAmount, $balance, $nextDueDate, $isNew, $notes);
                $stmt->execute();
                $tenantId = $db->insert_id;
                
                // Add activity log
                addActivityLog('Summarcity Tenants', "Added new tenant: {$fullName}", "Business: {$businessName}, Shop: {$shopNumber}, Rent: \${$rentAmount}, Member Code: {$memberCode}");
                
                // Send notification for new tenant
                if ($isNew) {
                    sendNotification('new_tenant', 'New Tenant Added', "New tenant {$fullName} ({$businessName}) has been added to Summarcity Mall. Shop: {$shopNumber}");
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Tenant added successfully', 'tenant_id' => $tenantId, 'member_code' => $memberCode]);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'update_tenant':
            $tenantId = intval($_POST['tenant_id']);
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $idNumber = $_POST['id_number'] ?? '';
            $businessName = $_POST['business_name'] ?? '';
            $businessType = $_POST['business_type'] ?? '';
            $shopId = intval($_POST['shop_id']);
            $rentAmount = floatval($_POST['rent_amount']);
            $balance = floatval($_POST['balance']);
            $nextDueDate = $_POST['next_due_date'];
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            $db->begin_transaction();
            
            try {
                // Get current shop assignment
                $stmt = $db->prepare("SELECT shop_id FROM summarcity_members WHERE id = ?");
                $stmt->bind_param("i", $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                $currentTenant = $result->fetch_assoc();
                $oldShopId = $currentTenant['shop_id'] ?? 0;
                
                // Handle shop change
                if ($oldShopId != $shopId) {
                    // Release old shop
                    if ($oldShopId > 0) {
                        $stmt = $db->prepare("UPDATE summarcity_shops SET status = 'available', current_tenant_id = NULL, current_tenant_name = NULL, rent_amount = NULL, next_due_date = NULL WHERE id = ?");
                        $stmt->bind_param("i", $oldShopId);
                        $stmt->execute();
                    }
                    
                    // Assign new shop
                    if ($shopId > 0) {
                        // Check if new shop is available
                        $stmt = $db->prepare("SELECT status FROM summarcity_shops WHERE id = ?");
                        $stmt->bind_param("i", $shopId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $shop = $result->fetch_assoc();
                        
                        if ($shop && $shop['status'] !== 'available' && $shop['status'] !== 'maintenance') {
                            throw new Exception("Selected shop is not available");
                        }
                        
                        $stmt = $db->prepare("SELECT shop_number FROM summarcity_shops WHERE id = ?");
                        $stmt->bind_param("i", $shopId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $shop = $result->fetch_assoc();
                        $shopNumber = $shop['shop_number'] ?? '';
                        
                        // Update shop status
                        $stmt = $db->prepare("UPDATE summarcity_shops SET status = 'occupied', current_tenant_id = ?, current_tenant_name = ?, rent_amount = ?, next_due_date = ? WHERE id = ?");
                        $businessDisplayName = $businessName ?: $fullName;
                        $stmt->bind_param("isdsi", $tenantId, $businessDisplayName, $rentAmount, $nextDueDate, $shopId);
                        $stmt->execute();
                    } else {
                        $shopNumber = '';
                    }
                } else {
                    // Same shop, just update tenant info in shop
                    if ($shopId > 0) {
                        $stmt = $db->prepare("UPDATE summarcity_shops SET current_tenant_name = ?, rent_amount = ?, next_due_date = ? WHERE id = ?");
                        $businessDisplayName = $businessName ?: $fullName;
                        $stmt->bind_param("sdsi", $businessDisplayName, $rentAmount, $nextDueDate, $shopId);
                        $stmt->execute();
                    }
                    $shopNumber = $_POST['shop_number'] ?? '';
                }
                
                // Update tenant
                $stmt = $db->prepare("UPDATE summarcity_members SET full_name = ?, email = ?, phone = ?, id_number = ?, business_name = ?, business_type = ?, shop_id = ?, shop_number = ?, rent_amount = ?, balance = ?, next_due_date = ?, status = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("ssssssissdsssi", $fullName, $email, $phone, $idNumber, $businessName, $businessType, $shopId, $shopNumber, $rentAmount, $balance, $nextDueDate, $status, $notes, $tenantId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Summarcity Tenants', "Updated tenant: {$fullName}", "Business: {$businessName}, Shop: {$shopNumber}, Rent: \${$rentAmount}, Status: {$status}");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Tenant updated successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete_tenant':
            $tenantId = intval($_POST['tenant_id']);
            
            $db->begin_transaction();
            
            try {
                // Get tenant details
                $stmt = $db->prepare("SELECT full_name, business_name, shop_id FROM summarcity_members WHERE id = ?");
                $stmt->bind_param("i", $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                $tenant = $result->fetch_assoc();
                
                // Release shop if assigned
                if ($tenant && $tenant['shop_id'] > 0) {
                    $stmt = $db->prepare("UPDATE summarcity_shops SET status = 'available', current_tenant_id = NULL, current_tenant_name = NULL, rent_amount = NULL, next_due_date = NULL WHERE id = ?");
                    $stmt->bind_param("i", $tenant['shop_id']);
                    $stmt->execute();
                }
                
                // Delete tenant
                $stmt = $db->prepare("DELETE FROM summarcity_members WHERE id = ?");
                $stmt->bind_param("i", $tenantId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Summarcity Tenants', "Deleted tenant: {$tenant['full_name']}", "Business: {$tenant['business_name']}");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Tenant deleted successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'release_shop':
            $shopId = intval($_POST['shop_id']);
            
            $db->begin_transaction();
            
            try {
                // Get current tenant details
                $stmt = $db->prepare("SELECT id, full_name, business_name FROM summarcity_members WHERE shop_id = ?");
                $stmt->bind_param("i", $shopId);
                $stmt->execute();
                $result = $stmt->get_result();
                $tenant = $result->fetch_assoc();
                
                if ($tenant) {
                    // Clear tenant's shop assignment
                    $stmt = $db->prepare("UPDATE summarcity_members SET shop_id = NULL, shop_number = NULL WHERE id = ?");
                    $stmt->bind_param("i", $tenant['id']);
                    $stmt->execute();
                }
                
                // Update shop status
                $stmt = $db->prepare("UPDATE summarcity_shops SET status = 'available', current_tenant_id = NULL, current_tenant_name = NULL, rent_amount = NULL, next_due_date = NULL WHERE id = ?");
                $stmt->bind_param("i", $shopId);
                $stmt->execute();
                
                // Add activity log
                addActivityLog('Summarcity Shops', "Released shop", "Shop ID: {$shopId}, Previous tenant: " . ($tenant['full_name'] ?? 'None'));
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Shop released successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_payments':
            $month = $_POST['month'] ?? date('Y-m');
            $stmt = $db->prepare("SELECT mp.*, ss.shop_number 
                                 FROM mall_payments mp 
                                 LEFT JOIN summarcity_shops ss ON mp.shop_id = ss.id 
                                 WHERE DATE_FORMAT(mp.payment_date, '%Y-%m') = ?
                                 ORDER BY mp.payment_date DESC");
            $stmt->bind_param("s", $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $payments]);
            break;
            
        case 'add_shop':
            $shopNumber = $_POST['shop_number'];
            $shopName = $_POST['shop_name'] ?? '';
            $rentAmount = $_POST['rent_amount'] ? floatval($_POST['rent_amount']) : NULL;
            
            try {
                $stmt = $db->prepare("INSERT INTO summarcity_shops (shop_number, shop_name, rent_amount) VALUES (?, ?, ?)");
                $stmt->bind_param("ssd", $shopNumber, $shopName, $rentAmount);
                $stmt->execute();
                
                addActivityLog('Summarcity Shops', "Added new shop: {$shopNumber}", "Shop Name: {$shopName}");
                
                echo json_encode(['success' => true, 'message' => 'Shop added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'update_shop':
            $shopId = intval($_POST['shop_id']);
            $shopNumber = $_POST['shop_number'];
            $shopName = $_POST['shop_name'] ?? '';
            $status = $_POST['status'];
            $rentAmount = $_POST['rent_amount'] ? floatval($_POST['rent_amount']) : NULL;
            
            try {
                $stmt = $db->prepare("UPDATE summarcity_shops SET shop_number = ?, shop_name = ?, status = ?, rent_amount = ? WHERE id = ?");
                $stmt->bind_param("sssdi", $shopNumber, $shopName, $status, $rentAmount, $shopId);
                $stmt->execute();
                
                addActivityLog('Summarcity Shops', "Updated shop: {$shopNumber}", "Status: {$status}");
                
                echo json_encode(['success' => true, 'message' => 'Shop updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
    }
    exit;
}

// Get data for display
$shops = [];
$tenants = [];
$payments = [];

if ($action === 'tenants') {
    $stmt = $db->prepare("SELECT sm.*, ss.shop_name 
                         FROM summarcity_members sm 
                         LEFT JOIN summarcity_shops ss ON sm.shop_id = ss.id 
                         ORDER BY sm.full_name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tenants[] = $row;
    }
} elseif ($action === 'payments') {
    $stmt = $db->prepare("SELECT mp.*, ss.shop_number 
                         FROM mall_payments mp 
                         LEFT JOIN summarcity_shops ss ON mp.shop_id = ss.id 
                         ORDER BY mp.payment_date DESC 
                         LIMIT 50");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

// Get available shops
$stmt = $db->prepare("SELECT * FROM summarcity_shops ORDER BY shop_number");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $shops[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Summarcity Mall</title>
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
        
        /* Buttons */
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
        
        .btn-accent {
            background: var(--accent);
            color: white;
        }
        
        .btn-accent:hover {
            background: #d62c1a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Forms */
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
        
        /* Collapsible Form Toggle from linkspot_spaces.php */
        .form-toggle {
            background: var(--primary);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .form-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }
        
        .form-toggle.expanded {
            background: var(--danger);
        }
        
        .collapsible-form {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.5s ease-out;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 0 25px;
        }
        
        .collapsible-form.expanded {
            max-height: 2000px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
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
        
        /* Shop Grid */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .shop-card {
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .shop-card.available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .shop-card.occupied {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .shop-card.maintenance {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .shop-card.reserved {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .shop-card.selected {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .shop-card .shop-number {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .shop-card .shop-name {
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .shop-card .shop-status {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .shop-card .shop-tenant {
            font-size: 11px;
            margin-top: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-title {
            font-size: 20px;
            margin: 0;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
        
        /* Additional Components */
        .payment-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .payment-summary h4 {
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        .payment-summary .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
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
            
            .form-row {
                flex-direction: column;
            }
            
            .shop-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .tabs {
                flex-direction: column;
                gap: 5px;
            }
            
            .tab {
                padding: 8px 15px;
            }
            
            .table-responsive {
                margin: 0 -20px;
                width: calc(100% + 40px);
                border-radius: 0;
                border-left: none;
                border-right: none;
            }
        }
        
        .text-muted {
            color: #6c757d !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-page">
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-shopping-cart"></i> Summarcity Mall</h1>
                    <p>Manage tenants, shops, and payments</p>
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
            
            <div class="tabs">
                <div class="tab <?php echo $action === 'payments' ? 'active' : ''; ?>" onclick="switchTab('payments')">
                    <i class="fas fa-money-bill"></i> Record Payments
                </div>
                <div class="tab <?php echo $action === 'tenants' ? 'active' : ''; ?>" onclick="switchTab('tenants')">
                    <i class="fas fa-users"></i> Tenants
                </div>
                <div class="tab <?php echo $action === 'shops' ? 'active' : ''; ?>" onclick="switchTab('shops')">
                    <i class="fas fa-store"></i> Shop Management
                </div>
            </div>
            
            <!-- Record Payments Tab -->
            <div id="tab-payments" class="tab-content" style="<?php echo $action === 'payments' ? 'display: block;' : 'display: none;'; ?>">
                    <div class="form-toggle" onclick="togglePaymentForm()" id="paymentToggle">
        <i class="fas fa-plus"></i>
    </div>
   
    <div class="collapsible-form" id="paymentForm">
        <div class="alert" id="paymentAlert" style="display: none;"></div>
       
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label class="form-label">Select Tenant</label>
                    <select id="tenantSelect" class="form-control" onchange="loadTenantDetails()">
                        <option value="">-- Select Tenant --</option>
                        <?php 
                        // Get all tenants for the dropdown regardless of current action
                        $tenantStmt = $db->prepare("SELECT sm.*, ss.shop_number
                                                   FROM summarcity_members sm
                                                   LEFT JOIN summarcity_shops ss ON sm.shop_id = ss.id
                                                   ORDER BY sm.full_name");
                        $tenantStmt->execute();
                        $tenantResult = $tenantStmt->get_result();
                        while ($tenant = $tenantResult->fetch_assoc()): ?>
                        <option value="<?php echo $tenant['id']; ?>">
                            <?php echo $tenant['full_name'] . ($tenant['business_name'] ? " ({$tenant['business_name']})" : ''); ?>
                            <?php if ($tenant['shop_number']): ?>
                            - Shop <?php echo $tenant['shop_number']; ?>
                            <?php endif; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
        </div>
                    
                    <div id="tenantInfo" style="display: none; margin-bottom: 20px;">
                        <div class="payment-summary">
                            <h4>Tenant Information</h4>
                            <div class="row">
                                <div>Member Code: <span id="infoMemberCode"></span></div>
                                <div>Shop: <span id="infoShop"></span></div>
                            </div>
                            <div class="row">
                                <div>Rent Amount: <span id="infoRent"></span></div>
                                <div>Current Balance: <span id="infoBalance"></span></div>
                            </div>
                            <div class="row">
                                <div>Next Due Date: <span id="infoDueDate"></span></div>
                                <div>Status: <span id="infoStatus"></span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Amount ($) *</label>
                                <input type="number" id="paymentAmount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Payment Method *</label>
                                <select id="paymentMethod" class="form-control" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Ecocash">Ecocash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Swipe">Swipe</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Month Paid For *</label>
                                <select id="monthPaid" class="form-control" required>
                                    <?php
                                    $months = [
                                        'January', 'February', 'March', 'April', 'May', 'June',
                                        'July', 'August', 'September', 'October', 'November', 'December'
                                    ];
                                    $currentMonth = date('F');
                                    foreach ($months as $month): ?>
                                    <option value="<?php echo $month; ?>" <?php echo $month === $currentMonth ? 'selected' : ''; ?>>
                                        <?php echo $month . ' ' . date('Y'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Shop Number *</label>
                                <select id="shopSelect" class="form-control" required>
                                    <option value="">-- Select Shop --</option>
                                    <?php foreach ($shops as $shop): ?>
                                    <option value="<?php echo $shop['id']; ?>">
                                        <?php echo $shop['shop_number']; ?> - <?php echo $shop['shop_name'] ?: 'Unnamed'; ?>
                                        (<?php echo ucfirst($shop['status']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description (Optional)</label>
                        <textarea id="paymentDescription" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <button class="btn btn-accent" onclick="recordPayment()">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                </div>
                
                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Payments</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadRecentPayments()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Shop</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Month</th>
                                </tr>
                            </thead>
                            <tbody id="recentPaymentsBody">
                                <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #6c757d;">
                                        No payments recorded yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo $payment['payer_name']; ?></td>
                                    <td><?php echo $payment['shop_number'] ?? 'N/A'; ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo $payment['payment_method']; ?></td>
                                    <td><?php echo $payment['month_paid']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Tenants Tab -->
            <div id="tab-tenants" class="tab-content" style="<?php echo $action === 'tenants' ? 'display: block;' : 'display: none;'; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div class="form-toggle" onclick="toggleTenantForm()" id="tenantToggle">
                        <i class="fas fa-plus"></i>
                    </div>
                    <button class="btn btn-accent" onclick="toggleTenantForm()">
                        <i class="fas fa-user-plus"></i> Add New Tenant
                    </button>
                </div>
                
                <div class="collapsible-form" id="tenantForm">
                    <div class="alert" id="tenantAlert" style="display: none;"></div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" id="newTenantFullName" class="form-control" placeholder="Enter full name" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" id="newTenantEmail" class="form-control" placeholder="Enter email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" id="newTenantPhone" class="form-control" placeholder="Enter phone">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">ID Number</label>
                                <input type="text" id="newTenantIdNumber" class="form-control" placeholder="Enter ID number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Business Name</label>
                                <input type="text" id="newTenantBusinessName" class="form-control" placeholder="Enter business name">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Business Type</label>
                                <input type="text" id="newTenantBusinessType" class="form-control" placeholder="Enter business type">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Shop</label>
                                <select id="newTenantShopId" class="form-control">
                                    <option value="">-- Select Shop --</option>
                                    <?php foreach ($shops as $shop): ?>
                                    <?php if ($shop['status'] === 'available' || $shop['status'] === 'maintenance'): ?>
                                    <option value="<?php echo $shop['id']; ?>">
                                        <?php echo $shop['shop_number']; ?> - <?php echo $shop['shop_name'] ?: 'Unnamed'; ?>
                                        (<?php echo ucfirst($shop['status']); ?>)
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Monthly Rent ($) *</label>
                                <input type="number" id="newTenantRentAmount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea id="newTenantNotes" class="form-control" rows="3" placeholder="Enter notes"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="newTenantIsNew"> Mark as New Tenant (Send Notification)
                        </label>
                    </div>
                    
                    <button class="btn btn-success" onclick="addTenant()">
                        <i class="fas fa-user-plus"></i> Add Tenant
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Tenants List</h3>
                        <button class="btn btn-primary btn-sm" onclick="searchTenants()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="tenantSearch" placeholder="Search tenants by name, member code, or shop number..." onkeyup="searchTenants()">
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Member Code</th>
                                    <th>Name</th>
                                    <th>Business</th>
                                    <th>Shop</th>
                                    <th>Rent</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tenantsTableBody">
                                <?php foreach ($tenants as $tenant): ?>
                                <tr>
                                    <td><?php echo $tenant['member_code']; ?></td>
                                    <td>
                                        <div><?php echo $tenant['full_name']; ?></div>
                                        <small class="text-muted"><?php echo $tenant['phone']; ?></small>
                                    </td>
                                    <td><?php echo $tenant['business_name'] ?: '-'; ?></td>
                                    <td>
                                        <?php if ($tenant['shop_number']): ?>
                                        <span class="badge badge-info"><?php echo $tenant['shop_number']; ?></span>
                                        <?php else: ?>
                                        <span class="badge badge-warning">No Shop</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($tenant['rent_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($tenant['balance'] > 0): ?>
                                        <span class="badge badge-danger">$<?php echo number_format($tenant['balance'], 2); ?></span>
                                        <?php else: ?>
                                        <span class="badge badge-success">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($tenant['next_due_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'badge-success';
                                        if ($tenant['status'] === 'inactive') $statusClass = 'badge-warning';
                                        if ($tenant['status'] === 'suspended') $statusClass = 'badge-danger';
                                        if ($tenant['status'] === 'pending') $statusClass = 'badge-info';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($tenant['status']); ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editTenant(<?php echo $tenant['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteTenant(<?php echo $tenant['id']; ?>, '<?php echo $tenant['full_name']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Shop Management Tab -->
            <div id="tab-shops" class="tab-content" style="<?php echo $action === 'shops' ? 'display: block;' : 'display: none;'; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div class="form-toggle" onclick="toggleShopForm()" id="shopToggle">
                        <i class="fas fa-plus"></i>
                    </div>
                    <button class="btn btn-accent" onclick="toggleShopForm()">
                        <i class="fas fa-plus"></i> Add Shop
                    </button>
                </div>
                
                <div class="collapsible-form" id="shopForm">
                    <div class="alert" id="shopAlert" style="display: none;"></div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Shop Number *</label>
                                <input type="text" id="newShopNumber" class="form-control" placeholder="Enter shop number" required>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Shop Name</label>
                                <input type="text" id="newShopName" class="form-control" placeholder="Enter shop name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select id="newShopStatus" class="form-control">
                                    <option value="available">Available</option>
                                    <option value="occupied">Occupied</option>
                                    <option value="reserved">Reserved</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Rent Amount ($)</label>
                                <input type="number" id="newShopRentAmount" class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-success" onclick="addShop()">
                        <i class="fas fa-plus"></i> Add Shop
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Shops</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadShops()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="shop-grid" id="shopsGrid">
                        <?php foreach ($shops as $shop): ?>
                        <div class="shop-card <?php echo $shop['status']; ?>" onclick="selectShop(<?php echo $shop['id']; ?>)">
                            <div class="shop-number"><?php echo $shop['shop_number']; ?></div>
                            <div class="shop-name"><?php echo $shop['shop_name'] ?: '-'; ?></div>
                            <div class="shop-status"><?php echo ucfirst($shop['status']); ?></div>
                            <?php if ($shop['current_tenant_name']): ?>
                            <div class="shop-tenant"><?php echo $shop['current_tenant_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="shopActions" style="display: none; margin-top: 20px;">
                        <button class="btn btn-warning" onclick="releaseShop()">
                            <i class="fas fa-door-open"></i> Release Shop
                        </button>
                        <button class="btn btn-primary" onclick="showEditShopModal()">
                            <i class="fas fa-edit"></i> Edit Shop
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Tenant Modal -->
    <div class="modal" id="editTenantModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Tenant</h3>
                <button class="modal-close" onclick="closeModal('editTenantModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="editTenantModalBody">
                <!-- Edit form will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Edit Shop Modal -->
    <div class="modal" id="editShopModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Shop</h3>
                <button class="modal-close" onclick="closeModal('editShopModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="editShopModalBody">
                <!-- Edit form will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        let selectedShopId = null;
        let selectedShopElement = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set today as default for due date
            const today = new Date();
            const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, today.getDate());
            
            // Initialize payment form if on payments tab
            if ('<?php echo $action; ?>' === 'payments') {
                // Initialize form
            }
        });
        
        function switchTab(tab) {
            window.location.href = 'summarcity_mall.php?action=' + tab;
        }
        
        // Toggle payment form
        function togglePaymentForm() {
            const toggle = document.getElementById('paymentToggle');
            const form = document.getElementById('paymentForm');
            
            if (form.classList.contains('expanded')) {
                form.classList.remove('expanded');
                toggle.innerHTML = '<i class="fas fa-plus"></i>';
                toggle.classList.remove('expanded');
            } else {
                form.classList.add('expanded');
                toggle.innerHTML = '<i class="fas fa-minus"></i>';
                toggle.classList.add('expanded');
            }
        }
        
        // Toggle tenant form
        function toggleTenantForm() {
            const toggle = document.getElementById('tenantToggle');
            const form = document.getElementById('tenantForm');
            
            if (form.classList.contains('expanded')) {
                form.classList.remove('expanded');
                toggle.innerHTML = '<i class="fas fa-plus"></i>';
                toggle.classList.remove('expanded');
            } else {
                form.classList.add('expanded');
                toggle.innerHTML = '<i class="fas fa-minus"></i>';
                toggle.classList.add('expanded');
                
                // Set default due date
                const today = new Date();
                const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, today.getDate());
                document.getElementById('newTenantRentAmount').value = '';
                document.getElementById('newTenantNotes').value = '';
                document.getElementById('newTenantIsNew').checked = false;
            }
        }
        
        // Toggle shop form
        function toggleShopForm() {
            const toggle = document.getElementById('shopToggle');
            const form = document.getElementById('shopForm');
            
            if (form.classList.contains('expanded')) {
                form.classList.remove('expanded');
                toggle.innerHTML = '<i class="fas fa-plus"></i>';
                toggle.classList.remove('expanded');
            } else {
                form.classList.add('expanded');
                toggle.innerHTML = '<i class="fas fa-minus"></i>';
                toggle.classList.add('expanded');
                
                // Clear form
                document.getElementById('newShopNumber').value = '';
                document.getElementById('newShopName').value = '';
                document.getElementById('newShopStatus').value = 'available';
                document.getElementById('newShopRentAmount').value = '';
            }
        }
        
        // Show/hide alerts
        function showAlert(elementId, message, type = 'danger') {
            const alertDiv = document.getElementById(elementId);
            alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            alertDiv.style.display = 'block';
        }
        
        function hideAlert(elementId) {
            const alertDiv = document.getElementById(elementId);
            alertDiv.innerHTML = '';
            alertDiv.style.display = 'none';
        }
        
        // Payment Functions
        async function loadTenantDetails() {
            const tenantSelect = document.getElementById('tenantSelect');
            const tenantId = tenantSelect.value;
            const tenantInfo = document.getElementById('tenantInfo');
            
            if (!tenantId) {
                tenantInfo.style.display = 'none';
                document.getElementById('customerName').value = '';
                return;
            }
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=get_tenant_details&tenant_id=${tenantId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const tenant = data.data;
                    document.getElementById('infoMemberCode').textContent = tenant.member_code;
                    document.getElementById('infoShop').textContent = tenant.shop_number || 'No Shop';
                    document.getElementById('infoRent').textContent = `$${parseFloat(tenant.rent_amount).toFixed(2)}`;
                    document.getElementById('infoBalance').textContent = `$${parseFloat(tenant.balance).toFixed(2)}`;
                    document.getElementById('infoDueDate').textContent = tenant.next_due_date;
                    
                    let statusClass = 'badge-success';
                    if (tenant.status === 'inactive') statusClass = 'badge-warning';
                    if (tenant.status === 'suspended') statusClass = 'badge-danger';
                    if (tenant.status === 'pending') statusClass = 'badge-info';
                    
                    document.getElementById('infoStatus').innerHTML = 
                        `<span class="badge ${statusClass}">${tenant.status}</span>`;
                    
                    // Auto-fill payment amount with rent
                    document.getElementById('paymentAmount').value = parseFloat(tenant.rent_amount).toFixed(2);
                    
                    // Auto-select shop if tenant has one
                    if (tenant.shop_id) {
                        const shopSelect = document.getElementById('shopSelect');
                        for (let i = 0; i < shopSelect.options.length; i++) {
                            if (parseInt(shopSelect.options[i].value) === tenant.shop_id) {
                                shopSelect.value = tenant.shop_id;
                                break;
                            }
                        }
                    }
                    
                    tenantInfo.style.display = 'block';
                    document.getElementById('customerName').value = '';
                }
            } catch (error) {
                console.error('Error loading tenant details:', error);
            }
        }
        
        async function recordPayment() {
            const tenantSelect = document.getElementById('tenantSelect');
            const customerName = document.getElementById('customerName').value;
            const tenantId = tenantSelect.value;
            
            if (!tenantId && !customerName) {
                showAlert('paymentAlert', 'Please select a tenant or enter a customer name');
                return;
            }
            
            const amount = document.getElementById('paymentAmount').value;
            const paymentMethod = document.getElementById('paymentMethod').value;
            const monthPaid = document.getElementById('monthPaid').value;
            const shopId = document.getElementById('shopSelect').value;
            const description = document.getElementById('paymentDescription').value;
            
            if (!amount || parseFloat(amount) <= 0) {
                showAlert('paymentAlert', 'Please enter a valid payment amount');
                return;
            }
            
            if (!shopId) {
                showAlert('paymentAlert', 'Please select a shop');
                return;
            }
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=record_payment&tenant_id=${tenantId}&tenant_name=${encodeURIComponent(tenantId ? tenantSelect.options[tenantSelect.selectedIndex].text.split(' - ')[0] : customerName)}&amount=${amount}&payment_method=${encodeURIComponent(paymentMethod)}&month_paid=${encodeURIComponent(monthPaid)}&shop_id=${shopId}&description=${encodeURIComponent(description)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('paymentAlert', 'Payment recorded successfully!', 'success');
                    // Reset form
                    document.getElementById('tenantSelect').value = '';
                    document.getElementById('customerName').value = '';
                    document.getElementById('paymentAmount').value = '';
                    document.getElementById('paymentDescription').value = '';
                    document.getElementById('shopSelect').value = '';
                    document.getElementById('tenantInfo').style.display = 'none';
                    
                    setTimeout(() => {
                        hideAlert('paymentAlert');
                    }, 3000);
                    
                    // Reload recent payments
                    loadRecentPayments();
                } else {
                    showAlert('paymentAlert', 'Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error recording payment:', error);
                showAlert('paymentAlert', 'An error occurred while recording the payment');
            }
        }
        
        function loadRecentPayments() {
            // Reload the page to show updated payments
            window.location.reload();
        }
        
        // Tenant Functions
        async function addTenant() {
            const fullName = document.getElementById('newTenantFullName').value;
            const email = document.getElementById('newTenantEmail').value;
            const phone = document.getElementById('newTenantPhone').value;
            const idNumber = document.getElementById('newTenantIdNumber').value;
            const businessName = document.getElementById('newTenantBusinessName').value;
            const businessType = document.getElementById('newTenantBusinessType').value;
            const shopId = document.getElementById('newTenantShopId').value;
            const rentAmount = document.getElementById('newTenantRentAmount').value;
            const notes = document.getElementById('newTenantNotes').value;
            const isNew = document.getElementById('newTenantIsNew').checked;
            
            if (!fullName) {
                showAlert('tenantAlert', 'Full Name is required');
                return;
            }
            
            if (!rentAmount || parseFloat(rentAmount) <= 0) {
                showAlert('tenantAlert', 'Valid Rent Amount is required');
                return;
            }
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=add_tenant&full_name=${encodeURIComponent(fullName)}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&id_number=${encodeURIComponent(idNumber)}&business_name=${encodeURIComponent(businessName)}&business_type=${encodeURIComponent(businessType)}&shop_id=${shopId}&rent_amount=${rentAmount}&notes=${encodeURIComponent(notes)}&is_new=${isNew ? 1 : 0}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('tenantAlert', `Tenant added successfully! Member Code: ${data.member_code}`, 'success');
                    // Reset form
                    document.getElementById('newTenantFullName').value = '';
                    document.getElementById('newTenantEmail').value = '';
                    document.getElementById('newTenantPhone').value = '';
                    document.getElementById('newTenantIdNumber').value = '';
                    document.getElementById('newTenantBusinessName').value = '';
                    document.getElementById('newTenantBusinessType').value = '';
                    document.getElementById('newTenantShopId').value = '';
                    document.getElementById('newTenantRentAmount').value = '';
                    document.getElementById('newTenantNotes').value = '';
                    document.getElementById('newTenantIsNew').checked = false;
                    
                    setTimeout(() => {
                        hideAlert('tenantAlert');
                    }, 5000);
                    
                    // Refresh tenant list
                    searchTenants();
                } else {
                    showAlert('tenantAlert', 'Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error adding tenant:', error);
                showAlert('tenantAlert', 'An error occurred while adding the tenant');
            }
        }
        
        async function editTenant(tenantId) {
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=get_tenant_details&tenant_id=${tenantId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const tenant = data.data;
                    
                    const modalBody = document.getElementById('editTenantModalBody');
                    modalBody.innerHTML = `
                        <div class="alert" id="editTenantAlert" style="display: none;"></div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" id="editTenantFullName" class="form-control" value="${tenant.full_name}" required>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" id="editTenantEmail" class="form-control" value="${tenant.email || ''}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" id="editTenantPhone" class="form-control" value="${tenant.phone || ''}">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" id="editTenantIdNumber" class="form-control" value="${tenant.id_number || ''}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Business Name</label>
                                    <input type="text" id="editTenantBusinessName" class="form-control" value="${tenant.business_name || ''}">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Business Type</label>
                                    <input type="text" id="editTenantBusinessType" class="form-control" value="${tenant.business_type || ''}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Shop</label>
                                    <select id="editTenantShopId" class="form-control">
                                        <option value="">-- Select Shop --</option>
                                        <?php foreach ($shops as $shop): ?>
                                        <option value="<?php echo $shop['id']; ?>" ${tenant.shop_id == <?php echo $shop['id']; ?> ? 'selected' : ''}>
                                            <?php echo $shop['shop_number']; ?> - <?php echo $shop['shop_name'] ?: 'Unnamed'; ?>
                                            (<?php echo ucfirst($shop['status']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Monthly Rent ($) *</label>
                                    <input type="number" id="editTenantRentAmount" class="form-control" value="${tenant.rent_amount}" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Balance ($)</label>
                                    <input type="number" id="editTenantBalance" class="form-control" value="${tenant.balance}" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Next Due Date</label>
                                    <input type="date" id="editTenantDueDate" class="form-control" value="${tenant.next_due_date}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select id="editTenantStatus" class="form-control">
                                        <option value="active" ${tenant.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${tenant.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        <option value="suspended" ${tenant.status === 'suspended' ? 'selected' : ''}>Suspended</option>
                                        <option value="pending" ${tenant.status === 'pending' ? 'selected' : ''}>Pending</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" id="editTenantIsNew" ${tenant.is_new == 1 ? 'checked' : ''}> Is New Tenant
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea id="editTenantNotes" class="form-control" rows="3">${tenant.notes || ''}</textarea>
                        </div>
                        
                        <input type="hidden" id="editTenantId" value="${tenant.id}">
                        
                        <div class="form-group">
                            <button class="btn btn-accent" onclick="updateTenant()">
                                <i class="fas fa-save"></i> Update Tenant
                            </button>
                            <button class="btn btn-danger" onclick="closeModal('editTenantModal')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('editTenantModal').style.display = 'flex';
                }
            } catch (error) {
                console.error('Error loading tenant:', error);
            }
        }
        
        async function updateTenant() {
            const tenantId = document.getElementById('editTenantId').value;
            
            const formData = {
                full_name: document.getElementById('editTenantFullName').value,
                email: document.getElementById('editTenantEmail').value,
                phone: document.getElementById('editTenantPhone').value,
                id_number: document.getElementById('editTenantIdNumber').value,
                business_name: document.getElementById('editTenantBusinessName').value,
                business_type: document.getElementById('editTenantBusinessType').value,
                shop_id: document.getElementById('editTenantShopId').value,
                rent_amount: document.getElementById('editTenantRentAmount').value,
                balance: document.getElementById('editTenantBalance').value || '0',
                next_due_date: document.getElementById('editTenantDueDate').value,
                status: document.getElementById('editTenantStatus').value,
                is_new: document.getElementById('editTenantIsNew').checked ? '1' : '0',
                notes: document.getElementById('editTenantNotes').value
            };
            
            if (!formData.full_name) {
                showAlert('editTenantAlert', 'Full Name is required');
                return;
            }
            
            if (!formData.rent_amount || parseFloat(formData.rent_amount) <= 0) {
                showAlert('editTenantAlert', 'Valid Rent Amount is required');
                return;
            }
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=update_tenant&tenant_id=${tenantId}&${Object.keys(formData).map(key => `${key}=${encodeURIComponent(formData[key])}`).join('&')}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Tenant updated successfully!');
                    closeModal('editTenantModal');
                    window.location.reload();
                } else {
                    showAlert('editTenantAlert', 'Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error updating tenant:', error);
                showAlert('editTenantAlert', 'An error occurred while updating the tenant');
            }
        }
        
        function deleteTenant(tenantId, tenantName) {
            if (confirm(`Are you sure you want to delete tenant "${tenantName}"? This will also release their shop if assigned.`)) {
                fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=delete_tenant&tenant_id=${tenantId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Tenant deleted successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error deleting tenant:', error);
                    alert('An error occurred while deleting the tenant');
                });
            }
        }
        
        async function searchTenants() {
            const searchTerm = document.getElementById('tenantSearch').value;
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=get_tenants&search=${encodeURIComponent(searchTerm)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const tbody = document.getElementById('tenantsTableBody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(tenant => {
                        const statusClass = tenant.status === 'active' ? 'badge-success' :
                                          tenant.status === 'inactive' ? 'badge-warning' :
                                          tenant.status === 'suspended' ? 'badge-danger' : 'badge-info';
                        
                        const row = `
                            <tr>
                                <td>${tenant.member_code}</td>
                                <td>
                                    <div>${tenant.full_name}</div>
                                    <small class="text-muted">${tenant.phone || ''}</small>
                                </td>
                                <td>${tenant.business_name || '-'}</td>
                                <td>
                                    ${tenant.shop_number ? 
                                        `<span class="badge badge-info">${tenant.shop_number}</span>` : 
                                        `<span class="badge badge-warning">No Shop</span>`}
                                </td>
                                <td>$${parseFloat(tenant.rent_amount).toFixed(2)}</td>
                                <td>
                                    ${tenant.balance > 0 ? 
                                        `<span class="badge badge-danger">$${parseFloat(tenant.balance).toFixed(2)}</span>` : 
                                        `<span class="badge badge-success">Paid</span>`}
                                </td>
                                <td>${new Date(tenant.next_due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                <td>
                                    <span class="badge ${statusClass}">${tenant.status}</span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editTenant(${tenant.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteTenant(${tenant.id}, '${tenant.full_name.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            } catch (error) {
                console.error('Error searching tenants:', error);
            }
        }
        
        // Shop Functions
        function selectShop(shopId) {
            if (selectedShopElement) {
                selectedShopElement.classList.remove('selected');
            }
            
            selectedShopId = shopId;
            selectedShopElement = event.currentTarget;
            selectedShopElement.classList.add('selected');
            
            document.getElementById('shopActions').style.display = 'block';
        }
        
        async function addShop() {
            const shopNumber = document.getElementById('newShopNumber').value;
            const shopName = document.getElementById('newShopName').value;
            const status = document.getElementById('newShopStatus').value;
            const rentAmount = document.getElementById('newShopRentAmount').value || null;
            
            if (!shopNumber) {
                showAlert('shopAlert', 'Shop Number is required');
                return;
            }
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=add_shop&shop_number=${encodeURIComponent(shopNumber)}&shop_name=${encodeURIComponent(shopName)}&status=${status}&rent_amount=${rentAmount}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('shopAlert', 'Shop added successfully!', 'success');
                    // Reset form
                    document.getElementById('newShopNumber').value = '';
                    document.getElementById('newShopName').value = '';
                    document.getElementById('newShopStatus').value = 'available';
                    document.getElementById('newShopRentAmount').value = '';
                    
                    setTimeout(() => {
                        hideAlert('shopAlert');
                    }, 3000);
                    
                    // Refresh shop grid
                    loadShops();
                } else {
                    showAlert('shopAlert', 'Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error adding shop:', error);
                showAlert('shopAlert', 'An error occurred while adding the shop');
            }
        }
        
        function loadShops() {
            // Reload the page to show updated shops
            window.location.reload();
        }
        
        function showEditShopModal() {
            if (!selectedShopId) {
                alert('Please select a shop first');
                return;
            }
            
            const shopElement = selectedShopElement;
            const shopNumber = shopElement.querySelector('.shop-number').textContent;
            const shopName = shopElement.querySelector('.shop-name').textContent;
            const shopStatus = shopElement.className.includes('available') ? 'available' :
                             shopElement.className.includes('occupied') ? 'occupied' :
                             shopElement.className.includes('reserved') ? 'reserved' : 'maintenance';
            
            const modalBody = document.getElementById('editShopModalBody');
            modalBody.innerHTML = `
                <div class="alert" id="editShopAlert" style="display: none;"></div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label class="form-label">Shop Number *</label>
                            <input type="text" id="editShopNumber" class="form-control" value="${shopNumber}" required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label class="form-label">Shop Name</label>
                            <input type="text" id="editShopName" class="form-control" value="${shopName === '-' ? '' : shopName}">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select id="editShopStatus" class="form-control">
                                <option value="available" ${shopStatus === 'available' ? 'selected' : ''}>Available</option>
                                <option value="occupied" ${shopStatus === 'occupied' ? 'selected' : ''}>Occupied</option>
                                <option value="reserved" ${shopStatus === 'reserved' ? 'selected' : ''}>Reserved</option>
                                <option value="maintenance" ${shopStatus === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label class="form-label">Rent Amount ($)</label>
                            <input type="number" id="editShopRentAmount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="editShopId" value="${selectedShopId}">
                
                <div class="form-group">
                    <button class="btn btn-accent" onclick="updateShop()">
                        <i class="fas fa-save"></i> Update Shop
                    </button>
                    <button class="btn btn-danger" onclick="closeModal('editShopModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            `;
            
            document.getElementById('editShopModal').style.display = 'flex';
        }
        
        async function updateShop() {
            const shopId = document.getElementById('editShopId').value;
            
            const formData = {
                shop_number: document.getElementById('editShopNumber').value,
                shop_name: document.getElementById('editShopName').value,
                status: document.getElementById('editShopStatus').value,
                rent_amount: document.getElementById('editShopRentAmount').value || null
            };
            
            if (!formData.shop_number) {
                showAlert('editShopAlert', 'Shop Number is required');
                return;
            }
            
            try {
                const response = await fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=update_shop&shop_id=${shopId}&${Object.keys(formData).map(key => `${key}=${encodeURIComponent(formData[key])}`).join('&')}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Shop updated successfully!');
                    closeModal('editShopModal');
                    window.location.reload();
                } else {
                    showAlert('editShopAlert', 'Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error updating shop:', error);
                showAlert('editShopAlert', 'An error occurred while updating the shop');
            }
        }
        
        function releaseShop() {
            if (!selectedShopId) {
                alert('Please select a shop first');
                return;
            }
            
            if (confirm('Are you sure you want to release this shop? This will remove the current tenant assignment.')) {
                fetch('summarcity_mall.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=true&action=release_shop&shop_id=${selectedShopId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Shop released successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error releasing shop:', error);
                    alert('An error occurred while releasing the shop');
                });
            }
        }
        
        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['editTenantModal', 'editShopModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });
    </script>
</body>
</html>