<?php
// settings.php - System Settings Management
require_once 'config.php';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_settings':
            // Get all system settings
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE branch_id IS NULL ORDER BY setting_key");
            $stmt->execute();
            $result = $stmt->get_result();
            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
            }
            
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        case 'save_setting':
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if (empty($key)) {
                echo json_encode(['success' => false, 'message' => 'Setting key is required']);
                break;
            }
            
            // Convert value to JSON
            $jsonValue = json_encode($value);
            
            // Check if setting exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = ? AND branch_id IS NULL");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                // Update existing setting
                $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ? AND branch_id IS NULL");
                $stmt->bind_param("ss", $jsonValue, $key);
            } else {
                // Insert new setting
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, branch_id) VALUES (?, ?, NULL)");
                $stmt->bind_param("ss", $key, $jsonValue);
            }
            
            if ($stmt->execute()) {
                // Add activity log
                addActivityLog('System Settings', "Updated setting: $key", "Admin action");
                
                echo json_encode(['success' => true, 'message' => 'Setting saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save setting']);
            }
            break;
            
        case 'save_settings_bulk':
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                echo json_encode(['success' => false, 'message' => 'No settings provided']);
                break;
            }
            
            $db->begin_transaction();
            try {
                foreach ($settings as $key => $value) {
                    $jsonValue = json_encode($value);
                    
                    // Check if setting exists
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = ? AND branch_id IS NULL");
                    $stmt->bind_param("s", $key);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    if ($row['count'] > 0) {
                        // Update
                        $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ? AND branch_id IS NULL");
                        $stmt->bind_param("ss", $jsonValue, $key);
                    } else {
                        // Insert
                        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, branch_id) VALUES (?, ?, NULL)");
                        $stmt->bind_param("ss", $key, $jsonValue);
                    }
                    $stmt->execute();
                }
                
                // Add activity log
                addActivityLog('System Settings', "Updated multiple system settings", "Admin action");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
    }
    exit;
}

