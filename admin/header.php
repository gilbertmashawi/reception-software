<?php
// header.php - Admin Header with Sidebar
if (!isset($_SESSION)) session_start();
require_once 'config.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user info (assuming you have user data in session)
$user_name = $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['user_role'] ?? 'Administrator';
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="https://scontent.fhre1-2.fna.fbcdn.net/v/t39.30808-6/358463300_669326775209841_5422243091594236605_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=xvYFlRpuCysQ7kNvwE1kVf7&_nc_oc=AdkIzCWNuer7eKsitiYPxWIEh6EeFJiR3OzMoX521DlWvneItUpdDQFAWg3h-EMy8-s&_nc_zt=23&_nc_ht=scontent.fhre1-2.fna&_nc_gid=igic6zzb6eWSf_Zi3oMxRw&oh=00_AfqSWFzSgi_3m_PNmez8FRZVwa7RrMtw_TkV8n8fIrbSrg&oe=697A3BAF" 
             alt="Logo">
        <h3>LinkSpot Admin</h3>
        <div class="user-info">
            <small><?php echo htmlspecialchars($user_name); ?></small>
            <small class="role-badge"><?php echo htmlspecialchars($user_role); ?></small>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        <!-- Admin Dashboard -->
        
        
        <!-- Admin Analytics -->
        <li class="nav-item">
            <a href="admin_analytics.php" class="nav-link <?php echo $current_page == 'admin_analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Admin Analytics</span>
            </a>
        </li>
        
        <!-- Admin Spaces -->
        <li class="nav-item">
            <a href="admin_spaces.php" class="nav-link <?php echo $current_page == 'admin_spaces.php' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i>
                <span>Admin Spaces</span>
            </a>
        </li>
        
        <!-- Admin Logs -->
        <li class="nav-item">
            <a href="admin_logs.php" class="nav-link <?php echo $current_page == 'admin_logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Admin Logs</span>
            </a>
        </li>
        
        <!-- Admin Mall -->
        <li class="nav-item">
            <a href="admin_mall.php" class="nav-link <?php echo $current_page == 'admin_mall.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Admin Mall</span>
            </a>
        </li>
        
        <!-- Today's Cashup -->
        <li class="nav-item">
            <a href="cashup.php" class="nav-link <?php echo $current_page == 'cashup.php' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i>
                <span>Today's Cashup</span>
            </a>
        </li>
        
        <!-- Profile -->
        <li class="nav-item">
            <a href="admin_profile.php" class="nav-link <?php echo $current_page == 'admin_profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>My Profile</span>
            </a>
        </li>
        
        <!-- Settings -->
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Settings</span>
            </a>
        </li>
        
        <!-- Logout -->
        <li class="nav-item logout-item">
            <a href="logout.php" class="nav-link logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="system-status">
            <span class="status-indicator active"></span>
            <small>System: Online</small>
        </div>
    </div>
</div>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<style>
    /* Existing styles from your original header remain the same... */
    /* Just add these new styles */
    
    .user-info {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.1);
        text-align: center;
    }
    
    .user-info small {
        display: block;
        color: rgba(255,255,255,0.8);
        font-size: 12px;
    }
    
    .role-badge {
        background: var(--primary);
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        margin-top: 5px;
        display: inline-block;
    }
    
    .logout-item {
        margin-top: auto;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .logout-link {
        color: #ff6b6b;
    }
    
    .logout-link:hover {
        background: rgba(255,107,107,0.1);
        color: #ff6b6b;
    }
    
    .sidebar-footer {
        padding: 15px 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: 10px;
    }
    
    .system-status {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #4CAF50;
    }
    
    .status-indicator.active {
        background: #4CAF50;
        box-shadow: 0 0 10px #4CAF50;
    }
    
    .status-indicator.inactive {
        background: #ff6b6b;
    }
    
    .sidebar-footer small {
        color: rgba(255,255,255,0.7);
        font-size: 12px;
    }
</style>

<script>
    // Initialize sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('active') &&
                !sidebar.contains(event.target) &&
                !mobileToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
        
        // Make toggleSidebar function globally available
        window.toggleSidebar = toggleSidebar;
        
        // Close sidebar on mobile after clicking a link
        document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        document.querySelector('.sidebar').classList.remove('active');
                    }, 300);
                }
            });
        });
        
        // Confirm logout
        document.querySelector('.logout-link').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth > 768) {
            // On desktop, remove active class from sidebar
            sidebar.classList.remove('active');
        }
    });
</script>