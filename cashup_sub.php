<?php
// cashup.php - COMPLETE FIXED VERSION with consistent styling
// February 2026
// ────────────────────────────────────────────────────────────────────────────────
// AJAX HANDLER - This must be at the VERY TOP to prevent any output interference
// ────────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['action'] === 'submit_cashup') {
    // Completely clean all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
   
    // Set JSON header immediately
    header('Content-Type: application/json; charset=utf-8');
   
    // Temporarily suppress ALL PHP errors/warnings/output (production)
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
   
    // Initialize response
    $response = ['success' => false, 'message' => 'Unknown error'];
   
    try {
        // Simple direct database connection for AJAX
        require_once 'config_simple.php';
       
        $db = new mysqli("localhost", "root", "", "linkspot_db");
        if ($db->connect_error) {
            throw new Exception("Database connection failed: " . $db->connect_error);
        }
       
        // Get user info from session
        session_start();
        $userId   = (int) ($_SESSION['user_id']   ?? 0);
        $userName =       $_SESSION['user_name']  ?? 'Unknown User';
       
        // Get POST data with safe defaults
        $notes = trim($_POST['notes'] ?? '');
       
        $vRev    = floatval($_POST['daily_vouchers_revenue']          ?? 0);
        $vCash   = floatval($_POST['daily_vouchers_cash_received']    ?? 0);
        $vChange = floatval($_POST['daily_vouchers_change_given']     ?? 0);
        $vTrans  = (int)   ($_POST['daily_vouchers_transactions']     ?? 0);
       
        $lRev    = floatval($_POST['daily_linkspot_revenue']          ?? 0);
        $lCash   = floatval($_POST['daily_linkspot_cash']             ?? 0);
        $lTrans  = (int)   ($_POST['daily_linkspot_transactions']     ?? 0);
       
        $mRev    = floatval($_POST['daily_mall_revenue']              ?? 0);
        $mCash   = floatval($_POST['daily_mall_cash']                 ?? 0);
        $mTrans  = (int)   ($_POST['daily_mall_transactions']         ?? 0);
       
        $gtRev   = floatval($_POST['grand_total_revenue']             ?? 0);
        $gtCash  = floatval($_POST['grand_total_cash_in']             ?? 0);
       
        // Check if table exists, create if not
        $checkTable = $db->query("SHOW TABLES LIKE 'cashup_submissions'");
        if ($checkTable->num_rows == 0) {
            $createTable = "CREATE TABLE cashup_submissions (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                submission_date DATE NOT NULL,
                submitted_by_user_id INT(11) NOT NULL,
                submitted_by_name VARCHAR(100) NOT NULL,
                daily_vouchers_revenue DECIMAL(10,2) DEFAULT 0,
                daily_vouchers_cash_received DECIMAL(10,2) DEFAULT 0,
                daily_vouchers_change_given DECIMAL(10,2) DEFAULT 0,
                daily_vouchers_transactions INT(11) DEFAULT 0,
                daily_linkspot_revenue DECIMAL(10,2) DEFAULT 0,
                daily_linkspot_cash DECIMAL(10,2) DEFAULT 0,
                daily_linkspot_transactions INT(11) DEFAULT 0,
                daily_mall_revenue DECIMAL(10,2) DEFAULT 0,
                daily_mall_cash DECIMAL(10,2) DEFAULT 0,
                daily_mall_transactions INT(11) DEFAULT 0,
                grand_total_revenue DECIMAL(10,2) DEFAULT 0,
                grand_total_cash_in DECIMAL(10,2) DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
           
            if (!$db->query($createTable)) {
                throw new Exception("Failed to create table: " . $db->error);
            }
        }
       
        // Prepare and execute INSERT
        $sql = "INSERT INTO cashup_submissions (
            submission_date,
            submitted_by_user_id,
            submitted_by_name,
            daily_vouchers_revenue,
            daily_vouchers_cash_received,
            daily_vouchers_change_given,
            daily_vouchers_transactions,
            daily_linkspot_revenue,
            daily_linkspot_cash,
            daily_linkspot_transactions,
            daily_mall_revenue,
            daily_mall_cash,
            daily_mall_transactions,
            grand_total_revenue,
            grand_total_cash_in,
            notes
        ) VALUES (
            CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
       
        $stmt->bind_param(
            "issddddidididdds",
            $userId,
            $userName,
            $vRev,
            $vCash,
            $vChange,
            $vTrans,
            $lRev,
            $lCash,
            $lTrans,
            $mRev,
            $mCash,
            $mTrans,
            $gtRev,
            $gtCash,
            $notes
        );
       
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
       
        $submissionId = $stmt->insert_id;
       
        // Log activity if possible
        $logMsg = "Daily cash-up sent by $userName (submission #$submissionId)";
        @file_put_contents(__DIR__ . '/activity.log', date('[Y-m-d H:i:s] ') . $logMsg . "\n", FILE_APPEND);
       
        $response = [
            'success'      => true,
            'message'      => 'Cash-up summary sent successfully!',
            'submission_id' => $submissionId
        ];
       
        $stmt->close();
        $db->close();
       
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
       
        // Write real error to file for debugging
        @file_put_contents(
            __DIR__ . '/ajax-cashup-errors.log',
            date('[Y-m-d H:i:s] ') . $e->getMessage() . " | POST data: " . json_encode($_POST, JSON_PRETTY_PRINT) . "\n\n",
            FILE_APPEND
        );
    }
   
    // Output clean JSON and exit
    echo json_encode($response);
    exit;
}

