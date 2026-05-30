<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and admin role
requireLogin();
requireRole('admin');

$page_title = "System Settings - Smart Hospital Management System";
$page_heading = "System Settings";

$error = '';
$success = '';

// Handle Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Collect inputs
        $keys = [
            'hospital_name',
            'hospital_address',
            'hospital_phone',
            'hospital_email',
            'appointment_duration',
            'appointment_advance_booking',
            'currency',
            'tax_rate',
            'email_notifications',
            'sms_notifications',
            'auto_backup',
            'backup_frequency',
            'session_timeout',
            'max_file_size',
            'maintenance_mode',
            'timezone'
        ];
        
        try {
            $conn->begin_transaction();
            
            foreach ($keys as $key) {
                // Determine value (booleans are handled differently because of checkboxes)
                if (in_array($key, ['email_notifications', 'sms_notifications', 'auto_backup', 'maintenance_mode'])) {
                    $val = isset($_POST[$key]) ? '1' : '0';
                } else {
                    $val = sanitizeInput($_POST[$key] ?? '');
                }
                
                // Extra validations
                if ($key === 'hospital_name' && empty($val)) {
                    throw new Exception('Hospital name is required.');
                }
                if ($key === 'hospital_email' && !empty($val) && !validateEmail($val)) {
                    throw new Exception('Invalid hospital email address.');
                }
                
                // Update key in database
                $stmt = prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param("ss", $val, $key);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update setting: $key");
                }
            }
            
            $conn->commit();
            logActivity('update_settings', 'System settings updated');
            $success = 'System settings updated successfully!';
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Fetch Current Settings
$settings = [];
$result = query("SELECT setting_key, setting_value FROM system_settings");
while ($row = fetchAssoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

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

<!-- Settings Container -->
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-cogs fa-2x text-primary me-3"></i>
                    <div>
                        <h5 class="card-title mb-0 fw-bold">Configure Hospital Settings</h5>
                        <small class="text-muted">Manage system preferences, modules, notifications, and parameters.</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form action="system.php" method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <div class="row g-0">
                        <!-- Navigation Tabs (Left Sidebar) -->
                        <div class="col-md-3 bg-light border-end py-3">
                            <div class="nav flex-column nav-pills px-3" id="settingsTabs" role="tablist">
                                <button class="nav-link active text-start py-3 mb-2" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                                    <i class="fas fa-hospital me-2"></i>General Info
                                </button>
                                <button class="nav-link text-start py-3 mb-2" id="appointments-tab" data-bs-toggle="pill" data-bs-target="#appointments" type="button" role="tab">
                                    <i class="fas fa-calendar-alt me-2"></i>Appointments
                                </button>
                                <button class="nav-link text-start py-3 mb-2" id="financials-tab" data-bs-toggle="pill" data-bs-target="#financials" type="button" role="tab">
                                    <i class="fas fa-file-invoice-dollar me-2"></i>Financials & Tax
                                </button>
                                <button class="nav-link text-start py-3 mb-2" id="features-tab" data-bs-toggle="pill" data-bs-target="#features" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>Security & Limits
                                </button>
                                <button class="nav-link text-start py-3 mb-2" id="notifications-tab" data-bs-toggle="pill" data-bs-target="#notifications" type="button" role="tab">
                                    <i class="fas fa-bell me-2"></i>Notifications & Backups
                                </button>
                            </div>
                        </div>
                        
                        <!-- Content Panel (Right Panel) -->
                        <div class="col-md-9 p-4">
                            <div class="tab-content" id="settingsTabsContent">
                                <!-- 1. General Info -->
                                <div class="tab-pane fade show active" id="general" role="tabpanel">
                                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-hospital text-primary me-2"></i>Hospital Identity</h5>
                                    
                                    <div class="mb-3">
                                        <label for="hospital_name" class="form-label">Hospital Name *</label>
                                        <input type="text" class="form-control form-control-lg" id="hospital_name" name="hospital_name" 
                                               value="<?php echo htmlspecialchars($settings['hospital_name'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter the hospital name.</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="hospital_phone" class="form-label">Hospital Phone Number</label>
                                            <input type="text" class="form-control" id="hospital_phone" name="hospital_phone" 
                                                   value="<?php echo htmlspecialchars($settings['hospital_phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="hospital_email" class="form-label">Hospital Email Address</label>
                                            <input type="email" class="form-control" id="hospital_email" name="hospital_email" 
                                                   value="<?php echo htmlspecialchars($settings['hospital_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="hospital_address" class="form-label">Hospital Address</label>
                                        <textarea class="form-control" id="hospital_address" name="hospital_address" rows="3"><?php echo htmlspecialchars($settings['hospital_address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3 col-md-6">
                                        <label for="timezone" class="form-label">System Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option value="UTC" <?php echo ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC (Coordinated Universal Time)</option>
                                            <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (New York)</option>
                                            <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Greenwich Mean Time (London)</option>
                                            <option value="Africa/Lagos" <?php echo ($settings['timezone'] ?? '') === 'Africa/Lagos' ? 'selected' : ''; ?>>West Africa Time (Lagos)</option>
                                            <option value="Asia/Kolkata" <?php echo ($settings['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Indian Standard Time (Kolkata)</option>
                                            <option value="Asia/Singapore" <?php echo ($settings['timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : ''; ?>>Singapore Time</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- 2. Appointments -->
                                <div class="tab-pane fade" id="appointments" role="tabpanel">
                                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-calendar-alt text-primary me-2"></i>Appointment Scheduling</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_duration" class="form-label">Default Consultation Duration (Minutes)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="appointment_duration" name="appointment_duration" 
                                                       value="<?php echo htmlspecialchars($settings['appointment_duration'] ?? '30'); ?>" min="5" max="180">
                                                <span class="input-group-text">minutes</span>
                                            </div>
                                            <small class="text-muted">Average duration allotted per doctor appointment.</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_advance_booking" class="form-label">Max Advance Booking Period (Days)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="appointment_advance_booking" name="appointment_advance_booking" 
                                                       value="<?php echo htmlspecialchars($settings['appointment_advance_booking'] ?? '7'); ?>" min="1" max="365">
                                                <span class="input-group-text">days</span>
                                            </div>
                                            <small class="text-muted">Maximum number of days in advance patients can book.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 3. Financials -->
                                <div class="tab-pane fade" id="financials" role="tabpanel">
                                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Financial & Billing Settings</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="currency" class="form-label">Currency Symbol / Code</label>
                                            <input type="text" class="form-control" id="currency" name="currency" 
                                                   value="<?php echo htmlspecialchars($settings['currency'] ?? 'USD'); ?>">
                                            <small class="text-muted">e.g. $, USD, €, £</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="tax_rate" class="form-label">Default Invoice Tax Rate (%)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                                       value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '0'); ?>" min="0" max="50" step="0.01">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">Tax automatically added to billing invoices.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 4. Security & Limits -->
                                <div class="tab-pane fade" id="features" role="tabpanel">
                                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-shield-alt text-primary me-2"></i>Security & File System Limits</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="session_timeout" class="form-label">User Session Timeout (Minutes)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                       value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>" min="5" max="1440">
                                                <span class="input-group-text">minutes</span>
                                            </div>
                                            <small class="text-muted">Inactivity time before an automatic session logout.</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="max_file_size" class="form-label">Maximum Upload File Size (MB)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                                       value="<?php echo htmlspecialchars($settings['max_file_size'] ?? '5'); ?>" min="1" max="100">
                                                <span class="input-group-text">MB</span>
                                            </div>
                                            <small class="text-muted">Max size for patient uploads, profiles, or radiology scans.</small>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h6 class="fw-bold text-danger mb-3">Critical Action</h6>
                                    <div class="p-3 bg-light rounded border border-danger-subtle d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="fw-bold text-danger">System Maintenance Mode</div>
                                            <small class="text-muted">Under maintenance mode, only administrators can access the system. Others are shown a maintenance screen.</small>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" 
                                                   <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 5. Notifications & Backups -->
                                <div class="tab-pane fade" id="notifications" role="tabpanel">
                                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-bell text-primary me-2"></i>Notifications & Auto Backups</h5>
                                    
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-muted mb-3">Alert Modules</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="email_notifications" name="email_notifications" 
                                                   <?php echo ($settings['email_notifications'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="email_notifications">Enable Email Notifications</label>
                                            <div class="text-muted small">Sends automated emails for appointments, bills, and test requests.</div>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="sms_notifications" name="sms_notifications" 
                                                   <?php echo ($settings['sms_notifications'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="sms_notifications">Enable SMS Alerts</label>
                                            <div class="text-muted small">Sends quick notification text alerts directly to doctor and patient mobile numbers.</div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <div>
                                        <h6 class="fw-bold text-muted mb-3">Database Auto-Backups</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="auto_backup" name="auto_backup" 
                                                   <?php echo ($settings['auto_backup'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="auto_backup">Enable Automated Database Backup</label>
                                            <div class="text-muted small">Regularly backs up entire system database tables automatically.</div>
                                        </div>
                                        
                                        <div class="mb-3 col-md-6" id="backup_freq_container">
                                            <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                            <select class="form-select" id="backup_frequency" name="backup_frequency">
                                                <option value="daily" <?php echo ($settings['backup_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo ($settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo ($settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Panel -->
                    <div class="card-footer bg-light text-end p-3 border-top">
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="fas fa-save me-2"></i>Save System Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle backup frequency select visibility based on switch
const autoBackupSwitch = document.getElementById('auto_backup');
const backupFreqContainer = document.getElementById('backup_freq_container');

function toggleBackupFreq() {
    if (autoBackupSwitch.checked) {
        backupFreqContainer.style.display = 'block';
    } else {
        backupFreqContainer.style.display = 'none';
    }
}

autoBackupSwitch.addEventListener('change', toggleBackupFreq);
toggleBackupFreq(); // Run initially

// Bootstrap validation styling
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
