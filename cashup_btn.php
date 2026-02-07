<?php
// cashup.php - SIMPLIFIED VERSION - Cashier View
// February 2026

// ────────────────────────────────────────────────────────────────────────────────
// AJAX HANDLER - This must be at the VERY TOP to prevent any output interference
// ────────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['action'] === 'submit_cashup') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Completely clean all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
   
    // Set JSON header immediately
    header('Content-Type: application/json; charset=utf-8');
    session_start();
   
    // Temporarily suppress ALL PHP errors/warnings/output (production)
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
   
    // Initialize response
    $response = ['success' => false, 'message' => 'Unknown error'];
   
    try {
        // Simple direct database connection for AJAX
        // require_once 'config_simple.php';
        require_once 'config.php';
       
        // $db = new mysqli("localhost", "root", "", "linkspot_db");
        $db = getDB();
        if ($db->connect_error) {
            throw new Exception("Database connection failed: " . $db->connect_error);
        }
       
        // Get user info from session
        session_start();
        $userId   = (int) ($_SESSION['user_id']   ?? 0);
        $userName =       $_SESSION['full_name']  ?? 'Unknown User';
       
        // Get POST data with safe defaults
        $notes = trim($_POST['notes'] ?? '');
       
        // Calculate today's totals
        $today = date('Y-m-d');
        
        // Get voucher sales for today
        $vStmt = $db->prepare("SELECT 
            COUNT(*) as transactions,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(amount_received), 0) as cash_received,
            COALESCE(SUM(change_amount), 0) as change_given
            FROM voucher_sales WHERE sale_date = ?");
        $vStmt->bind_param("s", $today);
        $vStmt->execute();
        $vResult = $vStmt->get_result();
        $vData = $vResult->fetch_assoc();
        
        $vRev = floatval($vData['total_amount'] ?? 0);
        $vCash = floatval($vData['cash_received'] ?? 0);
        $vChange = floatval($vData['change_given'] ?? 0);
        $vTrans = intval($vData['transactions'] ?? 0);
        $vStmt->close();
        
        // Get LinkSpot payments for today
        $lStmt = $db->prepare("SELECT 
            COUNT(*) as transactions,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) as cash_amount
            FROM linkspot_payments WHERE payment_date = ?");
        $lStmt->bind_param("s", $today);
        $lStmt->execute();
        $lResult = $lStmt->get_result();
        $lData = $lResult->fetch_assoc();
        
        $lRev = floatval($lData['total_amount'] ?? 0);
        $lCash = floatval($lData['cash_amount'] ?? 0);
        $lTrans = intval($lData['transactions'] ?? 0);
        $lStmt->close();
        
        // Get mall payments for today
        $mStmt = $db->prepare("SELECT 
            COUNT(*) as transactions,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) as cash_amount
            FROM mall_payments WHERE payment_date = ?");
        $mStmt->bind_param("s", $today);
        $mStmt->execute();
        $mResult = $mStmt->get_result();
        $mData = $mResult->fetch_assoc();
        
        $mRev = floatval($mData['total_amount'] ?? 0);
        $mCash = floatval($mData['cash_amount'] ?? 0);
        $mTrans = intval($mData['transactions'] ?? 0);
        $mStmt->close();
        
        // Calculate grand totals
        $gtRev = $vRev + $lRev + $mRev;
        $gtCash = $vCash + $lCash + $mCash;
        
        // Check if already submitted for today
        $checkStmt = $db->prepare("SELECT id FROM cashup_submissions WHERE submission_date = ? AND submitted_by_user_id = ?");
        $checkStmt->bind_param("si", $today, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE cashup_submissions SET
                daily_vouchers_revenue = ?,
                daily_vouchers_cash_received = ?,
                daily_vouchers_change_given = ?,
                daily_vouchers_transactions = ?,
                daily_linkspot_revenue = ?,
                daily_linkspot_cash = ?,
                daily_linkspot_transactions = ?,
                daily_mall_revenue = ?,
                daily_mall_cash = ?,
                daily_mall_transactions = ?,
                grand_total_revenue = ?,
                grand_total_cash_in = ?,
                notes = ?,
                status = 'new',
                submitted_at = CURRENT_TIMESTAMP
                WHERE submission_date = ? AND submitted_by_user_id = ?";
                
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }
            
            $stmt->bind_param(
                "dddididididdssi",
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
                $notes,
                $today,
                $userId
            );
        } else {
            // Insert new record
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
                notes,
                status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new'
            )";
            
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }
            
            $stmt->bind_param(
                "sisdddididididds",
                $today,
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
        }
       
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
       
        $submissionId = $stmt->insert_id ?: 'updated';
       
        // Log activity
        $logMsg = "Daily cash-up sent by $userName (ID: $submissionId)";
        @file_put_contents(__DIR__ . '/activity.log', date('[Y-m-d H:i:s] ') . $logMsg . "\n", FILE_APPEND);
       
        $response = [
            'success'      => true,
            'message'      => 'Cash-up summary sent successfully to admin!',
            'submission_id' => $submissionId
        ];
       
        $stmt->close();
        $db->close();
       
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
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

