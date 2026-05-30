<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get patient ID
$patient_id = intval($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    redirect('patients/index.php?error=Invalid patient ID');
}

// Get patient details
$sql = "SELECT p.*, u.email as user_email, u.created_at as user_created_at 
        FROM patients p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?";
$stmt = prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    redirect('patients/index.php?error=Patient not found');
}

$page_title = $patient['first_name'] . ' ' . $patient['last_name'] . ' - Patient Details';
$page_heading = "Patient Details";

// Get patient's appointments
$appointments = [];
$result = query("SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization 
                 FROM appointments a 
                 JOIN doctors d ON a.doctor_id = d.id 
                 WHERE a.patient_id = $patient_id 
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

// Get patient's medical records
$medical_records = [];
$result = query("SELECT mr.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                 FROM medical_records mr 
                 JOIN doctors d ON mr.doctor_id = d.id 
                 WHERE mr.patient_id = $patient_id 
                 ORDER BY mr.created_at DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $medical_records[] = $row;
}

// Get patient's lab results
$lab_results = [];
$result = query("SELECT lr.*, lt.name as test_name, lt.category 
                 FROM lab_results lr 
                 JOIN lab_test_requests ltr ON lr.request_id = ltr.id 
                 JOIN lab_tests lt ON lr.test_id = lt.id 
                 WHERE lr.patient_id = $patient_id 
                 ORDER BY lr.created_at DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $lab_results[] = $row;
}

// Get patient's invoices
$invoices = [];
$result = query("SELECT i.*, COUNT(ii.id) as item_count 
                 FROM invoices i 
                 LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
                 WHERE i.patient_id = $patient_id 
                 GROUP BY i.id 
                 ORDER BY i.invoice_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}

// Calculate age
$dob = new DateTime($patient['date_of_birth']);
$now = new DateTime();
$age = $now->diff($dob)->y;

include '../includes/header.php';
?>

<!-- Patient Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if ($patient['profile_image']): ?>
                            <img src="../uploads/patients/<?php echo htmlspecialchars($patient['profile_image']); ?>" 
                                 alt="Profile" class="rounded-circle img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center img-thumbnail" 
                                 style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-1"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                        <p class="text-muted mb-2">Patient ID: <span class="badge bg-primary"><?php echo htmlspecialchars($patient['patient_id']); ?></span></p>
                        <div class="row small">
                            <div class="col-6">
                                <div><i class="fas fa-birthday-cake me-2"></i><?php echo $age; ?> years old</div>
                                <div><i class="fas fa-venus-mars me-2"></i><?php echo ucfirst($patient['gender']); ?></div>
                                <div><i class="fas fa-tint me-2"></i><?php echo $patient['blood_group'] ?: 'N/A'; ?></div>
                            </div>
                            <div class="col-6">
                                <div><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($patient['phone']); ?></div>
                                <div><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($patient['email'] ?: 'N/A'); ?></div>
                                <div><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($patient['address'] ?: 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="mb-2"><?php echo getStatusBadge($patient['status']); ?></div>
                        <div class="btn-group" role="group">
                            <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                <a href="edit.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                <a href="appointments/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Book Appointment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions and Stats -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="../appointments/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../medical_records/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-success w-100">
                            <i class="fas fa-notes-medical me-2"></i>Add Medical Record
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../laboratory/request.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-info w-100">
                            <i class="fas fa-flask me-2"></i>Request Lab Test
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../billing/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-warning w-100">
                            <i class="fas fa-file-invoice me-2"></i>Create Invoice
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Patient Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 mb-0"><?php echo count($appointments); ?></div>
                        <small class="text-muted">Appointments</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 mb-0"><?php echo count($medical_records); ?></div>
                        <small class="text-muted">Medical Records</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 mb-0"><?php echo count($lab_results); ?></div>
                        <small class="text-muted">Lab Results</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Medical Information -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Medical Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-primary">Medical History</h6>
                    <p><?php echo nl2br(htmlspecialchars($patient['medical_history'] ?: 'No medical history recorded')); ?></p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-primary">Allergies</h6>
                    <p><?php echo nl2br(htmlspecialchars($patient['allergies'] ?: 'No allergies recorded')); ?></p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-primary">Current Medications</h6>
                    <p><?php echo nl2br(htmlspecialchars($patient['current_medications'] ?: 'No current medications recorded')); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Emergency Contact</h5>
            </div>
            <div class="card-body">
                <?php if ($patient['emergency_contact_name']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary">Contact Person</h6>
                        <p><strong><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></strong></p>
                        <p><?php echo htmlspecialchars($patient['emergency_contact_relation'] ?: 'N/A'); ?></p>
                        <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></p>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No emergency contact information available</p>
                <?php endif; ?>
                
                <?php if ($patient['insurance_provider']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary">Insurance Information</h6>
                        <p><strong>Provider:</strong> <?php echo htmlspecialchars($patient['insurance_provider']); ?></p>
                        <p><strong>Policy Number:</strong> <?php echo htmlspecialchars($patient['insurance_policy_number'] ?: 'N/A'); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <h6 class="text-primary">Registration Information</h6>
                    <p><strong>Registered:</strong> <?php echo formatDate($patient['created_at']); ?></p>
                    <?php if ($patient['admission_date']): ?>
                        <p><strong>Last Admission:</strong> <?php echo formatDateTime($patient['admission_date']); ?></p>
                    <?php endif; ?>
                    <?php if ($patient['discharge_date']): ?>
                        <p><strong>Last Discharge:</strong> <?php echo formatDateTime($patient['discharge_date']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Detailed Information -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="patientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                            <i class="fas fa-calendar-check me-2"></i>Appointments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="medical-tab" data-bs-toggle="tab" data-bs-target="#medical" type="button" role="tab">
                            <i class="fas fa-notes-medical me-2"></i>Medical Records
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="lab-tab" data-bs-toggle="tab" data-bs-target="#lab" type="button" role="tab">
                            <i class="fas fa-flask me-2"></i>Lab Results
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="billing-tab" data-bs-toggle="tab" data-bs-target="#billing" type="button" role="tab">
                            <i class="fas fa-file-invoice me-2"></i>Billing
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="patientTabsContent">
                    <!-- Appointments Tab -->
                    <div class="tab-pane fade show active" id="appointments" role="tabpanel">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h5>No Appointments</h5>
                                <p class="text-muted">No appointments found for this patient.</p>
                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                    <a href="../appointments/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">Book First Appointment</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Doctor</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                                </td>
                                                <td><?php echo ucfirst($appointment['type']); ?></td>
                                                <td><?php echo getStatusBadge($appointment['status']); ?></td>
                                                <td>
                                                    <a href="../appointments/view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Medical Records Tab -->
                    <div class="tab-pane fade" id="medical" role="tabpanel">
                        <?php if (empty($medical_records)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-notes-medical fa-3x text-muted mb-3"></i>
                                <h5>No Medical Records</h5>
                                <p class="text-muted">No medical records found for this patient.</p>
                                <?php if (hasRole('admin') || hasRole('doctor')): ?>
                                    <a href="../medical_records/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">Add Medical Record</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($medical_records as $record): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($record['title']); ?></h6>
                                                    <p class="mb-1"><?php echo htmlspecialchars($record['description']); ?></p>
                                                    <small class="text-muted">
                                                        Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?> • 
                                                        <?php echo timeAgo($record['created_at']); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-primary"><?php echo ucfirst($record['type']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Lab Results Tab -->
                    <div class="tab-pane fade" id="lab" role="tabpanel">
                        <?php if (empty($lab_results)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-flask fa-3x text-muted mb-3"></i>
                                <h5>No Lab Results</h5>
                                <p class="text-muted">No lab results found for this patient.</p>
                                <?php if (hasRole('admin') || hasRole('doctor')): ?>
                                    <a href="../laboratory/request.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">Request Lab Test</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($lab_results as $result): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($result['test_name']); ?></h6>
                                                <p class="card-text">
                                                    <strong>Result:</strong> <?php echo htmlspecialchars($result['result_value'] ?? 'Pending'); ?><br>
                                                    <strong>Status:</strong> <?php echo getStatusBadge($result['status']); ?><br>
                                                    <strong>Date:</strong> <?php echo formatDate($result['created_at']); ?>
                                                </p>
                                                <a href="../laboratory/result.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Billing Tab -->
                    <div class="tab-pane fade" id="billing" role="tabpanel">
                        <?php if (empty($invoices)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h5>No Invoices</h5>
                                <p class="text-muted">No invoices found for this patient.</p>
                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                    <a href="../billing/create.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">Create Invoice</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Invoice ID</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($invoice['invoice_id']); ?></span></td>
                                                <td><?php echo formatDate($invoice['invoice_date']); ?></td>
                                                <td><?php echo $invoice['item_count']; ?></td>
                                                <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                                                <td><?php echo getStatusBadge($invoice['status']); ?></td>
                                                <td>
                                                    <a href="../billing/view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
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
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -34px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--bs-primary);
    border: 2px solid white;
}

.timeline-content {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
}
</style>

<?php include '../includes/footer.php'; ?>
