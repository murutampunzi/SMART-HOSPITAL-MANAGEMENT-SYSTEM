<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$page_title = "Appointments - Smart Hospital Management System";
$page_heading = "Appointment Management";

// Get user role and ID
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$doctor_id = intval($_GET['doctor_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build base query based on user role
$base_sql = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.phone as patient_phone,
                   d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialization,
                   u_d.email as doctor_email
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.id 
            JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN users u_d ON d.user_id = u_d.id";

// Add role-specific conditions
$where_conditions = [];
$params = [];
$types = '';

if ($user_role === 'doctor') {
    // Doctors can only see their own appointments
    $doctor_sql = "SELECT id FROM doctors WHERE user_id = ?";
    $doctor_stmt = prepare($doctor_sql);
    $doctor_stmt->bind_param("i", $user_id);
    $doctor_stmt->execute();
    $doctor_result = $doctor_stmt->get_result();
    $doctor_record = $doctor_result->fetch_assoc();
    
    if ($doctor_record) {
        $where_conditions[] = "a.doctor_id = ?";
        $params[] = $doctor_record['id'];
        $types .= 'i';
    }
} elseif ($user_role === 'patient') {
    // Patients can only see their own appointments
    $patient_sql = "SELECT id FROM patients WHERE user_id = ?";
    $patient_stmt = prepare($patient_sql);
    $patient_stmt->bind_param("i", $user_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    $patient_record = $patient_result->fetch_assoc();
    
    if ($patient_record) {
        $where_conditions[] = "a.patient_id = ?";
        $params[] = $patient_record['id'];
        $types .= 'i';
    }
}

// Add search conditions
if (!empty($search)) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR a.appointment_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 5);
}

if (!empty($status)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($doctor_id > 0 && hasRole('admin')) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_id;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM appointments a $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_appointments = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_appointments, ITEMS_PER_PAGE, $page);

// Get appointments
$sql = "$base_sql $where_clause ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get doctors for filter
$doctors = [];
if (hasRole('admin')) {
    $result = query("SELECT id, first_name, last_name, specialization FROM doctors WHERE status = 'active' ORDER BY first_name, last_name");
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Get today's appointments count
$today_count = query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch_assoc()['count'];

// Get upcoming appointments count
$upcoming_count = query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')")->fetch_assoc()['count'];

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search Appointments</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by patient, doctor, ID...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <?php if (hasRole('admin') && !empty($doctors)): ?>
                        <div class="col-md-2">
                            <label for="doctor_id" class="form-label">Doctor</label>
                            <select class="form-select" id="doctor_id" name="doctor_id">
                                <option value="">All Doctors</option>
                                <?php foreach ($doctors as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>" 
                                            <?php echo $doctor_id === $doc['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name'] . ' - ' . $doc['specialization']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Actions Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Appointments List</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_appointments); ?> of <?php echo $total_appointments; ?> appointments</small>
            </div>
            <div>
                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Appointment
                    </a>
                <?php endif; ?>
                <?php if (hasRole('patient')): ?>
                    <a href="book.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="exportAppointments()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $today_count; ?></h4>
                        <p class="mb-0">Today's Appointments</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $upcoming_count; ?></h4>
                        <p class="mb-0">Upcoming</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")->fetch_assoc()['count']; ?></h4>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $total_appointments; ?></h4>
                        <p class="mb-0">Total Appointments</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointments Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5>No Appointments Found</h5>
                        <p class="text-muted">No appointments match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                            <a href="create.php" class="btn btn-primary">Create Appointment</a>
                        <?php elseif (hasRole('patient')): ?>
                            <a href="book.php" class="btn btn-primary">Book Appointment</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Appointment ID</th>
                                    <th>Patient</th>
                                    <?php if (!hasRole('doctor')): ?>
                                        <th>Doctor</th>
                                    <?php endif; ?>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                        </td>
                                        <?php if (!hasRole('doctor')): ?>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <div><?php echo formatDate($appointment['appointment_date']); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($appointment['type']); ?></span>
                                        </td>
                                        <td><?php echo getStatusBadge($appointment['status']); ?></td>
                                        <td>
                                            <div class="text-end">
                                                <small><?php echo getStatusBadge($appointment['payment_status'], $appointment['payment_status'] === 'paid' ? 'success' : 'warning'); ?></small>
                                                <?php if ($appointment['payment_amount'] > 0): ?>
                                                    <br><small><?php echo formatCurrency($appointment['payment_amount']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                                    <a href="edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (hasRole('doctor') && in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Start Consultation" 
                                                            onclick="startConsultation(<?php echo $appointment['id']; ?>)">
                                                        <i class="fas fa-stethoscope"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Cancel" 
                                                            onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <small class="text-muted">
                                    Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                                </small>
                            </div>
                            <?php
                            $url_pattern = 'index.php?' . http_build_query(array_merge($_GET, ['page' => '{page}']));
                            echo renderPagination($pagination, $url_pattern);
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
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
                <input type="hidden" id="cancelAppointmentId" name="appointment_id">
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
function cancelAppointment(appointmentId) {
    document.getElementById('cancelAppointmentId').value = appointmentId;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function startConsultation(appointmentId) {
    if (confirm('Are you ready to start the consultation? This will mark the appointment as in progress.')) {
        window.location.href = 'consultation.php?id=' + appointmentId;
    }
}

function exportAppointments() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

// Auto-refresh every 30 seconds for real-time updates
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);

// Set today's date as default for date filters
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    if (!document.getElementById('date_from').value) {
        document.getElementById('date_from').value = today;
    }
    if (!document.getElementById('date_to').value) {
        document.getElementById('date_to').value = today;
    }
});

// Quick status update via AJAX
function updateStatus(appointmentId, newStatus) {
    fetch('update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `appointment_id=${appointmentId}&status=${newStatus}&csrf_token=<?php echo generateCSRFToken(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating status');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
