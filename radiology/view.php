<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) {
    redirect('radiology/index.php?error=Invalid radiology request ID');
}

// Fetch radiology request details
$stmt = prepare("SELECT rr.*, rt.name as test_name, rt.modality, rt.contrast_required, rt.preparation_instructions, rt.price,
                p.first_name, p.last_name, p.patient_id, p.date_of_birth, p.gender, p.blood_group,
                d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                FROM radiology_requests rr 
                JOIN radiology_tests rt ON rr.test_id = rt.id
                JOIN patients p ON rr.patient_id = p.id
                LEFT JOIN doctors d ON rr.doctor_id = d.id
                WHERE rr.id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    redirect('radiology/index.php?error=Radiology request not found');
}

$page_title = "Radiology Scan " . $request['request_id'] . " - Smart Hospital Management System";
$page_heading = "Radiology Scan details";

// Fetch any uploaded images & reports
$img_stmt = prepare("SELECT ri.*, u.name as radiologist_name FROM radiology_images ri LEFT JOIN users u ON ri.radiologist_id = u.id WHERE ri.request_id = ?");
$img_stmt->bind_param("i", $request_id);
$img_stmt->execute();
$imaging = $img_stmt->get_result()->fetch_assoc();

include '../includes/header.php';
?>

<div class="row">
    <!-- Main Scan Information Card -->
    <div class="col-lg-8 mb-4">
        <!-- Scan Details Card -->
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-x-ray text-primary me-2"></i>Scan Request Particulars</h5>
                <span class="badge bg-secondary py-2 px-3">Request ID: <?php echo htmlspecialchars($request['request_id']); ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-primary fw-bold mb-1">Requested Diagnostic Scan</label>
                        <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($request['test_name']); ?></h4>
                        <span class="badge bg-info text-uppercase"><?php echo htmlspecialchars($request['modality']); ?> Scan</span>
                        <?php if ($request['contrast_required']): ?>
                            <span class="badge bg-danger text-uppercase"><i class="fas fa-tint me-1"></i>Contrast Required</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 mb-3 text-md-end">
                        <label class="form-label text-primary fw-bold mb-1">Priority & Status</label>
                        <div>
                            <?php 
                            $priorityColors = [
                                'routine' => 'success',
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
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary fw-bold mb-2">Clinical Indication</h6>
                        <p class="text-muted bg-light p-3 rounded border"><?php echo nl2br(htmlspecialchars($request['clinical_indication'] ?: 'No specific clinical indication logged.')); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary fw-bold mb-2">Safety Indicators</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas <?php echo $request['contrast_allergy'] ? 'fa-exclamation-triangle text-danger' : 'fa-check-circle text-success'; ?> me-2"></i>
                                <strong>Contrast Allergy:</strong> <?php echo $request['contrast_allergy'] ? 'YES' : 'NO'; ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas <?php echo $request['pregnancy'] ? 'fa-exclamation-triangle text-danger' : 'fa-check-circle text-success'; ?> me-2"></i>
                                <strong>Pregnancy Status:</strong> <?php echo $request['pregnancy'] ? 'YES / Possible' : 'NO / Not Applicable'; ?>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($request['preparation_instructions']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold mb-2">Preparation Instructions for Patient</h6>
                        <p class="text-muted small"><i class="fas fa-info-circle text-info me-2"></i><?php echo htmlspecialchars($request['preparation_instructions']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Diagnostic Imaging & Findings Report Panel (Show only if completed or uploaded) -->
        <?php if ($imaging): ?>
            <div class="card shadow-sm border-0 mb-4 animate fade-in">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-file-medical text-light me-2"></i>Radiologist Diagnostic Report</h5>
                </div>
                <div class="card-body p-4 bg-light">
                    <!-- Diagnostic Image Viewer (Placeholder/Grid) -->
                    <?php if ($imaging['image_path']): ?>
                        <div class="mb-4 text-center">
                            <h6 class="text-primary fw-bold text-start mb-3"><i class="fas fa-images me-2"></i>Diagnostic Images</h6>
                            <div class="bg-dark p-3 rounded text-center border">
                                <img src="../uploads/radiology/<?php echo htmlspecialchars($imaging['image_path']); ?>" 
                                     alt="Radiology Scan" class="img-fluid rounded shadow-sm border border-secondary" style="max-height: 400px; object-fit: contain;">
                                <div class="mt-2 text-white-50 small"><?php echo htmlspecialchars($imaging['image_description'] ?: 'Diagnostic Scan View'); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h6 class="text-primary fw-bold mb-2">Clinical Findings</h6>
                        <p class="text-muted bg-white p-3 rounded border shadow-sm"><?php echo nl2br(htmlspecialchars($imaging['findings'] ?: 'No findings logged.')); ?></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-primary fw-bold mb-2">Diagnostic Impression</h6>
                            <p class="text-muted bg-white p-3 rounded border shadow-sm"><?php echo nl2br(htmlspecialchars($imaging['impression'] ?: 'N/A')); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-primary fw-bold mb-2">Recommendations</h6>
                            <p class="text-muted bg-white p-3 rounded border shadow-sm"><?php echo nl2br(htmlspecialchars($imaging['recommendation'] ?: 'N/A')); ?></p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <div class="text-muted small"><strong>Reporting Radiologist:</strong></div>
                            <div class="fw-bold text-dark">Dr. <?php echo htmlspecialchars($imaging['radiologist_name'] ?: 'Pending Reviewer'); ?></div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle uppercase mt-1">
                                <i class="fas fa-file-signature me-1"></i><?php echo htmlspecialchars($imaging['status']); ?>
                            </span>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                            <div class="text-muted small"><strong>Report Sign Date:</strong></div>
                            <div class="fw-semibold text-dark"><?php echo $imaging['report_date'] ? formatDateTime($imaging['report_date']) : 'N/A'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar: Patient Info & Administrative Actions -->
    <div class="col-lg-4 mb-4">
        <!-- Patient Information Card -->
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
                <div class="small text-muted mb-0"><i class="fas fa-user-md me-2"></i><strong>Referred By:</strong> Dr. <?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></div>
            </div>
        </div>
        
        <!-- Scan Schedule Info Card -->
        <?php if ($request['scheduled_date']): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold"><i class="far fa-calendar-check text-primary me-2"></i>Appointment Schedule</h6>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-2"><i class="far fa-calendar-alt me-2"></i><strong>Scheduled Date:</strong> <?php echo formatDate($request['scheduled_date']); ?></div>
                    <div class="small text-muted mb-0"><i class="far fa-clock me-2"></i><strong>Scheduled Time:</strong> <?php echo date('h:i A', strtotime($request['scheduled_time'])); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Radiology Actions Card -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold"><i class="fas fa-sliders-h text-primary me-2"></i>Administrative Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($request['status'] === 'pending' && (hasRole('admin') || hasRole('radiologist') || hasRole('receptionist'))): ?>
                        <a href="schedule_scan.php?id=<?php echo $request['id']; ?>" class="btn btn-primary">
                            <i class="far fa-calendar-plus me-2"></i>Schedule Scan
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($request['status'] === 'scheduled' && (hasRole('admin') || hasRole('radiologist'))): ?>
                        <a href="start_scan.php?id=<?php echo $request['id']; ?>" class="btn btn-info text-white">
                            <i class="fas fa-play me-2"></i>Begin Scan Execution
                        </a>
                    <?php endif; ?>
                    
                    <?php if (($request['status'] === 'in_progress' || $request['status'] === 'scheduled') && (hasRole('admin') || hasRole('radiologist'))): ?>
                        <a href="upload_images.php?id=<?php echo $request['id']; ?>" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Upload Scan Images
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($imaging && $imaging['status'] === 'pending_review' && (hasRole('admin') || hasRole('radiologist'))): ?>
                        <a href="write_report.php?id=<?php echo $request['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-file-signature me-2"></i>Write Diagnostic Report
                        </a>
                    <?php endif; ?>
                    
                    <?php if (($request['status'] === 'pending' || $request['status'] === 'scheduled') && (hasRole('admin') || hasRole('doctor'))): ?>
                        <a href="cancel_request.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-danger">
                            <i class="fas fa-times me-2"></i>Cancel Request
                        </a>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
