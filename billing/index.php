<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'receptionist']);

$page_title = "Billing - Smart Hospital Management System";
$page_heading = "Billing Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$payment_status = sanitizeInput($_GET['payment_status'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR i.patient_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 4);
}

if (!empty($status)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($payment_status)) {
    $where_conditions[] = "i.payment_status = ?";
    $params[] = $payment_status;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "i.invoice_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "i.invoice_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM invoices i 
             JOIN patients p ON i.patient_id = p.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_invoices = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_invoices, ITEMS_PER_PAGE, $page);

// Get invoices
$sql = "SELECT i.*, p.first_name, p.last_name, p.phone as patient_phone,
              COUNT(ii.id) as item_count,
              COALESCE(SUM(pm.amount), 0) as paid_amount
       FROM invoices i 
       JOIN patients p ON i.patient_id = p.id 
       LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
       LEFT JOIN payments pm ON i.id = pm.invoice_id AND pm.status = 'completed'
       $where_clause 
       GROUP BY i.id
       ORDER BY i.invoice_date DESC, i.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_invoices'] = query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];
$stats['total_revenue'] = query("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices")->fetch_assoc()['total'];
$stats['pending_payment'] = query("SELECT COALESCE(SUM(balance_amount), 0) as total FROM invoices WHERE status != 'paid'")->fetch_assoc()['total'];
$stats['overdue'] = query("SELECT COUNT(*) as count FROM invoices WHERE status = 'overdue'")->fetch_assoc()['count'];

// Get today's revenue
$today_revenue = query("SELECT COALESCE(SUM(pm.amount), 0) as total FROM payments pm WHERE DATE(pm.payment_date) = CURDATE() AND pm.status = 'completed'")->fetch_assoc()['total'];

// Get recent payments
$recent_payments = [];
$result = query("SELECT pm.payment_id, pm.amount, pm.payment_date, pm.payment_method, pm.status,
                      i.invoice_id, p.first_name, p.last_name
               FROM payments pm
               JOIN invoices i ON pm.invoice_id = i.id
               JOIN patients p ON i.patient_id = p.id
               ORDER BY pm.payment_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_payments[] = $row;
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
                        <i class="fas fa-file-invoice fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_invoices']; ?></h4>
                        <p class="mb-0">Total Invoices</p>
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
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo formatCurrency($stats['total_revenue']); ?></h4>
                        <p class="mb-0">Total Revenue</p>
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
                        <h4 class="mb-0"><?php echo formatCurrency($stats['pending_payment']); ?></h4>
                        <p class="mb-0">Pending Payment</p>
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
                        <h4 class="mb-0"><?php echo formatCurrency($today_revenue); ?></h4>
                        <p class="mb-0">Today's Revenue</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overdue Invoices Alert -->
<?php if ($stats['overdue'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $stats['overdue']; ?> overdue invoice(s)</strong> require attention!
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
                        <label for="search" class="form-label">Search Invoices</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by ID, patient name...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status">
                            <option value="">All Payment</option>
                            <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="refunded" <?php echo $payment_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
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
                                <i class="fas fa-plus"></i> New
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
                <h5 class="mb-0">Invoices</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_invoices); ?> of <?php echo $total_invoices; ?> invoices</small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="exportInvoices()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-outline-info" onclick="printInvoices()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($invoices)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                        <h5>No Invoices Found</h5>
                        <p class="text-muted">No invoices match your search criteria.</p>
                        <a href="create.php" class="btn btn-primary">Create First Invoice</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr class="<?php 
                                        echo $invoice['status'] === 'overdue' ? 'table-danger' : 
                                             ($invoice['status'] === 'sent' && $invoice['balance_amount'] > 0 ? 'table-warning' : ''); 
                                    ?>">
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($invoice['invoice_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($invoice['patient_phone']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($invoice['invoice_date']); ?></div>
                                            <?php if ($invoice['due_date']): ?>
                                                <small class="text-muted">Due: <?php echo formatDate($invoice['due_date']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $invoice['item_count']; ?></span>
                                        </td>
                                        <td>
                                            <div class="text-end fw-bold"><?php echo formatCurrency($invoice['total_amount']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-end text-success"><?php echo formatCurrency($invoice['paid_amount']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-end <?php echo $invoice['balance_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatCurrency($invoice['balance_amount']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo getStatusBadge($invoice['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-success" title="Payment" 
                                                        onclick="recordPayment(<?php echo $invoice['id']; ?>)">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" title="Print" 
                                                        onclick="printInvoice(<?php echo $invoice['id']; ?>)">
                                                    <i class="fas fa-print"></i>
                                                </button>
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

<!-- Recent Payments -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Payments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_payments)): ?>
                    <p class="text-muted text-center">No recent payments</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Invoice</th>
                                    <th>Patient</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($payment['payment_id']); ?></span></td>
                                        <td><?php echo htmlspecialchars($payment['invoice_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td><?php echo getStatusBadge($payment['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="create.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>New Invoice
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="payments.php" class="btn btn-success w-100">
                            <i class="fas fa-dollar-sign me-2"></i>Payments
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="overdue.php" class="btn btn-warning w-100">
                            <i class="fas fa-exclamation-triangle me-2"></i>Overdue
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="reports.php" class="btn btn-info w-100">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </div>
                    <div class="col-12">
                        <a href="export.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-download me-2"></i>Export Data
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Revenue Summary</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total Revenue</span>
                        <strong><?php echo formatCurrency($stats['total_revenue']); ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Today's Revenue</span>
                        <strong class="text-success"><?php echo formatCurrency($today_revenue); ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Pending Payment</span>
                        <strong class="text-warning"><?php echo formatCurrency($stats['pending_payment']); ?></strong>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Overdue Invoices</span>
                        <strong class="text-danger"><?php echo $stats['overdue']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="record_payment.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="paymentInvoiceId" name="invoice_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="payment_amount" class="form-label">Payment Amount *</label>
                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                               step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="online">Online</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label">Transaction ID</label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                               placeholder="Optional transaction reference">
                    </div>
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2" 
                                  placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recordPayment(invoiceId) {
    document.getElementById('paymentInvoiceId').value = invoiceId;
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function printInvoice(invoiceId) {
    window.open('print.php?id=' + invoiceId, '_blank');
}

function exportInvoices() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printInvoices() {
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
        window.location.href = 'create.php';
    }
});

// Format currency inputs
document.getElementById('payment_amount')?.addEventListener('input', function(e) {
    let value = parseFloat(e.target.value);
    if (!isNaN(value)) {
        e.target.value = value.toFixed(2);
    }
});

// Set today's date as default for date filters
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    if (!document.getElementById('date_from').value) {
        document.getElementById('date_from').value = date('Y-m', strtotime('-1 month'));
    }
    if (!document.getElementById('date_to').value) {
        document.getElementById('date_to').value = today;
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

.overdue-row {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { background-color: rgba(255, 0, 0, 0.1); }
    50% { background-color: rgba(255, 0, 0, 0.2); }
    100% { background-color: rgba(255, 0, 0, 0.1); }
}
</style>

<?php include '../includes/footer.php'; ?>