// ────────────────────────────────────────────────────────────────────────────────
// NORMAL PAGE - This only runs for non-AJAX requests
// ────────────────────────────────────────────────────────────────────────────────
ob_start();

// Enable errors for debugging during development
$debug_mode = true;
if ($debug_mode) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Simple error logging function
function debug_log($msg) {
    file_put_contents(__DIR__ . '/debug-cashup.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// Load configuration with error handling
try {
    if (!file_exists('config.php')) {
        throw new Exception('config.php not found');
    }
   
    require_once 'config.php';
   
    // Check for required functions
    $required_functions = ['requireLogin', 'getDB', 'getCurrentUser', 'getUnreadNotificationsCount'];
    foreach ($required_functions as $func) {
        if (!function_exists($func)) {
            throw new Exception("Required function '$func' not found in config.php");
        }
    }
   
    requireLogin();
    $db = getDB();
    $user = getCurrentUser() ?? ['id' => 0, 'full_name' => 'Unknown'];
   
} catch (Exception $e) {
    die("<div style='padding:20px; background:#ffebee; color:#c62828; border:1px solid #ef9a9a; border-radius:5px;'>
        <h3>Configuration Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check your config.php file.</p>
    </div>");
}

// Ensure cashup_submissions table exists
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'cashup_submissions'");
    if ($checkTable->num_rows == 0) {
        $createTable = "CREATE TABLE IF NOT EXISTS cashup_submissions (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            submission_date DATE NOT NULL,
            submitted_by_user_id INT(11) NOT NULL,
            submitted_by_name VARCHAR(100) NOT NULL,
            daily_vouchers_revenue DECIMAL(10,2) DEFAULT 0,
            daily_vouchers_cash_received DECIMAL(10,2) DEFAULT 0,
            daily_vouchers_change_given DECIMAL(10,2) DEFAULT 0,
            daily_vouchers_transactions INT(11) DEFAULT 0,
            daily_linkspot_revenue DECIMAL(10,2) DEFAULT 0,
            daily_linkspot_cash DECIMAL(10,2) DEFAULT 0,
            daily_linkspot_transactions INT(11) DEFAULT 0,
            daily_mall_revenue DECIMAL(10,2) DEFAULT 0,
            daily_mall_cash DECIMAL(10,2) DEFAULT 0,
            daily_mall_transactions INT(11) DEFAULT 0,
            grand_total_revenue DECIMAL(10,2) DEFAULT 0,
            grand_total_cash_in DECIMAL(10,2) DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (submission_date),
            INDEX (submitted_by_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
       
        if (!$db->query($createTable)) {
            throw new Exception("Failed to create cashup_submissions table: " . $db->error);
        }
        debug_log("Created cashup_submissions table");
    }
} catch (Exception $e) {
    debug_log("Table check error: " . $e->getMessage());
    // Continue anyway - we'll handle missing table gracefully
}

// Set date variables
$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');
$lastOfMonth = date('Y-m-t');

// Formatting function
function fmt($amount) {
    return number_format((float)$amount, 2);
}

// Initialize arrays with defaults
$dailyVouchers = $dailyLinkspot = $dailyMall = [];
$monthlyVouchers = $monthlyLinkspot = $monthlyMall = [];

// ────────────────────────────────────────────────
// Get Daily / Monthly summaries
// ────────────────────────────────────────────────

// Get Daily Vouchers Summary
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(total_amount), 0) AS total_voucher_sales,
            COALESCE(SUM(amount_received), 0) AS cash_received_vouchers,
            COALESCE(SUM(change_amount), 0) AS change_given_vouchers
        FROM voucher_sales
        WHERE sale_date = ?
    ");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $dailyVouchers = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Daily vouchers query error: " . $e->getMessage());
}
$dailyVouchers = array_merge([
    'transactions' => 0,
    'total_voucher_sales' => 0,
    'cash_received_vouchers' => 0,
    'change_given_vouchers' => 0
], $dailyVouchers);

