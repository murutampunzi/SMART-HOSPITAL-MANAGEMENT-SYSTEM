<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$page_title = "Account Settings - Smart Hospital Management System";
$page_heading = "Account Settings";

$error = '';
$success = '';

$user_id = $_SESSION['user_id'];

// Fetch current user details
$stmt = prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'profile') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            
            if (empty($name)) {
                $error = 'Full name is required.';
            } else {
                $update = prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
                $update->bind_param("sssi", $name, $phone, $address, $user_id);
                if ($update->execute()) {
                    $_SESSION['user_name'] = $name;
                    logActivity('update_account_profile', 'Updated profile information');
                    $success = 'Profile details updated successfully!';
                    
                    // Refresh user data
                    $user['name'] = $name;
                    $user['phone'] = $phone;
                    $user['address'] = $address;
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        } elseif ($action === 'password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (!validatePassword($new_password)) {
                $error = 'New password must be at least 8 characters long.';
            } elseif (!verifyPassword($current_password, $user['password'])) {
                $error = 'Incorrect current password.';
            } else {
                $hashed_password = hashPassword($new_password);
                $update = prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashed_password, $user_id);
                if ($update->execute()) {
                    logActivity('change_password', 'Changed account password');
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password. Please try again.';
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: User Profile Details -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-user-circle text-primary me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body p-4">
                <form action="account.php" method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    <input type="hidden" name="action" value="profile">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        <div class="invalid-feedback">Please enter your full name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address (Read Only)</label>
                        <input type="email" class="form-control bg-light" id="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Save Profile Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Change Password -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-key text-primary me-2"></i>Change Password</h5>
            </div>
            <div class="card-body p-4">
                <form action="account.php" method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    <input type="hidden" name="action" value="password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <div class="invalid-feedback">Please enter your current password.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                        <div class="invalid-feedback">New password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">Please confirm your new password.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-lock me-2"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password validation matching
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Bootstrap validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include '../includes/footer.php'; ?>
