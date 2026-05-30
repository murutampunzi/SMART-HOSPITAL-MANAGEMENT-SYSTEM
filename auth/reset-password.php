<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$token = sanitizeInput($_GET['token'] ?? $_POST['token'] ?? '');
$user = null;

if (empty($token)) {
    $error = 'Invalid request. Missing token.';
} else {
    // Validate token and check expiration
    $sql = "SELECT id, name FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND status = 'active'";
    $stmt = prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new recovery link.';
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            
            // Update password and clear token fields
            $update_sql = "UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?";
            $update_stmt = prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                // Log activity
                logActivity('password_reset_success', 'Password successfully reset for user: ' . $user['name']);
                
                $success = 'Password reset successfully! Redirecting you to the login page in 3 seconds...';
                header('Refresh: 3; url=login.php');
            } else {
                $error = 'Failed to reset password. Please try again later.';
            }
        }
    }
}

$page_title = "Reset Password - Smart Hospital Management System";
include '../includes/header.php';
?>

<div class="container-fluid vh-100">
    <div class="row h-100">
        <!-- Left side - Info Card -->
        <div class="col-lg-6 bg-gradient-primary text-white d-flex align-items-center">
            <div class="p-5">
                <h1 class="display-4 fw-bold mb-4">Choose a New Password</h1>
                <p class="lead mb-4">Your account identity is verified. Resetting your password is the final step to secure your medical details and portal access.</p>
                <div class="mt-4">
                    <h5><i class="fas fa-key me-2 text-warning"></i>Password Strength Tips</h5>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-2"><i class="fas fa-check me-2 text-success"></i>Use at least 8 characters</li>
                        <li class="mb-2"><i class="fas fa-check me-2 text-success"></i>Mix uppercase and lowercase letters</li>
                        <li class="mb-2"><i class="fas fa-check me-2 text-success"></i>Include at least one digit and one special symbol</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right side - Reset Password Form -->
        <div class="col-lg-6 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 440px; padding: 2rem;">
                <div class="text-center mb-4">
                    <i class="fas fa-lock-open fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold">Reset Password</h2>
                    <p class="text-muted">Enter and confirm your new account password</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($user && empty($success)): ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <?php echo getCSRFInput(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                <div class="invalid-feedback">
                                    Password must be at least 8 characters
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                <div class="invalid-feedback">
                                    Please confirm your new password
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none">Return to Login Screen</a>
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
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            
            if (password.value !== confirm.value) {
                confirm.setCustomValidity('Passwords do not match');
            } else {
                confirm.setCustomValidity('');
            }
            
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
