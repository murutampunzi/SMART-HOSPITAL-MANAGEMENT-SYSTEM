<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate roles
requireLogin();
requireAnyRole(['admin', 'doctor', 'nurse', 'receptionist']);

$page_title = "Request Lab Test - Smart Hospital Management System";
$page_heading = "Request Lab Test";

$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $test_id = intval($_POST['test_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $priority = sanitizeInput($_POST['priority'] ?? 'normal');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Validations
        if ($patient_id <= 0) {
            $error = 'Please select a valid patient.';
        } elseif ($test_id <= 0) {
            $error = 'Please select a lab test.';
        } elseif ($doctor_id <= 0) {
            $error = 'Please select the requesting doctor.';
        } elseif (!in_array($priority, ['normal', 'urgent', 'stat'])) {
            $error = 'Invalid priority level.';
        } else {
            // Generate Unique Request ID
            $request_id = 'LAB' . date('ymd') . rand(100, 999);
            
            $stmt = prepare("INSERT INTO lab_test_requests (request_id, patient_id, doctor_id, test_id, requested_date, priority, notes, status) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'pending')");
            $stmt->bind_param("siiiss", $request_id, $patient_id, $doctor_id, $test_id, $priority, $notes);
            
            if ($stmt->execute()) {
                logActivity('lab_request_created', "Requested lab test (ID: $request_id) for Patient ID: $patient_id");
                
                // Get patient name for success alert
                $pat_query = query("SELECT first_name, last_name FROM patients WHERE id = $patient_id")->fetch_assoc();
                $pat_name = $pat_query['first_name'] . ' ' . $pat_query['last_name'];
                
                $success = "Lab test requested successfully! Request ID: $request_id for $pat_name";
                
                // Redirect back to patient profile after 2.5 seconds
                header('Refresh: 2.5; url=../patients/view.php?id=' . $patient_id);
            } else {
                $error = 'Failed to submit lab test request. Please try again.';
            }
        }
    }
}

// 1. Fetch Patient Info (if patient_id is provided in GET)
$patient_id = intval($_GET['patient_id'] ?? 0);
$selected_patient = null;
if ($patient_id > 0) {
    $selected_patient = query("SELECT id, patient_id, first_name, last_name, date_of_birth, gender, blood_group FROM patients WHERE id = $patient_id AND status = 'active'")->fetch_assoc();
}

// 2. Fetch All Active Patients (for dropdown/fallback search)
$patients = query("SELECT id, patient_id, first_name, last_name, phone FROM patients WHERE status = 'active' ORDER BY first_name ASC")->fetch_all(MYSQLI_ASSOC);

