<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$page_title = "Dashboard - Smart Hospital Management System";
$page_heading = "Dashboard";

// Get dashboard statistics
$stats = [];

// Total patients
$stats['total_patients'] = query("SELECT COUNT(*) as count FROM patients WHERE status = 'active'")->fetch_assoc()['count'];

// Total doctors
$stats['total_doctors'] = query("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'")->fetch_assoc()['count'];

// Today's appointments
$stats['today_appointments'] = query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch_assoc()['count'];

// Total appointments
$stats['total_appointments'] = query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];

// Pending lab tests
$stats['pending_lab_tests'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE status IN ('pending', 'sample_collected')")->fetch_assoc()['count'];

// Total revenue this month
$stats['monthly_revenue'] = query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'completed'")->fetch_assoc()['total'];

// Unpaid Invoices
$stats['unpaid_invoices'] = query("SELECT COUNT(*) as count FROM invoices WHERE status IN ('draft', 'sent', 'overdue')")->fetch_assoc()['count'];

// Low Stock Medicines
$stats['low_stock_items'] = query("SELECT COUNT(*) as count FROM medicines WHERE stock_quantity <= reorder_level")->fetch_assoc()['count'];

// Recent appointments
$recent_appointments = [];
$result = query("SELECT a.*, p.first_name, p.last_name, d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                 FROM appointments a 
                 JOIN patients p ON a.patient_id = p.id 
                 JOIN doctors d ON a.doctor_id = d.id 
                 ORDER BY a.created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_appointments[] = $row;
}

// Recent lab results
$recent_lab_results = [];
$result = query("SELECT lr.*, ltr.request_id, p.first_name, p.last_name, lt.name as test_name 
                 FROM lab_results lr 
                 JOIN lab_test_requests ltr ON lr.request_id = ltr.id 
                 JOIN patients p ON lr.patient_id = p.id 
                 JOIN lab_tests lt ON lr.test_id = lt.id 
                 ORDER BY lr.created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_lab_results[] = $row;
}

// Upcoming appointments for current user
$upcoming_appointments = [];
if (hasRole('doctor')) {
    $user_id = $_SESSION['user_id'];
    $result = query("SELECT a.*, p.first_name, p.last_name, p.phone 
                     FROM appointments a 
                     JOIN patients p ON a.patient_id = p.id 
                     JOIN doctors d ON a.doctor_id = d.id 
                     JOIN users u ON d.user_id = u.id 
                     WHERE u.id = $user_id AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed') 
                     ORDER BY a.appointment_date, a.appointment_time LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $upcoming_appointments[] = $row;
    }
} elseif (hasRole('patient')) {
    $user_id = $_SESSION['user_id'];
    $result = query("SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization 
                     FROM appointments a 
                     JOIN doctors d ON a.doctor_id = d.id 
                     JOIN patients p ON a.patient_id = p.id 
                     JOIN users u ON p.user_id = u.id 
                     WHERE u.id = $user_id AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed') 
                     ORDER BY a.appointment_date, a.appointment_time LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $upcoming_appointments[] = $row;
    }
}

// Chart data
$appointment_chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$date'")->fetch_assoc()['count'];
    $appointment_chart_data[] = [
        'date' => date('M j', strtotime($date)),
        'count' => $count
    ];
}

$revenue_chart_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $revenue = query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month' AND status = 'completed'")->fetch_assoc()['total'];
    $revenue_chart_data[] = [
        'month' => date('M Y', strtotime($month)),
        'revenue' => $revenue
    ];
}

include 'includes/header.php';
?>

