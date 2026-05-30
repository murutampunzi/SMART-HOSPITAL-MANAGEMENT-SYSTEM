<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'receptionist']);

$page_title = "Insurance Management - Smart Hospital Management System";
$page_heading = "Insurance Management";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$provider_id = intval($_GET['provider_id'] ?? 0);
$status = sanitizeInput($_GET['status'] ?? '');
$claim_status = sanitizeInput($_GET['claim_status'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(ip.claim_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR ip.policy_number LIKE ? OR ip.claim_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 5);
}

if ($provider_id > 0) {
    $where_conditions[] = "ip.provider_id = ?";
    $params[] = $provider_id;
    $types .= 'i';
}

if (!empty($status)) {
    $where_conditions[] = "ip.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($claim_status)) {
    $where_conditions[] = "ip.claim_status = ?";
    $params[] = $claim_status;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "ip.claim_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "ip.claim_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM insurance_policies ip 
             JOIN patients p ON ip.patient_id = p.id 
             LEFT JOIN insurance_providers prov ON ip.provider_id = prov.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_policies = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_policies, ITEMS_PER_PAGE, $page);

// Get insurance policies
$sql = "SELECT ip.*, p.first_name, p.last_name, p.patient_id, p.phone as patient_phone,
              prov.name as provider_name, prov.contact_person, prov.phone as provider_phone,
              i.invoice_id, i.total_amount, i.paid_amount, i.balance_amount,
              ip.policy_number, ip.claim_number, ip.coverage_amount, ip.claim_amount,
              ip.approved_amount, ip.deductible, ip.co_insurance, ip.claim_date, ip.approval_date
       FROM insurance_policies ip 
       JOIN patients p ON ip.patient_id = p.id 
       LEFT JOIN insurance_providers prov ON ip.provider_id = prov.id
       LEFT JOIN invoices i ON ip.invoice_id = i.id
       $where_clause 
       ORDER BY ip.claim_date DESC, ip.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$policies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get insurance providers for filter
$providers = query("SELECT id, name FROM insurance_providers ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_policies'] = query("SELECT COUNT(*) as count FROM insurance_policies")->fetch_assoc()['count'];
$stats['active_policies'] = query("SELECT COUNT(*) as count FROM insurance_policies WHERE status = 'active'")->fetch_assoc()['count'];
$stats['pending_claims'] = query("SELECT COUNT(*) as count FROM insurance_policies WHERE claim_status = 'pending'")->fetch_assoc()['count'];
$stats['approved_claims'] = query("SELECT COUNT(*) as count FROM insurance_policies WHERE claim_status = 'approved'")->fetch_assoc()['count'];
$stats['total_claims_amount'] = query("SELECT COALESCE(SUM(claim_amount), 0) as total FROM insurance_policies WHERE claim_status IN ('pending', 'approved')")->fetch_assoc()['total'];
$stats['total_approved_amount'] = query("SELECT COALESCE(SUM(approved_amount), 0) as total FROM insurance_policies WHERE claim_status = 'approved'")->fetch_assoc()['total'];

// Recent claims
$recent_claims = [];
$result = query("SELECT ip.claim_id, ip.claim_number, ip.claim_date, ip.claim_status, ip.claim_amount,
                      p.first_name, p.last_name, prov.name as provider_name
               FROM insurance_policies ip
               JOIN patients p ON ip.patient_id = p.id
               LEFT JOIN insurance_providers prov ON ip.provider_id = prov.id
               ORDER BY ip.claim_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_claims[] = $row;
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
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_policies']; ?></h4>
                        <p class="mb-0">Total Policies</p>
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
                        <h4 class="mb-0"><?php echo $stats['approved_claims']; ?></h4>
                        <p class="mb-0">Approved Claims</p>
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
                        <h4 class="mb-0"><?php echo $stats['pending_claims']; ?></h4>
                        <p class="mb-0">Pending Claims</p>
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
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo formatCurrency($stats['total_approved_amount']); ?></h4>
                        <p class="mb-0">Approved Amount</p>
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
                        <label for="search" class="form-label">Search Policies</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by patient, policy, claim...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="provider_id" class="form-label">Insurance Provider</label>
                        <select class="form-select" id="provider_id" name="provider_id">
                            <option value="">All Providers</option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?php echo $provider['id']; ?>" 
                                        <?php echo $provider_id === $provider['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($provider['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Policy Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="claim_status" class="form-label">Claim Status</label>
                        <select class="form-select" id="claim_status" name="claim_status">
                            <option value="">All Claims</option>
                            <option value="pending" <?php echo $claim_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $claim_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $claim_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="processed" <?php echo $claim_status === 'processed' ? 'selected' : ''; ?>>Processed</option>
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
                                <i class="fas fa-plus"></i> New Policy
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
                <h5 class="mb-0">Insurance Policies</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_policies); ?> of <?php echo $total_policies; ?> policies</small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="exportPolicies()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-outline-info" onclick="printPolicies()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Insurance Policies Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($policies)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                        <h5>No Insurance Policies Found</h5>
                        <p class="text-muted">No insurance policies match your search criteria.</p>
                        <a href="create.php" class="btn btn-primary">Create Insurance Policy</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Policy ID</th>
                                    <th>Patient</th>
                                    <th>Provider</th>
                                    <th>Policy Number</th>
                                    <th>Claim Number</th>
                                    <th>Claim Amount</th>
                                    <th>Approved Amount</th>
                                    <th>Claim Status</th>
                                    <th>Claim Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($policies as $policy): ?>
                                    <tr class="<?php 
                                        if ($policy['claim_status'] === 'pending') {
                                            echo 'table-warning';
                                        } elseif ($policy['claim_status'] === 'approved') {
                                            echo 'table-success';
                                        } elseif ($policy['claim_status'] === 'rejected') {
                                            echo 'table-danger';
                                        } else {
                                            echo 'table-light';
                                        }
                                    ?>">
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($policy['claim_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($policy['first_name'] . ' ' . $policy['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($policy['patient_id']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($policy['provider_name'] ?: 'N/A'); ?></div>
                                            <?php if ($policy['contact_person']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($policy['contact_person']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($policy['policy_number']); ?></div>
                                            <small class="text-muted"><?php echo getStatusBadge($policy['status']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($policy['claim_number']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-end fw-bold"><?php echo formatCurrency($policy['claim_amount']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-end fw-bold text-success"><?php echo formatCurrency($policy['approved_amount']); ?></div>
                                            <?php if ($policy['deductible'] > 0): ?>
                                                <small class="text-muted">Deductible: <?php echo formatCurrency($policy['deductible']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($policy['claim_status']); ?></td>
                                        <td>
                                            <div><?php echo formatDate($policy['claim_date']); ?></div>
                                            <?php if ($policy['approval_date']): ?>
                                                <small class="text-muted">Approved: <?php echo formatDate($policy['approval_date']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $policy['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="edit.php?id=<?php echo $policy['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Policy">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <?php if ($policy['claim_status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Process Claim" 
                                                            onclick="processClaim(<?php echo $policy['id']; ?>)">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($policy['claim_status'] === 'approved' && $policy['approved_amount'] > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Process Payment" 
                                                            onclick="processPayment(<?php echo $policy['id']; ?>)">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Print Policy" 
                                                        onclick="printPolicy(<?php echo $policy['id']; ?>)">
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

<!-- Recent Claims -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Claims</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_claims)): ?>
                    <p class="text-muted text-center">No recent claims</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_claims as $claim): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($claim['claim_number']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?> • 
                                            <?php echo htmlspecialchars($claim['provider_name']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-<?php 
                                            echo $claim['claim_status'] === 'approved' ? 'success' : 
                                                 ($claim['claim_status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($claim['claim_status']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo formatCurrency($claim['claim_amount']); ?></small>
                                        <br><small class="text-muted"><?php echo timeAgo($claim['claim_date']); ?></small>
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
                            <i class="fas fa-plus me-2"></i>New Policy
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="providers.php" class="btn btn-success w-100">
                            <i class="fas fa-building me-2"></i>Providers
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="claims.php" class="btn btn-info w-100">
                            <i class="fas fa-file-invoice me-2"></i>Claims
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="eligibility.php" class="btn btn-warning w-100">
                            <i class="fas fa-check-circle me-2"></i>Eligibility
                        </a>
                    </div>
                    <div class="col-12">
                        <a href="reports.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Claim Modal -->
<div class="modal fade" id="processClaimModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Insurance Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process_claim.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="processClaimId" name="policy_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="claim_status" class="form-label">Claim Status *</label>
                        <select class="form-select" id="claim_status" name="claim_status" required onchange="toggleApprovalFields()">
                            <option value="">Select Status</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    
                    <div id="approvalFields" style="display: none;">
                        <div class="mb-3">
                            <label for="approved_amount" class="form-label">Approved Amount *</label>
                            <input type="number" class="form-control" id="approved_amount" name="approved_amount" 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        
                        <div class="mb-3">
                            <label for="deductible" class="form-label">Deductible</label>
                            <input type="number" class="form-control" id="deductible" name="deductible" 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        
                        <div class="mb-3">
                            <label for="co_insurance" class="form-label">Co-insurance (%)</label>
                            <input type="number" class="form-control" id="co_insurance" name="co_insurance" 
                                   step="0.1" min="0" max="100" placeholder="0.0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="approval_notes" class="form-label">Approval Notes</label>
                        <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3" 
                                  placeholder="Reason for approval/rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Claim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function processClaim(policyId) {
    document.getElementById('processClaimId').value = policyId;
    new bootstrap.Modal(document.getElementById('processClaimModal')).show();
}

function processPayment(policyId) {
    if (confirm('Process payment for this approved claim?')) {
        window.location.href = 'process_payment.php?id=' + policyId;
    }
}

function printPolicy(policyId) {
    window.open('print_policy.php?id=' + policyId, '_blank');
}

function exportPolicies() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printPolicies() {
    window.print();
}

function toggleApprovalFields() {
    const status = document.getElementById('claim_status').value;
    const approvalFields = document.getElementById('approvalFields');
    
    if (status === 'approved') {
        approvalFields.style.display = 'block';
        document.getElementById('approved_amount').required = true;
    } else {
        approvalFields.style.display = 'none';
        document.getElementById('approved_amount').required = false;
    }
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

.pending-row {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { background-color: rgba(255, 193, 7, 0.1); }
    50% { background-color: rgba(255, 193, 7, 0.2); }
    100% { background-color: rgba(255, 193, 7, 0.1); }
}
</style>

<?php include '../includes/footer.php'; ?>
