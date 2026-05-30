<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor']);

$error = '';
$success = '';

// Get active patients
$patients = query("SELECT p.id, p.patient_id, p.first_name, p.last_name 
                   FROM patients p 
                   WHERE p.status = 'active' 
                   ORDER BY p.first_name, p.last_name")->fetch_all(MYSQLI_ASSOC);

// Get active doctors
$doctors = query("SELECT d.id, d.doctor_id, d.first_name, d.last_name 
                  FROM doctors d 
                  WHERE d.status = 'active' 
                  ORDER BY d.first_name, d.last_name")->fetch_all(MYSQLI_ASSOC);

// Get active radiology tests
$tests = query("SELECT id, name, modality, price, contrast_required 
                FROM radiology_tests 
                WHERE status = 'active' 
                ORDER BY modality, name")->fetch_all(MYSQLI_ASSOC);

// Check if current user is a doctor
$current_doctor_id = null;
if (hasRole('doctor')) {
    $user_id = $_SESSION['user_id'];
    $doc_res = query("SELECT id FROM doctors WHERE user_id = $user_id LIMIT 1");
    if ($doc_res && numRows($doc_res) > 0) {
        $current_doctor_id = $doc_res->fetch_assoc()['id'];
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $test_id = intval($_POST['test_id'] ?? 0);
        $requested_date = sanitizeInput($_POST['requested_date'] ?? '');
        $priority = sanitizeInput($_POST['priority'] ?? 'routine');
        $clinical_indication = sanitizeInput($_POST['clinical_indication'] ?? '');
        $contrast_allergy = isset($_POST['contrast_allergy']) ? 1 : 0;
        $pregnancy = isset($_POST['pregnancy']) ? 1 : 0;
        $notes = sanitizeInput($_POST['notes'] ?? '');

        // Validation
        if (empty($patient_id) || empty($test_id) || empty($requested_date)) {
            $error = 'Please fill in all required fields.';
        } elseif (!validateDate($requested_date)) {
            $error = 'Invalid requested date format.';
        } else {
            // Insert request
            $sql = "INSERT INTO radiology_requests (patient_id, doctor_id, test_id, requested_date, priority, status, clinical_indication, contrast_allergy, pregnancy, notes) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";
            
            $stmt = prepare($sql);
            $stmt->bind_param("iiisssiis", $patient_id, $doctor_id, $test_id, $requested_date, $priority, $clinical_indication, $contrast_allergy, $pregnancy, $notes);
            
            if ($stmt->execute()) {
                $new_id = insertId();
                logActivity('create_radiology_request', "Created radiology request ID $new_id");

                // Get test name for notifications
                $test_name = '';
                foreach ($tests as $t) {
                    if ($t['id'] === $test_id) {
                        $test_name = $t['name'];
                        break;
                    }
                }

                // Notify Patient (if user_id exists)
                $pat_user = query("SELECT user_id FROM patients WHERE id = $patient_id LIMIT 1")->fetch_assoc();
                if ($pat_user && $pat_user['user_id']) {
                    $p_uid = $pat_user['user_id'];
                    $title = "Radiology Scan Requested";
                    $msg = "A new scan request ($test_name) has been placed for you.";
                    $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', ?)";
                    $n_stmt = prepare($notif_sql);
                    $link = "radiology/view.php?id=" . $new_id;
                    $n_stmt->bind_param("isss", $p_uid, $title, $msg, $link);
                    $n_stmt->execute();
                }

                // Notify Doctor if not the one who requested
                if ($doctor_id && (!hasRole('doctor') || $doctor_id !== $current_doctor_id)) {
                    $doc_user = query("SELECT user_id FROM doctors WHERE id = $doctor_id LIMIT 1")->fetch_assoc();
                    if ($doc_user && $doc_user['user_id']) {
                        $d_uid = $doc_user['user_id'];
                        $title = "New Radiology Scan Requested";
                        $msg = "You are assigned as the referring doctor for a new scan request ($test_name).";
                        $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'appointment', ?)";
                        $n_stmt = prepare($notif_sql);
                        $link = "radiology/view.php?id=" . $new_id;
                        $n_stmt->bind_param("isss", $d_uid, $title, $msg, $link);
                        $n_stmt->execute();
                    }
                }

                $success = 'Radiology scan request created successfully.';
                // Redirect to index
                header("Refresh: 2; url=index.php");
            } else {
                $error = 'Failed to create request: ' . $conn->error;
            }
        }
    }
}

$page_title = "Request Radiology Scan - Smart Hospital Management System";
$page_heading = "Request Radiology Scan";
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-prescription me-2"></i>New Radiology Request</h5>
                <a href="index.php" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="patient_id" class="form-label">Select Patient *</label>
                            <select class="form-select" id="patient_id" name="patient_id" required>
                                <option value="">-- Choose Patient --</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo (isset($_POST['patient_id']) && intval($_POST['patient_id']) === $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a patient.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="doctor_id" class="form-label">Referring Doctor</label>
                            <select class="form-select" id="doctor_id" name="doctor_id">
                                <option value="">-- Choose Referring Doctor --</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo ($current_doctor_id === $d['id'] || (isset($_POST['doctor_id']) && intval($_POST['doctor_id']) === $d['id'])) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name'] . ' (' . $d['doctor_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="test_id" class="form-label">Radiology Scan Test *</label>
                            <select class="form-select" id="test_id" name="test_id" required>
                                <option value="">-- Choose Scan / Modality --</option>
                                <?php 
                                $curr_mod = '';
                                foreach ($tests as $t): 
                                    if ($curr_mod !== $t['modality']):
                                        if ($curr_mod !== '') echo '</optgroup>';
                                        $curr_mod = $t['modality'];
                                        echo '<optgroup label="' . htmlspecialchars($curr_mod) . '">';
                                    endif;
                                ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo (isset($_POST['test_id']) && intval($_POST['test_id']) === $t['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['name'] . ' (' . formatCurrency($t['price']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($curr_mod !== '') echo '</optgroup>'; ?>
                            </select>
                            <div class="invalid-feedback">Please select a scan test.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="requested_date" class="form-label">Requested Date *</label>
                            <input type="date" class="form-control" id="requested_date" name="requested_date" 
                                   value="<?php echo htmlspecialchars($_POST['requested_date'] ?? date('Y-m-d')); ?>" required>
                            <div class="invalid-feedback">Please provide a request date.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority Level *</label>
                        <div class="d-flex gap-4 align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="priority" id="p_routine" value="routine" 
                                    <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'routine') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="p_routine">Routine</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="priority" id="p_urgent" value="urgent"
                                    <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'urgent') ? 'checked' : ''; ?>>
                                <label class="form-check-label text-warning fw-bold" for="p_urgent">Urgent</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="priority" id="p_stat" value="stat"
                                    <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'stat') ? 'checked' : ''; ?>>
                                <label class="form-check-label text-danger fw-bold" for="p_stat">STAT (Emergency)</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch pt-3">
                                <input class="form-check-input" type="checkbox" id="contrast_allergy" name="contrast_allergy" value="1"
                                    <?php echo isset($_POST['contrast_allergy']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold text-danger" for="contrast_allergy">Known Contrast Allergy</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch pt-3">
                                <input class="form-check-input" type="checkbox" id="pregnancy" name="pregnancy" value="1"
                                    <?php echo isset($_POST['pregnancy']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold text-warning" for="pregnancy">Pregnancy Check Required</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="clinical_indication" class="form-label">Clinical Indication / Symptoms *</label>
                        <textarea class="form-control" id="clinical_indication" name="clinical_indication" rows="3" required placeholder="Reason for scan, clinical findings..."><?php echo htmlspecialchars($_POST['clinical_indication'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">Please enter the clinical indication.</div>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Special Notes / Instructions</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any additional notes for the technician..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i>Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Bootstrap form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
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
