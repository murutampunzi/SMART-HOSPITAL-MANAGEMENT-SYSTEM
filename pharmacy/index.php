<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'pharmacist']);

$page_title = "Pharmacy - Smart Hospital Management System";
$page_heading = "Pharmacy Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$stock_status = sanitizeInput($_GET['stock_status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(m.name LIKE ? OR m.medicine_id LIKE ? OR m.generic_name LIKE ? OR m.manufacturer LIKE ?)";
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
    $where_conditions[] = "m.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($stock_status)) {
    if ($stock_status === 'low') {
        $where_conditions[] = "m.stock_quantity <= m.reorder_level";
    } elseif ($stock_status === 'out') {
        $where_conditions[] = "m.stock_quantity = 0";
    } elseif ($stock_status === 'available') {
        $where_conditions[] = "m.stock_quantity > m.reorder_level";
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
$sql = "SELECT m.*, 
               (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.medicine_id = m.id) as prescription_count
        FROM medicines m 
        $where_clause 
        ORDER BY m.name 
        LIMIT ? OFFSET ?";
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
$stats['out_of_stock'] = query("SELECT COUNT(*) as count FROM medicines WHERE stock_quantity = 0")->fetch_assoc()['count'];
$stats['expiring_soon'] = query("SELECT COUNT(*) as count FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

// Get recent prescriptions
$recent_prescriptions = [];
$result = query("SELECT p.prescription_id, p.created_at, p.doctor_id, p.patient_id,
                      pt.first_name as patient_first_name, pt.last_name as patient_last_name,
                      d.first_name as doctor_first_name, d.last_name as doctor_last_name
               FROM prescriptions p
               JOIN patients pt ON p.patient_id = pt.id
               JOIN doctors d ON p.doctor_id = d.id
               ORDER BY p.created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_prescriptions[] = $row;
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
                        <h4 class="mb-0"><?php echo $stats['out_of_stock']; ?></h4>
                        <p class="mb-0">Out of Stock</p>
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

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search Medicines</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, ID, generic...">
                        </div>
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
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="discontinued" <?php echo $status === 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="stock_status" class="form-label">Stock Status</label>
                        <select class="form-select" id="stock_status" name="stock_status">
                            <option value="">All Stock</option>
                            <option value="available" <?php echo $stock_status === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="low" <?php echo $stock_status === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_status === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
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
                            <a href="create.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add
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
                <h5 class="mb-0">Medicine Inventory</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_medicines); ?> of <?php echo $total_medicines; ?> medicines</small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="exportMedicines()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-outline-info" onclick="printInventory()">
                    <i class="fas fa-print me-2"></i>Print
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
                        <a href="create.php" class="btn btn-primary">Add First Medicine</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Medicine ID</th>
                                    <th>Name</th>
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
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($medicine['medicine_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($medicine['name']); ?></div>
                                            <?php if ($medicine['generic_name']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($medicine['generic_name']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($medicine['manufacturer']): ?>
                                                <br><small class="text-muted">Mfg: <?php echo htmlspecialchars($medicine['manufacturer']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($medicine['category'] ?: 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold <?php 
                                                    echo $medicine['stock_quantity'] == 0 ? 'text-danger' : 
                                                         ($medicine['stock_quantity'] <= $medicine['reorder_level'] ? 'text-warning' : 'text-success'); 
                                                ?>">
                                                    <?php echo $medicine['stock_quantity']; ?>
                                                </span>
                                                <small class="text-muted ms-1"><?php echo htmlspecialchars($medicine['unit'] ?: 'pcs'); ?></small>
                                            </div>
                                            <?php if ($medicine['stock_quantity'] <= $medicine['reorder_level']): ?>
                                                <small class="text-warning">Reorder at <?php echo $medicine['reorder_level']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-end">
                                                <div><?php echo formatCurrency($medicine['selling_price']); ?></div>
                                                <small class="text-muted">Cost: <?php echo formatCurrency($medicine['unit_price']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($medicine['expiry_date']): ?>
                                                <div class="<?php 
                                                    $days_to_expiry = (new DateTime($medicine['expiry_date']))->diff(new DateTime())->days;
                                                    echo $days_to_expiry <= 30 ? 'text-danger fw-bold' : 'text-muted'; 
                                                ?>">
                                                    <?php echo formatDate($medicine['expiry_date']); ?>
                                                </div>
                                                <?php if ($days_to_expiry <= 30): ?>
                                                    <small class="text-danger">Expires in <?php echo $days_to_expiry; ?> days</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($medicine['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $medicine['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-success" title="Update Stock" 
                                                        onclick="updateStock(<?php echo $medicine['id']; ?>, '<?php echo htmlspecialchars($medicine['name']); ?>')">
                                                    <i class="fas fa-boxes"></i>
                                                </button>
                                                <?php if ($medicine['prescription_count'] > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Prescriptions" 
                                                            onclick="viewPrescriptions(<?php echo $medicine['id']; ?>)">
                                                        <i class="fas fa-file-prescription"></i>
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

<!-- Recent Prescriptions -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Prescriptions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_prescriptions)): ?>
                    <p class="text-muted text-center">No recent prescriptions</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_prescriptions as $prescription): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($prescription['prescription_id']); ?></div>
                                        <small class="text-muted">
                                            Patient: <?php echo htmlspecialchars($prescription['patient_first_name'] . ' ' . $prescription['patient_last_name']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            Doctor: <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?php echo timeAgo($prescription['created_at']); ?></small>
                                        <br>
                                        <a href="../prescriptions/view.php?id=<?php echo $prescription['prescription_id']; ?>" class="btn btn-sm btn-outline-primary mt-1">
                                            View
                                        </a>
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
                    <div class="col-6">
                        <a href="create.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Medicine
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="stock_update.php" class="btn btn-success w-100">
                            <i class="fas fa-boxes me-2"></i>Update Stock
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="expiry_report.php" class="btn btn-warning w-100">
                            <i class="fas fa-clock me-2"></i>Expiry Report
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="suppliers.php" class="btn btn-info w-100">
                            <i class="fas fa-truck me-2"></i>Suppliers
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="prescriptions.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-file-prescription me-2"></i>Prescriptions
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

<!-- Update Stock Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_stock.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="stockMedicineId" name="medicine_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Medicine</label>
                        <input type="text" class="form-control" id="stockMedicineName" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="stock_action" class="form-label">Action</label>
                        <select class="form-select" id="stock_action" name="stock_action" required onchange="toggleStockFields()">
                            <option value="add">Add Stock</option>
                            <option value="subtract">Subtract Stock</option>
                            <option value="set">Set Stock Level</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock_reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="stock_reason" name="stock_reason" rows="2" placeholder="e.g., New purchase, Return, Damage, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStock(medicineId, medicineName) {
    document.getElementById('stockMedicineId').value = medicineId;
    document.getElementById('stockMedicineName').value = medicineName;
    document.getElementById('stock_quantity').value = '';
    document.getElementById('stock_reason').value = '';
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}

function toggleStockFields() {
    const action = document.getElementById('stock_action').value;
    const quantityField = document.getElementById('stock_quantity');
    
    if (action === 'set') {
        quantityField.placeholder = 'Enter new stock level';
    } else {
        quantityField.placeholder = 'Enter quantity to add/subtract';
    }
}

function viewPrescriptions(medicineId) {
    window.location.href = 'prescriptions.php?medicine_id=' + medicineId;
}

function exportMedicines() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printInventory() {
    window.print();
}

// Auto-refresh every 60 seconds for stock updates
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 60000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'create.php';
    }
});

// Highlight low stock items
document.addEventListener('DOMContentLoaded', function() {
    const stockCells = document.querySelectorAll('td:nth-child(4)');
    stockCells.forEach(cell => {
        if (cell.textContent.includes('0')) {
            cell.parentElement.classList.add('table-danger');
        } else if (cell.querySelector('.text-warning')) {
            cell.parentElement.classList.add('table-warning');
        }
    });
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
}
</style>

<?php include '../includes/footer.php'; ?>
