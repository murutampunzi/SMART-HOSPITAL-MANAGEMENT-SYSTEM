<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$page_title = "Compose Message - Smart Hospital Management System";
$page_heading = "Compose Message";

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $recipient_id = intval($_POST['recipient_id'] ?? 0);
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        
        // Validate required fields
        $required_fields = [
            'recipient_id' => $recipient_id,
            'subject' => $subject,
            'message' => $message
        ];
        
        $validation_errors = validateRequired($required_fields);
        if (!empty($validation_errors)) {
            $error = reset($validation_errors);
        } else {
            try {
                $sender_id = $_SESSION['user_id'];
                
                // Create message
                $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message) 
                        VALUES (?, ?, ?, ?)";
                
                $stmt = prepare($sql);
                $stmt->bind_param("iiss", $sender_id, $recipient_id, $subject, $message);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to send message');
                }
                
                // Log activity
                logActivity('message_sent', "Message sent to user ID: $recipient_id");
                
                // Set success message
                $success = "Message sent successfully!";
                
                // Redirect to messages index
                header('Refresh: 2; url=index.php');
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Get users for recipient dropdown (excluding current user)
$current_user_id = $_SESSION['user_id'];
$users = query("SELECT id, name, role FROM users WHERE id != $current_user_id AND status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Compose New Message</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFInput(); ?>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="recipient_id" class="form-label">To *</label>
                            <select class="form-select" id="recipient_id" name="recipient_id" required>
                                <option value="">Select Recipient</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Recipient is required</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Subject is required</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Type your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">Message is required</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Side Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Tips</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>All fields marked with * are required</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Select the recipient from the dropdown</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Use priority for urgent messages</li>
                    <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Messages are logged for audit purposes</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Recent Contacts</h6>
            </div>
            <div class="card-body">
                <?php
                $recent_contacts = query("SELECT DISTINCT m.recipient_id, u.name, u.role 
                                         FROM messages m 
                                         JOIN users u ON m.recipient_id = u.id 
                                         WHERE m.sender_id = $current_user_id 
                                         ORDER BY m.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($recent_contacts)): ?>
                    <p class="text-muted small">No recent contacts</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_contacts as $contact): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($contact['name']); ?></div>
                                        <small class="text-muted"><?php echo ucfirst($contact['role']); ?></small>
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

<script>
// Form validation
(function() {
    'use strict';
    
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
