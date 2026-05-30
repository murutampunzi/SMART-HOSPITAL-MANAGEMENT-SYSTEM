<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'receptionist']);

$page_title = "Create Appointment - Smart Hospital Management System";
$page_heading = "Create New Appointment";

$error = '';
$success = '';

// Get pre-filled patient ID if provided
$prefilled_patient_id = intval($_GET['patient_id'] ?? 0);
$prefilled_doctor_id = intval($_GET['doctor_id'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $type = sanitizeInput($_POST['type'] ?? 'general');
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $payment_status = sanitizeInput($_POST['payment_status'] ?? 'pending');
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        
        // Validate required fields
        $required_fields = [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_date' => $appointment_date,
            'appointment_time' => $appointment_time
        ];
        
        $validation_errors = validateRequired($required_fields);
        if (!empty($validation_errors)) {
            $error = reset($validation_errors);
        } elseif (!validateDate($appointment_date)) {
            $error = 'Invalid appointment date';
        } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
            $error = 'Appointment date cannot be in the past';
        } elseif (!in_array($type, ['general', 'consultation', 'follow_up', 'emergency', 'surgery'])) {
            $error = 'Invalid appointment type';
        } elseif (!in_array($payment_status, ['pending', 'paid', 'partial', 'refunded'])) {
            $error = 'Invalid payment status';
        } else {
            try {
                // Check if doctor is available at the selected time
                $check_sql = "SELECT COUNT(*) as count FROM appointments 
                              WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
                              AND status NOT IN ('cancelled', 'no_show', 'completed')";
                $check_stmt = prepare($check_sql);
                $check_stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result()->fetch_assoc();
                
                if ($check_result['count'] > 0) {
                    throw new Exception('Doctor is not available at the selected time');
                }
                
                // Generate appointment ID
                $appointment_id = 'APT' . str_pad(insertId() + 1, 6, '0', STR_PAD_LEFT);
                
                // Create appointment
                $sql = "INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, 
                        type, reason, notes, payment_status, payment_amount, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                
                $stmt = prepare($sql);
                $stmt->bind_param("siisssssd", 
                    $appointment_id, $patient_id, $doctor_id, $appointment_date, $appointment_time, 
                    $type, $reason, $notes, $payment_status, $payment_amount
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create appointment');
                }
                
                $appointment_record_id = insertId();
                
                // Log activity
                logActivity('appointment_created', "New appointment created: $appointment_id");
                
                // Set success message
                $success = "Appointment created successfully! Appointment ID: $appointment_id";
                
                // Redirect to appointment view
                header('Refresh: 2; url=view.php?id=' . $appointment_record_id);
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Get patients for dropdown
$patients = query("SELECT id, patient_id, first_name, last_name, phone FROM patients WHERE status = 'active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

// Get doctors for dropdown
$doctors = query("SELECT id, doctor_id, first_name, last_name, specialization, consultation_fee FROM doctors WHERE status = 'active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Appointment Details</h5>
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
                
                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <!-- Patient and Doctor Selection -->
                    <h6 class="text-primary mb-3">Select Patient & Doctor</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="patient_id" class="form-label">Patient *</label>
                            <select class="form-select" id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>" 
                                            <?php echo $prefilled_patient_id === $patient['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' - ' . $patient['patient_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Patient is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="doctor_id" class="form-label">Doctor *</label>
                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>" 
                                            data-fee="<?php echo $doctor['consultation_fee']; ?>"
                                            <?php echo $prefilled_doctor_id === $doctor['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name'] . ' - ' . $doctor['specialization']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Doctor is required</div>
                        </div>
                    </div>
                    
                    <!-- Date and Time -->
                    <h6 class="text-primary mb-3">Date & Time</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="appointment_date" class="form-label">Appointment Date *</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Appointment date is required</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="appointment_time" class="form-label">Appointment Time *</label>
                            <input type="time" class="form-control" id="appointment_time" name="appointment_time" required>
                            <div class="invalid-feedback">Appointment time is required</div>
                        </div>
                    </div>
                    
                    <!-- Appointment Details -->
                    <h6 class="text-primary mb-3">Appointment Details</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Appointment Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="general">General Checkup</option>
                                <option value="consultation">Consultation</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="emergency">Emergency</option>
                                <option value="surgery">Surgery</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="reason" class="form-label">Reason for Visit</label>
                            <textarea class="form-control" id="reason" name="reason" rows="2" 
                                      placeholder="Brief description of the reason for appointment..."></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      placeholder="Any additional information..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <h6 class="text-primary mb-3">Payment Information</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial Payment</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="payment_amount" class="form-label">Payment Amount</label>
                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                   min="0" step="0.01" placeholder="0.00">
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
                                    <i class="fas fa-save me-2"></i>Create Appointment
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
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Appointment ID will be generated automatically</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Check doctor availability before booking</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Payment amount will auto-fill based on doctor fee</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Patient will receive notification if enabled</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Today's Schedule</h6>
            </div>
            <div class="card-body">
                <?php
                $today_appointments = query("SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, 
                                             p.first_name as patient_first_name, p.last_name as patient_last_name
                                             FROM appointments a 
                                             JOIN doctors d ON a.doctor_id = d.id 
                                             JOIN patients p ON a.patient_id = p.id 
                                             WHERE a.appointment_date = CURDATE() 
                                             ORDER BY a.appointment_time LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($today_appointments)): ?>
                    <p class="text-muted small">No appointments today</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($today_appointments as $apt): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($apt['doctor_first_name']); ?></small>
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
// Auto-fill payment amount based on doctor selection
document.getElementById('doctor_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const fee = selectedOption.getAttribute('data-fee');
    if (fee) {
        document.getElementById('payment_amount').value = fee;
    }
});

// Set minimum date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('appointment_date').setAttribute('min', today);
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
