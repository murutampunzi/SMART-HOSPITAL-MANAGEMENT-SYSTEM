<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? 'patient');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        
        // Validate input
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all required fields';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } elseif (!validatePassword($password)) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (!in_array($role, ['patient', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician'])) {
            $error = 'Invalid user role selected';
        } else {
            // Check if email already exists
            $check_sql = "SELECT id FROM users WHERE email = ?";
            $check_stmt = prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Email address already exists';
            } else {
                // Create user account
                $hashed_password = hashPassword($password);
                $verification_token = bin2hex(random_bytes(32));
                
                $sql = "INSERT INTO users (name, email, password, role, phone, address, email_verification_token, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
                
                $stmt = prepare($sql);
                $stmt->bind_param("sssssss", $name, $email, $hashed_password, $role, $phone, $address, $verification_token);
                
                if ($stmt->execute()) {
                    $user_id = insertId();
                    
                    // Create role-specific record
                    if ($role === 'patient') {
                        $patient_id = 'PAT' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                        $patient_sql = "INSERT INTO patients (user_id, patient_id, first_name, last_name, date_of_birth, gender, phone, email, address) 
                                      VALUES (?, ?, '', '', CURDATE(), 'other', ?, ?, ?)";
                        $patient_stmt = prepare($patient_sql);
                        $patient_stmt->bind_param("issss", $user_id, $patient_id, $phone, $email, $address);
                        $patient_stmt->execute();
                    } elseif ($role === 'doctor') {
                        $doctor_id = 'DOC' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                        $doctor_sql = "INSERT INTO doctors (user_id, doctor_id, first_name, last_name, specialization, phone, email, address) 
                                     VALUES (?, ?, '', '', 'General Practice', ?, ?, ?)";
                        $doctor_stmt = prepare($doctor_sql);
                        $doctor_stmt->bind_param("issss", $user_id, $doctor_id, $phone, $email, $address);
                        $doctor_stmt->execute();
                    }
                    
                    // Log activity
                    logActivity('register', 'New user registered: ' . $email);
                    
                    // Send verification email (in production)
                    // sendEmail($email, 'Verify Your Email', "Click here to verify: " . BASE_URL . "auth/verify.php?token=" . $verification_token);
                    
                    $success = 'Registration successful! You can now login with your credentials.';
                    
                    // Redirect to login after 3 seconds
                    header('Refresh: 3; url=login.php');
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

$page_title = "Register - Smart Hospital Management System";
include '../includes/header.php';
?>

<div class="container-fluid vh-100">
    <div class="row h-100">
        <!-- Left side - Registration form -->
        <div class="col-lg-6 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 450px;">
                <div class="text-center mb-4">
                    <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold">Create Account</h2>
                    <p class="text-muted">Join our hospital management system</p>
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
                        <label for="name" class="form-label">Full Name *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please enter your full name
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please enter a valid email address
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">User Role *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="patient" <?php echo ($_POST['role'] ?? '') === 'patient' ? 'selected' : ''; ?>>Patient</option>
                                <option value="doctor" <?php echo ($_POST['role'] ?? '') === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                <option value="nurse" <?php echo ($_POST['role'] ?? '') === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                                <option value="receptionist" <?php echo ($_POST['role'] ?? '') === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                                <option value="pharmacist" <?php echo ($_POST['role'] ?? '') === 'pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                                <option value="lab_technician" <?php echo ($_POST['role'] ?? '') === 'lab_technician' ? 'selected' : ''; ?>>Lab Technician</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user role
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Password must be at least 8 characters long
                            </div>
                        </div>
                        <small class="text-muted">Password must be at least 8 characters long</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">
                                Please confirm your password
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-decoration-none">Terms and Conditions</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                            </label>
                            <div class="invalid-feedback">
                                You must agree to the terms and conditions
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Sign in here</a></p>
                </div>
            </div>
        </div>
        
        <!-- Right side - Benefits -->
        <div class="col-lg-6 bg-gradient-primary text-white d-flex align-items-center">
            <div class="p-5">
                <h1 class="display-4 fw-bold mb-4">Why Choose SHMS?</h1>
                <p class="lead mb-4">Experience the future of hospital management with our comprehensive and user-friendly system.</p>
                
                <div class="mb-5">
                    <h3 class="h4 mb-3">Benefits for All Users</h3>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shield-alt fa-2x me-3"></i>
                                </div>
                                <div>
                                    <h5 class="h6 mb-1">Secure & Reliable</h5>
                                    <p class="mb-0 small">Bank-level security for all your medical data</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-mobile-alt fa-2x me-3"></i>
                                </div>
                                <div>
                                    <h5 class="h6 mb-1">Mobile Friendly</h5>
                                    <p class="mb-0 small">Access from any device, anywhere</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-bell fa-2x me-3"></i>
                                </div>
                                <div>
                                    <h5 class="h6 mb-1">Real-time Updates</h5>
                                    <p class="mb-0 small">Instant notifications for appointments and results</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chart-line fa-2x me-3"></i>
                                </div>
                                <div>
                                    <h5 class="h6 mb-1">Advanced Analytics</h5>
                                    <p class="mb-0 small">Comprehensive reports and insights</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h3 class="h4 mb-3">Role-Specific Features</h3>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i><strong>Patients:</strong> Book appointments, view records, access lab results</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i><strong>Doctors:</strong> Manage schedules, access patient history, prescribe medications</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i><strong>Staff:</strong> Streamlined workflows, efficient communication tools</li>
                        <li class="mb-2"><i class="fas fa-check-circle me-2"></i><strong>Admin:</strong> Complete system control, advanced reporting, user management</li>
                    </ul>
                </div>
                
                <div class="text-center">
                    <h4>Join Thousands of Healthcare Professionals</h4>
                    <p class="lead">Trusted by hospitals and clinics worldwide</p>
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

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
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

// Auto-focus name field
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    if (nameInput && !nameInput.value) {
        nameInput.focus();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