// 3. Fetch All Available Active Lab Tests
$lab_tests = query("SELECT id, test_id, name, category, price FROM lab_tests WHERE status = 'active' ORDER BY category ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

// 4. Fetch All Active Doctors
$doctors = query("SELECT id, doctor_id, first_name, last_name, specialization FROM doctors WHERE status = 'active' ORDER BY first_name ASC")->fetch_all(MYSQLI_ASSOC);

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
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-microscope fa-2x text-primary me-3"></i>
                    <div>
                        <h5 class="card-title mb-0 fw-bold">Request New Diagnostic Lab Test</h5>
                        <small class="text-muted">Create a diagnostic test request, set clinical priority, and assign to a doctor.</small>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4">
                <form action="request.php?patient_id=<?php echo $patient_id; ?>" method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <!-- Patient Selection -->
                    <div class="mb-4">
                        <h6 class="text-primary fw-bold mb-3"><i class="fas fa-user-injured me-2"></i>Patient Selection</h6>
                        
                        <?php if ($selected_patient): ?>
                            <!-- Pre-selected Patient Card (View Context) -->
                            <div class="p-3 bg-light rounded border border-primary-subtle d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-primary fs-5">
                                        <?php echo htmlspecialchars($selected_patient['first_name'] . ' ' . $selected_patient['last_name']); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <span>Patient ID: <strong class="text-dark"><?php echo htmlspecialchars($selected_patient['patient_id']); ?></strong></span> • 
                                        <span>Gender: <strong><?php echo ucfirst($selected_patient['gender']); ?></strong></span> • 
                                        <span>Blood: <strong><?php echo $selected_patient['blood_group'] ?: 'N/A'; ?></strong></span>
                                    </div>
                                </div>
                                <input type="hidden" name="patient_id" value="<?php echo $selected_patient['id']; ?>">
                                <a href="request.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-exchange-alt me-1"></i>Change Patient
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Select Patient Dropdown (Fallback context) -->
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">Select Patient *</label>
                                <select class="form-select select2" id="patient_id" name="patient_id" required>
                                    <option value="">-- Choose Patient --</option>
                                    <?php foreach ($patients as $pat): ?>
                                        <option value="<?php echo $pat['id']; ?>">
                                            <?php echo htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']); ?> 
                                            (<?php echo htmlspecialchars($pat['patient_id']); ?>) - <?php echo htmlspecialchars($pat['phone']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a patient.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Test Details -->
                    <div class="mb-4">
                        <h6 class="text-primary fw-bold mb-3"><i class="fas fa-vial me-2"></i>Diagnostic Test details</h6>
                        
                        <div class="row">
                            <!-- Select Lab Test -->
                            <div class="col-md-6 mb-3">
                                <label for="test_id" class="form-label">Lab Diagnostic Test *</label>
                                <select class="form-select" id="test_id" name="test_id" required>
                                    <option value="">-- Select Lab Test --</option>
                                    <?php 
                                    $current_category = '';
                                    foreach ($lab_tests as $test): 
                                        if ($current_category !== $test['category']):
                                            if ($current_category !== '') echo '</optgroup>';
                                            $current_category = $test['category'];
                                            echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                        endif;
                                    ?>
                                        <option value="<?php echo $test['id']; ?>">
                                            <?php echo htmlspecialchars($test['name']); ?> 
                                            (<?php echo formatCurrency($test['price']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($current_category !== '') echo '</optgroup>'; ?>
                                </select>
                                <div class="invalid-feedback">Please select a lab diagnostic test.</div>
                            </div>
                            
                            <!-- Select Requesting Doctor -->
                            <div class="col-md-6 mb-3">
                                <label for="doctor_id" class="form-label">Requesting Clinician / Doctor *</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                    <option value="">-- Choose Doctor --</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?> 
                                            (<?php echo htmlspecialchars($doc['specialization']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select the requesting clinician.</div>
                            </div>
                        </div>
                        
                        <!-- Test Priority -->
                        <div class="mb-3">
                            <label class="form-label d-block">Clinical Priority Level *</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="priority" id="priority_normal" value="normal" checked>
                                <label class="btn btn-outline-success py-2" for="priority_normal">
                                    <i class="fas fa-check-circle me-1"></i>Normal
                                </label>
                                
                                <input type="radio" class="btn-check" name="priority" id="priority_urgent" value="urgent">
                                <label class="btn btn-outline-warning py-2" for="priority_urgent">
                                    <i class="fas fa-exclamation-circle me-1"></i>Urgent
                                </label>
                                
                                <input type="radio" class="btn-check" name="priority" id="priority_stat" value="stat">
                                <label class="btn btn-outline-danger py-2" for="priority_stat">
                                    <i class="fas fa-bolt me-1"></i>STAT (Immediate)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Clinical Notes -->
                    <div class="mb-4">
                        <h6 class="text-primary fw-bold mb-3"><i class="fas fa-file-medical-alt me-2"></i>Clinical Notes & Symptoms</h6>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Clinical Indication / Instructions</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      placeholder="Provide diagnostic indications, patient symptoms, or special sample instructions here..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="d-flex justify-content-between border-top pt-4">
                        <?php if ($patient_id > 0): ?>
                            <a href="../patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-secondary px-4 py-2">
                                <i class="fas fa-arrow-left me-2"></i>Back to Patient Profile
                            </a>
                        <?php else: ?>
                            <a href="index.php" class="btn btn-outline-secondary px-4 py-2">
                                <i class="fas fa-times me-2"></i>Cancel Request
                            </a>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary px-5 py-2">
                            <i class="fas fa-paper-plane me-2"></i>Submit Test Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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
