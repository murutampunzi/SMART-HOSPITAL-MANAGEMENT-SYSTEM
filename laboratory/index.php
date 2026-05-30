<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician', 'doctor']);

$page_title = "Laboratory - Smart Hospital Management System";
$page_heading = "Laboratory Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$priority = sanitizeInput($_GET['priority'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(ltr.request_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR lt.name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 6);
}

if (!empty($status)) {
    $where_conditions[] = "ltr.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($priority)) {
    $where_conditions[] = "ltr.priority = ?";
    $params[] = $priority;
    $types .= 's';
}

if (!empty($category)) {
    $where_conditions[] = "lt.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "ltr.requested_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "ltr.requested_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM lab_test_requests ltr 
             JOIN patients p ON ltr.patient_id = p.id 
             JOIN lab_tests lt ON ltr.test_id = lt.id 
             LEFT JOIN doctors d ON ltr.doctor_id = d.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_requests = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_requests, ITEMS_PER_PAGE, $page);

// Get lab test requests
$sql = "SELECT ltr.*, p.first_name, p.last_name, p.phone as patient_phone,
              lt.name as test_name, lt.category, lt.sample_type, lt.duration_minutes,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name,
              lr.result_value, lr.status as result_status
       FROM lab_test_requests ltr 
       JOIN patients p ON ltr.patient_id = p.id 
       JOIN lab_tests lt ON ltr.test_id = lt.id 
       LEFT JOIN doctors d ON ltr.doctor_id = d.id 
       LEFT JOIN lab_results lr ON ltr.id = lr.request_id
       $where_clause 
       ORDER BY ltr.requested_date DESC, ltr.priority DESC, ltr.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = query("SELECT DISTINCT category FROM lab_tests WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_requests'] = query("SELECT COUNT(*) as count FROM lab_test_requests")->fetch_assoc()['count'];
$stats['pending'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['in_progress'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE status = 'in_progress'")->fetch_assoc()['count'];
$stats['completed'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE status = 'completed'")->fetch_assoc()['count'];
$stats['urgent'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE priority = 'urgent' AND status IN ('pending', 'in_progress')")->fetch_assoc()['count'];

// Get recent completed tests
$recent_completed = [];
$result = query("SELECT ltr.request_id, ltr.requested_date, p.first_name, p.last_name,
                      lt.name as test_name, lr.result_value, lr.created_at as result_date
               FROM lab_test_requests ltr
               JOIN patients p ON ltr.patient_id = p.id
               JOIN lab_tests lt ON ltr.test_id = lt.id
               JOIN lab_results lr ON ltr.id = lr.request_id
               WHERE ltr.status = 'completed'
               ORDER BY lr.created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_completed[] = $row;
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
                        <i class="fas fa-flask fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_requests']; ?></h4>
                        <p class="mb-0">Total Requests</p>
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
                        <h4 class="mb-0"><?php echo $stats['pending']; ?></h4>
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
                        <i class="fas fa-spinner fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['in_progress']; ?></h4>
                        <p class="mb-0">In Progress</p>
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
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['completed']; ?></h4>
                        <p class="mb-0">Completed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Urgent Requests Alert -->
<?php if ($stats['urgent'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $stats['urgent']; ?> urgent test request(s)</strong> pending attention!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search Requests</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by ID, patient, test, doctor...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="sample_collected" <?php echo $status === 'sample_collected' ? 'selected' : ''; ?>>Sample Collected</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All Priority</option>
                            <option value="normal" <?php echo $priority === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            <option value="stat" <?php echo $priority === 'stat' ? 'selected' : ''; ?>>Stat</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
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
                            <?php if (hasRole('admin') || hasRole('doctor')): ?>
                                <a href="request.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Request
                                </a>
                            <?php endif; ?>
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
                <h5 class="mb-0">Lab Test Requests</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_requests); ?> of <?php echo $total_requests; ?> requests</small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="exportRequests()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-outline-info" onclick="printRequests()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lab Requests Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-flask fa-3x text-muted mb-3"></i>
                        <h5>No Lab Requests Found</h5>
                        <p class="text-muted">No lab test requests match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('doctor')): ?>
                            <a href="request.php" class="btn btn-primary">Create Lab Request</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Doctor</th>
                                    <th>Requested</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Result</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr class="<?php 
                                        echo $request['priority'] === 'stat' ? 'table-danger' : 
                                             ($request['priority'] === 'urgent' ? 'table-warning' : ''); 
                                    ?>">
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($request['request_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($request['patient_phone']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($request['test_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($request['category']); ?> • <?php echo htmlspecialchars($request['sample_type']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($request['doctor_first_name']): ?>
                                                <div><?php echo htmlspecialchars($request['doctor_first_name'] . ' ' . $request['doctor_last_name']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($request['requested_date']); ?></div>
                                            <?php if ($request['sample_collected_date']): ?>
                                                <small class="text-muted">Collected: <?php echo formatDateTime($request['sample_collected_date'], 'H:i'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $priority_colors = [
                                                'normal' => 'secondary',
                                                'urgent' => 'warning',
                                                'stat' => 'danger'
                                            ];
                                            $color = $priority_colors[$request['priority']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($request['priority']); ?></span>
                                        </td>
                                        <td><?php echo getStatusBadge($request['status']); ?></td>
                                        <td>
                                            <?php if ($request['result_value']): ?>
                                                <div class="fw-bold"><?php echo htmlspecialchars($request['result_value']); ?></div>
                                                <small class="text-muted"><?php echo getStatusBadge($request['result_status']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if (hasRole('lab_technician')): ?>
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" title="Collect Sample" 
                                                                onclick="collectSample(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-vial"></i>
                                                        </button>
                                                    <?php elseif ($request['status'] === 'sample_collected'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-info" title="Start Test" 
                                                                onclick="startTest(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php elseif ($request['status'] === 'in_progress'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Enter Result" 
                                                                onclick="enterResult(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if (hasRole('admin')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Cancel" 
                                                            onclick="cancelRequest(<?php echo $request['id']; ?>)">
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

<!-- Recent Completed Tests -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Completed Tests</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_completed)): ?>
                    <p class="text-muted text-center">No completed tests yet</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_completed as $test): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($test['test_name']); ?></div>
                                        <small class="text-muted">
                                            Patient: <?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">Request: <?php echo htmlspecialchars($test['request_id']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success"><?php echo htmlspecialchars($test['result_value']); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($test['result_date']); ?></small>
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
                    <?php if (hasRole('admin') || hasRole('doctor')): ?>
                        <div class="col-6">
                            <a href="request.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>New Request
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('lab_technician')): ?>
                        <div class="col-6">
                            <a href="index.php?status=pending" class="btn btn-success w-100">
                                <i class="fas fa-vial me-2"></i>Sample Collection
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="index.php?status=in_progress" class="btn btn-info w-100">
                                <i class="fas fa-edit me-2"></i>Enter Results
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-6">
                        <a href="tests.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-list me-2"></i>Test Catalog
                        </a>
                    </div>
                    
                    <div class="col-6">
                        <a href="reports.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Modals -->
<div class="modal fade" id="sampleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Sample</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="collect_sample.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="sampleRequestId" name="request_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sample_notes" class="form-label">Collection Notes</label>
                        <textarea class="form-control" id="sample_notes" name="sample_notes" rows="3" placeholder="Any notes about sample collection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Collected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Lab Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="cancel_request.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="cancelRequestId" name="request_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Reason for Cancellation *</label>
                        <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required placeholder="Please provide a reason for cancellation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Cancel Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function collectSample(requestId) {
    document.getElementById('sampleRequestId').value = requestId;
    new bootstrap.Modal(document.getElementById('sampleModal')).show();
}

function startTest(requestId) {
    if (confirm('Are you ready to start processing this test?')) {
        window.location.href = 'start_test.php?id=' + requestId;
    }
}

function enterResult(requestId) {
    window.location.href = 'enter_result.php?id=' + requestId;
}

function cancelRequest(requestId) {
    document.getElementById('cancelRequestId').value = requestId;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function exportRequests() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printRequests() {
    window.print();
}

// Auto-refresh every 30 seconds for real-time updates
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);

// Sound notification for urgent requests
<?php if ($stats['urgent'] > 0): ?>
    // Play sound for urgent requests (optional)
    function playNotificationSound() {
        const audio = new Audio('../assets/sounds/alert.mp3');
        audio.play().catch(e => console.log('Audio play failed:', e));
    }
    
    // Play sound on page load for urgent requests
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(playNotificationSound, 1000);
    });
<?php endif; ?>

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'request.php';
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

.urgent-row {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { background-color: rgba(255, 0, 0, 0.1); }
    50% { background-color: rgba(255, 0, 0, 0.2); }
    100% { background-color: rgba(255, 0, 0, 0.1); }
}
</style>

<?php include '../includes/footer.php'; ?>
