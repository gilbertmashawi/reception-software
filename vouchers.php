<?php
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering immediately
ob_start();

// vouchers.php - Internet Vouchers Management
require_once 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'sell';
$db = getDB();
$user = getCurrentUser();

// NEW CODE: Delete old voucher batches and their vouchers automatically
function cleanupOldVoucherBatches() {
    $db = getDB();
    $today = date('Y-m-d');
    
    // Get all batch names and extract their dates
    $stmt = $db->prepare("SELECT id, batch_name FROM voucher_batches WHERE batch_name LIKE 'Import%'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $batchesToDelete = [];
    
    while ($row = $result->fetch_assoc()) {
        $batchName = $row['batch_name'];
        
        // Extract date from batch name (format: "Import YYYY-MM-DD HH:MM:SS")
        if (preg_match('/Import (\d{4}-\d{2}-\d{2})/', $batchName, $matches)) {
            $batchDate = $matches[1];
            
            // If batch date is older than today, mark for deletion
            if ($batchDate < $today) {
                $batchesToDelete[] = $row['id'];
            }
        }
    }
    
    // Delete old batches and their associated vouchers
    if (!empty($batchesToDelete)) {
        $deletedBatches = 0;
        $deletedVouchers = 0;
        
        foreach ($batchesToDelete as $batchId) {
            // Delete associated vouchers first (due to foreign key constraints)
            $deleteVouchersStmt = $db->prepare("DELETE FROM vouchers WHERE batch_id = ?");
            $deleteVouchersStmt->bind_param("i", $batchId);
            $deleteVouchersStmt->execute();
            $deletedVouchers += $deleteVouchersStmt->affected_rows;
            
            // Delete the batch
            $deleteBatchStmt = $db->prepare("DELETE FROM voucher_batches WHERE id = ?");
            $deleteBatchStmt->bind_param("i", $batchId);
            $deleteBatchStmt->execute();
            $deletedBatches++;
        }
        
        // Log the cleanup activity if anything was deleted
        if ($deletedBatches > 0) {
            addActivityLog('Batch Cleanup', 
                         "Deleted {$deletedBatches} old voucher batches and {$deletedVouchers} vouchers", 
                         "Automated cleanup of batches from before {$today}");
        }
    }
}

// Run the cleanup
cleanupOldVoucherBatches();

// Check if vouchers need to be imported
function checkVouchersAvailable() {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM vouchers WHERE status = 'available'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

$vouchersAvailable = checkVouchersAvailable();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $response = [];
    
    switch ($_POST['action']) {
        case 'get_voucher_types':
            $stmt = $db->prepare("SELECT vt.*, 
                (SELECT COUNT(*) FROM vouchers v WHERE v.voucher_type_id = vt.id AND v.status = 'available') as available_count
                FROM voucher_types vt WHERE vt.is_active = 1 ORDER BY vt.price");
            $stmt->execute();
            $result = $stmt->get_result();
            $vouchers = [];
            while ($row = $result->fetch_assoc()) {
                $vouchers[] = $row;
            }
            $response = ['success' => true, 'data' => $vouchers];
            break;
            
        case 'get_available_stations':
            $stations = getAvailableStationAddresses();
            $response = ['success' => true, 'data' => $stations];
            break;
            
        case 'process_sale':
            if (!$vouchersAvailable) {
                $response = ['success' => false, 'message' => 'No vouchers available. Please import vouchers first.'];
                break;
            }
            
            $items = json_decode($_POST['items'], true);
            $customerName = $_POST['customer_name'] ?? '';
            $amountReceived = floatval($_POST['amount_received']);
            $stationId = intval($_POST['station_id']);
            $changeAmount = floatval($_POST['change_amount']);
            
            if (empty($items)) {
                $response = ['success' => false, 'message' => 'Please select at least one voucher'];
                break;
            }
            
            $db->begin_transaction();
            
            try {
                $total = 0;
                $voucherCodes = [];
                
                foreach ($items as $item) {
                    $itemTotal = $item['price'] * $item['quantity'];
                    $total += $itemTotal;
                    
                    // Skip laptop vouchers (they get generated codes)
                    if (in_array($item['id'], [6, 7])) {
                        for ($i = 0; $i < $item['quantity']; $i++) {
                            $laptopCode = 'LP' . strtoupper(substr(md5(uniqid()), 0, 9));
                            $voucherCodes[] = [
                                'code' => $laptopCode,
                                'type' => $item['name'],
                                'price' => $item['price']
                            ];
                        }
                        continue;
                    }
                    
                    // For regular vouchers, get from available stock
                    $voucherTypeId = (int)$item['id'];
                    $limit = (int)$item['quantity'];
                    
                    // Get oldest available vouchers (FIFO)
                    $stmt = $db->prepare("SELECT id, voucher_code, batch_id FROM vouchers 
                                          WHERE voucher_type_id = ? AND status = 'available' 
                                          ORDER BY id ASC LIMIT ?");
                    $stmt->bind_param("ii", $voucherTypeId, $limit);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $vouchersForItem = [];
                    while ($voucher = $result->fetch_assoc()) {
                        $vouchersForItem[] = $voucher;
                    }
                    
                    if (count($vouchersForItem) < $item['quantity']) {
                        throw new Exception("Not enough vouchers available for '{$item['name']}'. Available: " . count($vouchersForItem));
                    }
                    
                    // Store voucher codes and mark as used
                    foreach ($vouchersForItem as $voucher) {
                        $voucherCodes[] = [
                            'code' => $voucher['voucher_code'],
                            'type' => $item['name'],
                            'price' => $item['price'],
                            'batch_id' => $voucher['batch_id']
                        ];
                    }
                }
                
                // Check amount with tolerance
                if ($amountReceived < ($total - 0.01)) {
                    throw new Exception('Amount received is less than total amount.');
                }
                
                // Create sale record - FIXED: added created_by parameter
                $stmt = $db->prepare("INSERT INTO voucher_sales (sale_date, sale_time, total_amount, amount_received, change_amount, customer_name, station_address_id, created_by) VALUES (CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?)");
                $userId = (int)$user['id'];
                $stmt->bind_param("dddsii", $total, $amountReceived, $changeAmount, $customerName, $stationId, $userId);
                $stmt->execute();
                $saleId = $db->insert_id;
                
                // Insert sale items and mark vouchers as used
                $voucherIndex = 0;
                
                foreach ($items as $item) {
                    $voucherTypeId = (int)$item['id'];
                    $quantity = (int)$item['quantity'];
                    $unitPrice = (float)$item['price'];
                    
                    // Insert sale item
                    $stmt = $db->prepare("INSERT INTO voucher_sale_items (sale_id, voucher_type_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iidd", $saleId, $voucherTypeId, $quantity, $unitPrice);
                    $stmt->execute();
                    
                    // Skip laptop vouchers
                    if (in_array($item['id'], [6, 7])) {
                        continue;
                    }
                    
                    // Mark vouchers as used
                    for ($i = 0; $i < $quantity; $i++) {
                        if (isset($voucherCodes[$voucherIndex])) {
                            $voucherCode = $voucherCodes[$voucherIndex]['code'];
                            $stmt = $db->prepare("UPDATE vouchers SET status = 'used', used_date = NOW(), sale_id = ?, station_id = ? WHERE voucher_code = ?");
                            $stationIdForVoucher = $stationId > 0 ? $stationId : null;
                            $stmt->bind_param("iis", $saleId, $stationIdForVoucher, $voucherCode);
                            if (!$stmt->execute()) {
                                throw new Exception("Failed to mark voucher as used.");
                            }
                            $voucherIndex++;
                        }
                    }
                }
                
                // Update station if assigned
                if ($stationId > 0) {
                    $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'occupied', occupation_start = NOW(), current_user_name = ? WHERE id = ?");
                    $userName = !empty($customerName) ? $customerName : 'Walk-in Customer';
                    $stmt->bind_param("si", $userName, $stationId);
                    $stmt->execute();
                }
                
                // Record change if any
                if ($changeAmount > 0.01 && !empty($customerName)) {
                    $stmt = $db->prepare("INSERT INTO customer_changes (customer_name, amount, phone_number, notes, status, given_by) VALUES (?, ?, ?, ?, 'given', ?)");
                    $notes = "Change from voucher sale #{$saleId}";
                    $phone = '';
                    $givenBy = $user['username'];
                    $stmt->bind_param("sdsss", $customerName, $changeAmount, $phone, $notes, $givenBy);
                    $stmt->execute();
                }
                
                // Log activity
                addActivityLog('Internet Vouchers', "Sold vouchers for $" . number_format($total, 2), implode(', ', array_map(function($item) {
                    return "{$item['quantity']}x {$item['name']}";
                }, $items)));
                
                // Send notification
                sendNotification('voucher_sale', 'New Voucher Sale', "Voucher sale #{$saleId} for $" . number_format($total, 2) . " processed by {$user['full_name']}");
                
                $db->commit();
                
                $response = [
                    'success' => true, 
                    'message' => 'Voucher sale processed successfully!',
                    'sale_id' => $saleId,
                    'voucher_codes' => $voucherCodes,
                    'total' => $total,
                    'amount_received' => $amountReceived,
                    'change' => $changeAmount,
                    'customer_name' => $customerName,
                    'date' => date('Y-m-d'),
                    'time' => date('H:i:s'),
                    'station_id' => $stationId,
                    'items' => $items
                ];
                
            } catch (Exception $e) {
                $db->rollback();
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
            break;
                    
        case 'get_active_sessions':
            $occupied = getOccupiedSpacesWithRemainingTime();
            $response = ['success' => true, 'data' => $occupied];
            break;
            
        case 'release_station':
            $stationId = intval($_POST['station_id']);
            
            $stmt = $db->prepare("UPDATE linkspot_station_addresses SET status = 'available', occupation_start = NULL, occupation_end = NULL, current_user_id = NULL, current_user_name = NULL WHERE id = ?");
            $stmt->bind_param("i", $stationId);
            
            if ($stmt->execute()) {
                addActivityLog('Internet Vouchers', "Released station ID {$stationId}", "Released by {$user['full_name']}");
                $response = ['success' => true, 'message' => 'Station released successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to release station'];
            }
            break;
            
        case 'check_voucher_availability':
            $voucherTypeId = intval($_POST['voucher_type_id']);
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM vouchers WHERE voucher_type_id = ? AND status = 'available'");
            $stmt->bind_param("i", $voucherTypeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $response = ['success' => true, 'count' => $row['count']];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_vouchers'])) {
    $voucherTypeId = intval($_POST['voucher_type_id']);
    $batchName = $_POST['batch_name'] ?? 'Import ' . date('Y-m-d H:i:s');
    
    if (!isset($_FILES['voucher_file']) || $_FILES['voucher_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['import_error'] = 'Please upload a valid file.';
        header('Location: vouchers.php?action=import');
        exit;
    }
    
    $tmpFile = $_FILES['voucher_file']['tmp_name'];

    if (!is_uploaded_file($tmpFile)) {
        $_SESSION['import_error'] = 'Invalid upload.';
        header('Location: vouchers.php?action=import');
        exit;
    }

    $lines = file($tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $vouchers = [];
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $value = str_replace('"', '', $line);

        if (strlen($value) === 11) {
            $vouchers[] = $value;
        }
    }

    $vouchers = array_values(array_unique($vouchers));
    
    if (empty($vouchers)) {
        $_SESSION['import_error'] = 'No valid 11-character voucher codes found in the file.';
        header('Location: vouchers.php?action=import');
        exit;
    }
    
    $db->begin_transaction();
    
    try {
        $stmt = $db->prepare("INSERT INTO voucher_batches (batch_name, import_date, file_name, total_vouchers, voucher_type_id, created_by) VALUES (?, CURDATE(), ?, ?, ?, ?)");
        $fileName = $_FILES['voucher_file']['name'];
        $totalVouchers = count($vouchers);
        $createdById = (int)$user['id'];
        $stmt->bind_param("ssiii", $batchName, $fileName, $totalVouchers, $voucherTypeId, $createdById);
        $stmt->execute();
        $batchId = $db->insert_id;
        
        $stmt = $db->prepare("INSERT INTO vouchers (batch_id, voucher_code, voucher_type_id, status) VALUES (?, ?, ?, 'available')");
        
        foreach ($vouchers as $voucherCode) {
            $stmt->bind_param("isi", $batchId, $voucherCode, $voucherTypeId);
            $stmt->execute();
        }
        
        $stmt = $db->prepare("UPDATE voucher_types SET last_import_date = NOW() WHERE id = ?");
        $stmt->bind_param("i", $voucherTypeId);
        $stmt->execute();
        
        $db->commit();
        
        addActivityLog('Vouchers Import', "Imported " . count($vouchers) . " vouchers for batch: {$batchName}", "Voucher Type ID: {$voucherTypeId}");
        sendNotification('voucher_import', 'Vouchers Imported', count($vouchers) . " vouchers imported by {$user['full_name']} for batch: {$batchName}");
        
        $_SESSION['import_success'] = 'Successfully imported ' . count($vouchers) . ' vouchers.';
        header('Location: vouchers.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['import_error'] = 'Error importing vouchers: ' . $e->getMessage();
        header('Location: vouchers.php?action=import');
        exit;
    }
}

// Handle history data request
if (isset($_GET['action']) && $_GET['action'] === 'history_data') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $db = getDB();
    $date = $_GET['date'] ?? '';
    
    $sql = "SELECT vs.*, 
            CONCAT(lsa.station_code, lsa.desk_number) as station, 
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
    
    $sql .= " GROUP BY vs.id ORDER BY vs.id DESC LIMIT 50";
    
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
    
    echo json_encode(['success' => true, 'data' => $sales]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Internet Vouchers</title>
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
        
        /* Voucher Grid */
        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .voucher-card {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .voucher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .voucher-card.selected {
            border-color: var(--primary);
            background: rgba(39, 174, 96, 0.1);
        }
        
        .voucher-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .voucher-card.laptop {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            color: #721c24;
        }
        
        .voucher-card.laptop.selected {
            border: 3px solid #dc3545 !important;
            background: rgba(220, 53, 69, 0.1) !important;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.3) !important;
        }
        
        .voucher-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .voucher-card:not(.laptop) .voucher-name {
            color: var(--secondary);
        }
        
        .voucher-card.laptop .voucher-name {
            color: #721c24 !important;
            font-weight: bold;
        }
        
        .voucher-card.laptop.selected .voucher-name {
            color: #dc3545 !important;
        }
        
        .voucher-price {
            font-size: 18px;
            font-weight: 600;
        }
        
        .voucher-card:not(.laptop) .voucher-price {
            color: var(--primary);
        }
        
        .voucher-card.laptop .voucher-price {
            color: #dc3545 !important;
            font-weight: bold;
        }
        
        .voucher-card.laptop.selected .voucher-price {
            color: #dc3545 !important;
        }
        
        .voucher-count {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .voucher-count.warning {
            color: #ffc107;
        }
        
        .voucher-count.danger {
            color: #dc3545;
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
        
        /* Summary Box */
        .summary-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 18px;
            color: var(--primary);
        }
        
        /* Station Grid */
        .station-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        
        .station-card {
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .station-card.available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .station-card.occupied {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            cursor: not-allowed;
        }
        
        .station-card.selected {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--secondary);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .close:hover {
            color: #343a40;
        }
        
        /* Receipt Styles */
        .receipt {
            font-family: 'Courier New', monospace;
            text-align: center;
        }
        
        .receipt-header {
            margin-bottom: 20px;
        }
        
        .receipt-header h2 {
            font-size: 24px;
            color: var(--primary);
            margin: 0 0 5px 0;
        }
        
        .receipt-header h3 {
            font-size: 14px;
            color: #6c757d;
            margin: 0 0 10px 0;
            font-weight: normal;
        }
        
        .receipt-header p {
            font-size: 12px;
            color: #6c757d;
            margin: 5px 0;
        }
        
        .receipt-details {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        
        .receipt-details td {
            padding: 8px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .receipt-details td:last-child {
            text-align: right;
        }
        
        .voucher-code-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        .voucher-code-display h4 {
            margin: 0 0 10px 0;
            color: var(--secondary);
        }
        
        .voucher-code {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            color: var(--primary);
            padding: 10px;
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            margin: 10px 0;
            word-break: break-all;
        }
        
        .receipt-total {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
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
            
            .voucher-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .station-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-content {
                width: 95%;
            }
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .modal-content * {
                visibility: visible;
            }
            .modal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: auto;
                background: white;
            }
            .modal-content {
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                border: none;
            }
            .modal-footer {
                display: none !important;
            }
            .close {
                display: none !important;
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
                    <h1><i class="fas fa-wifi"></i> Internet Vouchers</h1>
                    <p>Manage internet voucher sales and active sessions</p>
                </div>
                <div class="user-menu">
                    <div class="notification-badge">
                        <i class="fas fa-bell"></i>
                        <?php $unread = getUnreadNotificationsCount(); ?>
                        <?php if ($unread > 0): ?>
                        <span class="badge"><?php echo $unread; ?></span>
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
                <div class="tab <?php echo $action === 'sell' ? 'active' : ''; ?>" onclick="switchTab('sell')">
                    Sell Vouchers
                </div>
                <div class="tab <?php echo $action === 'import' ? 'active' : ''; ?>" onclick="switchTab('import')">
                    Import Vouchers
                </div>
                <div class="tab <?php echo $action === 'history' ? 'active' : ''; ?>" onclick="switchTab('history')">
                    Sales History
                </div>
                <div class="tab <?php echo $action === 'active' ? 'active' : ''; ?>" onclick="switchTab('active')">
                    Active Sessions
                </div>
            </div>
            
            <?php if (!$vouchersAvailable && $action === 'sell'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>No vouchers available!</strong> Please import vouchers before selling.
                <a href="vouchers.php?action=import" class="btn btn-sm btn-warning" style="margin-left: 10px;">
                    <i class="fas fa-file-import"></i> Import Now
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['import_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['import_success']; ?>
                <?php unset($_SESSION['import_success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['import_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['import_error']; ?>
                <?php unset($_SESSION['import_error']); ?>
            </div>
            <?php endif; ?>
            
            <div id="tab-sell" class="tab-content" style="<?php echo $action === 'sell' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sell Internet Vouchers</h3>
                    </div>
                    
                    <div class="alert" id="alertMessage" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label class="form-label">Customer Name (Optional)</label>
                        <input type="text" id="customerName" class="form-control" placeholder="Enter customer name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Select Voucher Type</label>
                        <div class="voucher-grid" id="voucherGrid">
                            <!-- Vouchers will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assign Station (Optional)</label>
                        <div class="station-grid" id="stationGrid">
                            <!-- Stations will be loaded here -->
                        </div>
                        <small class="text-muted">Select an available station to assign to customer</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Total Amount</label>
                            <input type="text" id="totalAmount" class="form-control" readonly value="$0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount Received</label>
                            <input type="number" id="amountReceived" class="form-control" placeholder="0.00" step="0.01" min="0" oninput="calculateChange()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Change</label>
                            <input type="text" id="changeAmount" class="form-control" readonly value="$0.00">
                        </div>
                    </div>
                    
                    <div class="summary-box">
                        <h4>Selected Items</h4>
                        <div id="selectedItems">No items selected</div>
                    </div>
                    
                    <button class="btn btn-primary btn-lg" onclick="processSale()" style="margin-top: 20px; width: 100%;" <?php echo !$vouchersAvailable ? 'disabled' : ''; ?>>
                        <i class="fas fa-cash-register"></i> Process Sale
                    </button>
                </div>
            </div>
            
            <div id="tab-import" class="tab-content" style="<?php echo $action === 'import' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Import Vouchers</h3>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Instructions:</strong> Upload a CSV or text file containing 11-character voucher codes. 
                        Each code should be on a separate line. The system will automatically extract only valid 11-character codes.
                        <br><br>
                        <strong>Note:</strong> "Laptop" and "Day Laptop" vouchers are for walk-in laptop users and do not require imported codes.
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Batch Name</label>
                            <input type="text" name="batch_name" class="form-control" placeholder="e.g., January 2026 Vouchers" value="Import <?php echo date('Y-m-d H:i:s'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Voucher Type</label>
                            <select name="voucher_type_id" class="form-control" required>
                                <option value="">-- Select Voucher Type --</option>
                                <?php
                                $stmt = $db->prepare("SELECT * FROM voucher_types WHERE is_active = 1 AND id NOT IN (6, 7) ORDER BY price");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo $row['name']; ?> - $<?php echo $row['price']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted" style="color: #dc3545;">
                                <i class="fas fa-exclamation-triangle"></i> Laptop vouchers cannot be imported - they are for walk-in laptop users
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Voucher File (CSV or Text)</label>
                            <input type="file" name="voucher_file" class="form-control" accept=".csv,.txt" required>
                            <small class="text-muted">File should contain one 11-character voucher code per line</small>
                        </div>
                        
                        <button type="submit" name="import_vouchers" class="btn btn-primary btn-lg" style="margin-top: 20px; width: 100%;">
                            <i class="fas fa-file-import"></i> Import Vouchers
                        </button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Available Voucher Inventory</h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Voucher Type</th>
                                    <th>Price</th>
                                    <th>Available</th>
                                    <th>Used</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->prepare("SELECT 
                                    vt.*,
                                    COUNT(CASE WHEN v.status = 'available' THEN 1 END) as available_count,
                                    COUNT(CASE WHEN v.status = 'used' THEN 1 END) as used_count,
                                    COUNT(*) as total_count
                                    FROM voucher_types vt
                                    LEFT JOIN vouchers v ON vt.id = v.voucher_type_id
                                    WHERE vt.is_active = 1
                                    GROUP BY vt.id
                                    ORDER BY vt.price");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #6c757d;">
                                            No vouchers imported yet.
                                        </td>
                                    </tr>
                                <?php else:
                                    while ($row = $result->fetch_assoc()): 
                                        $isLaptop = in_array($row['id'], [6, 7]); ?>
                                        <tr <?php echo $isLaptop ? 'style="background-color: #f8d7da20;"' : ''; ?>>
                                            <td>
                                                <?php echo $row['name']; ?>
                                                <?php if ($isLaptop): ?>
                                                    <span class="badge badge-danger">Laptop User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?php echo $row['price']; ?></td>
                                            <td>
                                                <?php if ($isLaptop): ?>
                                                    <span class="badge badge-danger">N/A</span>
                                                <?php else: ?>
                                                    <span class="badge <?php echo $row['available_count'] == 0 ? 'badge-danger' : 'badge-success'; ?>">
                                                        <?php echo $row['available_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isLaptop): ?>
                                                    <span class="badge badge-danger">N/A</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info"><?php echo $row['used_count']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isLaptop): ?>
                                                    <span class="badge badge-danger">N/A</span>
                                                <?php else: ?>
                                                    <?php echo $row['total_count']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isLaptop): ?>
                                                    <span class="badge badge-danger">Walk-in Only</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Importable</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="tab-history" class="tab-content" style="<?php echo $action === 'history' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sales History</h3>
                        <div>
                            <input type="date" id="historyDate" class="form-control" style="width: auto; display: inline-block;">
                            <button class="btn btn-primary btn-sm" onclick="loadHistory()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table" id="historyTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date/Time</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Voucher Codes</th>
                                    <th>Station</th>
                                    <th>Total</th>
                                    <th>Received</th>
                                    <th>Change</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <!-- History will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="tab-active" class="tab-content" style="<?php echo $action === 'active' ? 'display: block;' : 'display: none;'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Active Sessions</h3>
                        <button class="btn btn-primary btn-sm" onclick="loadActiveSessions()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table" id="activeSessionsTable">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th>Customer</th>
                                    <th>Voucher Type</th>
                                    <th>Start Time</th>
                                    <th>Remaining Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="activeSessionsBody">
                                <!-- Active sessions will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Receipt Modal -->
    <div class="modal" id="receiptModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Linkspot Services</h3>
                <button class="close" onclick="closeReceiptModal()">&times;</button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeReceiptModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let selectedVouchers = [];
        let selectedStation = null;
        let availableStations = [];
        let voucherTypes = [];
        
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadVouchers();
            loadStations();
            if ('<?php echo $action; ?>' === 'history') {
                loadHistory();
            }
            if ('<?php echo $action; ?>' === 'active') {
                loadActiveSessions();
            }
        });
        
        // Switch tabs
        function switchTab(tab) {
            window.location.href = 'vouchers.php?action=' + tab;
        }
        
        // Load voucher types
        function loadVouchers() {
            fetch('vouchers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_voucher_types'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text.substring(0, 200));
                        throw new Error('Server returned non-JSON response');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    voucherTypes = data.data;
                    const grid = document.getElementById('voucherGrid');
                    grid.innerHTML = '';
                    
                    data.data.forEach(voucher => {
                        const isLaptop = [6, 7].includes(voucher.id);
                        const card = document.createElement('div');
                        card.className = isLaptop ? 'voucher-card laptop' : 'voucher-card';
                        if (!isLaptop && voucher.available_count <= 0) {
                            card.classList.add('disabled');
                        }
                        card.dataset.id = voucher.id;
                        card.innerHTML = `
                            <div class="voucher-name">${voucher.name}</div>
                            <div class="voucher-price">$${parseFloat(voucher.price).toFixed(2)}</div>
                            <div class="voucher-count ${!isLaptop && voucher.available_count <= 10 ? 'danger' : !isLaptop && voucher.available_count <= 50 ? 'warning' : ''}">
                                ${isLaptop ? 'Walk-in Laptop' : (voucher.available_count + ' available')}
                            </div>
                        `;
                        
                        if (isLaptop || (!isLaptop && voucher.available_count > 0)) {
                            card.onclick = () => selectVoucher(voucher.id, voucher.name, voucher.price, voucher.available_count, isLaptop);
                        }
                        
                        grid.appendChild(card);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading vouchers:', error);
                showAlert('Error loading voucher types: ' + error.message, 'danger');
            });
        }
        
        // Load available stations
        function loadStations() {
            fetch('vouchers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_available_stations'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text.substring(0, 200));
                        throw new Error('Server returned non-JSON response');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    availableStations = data.data;
                    const grid = document.getElementById('stationGrid');
                    grid.innerHTML = '';
                    
                    // Group stations by code
                    const grouped = {};
                    data.data.forEach(station => {
                        if (!grouped[station.station_code]) {
                            grouped[station.station_code] = [];
                        }
                        grouped[station.station_code].push(station);
                    });
                    
                    // Display stations
                    for (const [code, stations] of Object.entries(grouped)) {
                        stations.forEach(station => {
                            const card = document.createElement('div');
                            card.className = 'station-card available';
                            card.dataset.id = station.id;
                            card.innerHTML = `${code}${station.desk_number}`;
                            card.onclick = () => selectStation(station.id);
                            grid.appendChild(card);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading stations:', error);
                showAlert('Error loading stations: ' + error.message, 'danger');
            });
        }
        
        // Select voucher
        function selectVoucher(id, name, price, availableCount, isLaptop = false) {
            if (!isLaptop && availableCount <= 0) {
                showAlert(`No vouchers available for "${name}". Please import more vouchers.`, 'danger');
                return;
            }
            
            // Check if we have enough for this selection (only for non-laptop)
            if (!isLaptop) {
                const existingIndex = selectedVouchers.findIndex(v => v.id === id);
                const currentQuantity = existingIndex >= 0 ? selectedVouchers[existingIndex].quantity : 0;
                
                if (currentQuantity + 1 > availableCount) {
                    showAlert(`Not enough vouchers available for "${name}". Only ${availableCount} remaining.`, 'danger');
                    return;
                }
            }
            
            const existingIndex = selectedVouchers.findIndex(v => v.id === id);
            
            if (existingIndex === -1) {
                selectedVouchers.push({
                    id: id,
                    name: name,
                    price: parseFloat(price),
                    quantity: 1,
                    isLaptop: isLaptop
                });
            } else {
                selectedVouchers[existingIndex].quantity++;
            }
            
            updateSelection();
        }
        
        // Select station
        function selectStation(stationId) {
            if (selectedStation === stationId) {
                selectedStation = null;
            } else {
                selectedStation = stationId;
            }
            
            updateStationSelection();
        }
        
        // Update station selection UI
        function updateStationSelection() {
            const cards = document.querySelectorAll('.station-card');
            cards.forEach(card => {
                card.classList.remove('selected');
                if (parseInt(card.dataset.id) === selectedStation) {
                    card.classList.add('selected');
                }
            });
        }
        
        // Update selection display
        function updateSelection() {
            const selectedItems = document.getElementById('selectedItems');
            const totalAmount = document.getElementById('totalAmount');
            
            let total = 0;
            let itemsHtml = '';
            
            selectedVouchers.forEach(voucher => {
                const itemTotal = voucher.price * voucher.quantity;
                total += itemTotal;
                itemsHtml += `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>${voucher.quantity}x ${voucher.name} ${voucher.isLaptop ? '(Laptop)' : ''}</span>
                        <span>$${itemTotal.toFixed(2)}</span>
                    </div>
                `;
            });
            
            selectedItems.innerHTML = itemsHtml || 'No items selected';
            totalAmount.value = '$' + total.toFixed(2);
            
            // Update voucher card selection
            const cards = document.querySelectorAll('.voucher-card');
            cards.forEach(card => {
                const voucherId = parseInt(card.dataset.id);
                card.classList.toggle('selected', selectedVouchers.some(v => v.id === voucherId));
            });
            
            // Recalculate change
            calculateChange();
        }
        
        // Calculate change
        function calculateChange() {
            const totalInput = document.getElementById('totalAmount');
            const receivedInput = document.getElementById('amountReceived');
            const changeInput = document.getElementById('changeAmount');
            
            const total = parseFloat(totalInput.value.replace('$', '')) || 0;
            const received = parseFloat(receivedInput.value) || 0;
            
            const change = received - total;
            changeInput.value = change > 0 ? '$' + change.toFixed(2) : '$0.00';
            
            // Show alert if insufficient amount
            if (received > 0 && received < (total - 0.01)) {
                showAlert('Amount received is less than total amount', 'danger');
            } else {
                hideAlert();
            }
        }
        
        // Process sale
        function processSale() {
            if (selectedVouchers.length === 0) {
                showAlert('Please select at least one voucher', 'danger');
                return;
            }
            
            const customerName = document.getElementById('customerName').value;
            const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
            const total = parseFloat(document.getElementById('totalAmount').value.replace('$', ''));
            const change = parseFloat(document.getElementById('changeAmount').value.replace('$', '')) || 0;
            
            if (amountReceived < (total - 0.01)) {
                showAlert('Amount received is less than total amount', 'danger');
                return;
            }
            
            if (!confirm('Process this sale?')) {
                return;
            }
            
            // Debug: Log what we're sending
            console.log('Sending items:', selectedVouchers);
            
            // Disable button to prevent double-click
            const processBtn = document.querySelector('button[onclick="processSale()"]');
            const originalText = processBtn.innerHTML;
            processBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            processBtn.disabled = true;
            
            // Create form data
            const formData = new URLSearchParams();
            formData.append('ajax', 'true');
            formData.append('action', 'process_sale');
            formData.append('items', JSON.stringify(selectedVouchers));
            formData.append('customer_name', customerName);
            formData.append('amount_received', amountReceived);
            formData.append('change_amount', change);
            formData.append('station_id', selectedStation || 0);
            
            fetch('vouchers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => {
                // First check if response is valid
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response as text first to debug
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                });
            })
            .then(data => {
                // Re-enable button
                processBtn.innerHTML = originalText;
                processBtn.disabled = false;
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    showReceiptModal(data);
                    
                    // Reset form
                    selectedVouchers = [];
                    selectedStation = null;
                    document.getElementById('customerName').value = '';
                    document.getElementById('amountReceived').value = '';
                    updateSelection();
                    updateStationSelection();
                    loadStations();
                    loadVouchers();
                    
                    // Auto-hide success alert after 5 seconds
                    setTimeout(() => {
                        hideAlert();
                    }, 5000);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error processing sale:', error);
                showAlert('Error processing sale: ' + error.message, 'danger');
                
                // Re-enable button
                processBtn.innerHTML = originalText;
                processBtn.disabled = false;
            });
        }
        
        // Show receipt modal
        function showReceiptModal(data) {
            const modal = document.getElementById('receiptModal');
            const content = document.getElementById('receiptContent');
            
            // Group voucher codes by type
            const voucherGroups = {};
            data.voucher_codes.forEach(voucher => {
                if (!voucherGroups[voucher.type]) {
                    voucherGroups[voucher.type] = [];
                }
                voucherGroups[voucher.type].push(voucher);
            });
            
            let receiptHtml = `
    <div class="receipt" style="position: relative;">
        
        <!-- WATERMARK OVERLAY -->
        <div style="
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9999;
            overflow: hidden;
        ">
            <span style="position:absolute; top:10%; left:5%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:30%; left:25%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:50%; left:10%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:70%; left:35%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:20%; left:60%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:40%; left:75%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:60%; left:55%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
            <span style="position:absolute; top:80%; left:70%; 
                color: rgba(0,128,0,0.08); font-size: 2.5rem; 
                font-weight: bold; transform: rotate(-30deg); white-space: nowrap;">
                We are the connecting center
            </span>
        </div>

        <!-- RECEIPT CONTENT -->
        <div class="receipt-header">
            <h2>Linkspot</h2>

            <p>Customer: ${data.customer_name || 'Walk-in'}</p>
            ${data.station_id ? `<p>Station: ${getStationName(data.station_id)}</p>` : ''}
            <p>Received from: <?php echo $_SESSION['full_name']; ?></p>
        </div>

        <table class="receipt-details">
`;

            
            // Add items
            data.items.forEach(item => {
                const itemTotal = item.price * item.quantity;
                receiptHtml += `
                    <tr>
                        <td>${item.quantity}x ${item.name} ${item.isLaptop ? '(Laptop)' : ''}</td>
                        <td>$${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            });
            
            receiptHtml += `
                        <tr>
                            <td colspan="2" style="border-bottom: 1px solid #000;"></td>
                        </tr>
                        <tr>
                            <td>Total:</td>
                            <td>$${data.total.toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td>Amount Received:</td>
                            <td>$${data.amount_received.toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td>Change:</td>
                            <td>$${data.change.toFixed(2)}</td>
                        </tr>
                    </table>
            `;
            
            // Add voucher codes
            for (const [type, vouchers] of Object.entries(voucherGroups)) {
                receiptHtml += `
                    <div class="voucher-code-display">
                        <h4>${type} Voucher${vouchers.length > 1 ? 's' : ''}</h4>
                `;
                
                vouchers.forEach(voucher => {
                    receiptHtml += `
                        <div class="voucher-code">${voucher.code}</div>
                        <small>Price: $${voucher.price.toFixed(2)}</small>
                    `;
                });
                
                receiptHtml += `
                    </div>
                `;
            }
            
            receiptHtml += `
                            <p>${data.date} ${data.time}</p>
                    <p style="margin-top: 20px; font-size: 12px; color: #6c757d;">
                        Thank you for your purchase!<br>
                    </p>
                </div>
            `;
            
            content.innerHTML = receiptHtml;
            modal.style.display = 'flex';
        }
        
        // Get station name from ID
        function getStationName(stationId) {
            const station = availableStations.find(s => s.id == stationId);
            return station ? `${station.station_code}${station.desk_number}` : stationId;
        }
        
        // Close receipt modal
        function closeReceiptModal() {
            document.getElementById('receiptModal').style.display = 'none';
        }
        
        // Print receipt
        function printReceipt() {
            window.print();
        }
        
        // Load sales history
        function loadHistory() {
            const date = document.getElementById('historyDate').value;
            let url = 'vouchers.php?action=history_data';
            if (date) {
                url += '&date=' + date;
            }
            
            fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('historyTableBody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(sale => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${sale.id}</td>
                            <td>${sale.sale_date} ${sale.sale_time}</td>
                            <td>${sale.customer_name || 'Walk-in'}</td>
                            <td>${sale.items || 'N/A'}</td>
                            <td>${sale.voucher_codes || 'N/A'}</td>
                            <td>${sale.station || 'N/A'}</td>
                            <td>$${parseFloat(sale.total_amount).toFixed(2)}</td>
                            <td>$${parseFloat(sale.amount_received || sale.total_amount).toFixed(2)}</td>
                            <td>$${parseFloat(sale.change_amount || 0).toFixed(2)}</td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            });
        }
        
        // Load active sessions
        function loadActiveSessions() {
            fetch('vouchers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_active_sessions'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('activeSessionsBody');
                    tbody.innerHTML = '';
                    
                    if (data.data.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7" style="text-align: center; color: #6c757d;">
                                    No active sessions
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    data.data.forEach(session => {
                        const remainingMinutes = session.remaining_minutes || 0;
                        let statusBadge = '<span class="badge badge-success">Active</span>';
                        let timeClass = '';
                        
                        if (remainingMinutes <= 5) {
                            statusBadge = '<span class="badge badge-danger">Expiring Soon</span>';
                            timeClass = 'badge-danger';
                        } else if (remainingMinutes <= 15) {
                            statusBadge = '<span class="badge badge-warning">Almost Done</span>';
                            timeClass = 'badge-warning';
                        }
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${session.station || 'N/A'}</td>
                            <td>${session.customer_name || 'Walk-in'}</td>
                            <td>${session.voucher_type || 'N/A'}</td>
                            <td>${session.sale_time}</td>
                            <td><span class="badge ${timeClass}">${session.remaining_time || 'N/A'}</span></td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="releaseStation(${session.station_address_id})">
                                    <i class="fas fa-times"></i> Release
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            });
        }
        
        // Release station
        function releaseStation(stationId) {
            if (!confirm('Release this station?')) {
                return;
            }
            
            fetch('vouchers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=true&action=release_station&station_id=${stationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadActiveSessions();
                    loadStations();
                } else {
                    showAlert(data.message, 'danger');
                }
            });
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
        
        // Auto-refresh active sessions every minute
        if ('<?php echo $action; ?>' === 'active') {
            setInterval(loadActiveSessions, 60000);
        }
    </script>
</body>
</html>