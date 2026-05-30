<?php if (isLoggedIn()): ?>
    <!-- Footer -->
    <footer class="py-4 mt-5 bg-card border-top no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; 2026 Smart Hospital Management System. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted small">Version <?php echo APP_VERSION; ?> | 
                    Logged in as: <strong class="text-primary"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> (<?php echo ucfirst($_SESSION['user_role']); ?>)</span>
                </div>
            </div>
        </div>
    </footer>
    </main> <!-- Close .main-wrapper opened in header.php -->
<?php else: ?>
    <!-- Landing page footer - Hidden on login, register, and main landing entrypoints to keep design clean -->
    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    $hide_footer_pages = ['index.php', 'login.php', 'register.php', 'forgot-password.php', 'reset-password.php'];
    if (!in_array($current_page, $hide_footer_pages)): 
    ?>
    <!-- Landing page footer -->
    <footer class="bg-dark text-white py-5 no-print">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-hospital-alt me-2 text-primary"></i>SHMS</h5>
                    <p class="text-muted">Smart Hospital Management System provides comprehensive healthcare management solutions for modern medical facilities.</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?php echo BASE_PATH; ?>index.php" class="text-muted">Home</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">About Us</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">Services</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Services</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-muted">Patient Management</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">Doctor Consultation</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">Laboratory Tests</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">Pharmacy Services</a></li>
                        <li class="mb-2"><a href="#" class="text-muted">Emergency Care</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2 text-muted"><i class="fas fa-map-marker-alt me-2 text-primary"></i> 123 Hospital Street, Medical City</li>
                        <li class="mb-2 text-muted"><i class="fas fa-phone me-2 text-primary"></i> +1 234 567 8900</li>
                        <li class="mb-2 text-muted"><i class="fas fa-envelope me-2 text-primary"></i> info@shms.com</li>
                        <li class="mb-2 text-muted"><i class="fas fa-clock me-2 text-primary"></i> 24/7 Emergency Services</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; 2026 Smart Hospital Management System. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted me-3">Terms of Service</a>
                    <a href="#" class="text-muted">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
<?php endif; ?>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom Main JS application layer -->
<script src="<?php echo BASE_PATH; ?>assets/js/app.js?v=2.1"></script>

<!-- Notification and Global Interactive Layer -->
<script>
// Load notifications every 30 seconds
if (typeof isLoggedIn !== 'undefined' && isLoggedIn()) {
    setInterval(loadNotifications, 30000);
    loadNotifications(); // Initial load
}

function loadNotifications() {
    fetch('<?php echo BASE_PATH; ?>api/notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationUI(data.data);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationUI(notifications) {
    const count = notifications.filter(n => !n.read).length;
    const countBadge = document.getElementById('notificationCount');
    if (countBadge) {
        countBadge.textContent = count;
        countBadge.style.display = count > 0 ? 'inline-block' : 'none';
    }
    
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        const html = notifications.slice(0, 5).map(notification => `
            <li>
                <a class="dropdown-item py-2 px-3 notification-item" href="${notification.link}" data-notification-id="${notification.id}" style="border-bottom: 1px solid var(--border-color); white-space: normal;">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 mt-1">
                            <i class="fas fa-${getNotificationIcon(notification.type)} text-${getNotificationColor(notification.type)}"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold small text-primary" style="font-size: 0.85rem;">${notification.title}</div>
                            <div class="text-muted text-truncate-2" style="font-size: 0.8rem; line-height: 1.3;">${notification.message}</div>
                            <div class="text-muted mt-1" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i>${timeAgo(notification.created_at)}</div>
                        </div>
                    </div>
                </a>
            </li>
        `).join('');
        
        if (html) {
            dropdown.innerHTML = '<li><h6 class="dropdown-header py-2 px-3 fw-bold">Notifications</h6></li><li><hr class="dropdown-divider my-0"></li>' + html + 
                                  '<li><a class="dropdown-item text-center py-2 text-primary fw-semibold" href="<?php echo BASE_PATH; ?>notifications/index.php" style="font-size: 0.85rem;">View All Notifications</a></li>';
        } else {
            dropdown.innerHTML = '<li><h6 class="dropdown-header py-2 px-3 fw-bold">Notifications</h6></li><li><hr class="dropdown-divider my-0"></li><li class="text-center p-3 text-muted"><small>No new notifications</small></li>';
        }
    }
}

function getNotificationIcon(type) {
    const icons = {
        'appointment': 'calendar-check',
        'message': 'envelope',
        'lab_result': 'flask',
        'prescription': 'pills',
        'payment': 'credit-card',
        'system': 'cog'
    };
    return icons[type] || 'bell';
}

function getNotificationColor(type) {
    const colors = {
        'appointment': 'primary',
        'message': 'info',
        'lab_result': 'success',
        'prescription': 'warning',
        'payment': 'success',
        'system': 'secondary'
    };
    return colors[type] || 'primary';
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString();
}

// Export data function
function exportData() {
    const currentUrl = window.location.href;
    const exportUrl = currentUrl.includes('?') ? currentUrl + '&export=1' : currentUrl + '?export=1';
    window.open(exportUrl, '_blank');
}

// Auto-refresh for dashboard
if (window.location.pathname.includes('dashboard.php')) {
    setTimeout(() => {
        location.reload();
    }, 300000); // Refresh every 5 minutes
}

// Interactive Theme and Responsive Control scripts
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar responsive toggle logic
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const appSidebar = document.getElementById('appSidebar');
    if (sidebarToggleBtn && appSidebar) {
        sidebarToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            appSidebar.classList.toggle('show');
        });
        
        // Hide sidebar on body clicks outside
        document.addEventListener('click', function(e) {
            if (appSidebar.classList.contains('show') && !appSidebar.contains(e.target) && e.target !== sidebarToggleBtn) {
                appSidebar.classList.remove('show');
            }
        });
    }

    // Dynamic light/dark theme switch control
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            const currentTheme = document.body.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Sync theme button icon state
            const icon = themeToggleBtn.querySelector('[data-theme-icon]');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            
            // Re-apply matching colors to Chart.js elements
            if (typeof updateChartThemes === 'function') {
                updateChartThemes(newTheme);
            }
        });
        
        // Initialize active icon state
        const activeTheme = document.body.getAttribute('data-theme') || 'light';
        const icon = themeToggleBtn.querySelector('[data-theme-icon]');
        if (icon) {
            icon.className = activeTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
});
</script>

<?php if (isset($custom_js)): ?>
    <script><?php echo $custom_js; ?></script>
<?php endif; ?>

</body>
</html>
