<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'pharmacist']);

$page_title = "Medicines - Smart Hospital Management System";
$page_heading = "Medicine Inventory";

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
    $where_conditions[] = "(m.name LIKE ? OR m.generic_name LIKE ? OR m.manufacturer LIKE ? OR m.medicine_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 4);
}

if (!empty($category)) {
    $where_conditions[] = "m.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($status)) {
    if ($status === 'low_stock') {
        $where_conditions[] = "m.stock_quantity <= m.reorder_level";
    } elseif ($status === 'expired') {
        $where_conditions[] = "m.expiry_date < CURDATE()";
    } elseif ($status === 'expiring_soon') {
        $where_conditions[] = "m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    } else {
        $where_conditions[] = "m.status = ?";
        $params[] = $status;
        $types .= 's';
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM medicines m $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_medicines = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_medicines, ITEMS_PER_PAGE, $page);

// Get medicines
$sql = "SELECT m.* FROM medicines m $where_clause ORDER BY m.name ASC LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$medicines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_medicines'] = query("SELECT COUNT(*) as count FROM medicines")->fetch_assoc()['count'];
$stats['low_stock'] = query("SELECT COUNT(*) as count FROM medicines WHERE stock_quantity <= reorder_level")->fetch_assoc()['count'];
$stats['expired'] = query("SELECT COUNT(*) as count FROM medicines WHERE expiry_date < CURDATE()")->fetch_assoc()['count'];
$stats['expiring_soon'] = query("SELECT COUNT(*) as count FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Medicines</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, generic, manufacturer...">
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
                            <option value="low_stock" <?php echo $status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="expiring_soon" <?php echo $status === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon</option>
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
                            <a href="medicines.php" class="btn btn-outline-secondary">
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
                        <i class="fas fa-pills fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_medicines']; ?></h4>
                        <p class="mb-0">Total Medicines</p>
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
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['low_stock']; ?></h4>
                        <p class="mb-0">Low Stock</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['expired']; ?></h4>
                        <p class="mb-0">Expired</p>
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
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['expiring_soon']; ?></h4>
                        <p class="mb-0">Expiring Soon</p>
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
                <h5 class="mb-0">Medicines List</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_medicines); ?> of <?php echo $total_medicines; ?> medicines</small>
            </div>
            <div>
                <?php if (hasRole('admin') || hasRole('pharmacist')): ?>
                    <a href="medicine_create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Medicine
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="exportMedicines()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medicines Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($medicines)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                        <h5>No Medicines Found</h5>
                        <p class="text-muted">No medicines match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('pharmacist')): ?>
                            <a href="medicine_create.php" class="btn btn-primary">Add First Medicine</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Medicine ID</th>
                                    <th>Name</th>
                                    <th>Generic Name</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Price</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicines as $medicine): ?>
                                    <tr class="<?php echo $medicine['stock_quantity'] <= $medicine['reorder_level'] ? 'table-warning' : ''; ?>">
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($medicine['medicine_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($medicine['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($medicine['manufacturer'] ?: ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($medicine['generic_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['category'] ?: 'N/A'); ?></td>
                                        <td>
                                            <div class="text-center">
                                                <strong><?php echo $medicine['stock_quantity']; ?></strong>
                                                <br><small class="text-muted">Reorder: <?php echo $medicine['reorder_level']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-end">
                                                <strong><?php echo formatCurrency($medicine['selling_price']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $expiry_date = new DateTime($medicine['expiry_date']);
                                            $today = new DateTime();
                                            $diff = $today->diff($expiry_date);
                                            $is_expired = $expiry_date < $today;
                                            $is_expiring_soon = $diff->days <= 30 && !$is_expired;
                                            ?>
                                            <?php if ($is_expired): ?>
                                                <span class="text-danger"><?php echo formatDate($medicine['expiry_date']); ?></span>
                                            <?php elseif ($is_expiring_soon): ?>
                                                <span class="text-warning"><?php echo formatDate($medicine['expiry_date']); ?></span>
                                            <?php else: ?>
                                                <?php echo formatDate($medicine['expiry_date']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($medicine['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="medicine_view.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasRole('admin') || hasRole('pharmacist')): ?>
                                                    <a href="medicine_edit.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="medicine_stock.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-success" title="Update Stock">
                                                        <i class="fas fa-warehouse"></i>
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
                            $url_pattern = 'medicines.php?' . http_build_query(array_merge($_GET, ['page' => '{page}']));
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
function exportMedicines() {
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
