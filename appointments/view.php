<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get appointment ID
$appointment_id = intval($_GET['id'] ?? 0);
if ($appointment_id <= 0) {
    redirect('index.php?error=Invalid appointment ID');
}

// Get appointment details
$sql = "SELECT a.*, 
        p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id, p.phone as patient_phone, p.email as patient_email,
        d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.doctor_id, d.specialization, d.consultation_fee
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.id = ?";
$stmt = prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    redirect('index.php?error=Appointment not found');
}

$page_title = "Appointment {$appointment['appointment_id']} - Smart Hospital Management System";
$page_heading = "Appointment Details";

// Check access permissions
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Get user's patient or doctor record if applicable
$can_view = true;
if ($user_role === 'patient') {
    $patient_sql = "SELECT id FROM patients WHERE user_id = ?";
    $patient_stmt = prepare($patient_sql);
    $patient_stmt->bind_param("i", $user_id);
    $patient_stmt->execute();
    $patient_record = $patient_stmt->get_result()->fetch_assoc();
    if (!$patient_record || $patient_record['id'] !== $appointment['patient_id']) {
        $can_view = false;
    }
} elseif ($user_role === 'doctor') {
    $doctor_sql = "SELECT id FROM doctors WHERE user_id = ?";
    $doctor_stmt = prepare($doctor_sql);
    $doctor_stmt->bind_param("i", $user_id);
    $doctor_stmt->execute();
    $doctor_record = $doctor_stmt->get_result()->fetch_assoc();
    if (!$doctor_record || $doctor_record['id'] !== $appointment['doctor_id']) {
        $can_view = false;
    }
}

if (!$can_view) {
    redirect('index.php?error=Access denied');
}

include '../includes/header.php';
?>

<!-- Appointment Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px; font-size: 2rem;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-4">
                                <h2 class="mb-1">Appointment <?php echo htmlspecialchars($appointment['appointment_id']); ?></h2>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-clock me-2"></i><?php echo formatDate($appointment['appointment_date']); ?> at <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                </p>
                                <div>
                                    <?php echo getStatusBadge($appointment['status']); ?>
                                    <?php echo getStatusBadge($appointment['payment_status'], $appointment['payment_status'] === 'paid' ? 'success' : 'warning'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                <a href="edit.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> All Appointments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Patient and Doctor Information -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-user-injured me-2"></i>Patient Information</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <?php echo strtoupper(substr($appointment['patient_first_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-0"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_id']); ?></small>
                    </div>
                </div>
                <hr>
                <div class="small">
                    <div class="mb-2"><i class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                    <div class="mb-2"><i class="fas fa-envelope me-2 text-muted"></i><?php echo htmlspecialchars($appointment['patient_email'] ?: 'N/A'); ?></div>
                </div>
                <div class="mt-3">
                    <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View Patient Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-user-md me-2"></i>Doctor Information</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fas fa-user-md"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-0"><?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                    </div>
                </div>
                <hr>
                <div class="small">
                    <div class="mb-2"><i class="fas fa-id-badge me-2 text-muted"></i><?php echo htmlspecialchars($appointment['doctor_id']); ?></div>
                    <div class="mb-2"><i class="fas fa-dollar-sign me-2 text-muted"></i>Consultation Fee: <?php echo formatCurrency($appointment['consultation_fee']); ?></div>
                </div>
                <div class="mt-3">
                    <a href="../doctors/view.php?id=<?php echo $appointment['doctor_id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View Doctor Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Appointment Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Type</h6>
                        <p><?php echo ucfirst($appointment['type']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Date & Time</h6>
                        <p><?php echo formatDate($appointment['appointment_date']); ?> at <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Status</h6>
                        <p><?php echo getStatusBadge($appointment['status']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Payment Status</h6>
                        <p><?php echo getStatusBadge($appointment['payment_status'], $appointment['payment_status'] === 'paid' ? 'success' : 'warning'); ?></p>
                    </div>
                    <?php if ($appointment['payment_amount'] > 0): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-primary">Payment Amount</h6>
                            <p><?php echo formatCurrency($appointment['payment_amount']); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Created</h6>
                        <p><?php echo formatDateTime($appointment['created_at']); ?></p>
                    </div>
                </div>
                
                <?php if ($appointment['reason']): ?>
                    <div class="mt-3">
                        <h6 class="text-primary">Reason for Visit</h6>
                        <p><?php echo nl2br(htmlspecialchars($appointment['reason'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($appointment['notes']): ?>
                    <div class="mt-3">
                        <h6 class="text-primary">Additional Notes</h6>
                        <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="../billing/create.php?appointment_id=<?php echo $appointment_id; ?>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-file-invoice me-2"></i>Create Invoice
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../laboratory/request.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-flask me-2"></i>Request Lab Test
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('doctor') && in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                        <div class="col-md-3 mb-2">
                            <a href="consultation.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-stethoscope me-2"></i>Start Consultation
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                        <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                            <div class="col-md-3 mb-2">
                                <button type="button" class="btn btn-outline-danger w-100" onclick="cancelAppointment()">
                                    <i class="fas fa-times me-2"></i>Cancel Appointment
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="col-md-3 mb-2">
                        <a href="index.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Appointment Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="cancel.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cancellation_reason" class="form-label">Reason for Cancellation *</label>
                        <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="cancelled_by" class="form-label">Cancelled By *</label>
                        <select class="form-select" id="cancelled_by" name="cancelled_by" required>
                            <option value="">Select...</option>
                            <option value="admin">Admin</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="doctor">Doctor</option>
                            <option value="patient">Patient</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Cancel Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cancelAppointment() {
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
