<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$page_title = "My Profile - Smart Hospital Management System";
$page_heading = "My Profile";

$user_id = $_SESSION['user_id'];

// Fetch user details
$user_stmt = prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User details not found.");
}

$role_details = null;
// Fetch role-specific details
if ($user['role'] === 'patient') {
    $patient_stmt = prepare("SELECT * FROM patients WHERE user_id = ?");
    $patient_stmt->bind_param("i", $user_id);
    $patient_stmt->execute();
    $role_details = $patient_stmt->get_result()->fetch_assoc();
} elseif ($user['role'] === 'doctor') {
    $doctor_stmt = prepare("SELECT * FROM doctors WHERE user_id = ?");
    $doctor_stmt->bind_param("i", $user_id);
    $doctor_stmt->execute();
    $role_details = $doctor_stmt->get_result()->fetch_assoc();
}

// Fetch recent activity logs
$activity_logs = [];
$log_stmt = prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$log_stmt->bind_param("i", $user_id);
$log_stmt->execute();
$log_result = $log_stmt->get_result();
while ($row = $log_result->fetch_assoc()) {
    $activity_logs[] = $row;
}

include 'includes/header.php';
?>

<div class="row">
    <!-- Left Column: User Summary Card -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 text-center h-100">
            <div class="card-body p-5 d-flex flex-column align-items-center justify-content-center">
                <!-- Large Initials Avatar -->
                <div class="avatar-circle bg-primary-gradient text-white rounded-circle d-flex align-items-center justify-content-center mb-4 shadow" 
                     style="width: 110px; height: 110px; font-size: 2.2rem; font-weight: 700; border: 4px solid var(--border-color);">
                    <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                </div>
                
                <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($user['name']); ?></h4>
                <p class="text-muted mb-3"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                
                <div class="mb-4">
                    <span class="badge bg-primary text-capitalize px-3 py-2 fs-6">
                        <i class="fas fa-user-shield me-2"></i><?php echo htmlspecialchars($user['role']); ?>
                    </span>
                    <span class="badge bg-success px-3 py-2 fs-6 ms-2">
                        <span class="status-indicator online me-1" style="width: 6px; height: 6px;"></span>Active
                    </span>
                </div>
                
                <div class="border-top pt-4 w-100">
                    <div class="row text-start text-muted small">
                        <div class="col-6 mb-2"><strong>Phone:</strong></div>
                        <div class="col-6 mb-2 text-dark text-end"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></div>
                        
                        <div class="col-6 mb-2"><strong>Joined Date:</strong></div>
                        <div class="col-6 mb-2 text-dark text-end"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                        
                        <div class="col-6"><strong>Last Login:</strong></div>
                        <div class="col-6 text-dark text-end">
                            <?php echo $user['last_login'] ? date('M j, g:i A', strtotime($user['last_login'])) : 'First login session'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5 w-100">
                    <a href="settings/account.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile Details
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Details & Activities -->
    <div class="col-lg-8 mb-4">
        <!-- Details Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fas fa-id-card text-primary me-2"></i>Personal & Role Information
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6 border-bottom pb-3">
                        <label class="form-label text-muted mb-1 d-block">Full Name</label>
                        <span class="fw-semibold text-dark fs-5"><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    
                    <div class="col-md-6 border-bottom pb-3">
                        <label class="form-label text-muted mb-1 d-block">Email Address</label>
                        <span class="fw-semibold text-dark fs-5"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>

                    <div class="col-12 border-bottom pb-3">
                        <label class="form-label text-muted mb-1 d-block">Mailing Address</label>
                        <span class="fw-semibold text-dark fs-6"><?php echo nl2br(htmlspecialchars($user['address'] ?? 'No address registered.')); ?></span>
                    </div>
                    
                    <!-- Doctor Role-Specific Metadata -->
                    <?php if ($user['role'] === 'doctor' && $role_details): ?>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Specialization</label>
                            <span class="badge bg-info text-capitalize fs-6 px-3 py-2"><?php echo htmlspecialchars($role_details['specialization']); ?></span>
                        </div>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">License Number</label>
                            <span class="fw-semibold text-dark fs-6"><?php echo htmlspecialchars($role_details['license_number'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Consultation Fee</label>
                            <span class="fw-semibold text-success fs-5"><?php echo formatCurrency($role_details['consultation_fee']); ?></span>
                        </div>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Consultation Hours</label>
                            <span class="fw-semibold text-dark fs-6"><?php echo htmlspecialchars($role_details['consultation_hours'] ?? 'N/A'); ?></span>
                        </div>
                    
                    <!-- Patient Role-Specific Metadata -->
                    <?php elseif ($user['role'] === 'patient' && $role_details): ?>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Blood Group</label>
                            <span class="badge bg-danger fs-6 px-3 py-2"><?php echo htmlspecialchars($role_details['blood_group'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Date of Birth</label>
                            <span class="fw-semibold text-dark fs-6"><?php echo date('M j, Y', strtotime($role_details['date_of_birth'])); ?></span>
                        </div>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Emergency Contact Name</label>
                            <span class="fw-semibold text-dark fs-6"><?php echo htmlspecialchars($role_details['emergency_contact_name'] ?? 'None'); ?></span>
                        </div>
                        <div class="col-md-6 border-bottom pb-3">
                            <label class="form-label text-muted mb-1 d-block">Emergency Contact Phone</label>
                            <span class="fw-semibold text-dark fs-6"><?php echo htmlspecialchars($role_details['emergency_contact_phone'] ?? 'None'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Activity History Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fas fa-history text-primary me-2"></i>Recent Activity History
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($activity_logs)): ?>
                    <p class="text-muted text-center py-3">No activity logs recorded for this account.</p>
                <?php else: ?>
                    <div class="activity-feed">
                        <?php foreach ($activity_logs as $log): ?>
                            <div class="d-flex align-items-start mb-3 border-bottom pb-3">
                                <div class="avatar-circle rounded-circle bg-light text-primary d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 38px; height: 38px;">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 fw-semibold text-dark text-capitalize">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                                        </h6>
                                        <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo timeAgo($log['created_at']); ?></small>
                                    </div>
                                    <p class="mb-0 text-muted small"><?php echo htmlspecialchars($log['details']); ?></p>
                                    <small class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-desktop me-1"></i>IP: <?php echo htmlspecialchars($log['ip_address'] ?? 'unknown'); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
