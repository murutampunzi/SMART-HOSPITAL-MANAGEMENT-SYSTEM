<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin']);

$page_title = "Add Doctor - Smart Hospital Management System";
$page_heading = "Add New Doctor";

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $specialization = sanitizeInput($_POST['specialization'] ?? '');
        $qualification = sanitizeInput($_POST['qualification'] ?? '');
        $experience_years = intval($_POST['experience_years'] ?? 0);
        $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $availability = sanitizeInput($_POST['availability'] ?? 'full_time');
        $consultation_hours = sanitizeInput($_POST['consultation_hours'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        $education = sanitizeInput($_POST['education'] ?? '');
        $create_user_account = isset($_POST['create_user_account']);
        $user_email = sanitizeInput($_POST['user_email'] ?? '');
        
        // Validate required fields
        $required_fields = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'specialization' => $specialization,
            'phone' => $phone
        ];
        
        $validation_errors = validateRequired($required_fields);
        if (!empty($validation_errors)) {
            $error = reset($validation_errors);
        } elseif ($experience_years < 0 || $experience_years > 70) {
            $error = 'Invalid years of experience';
        } elseif ($consultation_fee < 0) {
            $error = 'Invalid consultation fee';
        } elseif (!empty($email) && !validateEmail($email)) {
            $error = 'Invalid email address';
        } elseif ($create_user_account && empty($user_email)) {
            $error = 'User email is required when creating user account';
        } elseif ($create_user_account && !validateEmail($user_email)) {
            $error = 'Invalid user email address';
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                $user_id = null;
                
                // Create user account if requested
                if ($create_user_account) {
                    // Check if user email already exists
                    $check_sql = "SELECT id FROM users WHERE email = ?";
                    $check_stmt = prepare($check_sql);
                    $check_stmt->bind_param("s", $user_email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        throw new Exception('User account with this email already exists');
                    }
                    
                    // Generate random password
                    $password = bin2hex(random_bytes(4));
                    $hashed_password = hashPassword($password);
                    
                    // Create user
                    $user_sql = "INSERT INTO users (name, email, password, role, phone, address, status) 
                                 VALUES (?, ?, ?, 'doctor', ?, ?, 'active')";
                    $user_stmt = prepare($user_sql);
                    $full_name = $first_name . ' ' . $last_name;
                    $user_stmt->bind_param("sssss", $full_name, $user_email, $hashed_password, $phone, $address);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception('Failed to create user account');
                    }
                    
                    $user_id = insertId();
                    
                    // Send welcome email with password (in production)
                    // sendEmail($user_email, 'Welcome to SHMS', "Your account has been created. Password: $password");
                }
                
                // Generate doctor ID
                $doctor_id = 'DOC' . str_pad(insertId() + 1, 6, '0', STR_PAD_LEFT);
                
                // Create doctor record
                $sql = "INSERT INTO doctors (user_id, doctor_id, first_name, last_name, specialization, qualification, 
                        experience_years, consultation_fee, phone, email, address, availability, consultation_hours, bio, education) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = prepare($sql);
                $stmt->bind_param("issssidssssss", 
                    $user_id, $doctor_id, $first_name, $last_name, $specialization, $qualification, 
                    $experience_years, $consultation_fee, $phone, $email, $address, $availability, $consultation_hours, $bio, $education
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create doctor record');
                }
                
                $doctor_record_id = insertId();
                
                // Handle file upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadFile($_FILES['profile_image'], '../uploads/doctors', ['jpg', 'jpeg', 'png', 'gif']);
                    
                    if ($upload_result['success']) {
                        $update_sql = "UPDATE doctors SET profile_image = ? WHERE id = ?";
                        $update_stmt = prepare($update_sql);
                        $update_stmt->bind_param("si", $upload_result['filename'], $doctor_record_id);
                        $update_stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Log activity
                logActivity('doctor_created', "New doctor created: $first_name $last_name (ID: $doctor_id)");
                
                // Set success message
                $success = "Doctor created successfully! Doctor ID: $doctor_id";
                if ($create_user_account) {
                    $success .= " User account created with email: $user_email";
                }
                
                // Redirect to doctor view
                header('Refresh: 2; url=view.php?id=' . $doctor_record_id);
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Doctor Information</h5>
            </div>
            <div class="card-body">
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
                
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <!-- Basic Information -->
                    <h6 class="text-primary mb-3">Basic Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            <div class="invalid-feedback">First name is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Last name is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="specialization" class="form-label">Specialization *</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>" required
                                   placeholder="e.g., Cardiology, Neurology, Pediatrics">
                            <div class="invalid-feedback">Specialization is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?>"
                                   placeholder="e.g., MBBS, MD, FRCS">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="experience_years" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                   value="<?php echo htmlspecialchars($_POST['experience_years'] ?? ''); ?>" min="0" max="70">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="consultation_fee" class="form-label">Consultation Fee</label>
                            <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" 
                                   value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? ''); ?>" min="0" step="0.01">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="availability" class="form-label">Availability</label>
                            <select class="form-select" id="availability" name="availability">
                                <option value="full_time" <?php echo ($_POST['availability'] ?? '') === 'full_time' ? 'selected' : ''; ?>>Full Time</option>
                                <option value="part_time" <?php echo ($_POST['availability'] ?? '') === 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                                <option value="on_call" <?php echo ($_POST['availability'] ?? '') === 'on_call' ? 'selected' : ''; ?>>On Call</option>
                                <option value="visiting" <?php echo ($_POST['availability'] ?? '') === 'visiting' ? 'selected' : ''; ?>>Visiting</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h6 class="text-primary mb-3">Contact Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Phone number is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <h6 class="text-primary mb-3">Professional Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="consultation_hours" class="form-label">Consultation Hours</label>
                            <input type="text" class="form-control" id="consultation_hours" name="consultation_hours" 
                                   value="<?php echo htmlspecialchars($_POST['consultation_hours'] ?? ''); ?>"
                                   placeholder="e.g., Mon-Fri 9AM-5PM">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="education" class="form-label">Education & Training</label>
                            <textarea class="form-control" id="education" name="education" rows="3" 
                                      placeholder="List medical degrees, certifications, and training..."><?php echo htmlspecialchars($_POST['education'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="bio" class="form-label">Professional Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" 
                                      placeholder="Brief professional biography..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Profile Image -->
                    <h6 class="text-primary mb-3">Profile Image</h6>
                    <div class="row mb-4">
                        <div class="col-12">
                            <label for="profile_image" class="form-label">Upload Photo</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" 
                                   accept="image/*">
                            <small class="text-muted">Allowed formats: JPG, PNG, GIF. Max size: 5MB</small>
                            <div id="imagePreview" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <!-- User Account Creation -->
                    <h6 class="text-primary mb-3">User Account</h6>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="create_user_account" name="create_user_account" 
                                       <?php echo isset($_POST['create_user_account']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="create_user_account">
                                    Create user account for doctor portal access
                                </label>
                            </div>
                            
                            <div id="userAccountFields" style="display: none;">
                                <div class="col-md-6 mb-3">
                                    <label for="user_email" class="form-label">User Account Email *</label>
                                    <input type="email" class="form-control" id="user_email" name="user_email" 
                                           value="<?php echo htmlspecialchars($_POST['user_email'] ?? ''); ?>">
                                    <small class="text-muted">A random password will be generated and sent to this email</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Doctor
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Side Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Tips</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>All fields marked with * are required</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Doctor ID will be generated automatically</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Specialization helps patients find the right doctor</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Consultation fee is charged per visit</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>User account allows doctor to access portal</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Recent Doctors</h6>
            </div>
            <div class="card-body">
                <?php
                $recent_doctors = query("SELECT doctor_id, first_name, last_name, specialization, created_at 
                                         FROM doctors 
                                         ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($recent_doctors)): ?>
                    <p class="text-muted small">No recent doctors</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_doctors as $doctor): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($doctor['created_at']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle user account fields
document.getElementById('create_user_account').addEventListener('change', function() {
    const userFields = document.getElementById('userAccountFields');
    const userEmail = document.getElementById('user_email');
    
    if (this.checked) {
        userFields.style.display = 'block';
        userEmail.required = true;
        
        // Auto-fill user email with doctor email if available
        const doctorEmail = document.getElementById('email').value;
        if (doctorEmail && !userEmail.value) {
            userEmail.value = doctorEmail;
        }
    } else {
        userFields.style.display = 'none';
        userEmail.required = false;
    }
});

// Image preview
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="mt-2">
                    <img src="${e.target.result}" class="img-thumbnail" style="max-height: 150px;">
                    <div class="mt-1">
                        <small class="text-muted">Selected: ${file.name}</small>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});

// Copy email to user account field
document.getElementById('email').addEventListener('blur', function() {
    const userAccountCheckbox = document.getElementById('create_user_account');
    const userEmail = document.getElementById('user_email');
    
    if (userAccountCheckbox.checked && !userEmail.value) {
        userEmail.value = this.value;
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
</script>

<?php include '../includes/footer.php'; ?>