// Get Daily LinkSpot Summary
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(amount), 0) AS total_linkspot_payments,
            COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) AS cash_linkspot
        FROM linkspot_payments
        WHERE payment_date = ?
    ");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $dailyLinkspot = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Daily linkspot query error: " . $e->getMessage());
}
$dailyLinkspot = array_merge([
    'transactions' => 0,
    'total_linkspot_payments' => 0,
    'cash_linkspot' => 0
], $dailyLinkspot);

// Get Daily Mall Summary
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(amount), 0) AS total_mall_payments,
            COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) AS cash_mall
        FROM mall_payments
        WHERE payment_date = ?
    ");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $dailyMall = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Daily mall query error: " . $e->getMessage());
}
$dailyMall = array_merge([
    'transactions' => 0,
    'total_mall_payments' => 0,
    'cash_mall' => 0
], $dailyMall);

// Get Monthly Vouchers Summary
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(total_amount), 0) AS total_voucher_sales,
            COALESCE(SUM(amount_received), 0) AS cash_received_vouchers,
            COALESCE(SUM(change_amount), 0) AS change_given_vouchers
        FROM voucher_sales
        WHERE sale_date >= ? AND sale_date <= ?
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $firstOfMonth, $lastOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyVouchers = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Monthly vouchers query error: " . $e->getMessage());
}
$monthlyVouchers = array_merge([
    'transactions' => 0,
    'total_voucher_sales' => 0,
    'cash_received_vouchers' => 0,
    'change_given_vouchers' => 0
], $monthlyVouchers);

// Get Monthly LinkSpot Summary
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(amount), 0) AS total_linkspot_payments,
            COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) AS cash_linkspot
        FROM linkspot_payments
        WHERE payment_date >= ? AND payment_date <= ?
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $firstOfMonth, $lastOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyLinkspot = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Monthly linkspot query error: " . $e->getMessage());
}
$monthlyLinkspot = array_merge([
    'transactions' => 0,
    'total_linkspot_payments' => 0,
    'cash_linkspot' => 0
], $monthlyLinkspot);

// Get Monthly Mall Summary
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS transactions,
            COALESCE(SUM(amount), 0) AS total_mall_payments,
            COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) AS cash_mall
        FROM mall_payments
        WHERE payment_date >= ? AND payment_date <= ?
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $firstOfMonth, $lastOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyMall = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Monthly mall query error: " . $e->getMessage());
}
$monthlyMall = array_merge([
    'transactions' => 0,
    'total_mall_payments' => 0,
    'cash_mall' => 0
], $monthlyMall);

// ────────────────────────────────────────────────
// Get detailed breakdowns
// ────────────────────────────────────────────────

// Get voucher type breakdown
$voucherBreakdown = [];
try {
    $stmt = $db->prepare("
        SELECT 
            vt.name as voucher_type,
            COUNT(*) as quantity,
            SUM(vsi.unit_price) as total_amount
        FROM voucher_sales vs
        JOIN voucher_sale_items vsi ON vs.id = vsi.sale_id
        JOIN voucher_types vt ON vsi.voucher_type_id = vt.id
        WHERE vs.sale_date = ?
        GROUP BY vt.id, vt.name
        ORDER BY vt.price
    ");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $voucherBreakdown[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Voucher breakdown query error: " . $e->getMessage());
}

