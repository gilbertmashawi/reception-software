<?php
// admin_profile.php - Admin User Profile Management
require_once 'config.php';
$db = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_profile':
            // Get current user profile (in real app, use session user ID)
            $userId = 1; // Dummy admin ID
            
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $profile = $result->fetch_assoc();
            
            echo json_encode(['success' => true, 'data' => $profile]);
            break;
            
        case 'update_profile':
            $fullName = $_POST['full_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $userId = 1; // Dummy admin ID
            
            $errors = [];
            
            // Validate inputs
            if (empty($fullName)) {
                $errors[] = 'Full name is required';
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email is required';
            }
            
            // Check if password change requested
            $passwordChange = !empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword);
            
            if ($passwordChange) {
                if (empty($currentPassword)) {
                    $errors[] = 'Current password is required for password change';
                }
                if (empty($newPassword)) {
                    $errors[] = 'New password is required';
                }
                if (empty($confirmPassword)) {
                    $errors[] = 'Confirm password is required';
                }
                if ($newPassword !== $confirmPassword) {
                    $errors[] = 'New password and confirm password do not match';
                }
                if (strlen($newPassword) < 6) {
                    $errors[] = 'New password must be at least 6 characters';
                }
                
                // Verify current password
                if (empty($errors)) {
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    
                    // For demo, using simple check. In real app, use password_verify()
                    if ($currentPassword !== 'admin123' && $currentPassword !== 'reception') {
                        $errors[] = 'Current password is incorrect';
                    }
                }
            }
            
            if (empty($errors)) {
                $db->begin_transaction();
                try {
                    if ($passwordChange) {
                        // Update with new password (in real app, hash the password)
                        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $fullName, $email, $phone, $newPasswordHash, $userId);
                    } else {
                        // Update without password
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                        $stmt->bind_param("sssi", $fullName, $email, $phone, $userId);
                    }
                    
                    $stmt->execute();
                    
                    // Add activity log
                    addActivityLog('Admin Profile', "Updated profile information", "Admin action");
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'errors' => $errors]);
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
    <title><?php echo SITE_NAME; ?> - Admin Profile</title>
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
        
        /* Profile Container */
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
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
            margin-bottom: 25px;
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
        
        /* Profile Info */
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(39, 174, 96, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .info-content h4 {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-content p {
            font-size: 16px;
            font-weight: 500;
            color: var(--secondary);
            margin: 0;
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
            font-size: 14px;
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
        
        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #6c757d;
        }
        
        /* Form Row */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Password Section */
        .password-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid var(--info);
        }
        
        .password-section h4 {
            color: var(--info);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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
        
        /* Activity Log */
        .activity-log {
            margin-top: 30px;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(39, 174, 96, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .activity-content h5 {
            font-size: 14px;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .activity-content p {
            font-size: 13px;
            color: #6c757d;
            margin: 0;
        }
        
        .activity-time {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
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
            
            .profile-info {
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
                    <h1><i class="fas fa-user-cog"></i> Admin Profile</h1>
                    <p>Manage your account settings and preferences</p>
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
            
            <!-- Profile Container -->
            <div class="profile-container">
                <!-- Profile Info Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3 class="card-title">Profile Information</h3>
                    </div>
                    
                    <div id="profileInfo">
                        <!-- Profile info will be loaded here -->
                    </div>
                </div>
                
                <!-- Edit Profile Form -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3 class="card-title">Edit Profile</h3>
                    </div>
                    
                    <form id="profileForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" id="username" class="form-control" disabled>
                                <small class="form-text">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Password Section -->
                        <div class="password-section">
                            <h4><i class="fas fa-lock"></i> Change Password (Optional)</h4>
                            <p style="font-size: 13px; color: #6c757d; margin-bottom: 20px;">
                                Leave blank if you don't want to change password
                            </p>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control">
                                    <small class="form-text">Minimum 6 characters</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-secondary" onclick="loadProfile()">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    
                    <div class="activity-log">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <div class="activity-content">
                                <h5>Profile Updated</h5>
                                <p>You updated your profile information</p>
                                <div class="activity-time">Today, 10:30 AM</div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="activity-content">
                                <h5>Login</h5>
                                <p>You logged into the system</p>
                                <div class="activity-time">Today, 09:15 AM</div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="activity-content">
                                <h5>Settings Updated</h5>
                                <p>You updated system preferences</p>
                                <div class="activity-time">Yesterday, 03:45 PM</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadProfile();
            
            // Form submission
            document.getElementById('profileForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updateProfile();
            });
        });
        
        // Load profile data
        function loadProfile() {
            fetch('admin_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=true&action=get_profile'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const profile = data.data;
                    
                    // Update form fields
                    document.getElementById('full_name').value = profile.full_name || '';
                    document.getElementById('username').value = profile.username || '';
                    document.getElementById('email').value = profile.email || '';
                    document.getElementById('phone').value = profile.phone || '';
                    
                    // Update profile info display
                    const profileInfo = document.getElementById('profileInfo');
                    profileInfo.innerHTML = `
                        <div class="profile-info">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Full Name</h4>
                                    <p>${profile.full_name || 'Not set'}</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-at"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Username</h4>
                                    <p>${profile.username || 'Not set'}</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Email</h4>
                                    <p>${profile.email || 'Not set'}</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Phone</h4>
                                    <p>${profile.phone || 'Not set'}</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Role</h4>
                                    <p style="text-transform: capitalize;">${profile.role || 'Not set'}</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Member Since</h4>
                                    <p>${profile.created_at ? new Date(profile.created_at).toLocaleDateString() : 'Not set'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
        }
        
        // Update profile
        function updateProfile() {
            const formData = new FormData(document.getElementById('profileForm'));
            formData.append('ajax', 'true');
            formData.append('action', 'update_profile');
            
            // Show loading
            const submitBtn = document.querySelector('#profileForm button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            fetch('admin_profile.php', {
                method: 'POST',
                body: new FormData(document.getElementById('profileForm'))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                const alertContainer = document.getElementById('alertContainer');
                
                if (data.success) {
                    // Show success message
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Success!</strong> ${data.message}
                            </div>
                        </div>
                    `;
                    
                    // Clear password fields
                    document.getElementById('current_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                    
                    // Reload profile data
                    loadProfile();
                    
                    // Auto-hide success message after 5 seconds
                    setTimeout(() => {
                        alertContainer.innerHTML = '';
                    }, 5000);
                    
                } else {
                    // Show error messages
                    let errorHtml = '';
                    if (data.errors && data.errors.length > 0) {
                        data.errors.forEach(error => {
                            errorHtml += `<div style="margin-bottom: 5px;">• ${error}</div>`;
                        });
                    } else {
                        errorHtml = data.message || 'An error occurred';
                    }
                    
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <strong>Error!</strong>
                                <div style="margin-top: 5px;">${errorHtml}</div>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Network Error!</strong>
                            <div style="margin-top: 5px;">Unable to connect to server. Please try again.</div>
                        </div>
                    </div>
                `;
            });
        }
        
        // Validate password strength
        function validatePassword(password) {
            const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            const mediumRegex = /^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*\d))|((?=.*[A-Z])(?=.*\d)))[A-Za-z\d]{6,}$/;
            
            if (strongRegex.test(password)) {
                return { strength: 'strong', color: 'var(--success)' };
            } else if (mediumRegex.test(password)) {
                return { strength: 'medium', color: 'var(--warning)' };
            } else {
                return { strength: 'weak', color: 'var(--danger)' };
            }
        }
        
        // Add password strength indicator
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            if (password.length > 0) {
                const strength = validatePassword(password);
                const indicator = document.getElementById('password-strength') || createPasswordStrengthIndicator();
                indicator.innerHTML = `
                    Strength: <span style="color: ${strength.color}; font-weight: 600;">${strength.strength.toUpperCase()}</span>
                `;
            }
        });
        
        function createPasswordStrengthIndicator() {
            const container = document.createElement('div');
            container.id = 'password-strength';
            container.className = 'form-text';
            document.getElementById('new_password').parentNode.appendChild(container);
            return container;
        }
    </script>
</body>
</html>