<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician', 'doctor']);

$page_title = "Lab Tests - Smart Hospital Management System";
$page_heading = "Laboratory Test Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(lt.name LIKE ? OR lt.test_id LIKE ? OR lt.category LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 3);
}

if (!empty($category)) {
    $where_conditions[] = "lt.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($status)) {
    $where_conditions[] = "lt.status = ?";
    $params[] = $status;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM lab_tests lt $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_tests = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_tests, ITEMS_PER_PAGE, $page);

// Get lab tests
$sql = "SELECT lt.* FROM lab_tests lt $where_clause ORDER BY lt.name ASC LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = query("SELECT DISTINCT category FROM lab_tests WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_tests'] = query("SELECT COUNT(*) as count FROM lab_tests")->fetch_assoc()['count'];
$stats['active_tests'] = query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'active'")->fetch_assoc()['count'];
$stats['pending_requests'] = query("SELECT COUNT(*) as count FROM lab_test_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['today_results'] = query("SELECT COUNT(*) as count FROM lab_results WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Tests</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, code, category...">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
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
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                            <a href="tests.php" class="btn btn-outline-secondary">
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
                        <i class="fas fa-flask fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_tests']; ?></h4>
                        <p class="mb-0">Total Tests</p>
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
                        <h4 class="mb-0"><?php echo $stats['active_tests']; ?></h4>
                        <p class="mb-0">Active Tests</p>
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
                        <h4 class="mb-0"><?php echo $stats['pending_requests']; ?></h4>
                        <p class="mb-0">Pending Requests</p>
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
                        <h4 class="mb-0"><?php echo $stats['today_results']; ?></h4>
                        <p class="mb-0">Today's Results</p>
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
                <h5 class="mb-0">Lab Tests List</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_tests); ?> of <?php echo $total_tests; ?> tests</small>
            </div>
            <div>
                <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                    <a href="test_create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Test
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="exportTests()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lab Tests Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($tests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-flask fa-3x text-muted mb-3"></i>
                        <h5>No Lab Tests Found</h5>
                        <p class="text-muted">No lab tests match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                            <a href="test_create.php" class="btn btn-primary">Add First Test</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Test Code</th>
                                    <th>Test Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tests as $test): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($test['test_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($test['name']); ?></div>
                                            <?php if ($test['description']): ?>
                                                <small class="text-muted"><?php echo truncateText($test['description'], 50); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($test['category'] ?: 'N/A'); ?></td>
                                        <td>
                                            <div class="text-end">
                                                <strong><?php echo formatCurrency($test['price']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <?php echo $test['duration_minutes']; ?> mins
                                            </div>
                                        </td>
                                        <td><?php echo getStatusBadge($test['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="test_view.php?id=<?php echo $test['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasRole('admin') || hasRole('lab_technician')): ?>
                                                    <a href="test_edit.php?id=<?php echo $test['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
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
                            $url_pattern = 'tests.php?' . http_build_query(array_merge($_GET, ['page' => '{page}']));
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
function exportTests() {
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