// Get LinkSpot member breakdown
$linkspotMemberBreakdown = [];
try {
    $stmt = $db->prepare("
        SELECT 
            payer_name as member_name,
            COUNT(*) as quantity,
            SUM(amount) as total_amount
        FROM linkspot_payments
        WHERE payment_date = ?
        GROUP BY payer_name
        ORDER BY total_amount DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $linkspotMemberBreakdown[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Linkspot member breakdown query error: " . $e->getMessage());
}

// Get Mall shop breakdown
$mallShopBreakdown = [];
try {
    $stmt = $db->prepare("
        SELECT 
            payer_name as shop_name,
            COUNT(*) as quantity,
            SUM(amount) as total_amount
        FROM mall_payments
        WHERE payment_date = ?
        GROUP BY payer_name
        ORDER BY total_amount DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $mallShopBreakdown[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    debug_log("Mall shop breakdown query error: " . $e->getMessage());
}

// Calculate totals
$daily_total_cash_in = $dailyVouchers['cash_received_vouchers'] + $dailyLinkspot['cash_linkspot'] + $dailyMall['cash_mall'];
$daily_total_revenue = $dailyVouchers['total_voucher_sales'] + $dailyLinkspot['total_linkspot_payments'] + $dailyMall['total_mall_payments'];
$monthly_total_cash_in = $monthlyVouchers['cash_received_vouchers'] + $monthlyLinkspot['cash_linkspot'] + $monthlyMall['cash_mall'];
$monthly_total_revenue = $monthlyVouchers['total_voucher_sales'] + $monthlyLinkspot['total_linkspot_payments'] + $monthlyMall['total_mall_payments'];

// ────────────────────────────────────────────────
// HTML OUTPUT
// ────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Cash-up / End of Day</title>
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
        
        /* Service Sections */
        .service-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .service-section h3 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-row.total {
            font-weight: 600;
            border-top: 2px solid #dee2e6;
            margin-top: 10px;
            padding-top: 15px;
            color: var(--primary);
            font-size: 16px;
        }
        
        .item-row.subtotal {
            font-weight: 600;
            color: var(--secondary);
            border-top: 1px dashed #dee2e6;
            margin-top: 5px;
            padding-top: 10px;
        }
        
        .item-row .left {
            flex: 1;
        }
        
        .item-row .right {
            text-align: right;
            min-width: 100px;
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
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-lg {
            padding: 12px 25px;
            font-size: 16px;
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
        
        /* Totals Display */
        .totals-display {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
        }
        
        .totals-display h3 {
            color: white;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .totals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            text-align: center;
        }
        
        .total-item h4 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .total-amount {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
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
            
            .totals-grid {
                grid-template-columns: 1fr;
            }
            
            .item-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .item-row .right {
                text-align: left;
            }
        }
        
        /* No Data Message */
        .no-data {
            text-align: center;
            color: #6c757d;
            padding: 20px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="dashboard-page">
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-coins"></i> Cash-up / End of Day</h1>
                    <p><?= date('l, d F Y') ?> — <?= SITE_NAME ?? 'Linkspot' ?></p>
                </div>
                <div class="user-menu">
                    <div class="notification-badge">
                        <i class="fas fa-bell"></i>
                        <?php $unread = getUnreadNotificationsCount(); ?>
                        <?php if ($unread > 0): ?>
                        <span class="badge"><?= $unread ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 500;"><?= $_SESSION['full_name']; ?></div>
                            <div style="font-size: 12px; color: #6c757d;"><?= ucfirst($_SESSION['role']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="alertMessage" class="alert" style="display: none;"></div>

            <!-- Daily Summary Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Today's Summary — <?= date('d M Y') ?></h3>
                    <span class="badge badge-success">Daily Report</span>
                </div>
                
                <!-- Vouchers Section -->
                <div class="service-section">
                    <h3><i class="fas fa-wifi"></i> Internet Vouchers</h3>
                    
                    <?php if (!empty($voucherBreakdown)): ?>
                        <?php foreach ($voucherBreakdown as $voucher): ?>
                        <div class="item-row">
                            <div class="left">
                                <p><?= htmlspecialchars($voucher['voucher_type']) ?> x <?= $voucher['quantity'] ?></p>
                            </div>
                            <div class="right">
                                <p><strong>$<?= fmt($voucher['total_amount']) ?></strong></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item-row">
                            <div class="left">
                                <p>No voucher sales today</p>
                            </div>
                            <div class="right">
                                <p><strong>$0.00</strong></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-row total">
                        <div class="left">
                            <p>Total Vouchers Revenue</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyVouchers['total_voucher_sales']) ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="item-row">
                        <div class="left">
                            <p>Transactions</p>
                        </div>
                        <div class="right">
                            <p><strong><?= $dailyVouchers['transactions'] ?></strong></p>
                        </div>
                    </div>
                    <div class="item-row">
                        <div class="left">
                            <p>Cash Received</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyVouchers['cash_received_vouchers']) ?></strong></p>
                        </div>
                    </div>
                    <div class="item-row">
                        <div class="left">
                            <p>Change Given</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyVouchers['change_given_vouchers']) ?></strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- LinkSpot Spaces Section -->
                <div class="service-section">
                    <h3><i class="fas fa-desktop"></i> LinkSpot Spaces</h3>
                    
                    <?php if (!empty($linkspotMemberBreakdown)): ?>
                        <?php foreach ($linkspotMemberBreakdown as $member): ?>
                        <div class="item-row">
                            <div class="left">
                                <p><?= htmlspecialchars($member['member_name']) ?> x <?= $member['quantity'] ?></p>
                            </div>
                            <div class="right">
                                <p><strong>$<?= fmt($member['total_amount']) ?></strong></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item-row">
                            <div class="left">
                                <p>No LinkSpot payments today</p>
                            </div>
                            <div class="right">
                                <p><strong>$0.00</strong></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-row total">
                        <div class="left">
                            <p>LinkSpot Total Revenue</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyLinkspot['total_linkspot_payments']) ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="item-row">
                        <div class="left">
                            <p>Transactions</p>
                        </div>
                        <div class="right">
                            <p><strong><?= $dailyLinkspot['transactions'] ?></strong></p>
                        </div>
                    </div>
                    <div class="item-row">
                        <div class="left">
                            <p>Cash Payments</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyLinkspot['cash_linkspot']) ?></strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Summarcity Mall Section -->
                <div class="service-section">
                    <h3><i class="fas fa-store"></i> Summarcity Mall</h3>
                    
                    <?php if (!empty($mallShopBreakdown)): ?>
                        <?php foreach ($mallShopBreakdown as $shop): ?>
                        <div class="item-row">
                            <div class="left">
                                <p><?= htmlspecialchars($shop['shop_name']) ?> x <?= $shop['quantity'] ?></p>
                            </div>
                            <div class="right">
                                <p><strong>$<?= fmt($shop['total_amount']) ?></strong></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item-row">
                            <div class="left">
                                <p>No mall payments today</p>
                            </div>
                            <div class="right">
                                <p><strong>$0.00</strong></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-row total">
                        <div class="left">
                            <p>Mall Total Revenue</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyMall['total_mall_payments']) ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="item-row">
                        <div class="left">
                            <p>Transactions</p>
                        </div>
                        <div class="right">
                            <p><strong><?= $dailyMall['transactions'] ?></strong></p>
                        </div>
                    </div>
                    <div class="item-row">
                        <div class="left">
                            <p>Cash Payments</p>
                        </div>
                        <div class="right">
                            <p><strong>$<?= fmt($dailyMall['cash_mall']) ?></strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Totals -->
                <div class="totals-display">
                    <h3><i class="fas fa-chart-line"></i> Daily Totals</h3>
                    <div class="totals-grid">
                        <div class="total-item">
                            <h4>Total Revenue</h4>
                            <div class="total-amount">$<?= fmt($daily_total_revenue) ?></div>
                        </div>
                        <div class="total-item">
                            <h4>Total Cash In</h4>
                            <div class="total-amount">$<?= fmt($daily_total_cash_in) ?></div>
                        </div>
                        <div class="total-item">
                            <h4>Total Transactions</h4>
                            <div class="total-amount"><?= $dailyVouchers['transactions'] + $dailyLinkspot['transactions'] + $dailyMall['transactions'] ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Send Button -->
                <button id="sendToAdminBtn" class="btn btn-warning btn-lg" style="width: 100%; margin-top: 20px;">
                    <i class="fas fa-paper-plane"></i> Send to Admin (End of Day)
                </button>
            </div>
            
            <!-- Monthly Summary Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Monthly Summary — <?= date('F Y') ?></h3>
                    <span class="badge badge-info">Monthly Report</span>
                </div>
                
                <!-- Monthly Totals Grid -->
                <div class="totals-grid" style="margin: 20px 0;">
                    <div class="summary-box" style="text-align: center;">
                        <h4>Vouchers (Monthly)</h4>
                        <div style="font-size: 24px; font-weight: bold; color: var(--primary); margin: 10px 0;">
                            $<?= fmt($monthlyVouchers['total_voucher_sales']) ?>
                        </div>
                        <p><?= $monthlyVouchers['transactions'] ?> transactions</p>
                    </div>
                    
                    <div class="summary-box" style="text-align: center;">
                        <h4>LinkSpot (Monthly)</h4>
                        <div style="font-size: 24px; font-weight: bold; color: var(--primary); margin: 10px 0;">
                            $<?= fmt($monthlyLinkspot['total_linkspot_payments']) ?>
                        </div>
                        <p><?= $monthlyLinkspot['transactions'] ?> payments</p>
                    </div>
                    
                    <div class="summary-box" style="text-align: center;">
                        <h4>Mall (Monthly)</h4>
                        <div style="font-size: 24px; font-weight: bold; color: var(--primary); margin: 10px 0;">
                            $<?= fmt($monthlyMall['total_mall_payments']) ?>
                        </div>
                        <p><?= $monthlyMall['transactions'] ?> payments</p>
                    </div>
                </div>
                
                <!-- Monthly Totals -->
                <div class="totals-display" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3><i class="fas fa-chart-bar"></i> Monthly Totals</h3>
                    <div class="totals-grid">
                        <div class="total-item">
                            <h4>Total Monthly Revenue</h4>
                            <div class="total-amount">$<?= fmt($monthly_total_revenue) ?></div>
                        </div>
                        <div class="total-item">
                            <h4>Total Monthly Cash In</h4>
                            <div class="total-amount">$<?= fmt($monthly_total_cash_in) ?></div>
                        </div>
                        <div class="total-item">
                            <h4>Total Transactions</h4>
                            <div class="total-amount"><?= $monthlyVouchers['transactions'] + $monthlyLinkspot['transactions'] + $monthlyMall['transactions'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showAlert(message, type = 'success') {
        const alertDiv = document.getElementById('alertMessage');
        alertDiv.textContent = message;
        alertDiv.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
        alertDiv.style.display = 'block';
       
        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    }

    document.getElementById('sendToAdminBtn')?.addEventListener('click', function() {
        if (!confirm("Send today's cash-up summary to admin?")) return;

        const btn = this;
        const originalText = btn.innerHTML;
        const originalClass = btn.className;
       
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.className = 'btn btn-lg';

        const payload = {
            ajax: 'true',
            action: 'submit_cashup',
            daily_vouchers_revenue: <?= json_encode($dailyVouchers['total_voucher_sales']) ?>,
            daily_vouchers_cash_received: <?= json_encode($dailyVouchers['cash_received_vouchers']) ?>,
            daily_vouchers_change_given: <?= json_encode($dailyVouchers['change_given_vouchers']) ?>,
            daily_vouchers_transactions: <?= json_encode($dailyVouchers['transactions']) ?>,
            daily_linkspot_revenue: <?= json_encode($dailyLinkspot['total_linkspot_payments']) ?>,
            daily_linkspot_cash: <?= json_encode($dailyLinkspot['cash_linkspot']) ?>,
            daily_linkspot_transactions: <?= json_encode($dailyLinkspot['transactions']) ?>,
            daily_mall_revenue: <?= json_encode($dailyMall['total_mall_payments']) ?>,
            daily_mall_cash: <?= json_encode($dailyMall['cash_mall']) ?>,
            daily_mall_transactions: <?= json_encode($dailyMall['transactions']) ?>,
            grand_total_revenue: <?= json_encode($daily_total_revenue) ?>,
            grand_total_cash_in: <?= json_encode($daily_total_cash_in) ?>,
            notes: prompt("Optional note for admin:") || ""
        };

        fetch('cashup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload)
        })
        .then(r => {
            if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
            return r.json();
        })
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                btn.innerHTML = '<i class="fas fa-check"></i> Sent Successfully!';
                btn.className = 'btn btn-success btn-lg';
                btn.disabled = true;
               
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showAlert("Error: " + (data.message || "Unknown error"), 'danger');
                btn.innerHTML = originalText;
                btn.className = originalClass;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showAlert("Network/Request error: " + err.message, 'danger');
            btn.innerHTML = originalText;
            btn.className = originalClass;
            btn.disabled = false;
        });
    });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>