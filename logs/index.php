<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and admin role
requireLogin();
requireRole('admin');

$page_title = "Activity Logs - Smart Hospital Management System";
$page_heading = "Activity Logs";

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$action_filter = sanitizeInput($_GET['action_filter'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR al.action LIKE ? OR al.details LIKE ? OR al.ip_address LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if (!empty($action_filter)) {
    $where_conditions[] = "al.action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_logs = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_logs, ITEMS_PER_PAGE, $page);

// Get logs
$sql = "SELECT al.*, u.name as user_name, u.email as user_email, u.role as user_role 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        $where_clause 
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique actions for filter
$actions_result = query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
$actions = [];
while ($row = fetchAssoc($actions_result)) {
    $actions[] = $row['action'];
}

include '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label for="search" class="form-label">Search Activity Logs</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by user, action, details, IP...">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="action_filter" class="form-label">Filter Action</label>
                        <select class="form-select" id="action_filter" name="action_filter">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action_filter === $act ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $act))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i>Filter Logs
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Logs Directory -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-history text-primary me-2"></i>System Audit Trail</h5>
                        <small class="text-muted">View all administrative actions and security-sensitive events.</small>
                    </div>
                    <span class="badge bg-secondary text-uppercase py-2 px-3">Total Logs: <?php echo $total_logs; ?></span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5>No Activity Logs Found</h5>
                        <p class="text-muted">No system activity matches your filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <?php if ($log['user_name']): ?>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                                <small class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 600;">
                                                    <?php echo htmlspecialchars($log['user_role']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">System (Cron/Auto)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $actionColors = [
                                                'login' => 'success',
                                                'logout' => 'secondary',
                                                'create_user' => 'primary',
                                                'delete_user' => 'danger',
                                                'edit_user' => 'warning',
                                                'update_settings' => 'dark',
                                                'doctor_created' => 'primary',
                                                'doctor_updated' => 'warning',
                                            ];
                                            $badgeColor = $actionColors[$log['action']] ?? 'info';
                                            ?>
                                            <span class="badge bg-<?php echo $badgeColor; ?> text-uppercase">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-wrap" style="max-width: 350px;">
                                            <div class="small"><?php echo htmlspecialchars($log['details']); ?></div>
                                            <?php if ($log['user_agent']): ?>
                                                <small class="text-muted d-block text-truncate mt-1" title="<?php echo htmlspecialchars($log['user_agent']); ?>" style="max-width: 350px; font-size: 0.75rem;">
                                                    <i class="fas fa-laptop me-1"></i><?php echo htmlspecialchars($log['user_agent']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="font-monospace text-muted">
                                                <i class="fas fa-network-wired me-1"></i><?php echo htmlspecialchars($log['ip_address'] ?: 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div><i class="far fa-clock me-1 text-muted"></i><?php echo formatDateTime($log['created_at']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo($log['created_at']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="d-flex justify-content-between align-items-center p-4">
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

<?php include '../includes/footer.php'; ?>
