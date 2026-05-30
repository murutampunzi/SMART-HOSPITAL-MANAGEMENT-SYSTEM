<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'lab_technician', 'radiologist']);

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setNotification('Invalid request ID.', 'danger');
    redirect('index.php');
}

// Fetch request details
$res = query("SELECT rr.*, rt.name as test_name, rt.modality, p.first_name, p.last_name, p.patient_id, p.date_of_birth, p.gender 
              FROM radiology_requests rr
              JOIN patients p ON rr.patient_id = p.id
              JOIN radiology_tests rt ON rr.test_id = rt.id
              WHERE rr.id = $id LIMIT 1");

if (!$res || numRows($res) === 0) {
    setNotification('Radiology request not found.', 'danger');
    redirect('index.php');
}

$request = $res->fetch_assoc();

// Fetch uploaded images
$images_res = query("SELECT * FROM radiology_images WHERE request_id = $id ORDER BY upload_date ASC");
$images = $images_res->fetch_all(MYSQLI_ASSOC);

// Fetch existing report details if any
$existing_report = [
    'findings' => '',
    'impression' => '',
    'recommendation' => '',
    'status' => 'pending_review'
];

if (!empty($images)) {
    foreach ($images as $img) {
        if (!empty($img['findings']) || !empty($img['impression'])) {
            $existing_report = [
                'findings' => $img['findings'],
                'impression' => $img['impression'],
                'recommendation' => $img['recommendation'],
                'status' => $img['status']
            ];
            break;
        }
    }
}

$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request token.';
    } else {
        $findings = sanitizeInput($_POST['findings'] ?? '');
        $impression = sanitizeInput($_POST['impression'] ?? '');
        $recommendation = sanitizeInput($_POST['recommendation'] ?? '');
        $report_status = sanitizeInput($_POST['status'] ?? 'pending_review');

        if (empty($findings) || empty($impression)) {
            $error = 'Findings and Impression are required.';
        } else {
            $conn->begin_transaction();
            try {
                if (empty($images)) {
                    // Create placeholder image row so the report can be saved
                    $sql = "INSERT INTO radiology_images (request_id, image_path, findings, impression, recommendation, radiologist_id, report_date, status) 
                            VALUES (?, NULL, ?, ?, ?, ?, NOW(), ?)";
                    $stmt = prepare($sql);
                    $uid = $_SESSION['user_id'];
                    $stmt->bind_param("isssis", $id, $findings, $impression, $recommendation, $uid, $report_status);
                    $stmt->execute();
                } else {
                    // Update all existing rows
                    $sql = "UPDATE radiology_images 
                            SET findings = ?, 
                                impression = ?, 
                                recommendation = ?, 
                                radiologist_id = ?, 
                                report_date = NOW(), 
                                status = ? 
                            WHERE request_id = ?";
                    $stmt = prepare($sql);
                    $uid = $_SESSION['user_id'];
                    $stmt->bind_param("sssisi", $findings, $impression, $recommendation, $uid, $report_status, $id);
                    $stmt->execute();
                }

                // Update radiology request status to completed if signed
                if ($report_status === 'signed') {
                    query("UPDATE radiology_requests SET status = 'completed' WHERE id = $id");

                    // Notify Patient
                    $p_stmt = prepare("SELECT user_id FROM patients WHERE patient_id = ?");
                    $p_stmt->bind_param("s", $request['patient_id']);
                    $p_stmt->execute();
                    $p_res = $p_stmt->get_result()->fetch_assoc();
                    $p_uid = $p_res['user_id'] ?? null;
                    if ($p_uid) {
                        $title = "Radiology Report Signed";
                        $msg = "Your radiology report for " . $request['test_name'] . " has been signed by the radiologist.";
                        $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'lab_result', ?)";
                        $n_stmt = prepare($notif_sql);
                        $link = "radiology/view.php?id=" . $id;
                        $n_stmt->bind_param("isss", $p_uid, $title, $msg, $link);
                        $n_stmt->execute();
                    }

                    // Notify referring doctor
                    if ($request['doctor_id']) {
                        $d_uid = query("SELECT user_id FROM doctors WHERE id = " . $request['doctor_id'])->fetch_assoc()['user_id'];
                        if ($d_uid) {
                            $title = "Radiology Report Ready";
                            $msg = "The radiology report for patient " . $request['first_name'] . " " . $request['last_name'] . " (" . $request['test_name'] . ") is signed and ready.";
                            $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'lab_result', ?)";
                            $n_stmt = prepare($notif_sql);
                            $link = "radiology/view.php?id=" . $id;
                            $n_stmt->bind_param("isss", $d_uid, $title, $msg, $link);
                            $n_stmt->execute();
                        }
                    }
                }

                $conn->commit();
                logActivity('write_radiology_report', "Saved report for radiology request ID $id with status $report_status");
                setNotification('Radiology report saved successfully.', 'success');
                redirect("view.php?id=" . $id);
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to save report: ' . $e->getMessage();
            }
        }
    }
}

