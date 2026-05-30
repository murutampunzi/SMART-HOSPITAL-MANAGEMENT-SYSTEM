<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'nurse', 'receptionist']);

$page_title = "Doctors - Smart Hospital Management System";
$page_heading = "Doctor Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$specialization = sanitizeInput($_GET['specialization'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(d.first_name LIKE ? OR d.last_name LIKE ? OR d.doctor_id LIKE ? OR d.phone LIKE ? OR d.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 5);
}

if (!empty($specialization)) {
    $where_conditions[] = "d.specialization = ?";
    $params[] = $specialization;
    $types .= 's';
}

if (!empty($status)) {
    $where_conditions[] = "d.status = ?";
    $params[] = $status;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM doctors d $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_doctors = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_doctors, ITEMS_PER_PAGE, $page);

// Get doctors
$sql = "SELECT d.*, u.email as user_email 
        FROM doctors d 
        LEFT JOIN users u ON d.user_id = u.id 
        $where_clause 
        ORDER BY d.first_name, d.last_name 
        LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get specializations for filter
$specializations = query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Doctors</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, ID, phone, email...">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="specialization" class="form-label">Specialization</label>
                        <select class="form-select" id="specialization" name="specialization">
                            <option value="">All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" 
                                        <?php echo $specialization === $spec['specialization'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="on_leave" <?php echo $status === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
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
                <h5 class="mb-0">Doctors List</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_doctors); ?> of <?php echo $total_doctors; ?> doctors</small>
            </div>
            <div>
                <?php if (hasRole('admin')): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Doctor
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="exportDoctors()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Doctors Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($doctors)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                        <h5>No Doctors Found</h5>
                        <p class="text-muted">No doctors match your search criteria.</p>
                        <?php if (hasRole('admin')): ?>
                            <a href="create.php" class="btn btn-primary">Add First Doctor</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Doctor ID</th>
                                    <th>Name</th>
                                    <th>Specialization</th>
                                    <th>Contact</th>
                                    <th>Experience</th>
                                    <th>Consultation Fee</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($doctor['doctor_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <?php if ($doctor['profile_image']): ?>
                                                        <img src="../uploads/doctors/<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                                                             alt="Profile" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user-md"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($doctor['user_email'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                                            <?php if ($doctor['qualification']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($doctor['qualification']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($doctor['phone']); ?></div>
                                            <?php if ($doctor['address']): ?>
                                                <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo truncateText($doctor['address'], 30); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <strong><?php echo $doctor['experience_years']; ?></strong>
                                                <br><small class="text-muted">years</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-end">
                                                <strong><?php echo formatCurrency($doctor['consultation_fee']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo getStatusBadge($doctor['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $doctor['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasRole('admin')): ?>
                                                    <a href="edit.php?id=<?php echo $doctor['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" 
                                                            onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
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

<!-- Doctor Statistics Cards -->
<div class="row mt-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user-md fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo query("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'")->fetch_assoc()['count']; ?></h4>
                        <p class="mb-0">Active Doctors</p>
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
                        <i class="fas fa-stethoscope fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo count($specializations); ?></h4>
                        <p class="mb-0">Specializations</p>
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
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch_assoc()['count']; ?></h4>
                        <p class="mb-0">Today's Appointments</p>
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
                        <i class="fas fa-user-times fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo query("SELECT COUNT(*) as count FROM doctors WHERE status = 'on_leave'")->fetch_assoc()['count']; ?></h4>
                        <p class="mb-0">On Leave</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete doctor <strong id="deleteDoctorName"></strong>?</p>
                <p class="text-danger small">This action cannot be undone and will also delete all associated appointments and records.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <?php echo getCSRFInput(); ?>
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(doctorId, doctorName) {
    document.getElementById('deleteDoctorName').textContent = doctorName;
    document.getElementById('deleteForm').action = 'delete.php?id=' + doctorId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function exportDoctors() {
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

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>

<?php include '../includes/footer.php'; ?>
