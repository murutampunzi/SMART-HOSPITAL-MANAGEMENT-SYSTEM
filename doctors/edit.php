<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin']);

// Get doctor ID
$doctor_id = intval($_GET['id'] ?? 0);
if ($doctor_id <= 0) {
    redirect('index.php?error=Invalid doctor ID');
}

// Get doctor details
$sql = "SELECT d.*, u.email as user_email 
        FROM doctors d 
        LEFT JOIN users u ON d.user_id = u.id 
        WHERE d.id = ?";
$stmt = prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    redirect('index.php?error=Doctor not found');
}

$page_title = "Edit Doctor - Smart Hospital Management System";
$page_heading = "Edit Doctor";

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
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
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
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Update doctor record
                $sql = "UPDATE doctors SET 
                        first_name = ?, last_name = ?, specialization = ?, qualification = ?, 
                        experience_years = ?, consultation_fee = ?, phone = ?, email = ?, address = ?, 
                        availability = ?, consultation_hours = ?, bio = ?, education = ?, status = ?
                        WHERE id = ?";
                
                $stmt = prepare($sql);
                $stmt->bind_param("ssssidssssssi", 
                    $first_name, $last_name, $specialization, $qualification, 
                    $experience_years, $consultation_fee, $phone, $email, $address, 
                    $availability, $consultation_hours, $bio, $education, $status, $doctor_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update doctor record');
                }
                
                // Handle file upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadFile($_FILES['profile_image'], '../uploads/doctors', ['jpg', 'jpeg', 'png', 'gif']);
                    
                    if ($upload_result['success']) {
                        // Delete old image if exists
                        if ($doctor['profile_image']) {
                            $old_image_path = '../uploads/doctors/' . $doctor['profile_image'];
                            if (file_exists($old_image_path)) {
                                unlink($old_image_path);
                            }
                        }
                        
                        $update_sql = "UPDATE doctors SET profile_image = ? WHERE id = ?";
                        $update_stmt = prepare($update_sql);
                        $update_stmt->bind_param("si", $upload_result['filename'], $doctor_id);
                        $update_stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                // Log activity
                logActivity('doctor_updated', "Doctor updated: $first_name $last_name (ID: {$doctor['doctor_id']})");
                
                // Set success message
                $success = "Doctor updated successfully!";
                
                // Refresh doctor data
                $sql = "SELECT d.*, u.email as user_email 
                        FROM doctors d 
                        LEFT JOIN users u ON d.user_id = u.id 
                        WHERE d.id = ?";
                $stmt = prepare($sql);
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $doctor = $stmt->get_result()->fetch_assoc();
                
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
                <h5 class="card-title mb-0">Edit Doctor Information</h5>
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
                                   value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                            <div class="invalid-feedback">First name is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                            <div class="invalid-feedback">Last name is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="specialization" class="form-label">Specialization *</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required
                                   placeholder="e.g., Cardiology, Neurology, Pediatrics">
                            <div class="invalid-feedback">Specialization is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($doctor['qualification']); ?>"
                                   placeholder="e.g., MBBS, MD, FRCS">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="experience_years" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                   value="<?php echo htmlspecialchars($doctor['experience_years']); ?>" min="0" max="70">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="consultation_fee" class="form-label">Consultation Fee</label>
                            <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" 
                                   value="<?php echo htmlspecialchars($doctor['consultation_fee']); ?>" min="0" step="0.01">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="availability" class="form-label">Availability</label>
                            <select class="form-select" id="availability" name="availability">
                                <option value="full_time" <?php echo $doctor['availability'] === 'full_time' ? 'selected' : ''; ?>>Full Time</option>
                                <option value="part_time" <?php echo $doctor['availability'] === 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                                <option value="on_call" <?php echo $doctor['availability'] === 'on_call' ? 'selected' : ''; ?>>On Call</option>
                                <option value="visiting" <?php echo $doctor['availability'] === 'visiting' ? 'selected' : ''; ?>>Visiting</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h6 class="text-primary mb-3">Contact Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                            <div class="invalid-feedback">Phone number is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($doctor['email']); ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($doctor['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <h6 class="text-primary mb-3">Professional Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="consultation_hours" class="form-label">Consultation Hours</label>
                            <input type="text" class="form-control" id="consultation_hours" name="consultation_hours" 
                                   value="<?php echo htmlspecialchars($doctor['consultation_hours']); ?>"
                                   placeholder="e.g., Mon-Fri 9AM-5PM">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="education" class="form-label">Education & Training</label>
                            <textarea class="form-control" id="education" name="education" rows="3" 
                                      placeholder="List medical degrees, certifications, and training..."><?php echo htmlspecialchars($doctor['education']); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="bio" class="form-label">Professional Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" 
                                      placeholder="Brief professional biography..."><?php echo htmlspecialchars($doctor['bio']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <h6 class="text-primary mb-3">Status</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Doctor Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo $doctor['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $doctor['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="on_leave" <?php echo $doctor['status'] === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
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
                            <?php if ($doctor['profile_image']): ?>
                                <div class="mt-2">
                                    <img src="../uploads/doctors/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
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
                                <a href="view.php?id=<?php echo $doctor_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Doctor
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
                <h6 class="card-title mb-0">Doctor Information</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if ($doctor['profile_image']): ?>
                        <img src="../uploads/doctors/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                             alt="Profile" class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center img-thumbnail" 
                             style="width: 100px; height: 100px; font-size: 2.5rem; margin: 0 auto;">
                            <i class="fas fa-user-md"></i>
                        </div>
                    <?php endif; ?>
                    <h5 class="mt-2"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h5>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($doctor['doctor_id']); ?></span>
                </div>
                <hr>
                <div class="small">
                    <div class="mb-2"><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></div>
                    <div class="mb-2"><strong>Qualification:</strong> <?php echo htmlspecialchars($doctor['qualification'] ?: 'N/A'); ?></div>
                    <div class="mb-2"><strong>Experience:</strong> <?php echo $doctor['experience_years']; ?> years</div>
                    <div class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?></div>
                    <div class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email'] ?: 'N/A'); ?></div>
                    <div class="mb-2"><strong>Fee:</strong> <?php echo formatCurrency($doctor['consultation_fee']); ?></div>
                    <div class="mb-2"><strong>Registered:</strong> <?php echo formatDate($doctor['created_at']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $doctor_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View Doctor
                    </a>
                    <a href="../appointments/create.php?doctor_id=<?php echo $doctor_id; ?>" class="btn btn-outline-success">
                        <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-2"></i>All Doctors
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
</script>

<?php include '../includes/footer.php'; ?>
