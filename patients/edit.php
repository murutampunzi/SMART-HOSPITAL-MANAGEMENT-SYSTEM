<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'receptionist']);

// Get patient ID
$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    redirect('index.php?error=Invalid patient ID');
}

// Get patient details
$sql = "SELECT p.*, u.email as user_email 
        FROM patients p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?";
$stmt = prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    redirect('index.php?error=Patient not found');
}

$page_title = "Edit Patient - Smart Hospital Management System";
$page_heading = "Edit Patient";

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
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
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
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Update patient record
                $sql = "UPDATE patients SET 
                        first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, 
                        blood_group = ?, phone = ?, email = ?, address = ?, 
                        emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?, 
                        medical_history = ?, allergies = ?, current_medications = ?, 
                        insurance_provider = ?, insurance_policy_number = ?, status = ?
                        WHERE id = ?";
                
                $stmt = prepare($sql);
                $stmt->bind_param("sssssssssssssssi", 
                    $first_name, $last_name, $date_of_birth, $gender, 
                    $blood_group, $phone, $email, $address, 
                    $emergency_contact_name, $emergency_contact_phone, $emergency_contact_relation, 
                    $medical_history, $allergies, $current_medications, 
                    $insurance_provider, $insurance_policy_number, $status, $patient_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update patient record');
                }
                
                // Handle file upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadFile($_FILES['profile_image'], '../uploads/patients', ['jpg', 'jpeg', 'png', 'gif']);
                    
                    if ($upload_result['success']) {
                        // Delete old image if exists
                        if ($patient['profile_image']) {
                            $old_image_path = '../uploads/patients/' . $patient['profile_image'];
                            if (file_exists($old_image_path)) {
                                unlink($old_image_path);
                            }
                        }
                        
                        $update_sql = "UPDATE patients SET profile_image = ? WHERE id = ?";
                        $update_stmt = prepare($update_sql);
                        $update_stmt->bind_param("si", $upload_result['filename'], $patient_id);
                        $update_stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Log activity
                logActivity('patient_updated', "Patient updated: $first_name $last_name (ID: {$patient['patient_id']})");
                
                // Set success message
                $success = "Patient updated successfully!";
                
                // Refresh patient data
                $sql = "SELECT p.*, u.email as user_email 
                        FROM patients p 
                        LEFT JOIN users u ON p.user_id = u.id 
                        WHERE p.id = ?";
                $stmt = prepare($sql);
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $patient = $stmt->get_result()->fetch_assoc();
                
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
                <h5 class="card-title mb-0">Edit Patient Information</h5>
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
                                   value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                            <div class="invalid-feedback">First name is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                            <div class="invalid-feedback">Last name is required</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>" required>
                            <div class="invalid-feedback">Date of birth is required</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="gender" class="form-label">Gender *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $patient['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $patient['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $patient['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">Gender is required</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="blood_group" class="form-label">Blood Group</label>
                            <select class="form-select" id="blood_group" name="blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+" <?php echo $patient['blood_group'] === 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo $patient['blood_group'] === 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo $patient['blood_group'] === 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo $patient['blood_group'] === 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="O+" <?php echo $patient['blood_group'] === 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo $patient['blood_group'] === 'O-' ? 'selected' : ''; ?>>O-</option>
                                <option value="AB+" <?php echo $patient['blood_group'] === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo $patient['blood_group'] === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h6 class="text-primary mb-3">Contact Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                            <div class="invalid-feedback">Phone number is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($patient['email']); ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact -->
                    <h6 class="text-primary mb-3">Emergency Contact</h6>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_name" class="form-label">Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact_name']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="emergency_contact_relation" class="form-label">Relationship</label>
                            <input type="text" class="form-control" id="emergency_contact_relation" name="emergency_contact_relation" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact_relation']); ?>"
                                   placeholder="e.g., Spouse, Parent, Sibling">
                        </div>
                    </div>
                    
                    <!-- Medical Information -->
                    <h6 class="text-primary mb-3">Medical Information</h6>
                    <div class="row mb-4">
                        <div class="col-12 mb-3">
                            <label for="medical_history" class="form-label">Medical History</label>
                            <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                      placeholder="Previous illnesses, surgeries, chronic conditions..."><?php echo htmlspecialchars($patient['medical_history']); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="allergies" class="form-label">Allergies</label>
                            <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                      placeholder="Food allergies, medication allergies, environmental allergies..."><?php echo htmlspecialchars($patient['allergies']); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="current_medications" class="form-label">Current Medications</label>
                            <textarea class="form-control" id="current_medications" name="current_medications" rows="2" 
                                      placeholder="List any medications the patient is currently taking..."><?php echo htmlspecialchars($patient['current_medications']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Insurance Information -->
                    <h6 class="text-primary mb-3">Insurance Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="insurance_provider" class="form-label">Insurance Provider</label>
                            <input type="text" class="form-control" id="insurance_provider" name="insurance_provider" 
                                   value="<?php echo htmlspecialchars($patient['insurance_provider']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="insurance_policy_number" class="form-label">Policy Number</label>
                            <input type="text" class="form-control" id="insurance_policy_number" name="insurance_policy_number" 
                                   value="<?php echo htmlspecialchars($patient['insurance_policy_number']); ?>">
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <h6 class="text-primary mb-3">Status</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Patient Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo $patient['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $patient['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="discharged" <?php echo $patient['status'] === 'discharged' ? 'selected' : ''; ?>>Discharged</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Profile Image -->
                    <h6 class="text-primary mb-3">Profile Image</h6>
                    <div class="row mb-4">
                        <div class="col-12">
                            <label for="profile_image" class="form-label">Update Photo</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" 
                                   accept="image/*">
                            <small class="text-muted">Allowed formats: JPG, PNG, GIF. Max size: 5MB</small>
                            <?php if ($patient['profile_image']): ?>
                                <div class="mt-2">
                                    <img src="../uploads/patients/<?php echo htmlspecialchars($patient['profile_image']); ?>" 
                                         alt="Current profile" class="img-thumbnail" style="max-height: 150px;">
                                </div>
                            <?php endif; ?>
                            <div id="imagePreview" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="view.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Patient
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
                <h6 class="card-title mb-0">Patient Information</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if ($patient['profile_image']): ?>
                        <img src="../uploads/patients/<?php echo htmlspecialchars($patient['profile_image']); ?>" 
                             alt="Profile" class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center img-thumbnail" 
                             style="width: 100px; height: 100px; font-size: 2.5rem; margin: 0 auto;">
                            <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <h5 class="mt-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h5>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                </div>
                <hr>
                <div class="small">
                    <div class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></div>
                    <div class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($patient['email'] ?: 'N/A'); ?></div>
                    <div class="mb-2"><strong>Gender:</strong> <?php echo ucfirst($patient['gender']); ?></div>
                    <div class="mb-2"><strong>Blood Group:</strong> <?php echo $patient['blood_group'] ?: 'N/A'; ?></div>
                    <div class="mb-2"><strong>Registered:</strong> <?php echo formatDate($patient['created_at']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View Patient
                    </a>
                    <a href="../appointments/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-success">
                        <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                    </a>
                    <a href="../billing/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-warning">
                        <i class="fas fa-file-invoice me-2"></i>Create Invoice
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
                        <small class="text-muted">New: ${file.name}</small>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
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
    
    console.log('Calculated age:', ageDisplay);
});
</script>

<?php include '../includes/footer.php'; ?>
