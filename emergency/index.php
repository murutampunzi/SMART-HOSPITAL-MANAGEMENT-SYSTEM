<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'nurse', 'receptionist']);

$page_title = "Emergency Department - Smart Hospital Management System";
$page_heading = "Emergency Department";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$triage_level = sanitizeInput($_GET['triage_level'] ?? '');
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
    $where_conditions[] = "(er.patient_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR er.patient_id LIKE ? OR er.chief_complaint LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 5);
}

if (!empty($triage_level)) {
    $where_conditions[] = "er.triage_level = ?";
    $params[] = $triage_level;
    $types .= 's';
}

if (!empty($status)) {
    $where_conditions[] = "er.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "er.arrival_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "er.arrival_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM emergency_records er 
             JOIN patients p ON er.patient_id = p.id 
             LEFT JOIN users u ON er.created_by = u.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_records, ITEMS_PER_PAGE, $page);

// Get emergency records
$sql = "SELECT er.*, p.first_name, p.last_name, p.patient_id, p.phone as patient_phone,
              p.date_of_birth, p.gender, p.blood_group,
              u.name as created_by_name, u.role as created_by_role,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name,
              er.chief_complaint, er.diagnosis, er.treatment, er.notes,
              er.arrival_date, er.triage_date, er.discharge_date
       FROM emergency_records er 
       JOIN patients p ON er.patient_id = p.id 
       LEFT JOIN users u ON er.created_by = u.id
       LEFT JOIN doctors d ON er.assigned_doctor_id = d.id
       $where_clause 
       ORDER BY er.triage_level DESC, er.arrival_date DESC, er.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get triage levels for filter
$triage_levels = query("SELECT DISTINCT triage_level FROM emergency_records ORDER BY triage_level")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_patients'] = query("SELECT COUNT(*) as count FROM emergency_records")->fetch_assoc()['count'];
$stats['critical'] = query("SELECT COUNT(*) as count FROM emergency_records WHERE triage_level = 'critical'")->fetch_assoc()['count'];
$stats['urgent'] = query("SELECT COUNT(*) as count FROM emergency_records WHERE triage_level = 'urgent'")->fetch_assoc()['count'];
$stats['stable'] = query("SELECT COUNT(*) as count FROM emergency_records WHERE triage_level = 'stable'")->fetch_assoc()['count'];
$stats['discharged'] = query("SELECT COUNT(*) as count FROM emergency_records WHERE status = 'discharged'")->fetch_assoc()['count'];

// Recent emergency cases
$recent_cases = [];
$result = query("SELECT er.patient_id, er.triage_level, er.arrival_date, er.status,
                      p.first_name, p.last_name, p.patient_id
               FROM emergency_records er
               JOIN patients p ON er.patient_id = p.id
               ORDER BY er.arrival_date DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_cases[] = $row;
}

include '../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-ambulance fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['total_patients']; ?></h4>
                        <p class="mb-0">Total ER Patients</p>
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
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['critical']; ?></h4>
                        <p class="mb-0">Critical Cases</p>
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
                        <i class="fa fa-clock fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['urgent']; ?></h4>
                        <p class="mb-0">Urgent Cases</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user-clock fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h4 class="mb-0"><?php echo $stats['stable']; ?></h4>
                        <p class="mb-0">Stable Cases</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Critical Cases Alert -->
