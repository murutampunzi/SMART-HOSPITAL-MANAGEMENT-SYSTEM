<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician', 'doctor', 'patient']);

$page_title = "Lab Results - Smart Hospital Management System";
$page_heading = "Lab Results Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR lt.name LIKE ? OR lr.result_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 4);
}

if (!empty($status)) {
    $where_conditions[] = "lr.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(lr.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(lr.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Add role-based conditions
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

if ($user_role === 'patient') {
    $patient_sql = "SELECT id FROM patients WHERE user_id = ?";
    $patient_stmt = prepare($patient_sql);
    $patient_stmt->bind_param("i", $user_id);
    $patient_stmt->execute();
    $patient_record = $patient_stmt->get_result()->fetch_assoc();
    
    if ($patient_record) {
        $where_conditions[] = "lr.patient_id = ?";
        $params[] = $patient_record['id'];
        $types .= 'i';
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM lab_results lr 
              JOIN patients p ON lr.patient_id = p.id 
              JOIN lab_tests lt ON lr.test_id = lt.id 
              $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_results, ITEMS_PER_PAGE, $page);

// Get lab results
$sql = "SELECT lr.*, p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id,
              lt.name as test_name, lt.test_id as test_code, lt.category,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name
       FROM lab_results lr 
       JOIN patients p ON lr.patient_id = p.id 
       JOIN lab_tests lt ON lr.test_id = lt.id 
       JOIN lab_test_requests ltr ON lr.request_id = ltr.id
       LEFT JOIN doctors d ON ltr.doctor_id = d.id 
       $where_clause 
       ORDER BY lr.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_results'] = query("SELECT COUNT(*) as count FROM lab_results")->fetch_assoc()['count'];
$stats['pending'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE status IN ('pending', 'sample_collected', 'in_progress')")->fetch_assoc()['count'];
$stats['completed'] = query("SELECT COUNT(*) as count FROM lab_results")->fetch_assoc()['count'];
$stats['today'] = query("SELECT COUNT(*) as count FROM lab_results WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Results</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by patient, test, ID...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="normal" <?php echo $status === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="abnormal" <?php echo $status === 'abnormal' ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="critical" <?php echo $status === 'critical' ? 'selected' : ''; ?>>Critical</option>
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
                            <a href="results.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
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
                        <i class="fas fa-file-medical fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_results']; ?></h4>
                        <p class="mb-0">Total Results</p>
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
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['today']; ?></h4>
                        <p class="mb-0">Today</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Lab Results List</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_results); ?> of <?php echo $total_results; ?> results</small>
            </div>
            <div>
                <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-flask me-2"></i>Manage Requests
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="exportResults()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lab Results Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($results)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5>No Lab Results Found</h5>
                        <p class="text-muted">No lab results match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                            <a href="index.php" class="btn btn-primary">Manage Requests</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Result ID</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Date</th>
                                    <th>Result</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($result['result_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($result['patient_first_name'] . ' ' . $result['patient_last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($result['patient_id']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($result['test_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($result['test_code']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($result['created_at']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo($result['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($result['result_value']): ?>
                                                <small><?php echo truncateText($result['result_value'], 30); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Pending</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($result['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $result['request_id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                                                    <a href="view.php?id=<?php echo $result['request_id']; ?>&print=1" class="btn btn-sm btn-outline-secondary" title="Print" target="_blank">
                                                        <i class="fas fa-print"></i>
                                                    </a>
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
                            $url_pattern = 'results.php?' . http_build_query(array_merge($_GET, ['page' => '{page}']));
                            echo renderPagination($pagination, $url_pattern);
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function exportResults() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

// Auto-refresh every 60 seconds for real-time updates
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 60000);
</script>

<?php include '../includes/footer.php'; ?>