<!-- Dashboard Statistics -->
<div class="row mb-4 animate-fade-in">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stat-icon text-white">
                            <i class="fas fa-user-injured"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="card-title mb-0"><?php echo number_format($stats['total_patients']); ?></h3>
                        <p class="card-text text-muted mb-0">Total Patients</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stat-icon text-white">
                            <i class="fas fa-user-md"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="card-title mb-0"><?php echo number_format($stats['total_doctors']); ?></h3>
                        <p class="card-text text-muted mb-0">Total Doctors</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stat-icon text-white">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="card-title mb-0"><?php echo number_format($stats['today_appointments']); ?></h3>
                        <p class="card-text text-muted mb-0">Today's Appointments</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stat-icon text-white">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="card-title mb-0"><?php echo formatCurrency($stats['monthly_revenue']); ?></h3>
                        <p class="card-text text-muted mb-0">Monthly Revenue</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Weekly Appointments</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas data-chart="line" data-chart-data='<?php echo json_encode([
                        'labels' => array_column($appointment_chart_data, 'date'),
                        'datasets' => [[
                            'label' => 'Appointments',
                            'data' => array_column($appointment_chart_data, 'count'),
                            'borderColor' => 'rgb(75, 192, 192)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'tension' => 0.1
                        ]]
                    ]); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-bolt text-primary me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                        <a href="appointments/create.php" class="btn btn-primary w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-plus text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">New Appointment</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Create a patient booking</small>
                            </div>
                        </a>
                        <a href="patients/create.php" class="btn btn-success w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-user-plus text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">Register Patient</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Add a new patient record</small>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasRole('patient')): ?>
                        <a href="appointments/book.php" class="btn btn-primary w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-calendar-plus text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">Book Appointment</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Schedule a consultation</small>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasRole('doctor')): ?>
                        <a href="appointments/schedule.php" class="btn btn-info w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-calendar text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">My Schedule</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">View your consultation calendar</small>
                            </div>
                        </a>
                        <a href="radiology/index.php" class="btn btn-primary w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-x-ray text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">Radiology Scans</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Review patient radiology imaging</small>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasRole('pharmacist')): ?>
                        <a href="pharmacy/index.php" class="btn btn-warning w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-dark bg-opacity-10 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-pills text-dark fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-dark fs-6">Manage Pharmacy</span>
                                <small class="text-dark bg-opacity-75" style="font-size: 0.75rem; opacity: 0.8;">Dispense & track stock levels</small>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasRole('lab_technician')): ?>
                        <a href="laboratory/index.php" class="btn btn-info w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-flask text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">Lab Tests</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Analyze patient test requests</small>
                            </div>
                        </a>
                        <a href="radiology/index.php" class="btn btn-primary w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-x-ray text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">Radiology Scans</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Review radiology scans</small>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasRole('admin')): ?>
                        <a href="radiology/index.php" class="btn btn-primary w-100 text-start p-3 d-flex align-items-center justify-content-start border-0">
                            <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                                <i class="fas fa-x-ray text-white fs-5"></i>
                            </div>
                            <div class="d-flex flex-column text-start">
                                <span class="fw-bold text-white fs-6">Radiology Department</span>
                                <small class="text-white bg-opacity-75" style="font-size: 0.75rem; opacity: 0.85;">Manage radiology imaging</small>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <a href="reports/index.php" class="btn btn-outline-secondary w-100 text-start p-3 d-flex align-items-center justify-content-start">
                        <div class="d-flex align-items-center justify-content-center bg-secondary bg-opacity-10 rounded-circle me-3" style="width: 42px; height: 42px; min-width: 42px;">
                            <i class="fas fa-chart-bar text-secondary fs-5"></i>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="fw-bold fs-6">View Reports</span>
                            <small class="text-muted" style="font-size: 0.75rem;">View system analytics & reports</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-calendar-check text-primary me-2"></i>Recent Appointments</h5>
                <a href="appointments/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_appointments)): ?>
                    <p class="text-muted text-center py-4">No recent appointments</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-initial rounded-circle bg-light-primary text-primary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: 600; font-size: 0.8rem; background-color: rgba(79, 70, 229, 0.1);">
                                                    <?php echo strtoupper(substr($appointment['first_name'], 0, 1) . substr($appointment['last_name'], 0, 1)); ?>
                                                </div>
                                                <span class="fw-semibold text-primary"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-initial rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: 600; font-size: 0.8rem; background-color: rgba(16, 185, 129, 0.1); color: var(--success);">
                                                    <?php echo strtoupper(substr($appointment['doctor_first_name'], 0, 1) . substr($appointment['doctor_last_name'], 0, 1)); ?>
                                                </div>
                                                <span>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5 small text-secondary">
                                                <i class="far fa-calendar text-muted"></i>
                                                <?php echo formatDate($appointment['appointment_date']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo getStatusBadge($appointment['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-flask text-primary me-2"></i>Recent Lab Results</h5>
                <a href="laboratory/results.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_lab_results)): ?>
                    <p class="text-muted text-center py-4">No recent lab results</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Result</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_lab_results as $result): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-initial rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: 600; font-size: 0.8rem; background-color: rgba(79, 70, 229, 0.1); color: var(--primary);">
                                                    <?php echo strtoupper(substr($result['first_name'], 0, 1) . substr($result['last_name'], 0, 1)); ?>
                                                </div>
                                                <span class="fw-semibold text-primary"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center text-secondary">
                                                <i class="fas fa-microscope text-muted me-2 fs-7"></i>
                                                <span><?php echo htmlspecialchars($result['test_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($result['result_value'] === null || $result['result_value'] === ''): ?>
                                                <span class="text-muted fst-italic small">Pending Analysis</span>
                                            <?php else: ?>
                                                <span class="fw-bold text-primary"><?php echo htmlspecialchars($result['result_value']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($result['status']); ?></td>
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

<!-- Upcoming Appointments (Role-specific) -->
<?php if (!empty($upcoming_appointments)): ?>
    <div class="row mb-4 animate-fade-in">
        <div class="col-12">
            <div class="card shadow-md">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        <?php echo hasRole('doctor') ? 'Your Scheduled Consultations (Doctor View)' : 'Your Booked Consultations (Patient View)'; ?>
                    </h5>
                    <span class="badge bg-primary"><?php echo count($upcoming_appointments); ?> Upcoming</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <?php if (hasRole('doctor')): ?>
                                        <th>Patient</th>
                                        <th>Phone</th>
                                    <?php else: ?>
                                        <th>Doctor</th>
                                        <th>Specialization</th>
                                    <?php endif; ?>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <tr>
                                        <?php if (hasRole('doctor')): ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initial rounded-circle bg-light-primary text-primary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: 600; font-size: 0.8rem; background-color: rgba(79, 70, 229, 0.1);">
                                                        <?php echo strtoupper(substr($appointment['first_name'], 0, 1) . substr($appointment['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <span class="fw-semibold text-primary"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-secondary small">
                                                    <i class="fas fa-phone-alt text-muted me-1.5 fs-7"></i>
                                                    <?php echo htmlspecialchars($appointment['phone']); ?>
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initial rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: 600; font-size: 0.8rem; background-color: rgba(16, 185, 129, 0.1); color: var(--success);">
                                                        <?php echo strtoupper(substr($appointment['doctor_first_name'], 0, 1) . substr($appointment['doctor_last_name'], 0, 1)); ?>
                                                    </div>
                                                    <span class="fw-semibold text-success">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-stethoscope me-1 fs-8"></i>
                                                    <?php echo htmlspecialchars($appointment['specialization']); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5 small text-secondary">
                                                <i class="far fa-calendar text-muted"></i>
                                                <?php echo formatDate($appointment['appointment_date']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1.5 small text-secondary">
                                                <i class="far fa-clock text-muted"></i>
                                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $type_class = 'bg-secondary';
                                            $type = strtolower($appointment['type']);
                                            if ($type === 'consultation' || $type === 'first visit') {
                                                $type_class = 'bg-primary';
                                            } elseif ($type === 'follow-up' || $type === 'followup') {
                                                $type_class = 'bg-success';
                                            } elseif ($type === 'emergency') {
                                                $type_class = 'bg-danger';
                                            } elseif ($type === 'checkup' || $type === 'routine') {
                                                $type_class = 'bg-info';
                                            }
                                            ?>
                                            <span class="badge <?php echo $type_class; ?>"><?php echo ucfirst($appointment['type']); ?></span>
                                        </td>
                                        <td><?php echo getStatusBadge($appointment['status']); ?></td>
                                        <td>
                                            <a href="appointments/view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Revenue Chart (Admin only) -->
<?php if (hasRole('admin')): ?>
    <div class="row mb-4 animate-fade-in">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Revenue Overview (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas data-chart="bar" data-chart-data='<?php echo json_encode([
                            'labels' => array_column($revenue_chart_data, 'month'),
                            'datasets' => [[
                                'label' => 'Revenue',
                                'data' => array_column($revenue_chart_data, 'revenue')
                            ]]
                        ]); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- System Status, Pending Tasks & Recent Activity (Admin only) -->
<?php if (hasRole('admin')): ?>
    <div class="row animate-fade-in">
        <!-- System Status -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-server text-primary me-2"></i>System Status</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-database text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Database Engine</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator online"></span>
                                <span class="badge bg-success">Online</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-hdd text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Disk Storage</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator online"></span>
                                <span class="badge bg-success">Healthy</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-cloud-upload-alt text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Automated Backup</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator online"></span>
                                <span class="badge bg-success">Updated</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shield-alt text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Core Services</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator online"></span>
                                <span class="badge bg-success">Running</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Tasks -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-tasks text-primary me-2"></i>Pending Tasks</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-flask text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Pending Lab Tests</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator <?php echo $stats['pending_lab_tests'] > 0 ? 'busy' : 'online'; ?>"></span>
                                <span class="badge bg-warning"><?php echo $stats['pending_lab_tests']; ?> Pending</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-invoice-dollar text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Unpaid Invoices</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator <?php echo $stats['unpaid_invoices'] > 0 ? 'busy' : 'online'; ?>"></span>
                                <span class="badge bg-warning"><?php echo $stats['unpaid_invoices']; ?> Unpaid</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle text-muted me-2 fs-5"></i>
                                <span class="fw-semibold text-secondary">Low Stock Medicines</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-indicator <?php echo $stats['low_stock_items'] > 0 ? 'busy' : 'online'; ?>"></span>
                                <span class="badge bg-danger"><?php echo $stats['low_stock_items']; ?> Items</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Feed -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-history text-primary me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php
                    $recent_activities = [];
                    $result = query("SELECT al.*, u.name FROM activity_logs al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 5");
                    while ($row = $result->fetch_assoc()) {
                        $recent_activities[] = $row;
                    }
                    ?>
                    
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-muted text-center py-4">No recent activity</p>
                    <?php else: ?>
                        <div class="activity-feed">
                            <?php foreach ($recent_activities as $activity): 
                                // Dynamically assign beautiful icons and colors based on action type
                                $icon = 'bolt';
                                $color = 'primary';
                                $action_lower = strtolower($activity['action']);
                                if (strpos($action_lower, 'login') !== false || strpos($action_lower, 'logout') !== false) {
                                    $icon = 'sign-in-alt';
                                    $color = 'info';
                                } elseif (strpos($action_lower, 'appointment') !== false) {
                                    $icon = 'calendar-check';
                                    $color = 'success';
                                } elseif (strpos($action_lower, 'patient') !== false) {
                                    $icon = 'user-injured';
                                    $color = 'primary';
                                } elseif (strpos($action_lower, 'billing') !== false || strpos($action_lower, 'payment') !== false || strpos($action_lower, 'invoice') !== false) {
                                    $icon = 'file-invoice-dollar';
                                    $color = 'warning';
                                } elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'remove') !== false) {
                                    $icon = 'trash-alt';
                                    $color = 'danger';
                                }
                                
                                // Determine text color classes matching theme definitions
                                $bg_opacity_color = 'rgba(79, 70, 229, 0.1)';
                                $text_color_class = 'var(--primary)';
                                if ($color === 'success') {
                                    $bg_opacity_color = 'rgba(16, 185, 129, 0.1)';
                                    $text_color_class = 'var(--success)';
                                } elseif ($color === 'info') {
                                    $bg_opacity_color = 'rgba(6, 182, 212, 0.1)';
                                    $text_color_class = 'var(--info)';
                                } elseif ($color === 'warning') {
                                    $bg_opacity_color = 'rgba(245, 158, 11, 0.1)';
                                    $text_color_class = 'var(--warning)';
                                } elseif ($color === 'danger') {
                                    $bg_opacity_color = 'rgba(244, 63, 94, 0.1)';
                                    $text_color_class = 'var(--danger)';
                                }
                            ?>
                                <div class="activity-item">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; min-width: 36px; background-color: <?php echo $bg_opacity_color; ?>; color: <?php echo $text_color_class; ?>;">
                                        <i class="fas fa-<?php echo $icon; ?> fs-6"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="fw-semibold text-primary small"><?php echo htmlspecialchars($activity['name']); ?></span>
                                            <small class="text-muted" style="font-size: 0.7rem;"><?php echo timeAgo($activity['created_at']); ?></small>
                                        </div>
                                        <p class="mb-0 small text-secondary mt-0.5"><?php echo htmlspecialchars($activity['action']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
