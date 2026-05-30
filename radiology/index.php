<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor', 'lab_technician', 'radiologist']);

$page_title = "Radiology - Smart Hospital Management System";
$page_heading = "Radiology Department";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$modality = sanitizeInput($_GET['modality'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$priority = sanitizeInput($_GET['priority'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(rr.request_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR rt.name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 6);
}

if (!empty($modality)) {
    $where_conditions[] = "rt.modality = ?";
    $params[] = $modality;
    $types .= 's';
}

if (!empty($status)) {
    $where_conditions[] = "rr.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($priority)) {
    $where_conditions[] = "rr.priority = ?";
    $params[] = $priority;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "rr.requested_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "rr.requested_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM radiology_requests rr 
             JOIN patients p ON rr.patient_id = p.id 
             JOIN radiology_tests rt ON rr.test_id = rt.id 
             LEFT JOIN doctors d ON rr.doctor_id = d.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_requests = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_requests, ITEMS_PER_PAGE, $page);

// Get radiology requests
$sql = "SELECT rr.*, p.first_name, p.last_name, p.phone as patient_phone,
              rt.name as test_name, rt.modality, rt.description, rt.contrast_required,
              d.first_name as doctor_first_name, d.last_name as doctor_last_name,
              rri.image_path, rri.report_path, rri.radiologist_id, rri.report_date,
              u_rad.name as radiologist_name
       FROM radiology_requests rr 
       JOIN patients p ON rr.patient_id = p.id 
       JOIN radiology_tests rt ON rr.test_id = rt.id 
       LEFT JOIN doctors d ON rr.doctor_id = d.id 
       LEFT JOIN radiology_images rri ON rr.id = rri.request_id
       LEFT JOIN users u_rad ON rri.radiologist_id = u_rad.id
       $where_clause 
       ORDER BY rr.requested_date DESC, rr.priority DESC, rr.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get modalities for filter
$modalities = query("SELECT DISTINCT modality FROM radiology_tests WHERE modality IS NOT NULL ORDER BY modality")->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_requests'] = query("SELECT COUNT(*) as count FROM radiology_requests")->fetch_assoc()['count'];
$stats['pending'] = query("SELECT COUNT(*) as count FROM radiology_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['in_progress'] = query("SELECT COUNT(*) as count FROM radiology_requests WHERE status = 'in_progress'")->fetch_assoc()['count'];
$stats['completed'] = query("SELECT COUNT(*) as count FROM radiology_requests WHERE status = 'completed'")->fetch_assoc()['count'];
$stats['urgent'] = query("SELECT COUNT(*) as count FROM radiology_requests WHERE priority = 'urgent' AND status IN ('pending', 'in_progress')")->fetch_assoc()['count'];

// Get recent completed scans
$recent_completed = [];
$result = query("SELECT rr.request_id, rr.requested_date, p.first_name, p.last_name,
                      rt.name as test_name, rt.modality, rri.report_date
               FROM radiology_requests rr
               JOIN patients p ON rr.patient_id = p.id
               JOIN radiology_tests rt ON rr.test_id = rt.id
               JOIN radiology_images rri ON rr.id = rri.request_id
               WHERE rr.status = 'completed'
               ORDER BY rri.report_date DESC LIMIT 5");
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
                        <i class="fas fa-x-ray fa-2x"></i>
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
                <strong><?php echo $stats['urgent']; ?> urgent radiology request(s)</strong> pending attention!
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
                        <label for="modality" class="form-label">Modality</label>
                        <select class="form-select" id="modality" name="modality">
                            <option value="">All Modalities</option>
                            <?php foreach ($modalities as $mod): ?>
                                <option value="<?php echo htmlspecialchars($mod['modality']); ?>" 
                                        <?php echo $modality === $mod['modality'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mod['modality']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All Priority</option>
                            <option value="routine" <?php echo $priority === 'routine' ? 'selected' : ''; ?>>Routine</option>
                            <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            <option value="stat" <?php echo $priority === 'stat' ? 'selected' : ''; ?>>Stat</option>
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
                <h5 class="mb-0">Radiology Requests</h5>
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

<!-- Radiology Requests Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-x-ray fa-3x text-muted mb-3"></i>
                        <h5>No Radiology Requests Found</h5>
                        <p class="text-muted">No radiology requests match your search criteria.</p>
                        <?php if (hasRole('admin') || hasRole('doctor')): ?>
                            <a href="request.php" class="btn btn-primary">Create Radiology Request</a>
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
                                    <th>Images</th>
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
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($request['modality']); ?>
                                                <?php if ($request['contrast_required']): ?>
                                                    <span class="badge bg-info ms-1">Contrast</span>
                                                <?php endif; ?>
                                            </small>
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
                                            <?php if (isset($request['scheduled_date'])): ?>
                                                <small class="text-muted">Scheduled: <?php echo formatDate($request['scheduled_date']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $priority_colors = [
                                                'routine' => 'secondary',
                                                'urgent' => 'warning',
                                                'stat' => 'danger'
                                            ];
                                            $color = $priority_colors[$request['priority']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($request['priority']); ?></span>
                                        </td>
                                        <td><?php echo getStatusBadge($request['status']); ?></td>
                                        <td>
                                            <?php if ($request['image_path']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" title="View Images" 
                                                        onclick="viewImages(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-images"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No images</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if (hasRole('lab_technician') || hasRole('radiologist')): ?>
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-info" title="Schedule" 
                                                                onclick="scheduleScan(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-calendar"></i>
                                                        </button>
                                                    <?php elseif ($request['status'] === 'scheduled'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" title="Start Scan" 
                                                                onclick="startScan(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php elseif ($request['status'] === 'in_progress'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Upload Images" 
                                                                onclick="uploadImages(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-upload"></i>
                                                        </button>
                                                        <?php if (hasRole('radiologist')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning" title="Write Report" 
                                                                    onclick="writeReport(<?php echo $request['id']; ?>)">
                                                                <i class="fas fa-file-medical"></i>
                                                            </button>
                                                        <?php endif; ?>
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

<!-- Recent Completed Scans -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Completed Scans</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_completed)): ?>
                    <p class="text-muted text-center">No completed scans yet</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_completed as $scan): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($scan['test_name']); ?></div>
                                        <small class="text-muted">
                                            Patient: <?php echo htmlspecialchars($scan['first_name'] . ' ' . $scan['last_name']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($scan['modality']); ?> • 
                                            Request: <?php echo htmlspecialchars($scan['request_id']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success">Completed</div>
                                        <small class="text-muted"><?php echo timeAgo($scan['report_date']); ?></small>
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
                    
                    <?php if (hasRole('lab_technician') || hasRole('radiologist')): ?>
                        <div class="col-6">
                            <a href="schedule.php" class="btn btn-success w-100">
                                <i class="fas fa-calendar me-2"></i>Schedule
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="image_viewer.php" class="btn btn-info w-100">
                                <i class="fas fa-images me-2"></i>Image Viewer
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('radiologist')): ?>
                        <div class="col-6">
                            <a href="reports.php" class="btn btn-warning w-100">
                                <i class="fas fa-file-medical me-2"></i>Reports
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
                            <i class="fas fa-chart-bar me-2"></i>Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Radiology Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="imageContainer">
                    <!-- Images will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadImages()">Download All</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Radiology Scan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="schedule_scan.php">
                <?php echo getCSRFInput(); ?>
                <input type="hidden" id="scheduleRequestId" name="request_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label">Scheduled Date *</label>
                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="scheduled_time" class="form-label">Scheduled Time *</label>
                        <input type="time" class="form-control" id="scheduled_time" name="scheduled_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="technician_notes" class="form-label">Technician Notes</label>
                        <textarea class="form-control" id="technician_notes" name="technician_notes" rows="3" placeholder="Any special instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Scan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewImages(requestId) {
    // Load images via AJAX
    fetch(`api/get_images.php?request_id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('imageContainer');
                container.innerHTML = data.images.map(img => `
                    <div class="mb-3">
                        <img src="${img.path}" class="img-fluid rounded" alt="${img.description}">
                        <p class="mt-2">${img.description}</p>
                    </div>
                `).join('');
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            } else {
                alert('Error loading images: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading images');
        });
}

function scheduleScan(requestId) {
    document.getElementById('scheduleRequestId').value = requestId;
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function startScan(requestId) {
    if (confirm('Start the radiology scan for this request?')) {
        window.location.href = 'start_scan.php?id=' + requestId;
    }
}

function uploadImages(requestId) {
    window.location.href = 'upload_images.php?id=' + requestId;
}

function writeReport(requestId) {
    window.location.href = 'write_report.php?id=' + requestId;
}

function cancelRequest(requestId) {
    if (confirm('Cancel this radiology request?')) {
        window.location.href = 'cancel_request.php?id=' + requestId;
    }
}

function exportRequests() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

function printRequests() {
    window.print();
}

function downloadImages() {
    // Implement image download functionality
    alert('Download functionality would be implemented here');
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
