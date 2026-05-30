<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'nurse', 'receptionist']);

$page_title = "Patients - Smart Hospital Management System";
$page_heading = "Patient Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$blood_group = sanitizeInput($_GET['blood_group'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 5);
}

if (!empty($status)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($blood_group)) {
    $where_conditions[] = "p.blood_group = ?";
    $params[] = $blood_group;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM patients p $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_patients = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_patients, ITEMS_PER_PAGE, $page);

// Get patients
$sql = "SELECT p.*, u.email as user_email 
        FROM patients p 
        LEFT JOIN users u ON p.user_id = u.id 
        $where_clause 
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Patients</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, ID, phone, email...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="blood_group" class="form-label">Blood Group</label>
                        <select class="form-select" id="blood_group" name="blood_group">
                            <option value="">All Blood Groups</option>
                            <option value="A+" <?php echo $blood_group === 'A+' ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo $blood_group === 'A-' ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo $blood_group === 'B+' ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo $blood_group === 'B-' ? 'selected' : ''; ?>>B-</option>
                            <option value="O+" <?php echo $blood_group === 'O+' ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo $blood_group === 'O-' ? 'selected' : ''; ?>>O-</option>
                            <option value="AB+" <?php echo $blood_group === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo $blood_group === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
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
                <h5 class="mb-0">Patients List</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_patients); ?> of <?php echo $total_patients; ?> patients</small>
            </div>
            <div>
                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Patient
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="exportPatients()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Patients Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($patients)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-injured fa-3x text-muted mb-3"></i>
                        <h5>No Patients Found</h5>
                        <p class="text-muted">No patients match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                            <a href="create.php" class="btn btn-primary">Add First Patient</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Age/Gender</th>
                                    <th>Blood Group</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <?php if ($patient['profile_image']): ?>
                                                        <img src="../uploads/patients/<?php echo htmlspecialchars($patient['profile_image']); ?>" 
                                                             alt="Profile" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($patient['user_email'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($patient['phone']); ?></div>
                                            <?php if ($patient['address']): ?>
                                                <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo truncateText($patient['address'], 30); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $dob = new DateTime($patient['date_of_birth']);
                                            $now = new DateTime();
                                            $age = $now->diff($dob)->y;
                                            ?>
                                            <div><?php echo $age; ?> years</div>
                                            <small class="text-muted"><?php echo ucfirst($patient['gender']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($patient['blood_group']): ?>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars($patient['blood_group']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($patient['status']); ?></td>
                                        <td>
                                            <small><?php echo formatDate($patient['created_at']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                                    <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (hasRole('admin')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" 
                                                            onclick="confirmDelete(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete patient <strong id="deletePatientName"></strong>?</p>
                <p class="text-danger small">This action cannot be undone and will also delete all associated records.</p>
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
function confirmDelete(patientId, patientName) {
    document.getElementById('deletePatientName').textContent = patientName;
    document.getElementById('deleteForm').action = 'delete.php?id=' + patientId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function exportPatients() {
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
</script>

<?php include '../includes/footer.php'; ?>
