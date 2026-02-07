<?php
// footer.php - Common Footer for all pages
?>
<!-- Footer -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <div class="footer-logo">
                <img src="https://scontent.fhre1-2.fna.fbcdn.net/v/t39.30808-6/358463300_669326775209841_5422243091594236605_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=xvYFlRpuCysQ7kNvwE1kVf7&_nc_oc=AdkIzCWNuer7eKsitiYPxWIEh6EeFJiR3OzMoX521DlWvneItUpdDQFAWg3h-EMy8-s&_nc_zt=23&_nc_ht=scontent.fhre1-2.fna&_nc_gid=igic6zzb6eWSf_Zi3oMxRw&oh=00_AfqSWFzSgi_3m_PNmez8FRZVwa7RrMtw_TkV8n8fIrbSrg&oe=697A3BAF" 
                     alt="LinkSpot Logo" class="footer-logo-img">
                <div class="footer-logo-text">
                    <h3>LinkSpot</h3>
                    <p>Management System</p>
                </div>
            </div>
            <p class="footer-description">
                Professional workspace management system for LinkSpot Spaces and Summarcity Mall.
                Streamline operations, track revenue, and manage members efficiently.
            </p>
            <div class="footer-social">
                <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
        
        <div class="footer-section">
            <h4 class="footer-heading">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="vouchers.php"><i class="fas fa-wifi"></i> Internet Vouchers</a></li>
                <li><a href="meeting_rooms.php"><i class="fas fa-door-closed"></i> Meeting Rooms</a></li>
                <li><a href="linkspot_spaces.php"><i class="fas fa-link"></i> LinkSpot Spaces</a></li>
                <li><a href="summarcity_mall.php"><i class="fas fa-shopping-cart"></i> Summarcity Mall</a></li>
                <li><a href="tasks.php"><i class="fas fa-tasks"></i> Daily Tasker</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h4 class="footer-heading">Support</h4>
            <ul class="footer-links">
                <li><a href="#"><i class="fas fa-question-circle"></i> Help Center</a></li>
                <li><a href="#"><i class="fas fa-book"></i> Documentation</a></li>
                <li><a href="#"><i class="fas fa-phone"></i> Contact Support</a></li>
                <li><a href="#"><i class="fas fa-bug"></i> Report Issue</a></li>
                <li><a href="#"><i class="fas fa-lightbulb"></i> Feature Request</a></li>
                <li><a href="#"><i class="fas fa-download"></i> Updates</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h4 class="footer-heading">Contact Info</h4>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>123 Business Center, Harare, Zimbabwe</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span>+263 77 123 4567</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span>info@linkspot.co.zw</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <span>Mon-Fri: 8AM-6PM, Sat: 9AM-1PM</span>
                </div>
            </div>
            
            <div class="newsletter">
                <h5>Stay Updated</h5>
                <p>Subscribe to our newsletter for updates</p>
                <div class="newsletter-form">
                    <input type="email" placeholder="Your email" class="newsletter-input">
                    <button class="newsletter-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> LinkSpot Management System. All rights reserved.
                <span class="version">v2.0.0</span>
            </div>
            
            <div class="footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Cookie Policy</a>
                <a href="#">Sitemap</a>
            </div>
            
            <div class="system-info">
                <span class="user-info">
                    <i class="fas fa-user"></i> 
                    <?php 
                    if (isset($_SESSION['full_name'])) {
                        echo htmlspecialchars($_SESSION['full_name']);
                    } else {
                        echo 'Guest';
                    }
                    ?>
                </span>
                <span class="server-time">
                    <i class="fas fa-clock"></i> 
                    <span id="liveClock"><?php echo date('H:i:s'); ?></span>
                </span>
                <span class="server-date">
                    <i class="fas fa-calendar"></i> 
                    <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Footer Styles */
    .footer {
        background: linear-gradient(135deg, var(--secondary) 0%, #1a252f 100%);
        color: white;
        margin-top: auto;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
        padding: 50px 30px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .footer-section {
        padding: 0 15px;
    }
    
    .footer-logo {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .footer-logo-img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid var(--primary);
        object-fit: cover;
    }
    
    .footer-logo-text h3 {
        margin: 0;
        font-size: 22px;
        color: white;
    }
    
    .footer-logo-text p {
        margin: 5px 0 0 0;
        color: rgba(255,255,255,0.7);
        font-size: 14px;
    }
    
    .footer-description {
        color: rgba(255,255,255,0.8);
        line-height: 1.6;
        margin-bottom: 25px;
        font-size: 14px;
    }
    
    .footer-social {
        display: flex;
        gap: 15px;
    }
    
    .social-link {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .social-link:hover {
        background: var(--primary);
        transform: translateY(-3px);
    }
    
    .footer-heading {
        color: white;
        font-size: 18px;
        margin-bottom: 25px;
        position: relative;
        padding-bottom: 10px;
    }
    
    .footer-heading:after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 40px;
        height: 3px;
        background: var(--primary);
        border-radius: 2px;
    }
    
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 12px;
    }
    
    .footer-links a {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        font-size: 14px;
    }
    
    .footer-links a:hover {
        color: var(--primary);
        transform: translateX(5px);
    }
    
    .footer-links a i {
        width: 20px;
        text-align: center;
    }
    
    .contact-info {
        margin-bottom: 30px;
    }
    
    .contact-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
        color: rgba(255,255,255,0.8);
        font-size: 14px;
    }
    
    .contact-item i {
        color: var(--primary);
        margin-top: 3px;
    }
    
    .newsletter h5 {
        color: white;
        margin-bottom: 10px;
        font-size: 16px;
    }
    
    .newsletter p {
        color: rgba(255,255,255,0.7);
        font-size: 13px;
        margin-bottom: 15px;
    }
    
    .newsletter-form {
        display: flex;
        gap: 5px;
    }
    
    .newsletter-input {
        flex: 1;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        background: rgba(255,255,255,0.1);
        color: white;
        font-size: 14px;
    }
    
    .newsletter-input::placeholder {
        color: rgba(255,255,255,0.5);
    }
    
    .newsletter-input:focus {
        outline: none;
        background: rgba(255,255,255,0.15);
    }
    
    .newsletter-btn {
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 5px;
        width: 45px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .newsletter-btn:hover {
        background: #219653;
    }
    
    .footer-bottom {
        background: rgba(0,0,0,0.2);
        padding: 20px 30px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .footer-bottom-content {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .copyright {
        color: rgba(255,255,255,0.7);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .version {
        background: rgba(255,255,255,0.1);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .footer-bottom-links {
        display: flex;
        gap: 25px;
    }
    
    .footer-bottom-links a {
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 13px;
        transition: color 0.3s;
    }
    
    .footer-bottom-links a:hover {
        color: var(--primary);
    }
    
    .system-info {
        display: flex;
        gap: 20px;
        font-size: 13px;
        color: rgba(255,255,255,0.7);
    }
    
    .user-info, .server-time, .server-date {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .system-info i {
        color: var(--primary);
    }
    
    /* Mobile Responsiveness */
    @media (max-width: 768px) {
        .footer-content {
            grid-template-columns: 1fr;
            gap: 30px;
            padding: 30px 20px;
        }
        
        .footer-section {
            padding: 0;
        }
        
        .footer-bottom-content {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
        
        .footer-bottom-links {
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        .system-info {
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
    }
    
    @media (max-width: 480px) {
        .footer {
            margin-left: 0;
        }
        
        .footer-logo {
            flex-direction: column;
            text-align: center;
        }
        
        .footer-social {
            justify-content: center;
        }
    }
</style>

<script>
    // Live clock update
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { 
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const clockElement = document.getElementById('liveClock');
        if (clockElement) {
            clockElement.textContent = timeStr;
        }
    }
    
    // Update clock every second
    setInterval(updateClock, 1000);
    
    // Initialize clock on page load
    document.addEventListener('DOMContentLoaded', updateClock);
    
    // Newsletter subscription (placeholder)
    document.querySelector('.newsletter-btn')?.addEventListener('click', function() {
        const emailInput = document.querySelector('.newsletter-input');
        const email = emailInput.value.trim();
        
        if (!email) {
            alert('Please enter your email address');
            return;
        }
        
        if (!validateEmail(email)) {
            alert('Please enter a valid email address');
            return;
        }
        
        // In a real implementation, this would send to a server
        alert('Thank you for subscribing to our newsletter!');
        emailInput.value = '';
    });
    
    // Email validation
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Smooth scroll for anchor links in footer
    document.querySelectorAll('.footer-links a').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
</script>

<?php
// Close database connection if open
if (function_exists('getDB')) {
    $db = getDB();
    if ($db instanceof mysqli) {
        $db->close();
    }
}
?>