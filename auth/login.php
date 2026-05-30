<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Validate input
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } else {
            // Attempt login
            $sql = "SELECT * FROM users WHERE email = ? AND status = 'active'";
            $stmt = prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (verifyPassword($password, $user['password'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $update_stmt = prepare($update_sql);
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    
                    // Handle remember me
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        // Store token in database
                        $token_sql = "UPDATE users SET remember_token = ? WHERE id = ?";
                        $token_stmt = prepare($token_sql);
                        $token_stmt->bind_param("si", $token, $user['id']);
                        $token_stmt->execute();
                        
                        // Set cookie
                        setcookie('remember_token', $token, $expires, '/', '', SECURE, true);
                        setcookie('email', $email, $expires, '/', '', SECURE, true);
                    }
                    
                    // Log activity
                    logActivity('login', 'User logged in: ' . $email);
                    
                    // Redirect to dashboard
                    redirect('dashboard.php');
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        }
    }
}

// Handle remember me login
if (!isset($_POST['email']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['email'])) {
    $token = $_COOKIE['remember_token'];
    $email = $_COOKIE['email'];
    
    $sql = "SELECT * FROM users WHERE email = ? AND remember_token = ? AND status = 'active'";
    $stmt = prepare($sql);
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Auto login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Update last login
        $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $update_stmt = prepare($update_sql);
        $update_stmt->bind_param("i", $user['id']);
        $update_stmt->execute();
        
        // Log activity
        logActivity('auto_login', 'User auto logged in: ' . $email);
        
        redirect('dashboard.php');
    } else {
        // Clear invalid cookies
        setcookie('remember_token', '', time() - 3600, '/', '', SECURE, true);
        setcookie('email', '', time() - 3600, '/', '', SECURE, true);
    }
}

$page_title = "Login - Smart Hospital Management System";
include '../includes/header.php';
?>

<div class="container-fluid vh-100">
    <div class="row h-100">
        <!-- Left side - Login form -->
        <div class="col-lg-6 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 400px;">
                <div class="text-center mb-4">
                    <i class="fas fa-hospital-alt fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold">Welcome Back</h2>
                    <p class="text-muted">Sign in to your account</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $_COOKIE['email'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please enter a valid email address
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Please enter your password
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" 
                                   <?php echo isset($_COOKIE['email']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Register here</a></p>
                </div>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <strong>Default Login:</strong><br>
                        Email: admin@shms.com<br>
                        Password: admin123
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Right side - Features -->
        <div class="col-lg-6 bg-gradient-primary text-white d-flex align-items-center">
            <div class="p-5">
                <h1 class="display-4 fw-bold mb-4">Smart Hospital Management System</h1>
                <p class="lead mb-4">Complete healthcare solution for modern hospitals with advanced features and seamless user experience.</p>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-injured fa-2x mb-3"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Patient Management</h5>
                                <p class="mb-0">Complete patient records and medical history</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-calendar-check fa-2x mb-3"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Appointment Booking</h5>
                                <p class="mb-0">Online scheduling and reminders</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-flask fa-2x mb-3"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Laboratory Services</h5>
                                <p class="mb-0">Test management and results</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-pills fa-2x mb-3"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Pharmacy Management</h5>
                                <p class="mb-0">Inventory and prescription handling</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5">
                    <h4>Key Features</h4>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Role-based access control</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Real-time notifications</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Secure data management</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Mobile responsive design</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Advanced reporting</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password visibility toggle
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Form validation
(function() {
    'use strict';
    
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Auto-focus email field
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    if (emailInput && !emailInput.value) {
        emailInput.focus();
    } else {
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.focus();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