// Check if already submitted today
$today = date('Y-m-d');
$alreadySubmitted = false;
try {
    $checkStmt = $db->prepare("SELECT id, submitted_at FROM cashup_submissions WHERE submission_date = ? AND submitted_by_user_id = ?");
    $checkStmt->bind_param("si", $today, $user['id']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $alreadySubmitted = $result->num_rows > 0;
    $submissionData = $result->fetch_assoc();
    $checkStmt->close();
} catch (Exception $e) {
    // Ignore error
}

// ────────────────────────────────────────────────
// HTML OUTPUT - SIMPLIFIED CASHIER VIEW
// ────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - End of Day Cash-up</title>
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
            text-align: center;
        }
        
        .card-header {
            display: flex;
            justify-content: center;
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
        
        /* Info Box */
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        /* Buttons */
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
            min-width: 200px;
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
        
        .btn-disabled {
            background: #6c757d;
            color: white;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .btn-lg {
            padding: 18px 40px;
            font-size: 18px;
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
        
        /* Icon */
        .cashup-icon {
            font-size: 60px;
            color: var(--primary);
            margin: 20px 0;
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
            
            .btn {
                width: 100%;
                min-width: auto;
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
                    <h1><i class="fas fa-coins"></i> End of Day Cash-up</h1>
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

            <!-- Simple Cash-up Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Submit Daily Cash-up</h3>
                </div>
                
                <div class="cashup-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                
                <h2 style="color: var(--secondary); margin-bottom: 15px;">
                    <?= $alreadySubmitted ? 'Update Cash-up' : 'Submit Cash-up' ?>
                </h2>
                
                <p style="color: #6c757d; margin-bottom: 25px; line-height: 1.6;">
                    <?php if ($alreadySubmitted): ?>
                        You have already submitted your cash-up for today at 
                        <strong><?= date('H:i', strtotime($submissionData['submitted_at'])) ?></strong>.<br>
                        You can update it if needed.
                    <?php else: ?>
                        Click the button below to submit your end-of-day cash-up summary to the admin.
                    <?php endif; ?>
                </p>
                
                <div class="info-box">
                    <div class="info-item">
                        <span><i class="fas fa-calendar-day"></i> Date:</span>
                        <strong><?= date('d M Y') ?></strong>
                    </div>
                    <div class="info-item">
                        <span><i class="fas fa-user"></i> Cashier:</span>
                        <strong><?= $_SESSION['full_name'] ?></strong>
                    </div>
                    <div class="info-item">
                        <span><i class="fas fa-clock"></i> Time:</span>
                        <strong><?= date('H:i:s') ?></strong>
                    </div>
                </div>
                
                <div style="margin: 30px 0;">
                    <button id="submitCashupBtn" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> 
                        <?= $alreadySubmitted ? 'Update Cash-up to Admin' : 'Submit Cash-up to Admin' ?>
                    </button>
                </div>
                
                <div style="color: #6c757d; font-size: 14px; margin-top: 20px;">
                    <p><i class="fas fa-info-circle"></i> This will automatically calculate and send today's totals to the admin.</p>
                    <?php if ($alreadySubmitted): ?>
                        <p class="alert alert-info" style="margin-top: 15px;">
                            <i class="fas fa-sync-alt"></i> Updating will replace your previous submission.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Stats Card -->
            <div class="card" style="text-align: left;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-bar"></i> What Gets Submitted</h3>
                </div>
                
                <div class="info-box" style="background: #f0f8ff;">
                    <div class="info-item">
                        <span><i class="fas fa-wifi"></i> Internet Vouchers</span>
                        <span>Today's sales total</span>
                    </div>
                    <div class="info-item">
                        <span><i class="fas fa-desktop"></i> LinkSpot Spaces</span>
                        <span>Today's payments</span>
                    </div>
                    <div class="info-item">
                        <span><i class="fas fa-store"></i> Summarcity Mall</span>
                        <span>Today's payments</span>
                    </div>
                    <div class="info-item" style="font-weight: 600; color: var(--primary);">
                        <span><i class="fas fa-calculator"></i> Grand Totals</span>
                        <span>Revenue & Cash received</span>
                    </div>
                </div>
                
                <p style="text-align: center; margin-top: 15px; color: #6c757d;">
                    <i class="fas fa-eye"></i> View all submissions at: 
                    <a href="admin-cashup-view.php" target="_blank" style="color: var(--primary); text-decoration: none;">
                        admin-cashup-view.php
                    </a>
                </p>
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

    document.getElementById('submitCashupBtn')?.addEventListener('click', function() {
        const action = <?= $alreadySubmitted ? "'update'" : "'submit'" ?>;
        const message = action === 'update' 
            ? "Update today's cash-up summary to admin?" 
            : "Submit today's cash-up summary to admin?";
            
        if (!confirm(message)) return;

        const btn = this;
        const originalText = btn.innerHTML;
        const originalClass = btn.className;
       
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.className = 'btn btn-disabled btn-lg';

        const notes = prompt("Optional note for admin (press Cancel for none):", "");
        
        const payload = {
            ajax: 'true',
            action: 'submit_cashup',
            notes: notes || ""
        };

        fetch('cashup_btn.php', {
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
                btn.innerHTML = '<i class="fas fa-check"></i> ' + 
                    (action === 'update' ? 'Updated Successfully!' : 'Submitted Successfully!');
                btn.className = 'btn btn-success btn-lg';
                btn.disabled = true;
               
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
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
    
    // Auto-update time every second
    setInterval(() => {
        const timeElements = document.querySelectorAll('.info-item:nth-child(3) strong');
        timeElements.forEach(el => {
            const now = new Date();
            el.textContent = now.toLocaleTimeString();
        });
    }, 1000);
    </script>
</body>
</html>
<?php
ob_end_flush();
?>