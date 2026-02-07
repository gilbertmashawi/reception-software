<?php
// header.php - Common Header with Sidebar
if (!isset($_SESSION)) session_start();
require_once 'config.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="https://scontent.fhre1-2.fna.fbcdn.net/v/t39.30808-6/358463300_669326775209841_5422243091594236605_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=xvYFlRpuCysQ7kNvwE1kVf7&_nc_oc=AdkIzCWNuer7eKsitiYPxWIEh6EeFJiR3OzMoX521DlWvneItUpdDQFAWg3h-EMy8-s&_nc_zt=23&_nc_ht=scontent.fhre1-2.fna&_nc_gid=igic6zzb6eWSf_Zi3oMxRw&oh=00_AfqSWFzSgi_3m_PNmez8FRZVwa7RrMtw_TkV8n8fIrbSrg&oe=697A3BAF" 
             alt="Logo">
        <h3>LinkSpot</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="nav-item nav-dropdown">
            <a href="#" class="nav-link">
                <i class="fas fa-wifi"></i>
                <span>Internet Vouchers</span>
            </a>
            <ul class="dropdown-menu">
                <li><a href="vouchers.php?action=sell" class="nav-link <?php echo ($current_page == 'vouchers.php' && ($_GET['action'] ?? '') == 'sell') ? 'active' : ''; ?>">Sell Vouchers</a></li>
                <li><a href="vouchers.php?action=history" class="nav-link <?php echo ($current_page == 'vouchers.php' && ($_GET['action'] ?? '') == 'history') ? 'active' : ''; ?>">Sales History</a></li>
                <li><a href="vouchers.php?action=active" class="nav-link <?php echo ($current_page == 'vouchers.php' && ($_GET['action'] ?? '') == 'active') ? 'active' : ''; ?>">Active Sessions</a></li>
            </ul>
        </li>
        
        <li class="nav-item">
            <a href="meeting_rooms.php" class="nav-link <?php echo $current_page == 'meeting_rooms.php' ? 'active' : ''; ?>">
                <i class="fas fa-door-closed"></i>
                <span>Meeting Rooms</span>
            </a>
        </li>
        
        <li class="nav-item nav-dropdown">
            <a href="#" class="nav-link">
                <i class="fas fa-link"></i>
                <span>LinkSpot Spaces</span>
            </a>
            <ul class="dropdown-menu">
                <li><a href="linkspot_spaces.php?action=payments" class="nav-link <?php echo ($current_page == 'linkspot_spaces.php' && ($_GET['action'] ?? '') == 'payments') ? 'active' : ''; ?>">Record Payments</a></li>
                <li><a href="linkspot_spaces.php?action=members" class="nav-link <?php echo ($current_page == 'linkspot_spaces.php' && ($_GET['action'] ?? '') == 'members') ? 'active' : ''; ?>">Members</a></li>
                <li><a href="linkspot_spaces.php?action=spaces" class="nav-link <?php echo ($current_page == 'linkspot_spaces.php' && ($_GET['action'] ?? '') == 'spaces') ? 'active' : ''; ?>">Space Management</a></li>
            </ul>
        </li>
        
        <li class="nav-item nav-dropdown">
            <a href="#" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Summarcity Mall</span>
            </a>
            <ul class="dropdown-menu">
                <li><a href="summarcity_mall.php?action=payments" class="nav-link <?php echo ($current_page == 'summarcity_mall.php' && ($_GET['action'] ?? '') == 'payments') ? 'active' : ''; ?>">Record Payments</a></li>
                <li><a href="summarcity_mall.php?action=tenants" class="nav-link <?php echo ($current_page == 'summarcity_mall.php' && ($_GET['action'] ?? '') == 'tenants') ? 'active' : ''; ?>">Tenants</a></li>
                <li><a href="summarcity_mall.php?action=shops" class="nav-link <?php echo ($current_page == 'summarcity_mall.php' && ($_GET['action'] ?? '') == 'shops') ? 'active' : ''; ?>">Shop Management</a></li>
            </ul>
        </li>
        
        <li class="nav-item">
            <a href="tasks.php" class="nav-link <?php echo $current_page == 'tasks.php' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Daily Tasker</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="members.php" class="nav-link <?php echo $current_page == 'members.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Our Members </span>
            </a>
        </li>
                <!-- <li class="nav-item">
            <a href="cashup.php" class="nav-link <?php echo $current_page == 'cashup.php' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i>

                <span>cash up UI</span>
            </a>
        </li> -->

                        <li class="nav-item">
            <a href="cashup_btn.php" class="nav-link <?php echo $current_page == 'cashup_btn.php' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i>

                <span>Cash Up</span>
            </a>
        </li>
        
        <li class="nav-item nav-dropdown">
            <a href="#" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>

            </a>
            <ul class="dropdown-menu">
                <li><a href="analytics.php?type=revenue" class="nav-link <?php echo ($current_page == 'analytics.php' && ($_GET['type'] ?? '') == 'revenue') ? 'active' : ''; ?>">Revenue Reports</a></li>
                <li><a href="analytics.php?type=occupancy" class="nav-link <?php echo ($current_page == 'analytics.php' && ($_GET['type'] ?? '') == 'occupancy') ? 'active' : ''; ?>">Occupancy Reports</a></li>
                <li><a href="analytics.php?type=members" class="nav-link <?php echo ($current_page == 'analytics.php' && ($_GET['type'] ?? '') == 'members') ? 'active' : ''; ?>">Member Analytics</a></li>
            </ul>
        </li>
        
        <li class="nav-item">
            <a href="notifications.php" class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <?php if (isLoggedIn() && getUnreadNotificationsCount() > 0): ?>
                <span class="badge" style="background: var(--danger); color: white; margin-left: auto; font-size: 10px; padding: 2px 5px;">
                    <?php echo getUnreadNotificationsCount(); ?>
                </span>
                <?php endif; ?>
            </a>
        </li>

                <li class="nav-item">
            <a href="connectivity.php" class="nav-link <?php echo $current_page == 'connectivity.php' ? 'active' : ''; ?>">
                <i class="fas fa-print"></i>
                <span>Printer Connectivity </span>
            </a>
        </li>
        
        <?php if (isLoggedIn() && $_SESSION['role'] === 'admin'): ?>
        <li class="nav-item nav-dropdown">
            <a href="#" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a href="system.php?action=users" class="nav-link <?php echo ($current_page == 'system.php' && ($_GET['action'] ?? '') == 'users') ? 'active' : ''; ?>">User Management</a></li>
                <li><a href="system.php?action=logs" class="nav-link <?php echo ($current_page == 'system.php' && ($_GET['action'] ?? '') == 'logs') ? 'active' : ''; ?>">System Logs</a></li>
                <li><a href="system.php?action=settings" class="nav-link <?php echo ($current_page == 'system.php' && ($_GET['action'] ?? '') == 'settings') ? 'active' : ''; ?>">Settings</a></li>
            </ul>
        </li>
        <?php endif; ?>
        
        <li class="nav-item" style="margin-top: 20px;">
            <a href="?logout=true" class="nav-link" style="color: #e74c3c;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<style>
    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--primary);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 5px;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        transition: all 0.3s;
    }
    
    .mobile-menu-toggle:hover {
        background: #219653;
        transform: scale(1.1);
    }
    
    /* Dropdown Icon */
    .dropdown-icon {
        margin-left: auto;
        transition: transform 0.3s;
        font-size: 12px;
    }
    
    .nav-dropdown.active .dropdown-icon {
        transform: rotate(180deg);
    }
    
    /* Dropdown Menu */
    .dropdown-menu {
        padding-left: 0;
        background: rgba(0,0,0,0.2);
        display: none;
        overflow: hidden;
    }
    
    .nav-dropdown.active .dropdown-menu {
        display: block;
        animation: slideDown 0.3s ease;
    }
    
    .dropdown-menu .nav-link {
        padding-left: 55px;
        padding-top: 10px;
        padding-bottom: 10px;
        font-size: 14px;
        border-left: 3px solid transparent;
    }
    
    .dropdown-menu .nav-link:hover {
        background: rgba(255,255,255,0.1);
        border-left-color: var(--primary);
    }
    
    .dropdown-menu .nav-link.active {
        background: rgba(255,255,255,0.15);
        border-left-color: var(--primary);
        color: white;
    }
    
    /* Animation for dropdown */
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Make dropdown parent active when child is active */
    .dropdown-menu .nav-link.active ~ .nav-dropdown > .nav-link {
        background: rgba(255,255,255,0.1);
        color: white;
        border-left-color: var(--primary);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: flex;
        }
        
        .sidebar {
            transform: translateX(-100%);
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        /* Make dropdowns open by default on mobile for better UX */
        .nav-dropdown .dropdown-menu {
            position: static;
            box-shadow: none;
            border: none;
            width: 100%;
        }
    }
    
    /* Desktop hover effect */
    @media (min-width: 769px) {
        .nav-dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .nav-dropdown:hover .dropdown-icon {
            transform: rotate(180deg);
        }
        
        .nav-dropdown:hover > .nav-link {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }
    }
