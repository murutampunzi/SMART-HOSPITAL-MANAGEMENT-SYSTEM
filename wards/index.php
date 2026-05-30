<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'nurse', 'receptionist']);

$page_title = "Ward Management - Smart Hospital Management System";
$page_heading = "Ward Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$ward_id = intval($_GET['ward_id'] ?? 0);
$bed_status = sanitizeInput($_GET['bed_status'] ?? '');
$floor = sanitizeInput($_GET['floor'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(b.bed_number LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR w.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 4);
}

if ($ward_id > 0) {
    $where_conditions[] = "b.ward_id = ?";
    $params[] = $ward_id;
    $types .= 'i';
}

if (!empty($bed_status)) {
    $where_conditions[] = "b.status = ?";
    $params[] = $bed_status;
    $types .= 's';
}

if (!empty($floor)) {
    $where_conditions[] = "w.floor = ?";
    $params[] = $floor;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM beds b 
             JOIN wards w ON b.ward_id = w.id 
             LEFT JOIN patients p ON b.current_patient_id = p.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_beds = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_beds, ITEMS_PER_PAGE, $page);

// Get beds
$sql = "SELECT b.*, w.name as ward_name, w.floor, w.capacity,
              p.first_name as patient_first_name, p.last_name as patient_last_name,
              p.patient_id as patient_number, p.admission_date,
              u.name as assigned_nurse_name,
              DATEDIFF(CURDATE(), p.admission_date) as days_stayed
       FROM beds b 
       JOIN wards w ON b.ward_id = w.id 
       LEFT JOIN patients p ON b.current_patient_id = p.id
       LEFT JOIN users u ON b.assigned_nurse_id = u.id
       $where_clause 
       ORDER BY w.floor, w.name, b.bed_number 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$beds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get wards for filter
$wards = query("SELECT id, name, floor, capacity FROM wards ORDER BY floor, name")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_beds'] = query("SELECT COUNT(*) as count FROM beds")->fetch_assoc()['count'];
$stats['occupied_beds'] = query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch_assoc()['count'];
$stats['available_beds'] = query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch_assoc()['count'];
$stats['maintenance_beds'] = query("SELECT COUNT(*) as count FROM beds WHERE status = 'maintenance'")->fetch_assoc()['count'];
$stats['total_patients'] = query("SELECT COUNT(*) as count FROM patients WHERE discharge_date IS NULL")->fetch_assoc()['count'];

// Get recent admissions
$recent_admissions = [];
$result = query("SELECT p.patient_id, p.first_name, p.last_name, p.admission_date,
                      b.bed_number, w.name as ward_name, w.floor
               FROM patients p
               JOIN beds b ON p.assigned_bed_id = b.id
               JOIN wards w ON b.ward_id = w.id
               ORDER BY p.admission_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_admissions[] = $row;
}

// Get bed occupancy by ward
$ward_occupancy = [];
$result = query("SELECT w.id, w.name, w.capacity,
                      COUNT(CASE WHEN b.status = 'occupied' THEN 1 END) as occupied,
                      COUNT(CASE WHEN b.status = 'available' THEN 1 END) as available
               FROM wards w
               LEFT JOIN beds b ON w.id = b.ward_id
               GROUP BY w.id, w.name, w.capacity");
while ($row = $result->fetch_assoc()) {
    $ward_occupancy[] = $row;
}

include '../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-bed fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_beds']; ?></h4>
                        <p class="mb-0">Total Beds</p>
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
                        <i class="fas fa-user-injured fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['occupied_beds']; ?></h4>
                        <p class="mb-0">Occupied</p>
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
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['available_beds']; ?></h4>
                        <p class="mb-0">Available</p>
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
                        <i class="fas fa-tools fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['maintenance_beds']; ?></h4>
                        <p class="mb-0">Maintenance</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ward Occupancy Overview -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Ward Occupancy Overview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($ward_occupancy as $ward): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($ward['name']); ?></h6>
                                    <small class="text-muted">Floor <?php echo $ward['floor']; ?></small>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <?php
                                    $occupancy_rate = $ward['capacity'] > 0 ? ($ward['occupied'] / $ward['capacity']) * 100 : 0;
                                    $color = $occupancy_rate > 80 ? 'bg-danger' : ($occupancy_rate > 60 ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $occupancy_rate; ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between text-muted">
                                    <small><?php echo $ward['occupied']; ?> occupied</small>
                                    <small><?php echo $ward['available']; ?> available</small>
                                    <small><?php echo $ward['capacity']; ?> total</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search Beds</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by bed number, patient, ward...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="ward_id" class="form-label">Ward</label>
                        <select class="form-select" id="ward_id" name="ward_id">
                            <option value="">All Wards</option>
                            <?php foreach ($wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo $ward_id === $ward['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['name']); ?> (Floor <?php echo $ward['floor']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="bed_status" class="form-label">Bed Status</label>
                        <select class="form-select" id="bed_status" name="bed_status">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $bed_status === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="occupied" <?php echo $bed_status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="maintenance" <?php echo $bed_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="reserved" <?php echo $bed_status === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="floor" class="form-label">Floor</label>
                        <select class="form-select" id="floor" name="floor">
                            <option value="">All Floors</option>
                            <option value="1" <?php echo $floor === '1' ? 'selected' : ''; ?>>1st Floor</option>
                            <option value="2" <?php echo $floor === '2' ? 'selected' : ''; ?>>2nd Floor</option>
                            <option value="3" <?php echo $floor === '3' ? 'selected' : ''; ?>>3rd Floor</option>
                            <option value="4" <?php echo $floor === '4' ? 'selected' : ''; ?>>4th Floor</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="btn-group w-100" role="group">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                            <a href="admit.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> Admit
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
                <h5 class="mb-0">Bed Management</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_beds); ?> of <?php echo $total_beds; ?> beds</small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="exportBeds()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-outline-info" onclick="printBeds()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Beds Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($beds)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                        <h5>No Beds Found</h5>
                        <p class="text-muted">No beds match your search criteria.</p>
                        <a href="manage.php" class="btn btn-primary">Manage Wards</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Bed Number</th>
                                    <th>Ward</th>
                                    <th>Floor</th>
                                    <th>Patient</th>
                                    <th>Days Stayed</th>
                                    <th>Assigned Nurse</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($beds as $bed): ?>
                                    <tr class="<?php 
                                        if ($bed['status'] === 'available') {
                                            $class = 'table-success';
                                        } elseif ($bed['status'] === 'occupied') {
                                            $class = 'table-primary';
                                        } elseif ($bed['status'] === 'maintenance') {
                                            $class = 'table-warning';
                                        } else {
                                            $class = 'table-secondary';
                                        }
                                        echo $class;
                                    ?>">
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($bed['bed_number']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($bed['ward_name']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">Floor <?php echo $bed['floor']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($bed['current_patient_id']): ?>
                                                <div class="fw-bold"><?php echo htmlspecialchars($bed['patient_first_name'] . ' ' . $bed['patient_last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($bed['patient_number']); ?></small>
                                                <br><small class="text-muted">Admitted: <?php echo formatDate($bed['admission_date']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bed['days_stayed']): ?>
                                                <span class="badge bg-primary"><?php echo $bed['days_stayed']; ?> days</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bed['assigned_nurse_name']): ?>
                                                <div><?php echo htmlspecialchars($bed['assigned_nurse_name']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($bed['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $bed['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($bed['status'] === 'available' && hasRole('admin') || hasRole('receptionist')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Assign Patient" 
                                                            onclick="assignPatient(<?php echo $bed['id']; ?>)">
                                                        <i class="fas fa-user-plus"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($bed['status'] === 'occupied' && (hasRole('nurse') || hasRole('admin'))) : ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Patient Care" 
                                                            onclick="patientCare(<?php echo $bed['id']; ?>)">
                                                        <i class="fas fa-heartbeat"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($bed['status'] === 'occupied' && hasRole('admin')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Discharge" 
                                                            onclick="dischargePatient(<?php echo $bed['id']; ?>)">
                                                        <i class="fas fa-sign-out-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (hasRole('admin')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Maintenance" 
                                                            onclick="toggleMaintenance(<?php echo $bed['id']; ?>)">
                                                        <i class="fas fa-tools"></i>
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

<!-- Recent Admissions -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Admissions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_admissions)): ?>
                    <p class="text-muted text-center">No recent admissions</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_admissions as $admission): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($admission['first_name'] . ' ' . $admission['last_name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($admission['patient_number']); ?> • 
                                            Bed: <?php echo htmlspecialchars($admission['bed_number']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($admission['ward_name']); ?> (Floor <?php echo $admission['floor']; ?>)
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-info">Admitted</div>
                                        <small class="text-muted"><?php echo timeAgo($admission['admission_date']); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                        <div class="col-6">
                            <a href="admit.php" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>Admit Patient
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-6">
                        <a href="manage.php" class="btn btn-success w-100">
                            <i class="fas fa-cog me-2"></i>Manage Wards
                        </a>
                    </div>
                    
                    <?php if (hasRole('nurse')): ?>
                        <div class="col-6">
                            <a href="rounds.php" class="btn btn-info w-100">
                                <i class="fas fa-clipboard-list me-2"></i>Rounds
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-6">
                        <a href="schedule.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-calendar-alt me-2"></i>Schedule
                        </a>
                    </div>
                    
                    <div class="col-6">
                        <a href="reports.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </div>
                    
                    <div class="col-12">
                        <a href="discharges.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-sign-out-alt me-2"></i>Discharges
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Patient Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Patient to Bed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="assign_patient.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="assignBedId" name="bed_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="patient_id" class="form-label">Select Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php
                            $patients = query("SELECT id, first_name, last_name, patient_id FROM patients WHERE discharge_date IS NULL ORDER BY first_name, last_name");
                            while ($patient = $patients->fetch_assoc()) {
                                echo '<option value="' . $patient['id'] . '">' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . htmlspecialchars($patient['patient_id']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="admission_notes" class="form-label">Admission Notes</label>
                        <textarea class="form-control" id="admission_notes" name="admission_notes" rows="3" placeholder="Any special instructions or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function assignPatient(bedId) {
    document.getElementById('assignBedId').value = bedId;
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

function patientCare(bedId) {
    window.location.href = 'patient_care.php?bed_id=' + bedId;
}

function dischargePatient(bedId) {
    if (confirm('Discharge this patient from the bed?')) {
        window.location.href = 'discharge.php?bed_id=' + bedId;
    }
}

function toggleMaintenance(bedId) {
    if (confirm('Toggle maintenance status for this bed?')) {
        window.location.href = 'toggle_maintenance.php?bed_id=' + bedId;
    }
}

function exportBeds() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printBeds() {
    window.print();
}

// Auto-refresh every 60 seconds for real-time updates
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 60000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'admit.php';
    }
});
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .table {
        font-size: 12px;
    }
    
    .btn-group {
        display: none !important;
    }
    
    .alert {
        display: none !important;
    }
}

.occupied-row {
    animation: breathe 2s infinite;
}

@keyframes breathe {
    0% { background-color: rgba(0, 123, 255, 0.1); }
    50% { background-color: rgba(0, 123, 255, 0.2); }
    100% { background-color: rgba(0, 123, 255, 0.1); }
}
</style>

<?php include '../includes/footer.php'; ?>
