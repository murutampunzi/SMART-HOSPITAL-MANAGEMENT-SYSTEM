<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$page_title = "Smart Hospital Management System";
include 'includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center justify-content-center bg-gradient-primary">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <div class="col-lg-6 bg-gradient-primary text-white p-5">
                                <div class="text-center">
                                    <i class="fas fa-hospital-alt fa-4x mb-4"></i>
                                    <h1 class="display-4 fw-bold">SHMS</h1>
                                    <h2 class="h4 mb-4">Smart Hospital Management System</h2>
                                    <p class="lead">Complete healthcare solution for modern hospitals</p>
                                    <ul class="list-unstyled text-start mt-4">
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Patient Management</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Doctor Scheduling</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Appointment Booking</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Pharmacy & Lab</li>
                                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Billing System</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-lg-6 p-5">
                                <div class="text-center mb-4">
                                    <h3 class="fw-bold">Login</h3>
                                    <p class="text-muted">Access your hospital dashboard</p>
                                </div>
                                
                                <?php if (isset($_GET['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($_GET['error']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($_GET['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($_GET['success']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form action="auth/login.php" method="POST">
                                    <?php echo getCSRFInput(); ?>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo isset($_COOKIE['email']) ? htmlspecialchars($_COOKIE['email']) : ''; ?>" 
                                                   required>
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
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="remember" name="remember" 
                                               <?php echo isset($_COOKIE['email']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-sign-in-alt me-2"></i>Login
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-4">
                                    <p class="mb-2">Don't have an account?</p>
                                    <a href="auth/register.php" class="btn btn-outline-primary">Register Here</a>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        Default Admin: admin@shms.com / admin123
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>

<?php include 'includes/footer.php'; ?>
