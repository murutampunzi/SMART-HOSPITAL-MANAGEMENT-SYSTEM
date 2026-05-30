<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'receptionist']);

$page_title = "Add Patient - Smart Hospital Management System";
$page_heading = "Add New Patient";

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
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $blood_group = sanitizeInput($_POST['blood_group'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
        $emergency_contact_relation = sanitizeInput($_POST['emergency_contact_relation'] ?? '');
        $medical_history = sanitizeInput($_POST['medical_history'] ?? '');
        $allergies = sanitizeInput($_POST['allergies'] ?? '');
        $current_medications = sanitizeInput($_POST['current_medications'] ?? '');
        $insurance_provider = sanitizeInput($_POST['insurance_provider'] ?? '');
        $insurance_policy_number = sanitizeInput($_POST['insurance_policy_number'] ?? '');
        $create_user_account = isset($_POST['create_user_account']);
        $user_email = sanitizeInput($_POST['user_email'] ?? '');
        
        // Validate required fields
        $required_fields = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'date_of_birth' => $date_of_birth,
            'gender' => $gender,
            'phone' => $phone
        ];
        
        $validation_errors = validateRequired($required_fields);
        if (!empty($validation_errors)) {
            $error = reset($validation_errors);
        } elseif (!validateDate($date_of_birth)) {
            $error = 'Invalid date of birth';
        } elseif (!in_array($gender, ['male', 'female', 'other'])) {
            $error = 'Invalid gender selected';
        } elseif (!empty($blood_group) && !in_array($blood_group, ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])) {
            $error = 'Invalid blood group selected';
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
                                 VALUES (?, ?, ?, 'patient', ?, ?, 'active')";
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
                
                // Generate patient ID
                $patient_id = 'PAT' . str_pad(insertId() + 1, 6, '0', STR_PAD_LEFT);
                
                // Create patient record
                $sql = "INSERT INTO patients (user_id, patient_id, first_name, last_name, date_of_birth, gender, blood_group, phone, email, address, emergency_contact_name, emergency_contact_phone, emergency_contact_relation, medical_history, allergies, current_medications, insurance_provider, insurance_policy_number) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = prepare($sql);
                $stmt->bind_param("issssssssssssssss", 
                    $user_id, $patient_id, $first_name, $last_name, $date_of_birth, $gender, 
                    $blood_group, $phone, $email, $address, $emergency_contact_name, 
                    $emergency_contact_phone, $emergency_contact_relation, $medical_history, 
                    $allergies, $current_medications, $insurance_provider, $insurance_policy_number
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create patient record');
                }
                
                $patient_record_id = insertId();
                
                // Handle file upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadFile($_FILES['profile_image'], '../uploads/patients', ['jpg', 'jpeg', 'png', 'gif']);
                    
                    if ($upload_result['success']) {
                        $update_sql = "UPDATE patients SET profile_image = ? WHERE id = ?";
                        $update_stmt = prepare($update_sql);
                        $update_stmt->bind_param("si", $upload_result['filename'], $patient_record_id);
                        $update_stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Log activity
                logActivity('patient_created', "New patient created: $first_name $last_name (ID: $patient_id)");
                
                // Set success message
                $success = "Patient created successfully! Patient ID: $patient_id";
                if ($create_user_account) {
                    $success .= " User account created with email: $user_email";
                }
                
                // Redirect to patient view
                header('Refresh: 2; url=view.php?id=' . $patient_record_id);
                
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
                <h5 class="card-title mb-0">Patient Information</h5>
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
                        
                        <div class="col-md-4 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Date of birth is required</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="gender" class="form-label">Gender *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">Gender is required</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="blood_group" class="form-label">Blood Group</label>
                            <select class="form-select" id="blood_group" name="blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+" <?php echo ($_POST['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($_POST['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($_POST['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($_POST['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="O+" <?php echo ($_POST['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($_POST['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                <option value="AB+" <?php echo ($_POST['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($_POST['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
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
                    
                    <!-- Emergency Contact -->
                    <h6 class="text-primary mb-3">Emergency Contact</h6>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_name" class="form-label">Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_relation" class="form-label">Relationship</label>
                            <input type="text" class="form-control" id="emergency_contact_relation" name="emergency_contact_relation" 
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact_relation'] ?? ''); ?>"
                                   placeholder="e.g., Spouse, Parent, Sibling">
                        </div>
                    </div>
                    
                    <!-- Medical Information -->
                    <h6 class="text-primary mb-3">Medical Information</h6>
                    <div class="row mb-4">
                        <div class="col-12 mb-3">
                            <label for="medical_history" class="form-label">Medical History</label>
                            <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                      placeholder="Previous illnesses, surgeries, chronic conditions..."><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="allergies" class="form-label">Allergies</label>
                            <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                      placeholder="Food allergies, medication allergies, environmental allergies..."><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="current_medications" class="form-label">Current Medications</label>
                            <textarea class="form-control" id="current_medications" name="current_medications" rows="2" 
                                      placeholder="List any medications the patient is currently taking..."><?php echo htmlspecialchars($_POST['current_medications'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Insurance Information -->
                    <h6 class="text-primary mb-3">Insurance Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="insurance_provider" class="form-label">Insurance Provider</label>
                            <input type="text" class="form-control" id="insurance_provider" name="insurance_provider" 
                                   value="<?php echo htmlspecialchars($_POST['insurance_provider'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="insurance_policy_number" class="form-label">Policy Number</label>
                            <input type="text" class="form-control" id="insurance_policy_number" name="insurance_policy_number" 
                                   value="<?php echo htmlspecialchars($_POST['insurance_policy_number'] ?? ''); ?>">
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
                                    Create user account for patient portal access
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
                                    <i class="fas fa-save me-2"></i>Save Patient
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
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Patient ID will be generated automatically</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Emergency contact is important for patient safety</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Medical history helps in better treatment</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>User account allows patient to access portal</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Recent Patients</h6>
            </div>
            <div class="card-body">
                <?php
                $recent_patients = query("SELECT patient_id, first_name, last_name, created_at 
                                         FROM patients 
                                         ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($recent_patients)): ?>
                    <p class="text-muted small">No recent patients</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_patients as $patient): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($patient['patient_id']); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($patient['created_at']); ?></small>
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
        
        // Auto-fill user email with patient email if available
        const patientEmail = document.getElementById('email').value;
        if (patientEmail && !userEmail.value) {
            userEmail.value = patientEmail;
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

// Auto-calculate age from date of birth
document.getElementById('date_of_birth').addEventListener('change', function() {
    const dob = new Date(this.value);
    const today = new Date();
    const age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        const ageDisplay = age - 1;
    } else {
        const ageDisplay = age;
    }
    
    // You could display the calculated age here if needed
    console.log('Calculated age:', ageDisplay);
});
</script>

<?php include '../includes/footer.php'; ?>
