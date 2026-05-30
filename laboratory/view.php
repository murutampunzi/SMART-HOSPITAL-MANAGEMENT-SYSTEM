<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) {
    redirect('laboratory/index.php?error=Invalid laboratory request ID');
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
    redirect('laboratory/index.php?error=Laboratory request not found');
}

$page_title = "Laboratory Request " . $request['request_id'] . " - Smart Hospital Management System";
$page_heading = "Lab Request details";

$error = '';
$success = '';

// Handle POST actions for lab sample / result submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'collect_sample' && (hasRole('admin') || hasRole('lab_technician'))) {
            $update = query("UPDATE lab_test_requests SET status = 'sample_collected', sample_collected = 1, sample_collected_date = NOW(), sample_collected_by = " . intval($_SESSION['user_id']) . " WHERE id = $request_id");
            if ($update) {
                logActivity('lab_sample_collected', 'Collected sample for request: ' . $request['request_id']);
                $success = 'Diagnostic sample collected successfully!';
                $request['status'] = 'sample_collected';
            } else {
                $error = 'Failed to collect sample. Please try again.';
            }
        } elseif ($action === 'cancel_request' && (hasRole('admin') || hasRole('doctor'))) {
            $update = query("UPDATE lab_test_requests SET status = 'cancelled' WHERE id = $request_id");
            if ($update) {
                logActivity('lab_request_cancelled', 'Cancelled lab request: ' . $request['request_id']);
                $success = 'Lab request cancelled successfully!';
                $request['status'] = 'cancelled';
            } else {
                $error = 'Failed to cancel request.';
            }
        } elseif ($action === 'submit_results' && (hasRole('admin') || hasRole('lab_technician'))) {
            $result_value = sanitizeInput($_POST['result_value'] ?? '');
            $normal_range = sanitizeInput($_POST['normal_range'] ?? $request['default_range']);
            $unit = sanitizeInput($_POST['unit'] ?? $request['default_unit']);
            $status = sanitizeInput($_POST['status'] ?? 'normal');
            $comments = sanitizeInput($_POST['comments'] ?? '');
            
            if (empty($result_value)) {
                $error = 'Test result value is required.';
            } else {
                try {
                    $conn->begin_transaction();
                    
                    // 1. Insert into lab_results
                    $result_id = 'RES' . date('ymd') . rand(100, 999);
                    $ins = prepare("INSERT INTO lab_results (result_id, request_id, patient_id, test_id, result_value, normal_range, unit, status, comments, technician_id, verified_by, verified_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $tech_id = $_SESSION['user_id'];
                    $verifier_id = $_SESSION['user_id']; // auto-verified by technician in this basic flow
                    
                    $ins->bind_param("siiiisssiii", $result_id, $request_id, $request['patient_id'], $request['test_id'], $result_value, $normal_range, $unit, $status, $comments, $tech_id, $verifier_id);
                    
                    if (!$ins->execute()) {
                        throw new Exception('Failed to insert diagnostic result.');
                    }
                    
                    // 2. Update request status to completed
                    $upd = query("UPDATE lab_test_requests SET status = 'completed' WHERE id = $request_id");
                    if (!$upd) {
                        throw new Exception('Failed to update lab request status.');
                    }
                    
                    $conn->commit();
                    logActivity('lab_results_submitted', 'Submitted results for request: ' . $request['request_id']);
                    $success = 'Diagnostic results submitted and verified successfully!';
                    $request['status'] = 'completed';
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Fetch associated results
$res_stmt = prepare("SELECT lr.*, t.name as tech_name, v.name as verifier_name 
                    FROM lab_results lr 
                    LEFT JOIN users t ON lr.technician_id = t.id
                    LEFT JOIN users v ON lr.verified_by = v.id
                    WHERE lr.request_id = ?");
$res_stmt->bind_param("i", $request_id);
$res_stmt->execute();
$result_record = $res_stmt->get_result()->fetch_assoc();

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
    <!-- Left Column: Diagnostic Request & Findings -->
    <div class="col-lg-8 mb-4">
        <!-- Request Details -->
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-microscope text-primary me-2"></i>Diagnostic Request Info</h5>
                <span class="badge bg-secondary py-2 px-3">Request ID: <?php echo htmlspecialchars($request['request_id']); ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 text-start">
                        <label class="form-label text-primary fw-bold mb-1">Requested Diagnostic Test</label>
                        <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($request['test_name']); ?></h4>
                        <span class="badge bg-info text-uppercase"><?php echo htmlspecialchars($request['category']); ?></span>
                    </div>
                    
                    <div class="col-md-6 mb-3 text-md-end">
                        <label class="form-label text-primary fw-bold mb-1">Priority & Status</label>
                        <div>
                            <?php 
                            $priorityColors = [
                                'normal' => 'success',
                                'urgent' => 'warning',
                                'stat' => 'danger'
                            ];
                            $pColor = $priorityColors[$request['priority']] ?? 'success';
                            ?>
                            <span class="badge bg-<?php echo $pColor; ?> text-uppercase py-2 px-3 fs-6">
                                <?php echo ucfirst($request['priority']); ?> Priority
                            </span>
                        </div>
                        <div class="mt-2">
                            <?php echo getStatusBadge($request['status']); ?>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <h6 class="text-primary fw-bold mb-2">Clinical Indication / Notes</h6>
                        <p class="text-muted bg-light p-3 rounded border"><?php echo nl2br(htmlspecialchars($request['notes'] ?: 'No clinical indication provided.')); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-2">
                        <h6 class="text-primary fw-bold mb-2">Test Parameters (Defaults)</h6>
                        <ul class="list-unstyled small text-muted">
                            <li class="mb-2"><i class="fas fa-vial me-2"></i><strong>Standard Unit:</strong> <?php echo htmlspecialchars($request['default_unit'] ?: 'N/A'); ?></li>
                            <li class="mb-2"><i class="fas fa-notes-medical me-2"></i><strong>Standard Reference:</strong> <?php echo htmlspecialchars($request['default_range'] ?: 'N/A'); ?></li>
                            <li class="mb-0"><i class="fas fa-info-circle me-2"></i><strong>Instructions:</strong> <?php echo htmlspecialchars($request['preparation_instructions'] ?: 'Standard preparation procedures'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Diagnostic Laboratory Report (Show only if completed) -->
        <?php if ($result_record): ?>
            <div class="card shadow-sm border-0 mb-4 animate fade-in">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-file-medical-alt text-light me-2"></i>Diagnostic Laboratory Report</h5>
                </div>
                <div class="card-body p-4 bg-light">
                    <!-- Report Header -->
                    <div class="row mb-4">
                        <div class="col-sm-6 text-start">
                            <div class="text-muted small"><strong>Report ID:</strong></div>
                            <span class="badge bg-secondary py-1 px-2"><?php echo htmlspecialchars($result_record['result_id']); ?></span>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                            <div class="text-muted small"><strong>Analysis Date:</strong></div>
                            <div class="fw-semibold text-dark"><?php echo formatDateTime($result_record['verified_date']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Table showing diagnostic details -->
                    <div class="table-responsive mb-4 shadow-sm rounded">
                        <table class="table table-bordered table-hover align-middle mb-0 bg-white text-center">
                            <thead class="table-dark">
                                <tr>
                                    <th>Parameter / Test Name</th>
                                    <th>Observed Value</th>
                                    <th>Unit</th>
                                    <th>Flag Status</th>
                                    <th>Normal Reference Range</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold text-dark text-start ps-3"><?php echo htmlspecialchars($request['test_name']); ?></td>
                                    <td class="fs-5 fw-bold text-primary"><?php echo htmlspecialchars($result_record['result_value']); ?></td>
                                    <td><?php echo htmlspecialchars($result_record['unit'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $flagColors = [
                                            'normal' => 'success',
                                            'abnormal' => 'warning',
                                            'critical' => 'danger'
                                        ];
                                        $fColor = $flagColors[$result_record['status']] ?? 'success';
                                        ?>
                                        <span class="badge bg-<?php echo $fColor; ?> text-uppercase py-2 px-3">
                                            <?php echo ucfirst($result_record['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($result_record['normal_range'] ?: 'N/A'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($result_record['comments']): ?>
                        <div class="mb-4 text-start">
                            <h6 class="text-primary fw-bold mb-2">Pathologist / Technician Comments:</h6>
                            <p class="text-muted bg-white p-3 rounded border"><?php echo nl2br(htmlspecialchars($result_record['comments'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <div class="row align-items-center">
                        <div class="col-sm-6 text-start">
                            <div class="text-muted small"><strong>Analyzing Technician:</strong></div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($result_record['tech_name'] ?: 'Pathology Dept'); ?></div>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                            <div class="text-muted small"><strong>Signed & Verified By:</strong></div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($result_record['verifier_name'] ?: 'Lab Pathologist'); ?></div>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i>Digitally Signed & Validated</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column: Patient Info & Actions -->
    <div class="col-lg-4 mb-4">
        <!-- Patient Info -->
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-user-injured text-primary me-2"></i>Patient Information</h6>
            </div>
            <div class="card-body">
                <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></h5>
                <small class="text-muted d-block mb-3">Patient ID: <?php echo htmlspecialchars($request['patient_id']); ?></small>
                
                <hr class="my-3">
                
                <div class="small text-muted mb-2"><i class="fas fa-venus-mars me-2"></i><strong>Gender:</strong> <?php echo ucfirst($request['gender']); ?></div>
                <div class="small text-muted mb-2"><i class="fas fa-tint me-2"></i><strong>Blood Group:</strong> <?php echo $request['blood_group'] ?: 'N/A'; ?></div>
                <div class="small text-muted mb-2"><i class="fas fa-calendar-day me-2"></i><strong>DOB:</strong> <?php echo formatDate($request['date_of_birth']); ?></div>
                <div class="small text-muted mb-0"><i class="fas fa-user-md me-2"></i><strong>Requested By:</strong> Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></div>
            </div>
        </div>
        
        <!-- Sample Info (If collected) -->
        <?php if ($request['sample_collected']): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold"><i class="fas fa-flask text-primary me-2"></i>Specimen Sample Collected</h6>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-2"><i class="far fa-calendar-alt me-2"></i><strong>Date Collected:</strong> <?php echo formatDateTime($request['sample_collected_date']); ?></div>
                    <div class="small text-muted mb-0"><i class="far fa-check-square me-2"></i><strong>Specimen Status:</strong> Received & Logged</div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Lab Actions Card -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-sliders-h text-primary me-2"></i>Laboratory Workspace Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <!-- Collect Sample Form -->
                    <?php if ($request['status'] === 'pending' && (hasRole('admin') || hasRole('lab_technician'))): ?>
                        <form action="view.php?id=<?php echo $request_id; ?>" method="POST">
                            <?php echo getCSRFInput(); ?>
                            <input type="hidden" name="action" value="collect_sample">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-flask me-2"></i>Collect Specimen Sample
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Submit Results Button (Show modal trigger) -->
                    <?php if ($request['status'] === 'sample_collected' && (hasRole('admin') || hasRole('lab_technician'))): ?>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resultsModal">
                            <i class="fas fa-clipboard-check me-2"></i>Enter Diagnostic Findings
                        </button>
                    <?php endif; ?>
                    
                    <!-- Print Results -->
                    <?php if ($request['status'] === 'completed'): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Diagnostic Report
                        </button>
                    <?php endif; ?>
                    
                    <!-- Cancel Request Form -->
                    <?php if (($request['status'] === 'pending' || $request['status'] === 'sample_collected') && (hasRole('admin') || hasRole('doctor'))): ?>
                        <form action="view.php?id=<?php echo $request_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this lab request?');">
                            <?php echo getCSRFInput(); ?>
                            <input type="hidden" name="action" value="cancel_request">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-times me-2"></i>Cancel Request
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Lab Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit Results Modal -->
<?php if ($request['status'] === 'sample_collected' && (hasRole('admin') || hasRole('lab_technician'))): ?>
    <div class="modal fade" id="resultsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check text-primary me-2"></i>Enter Test Findings & Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="view.php?id=<?php echo $request_id; ?>" method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    <input type="hidden" name="action" value="submit_results">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="result_value" class="form-label">Observed Test Value / Finding *</label>
                            <input type="text" class="form-control" id="result_value" name="result_value" placeholder="e.g. 14.5, Negative, Active" required>
                            <div class="invalid-feedback">Please enter the test result finding.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="unit" class="form-label">Unit of Measure</label>
                                <input type="text" class="form-control" id="unit" name="unit" value="<?php echo htmlspecialchars($request['default_unit'] ?: ''); ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label for="normal_range" class="form-label">Normal Reference Range</label>
                                <input type="text" class="form-control" id="normal_range" name="normal_range" value="<?php echo htmlspecialchars($request['default_range'] ?: ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status_select" class="form-label">Diagnostic Flag Status *</label>
                            <select class="form-select" id="status_select" name="status" required>
                                <option value="normal">Normal</option>
                                <option value="abnormal">Abnormal (Out of range)</option>
                                <option value="critical">Critical (Immediate alert)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Pathologist Comments / Clinical Feedback</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Provide clinical descriptions or annotations..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit & Sign Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

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

<?php if (isset($_GET['print'])): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        window.print();
    }, 500);
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
