<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and admin role
requireLogin();
requireRole('admin');

$page_title = "Users Management - Smart Hospital Management System";
$page_heading = "Users Management";

$error = '';
$success = '';

// Handle POST actions (Create, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'delete') {
            $user_id = intval($_GET['id'] ?? 0);
            if ($user_id === intval($_SESSION['user_id'])) {
                $error = 'You cannot delete your own account.';
            } else {
                // Check if user exists
                $check = query("SELECT name, email FROM users WHERE id = $user_id")->fetch_assoc();
                if ($check) {
                    $delete = query("DELETE FROM users WHERE id = $user_id");
                    if ($delete) {
                        logActivity('delete_user', 'Deleted user: ' . $check['name'] . ' (' . $check['email'] . ')');
                        $success = 'User deleted successfully!';
                        header('Location: index.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to delete user. They may have related database records.';
                    }
                } else {
                    $error = 'User not found.';
                }
            }
        } elseif ($action === 'create') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitizeInput($_POST['role'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'active');
            
            // Validation
            if (empty($name) || empty($email) || empty($password) || empty($role)) {
                $error = 'Please fill in all required fields.';
            } elseif (!validateEmail($email)) {
                $error = 'Invalid email address.';
            } elseif (!validatePassword($password)) {
                $error = 'Password must be at least 8 characters long.';
            } elseif (!in_array($role, ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician', 'patient'])) {
                $error = 'Invalid user role selected.';
            } elseif (!in_array($status, ['active', 'inactive', 'suspended'])) {
                $error = 'Invalid status selected.';
            } else {
                // Check if email exists
                $check = prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error = 'Email address already exists.';
                } else {
                    $hashed_password = hashPassword($password);
                    $stmt = prepare("INSERT INTO users (name, email, password, role, phone, address, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param("sssssss", $name, $email, $hashed_password, $role, $phone, $address, $status);
                    
                    if ($stmt->execute()) {
                        $new_user_id = insertId();
                        if ($role === 'patient') {
                            $patient_id = 'PAT' . str_pad($new_user_id, 6, '0', STR_PAD_LEFT);
                            $name_parts = explode(' ', $name, 2);
                            $first_name = $name_parts[0];
                            $last_name = isset($name_parts[1]) ? $name_parts[1] : 'User';
                            $patient_stmt = prepare("INSERT INTO patients (user_id, patient_id, first_name, last_name, date_of_birth, gender, phone, email, address, status) VALUES (?, ?, ?, ?, '1990-01-01', 'other', ?, ?, ?, 'active')");
                            $patient_stmt->bind_param("isssssss", $new_user_id, $patient_id, $first_name, $last_name, $phone, $email, $address);
                            $patient_stmt->execute();
                        } elseif ($role === 'doctor') {
                            $doctor_id = 'DOC' . str_pad($new_user_id, 6, '0', STR_PAD_LEFT);
                            $name_parts = explode(' ', $name, 2);
                            $first_name = $name_parts[0];
                            $last_name = isset($name_parts[1]) ? $name_parts[1] : 'User';
                            $doctor_stmt = prepare("INSERT INTO doctors (user_id, doctor_id, first_name, last_name, specialization, phone, email, address, status, consultation_fee, consultation_hours) VALUES (?, ?, ?, ?, 'General Practice', ?, ?, ?, 'active', 50.00, '09:00 AM - 05:00 PM')");
                            $doctor_stmt->bind_param("isssssss", $new_user_id, $doctor_id, $first_name, $last_name, $phone, $email, $address);
                            $doctor_stmt->execute();
                        }
                        logActivity('create_user', 'Created user: ' . $name . ' (' . $email . ') as ' . $role);
                        $success = 'User created successfully!';
                        header('Location: index.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to create user. Please try again.';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $user_id = intval($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitizeInput($_POST['role'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'active');
            
            // Validation
            if (empty($name) || empty($email) || empty($role)) {
                $error = 'Please fill in all required fields.';
            } elseif (!validateEmail($email)) {
                $error = 'Invalid email address.';
            } elseif (!in_array($role, ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician', 'patient'])) {
                $error = 'Invalid user role selected.';
            } elseif (!in_array($status, ['active', 'inactive', 'suspended'])) {
                $error = 'Invalid status selected.';
            } else {
                // Check if email exists on another user
                $check = prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->bind_param("si", $email, $user_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error = 'Email address already exists on another user.';
                } else {
                    if (!empty($password)) {
                        if (!validatePassword($password)) {
                            $error = 'Password must be at least 8 characters long.';
                        } else {
                            $hashed_password = hashPassword($password);
                            $stmt = prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, phone = ?, address = ?, status = ? WHERE id = ?");
                            $stmt->bind_param("sssssssi", $name, $email, $hashed_password, $role, $phone, $address, $status, $user_id);
                        }
                    } else {
                        $stmt = prepare("UPDATE users SET name = ?, email = ?, role = ?, phone = ?, address = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("ssssssi", $name, $email, $role, $phone, $address, $status, $user_id);
                    }
                    
                    if (!isset($error) || $error === '') {
                        if ($stmt->execute()) {
                            if ($role === 'patient') {
                                $check = query("SELECT id FROM patients WHERE user_id = $user_id");
                                if (numRows($check) == 0) {
                                    $patient_id = 'PAT' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                                    $name_parts = explode(' ', $name, 2);
                                    $first_name = $name_parts[0];
                                    $last_name = isset($name_parts[1]) ? $name_parts[1] : 'User';
                                    $patient_stmt = prepare("INSERT INTO patients (user_id, patient_id, first_name, last_name, date_of_birth, gender, phone, email, address, status) VALUES (?, ?, ?, ?, '1990-01-01', 'other', ?, ?, ?, 'active')");
                                    $patient_stmt->bind_param("isssssss", $user_id, $patient_id, $first_name, $last_name, $phone, $email, $address);
                                    $patient_stmt->execute();
                                }
                            } elseif ($role === 'doctor') {
                                $check = query("SELECT id FROM doctors WHERE user_id = $user_id");
                                if (numRows($check) == 0) {
                                    $doctor_id = 'DOC' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                                    $name_parts = explode(' ', $name, 2);
                                    $first_name = $name_parts[0];
                                    $last_name = isset($name_parts[1]) ? $name_parts[1] : 'User';
                                    $doctor_stmt = prepare("INSERT INTO doctors (user_id, doctor_id, first_name, last_name, specialization, phone, email, address, status, consultation_fee, consultation_hours) VALUES (?, ?, ?, ?, 'General Practice', ?, ?, ?, 'active', 50.00, '09:00 AM - 05:00 PM')");
                                    $doctor_stmt->bind_param("isssssss", $user_id, $doctor_id, $first_name, $last_name, $phone, $email, $address);
                                    $doctor_stmt->execute();
                                }
                            }
                            logActivity('edit_user', 'Updated user: ' . $name . ' (' . $email . ')');
                            $success = 'User updated successfully!';
                            header('Location: index.php?success=' . urlencode($success));
                            exit();
                        } else {
                            $error = 'Failed to update user. Please try again.';
                        }
                    }
                }
            }
        }
    }
}

// Read alert messages from GET
if (isset($_GET['success'])) {
    $success = sanitizeInput($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = sanitizeInput($_GET['error']);
}

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_users, ITEMS_PER_PAGE, $page);

// Get users
$sql = "SELECT id, name, email, role, phone, address, status, last_login, created_at FROM users $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Users</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, email, phone...">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="doctor" <?php echo $role_filter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="nurse" <?php echo $role_filter === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                            <option value="receptionist" <?php echo $role_filter === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                            <option value="pharmacist" <?php echo $role_filter === 'pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                            <option value="lab_technician" <?php echo $role_filter === 'lab_technician' ? 'selected' : ''; ?>>Lab Technician</option>
                            <option value="patient" <?php echo $role_filter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i>Filter
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

<!-- Actions Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Users Directory</h5>
                <small class="text-muted">Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_users); ?> of <?php echo $total_users; ?> users</small>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-user-plus me-2"></i>Add New User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Users List -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No Users Found</h5>
                        <p class="text-muted">Try adjusting your filters or search terms.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Registered</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" 
                                                         style="width: 42px; height: 42px;">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $roleColors = [
                                                'admin' => 'danger',
                                                'doctor' => 'primary',
                                                'nurse' => 'info',
                                                'receptionist' => 'success',
                                                'pharmacist' => 'warning',
                                                'lab_technician' => 'secondary',
                                                'patient' => 'dark'
                                            ];
                                            $roleColor = $roleColors[$user['role']] ?? 'primary';
                                            ?>
                                            <span class="badge bg-<?php echo $roleColor; ?> text-uppercase" style="font-size: 0.75rem;">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-phone fa-xs text-muted me-2"></i><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></div>
                                            <?php if ($user['address']): ?>
                                                <div class="text-muted small"><i class="fas fa-map-marker-alt fa-xs me-2"></i><?php echo truncateText($user['address'], 30); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusColors = [
                                                'active' => 'success',
                                                'inactive' => 'secondary',
                                                'suspended' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$user['status']] ?? 'success';
                                            ?>
                                            <span class="badge bg-<?php echo $statusColor; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo formatDate($user['created_at']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit" 
                                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (intval($user['id']) !== intval($_SESSION['user_id'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" 
                                                            onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Delete Own Account" disabled>
                                                        <i class="fas fa-ban"></i>
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

<!-- Create User Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php" method="POST" class="needs-validation" novalidate>
                <?php echo getCSRFInput(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please enter full name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Please enter a valid email.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        <div class="invalid-feedback">Password must be at least 8 characters.</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role_select" class="form-label">Role *</label>
                            <select class="form-select" id="role_select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="nurse">Nurse</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="pharmacist">Pharmacist</option>
                                <option value="lab_technician">Lab Technician</option>
                                <option value="patient">Patient</option>
                            </select>
                            <div class="invalid-feedback">Please select a role.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="status_select" class="form-label">Status *</label>
                            <select class="form-select" id="status_select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="phone_input" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone_input" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="address_input" class="form-label">Address</label>
                        <textarea class="form-control" id="address_input" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2 text-primary"></i>Edit User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php" method="POST" class="needs-validation" novalidate>
                <?php echo getCSRFInput(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">Please enter full name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                        <div class="invalid-feedback">Please enter a valid email.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password" minlength="8">
                        <div class="invalid-feedback">Password must be at least 8 characters.</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_role" class="form-label">Role *</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="nurse">Nurse</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="pharmacist">Pharmacist</option>
                                <option value="lab_technician">Lab Technician</option>
                                <option value="patient">Patient</option>
                            </select>
                            <div class="invalid-feedback">Please select a role.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete user <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger small mb-0"><i class="fas fa-info-circle me-1"></i>This action cannot be undone. All activity records for this user will remain for audit logging.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <?php echo getCSRFInput(); ?>
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_address').value = user.address || '';
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_password').value = '';
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function confirmDelete(userId, userName) {
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteForm').action = 'index.php?id=' + userId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Bootstrap custom form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include '../includes/footer.php'; ?>
