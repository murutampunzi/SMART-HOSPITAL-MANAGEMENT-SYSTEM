<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician']);

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) {
    redirect('index.php?error=Invalid laboratory request ID');
}

// Fetch laboratory request details
$stmt = prepare("SELECT ltr.*, lt.name as test_name, lt.category, lt.preparation_instructions, lt.normal_range as default_range, lt.unit as default_unit, lt.price,
                p.first_name, p.last_name, p.patient_id, p.date_of_birth, p.gender, p.blood_group,
                d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                FROM lab_test_requests ltr 
                JOIN lab_tests lt ON ltr.test_id = lt.id
                JOIN patients p ON ltr.patient_id = p.id
                LEFT JOIN doctors d ON ltr.doctor_id = d.id
                WHERE ltr.id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    redirect('index.php?error=Laboratory request not found');
}

// Check if request is already completed
if ($request['status'] === 'completed') {
    redirect("view.php?id=$request_id&error=Results have already been submitted for this request");
}

$page_title = "Enter Diagnostic Results - Smart Hospital Management System";
$page_heading = "Enter Lab Results";

$error = '';
$success = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $result_value = sanitizeInput($_POST['result_value'] ?? '');
        $normal_range = sanitizeInput($_POST['normal_range'] ?? $request['default_range']);
        $unit = sanitizeInput($_POST['unit'] ?? $request['default_unit']);
        $status = sanitizeInput($_POST['status'] ?? 'normal');
        $comments = sanitizeInput($_POST['comments'] ?? '');
        
        if (empty($result_value)) {
            $error = 'Test result observed value is required.';
        } else {
            try {
                $conn->begin_transaction();
                
                // 1. Insert into lab_results
                $result_id = 'RES' . date('ymd') . rand(100, 999);
                $ins = prepare("INSERT INTO lab_results (result_id, request_id, patient_id, test_id, result_value, normal_range, unit, status, comments, technician_id, verified_by, verified_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $tech_id = $_SESSION['user_id'];
                $verifier_id = $_SESSION['user_id']; // Auto-verified by technician in this flow
                
                $ins->bind_param("siiiisssiii", $result_id, $request_id, $request['patient_id'], $request['test_id'], $result_value, $normal_range, $unit, $status, $comments, $tech_id, $verifier_id);
                
                if (!$ins->execute()) {
                    throw new Exception('Failed to insert diagnostic result into database.');
                }
                
                // 2. Update request status to completed
                $upd = query("UPDATE lab_test_requests SET status = 'completed', updated_at = NOW() WHERE id = $request_id");
                if (!$upd) {
                    throw new Exception('Failed to update laboratory request status.');
                }
                
                $conn->commit();
                logActivity('lab_results_submitted', 'Submitted and verified results for request: ' . $request['request_id']);
                
                $success = 'Diagnostic results submitted and verified successfully! Redirecting you to diagnostic report...';
                header('Refresh: 2; url=view.php?id=' . $request_id);
                
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
    <!-- Patient Info Panel -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-gradient-primary text-white py-3">
                <h5 class="card-title mb-0"><i class="fas fa-id-card me-2"></i>Patient Profile</h5>
            </div>
            <div class="card-body">
                <h4 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></h4>
                <span class="badge bg-secondary mb-3">Patient ID: <?php echo htmlspecialchars($request['patient_id']); ?></span>
                
                <hr>
                
                <div class="row g-2 small">
                    <div class="col-6 text-muted">Gender:</div>
                    <div class="col-6 fw-bold text-dark"><?php echo ucfirst($request['gender']); ?></div>
                    
                    <div class="col-6 text-muted">DOB:</div>
                    <div class="col-6 fw-bold text-dark"><?php echo formatDate($request['date_of_birth']); ?></div>
                    
                    <div class="col-6 text-muted">Blood Group:</div>
                    <div class="col-6 fw-bold text-danger"><?php echo htmlspecialchars($request['blood_group'] ?: 'Not Specified'); ?></div>
                    
                    <div class="col-6 text-muted">Requested By:</div>
                    <div class="col-6 fw-bold text-dark">Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></div>
                    
                    <div class="col-6 text-muted">Requested Date:</div>
                    <div class="col-6 fw-bold text-dark"><?php echo formatDate($request['requested_date']); ?></div>
                </div>
                
                <hr>
                
                <div class="mb-0">
                    <h6 class="text-primary fw-bold mb-2"><i class="fas fa-notes-medical me-1"></i>Clinical Indication / Notes</h6>
                    <p class="text-muted bg-light p-2 rounded small mb-0"><?php echo nl2br(htmlspecialchars($request['notes'] ?: 'No clinical indication provided.')); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagnostic Form Panel -->
    <div class="col-lg-8">
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

        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-gradient-success text-white py-3">
                <h5 class="card-title mb-0"><i class="fas fa-clipboard-check me-2"></i>Diagnostic Findings & Observed Parameters</h5>
            </div>
            
            <div class="card-body p-4">
                <div class="mb-4">
                    <span class="text-muted small">Diagnostic Test:</span>
                    <h3 class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($request['test_name']); ?></h3>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($request['category']); ?></span>
                </div>
                
                <hr class="my-4">
                
                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <div class="mb-4">
                        <label for="result_value" class="form-label fw-bold">Observed Test Value / Finding *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="fas fa-vial text-primary"></i></span>
                            <input type="text" class="form-control" id="result_value" name="result_value" 
                                   placeholder="e.g. 14.5, Negative, Reactive, Active" required>
                            <div class="invalid-feedback">Please enter the test result finding.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="unit" class="form-label fw-bold">Unit of Measure</label>
                            <input type="text" class="form-control" id="unit" name="unit" 
                                   value="<?php echo htmlspecialchars($request['default_unit'] ?: ''); ?>"
                                   placeholder="e.g. g/dL, mg/dL, cells/mm³">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="normal_range" class="form-label fw-bold">Normal Reference Range</label>
                            <input type="text" class="form-control" id="normal_range" name="normal_range" 
                                   value="<?php echo htmlspecialchars($request['default_range'] ?: ''); ?>"
                                   placeholder="e.g. 12.0 - 16.0, Negative">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label fw-bold">Diagnostic Status Flag *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="normal" selected>Normal</option>
                            <option value="abnormal">Abnormal (Out of range)</option>
                            <option value="critical">Critical (Immediate alert required)</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="comments" class="form-label fw-bold">Pathologist Comments / Clinical Feedback</label>
                        <textarea class="form-control" id="comments" name="comments" rows="4" 
                                  placeholder="Provide clinical observations, diagnoses, or technician annotations..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-file-signature me-2"></i>Submit & Sign Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
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
