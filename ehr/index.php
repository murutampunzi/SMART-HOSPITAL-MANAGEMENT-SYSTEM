<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'nurse', 'receptionist']);

$page_title = "Electronic Health Records - Smart Hospital Management System";
$page_heading = "Electronic Health Records";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$record_type = sanitizeInput($_GET['record_type'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(ehr.record_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR ehr.diagnosis LIKE ? OR ehr.treatment LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 5);
}

if (!empty($record_type)) {
    $where_conditions[] = "ehr.record_type = ?";
    $params[] = $record_type;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "ehr.record_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "ehr.record_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM electronic_health_records ehr 
             JOIN patients p ON ehr.patient_id = p.id 
             LEFT JOIN users u ON ehr.created_by = u.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_records, ITEMS_PER_PAGE, $page);

// Get health records
$sql = "SELECT ehr.*, p.first_name, p.last_name, p.patient_id, p.date_of_birth, p.gender,
              u.name as created_by_name, u.role as created_by_role,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name,
              ehr.vital_signs, ehr.symptoms, ehr.diagnosis, ehr.treatment, ehr.notes,
              ehr.follow_up_date, ehr.record_date, ehr.is_chronic
       FROM electronic_health_records ehr 
       JOIN patients p ON ehr.patient_id = p.id 
       LEFT JOIN users u ON ehr.created_by = u.id
       LEFT JOIN doctors d ON ehr.doctor_id = d.id
       $where_clause 
       ORDER BY ehr.record_date DESC, ehr.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get record types for filter
$record_types = query("SELECT DISTINCT record_type FROM electronic_health_records ORDER BY record_type")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_records'] = query("SELECT COUNT(*) as count FROM electronic_health_records")->fetch_assoc()['count'];
$stats['chronic_conditions'] = query("SELECT COUNT(*) as count FROM electronic_health_records WHERE is_chronic = 1")->fetch_assoc()['count'];
$stats['recent_visits'] = query("SELECT COUNT(*) as count FROM electronic_health_records WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
$stats['follow_ups'] = query("SELECT COUNT(*) as count FROM electronic_health_records WHERE follow_up_date = CURDATE()")->fetch_assoc()['count'];

// Recent health records
$recent_records = [];
$result = query("SELECT ehr.record_id, ehr.record_date, ehr.record_type, ehr.diagnosis,
                      p.first_name, p.last_name, p.patient_id, d.first_name as doctor_first_name, d.last_name as doctor_last_name
               FROM electronic_health_records ehr
               JOIN patients p ON ehr.patient_id = p.id
               LEFT JOIN doctors d ON ehr.doctor_id = d.id
               ORDER BY ehr.record_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_records[] = $row;
}

// Chronic disease statistics
$chronic_diseases = [];
$result = query("SELECT diagnosis, COUNT(*) as count 
               FROM electronic_health_records 
               WHERE is_chronic = 1 AND diagnosis IS NOT NULL 
               GROUP BY diagnosis 
               ORDER BY count DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $chronic_diseases[] = $row;
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
                        <i class="fas fa-file-medical fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_records']; ?></h4>
                        <p class="mb-0">Total Records</p>
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
                        <i class="fas fa-heartbeat fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['chronic_conditions']; ?></h4>
                        <p class="mb-0">Chronic Conditions</p>
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
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['recent_visits']; ?></h4>
                        <p class="mb-0">Recent Visits</p>
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
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['follow_ups']; ?></h4>
                        <p class="mb-0">Today's Follow-ups</p>
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
                        <label for="search" class="form-label">Search Records</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by patient, diagnosis, treatment...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="record_type" class="form-label">Record Type</label>
                        <select class="form-select" id="record_type" name="record_type">
                            <option value="">All Types</option>
                            <?php foreach ($record_types as $type): ?>
                                <option value="<?php echo $type['record_type']; ?>" 
                                        <?php echo $record_type === $type['record_type'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type['record_type']); ?>
                                </option>
                            <?php endforeach; ?>
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
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="btn-group w-100" role="group">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                            <a href="create.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> New Record
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
                <h5 class="mb-0">Health Records</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_records); ?> of <?php echo $total_records; ?> records</small>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="exportRecords()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-outline-info" onclick="printRecords()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Health Records Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($records)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5>No Health Records Found</h5>
                        <p class="text-muted">No health records match your search criteria.</p>
                        <a href="create.php" class="btn btn-primary">Create Health Record</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Record ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Diagnosis</th>
                                    <th>Treatment</th>
                                    <th>Record Date</th>
                                    <th>Follow-up</th>
                                    <th>Chronic</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr class="<?php echo $record['is_chronic'] ? 'table-warning' : 'table-light'; ?>">
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($record['record_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['patient_id']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($record['doctor_first_name']): ?>
                                                <div><?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-truncate"><?php echo htmlspecialchars($record['diagnosis']); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-truncate"><?php echo htmlspecialchars($record['treatment']); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($record['record_date']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo($record['record_date']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($record['follow_up_date']): ?>
                                                <div><?php echo formatDate($record['follow_up_date']); ?></div>
                                                <?php if ($record['follow_up_date'] === date('Y-m-d')): ?>
                                                    <span class="badge bg-warning">Today</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['is_chronic']): ?>
                                                <span class="badge bg-warning">Chronic</span>
                                            <?php else: ?>
                                                <span class="text-muted">Acute</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="edit.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Record">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-success" title="Add Follow-up" 
                                                        onclick="addFollowUp(<?php echo $record['id']; ?>)">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-info" title="Print Record" 
                                                        onclick="printRecord(<?php echo $record['id']; ?>)">
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

<!-- Recent Health Records -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Health Records</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_records)): ?>
                    <p class="text-muted text-center">No recent health records</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_records as $record): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($record['patient_id']); ?> • 
                                            <?php echo ucfirst($record['record_type']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo truncateText($record['diagnosis'], 50); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-info"><?php echo formatDate($record['record_date']); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($record['record_date']); ?></small>
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
                <h5 class="card-title mb-0">Top Chronic Conditions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($chronic_diseases)): ?>
                    <p class="text-muted text-center">No chronic conditions recorded</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($chronic_diseases as $disease): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($disease['diagnosis']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning"><?php echo $disease['count']; ?> cases</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <a href="create.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>New Record
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="timeline.php" class="btn btn-info w-100">
                            <i class="fas fa-timeline me-2"></i>Patient Timeline
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="chronic.php" class="btn btn-warning w-100">
                            <i class="fas fa-heartbeat me-2"></i>Chronic Care
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="vitals.php" class="btn btn-success w-100">
                            <i class="fas fa-heartbeat me-2"></i>Vital Signs
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="prescriptions.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-prescription me-2"></i>Prescriptions
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="reports.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Follow-up Modal -->
<div class="modal fade" id="followUpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_follow_up.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="followUpRecordId" name="record_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="follow_up_date" class="form-label">Follow-up Date *</label>
                        <input type="date" class="form-control" id="follow_up_date" name="follow_up_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="follow_up_notes" class="form-label">Follow-up Notes</label>
                        <textarea class="form-control" id="follow_up_notes" name="follow_up_notes" rows="3" 
                                  placeholder="Notes for follow-up visit..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="follow_up_type" class="form-label">Follow-up Type</label>
                        <select class="form-select" id="follow_up_type" name="follow_up_type">
                            <option value="routine">Routine Check</option>
                            <option value="urgent">Urgent</option>
                            <option value="specialist">Specialist Referral</option>
                            <option value="lab">Lab Results Review</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addFollowUp(recordId) {
    document.getElementById('followUpRecordId').value = recordId;
    new bootstrap.Modal(document.getElementById('followUpModal')).show();
}

function printRecord(recordId) {
    window.open('print_record.php?id=' + recordId, '_blank');
}

function exportRecords() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printRecords() {
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

.chronic-row {
    background-color: rgba(255, 193, 7, 0.1);
}
</style>

<?php include '../includes/footer.php'; ?>
