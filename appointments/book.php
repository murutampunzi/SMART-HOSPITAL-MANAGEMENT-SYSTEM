<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();
requireAnyRole(['patient']);

$page_title = "Book Appointment - Smart Hospital Management System";
$page_heading = "Book Appointment";

// Get current patient
$user_id = $_SESSION['user_id'];
$patient_sql = "SELECT id FROM patients WHERE user_id = ?";
$patient_stmt = prepare($patient_sql);
$patient_stmt->bind_param("i", $user_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();
$patient = $patient_result->fetch_assoc();

if (!$patient) {
    redirect('../index.php?error=Patient profile not found');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $type = sanitizeInput($_POST['type'] ?? 'general');
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Validate required fields
        $required_fields = [
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
        } elseif (!in_array($type, ['general', 'consultation', 'follow_up', 'emergency'])) {
            $error = 'Invalid appointment type';
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
                    throw new Exception('Doctor is not available at the selected time. Please choose a different time.');
                }
                
                // Get doctor's consultation fee
                $doctor_sql = "SELECT consultation_fee FROM doctors WHERE id = ?";
                $doctor_stmt = prepare($doctor_sql);
                $doctor_stmt->bind_param("i", $doctor_id);
                $doctor_stmt->execute();
                $doctor_info = $doctor_stmt->get_result()->fetch_assoc();
                
                // Generate appointment ID
                $appointment_id = 'APT' . str_pad(insertId() + 1, 6, '0', STR_PAD_LEFT);
                
                // Create appointment
                $sql = "INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, 
                        type, reason, notes, payment_status, payment_amount, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending')";
                
                $stmt = prepare($sql);
                $stmt->bind_param("siisssssd", 
                    $appointment_id, $patient['id'], $doctor_id, $appointment_date, $appointment_time, 
                    $type, $reason, $notes, $doctor_info['consultation_fee']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create appointment');
                }
                
                $appointment_record_id = insertId();
                
                // Log activity
                logActivity('appointment_booked', "Patient booked appointment: $appointment_id");
                
                // Set success message
                $success = "Appointment booked successfully! Appointment ID: $appointment_id. Please arrive 15 minutes before your appointment time.";
                
                // Redirect to appointment view
                header('Refresh: 3; url=view.php?id=' . $appointment_record_id);
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Get doctors for dropdown
$doctors = query("SELECT id, doctor_id, first_name, last_name, specialization, consultation_fee, consultation_hours 
                  FROM doctors WHERE status = 'active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Book Your Appointment</h5>
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
                    
                    <!-- Doctor Selection -->
                    <h6 class="text-primary mb-3">Select Doctor</h6>
                    <div class="row mb-4">
                        <div class="col-12 mb-3">
                            <label for="doctor_id" class="form-label">Choose a Doctor *</label>
                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>" 
                                            data-fee="<?php echo $doctor['consultation_fee']; ?>"
                                            data-hours="<?php echo htmlspecialchars($doctor['consultation_hours']); ?>">
                                        <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?> - 
                                        <?php echo htmlspecialchars($doctor['specialization']); ?>
                                        (Fee: <?php echo formatCurrency($doctor['consultation_fee']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Doctor is required</div>
                        </div>
                        
                        <div class="col-12">
                            <div id="doctorInfo" class="alert alert-info" style="display: none;">
                                <strong>Consultation Hours:</strong> <span id="doctorHours"></span><br>
                                <strong>Consultation Fee:</strong> <span id="doctorFee"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date and Time -->
                    <h6 class="text-primary mb-3">Select Date & Time</h6>
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
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="reason" class="form-label">Reason for Visit</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="Please describe why you need this appointment..." required></textarea>
                            <div class="invalid-feedback">Reason is required</div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" 
                                      placeholder="Any additional information the doctor should know..."></textarea>
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
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
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
                <h6 class="card-title mb-0">Booking Tips</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Choose a doctor based on your condition</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Check doctor's availability hours</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Arrive 15 minutes before appointment</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Bring your ID and medical records</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Payment is due at the time of visit</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Your Upcoming Appointments</h6>
            </div>
            <div class="card-body">
                <?php
                $upcoming_appointments = query("SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization
                                               FROM appointments a 
                                               JOIN doctors d ON a.doctor_id = d.id 
                                               WHERE a.patient_id = {$patient['id']} AND a.appointment_date >= CURDATE() 
                                               AND a.status NOT IN ('cancelled', 'no_show', 'completed')
                                               ORDER BY a.appointment_date, a.appointment_time LIMIT 3")->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($upcoming_appointments)): ?>
                    <p class="text-muted small">No upcoming appointments</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_appointments as $apt): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small"><?php echo formatDate($apt['appointment_date']); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?php echo htmlspecialchars($apt['doctor_first_name']); ?></small>
                                    </div>
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
// Show doctor info when selected
document.getElementById('doctor_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const fee = selectedOption.getAttribute('data-fee');
    const hours = selectedOption.getAttribute('data-hours');
    const doctorInfo = document.getElementById('doctorInfo');
    
    if (fee && hours) {
        document.getElementById('doctorFee').textContent = formatCurrency(parseFloat(fee));
        document.getElementById('doctorHours').textContent = hours;
        doctorInfo.style.display = 'block';
    } else {
        doctorInfo.style.display = 'none';
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
