<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$doctor_id = intval($_GET['id'] ?? 0);
if ($doctor_id <= 0) {
    redirect('doctors/index.php?error=Invalid doctor ID');
}

// Fetch doctor details
$stmt = prepare("SELECT d.*, u.email as user_email, u.status as user_status FROM doctors d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    redirect('doctors/index.php?error=Doctor not found');
}

$page_title = "Dr. " . $doctor['first_name'] . " " . $doctor['last_name'] . " - Smart Hospital Management System";
$page_heading = "Doctor Profile";

// Fetch upcoming appointments
$appts_result = query("SELECT a.*, p.first_name as pat_first_name, p.last_name as pat_last_name, p.patient_id 
                      FROM appointments a 
                      JOIN patients p ON a.patient_id = p.id 
                      WHERE a.doctor_id = $doctor_id AND a.appointment_date >= CURDATE()
                      ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 10");
$appointments = [];
while ($row = fetchAssoc($appts_result)) {
    $appointments[] = $row;
}

include '../includes/header.php';
?>

<div class="row">
    <!-- Left Column: Doctor Identity Profile Card -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 text-center p-4">
            <div class="card-body">
                <div class="mb-3">
                    <?php if ($doctor['profile_image']): ?>
                        <img src="../uploads/doctors/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                             alt="Profile" class="rounded-circle img-thumbnail shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center img-thumbnail shadow-sm mx-auto" 
                             style="width: 150px; height: 150px; font-size: 4rem;">
                            <?php echo strtoupper(substr($doctor['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h4 class="fw-bold mb-1">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h4>
                <p class="text-primary fw-semibold mb-2"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                <span class="badge bg-secondary mb-3">ID: <?php echo htmlspecialchars($doctor['doctor_id']); ?></span>
                
                <div class="mb-3">
                    <?php echo getStatusBadge($doctor['status']); ?>
                </div>
                
                <hr class="my-4">
                
                <!-- Quick Info Items -->
                <div class="text-start">
                    <div class="mb-2"><i class="fas fa-stethoscope text-muted me-2"></i><strong>Specialty:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></div>
                    <div class="mb-2"><i class="fas fa-graduation-cap text-muted me-2"></i><strong>Qualification:</strong> <?php echo htmlspecialchars($doctor['qualification'] ?: 'N/A'); ?></div>
                    <div class="mb-2"><i class="fas fa-briefcase text-muted me-2"></i><strong>Experience:</strong> <?php echo htmlspecialchars($doctor['experience_years'] ?: '0'); ?> Years</div>
                    <div class="mb-0"><i class="fas fa-money-bill-wave text-muted me-2"></i><strong>Consultation Fee:</strong> <?php echo formatCurrency($doctor['consultation_fee']); ?></div>
                </div>
                
                <?php if (hasRole('admin')): ?>
                    <div class="d-grid gap-2 mt-4">
                        <a href="edit.php?id=<?php echo $doctor['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Profile
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Details & Schedule -->
    <div class="col-lg-8 mb-4">
        <!-- Professional Card -->
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-user-md text-primary me-2"></i>Professional Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6 class="text-primary fw-bold mb-2">Biography</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($doctor['bio'] ?: 'No biography details provided.')); ?></p>
                </div>
                
                <div class="mb-4">
                    <h6 class="text-primary fw-bold mb-2">Education & Training</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($doctor['education'] ?: 'No education and training logs provided.')); ?></p>
                </div>
                
                <div>
                    <h6 class="text-primary fw-bold mb-2">Contact Details</h6>
                    <div class="row text-muted">
                        <div class="col-md-6 mb-2">
                            <i class="fas fa-phone me-2"></i><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <i class="fas fa-envelope me-2"></i><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email'] ?: 'N/A'); ?>
                        </div>
                        <div class="col-12">
                            <i class="fas fa-map-marker-alt me-2"></i><strong>Clinic Address:</strong> <?php echo htmlspecialchars($doctor['address'] ?: 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Availability & Schedule Card -->
        <div class="card shadow-sm border-0 mb-4 animate fade-in">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-calendar-alt text-primary me-2"></i>Availability & Consultation Schedule</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary fw-bold mb-2">Working Status</h6>
                        <span class="badge bg-info text-uppercase p-2"><?php echo htmlspecialchars(str_replace('_', ' ', $doctor['availability'] ?? 'full_time')); ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary fw-bold mb-2">Consultation Hours</h6>
                        <div class="fw-semibold text-dark"><i class="far fa-clock me-2 text-muted"></i><?php echo htmlspecialchars($doctor['consultation_hours'] ?: 'Standard Hours (Mon-Fri 9AM-5PM)'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Appointments Card -->
        <div class="card shadow-sm border-0 animate fade-in">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-calendar-check text-primary me-2"></i>Upcoming Appointments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <p class="text-muted text-center py-4 mb-0">No upcoming appointments scheduled for this doctor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appt): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($appt['pat_first_name'] . ' ' . $appt['pat_last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($appt['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo formatDate($appt['appointment_date']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo ucfirst($appt['type']); ?></span></td>
                                        <td><?php echo getStatusBadge($appt['status']); ?></td>
                                        <td class="text-center">
                                            <a href="../appointments/view.php?id=<?php echo $appt['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