// Default settings structure
$defaultSettings = [
    'general' => [
        'site_name' => SITE_NAME,
        'site_title' => 'LinkSpot Management System',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'items_per_page' => 25,
        'enable_registration' => true,
        'maintenance_mode' => false
    ],
    'business' => [
        'business_name' => 'LinkSpot',
        'address' => '123 Business Street, City, Country',
        'phone' => '+1 234 567 890',
        'email' => 'info@linkspot.com',
        'website' => 'https://linkspot.com',
        'tax_rate' => 0.15,
        'vat_number' => 'VAT123456789'
    ],
    'vouchers' => [
        'auto_cleanup_days' => 30,
        'default_expiry_days' => 365,
        'enable_batch_import' => true,
        'max_batch_size' => 100,
        'low_stock_threshold' => 10
    ],
    'linkspot' => [
        'default_monthly_rate' => 65,
        'grace_period_days' => 7,
        'auto_suspend_days' => 30,
        'enable_auto_reminders' => true,
        'reminder_days_before' => 3
    ],
    'summarcity' => [
        'default_rent_reminder_days' => 5,
        'late_fee_percentage' => 0.05,
        'enable_auto_rent_calculation' => true,
        'shop_status_check_interval' => 30
    ],
    'notifications' => [
        'email_notifications' => true,
        'sms_notifications' => false,
        'push_notifications' => true,
        'notification_sound' => true,
        'desktop_notifications' => true
    ],
    'backup' => [
        'auto_backup' => true,
        'backup_frequency' => 'daily',
        'keep_backups_days' => 30,
        'backup_location' => 'local',
        'cloud_backup_enabled' => false
    ],
    'security' => [
        'login_attempts' => 5,
        'lockout_minutes' => 15,
        'password_expiry_days' => 90,
        'force_password_change' => false,
        'two_factor_auth' => false,
        'session_timeout' => 30
    ],
    'printing' => [
        'receipt_printer_name' => 'Default Printer',
        'receipt_width' => 80,
        'print_header' => true,
        'print_footer' => true,
        'auto_print_receipts' => true,
        'print_copies' => 1
    ],
    'api' => [
        'enable_api' => false,
        'api_rate_limit' => 100,
        'api_key_expiry_days' => 365,
        'enable_webhooks' => false,
        'webhook_url' => ''
    ]
];
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
        
        /* Settings Container */
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            padding: 10px;
        }
        
        .tab-btn {
            padding: 12px 25px;
            background: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        
        .tab-btn:hover {
            background: #f8f9fa;
            color: var(--secondary);
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Settings Card */
        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 20px;
            color: var(--secondary);
            margin: 0;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        /* Settings Sections */
        .settings-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        /* Setting Item */
        .setting-item {
            margin-bottom: 20px;
        }
        
        .setting-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }
        
        .setting-description {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #6c757d;
            line-height: 1.4;
        }
        
        /* Form Controls */
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
        
        .form-control-sm {
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--primary);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        /* Color Picker */
        .color-picker {
            width: 60px;
            height: 40px;
            padding: 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
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
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            justify-content: flex-end;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid var(--warning);
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid var(--info);
        }
        
        /* System Info */
        .system-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            padding: 10px;
            background: white;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
            font-size: 14px;
            color: var(--secondary);
        }
        
        /* Danger Zone */
        .danger-zone {
            background: #f8d7da;
            border: 2px solid var(--danger);
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .danger-zone h4 {
            color: var(--danger);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            
            .settings-tabs {
                flex-direction: column;
                overflow-x: visible;
            }
            
            .tab-btn {
                justify-content: flex-start;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
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
                    <h1><i class="fas fa-cogs"></i> System Settings</h1>
                    <p>Configure and manage system-wide settings and preferences</p>
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
            
            <!-- Alert Container -->
            <div id="alertContainer"></div>
            
            <!-- Settings Tabs -->
            <div class="settings-tabs" id="settingsTabs">
                <button class="tab-btn active" data-tab="general">
                    <i class="fas fa-cog"></i> General
                </button>
                <button class="tab-btn" data-tab="business">
                    <i class="fas fa-building"></i> Business
                </button>
                <button class="tab-btn" data-tab="vouchers">
                    <i class="fas fa-wifi"></i> Vouchers
                </button>
                <button class="tab-btn" data-tab="linkspot">
                    <i class="fas fa-desktop"></i> LinkSpot
                </button>
                <button class="tab-btn" data-tab="summarcity">
                    <i class="fas fa-store"></i> Summarcity
                </button>
                <button class="tab-btn" data-tab="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </button>
                <button class="tab-btn" data-tab="security">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button class="tab-btn" data-tab="printing">
                    <i class="fas fa-print"></i> Printing
                </button>
            </div>
            
            <!-- Settings Container -->
            <div class="settings-container">
                <!-- General Settings -->
                <div class="settings-card" id="general-tab">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <h3 class="card-title">General Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Site Information</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Site Name *</label>
                                <input type="text" class="form-control" id="site_name" placeholder="Enter site name">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Site Title</label>
                                <input type="text" class="form-control" id="site_title" placeholder="Enter site title">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Default Currency</label>
                                <select class="form-select" id="currency">
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                    <option value="ZAR">ZAR - South African Rand</option>
                                    <option value="ZWL">ZWL - Zimbabwe Dollar</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Date & Time</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Timezone</label>
                                <select class="form-select" id="timezone">
                                    <option value="UTC">UTC</option>
                                    <option value="Africa/Harare">Africa/Harare</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="Europe/London">Europe/London</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo</option>
                                </select>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Date Format</label>
                                <select class="form-select" id="date_format">
                                    <option value="d/m/Y">DD/MM/YYYY</option>
                                    <option value="m/d/Y">MM/DD/YYYY</option>
                                    <option value="Y-m-d">YYYY-MM-DD</option>
                                    <option value="d-M-Y">DD-MMM-YYYY</option>
                                </select>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Time Format</label>
                                <select class="form-select" id="time_format">
                                    <option value="H:i">24-hour (14:30)</option>
                                    <option value="h:i A">12-hour (2:30 PM)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Display Settings</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Items Per Page</label>
                                <input type="number" class="form-control" id="items_per_page" min="10" max="100" value="25">
                                <span class="setting-description">Number of items to display per page in lists</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="enable_registration">
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Enable User Registration
                                </label>
                                <span class="setting-description">Allow new users to register accounts</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="maintenance_mode">
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Maintenance Mode
                                </label>
                                <span class="setting-description">Enable maintenance mode (site will be unavailable)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Business Settings -->
                <div class="settings-card" id="business-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="card-title">Business Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Company Information</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Business Name</label>
                                <input type="text" class="form-control" id="business_name" placeholder="Enter business name">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Address</label>
                                <textarea class="form-control" id="address" rows="3" placeholder="Enter business address"></textarea>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Phone Number</label>
                                <input type="tel" class="form-control" id="business_phone" placeholder="Enter phone number">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Email Address</label>
                                <input type="email" class="form-control" id="business_email" placeholder="Enter email address">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Website</label>
                                <input type="url" class="form-control" id="website" placeholder="https://example.com">
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Tax & Financial</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" id="tax_rate" min="0" max="100" step="0.01" placeholder="15">
                                <span class="setting-description">Default tax rate for all transactions</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">VAT Number</label>
                                <input type="text" class="form-control" id="vat_number" placeholder="Enter VAT number">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vouchers Settings -->
                <div class="settings-card" id="vouchers-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <h3 class="card-title">Voucher Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Voucher Management</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Auto Cleanup Days</label>
                                <input type="number" class="form-control" id="auto_cleanup_days" min="1" max="365" value="30">
                                <span class="setting-description">Automatically delete unused vouchers after this many days</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Default Expiry Days</label>
                                <input type="number" class="form-control" id="default_expiry_days" min="1" max="365" value="365">
                                <span class="setting-description">Default validity period for new vouchers</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Low Stock Threshold</label>
                                <input type="number" class="form-control" id="low_stock_threshold" min="1" max="100" value="10">
                                <span class="setting-description">Show low stock warning when voucher count drops below this number</span>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Import Settings</h4>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="enable_batch_import" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Enable Batch Import
                                </label>
                                <span class="setting-description">Allow importing vouchers in batches</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Max Batch Size</label>
                                <input type="number" class="form-control" id="max_batch_size" min="1" max="1000" value="100">
                                <span class="setting-description">Maximum number of vouchers per import batch</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- LinkSpot Settings -->
                <div class="settings-card" id="linkspot-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <h3 class="card-title">LinkSpot Space Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Membership Settings</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Default Monthly Rate ($)</label>
                                <input type="number" class="form-control" id="default_monthly_rate" min="0" step="0.01" value="65.00">
                                <span class="setting-description">Default monthly rate for LinkSpot spaces</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Grace Period (Days)</label>
                                <input type="number" class="form-control" id="grace_period_days" min="0" max="30" value="7">
                                <span class="setting-description">Number of days after due date before late fees apply</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Auto Suspend (Days)</label>
                                <input type="number" class="form-control" id="auto_suspend_days" min="1" max="90" value="30">
                                <span class="setting-description">Automatically suspend members after this many days overdue</span>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Notifications</h4>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="enable_auto_reminders" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Enable Automatic Reminders
                                </label>
                                <span class="setting-description">Send automatic payment reminders to members</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Reminder Days Before</label>
                                <input type="number" class="form-control" id="reminder_days_before" min="1" max="30" value="3">
                                <span class="setting-description">Send reminders this many days before due date</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summarcity Settings -->
                <div class="settings-card" id="summarcity-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <h3 class="card-title">Summarcity Mall Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Rent Management</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Rent Reminder Days</label>
                                <input type="number" class="form-control" id="default_rent_reminder_days" min="1" max="30" value="5">
                                <span class="setting-description">Days before due date to send rent reminders</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Late Fee Percentage (%)</label>
                                <input type="number" class="form-control" id="late_fee_percentage" min="0" max="100" step="0.01" value="5">
                                <span class="setting-description">Percentage of rent charged as late fee</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="enable_auto_rent_calculation" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Enable Auto Rent Calculation
                                </label>
                                <span class="setting-description">Automatically calculate next month's rent</span>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Shop Management</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Shop Status Check Interval (Days)</label>
                                <input type="number" class="form-control" id="shop_status_check_interval" min="1" max="90" value="30">
                                <span class="setting-description">Check and update shop status automatically</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Settings -->
                <div class="settings-card" id="notifications-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="card-title">Notification Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Notification Channels</h4>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="email_notifications" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Email Notifications
                                </label>
                                <span class="setting-description">Send notifications via email</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="sms_notifications">
                                        <span class="toggle-slider"></span>
                                    </div>
                                    SMS Notifications
                                </label>
                                <span class="setting-description">Send notifications via SMS (requires SMS gateway)</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="push_notifications" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Push Notifications
                                </label>
                                <span class="setting-description">Show push notifications in the browser</span>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Alert Settings</h4>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="notification_sound" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Notification Sound
                                </label>
                                <span class="setting-description">Play sound for new notifications</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="desktop_notifications" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Desktop Notifications
                                </label>
                                <span class="setting-description">Show desktop notifications (requires permission)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="settings-card" id="security-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="card-title">Security Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Login Security</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Max Login Attempts</label>
                                <input type="number" class="form-control" id="login_attempts" min="1" max="10" value="5">
                                <span class="setting-description">Number of failed attempts before lockout</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Lockout Duration (Minutes)</label>
                                <input type="number" class="form-control" id="lockout_minutes" min="1" max="1440" value="15">
                                <span class="setting-description">How long to lock out after too many failed attempts</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="two_factor_auth">
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Two-Factor Authentication
                                </label>
                                <span class="setting-description">Require 2FA for admin login</span>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Password Policy</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Password Expiry (Days)</label>
                                <input type="number" class="form-control" id="password_expiry_days" min="1" max="365" value="90">
                                <span class="setting-description">Number of days before password expires</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="force_password_change">
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Force Password Change
                                </label>
                                <span class="setting-description">Require password change on first login</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Session Timeout (Minutes)</label>
                                <input type="number" class="form-control" id="session_timeout" min="1" max="480" value="30">
                                <span class="setting-description">Auto-logout after this many minutes of inactivity</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Printing Settings -->
                <div class="settings-card" id="printing-tab" style="display: none;">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-print"></i>
                        </div>
                        <h3 class="card-title">Printing Settings</h3>
                    </div>
                    
                    <div class="settings-grid">
                        <div class="settings-section">
                            <h4 class="section-title">Printer Configuration</h4>
                            
                            <div class="setting-item">
                                <label class="setting-label">Receipt Printer Name</label>
                                <input type="text" class="form-control" id="receipt_printer_name" placeholder="Enter printer name">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Receipt Width (Characters)</label>
                                <input type="number" class="form-control" id="receipt_width" min="40" max="120" value="80">
                                <span class="setting-description">Number of characters per line on receipt</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Print Copies</label>
                                <input type="number" class="form-control" id="print_copies" min="1" max="5" value="1">
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h4 class="section-title">Print Options</h4>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="print_header" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Print Header
                                </label>
                                <span class="setting-description">Include business header on receipts</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="print_footer" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Print Footer
                                </label>
                                <span class="setting-description">Include footer information on receipts</span>
                            </div>
                            
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="auto_print_receipts" checked>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    Auto-Print Receipts
                                </label>
                                <span class="setting-description">Automatically print receipts after transaction</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="settings-card">
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" onclick="saveSettings()">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetSettings()">
                            <i class="fas fa-redo"></i> Reset to Defaults
                        </button>
                        <button type="button" class="btn btn-success" onclick="loadSettings()">
                            <i class="fas fa-sync"></i> Reload Settings
                        </button>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3 class="card-title">System Information</h3>
                    </div>
                    
                    <div class="system-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">PHP Version</div>
                                <div class="info-value"><?php echo phpversion(); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Database</div>
                                <div class="info-value">MySQL</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Server Time</div>
                                <div class="info-value"><?php echo date('Y-m-d H:i:s'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Timezone</div>
                                <div class="info-value"><?php echo date_default_timezone_get(); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Memory Usage</div>
                                <div class="info-value"><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Max Upload Size</div>
                                <div class="info-value"><?php echo ini_get('upload_max_filesize'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h4><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                    <p style="color: #721c24; margin-bottom: 15px;">
                        These actions are irreversible. Proceed with caution.
                    </p>
                    <div style="display: flex; gap: 15px;">
                        <button type="button" class="btn btn-danger" onclick="clearCache()">
                            <i class="fas fa-trash-alt"></i> Clear System Cache
                        </button>
                        <button type="button" class="btn btn-danger" onclick="backupDatabase()">
                            <i class="fas fa-database"></i> Backup Database
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadSettings();
            setupTabs();
        });
        
        // Tab Navigation
        function setupTabs() {
            const tabs = document.querySelectorAll('.tab-btn');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all tab content
                    document.querySelectorAll('.settings-card[id$="-tab"]').forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    // Show selected tab content
                    const tabId = this.dataset.tab;
                    document.getElementById(`${tabId}-tab`).style.display = 'block';
                });
            });
        }
        
        // Load settings from server
        function loadSettings() {
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_settings'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const settings = data.data;
                    
                    // Helper function to set form value
                    function setValue(id, value) {
                        const element = document.getElementById(id);
                        if (!element) return;
                        
                        if (element.type === 'checkbox') {
                            element.checked = !!value;
                        } else {
                            element.value = value || '';
                        }
                    }
                    
                    // Set values for all settings
                    <?php foreach ($defaultSettings as $category => $categorySettings): ?>
                        <?php foreach ($categorySettings as $key => $defaultValue): ?>
                            const <?php echo $key; ?>Value = settings['<?php echo $key; ?>'] || '<?php echo $defaultValue; ?>';
                            setValue('<?php echo $key; ?>', <?php echo $key; ?>Value);
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    
                    showAlert('Settings loaded successfully', 'success');
                }
            })
            .catch(error => {
                showAlert('Failed to load settings', 'danger');
            });
        }
        
        // Save settings
        function saveSettings() {
            // Collect all settings
            const settings = {};
            
            // Helper function to get form value
            function getValue(id) {
                const element = document.getElementById(id);
                if (!element) return null;
                
                if (element.type === 'checkbox') {
                    return element.checked;
                } else if (element.type === 'number') {
                    return element.value ? parseFloat(element.value) : null;
                } else {
                    return element.value || null;
                }
            }
            
            // Get values for all settings
            <?php foreach ($defaultSettings as $category => $categorySettings): ?>
                <?php foreach ($categorySettings as $key => $defaultValue): ?>
                    const <?php echo $key; ?>Value = getValue('<?php echo $key; ?>');
                    if (<?php echo $key; ?>Value !== null) {
                        settings['<?php echo $key; ?>'] = <?php echo $key; ?>Value;
                    }
                <?php endforeach; ?>
            <?php endforeach; ?>
            
            // Send to server
            const formData = new FormData();
            formData.append('ajax', 'true');
            formData.append('action', 'save_settings_bulk');
            formData.append('settings', JSON.stringify(settings));
            
            // Show loading
            const saveBtn = document.querySelector('.btn-primary');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;
            
            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                
                if (data.success) {
                    showAlert('Settings saved successfully', 'success');
                } else {
                    showAlert(data.message || 'Failed to save settings', 'danger');
                }
            })
            .catch(error => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                showAlert('Network error. Please try again.', 'danger');
            });
        }
        
        // Reset settings to defaults
        function resetSettings() {
            if (confirm('Are you sure you want to reset all settings to default values? This action cannot be undone.')) {
                <?php foreach ($defaultSettings as $category => $categorySettings): ?>
                    <?php foreach ($categorySettings as $key => $defaultValue): ?>
                        const <?php echo $key; ?>Element = document.getElementById('<?php echo $key; ?>');
                        if (<?php echo $key; ?>Element) {
                            if (<?php echo $key; ?>Element.type === 'checkbox') {
                                <?php echo $key; ?>Element.checked = <?php echo json_encode($defaultValue); ?>;
                            } else {
                                <?php echo $key; ?>Element.value = <?php echo json_encode($defaultValue); ?>;
                            }
                        }
                    <?php endforeach; ?>
                <?php endforeach; ?>
                
                showAlert('Settings reset to defaults', 'warning');
            }
        }
        
        // Clear system cache
        function clearCache() {
            if (confirm('Are you sure you want to clear the system cache? This may temporarily affect performance.')) {
                showAlert('Cache cleared successfully', 'success');
            }
        }
        
        // Backup database
        function backupDatabase() {
            if (confirm('This will create a backup of the entire database. Continue?')) {
                showAlert('Database backup started...', 'info');
                // In a real application, this would trigger a server-side backup process
            }
        }
        
        // Show alert message
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            
            const alertClass = {
                'success': 'alert-success',
                'danger': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type];
            
            const alertIcon = {
                'success': 'fa-check-circle',
                'danger': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            }[type];
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${alertIcon}"></i>
                    <div>${message}</div>
                </div>
            `;
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
        
        // Auto-save when leaving page
        window.addEventListener('beforeunload', function(e) {
            // Check if there are unsaved changes
            let hasUnsavedChanges = false;
            
            <?php foreach ($defaultSettings as $category => $categorySettings): ?>
                <?php foreach ($categorySettings as $key => $defaultValue): ?>
                    const <?php echo $key; ?>Element = document.getElementById('<?php echo $key; ?>');
                    if (<?php echo $key; ?>Element) {
                        let currentValue;
                        if (<?php echo $key; ?>Element.type === 'checkbox') {
                            currentValue = <?php echo $key; ?>Element.checked;
                        } else {
                            currentValue = <?php echo $key; ?>Element.value;
                        }
                        if (currentValue != <?php echo json_encode($defaultValue); ?>) {
                            hasUnsavedChanges = true;
                        }
                    }
                <?php endforeach; ?>
            <?php endforeach; ?>
            
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>