<?php if ($stats['critical'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $stats['critical']; ?> critical case(s) require immediate attention!</strong>
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
                        <label for="search" class="form-label">Search Records</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by patient, ID, complaint...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="triage_level" class="form-label">Triage Level</label>
                        <select class="form-select" id="triage_level" name="triage_level">
                            <option value="">All Levels</option>
                            <?php foreach ($triage_levels as $level): ?>
                                <option value="<?php echo $level['triage_level']; ?>" 
                                        <?php echo ucfirst($level['triage_level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="waiting" <?php echo $status === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                            <option value="in_treatment" <?php echo $status === 'in_treatment' ? 'selected' : ''; ?>>In Treatment</option>
                            <option value="discharged" <?php echo $status === 'discharged' ? 'selected' : ''; ?>>Discharged</option>
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
                        <div class="btn-group w-100" role="group">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                            <a href="triage.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> New Case
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
                <h5 class="mb-0">Emergency Records</h5>
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

<!-- Emergency Records Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($records)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ambulance fa-3x text-muted mb-3"></i>
                        <h5>No Emergency Records Found</h5>
                        <p class="text-muted">No emergency records match your search criteria.</p>
                        <a href="triage.php" class="btn btn-primary">Create Emergency Case</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ER ID</th>
                                    <th>Patient</th>
                                    <th>Triage Level</th>
                                    <th>Chief Complaint</th>
                                    <th>Doctor</th>
                                    <th>Arrival</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr class="<?php 
                                        echo $record['triage_level'] === 'critical' ? 'table-danger' : 
                                             ($record['triage_level'] === 'urgent' ? 'table-warning' : 
                                             ($record['triage_level'] === 'stable' ? 'table-info' : 'table-light'); 
                                    ?>">
                                        <td>
                                            <span class="badge bg-danger"><?php echo htmlspecialchars($record['patient_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['patient_number']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $record['triage_level'] === 'critical' ? 'danger' : 
                                                     ($record['triage_level'] === 'urgent' ? 'warning' : 
                                                     ($record['triage_level'] === 'stable' ? 'info' : 'secondary'); 
                                            ?>"><?php echo ucfirst($record['triage_level']); ?></span>
                                        </td>
                                        <td>
                                            <div class="text-truncate"><?php echo htmlspecialchars($record['chief_complaint']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($record['doctor_first_name']): ?>
                                                <div><?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($record['arrival_date']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo($record['arrival_date']); ?></small>
                                        </td>
                                        <td><?php echo getStatusBadge($record['status']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if (hasRole('admin') || hasRole('doctor')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Update Treatment" 
                                                            onclick="updateTreatment(<?php echo $record['id']; ?>)">
                                                        <i class="fas fa-medkit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (hasRole('admin')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Discharge" 
                                                            onclick="dischargePatient(<?php echo $record['id']; ?>)">
                                                        <i class="fas fa-sign-out-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (hasRole('nurse')): ?>
                                                    <button type="button" class="btn btn-outline-info" title="Patient Care" 
                                                            onclick="patientCare(<?php echo $record['id']; ?>)">
                                                        <i class="fas fa-heartbeat"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Print Record" 
                                                        onclick="printRecord(<?php echo $record['id']; ?>)">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </div>
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

<!-- Recent Emergency Cases -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Emergency Cases</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_cases)): ?>
                    <p class="text-muted text-center">No recent emergency cases</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_cases as $case): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($case['patient_id']); ?> • 
                                            <?php echo ucfirst($case['triage_level']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-<?php 
                                            echo $case['triage_level'] === 'critical' ? 'danger' : 
                                                 ($case['triage_level'] === 'urgent' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($case['triage_level']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo timeAgo($case['arrival_date']); ?></small>
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
                        <a href="triage.php" class="btn btn-danger w-100">
                            <i class="fas fa-plus me-2"></i>New Emergency Case
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="triage.php?priority=critical" class="btn btn-warning w-100">
                            <i class="fas fa-exclamation-triangle me-2"></i>Critical Cases Only
                        </a>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <a href="ambulance.php" class="btn btn-info w-100">
                            <i class="fas fa-ambulance me-2"></i>Ambulance Intake
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="reports.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-12">
                        <a href="statistics.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-line me-2"></i>Statistics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Triage Modal -->
<div class="modal fade" id="triageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Emergency Triage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="save_triage.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="triageRecordId" name="record_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="patient_search" class="form-label">Select Patient *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="patient_search" name="patient_search" 
                                   placeholder="Search patient by name or ID" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" onclick="searchPatients()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="patient_search_results"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="triage_level" class="form-label">Triage Level *</label>
                        <select class="form-select" id="triage_level" name="triage_level" required>
                            <option value="">Select Triage Level</option>
                            <option value="critical">Critical</option>
                            <option value="urgent">Urgent</option>
                            <option value="stable">Stable</option>
                            <option value="non-urgent">Non-Urgent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="chief_complaint" class="form-label">Chief Complaint *</label>
                        <textarea class="form-control" id="chief_complaint" name="chief_complaint" rows="3" required placeholder="Describe main complaint..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Initial Diagnosis *</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" placeholder="Initial assessment..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="treatment" class="form-label">Initial Treatment *</label>
                        <textarea class="form-control" id="treatment" name="treatment" rows="3" placeholder="Initial treatment..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="vital_signs" class="form-label">Vital Signs</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Blood Pressure</label>
                                    <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" placeholder="120/80">
                                    <div class="form-text">mmHg</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Heart Rate</label>
                                    <input type="text" class="form-control" id="heart_rate" name="heart_rate" placeholder="60-100">
                                    <div class="form-text">bpm</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="respiratory_rate" class="form-label">Respiratory Rate</label>
                            <input type="text" class="form-control" id="respiratory_rate" placeholder="12-20">
                                <div class="form-text">rpm</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Save Triage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function searchPatients() {
    const search = document.getElementById('patient_search').value;
    if (search.length >= 2) {
        fetch('api/search_patients.php?q=' . encodeURIComponent(search))
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('patient_search_results');
                if (data.success && data.patients && data.patients.length > 0) {
                    resultsDiv.innerHTML = data.patients.map(patient => 
                        `<div class="list-group-item px-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">${patient.first_name} ${patient.last_name}</div>
                                    <small class="text-muted">${patient.patient_id}</small>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="selectPatient(${patient.id})">
                                        Select
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.innerHTML = '<div class="text-muted">No patients found</div>';
                }
            })
            .catch(error => {
                console.error('Error searching patients:', error);
            });
    } else {
        document.getElementById('patient_search_results').innerHTML = '<div class="text-muted">Enter at least 2 characters to search</div>';
    }
}

function selectPatient(patientId) {
    document.getElementById('patient_id').value = patientId;
    document.getElementById('patient_search').value = '';
    document.getElementById('patient_search_results').innerHTML = '';
}

function updateTreatment(recordId) {
    document.getElementById('triageRecordId').value = recordId;
    new bootstrap.Modal(document.getElementById('triageModal')).show();
}

function dischargePatient(recordId) {
    if (confirm('Discharge this patient from emergency?')) {
        window.location.href = 'discharge.php?id=' + recordId;
    }
}

function patientCare(recordId) {
    window.location.href = 'patient_care.php?record_id=' + recordId;
}

function updateTreatment(recordId) {
    window.location.href = 'update_treatment.php?id=' + recordId;
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

// Auto-refresh every 30 seconds for real-time updates
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'triage.php';
    }
});

// Initialize patient search when modal opens
document.getElementById('triageModal').addEventListener('shown', function() {
    document.getElementById('patient_search').focus();
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

.critical-row {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { background-color: rgba(220, 53, 69, 0.1); }
    50% { background-color: rgba(220, 53, 69, 0.2); }
    100% { background-color: rgba(220, 53, 69, 0.1); }
}

.urgent-row {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { background-color: rgba(255, 193, 7, 0.1); }
    50% { background-color: rgba(255, 193, 7, 0.2); }
    100% { background-color: rgba(255, 193, 7, 0.1); }
}
</style>

<?php include '../includes/footer.php'; ?>