$page_title = "Write Diagnostic Report - Smart Hospital Management System";
$page_heading = "Write Diagnostic Report";
include '../includes/header.php';
?>

<div class="row">
    <!-- Left side - Scans & Images -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-images me-2"></i>Radiology Scans</h5>
                <small class="text-muted"><?php echo count($images); ?> image(s) uploaded</small>
            </div>
            <div class="card-body bg-light overflow-auto" style="max-height: 700px;">
                <?php if (empty($images)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-x-ray fa-3x mb-3"></i>
                        <h5>No Scan Images Uploaded</h5>
                        <p class="small">The diagnostic report can still be saved, but scan images should ideally be uploaded first.</p>
                        <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                            <a href="upload_images.php?id=<?php echo $id; ?>" class="btn btn-sm btn-primary">Upload Scans Now</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($images as $img): ?>
                            <div class="col-md-6">
                                <div class="card border shadow-xs h-100">
                                    <img src="<?php echo BASE_PATH . $img['image_path']; ?>" class="card-img-top border-bottom" alt="Scan" style="height: 200px; object-fit: cover;">
                                    <div class="card-body p-2">
                                        <p class="card-text small mb-0 fw-bold"><?php echo htmlspecialchars($img['image_description'] ?: 'Scan Image'); ?></p>
                                        <small class="text-muted small">Uploaded: <?php echo date('M j, Y h:i A', strtotime($img['upload_date'])); ?></small>
                                    </div>
                                    <div class="card-footer p-2 text-center bg-white border-0">
                                        <a href="<?php echo BASE_PATH . $img['image_path']; ?>" target="_blank" class="btn btn-xs btn-outline-secondary"><i class="fas fa-search-plus me-1"></i>View Full</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right side - Diagnostic Report Form -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Diagnostic Report</h5>
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-secondary border-0 p-3 small mb-4">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-user-circle me-1"></i>Patient Info</h6>
                    <div class="row">
                        <div class="col-md-6"><strong>Patient:</strong> <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name'] . ' (' . $request['patient_id'] . ')'); ?></div>
                        <div class="col-md-3"><strong>Age/Sex:</strong> <?php 
                            $dob = new DateTime($request['date_of_birth']);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                            echo $age . ' / ' . ucfirst($request['gender']);
                        ?></div>
                        <div class="col-md-3"><strong>Modality:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($request['modality']); ?></span></div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>

                    <div class="mb-3">
                        <label for="findings" class="form-label fw-bold">Findings *</label>
                        <textarea class="form-control" id="findings" name="findings" rows="6" required placeholder="Detailed observation of the scans..."><?php echo htmlspecialchars($existing_report['findings']); ?></textarea>
                        <div class="invalid-feedback">Please describe your diagnostic findings.</div>
                    </div>

                    <div class="mb-3">
                        <label for="impression" class="form-label fw-bold">Impression / Conclusion *</label>
                        <textarea class="form-control" id="impression" name="impression" rows="4" required placeholder="Summary of finding diagnosis, impression..."><?php echo htmlspecialchars($existing_report['impression']); ?></textarea>
                        <div class="invalid-feedback">Please enter the diagnostic impression.</div>
                    </div>

                    <div class="mb-3">
                        <label for="recommendation" class="form-label fw-bold">Recommendations</label>
                        <textarea class="form-control" id="recommendation" name="recommendation" rows="2" placeholder="Next steps, follow up scans..."><?php echo htmlspecialchars($existing_report['recommendation']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="status" class="form-label fw-bold">Signing / Report Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="pending_review" <?php echo $existing_report['status'] === 'pending_review' ? 'selected' : ''; ?>>Draft / Pending Review</option>
                            <option value="reviewed" <?php echo $existing_report['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="signed" <?php echo $existing_report['status'] === 'signed' ? 'selected' : ''; ?>>Signed & Complete (Locked)</option>
                        </select>
                        <small class="text-muted">Selecting "Signed & Complete" will lock the report and change the request status to completed.</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Diagnostic Report</button>
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
