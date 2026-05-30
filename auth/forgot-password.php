<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if email exists in database
            $sql = "SELECT id, name FROM users WHERE email = ? AND status = 'active'";
            $stmt = prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Generate token
                $token = bin2hex(random_bytes(32));
                
                // Update user with reset token and expiry (1 hour)
                $update_sql = "UPDATE users SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?";
                $update_stmt = prepare($update_sql);
                $update_stmt->bind_param("si", $token, $user['id']);
                
                if ($update_stmt->execute()) {
                    // Log activity
                    logActivity('password_reset_request', 'Password reset requested for: ' . $email);
                    
                    // In a production system, we would email the link to the user.
                    // For local development and demonstration, we display it on the screen.
                    $reset_link = "reset-password.php?token=" . $token;
                    $success = 'A password reset link has been generated! For this demonstration, please use the link below to complete the reset:<br><br>' .
                               '<a href="' . $reset_link . '" class="btn btn-sm btn-success"><i class="fas fa-key me-2"></i>Reset My Password</a>';
                } else {
                    $error = 'Failed to generate password reset request. Please try again later.';
                }
            } else {
                // To prevent email enumeration, we display a generic success message
                // but for testing convenience in this development context, we explain no user was found.
                $error = 'No active account found with that email address.';
            }
        }
    }
}

$page_title = "Forgot Password - Smart Hospital Management System";
include '../includes/header.php';
?>

<div class="container-fluid vh-100">
    <div class="row h-100">
        <!-- Left side - Features info card -->
        <div class="col-lg-6 bg-gradient-primary text-white d-flex align-items-center">
            <div class="p-5">
                <h1 class="display-4 fw-bold mb-4">Account Recovery</h1>
                <p class="lead mb-4">Get back access to your Smart Hospital Management System account. Secure, automated, and instant.</p>
                <div class="mt-4">
                    <h5><i class="fas fa-shield-alt me-2 text-warning"></i>Security Measures</h5>
                    <p class="text-white-50">Password recovery requests expire after 1 hour. Make sure to choose a strong password consisting of letters, numbers, and symbols once verified.</p>
                </div>
            </div>
        </div>

        <!-- Right side - Request Form -->
        <div class="col-lg-6 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 440px; padding: 2rem;">
                <div class="text-center mb-4">
                    <i class="fas fa-key fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold">Forgot Password?</h2>
                    <p class="text-muted">Enter your registered email to recover your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($success)): ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <?php echo getCSRFInput(); ?>
                        
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="name@hospital.com">
                                <div class="invalid-feedback">
                                    Please enter a valid email address
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Request
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <p class="mb-0">Remembered your password? <a href="login.php" class="text-decoration-none fw-bold">Sign In here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>

<?php include '../includes/footer.php'; ?>