</style>

<script>
    // Initialize sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-expand dropdown if child is active
        document.querySelectorAll('.dropdown-menu .nav-link.active').forEach(activeLink => {
            const dropdown = activeLink.closest('.nav-dropdown');
            if (dropdown) {
                dropdown.classList.add('active');
            }
        });
        
        // Toggle dropdown on click (for mobile)
        document.querySelectorAll('.nav-dropdown > .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const dropdown = this.parentElement;
                    dropdown.classList.toggle('active');
                    
                    // Close other dropdowns
                    document.querySelectorAll('.nav-dropdown').forEach(otherDropdown => {
                        if (otherDropdown !== dropdown) {
                            otherDropdown.classList.remove('active');
                        }
                    });
                }
            });
        });
        
        // Mobile menu toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
            
            // Close all dropdowns when closing sidebar
            if (!sidebar.classList.contains('active')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
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
                // Close all dropdowns
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
        
        // Make toggleSidebar function globally available
        window.toggleSidebar = toggleSidebar;
        
        // Close dropdowns when clicking on a dropdown item (for mobile)
        document.querySelectorAll('.dropdown-menu .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    // Close sidebar on mobile after clicking a link
                    setTimeout(() => {
                        document.querySelector('.sidebar').classList.remove('active');
                    }, 300);
                }
            });
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth > 768) {
            // On desktop, remove active class from sidebar
            sidebar.classList.remove('active');
            // Show dropdowns on hover (handled by CSS)
        } else {
            // On mobile, close all dropdowns
            document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
</script>